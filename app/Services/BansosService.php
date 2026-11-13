<?php

namespace App\Services;

use App\Enums\JenisBansos;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BansosService
{
    /**
     * Daftar nilai status kesejahteraan yang termasuk sasaran prioritas DTSEN Desil 1-4.
     *
     * @return list<string>
     */
    public static function prioritasDesilValues(): array
    {
        return [
            StatusKesejahteraan::DtsenDesil1->value,
            StatusKesejahteraan::DtsenDesil2->value,
            StatusKesejahteraan::DtsenDesil34->value,
        ];
    }

    /**
     * Tambah atau pulihkan (upsert with trashed) penerima Bansos dengan validasi penduduk aktif
     * dan pengambilan snapshot otomatis `kategori_ekonomi` dari `penduduk.status_kesejahteraan`.
     *
     * @param  array<string, mixed>  $data
     * @return array{model: PenerimaBansos, restored: bool}
     */
    public function storeOrRestore(array $data, ?int $userId = null): array
    {
        /** @var Penduduk|null $penduduk */
        $penduduk = Penduduk::query()->find($data['penduduk_id'] ?? null);

        if (! $penduduk) {
            throw ValidationException::withMessages([
                'penduduk_id' => 'Data penduduk tidak ditemukan.',
            ]);
        }

        $statusKependudukan = $penduduk->status_kependudukan instanceof StatusKependudukan
            ? $penduduk->status_kependudukan->value
            : (string) $penduduk->status_kependudukan;

        if ($statusKependudukan !== StatusKependudukan::Aktif->value) {
            throw ValidationException::withMessages([
                'penduduk_id' => 'Warga yang berstatus Meninggal Dunia atau Pindah Keluar tidak dapat didaftarkan sebagai penerima bantuan sosial baru.',
            ]);
        }

        $snapshotEkonomi = $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
            ? $penduduk->status_kesejahteraan->value
            : (string) ($penduduk->status_kesejahteraan ?: StatusKesejahteraan::NonPrioritas->value);

        $jenisBantuan = $data['jenis_bantuan'] instanceof JenisBansos
            ? $data['jenis_bantuan']->value
            : (string) $data['jenis_bantuan'];

        $tahunAnggaran = (int) $data['tahun_anggaran'];
        $tahapPenyaluran = trim((string) $data['tahap_penyaluran']);
        $statusKepesertaan = isset($data['status_kepesertaan']) && $data['status_kepesertaan'] !== ''
            ? ($data['status_kepesertaan'] instanceof StatusPesertaBansos
                ? $data['status_kepesertaan']->value
                : (string) $data['status_kepesertaan'])
            : StatusPesertaBansos::Aktif->value;

        if ($penduduk->kartu_keluarga_id && $statusKepesertaan === StatusPesertaBansos::Aktif->value) {
            $duplicate = PenerimaBansos::query()
                ->whereHas('penduduk', function (Builder $q) use ($penduduk) {
                    $q->where('kartu_keluarga_id', $penduduk->kartu_keluarga_id)
                        ->where('id', '!=', $penduduk->id);
                })
                ->where('jenis_bantuan', $jenisBantuan)
                ->where('tahun_anggaran', $tahunAnggaran)
                ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value)
                ->with('penduduk')
                ->first();

            if ($duplicate) {
                $namaAnggota = $duplicate->penduduk?->nama_lengkap ?? 'Anggota keluarga lain';
                $labelProgram = $duplicate->jenis_bantuan instanceof JenisBansos
                    ? $duplicate->jenis_bantuan->label()
                    : (string) $duplicate->jenis_bantuan;

                throw ValidationException::withMessages([
                    'penduduk_id' => "Bantuan ganda dalam 1 KK dilarang: Anggota keluarga lain atas nama {$namaAnggota} sudah terdaftar aktif menerima {$labelProgram} pada tahun anggaran {$tahunAnggaran}.",
                ]);
            }
        }

        return DB::transaction(function () use (
            $penduduk,
            $jenisBantuan,
            $tahunAnggaran,
            $tahapPenyaluran,
            $snapshotEkonomi,
            $statusKepesertaan,
            $data,
            $userId
        ) {
            $existing = PenerimaBansos::withTrashed()
                ->where('penduduk_id', $penduduk->id)
                ->where('jenis_bantuan', $jenisBantuan)
                ->where('tahun_anggaran', $tahunAnggaran)
                ->where('tahap_penyaluran', $tahapPenyaluran)
                ->lockForUpdate()
                ->first();

            $payload = [
                'penduduk_id' => $penduduk->id,
                'jenis_bantuan' => $jenisBantuan,
                'bentuk_nominal' => trim((string) $data['bentuk_nominal']),
                'tahun_anggaran' => $tahunAnggaran,
                'tahap_penyaluran' => $tahapPenyaluran,
                'kategori_ekonomi' => $snapshotEkonomi,
                'status_kepesertaan' => $statusKepesertaan,
                'keterangan' => isset($data['keterangan']) && trim((string) $data['keterangan']) !== ''
                    ? trim((string) $data['keterangan'])
                    : null,
            ];

            if ($existing !== null) {
                $wasTrashed = $existing->trashed();
                if ($wasTrashed) {
                    $existing->restore();
                }

                $existing->update($payload);

                return [
                    'model' => $existing->fresh(['penduduk.kartuKeluarga']),
                    'restored' => $wasTrashed,
                ];
            }

            $created = PenerimaBansos::create([
                ...$payload,
                'created_by' => $userId ?? auth()->id(),
            ]);

            return [
                'model' => $created->load(['penduduk.kartuKeluarga']),
                'restored' => false,
            ];
        });
    }

    /**
     * Perbarui data penerima Bansos.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(PenerimaBansos $penerimaBansos, array $data): PenerimaBansos
    {
        $targetPendudukId = isset($data['penduduk_id']) ? (int) $data['penduduk_id'] : (int) $penerimaBansos->penduduk_id;
        /** @var Penduduk|null $penduduk */
        $penduduk = Penduduk::query()->find($targetPendudukId);

        if (! $penduduk) {
            throw ValidationException::withMessages([
                'penduduk_id' => 'Data penduduk tidak ditemukan.',
            ]);
        }

        $statusKepesertaan = isset($data['status_kepesertaan']) && $data['status_kepesertaan'] !== ''
            ? ($data['status_kepesertaan'] instanceof StatusPesertaBansos
                ? $data['status_kepesertaan']->value
                : (string) $data['status_kepesertaan'])
            : ($penerimaBansos->status_kepesertaan instanceof StatusPesertaBansos
                ? $penerimaBansos->status_kepesertaan->value
                : (string) $penerimaBansos->status_kepesertaan);

        $statusKependudukan = $penduduk->status_kependudukan instanceof StatusKependudukan
            ? $penduduk->status_kependudukan->value
            : (string) $penduduk->status_kependudukan;

        if (
            $statusKepesertaan === StatusPesertaBansos::Aktif->value &&
            $statusKependudukan !== StatusKependudukan::Aktif->value
        ) {
            throw ValidationException::withMessages([
                'penduduk_id' => 'Warga non-aktif (Meninggal Dunia / Pindah Keluar) tidak dapat memiliki status kepesertaan Bansos Aktif.',
            ]);
        }

        $snapshotEkonomi = $targetPendudukId !== (int) $penerimaBansos->penduduk_id
            ? ($penduduk->status_kesejahteraan instanceof StatusKesejahteraan
                ? $penduduk->status_kesejahteraan->value
                : (string) $penduduk->status_kesejahteraan)
            : ($penerimaBansos->kategori_ekonomi instanceof StatusKesejahteraan
                ? $penerimaBansos->kategori_ekonomi->value
                : (string) $penerimaBansos->kategori_ekonomi);

        $jenisBantuan = isset($data['jenis_bantuan'])
            ? ($data['jenis_bantuan'] instanceof JenisBansos ? $data['jenis_bantuan']->value : (string) $data['jenis_bantuan'])
            : ($penerimaBansos->jenis_bantuan instanceof JenisBansos ? $penerimaBansos->jenis_bantuan->value : (string) $penerimaBansos->jenis_bantuan);

        $tahunAnggaran = isset($data['tahun_anggaran']) ? (int) $data['tahun_anggaran'] : (int) $penerimaBansos->tahun_anggaran;

        if ($penduduk->kartu_keluarga_id && $statusKepesertaan === StatusPesertaBansos::Aktif->value) {
            $duplicate = PenerimaBansos::query()
                ->where('id', '!=', $penerimaBansos->id)
                ->whereHas('penduduk', function (Builder $q) use ($penduduk) {
                    $q->where('kartu_keluarga_id', $penduduk->kartu_keluarga_id)
                        ->where('id', '!=', $penduduk->id);
                })
                ->where('jenis_bantuan', $jenisBantuan)
                ->where('tahun_anggaran', $tahunAnggaran)
                ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value)
                ->with('penduduk')
                ->first();

            if ($duplicate) {
                $namaAnggota = $duplicate->penduduk?->nama_lengkap ?? 'Anggota keluarga lain';
                $labelProgram = $duplicate->jenis_bantuan instanceof JenisBansos
                    ? $duplicate->jenis_bantuan->label()
                    : (string) $duplicate->jenis_bantuan;

                throw ValidationException::withMessages([
                    'penduduk_id' => "Bantuan ganda dalam 1 KK dilarang: Anggota keluarga lain atas nama {$namaAnggota} sudah terdaftar aktif menerima {$labelProgram} pada tahun anggaran {$tahunAnggaran}.",
                ]);
            }
        }

        $penerimaBansos->update([
            'penduduk_id' => $penduduk->id,
            'jenis_bantuan' => $jenisBantuan,
            'bentuk_nominal' => isset($data['bentuk_nominal']) ? trim((string) $data['bentuk_nominal']) : $penerimaBansos->bentuk_nominal,
            'tahun_anggaran' => isset($data['tahun_anggaran']) ? (int) $data['tahun_anggaran'] : $penerimaBansos->tahun_anggaran,
            'tahap_penyaluran' => isset($data['tahap_penyaluran']) ? trim((string) $data['tahap_penyaluran']) : $penerimaBansos->tahap_penyaluran,
            'kategori_ekonomi' => $snapshotEkonomi,
            'status_kepesertaan' => $statusKepesertaan,
            'keterangan' => array_key_exists('keterangan', $data)
                ? (trim((string) $data['keterangan']) !== '' ? trim((string) $data['keterangan']) : null)
                : $penerimaBansos->keterangan,
        ]);

        return $penerimaBansos->fresh(['penduduk.kartuKeluarga']);
    }

    /**
     * Filter Pemerataan Bantuan — Mode Default Berbasis Kartu Keluarga (KK).
     *
     * Menyaring `kartu_keluarga` aktif yang:
     * 1. Memiliki minimal 1 anggota `status_kependudukan = 'aktif'` dengan
     *    `status_kesejahteraan IN ('dtsen_desil_1', 'dtsen_desil_2', 'dtsen_desil_3_4')`.
     * 2. Seluruh anggota dalam KK tersebut BELUM memiliki catatan `penerima_bansos`
     *    non-terhapus apa pun (baik `aktif`, `graduasi`, `pindah`, maupun `meninggal_dunia`)
     *    pada `tahun_anggaran` terpilih.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getPemerataanBerbasisKk(int $tahunAnggaran, array $filters = []): Collection
    {
        $dusun = trim((string) ($filters['dusun'] ?? ''));
        $desilFilter = trim((string) ($filters['status_kesejahteraan'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $prioritasValues = self::prioritasDesilValues();

        $query = KartuKeluarga::query()
            ->with(['penduduk' => function ($q) {
                $q->where('status_kependudukan', StatusKependudukan::Aktif->value)
                    ->orderByRaw("CASE WHEN hubungan_keluarga = 'kepala_keluarga' THEN 0 WHEN hubungan_keluarga = 'istri' THEN 1 ELSE 2 END")
                    ->orderBy('id');
            }])
            ->whereHas('penduduk', function (Builder $pq) use ($prioritasValues, $desilFilter) {
                $pq->where('status_kependudukan', StatusKependudukan::Aktif->value)
                    ->whereIn('status_kesejahteraan', $prioritasValues);

                if ($desilFilter !== '' && in_array($desilFilter, $prioritasValues, true)) {
                    $pq->where('status_kesejahteraan', $desilFilter);
                }
            })
            ->whereDoesntHave('penduduk.penerimaBansos', function (Builder $bq) use ($tahunAnggaran) {
                // Mengecualikan KK apabila ada minimal 1 anggota KK yang sudah memiliki catatan penerima_bansos
                // non-terhapus apa pun (baik aktif maupun graduasi) pada tahun anggaran terpilih.
                $bq->where('tahun_anggaran', $tahunAnggaran);
            });

        if ($dusun !== '') {
            $query->where('dusun', $dusun);
        }

        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('nomor_kk', 'like', "%{$search}%")
                    ->orWhereHas('penduduk', function (Builder $pq) use ($search) {
                        $pq->where('status_kependudukan', StatusKependudukan::Aktif->value)
                            ->where(function (Builder $inner) use ($search) {
                                $inner->where('nik', 'like', "%{$search}%")
                                    ->orWhere('nama_lengkap', 'like', "%{$search}%");
                            });
                    });
            });
        }

        return $query
            ->orderBy('dusun')
            ->orderBy('nomor_kk')
            ->get()
            ->map(function (KartuKeluarga $kk) use ($prioritasValues) {
                $anggotaAktif = $kk->penduduk;
                $anggotaPrioritas = $anggotaAktif->filter(function (Penduduk $p) use ($prioritasValues) {
                    $val = $p->status_kesejahteraan instanceof StatusKesejahteraan
                        ? $p->status_kesejahteraan->value
                        : (string) $p->status_kesejahteraan;

                    return in_array($val, $prioritasValues, true);
                })->values();

                // Representasi KK: Kepala Keluarga aktif, atau anggota prioritas pertama
                /** @var Penduduk|null $representasi */
                $representasi = $anggotaAktif->firstWhere('hubungan_keluarga', 'kepala_keluarga')
                    ?? $anggotaPrioritas->first()
                    ?? $anggotaAktif->first();

                // Tentukan desil terendah (paling prioritas) di KK tersebut
                $priorityOrder = [
                    StatusKesejahteraan::DtsenDesil1->value => 1,
                    StatusKesejahteraan::DtsenDesil2->value => 2,
                    StatusKesejahteraan::DtsenDesil34->value => 3,
                ];

                $topPriorityMember = $anggotaPrioritas->sortBy(function (Penduduk $p) use ($priorityOrder) {
                    $val = $p->status_kesejahteraan instanceof StatusKesejahteraan
                        ? $p->status_kesejahteraan->value
                        : (string) $p->status_kesejahteraan;

                    return $priorityOrder[$val] ?? 99;
                })->first() ?? $representasi;

                $desilEnum = $topPriorityMember?->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $topPriorityMember->status_kesejahteraan
                    : StatusKesejahteraan::tryFrom((string) ($topPriorityMember?->status_kesejahteraan ?? ''));

                return [
                    'kartu_keluarga_id' => $kk->id,
                    'nomor_kk' => $kk->nomor_kk,
                    'dusun' => $kk->dusun,
                    'rt' => $kk->rt,
                    'rw' => $kk->rw,
                    'alamat' => $kk->alamat,
                    'penduduk_id' => $representasi?->id,
                    'nik_kepala_keluarga' => $representasi?->nik,
                    'nama_kepala_keluarga' => $representasi?->nama_lengkap,
                    'hubungan_representasi' => $representasi?->hubungan_keluarga,
                    'status_kesejahteraan' => $desilEnum?->value ?? StatusKesejahteraan::DtsenDesil34->value,
                    'status_kesejahteraan_label' => $desilEnum?->label() ?? 'DTSEN Desil 3-4',
                    'jumlah_anggota_aktif' => $anggotaAktif->count(),
                    'jumlah_anggota_prioritas' => $anggotaPrioritas->count(),
                    'anggota_prioritas' => $anggotaPrioritas->map(fn (Penduduk $m) => [
                        'id' => $m->id,
                        'nik' => $m->nik,
                        'nama_lengkap' => $m->nama_lengkap,
                        'hubungan_keluarga' => $m->hubungan_keluarga,
                        'status_kesejahteraan' => $m->status_kesejahteraan instanceof StatusKesejahteraan
                            ? $m->status_kesejahteraan->value
                            : (string) $m->status_kesejahteraan,
                    ])->all(),
                ];
            })
            ->values();
    }

    /**
     * Filter Pemerataan Bantuan — Mode Toggle Per Individu.
     *
     * Menyaring baris `penduduk` berstatus `aktif` dengan kategori DTSEN Desil 1–4
     * yang belum memiliki catatan `penerima_bansos` non-terhapus pada `tahun_anggaran` terpilih.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function getPemerataanPerIndividu(int $tahunAnggaran, array $filters = []): Collection
    {
        $dusun = trim((string) ($filters['dusun'] ?? ''));
        $desilFilter = trim((string) ($filters['status_kesejahteraan'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));
        $prioritasValues = self::prioritasDesilValues();

        $query = Penduduk::query()
            ->with('kartuKeluarga')
            ->where('status_kependudukan', StatusKependudukan::Aktif->value)
            ->whereIn('status_kesejahteraan', $prioritasValues)
            ->whereDoesntHave('penerimaBansos', function (Builder $bq) use ($tahunAnggaran) {
                $bq->where('tahun_anggaran', $tahunAnggaran);
            });

        if ($desilFilter !== '' && in_array($desilFilter, $prioritasValues, true)) {
            $query->where('status_kesejahteraan', $desilFilter);
        }

        if ($dusun !== '') {
            $query->whereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('dusun', $dusun));
        }

        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('nik', 'like', "%{$search}%")
                    ->orWhere('nama_lengkap', 'like', "%{$search}%")
                    ->orWhereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('nomor_kk', 'like', "%{$search}%"));
            });
        }

        return $query
            ->orderByRaw("CASE
                WHEN status_kesejahteraan = 'dtsen_desil_1' THEN 1
                WHEN status_kesejahteraan = 'dtsen_desil_2' THEN 2
                WHEN status_kesejahteraan = 'dtsen_desil_3_4' THEN 3
                ELSE 4 END")
            ->orderBy('nama_lengkap')
            ->get()
            ->map(function (Penduduk $p) {
                $kk = $p->kartuKeluarga;
                $desilEnum = $p->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $p->status_kesejahteraan
                    : StatusKesejahteraan::tryFrom((string) $p->status_kesejahteraan);

                return [
                    'penduduk_id' => $p->id,
                    'kartu_keluarga_id' => $p->kartu_keluarga_id,
                    'nik' => $p->nik,
                    'nama_lengkap' => $p->nama_lengkap,
                    'hubungan_keluarga' => $p->hubungan_keluarga,
                    'pekerjaan' => $p->pekerjaan,
                    'nomor_kk' => $kk?->nomor_kk,
                    'dusun' => $kk?->dusun,
                    'rt' => $kk?->rt,
                    'rw' => $kk?->rw,
                    'status_kesejahteraan' => $desilEnum?->value ?? (string) $p->status_kesejahteraan,
                    'status_kesejahteraan_label' => $desilEnum?->label() ?? (string) $p->status_kesejahteraan,
                ];
            })
            ->values();
    }
}
