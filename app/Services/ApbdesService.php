<?php

namespace App\Services;

use App\Enums\JenisDokumenApbdes;
use App\Enums\KelompokPosApbdes;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
use App\Models\ProyekPembangunan;
use App\Models\User;
use App\Support\ApbdesPosCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApbdesService
{
    /**
     * Inisialisasi atau ambil dokumen APBDes Murni pada Tahun Anggaran tertentu beserta 14 pos baku.
     */
    public function initializeMurni(
        int $tahunAnggaran,
        ?int $createdBy = null,
        ?string $nomorPerdes = null,
        ?string $tanggalPenetapan = null
    ): Apbdes {
        return DB::transaction(function () use ($tahunAnggaran, $createdBy, $nomorPerdes, $tanggalPenetapan) {
            $actorId = $createdBy ?? auth()->id() ?? User::query()->value('id');

            $perubahanExists = Apbdes::query()
                ->where('tahun_anggaran', $tahunAnggaran)
                ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
                ->exists();

            $murni = Apbdes::query()
                ->where('tahun_anggaran', $tahunAnggaran)
                ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
                ->first();

            if ($murni === null) {
                $murni = Apbdes::create([
                    'tahun_anggaran' => $tahunAnggaran,
                    'jenis_dokumen' => JenisDokumenApbdes::Murni,
                    'is_aktif_publik' => ! $perubahanExists,
                    'is_terkunci' => $perubahanExists,
                    'nomor_perdes' => $nomorPerdes,
                    'tanggal_penetapan' => $tanggalPenetapan,
                    'created_by' => $actorId,
                ]);
            } elseif (! $murni->is_terkunci && ($nomorPerdes !== null || $tanggalPenetapan !== null)) {
                $murni->update(array_filter([
                    'nomor_perdes' => $nomorPerdes,
                    'tanggal_penetapan' => $tanggalPenetapan,
                ], fn ($val) => $val !== null));
            }

            $this->ensureFourteenStandardRows($murni);

            if (! $murni->is_terkunci) {
                $this->applyProjectTotalsToDocument($murni);
            }

            return $murni->load('rincian');
        });
    }

    /**
     * Mekanisme Clone & Freeze:
     * Buat dokumen APBDes Perubahan pada tahun T dengan menyalin seluruh pagu & realisasi dari Murni tahun T,
     * lalu kunci dokumen Murni (is_terkunci = true, is_aktif_publik = false) dan aktifkan Perubahan (is_aktif_publik = true).
     */
    public function createPerubahanCloneAndFreeze(
        int $tahunAnggaran,
        ?int $createdBy = null,
        ?string $nomorPerdes = null,
        ?string $tanggalPenetapan = null
    ): Apbdes {
        return DB::transaction(function () use ($tahunAnggaran, $createdBy, $nomorPerdes, $tanggalPenetapan) {
            $existingPerubahan = Apbdes::query()
                ->where('tahun_anggaran', $tahunAnggaran)
                ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
                ->first();

            if ($existingPerubahan !== null) {
                throw ValidationException::withMessages([
                    'jenis_dokumen' => "Dokumen APBDes Perubahan untuk Tahun Anggaran {$tahunAnggaran} sudah pernah dibuat.",
                ]);
            }

            $actorId = $createdBy ?? auth()->id() ?? User::query()->value('id');
            $murni = $this->initializeMurni($tahunAnggaran, $actorId);
            $murni->load('rincian');

            // Buat header dokumen Perubahan
            $perubahan = Apbdes::create([
                'tahun_anggaran' => $tahunAnggaran,
                'jenis_dokumen' => JenisDokumenApbdes::Perubahan,
                'is_aktif_publik' => true,
                'is_terkunci' => false,
                'nomor_perdes' => $nomorPerdes,
                'tanggal_penetapan' => $tanggalPenetapan,
                'created_by' => $actorId,
            ]);

            // Salin 14 baris rincian dari Murni ke Perubahan (Clone)
            foreach ($murni->rincian as $row) {
                $kelompokVal = $row->kelompok instanceof KelompokPosApbdes
                    ? $row->kelompok->value
                    : (string) $row->kelompok;

                ApbdesRincian::create([
                    'apbdes_id' => $perubahan->id,
                    'kelompok' => $kelompokVal,
                    'kode_pos' => $row->kode_pos,
                    'nama_pos' => $row->nama_pos,
                    'is_otomatis_modul_1' => (bool) $row->is_otomatis_modul_1,
                    'pagu_anggaran' => (int) $row->pagu_anggaran,
                    'realisasi_tahap_1' => (int) $row->realisasi_tahap_1,
                    'realisasi_tahap_2' => (int) $row->realisasi_tahap_2,
                    'realisasi_tahap_3' => (int) $row->realisasi_tahap_3,
                    'total_realisasi' => (int) $row->total_realisasi,
                    'urutan' => (int) $row->urutan,
                ]);
            }

            // Bekukan dokumen Murni (Freeze)
            $murni->update([
                'is_terkunci' => true,
                'is_aktif_publik' => false,
            ]);

            // Pastikan pos bidang_2_fisik pada Perubahan tersinkron dengan Proyek Modul 1 tahun T
            $this->applyProjectTotalsToDocument($perubahan);

            return $perubahan->fresh(['rincian']);
        });
    }

    /**
     * Perbarui metadata header dokumen APBDes (Nomor Perdes & Tanggal Penetapan).
     * Ditolak apabila dokumen berstatus is_terkunci = true.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateMetadata(Apbdes $apbdes, array $data): Apbdes
    {
        $this->assertNotLocked($apbdes);

        $apbdes->update([
            'nomor_perdes' => $data['nomor_perdes'] ?? $apbdes->nomor_perdes,
            'tanggal_penetapan' => $data['tanggal_penetapan'] ?? $apbdes->tanggal_penetapan,
        ]);

        return $apbdes->fresh(['rincian']);
    }

    /**
     * Perbarui 1 baris pos rincian APBDes secara langsung.
     * Menolak jika dokumen terkunci atau jika baris adalah bidang_2_fisik (is_otomatis_modul_1 = true).
     *
     * @param  array<string, mixed>  $values
     */
    public function updateSingleRincian(ApbdesRincian $rincian, array $values): ApbdesRincian
    {
        $apbdes = $rincian->apbdes;
        $this->assertNotLocked($apbdes);

        if ($rincian->is_otomatis_modul_1 || $rincian->kode_pos === ApbdesPosCatalog::KODE_BIDANG_2_FISIK) {
            throw ValidationException::withMessages([
                'bidang_2_fisik' => 'Pos Pelaksanaan Pembangunan Desa - Komponen Fisik (Bidang 2) dihitung otomatis dari Modul 1 Proyek Fisik dan dikunci dari input manual.',
            ]);
        }

        return DB::transaction(function () use ($rincian, $values) {
            $validated = $this->validateAndComputeRowTotals($rincian, $values);
            $rincian->update($validated);

            return $rincian->fresh();
        });
    }

    /**
     * Perbarui banyak baris rincian pos APBDes sekaligus (batch update dari halaman /panel/apbdes).
     *
     * @param  array<int|string, array<string, mixed>>  $items
     */
    public function updateBatchRincian(Apbdes $apbdes, array $items): Apbdes
    {
        $this->assertNotLocked($apbdes);

        return DB::transaction(function () use ($apbdes, $items) {
            $apbdes->load('rincian');
            $rowsByKode = $apbdes->rincian->keyBy('kode_pos');
            $rowsById = $apbdes->rincian->keyBy('id');

            foreach ($items as $key => $itemData) {
                if (! is_array($itemData)) {
                    continue;
                }

                $kodePos = $itemData['kode_pos'] ?? (is_string($key) ? $key : null);
                $rowId = $itemData['id'] ?? (is_numeric($key) ? (int) $key : null);

                /** @var ?ApbdesRincian $row */
                $row = ($kodePos && $rowsByKode->has($kodePos))
                    ? $rowsByKode->get($kodePos)
                    : ($rowId && $rowsById->has($rowId) ? $rowsById->get($rowId) : null);

                if ($row === null) {
                    continue;
                }

                // Cek apakah baris adalah bidang_2_fisik (is_otomatis_modul_1 = true)
                if ($row->is_otomatis_modul_1 || $row->kode_pos === ApbdesPosCatalog::KODE_BIDANG_2_FISIK) {
                    $incomingPagu = isset($itemData['pagu_anggaran']) ? (int) $itemData['pagu_anggaran'] : (int) $row->pagu_anggaran;
                    $incomingT1 = isset($itemData['realisasi_tahap_1']) ? (int) $itemData['realisasi_tahap_1'] : (int) $row->realisasi_tahap_1;
                    $incomingT2 = isset($itemData['realisasi_tahap_2']) ? (int) $itemData['realisasi_tahap_2'] : (int) $row->realisasi_tahap_2;
                    $incomingT3 = isset($itemData['realisasi_tahap_3']) ? (int) $itemData['realisasi_tahap_3'] : (int) $row->realisasi_tahap_3;

                    $isModified = $incomingPagu !== (int) $row->pagu_anggaran
                        || $incomingT1 !== (int) $row->realisasi_tahap_1
                        || $incomingT2 !== (int) $row->realisasi_tahap_2
                        || $incomingT3 !== (int) $row->realisasi_tahap_3;

                    // Jika dipanggil khusus hanya untuk 1 baris bidang_2_fisik atau nilainya diubah manual, tolak tegas!
                    if ($isModified || count($items) === 1) {
                        throw ValidationException::withMessages([
                            'bidang_2_fisik' => 'Pos Pelaksanaan Pembangunan Desa - Komponen Fisik (Bidang 2) dihitung otomatis dari Modul 1 Proyek Fisik dan dikunci dari input manual.',
                        ]);
                    }

                    continue;
                }

                $validated = $this->validateAndComputeRowTotals($row, $itemData);
                $row->update($validated);
            }

            return $apbdes->fresh(['rincian']);
        });
    }

    /**
     * Ambil dokumen APBDes sasaran sinkronisasi Modul 1 pada tahun T:
     * Mengarah ke dokumen Perubahan bila sudah ada, atau ke Murni bila belum ada Perubahan
     * (termasuk menginisialisasi otomatis dokumen Murni beserta 14 pos baku bila tahun T belum ada).
     */
    public function getTargetDocumentForSync(int $tahunAnggaran, ?int $actorId = null): Apbdes
    {
        $perubahan = Apbdes::query()
            ->where('tahun_anggaran', $tahunAnggaran)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->first();

        if ($perubahan !== null) {
            $this->ensureFourteenStandardRows($perubahan);

            return $perubahan->load('rincian');
        }

        return $this->initializeMurni($tahunAnggaran, $actorId);
    }

    /**
     * Sinkronkan nilai Pagu dan Realisasi Komponen Proyek Fisik Bidang 2 dari akumulasi proyek Modul 1.
     */
    public function syncBidang2FisikFromProjects(int $tahunAnggaran, ?int $actorId = null): Apbdes
    {
        return DB::transaction(function () use ($tahunAnggaran, $actorId) {
            $targetDoc = $this->getTargetDocumentForSync($tahunAnggaran, $actorId);
            $this->applyProjectTotalsToDocument($targetDoc);

            return $targetDoc->fresh(['rincian']);
        });
    }

    /**
     * Hitung ringkasan Pendapatan, Belanja 5 Bidang (termasuk gabungan Fisik + Non-Fisik Bidang 2),
     * Pembiayaan, serta Rumus Keseimbangan Anggaran sesuai PRD Seksi 4.3.
     *
     * @return array<string, mixed>
     */
    public function calculateSummaryAndBalance(Apbdes $apbdes): array
    {
        $rincian = $apbdes->relationLoaded('rincian')
            ? $apbdes->rincian
            : $apbdes->rincian()->get();

        $pendapatanRows = $rincian->filter(fn (ApbdesRincian $r) => ($r->kelompok->value ?? $r->kelompok) === KelompokPosApbdes::Pendapatan->value);
        $belanjaRows = $rincian->filter(fn (ApbdesRincian $r) => ($r->kelompok->value ?? $r->kelompok) === KelompokPosApbdes::Belanja->value);
        $penerimaanPembiayaanRows = $rincian->filter(fn (ApbdesRincian $r) => ($r->kelompok->value ?? $r->kelompok) === KelompokPosApbdes::PembiayaanPenerimaan->value);
        $pengeluaranPembiayaanRows = $rincian->filter(fn (ApbdesRincian $r) => ($r->kelompok->value ?? $r->kelompok) === KelompokPosApbdes::PembiayaanPengeluaran->value);

        $totalPaguPendapatan = (int) $pendapatanRows->sum('pagu_anggaran');
        $totalRealisasiPendapatan = (int) $pendapatanRows->sum('total_realisasi');

        $totalPaguBelanja = (int) $belanjaRows->sum('pagu_anggaran');
        $totalRealisasiBelanja = (int) $belanjaRows->sum('total_realisasi');

        $paguPembiayaanPenerimaan = (int) $penerimaanPembiayaanRows->sum('pagu_anggaran');
        $realisasiPembiayaanPenerimaan = (int) $penerimaanPembiayaanRows->sum('total_realisasi');

        $paguPembiayaanPengeluaran = (int) $pengeluaranPembiayaanRows->sum('pagu_anggaran');
        $realisasiPembiayaanPengeluaran = (int) $pengeluaranPembiayaanRows->sum('total_realisasi');

        $pembiayaanNettoPagu = $paguPembiayaanPenerimaan - $paguPembiayaanPengeluaran;
        $pembiayaanNettoRealisasi = $realisasiPembiayaanPenerimaan - $realisasiPembiayaanPengeluaran;

        // Rumus Keseimbangan Anggaran (PRD 4.3):
        // Total Pagu Belanja (Bidang 1 s/d 5) = Total Pendapatan + Penerimaan Pembiayaan (SiLPA) - Pengeluaran Pembiayaan
        $ketersediaanDanaPagu = $totalPaguPendapatan + $paguPembiayaanPenerimaan - $paguPembiayaanPengeluaran;
        $selisihKeseimbangan = $ketersediaanDanaPagu - $totalPaguBelanja;
        $isSeimbang = $selisihKeseimbangan === 0;

        $sisaAnggaranBelanja = $totalPaguBelanja - $totalRealisasiBelanja;
        $persentaseSerapanBelanja = $totalPaguBelanja > 0
            ? round(($totalRealisasiBelanja / $totalPaguBelanja) * 100, 2)
            : 0.0;

        $persentaseRealisasiPendapatan = $totalPaguPendapatan > 0
            ? round(($totalRealisasiPendapatan / $totalPaguPendapatan) * 100, 2)
            : 0.0;

        // Agregasi 5 Bidang Belanja (Bidang 2 menggabungkan bidang_2_fisik + bidang_2_non_fisik)
        $byKode = $rincian->keyBy('kode_pos');
        $b1 = $byKode->get('bidang_1');
        $b2Fisik = $byKode->get('bidang_2_fisik');
        $b2NonFisik = $byKode->get('bidang_2_non_fisik');
        $b3 = $byKode->get('bidang_3');
        $b4 = $byKode->get('bidang_4');
        $b5 = $byKode->get('bidang_5');

        $b2TotalPagu = (int) ($b2Fisik?->pagu_anggaran ?? 0) + (int) ($b2NonFisik?->pagu_anggaran ?? 0);
        $b2TotalRealisasi = (int) ($b2Fisik?->total_realisasi ?? 0) + (int) ($b2NonFisik?->total_realisasi ?? 0);

        $limaBidang = [
            $this->formatBidangSummary('bidang_1', 'Bidang 1: Penyelenggaraan Pemerintahan Desa', (int) ($b1?->pagu_anggaran ?? 0), (int) ($b1?->total_realisasi ?? 0)),
            array_merge(
                $this->formatBidangSummary('bidang_2', 'Bidang 2: Pelaksanaan Pembangunan Desa (Fisik & Non-Fisik)', $b2TotalPagu, $b2TotalRealisasi),
                [
                    'pagu_fisik' => (int) ($b2Fisik?->pagu_anggaran ?? 0),
                    'realisasi_fisik' => (int) ($b2Fisik?->total_realisasi ?? 0),
                    'pagu_non_fisik' => (int) ($b2NonFisik?->pagu_anggaran ?? 0),
                    'realisasi_non_fisik' => (int) ($b2NonFisik?->total_realisasi ?? 0),
                ]
            ),
            $this->formatBidangSummary('bidang_3', 'Bidang 3: Pembinaan Kemasyarakatan Desa', (int) ($b3?->pagu_anggaran ?? 0), (int) ($b3?->total_realisasi ?? 0)),
            $this->formatBidangSummary('bidang_4', 'Bidang 4: Pemberdayaan Masyarakat Desa', (int) ($b4?->pagu_anggaran ?? 0), (int) ($b4?->total_realisasi ?? 0)),
            $this->formatBidangSummary('bidang_5', 'Bidang 5: Penanggulangan Bencana, Darurat & Mendesak', (int) ($b5?->pagu_anggaran ?? 0), (int) ($b5?->total_realisasi ?? 0)),
        ];

        return [
            'total_pagu_pendapatan' => $totalPaguPendapatan,
            'total_realisasi_pendapatan' => $totalRealisasiPendapatan,
            'persentase_realisasi_pendapatan' => $persentaseRealisasiPendapatan,
            'total_pagu_belanja' => $totalPaguBelanja,
            'total_realisasi_belanja' => $totalRealisasiBelanja,
            'sisa_anggaran_belanja' => $sisaAnggaranBelanja,
            'persentase_serapan_belanja' => $persentaseSerapanBelanja,
            'pagu_pembiayaan_penerimaan' => $paguPembiayaanPenerimaan,
            'realisasi_pembiayaan_penerimaan' => $realisasiPembiayaanPenerimaan,
            'pagu_pembiayaan_pengeluaran' => $paguPembiayaanPengeluaran,
            'realisasi_pembiayaan_pengeluaran' => $realisasiPembiayaanPengeluaran,
            'pembiayaan_netto_pagu' => $pembiayaanNettoPagu,
            'pembiayaan_netto_realisasi' => $pembiayaanNettoRealisasi,
            'ketersediaan_dana_pagu' => $ketersediaanDanaPagu,
            'selisih_keseimbangan' => $selisihKeseimbangan,
            'is_seimbang' => $isSeimbang,
            'lima_bidang' => $limaBidang,
        ];
    }

    /**
     * Pastikan dokumen APBDes memiliki tepat 14 pos anggaran baku.
     */
    public function ensureFourteenStandardRows(Apbdes $apbdes): void
    {
        $existingCodes = $apbdes->rincian()->pluck('kode_pos')->all();

        foreach (ApbdesPosCatalog::all() as $pos) {
            if (! in_array($pos['kode_pos'], $existingCodes, true)) {
                ApbdesRincian::create([
                    'apbdes_id' => $apbdes->id,
                    'kelompok' => $pos['kelompok'],
                    'kode_pos' => $pos['kode_pos'],
                    'nama_pos' => $pos['nama_pos'],
                    'is_otomatis_modul_1' => (bool) $pos['is_otomatis_modul_1'],
                    'pagu_anggaran' => 0,
                    'realisasi_tahap_1' => 0,
                    'realisasi_tahap_2' => 0,
                    'realisasi_tahap_3' => 0,
                    'total_realisasi' => 0,
                    'urutan' => (int) $pos['urutan'],
                ]);
            }
        }
    }

    protected function applyProjectTotalsToDocument(Apbdes $apbdes): void
    {
        if ($apbdes->is_terkunci) {
            return;
        }

        $totals = ProyekPembangunan::query()
            ->where('tahun_anggaran', $apbdes->tahun_anggaran)
            ->selectRaw('COALESCE(SUM(pagu_anggaran), 0) as total_pagu, COALESCE(SUM(realisasi_anggaran), 0) as total_realisasi')
            ->first();

        $totalPagu = (int) ($totals->total_pagu ?? 0);
        $totalRealisasi = (int) ($totals->total_realisasi ?? 0);

        $apbdes->rincian()
            ->where('kode_pos', ApbdesPosCatalog::KODE_BIDANG_2_FISIK)
            ->update([
                'pagu_anggaran' => $totalPagu,
                'realisasi_tahap_1' => $totalRealisasi,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
                'total_realisasi' => $totalRealisasi,
            ]);
    }

    protected function assertNotLocked(Apbdes $apbdes): void
    {
        if ($apbdes->is_terkunci) {
            throw ValidationException::withMessages([
                'apbdes' => 'Dokumen APBDes Awal (Murni) telah terkunci sebagai arsip historis karena dokumen APBDes Perubahan sudah dibuat.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{pagu_anggaran: int, realisasi_tahap_1: int, realisasi_tahap_2: int, realisasi_tahap_3: int, total_realisasi: int}
     */
    protected function validateAndComputeRowTotals(ApbdesRincian $row, array $values): array
    {
        $pagu = isset($values['pagu_anggaran']) ? (int) $values['pagu_anggaran'] : (int) $row->pagu_anggaran;
        $t1 = isset($values['realisasi_tahap_1']) ? (int) $values['realisasi_tahap_1'] : (int) $row->realisasi_tahap_1;
        $t2 = isset($values['realisasi_tahap_2']) ? (int) $values['realisasi_tahap_2'] : (int) $row->realisasi_tahap_2;
        $t3 = isset($values['realisasi_tahap_3']) ? (int) $values['realisasi_tahap_3'] : (int) $row->realisasi_tahap_3;

        if ($pagu < 0 || $t1 < 0 || $t2 < 0 || $t3 < 0) {
            throw ValidationException::withMessages([
                "rincian.{$row->kode_pos}" => 'Nilai pagu dan realisasi tidak boleh bernilai negatif.',
                'pagu_anggaran' => 'Nilai pagu dan realisasi tidak boleh bernilai negatif.',
            ]);
        }

        $totalRealisasi = $t1 + $t2 + $t3;

        $kelompok = $row->kelompok instanceof KelompokPosApbdes
            ? $row->kelompok
            : KelompokPosApbdes::from((string) $row->kelompok);

        // Kelompok belanja & pembiayaan_pengeluaran: wajib total_realisasi <= pagu_anggaran
        // Kelompok pendapatan & pembiayaan_penerimaan: diizinkan melampaui pagu_anggaran (> 100%)
        if (in_array($kelompok, [KelompokPosApbdes::Belanja, KelompokPosApbdes::PembiayaanPengeluaran], true)) {
            if ($totalRealisasi > $pagu) {
                $msg = "Akumulasi realisasi Tahap I s/d III pada pos \"{$row->nama_pos}\" (Rp " . number_format($totalRealisasi, 0, ',', '.') . ') tidak boleh melebihi Pagu Anggaran (Rp ' . number_format($pagu, 0, ',', '.') . ').';

                throw ValidationException::withMessages([
                    "rincian.{$row->kode_pos}" => $msg,
                    'realisasi' => $msg,
                ]);
            }
        }

        return [
            'pagu_anggaran' => $pagu,
            'realisasi_tahap_1' => $t1,
            'realisasi_tahap_2' => $t2,
            'realisasi_tahap_3' => $t3,
            'total_realisasi' => $totalRealisasi,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatBidangSummary(string $kode, string $nama, int $pagu, int $realisasi): array
    {
        $sisa = $pagu - $realisasi;
        $persentase = $pagu > 0 ? round(($realisasi / $pagu) * 100, 2) : 0.0;

        return [
            'kode' => $kode,
            'nama' => $nama,
            'pagu_anggaran' => $pagu,
            'total_realisasi' => $realisasi,
            'sisa_anggaran' => $sisa,
            'persentase_serapan' => $persentase,
        ];
    }
}
