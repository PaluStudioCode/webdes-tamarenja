<?php

namespace App\Http\Controllers\Panel;

use App\Enums\JenisBansos;
use App\Enums\JenisDokumenApbdes;
use App\Enums\StatusKependudukan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusProyek;
use App\Enums\StatusSuratKeluar;
use App\Enums\SumberDanaProyek;
use App\Http\Controllers\Controller;
use App\Models\Apbdes;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Services\ApbdesService;
use App\Services\BansosService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ApbdesService $apbdesService,
        private readonly BansosService $bansosService,
    ) {}

    /**
     * Tampilkan Dashboard Eksekutif & Operasional (/panel/dashboard)
     * sesuai Tahun Anggaran sesi di Topbar.
     */
    public function index(Request $request): Response
    {
        $profil = ProfilDesa::singleton();
        $tahunDefault = (int) ($profil?->tahun_anggaran_aktif ?? date('Y'));
        $tahunAnggaran = $request->hasSession()
            ? (int) $request->session()->get('filter_tahun', $tahunDefault)
            : $tahunDefault;

        return Inertia::render('Panel/Dashboard', [
            'tahun_anggaran' => $tahunAnggaran,
            'apbdes' => $this->buildApbdesMetrics($tahunAnggaran),
            'pembangunan' => $this->buildPembangunanMetrics($tahunAnggaran),
            'persuratan' => $this->buildPersuratanMetrics($tahunAnggaran),
            'sosial' => $this->buildSosialMetrics($tahunAnggaran),
        ]);
    }

    /**
     * Agregasi ringkasan APBDes tahun terpilih:
     * Mengutamakan dokumen 'perubahan' jika sudah ada, selain itu 'murni'.
     *
     * @return array<string, mixed>
     */
    private function buildApbdesMetrics(int $tahunAnggaran): array
    {
        $perubahan = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', $tahunAnggaran)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->first();

        $murni = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', $tahunAnggaran)
            ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
            ->first();

        $activeDoc = $perubahan ?? $murni;

        if ($activeDoc === null) {
            return [
                'id' => null,
                'jenis_dokumen' => 'murni',
                'jenis_dokumen_label' => 'APBDes Awal (Murni)',
                'nomor_perdes' => null,
                'has_perubahan' => false,
                'is_terkunci' => false,
                'total_pagu_pendapatan' => 0,
                'total_realisasi_pendapatan' => 0,
                'persentase_realisasi_pendapatan' => 0.0,
                'total_pagu_belanja' => 0,
                'total_realisasi_belanja' => 0,
                'sisa_anggaran_belanja' => 0,
                'persentase_serapan_belanja' => 0.0,
                'ketersediaan_dana_pagu' => 0,
                'selisih_keseimbangan' => 0,
                'is_seimbang' => true,
                'lima_bidang' => [
                    ['kode' => 'bidang_1', 'nama' => 'Bidang 1: Penyelenggaraan Pemerintahan Desa', 'pagu_anggaran' => 0, 'total_realisasi' => 0, 'sisa_anggaran' => 0, 'persentase_serapan' => 0.0],
                    ['kode' => 'bidang_2', 'nama' => 'Bidang 2: Pelaksanaan Pembangunan Desa (Fisik & Non-Fisik)', 'pagu_anggaran' => 0, 'total_realisasi' => 0, 'sisa_anggaran' => 0, 'persentase_serapan' => 0.0],
                    ['kode' => 'bidang_3', 'nama' => 'Bidang 3: Pembinaan Kemasyarakatan Desa', 'pagu_anggaran' => 0, 'total_realisasi' => 0, 'sisa_anggaran' => 0, 'persentase_serapan' => 0.0],
                    ['kode' => 'bidang_4', 'nama' => 'Bidang 4: Pemberdayaan Masyarakat Desa', 'pagu_anggaran' => 0, 'total_realisasi' => 0, 'sisa_anggaran' => 0, 'persentase_serapan' => 0.0],
                    ['kode' => 'bidang_5', 'nama' => 'Bidang 5: Penanggulangan Bencana, Darurat & Mendesak', 'pagu_anggaran' => 0, 'total_realisasi' => 0, 'sisa_anggaran' => 0, 'persentase_serapan' => 0.0],
                ],
            ];
        }

        $summary = $this->apbdesService->calculateSummaryAndBalance($activeDoc);
        $jenisVal = $activeDoc->jenis_dokumen instanceof JenisDokumenApbdes
            ? $activeDoc->jenis_dokumen->value
            : (string) $activeDoc->jenis_dokumen;

        return array_merge($summary, [
            'id' => $activeDoc->id,
            'jenis_dokumen' => $jenisVal,
            'jenis_dokumen_label' => $jenisVal === 'perubahan'
                ? 'APBDes Perubahan (P-APBDes)'
                : 'APBDes Awal (Murni)',
            'nomor_perdes' => $activeDoc->nomor_perdes,
            'has_perubahan' => $perubahan !== null,
            'is_terkunci' => (bool) $activeDoc->is_terkunci,
        ]);
    }

    /**
     * Agregasi progres fisik vs keuangan proyek Modul 1 beserta path foto 0%/50%/100% dan kendala lapangan.
     *
     * @return array<string, mixed>
     */
    private function buildPembangunanMetrics(int $tahunAnggaran): array
    {
        $projects = ProyekPembangunan::query()
            ->where('tahun_anggaran', $tahunAnggaran)
            ->orderByDesc('progres_fisik')
            ->orderBy('id')
            ->get();

        $totalProyek = $projects->count();
        $selesaiCount = $projects->filter(
            fn (ProyekPembangunan $p) => ($p->status_proyek->value ?? $p->status_proyek) === StatusProyek::Selesai->value
        )->count();
        $sedangDikerjakanCount = $projects->filter(
            fn (ProyekPembangunan $p) => ($p->status_proyek->value ?? $p->status_proyek) === StatusProyek::SedangDikerjakan->value
        )->count();
        $belumDimulaiCount = $projects->filter(
            fn (ProyekPembangunan $p) => ($p->status_proyek->value ?? $p->status_proyek) === StatusProyek::BelumDimulai->value
        )->count();

        $totalPagu = (int) $projects->sum('pagu_anggaran');
        $totalRealisasi = (int) $projects->sum('realisasi_anggaran');
        $rataProgresFisik = $totalProyek > 0
            ? (int) round((float) $projects->avg('progres_fisik'))
            : 0;

        $daftarProyek = $projects->map(function (ProyekPembangunan $p): array {
            $sumberVal = $p->sumber_dana instanceof SumberDanaProyek
                ? $p->sumber_dana->value
                : (string) $p->sumber_dana;
            $sumberLabel = $p->sumber_dana instanceof SumberDanaProyek
                ? $p->sumber_dana->label()
                : strtoupper($sumberVal);

            $statusVal = $p->status_proyek instanceof StatusProyek
                ? $p->status_proyek->value
                : (string) $p->status_proyek;
            $statusLabel = $p->status_proyek instanceof StatusProyek
                ? $p->status_proyek->label()
                : $statusVal;

            return [
                'id' => $p->id,
                'nama_kegiatan' => $p->nama_kegiatan,
                'lokasi_dusun' => $p->lokasi_dusun,
                'sumber_dana' => $sumberVal,
                'sumber_dana_label' => $sumberLabel,
                'pagu_anggaran' => (int) $p->pagu_anggaran,
                'realisasi_anggaran' => (int) $p->realisasi_anggaran,
                'persentase_serapan' => $p->persentaseSerapan(),
                'progres_fisik' => (int) $p->progres_fisik,
                'status_proyek' => $statusVal,
                'status_label' => $statusLabel,
                'volume_fisik' => $p->volume_fisik,
                'waktu_pelaksanaan' => $p->waktu_pelaksanaan,
                'pelaksana_kegiatan' => $p->pelaksana_kegiatan,
                'catatan_kendala' => $p->catatan_kendala,
                'foto_0_path' => $p->foto_0_path,
                'foto_50_path' => $p->foto_50_path,
                'foto_100_path' => $p->foto_100_path,
                'foto_0_url' => $p->foto_0_path ? Storage::disk('public')->url($p->foto_0_path) : null,
                'foto_50_url' => $p->foto_50_path ? Storage::disk('public')->url($p->foto_50_path) : null,
                'foto_100_url' => $p->foto_100_path ? Storage::disk('public')->url($p->foto_100_path) : null,
            ];
        })->values()->all();

        return [
            'total_proyek' => $totalProyek,
            'selesai_count' => $selesaiCount,
            'sedang_dikerjakan_count' => $sedangDikerjakanCount,
            'belum_dimulai_count' => $belumDimulaiCount,
            'total_pagu' => $totalPagu,
            'total_realisasi' => $totalRealisasi,
            'rata_progres_fisik' => $rataProgresFisik,
            'daftar_proyek' => $daftarProyek,
        ];
    }

    /**
     * Agregasi ringkasan surat keluar, surat masuk, dan antrean verifikasi online Modul 3.
     *
     * @return array<string, int>
     */
    private function buildPersuratanMetrics(int $tahunAnggaran): array
    {
        return [
            'antrean_menunggu_count' => SuratKeluar::query()
                ->where('status', StatusSuratKeluar::MenungguVerifikasi)
                ->count(),
            'surat_keluar_terbit_count' => SuratKeluar::query()
                ->where('tahun_surat', $tahunAnggaran)
                ->where('status', StatusSuratKeluar::Terbit)
                ->count(),
            'surat_keluar_dibatalkan_count' => SuratKeluar::query()
                ->where('tahun_surat', $tahunAnggaran)
                ->where('status', StatusSuratKeluar::Dibatalkan)
                ->count(),
            'surat_masuk_count' => SuratMasuk::query()
                ->where('tahun_agenda', $tahunAnggaran)
                ->count(),
        ];
    }

    /**
     * Agregasi rekap penyaluran Bansos, pemerataan KK DTSEN, dan demografi warga aktif.
     *
     * @return array<string, mixed>
     */
    private function buildSosialMetrics(int $tahunAnggaran): array
    {
        $totalPenerima = PenerimaBansos::query()
            ->where('tahun_anggaran', $tahunAnggaran)
            ->count();

        $penerimaAktif = PenerimaBansos::query()
            ->where('tahun_anggaran', $tahunAnggaran)
            ->where('status_kepesertaan', StatusPesertaBansos::Aktif)
            ->count();

        $kkBelumMenerimaCount = $this->bansosService
            ->getPemerataanBerbasisKk($tahunAnggaran)
            ->count();

        $countsByJenis = PenerimaBansos::query()
            ->where('tahun_anggaran', $tahunAnggaran)
            ->selectRaw('jenis_bantuan, COUNT(*) as total')
            ->groupBy('jenis_bantuan')
            ->pluck('total', 'jenis_bantuan');

        $perJenis = collect(JenisBansos::cases())->map(function (JenisBansos $jenis) use ($countsByJenis): array {
            return [
                'jenis_bantuan' => $jenis->value,
                'label' => $jenis->label(),
                'jumlah' => (int) ($countsByJenis[$jenis->value] ?? 0),
            ];
        })->values()->all();

        $pendudukAktifCount = Penduduk::query()
            ->where('status_kependudukan', StatusKependudukan::Aktif)
            ->count();

        $kkAktifCount = KartuKeluarga::query()
            ->aktif()
            ->count();

        return [
            'total_penerima_bansos' => $totalPenerima,
            'penerima_aktif_count' => $penerimaAktif,
            'kk_belum_menerima_count' => $kkBelumMenerimaCount,
            'per_jenis' => $perJenis,
            'penduduk_aktif_count' => $pendudukAktifCount,
            'kk_aktif_count' => $kkAktifCount,
        ];
    }
}
