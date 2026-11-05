<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StatusProyek;
use App\Enums\SumberDanaProyek;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pembangunan\SaveProyekPembangunanRequest;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Services\PdfService;
use App\Services\ProyekSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProyekPembangunanController extends Controller
{
    public function __construct(
        protected ProyekSyncService $proyekSyncService
    ) {}

    /**
     * Halaman daftar & monitoring proyek pembangunan fisik (/panel/pembangunan).
     */
    public function index(Request $request): Response
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahunAnggaran = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        if ($request->filled('tahun') && $request->hasSession()) {
            $request->session()->put('filter_tahun', $tahunAnggaran);
        }

        $search = trim((string) $request->input('search', ''));
        $dusun = trim((string) $request->input('dusun', ''));
        $status = trim((string) $request->input('status', ''));
        $sumberDana = trim((string) $request->input('sumber_dana', ''));

        $baseTahunQuery = ProyekPembangunan::query()->where('tahun_anggaran', $tahunAnggaran);
        $allTahunProjects = (clone $baseTahunQuery)->orderByDesc('id')->get();

        $filteredQuery = (clone $baseTahunQuery)->orderByDesc('id');

        if ($search !== '') {
            $filteredQuery->where(function ($q) use ($search) {
                $q->where('nama_kegiatan', 'like', "%{$search}%")
                    ->orWhere('pelaksana_kegiatan', 'like', "%{$search}%")
                    ->orWhere('lokasi_dusun', 'like', "%{$search}%");
            });
        }

        if ($dusun !== '') {
            $filteredQuery->where('lokasi_dusun', $dusun);
        }

        if ($status !== '') {
            $filteredQuery->where('status_proyek', $status);
        }

        if ($sumberDana !== '') {
            $filteredQuery->where('sumber_dana', $sumberDana);
        }

        $proyekItems = $filteredQuery->get()->map(fn (ProyekPembangunan $p) => $this->transformProyek($p))->values();

        $totalPagu = (int) $allTahunProjects->sum('pagu_anggaran');
        $totalRealisasi = (int) $allTahunProjects->sum('realisasi_anggaran');
        $persentaseSerapan = $totalPagu > 0
            ? round(($totalRealisasi / $totalPagu) * 100, 2)
            : 0.0;
        $rataProgresFisik = $allTahunProjects->count() > 0
            ? round((float) $allTahunProjects->avg('progres_fisik'), 1)
            : 0.0;

        return Inertia::render('Pembangunan/Index', [
            'tahun_anggaran' => $tahunAnggaran,
            'proyek' => $proyekItems,
            'ringkasan' => [
                'total_proyek' => $allTahunProjects->count(),
                'total_pagu' => $totalPagu,
                'total_realisasi' => $totalRealisasi,
                'sisa_anggaran' => $totalPagu - $totalRealisasi,
                'persentase_serapan' => $persentaseSerapan,
                'rata_progres_fisik' => $rataProgresFisik,
                'jumlah_belum_dimulai' => $allTahunProjects->filter(
                    fn ($p) => ($p->status_proyek->value ?? $p->status_proyek) === StatusProyek::BelumDimulai->value
                )->count(),
                'jumlah_sedang_dikerjakan' => $allTahunProjects->filter(
                    fn ($p) => ($p->status_proyek->value ?? $p->status_proyek) === StatusProyek::SedangDikerjakan->value
                )->count(),
                'jumlah_selesai' => $allTahunProjects->filter(
                    fn ($p) => ($p->status_proyek->value ?? $p->status_proyek) === StatusProyek::Selesai->value
                )->count(),
            ],
            'filters' => [
                'search' => $search,
                'dusun' => $dusun,
                'status' => $status,
                'sumber_dana' => $sumberDana,
            ],
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_sumber_dana' => $this->getSumberDanaOptions(),
            'opsi_status' => $this->getStatusOptions(),
        ]);
    }

    /**
     * Halaman form tambah proyek pembangunan fisik (/panel/pembangunan/tambah).
     */
    public function create(Request $request): Response
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahunAnggaran = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        return Inertia::render('Pembangunan/Form', [
            'mode' => 'create',
            'tahun_anggaran' => $tahunAnggaran,
            'proyek' => null,
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_sumber_dana' => $this->getSumberDanaOptions(),
            'koordinat_default' => [
                'latitude' => -0.5835,
                'longitude' => 119.7932,
            ],
        ]);
    }

    /**
     * Simpan proyek pembangunan fisik baru dan sinkronkan APBDes Bidang 2 Fisik.
     */
    public function store(SaveProyekPembangunanRequest $request): RedirectResponse
    {
        $proyek = $this->proyekSyncService->createProject(
            $request->validated(),
            [
                'foto_0' => $request->file('foto_0'),
                'foto_50' => $request->file('foto_50'),
                'foto_100' => $request->file('foto_100'),
            ],
            $request->user()?->id
        );

        return redirect()
            ->route('panel.pembangunan.index', ['tahun' => $proyek->tahun_anggaran])
            ->with('notification', [
                'type' => 'success',
                'message' => "Proyek \"{$proyek->nama_kegiatan}\" berhasil ditambahkan dan disinkronkan ke APBDes Bidang 2.",
            ]);
    }

    /**
     * Halaman form edit proyek pembangunan fisik (/panel/pembangunan/{proyek}/edit).
     */
    public function edit(ProyekPembangunan $proyek): Response
    {
        $profilDesa = ProfilDesa::singleton();

        return Inertia::render('Pembangunan/Form', [
            'mode' => 'edit',
            'tahun_anggaran' => (int) $proyek->tahun_anggaran,
            'proyek' => $this->transformProyek($proyek),
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_sumber_dana' => $this->getSumberDanaOptions(),
            'koordinat_default' => [
                'latitude' => $proyek->latitude !== null ? (float) $proyek->latitude : -0.5835,
                'longitude' => $proyek->longitude !== null ? (float) $proyek->longitude : 119.7932,
            ],
        ]);
    }

    /**
     * Perbarui proyek pembangunan fisik, sinkronkan APBDes Bidang 2, dan bersihkan foto lama.
     */
    public function update(SaveProyekPembangunanRequest $request, ProyekPembangunan $proyek): RedirectResponse
    {
        $updated = $this->proyekSyncService->updateProject(
            $proyek,
            $request->validated(),
            [
                'foto_0' => $request->file('foto_0'),
                'foto_50' => $request->file('foto_50'),
                'foto_100' => $request->file('foto_100'),
            ],
            $request->user()?->id
        );

        return redirect()
            ->route('panel.pembangunan.index', ['tahun' => $updated->tahun_anggaran])
            ->with('notification', [
                'type' => 'success',
                'message' => "Proyek \"{$updated->nama_kegiatan}\" berhasil diperbarui dan disinkronkan ke APBDes Bidang 2.",
            ]);
    }

    /**
     * Soft-delete proyek pembangunan fisik dan hitung ulang APBDes Bidang 2 Fisik.
     */
    public function destroy(Request $request, ProyekPembangunan $proyek): RedirectResponse
    {
        $nama = $proyek->nama_kegiatan;
        $this->proyekSyncService->deleteProject($proyek, $request->user()?->id);

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Proyek \"{$nama}\" berhasil dihapus dan total APBDes Bidang 2 telah disesuaikan.",
        ]);
    }

    /**
     * Cetak / Unduh PDF Rekapitulasi Proyek Pembangunan ukuran F4 Landscape.
     * Dapat diakses oleh Operator Desa maupun Kepala Desa.
     */
    public function cetakPdf(Request $request): HttpResponse
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahun = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        $proyekList = ProyekPembangunan::query()
            ->where('tahun_anggaran', $tahun)
            ->orderBy('id')
            ->get();

        $logoBase64 = $this->resolveLogoBase64($profilDesa);
        $filename = sprintf('Rekapitulasi_Proyek_Pembangunan_TA_%d.pdf', $tahun);

        $pdf = PdfService::createF4Pdf('pdf.proyek-rekap', [
            'tahun' => $tahun,
            'proyekList' => $proyekList,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
        ], 'landscape');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformProyek(ProyekPembangunan $proyek): array
    {
        $sumberVal = $proyek->sumber_dana instanceof SumberDanaProyek
            ? $proyek->sumber_dana->value
            : (string) $proyek->sumber_dana;
        $sumberLabel = $proyek->sumber_dana instanceof SumberDanaProyek
            ? $proyek->sumber_dana->label()
            : $sumberVal;

        $statusVal = $proyek->status_proyek instanceof StatusProyek
            ? $proyek->status_proyek->value
            : (string) $proyek->status_proyek;
        $statusLabel = $proyek->status_proyek instanceof StatusProyek
            ? $proyek->status_proyek->label()
            : $statusVal;

        $pagu = (int) $proyek->pagu_anggaran;
        $realisasi = (int) $proyek->realisasi_anggaran;
        $persentaseDana = $pagu > 0 ? round(($realisasi / $pagu) * 100, 1) : 0.0;

        return [
            'id' => $proyek->id,
            'tahun_anggaran' => (int) $proyek->tahun_anggaran,
            'nama_kegiatan' => $proyek->nama_kegiatan,
            'lokasi_dusun' => $proyek->lokasi_dusun,
            'pagu_anggaran' => $pagu,
            'realisasi_anggaran' => $realisasi,
            'sisa_anggaran' => $pagu - $realisasi,
            'persentase_dana' => $persentaseDana,
            'sumber_dana' => $sumberVal,
            'sumber_dana_label' => $sumberLabel,
            'volume_fisik' => $proyek->volume_fisik,
            'waktu_pelaksanaan' => $proyek->waktu_pelaksanaan,
            'pelaksana_kegiatan' => $proyek->pelaksana_kegiatan,
            'latitude' => $proyek->latitude !== null ? (float) $proyek->latitude : null,
            'longitude' => $proyek->longitude !== null ? (float) $proyek->longitude : null,
            'progres_fisik' => (int) $proyek->progres_fisik,
            'status_proyek' => $statusVal,
            'status_proyek_label' => $statusLabel,
            'foto_0_path' => $proyek->foto_0_path,
            'foto_50_path' => $proyek->foto_50_path,
            'foto_100_path' => $proyek->foto_100_path,
            'foto_0_url' => $proyek->foto_0_path ? Storage::disk('public')->url($proyek->foto_0_path) : null,
            'foto_50_url' => $proyek->foto_50_path ? Storage::disk('public')->url($proyek->foto_50_path) : null,
            'foto_100_url' => $proyek->foto_100_path ? Storage::disk('public')->url($proyek->foto_100_path) : null,
            'catatan_kendala' => $proyek->catatan_kendala,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function getSumberDanaOptions(): array
    {
        return collect(SumberDanaProyek::cases())->map(fn (SumberDanaProyek $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    protected function getStatusOptions(): array
    {
        return collect(StatusProyek::cases())->map(fn (StatusProyek $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->values()->all();
    }

    protected function resolveLogoBase64(?ProfilDesa $profilDesa): ?string
    {
        if (! $profilDesa || empty($profilDesa->logo_desa_path)) {
            return null;
        }

        if (! Storage::disk('public')->exists($profilDesa->logo_desa_path)) {
            return null;
        }

        $bytes = Storage::disk('public')->get($profilDesa->logo_desa_path);
        if (! $bytes) {
            return null;
        }

        return 'data:image/png;base64,' . base64_encode($bytes);
    }
}
