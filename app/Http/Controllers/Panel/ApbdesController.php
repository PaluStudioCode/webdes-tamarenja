<?php

namespace App\Http\Controllers\Panel;

use App\Enums\JenisDokumenApbdes;
use App\Enums\KelompokPosApbdes;
use App\Http\Controllers\Controller;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
use App\Models\ProfilDesa;
use App\Services\ApbdesService;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApbdesController extends Controller
{
    public function __construct(
        protected ApbdesService $apbdesService
    ) {}

    /**
     * Halaman pengelolaan APBDes Multi-Tahun (/panel/apbdes).
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

        // Pastikan minimal dokumen Murni pada tahun terpilih telah terinisialisasi beserta 14 pos baku
        $murni = $this->apbdesService->initializeMurni($tahunAnggaran, $request->user()?->id);
        $perubahan = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', $tahunAnggaran)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->first();

        $requestedJenis = $request->input('jenis_dokumen');
        if ($requestedJenis === JenisDokumenApbdes::Murni->value) {
            $selectedDoc = $murni;
        } elseif ($requestedJenis === JenisDokumenApbdes::Perubahan->value && $perubahan !== null) {
            $selectedDoc = $perubahan;
        } else {
            // Prioritas tampilan: Perubahan bila sudah dibuat, selain itu Murni
            $selectedDoc = $perubahan ?? $murni;
        }

        $selectedDoc->load('rincian');
        $summary = $this->apbdesService->calculateSummaryAndBalance($selectedDoc);

        // Data perbandingan lintas tahun untuk Tab Grafik Multi-Tahun
        $allActiveDocs = Apbdes::query()
            ->with('rincian')
            ->where('is_aktif_publik', true)
            ->orderBy('tahun_anggaran')
            ->get();

        $perbandinganLintasTahun = $allActiveDocs->map(function (Apbdes $doc) {
            $docSummary = $this->apbdesService->calculateSummaryAndBalance($doc);
            $jenisVal = $doc->jenis_dokumen instanceof JenisDokumenApbdes
                ? $doc->jenis_dokumen->value
                : (string) $doc->jenis_dokumen;

            return [
                'id' => $doc->id,
                'tahun_anggaran' => $doc->tahun_anggaran,
                'jenis_dokumen' => $jenisVal,
                'total_pagu_pendapatan' => $docSummary['total_pagu_pendapatan'],
                'total_realisasi_pendapatan' => $docSummary['total_realisasi_pendapatan'],
                'total_pagu_belanja' => $docSummary['total_pagu_belanja'],
                'total_realisasi_belanja' => $docSummary['total_realisasi_belanja'],
                'persentase_serapan_belanja' => $docSummary['persentase_serapan_belanja'],
            ];
        })->values();

        return Inertia::render('Apbdes/Index', [
            'tahun_anggaran' => $tahunAnggaran,
            'dokumen_aktif' => $this->transformDocument($selectedDoc),
            'dokumen_murni' => $this->transformDocument($murni),
            'dokumen_perubahan' => $perubahan ? $this->transformDocument($perubahan) : null,
            'has_perubahan' => $perubahan !== null,
            'ringkasan' => $summary,
            'perbandingan_lintas_tahun' => $perbandinganLintasTahun,
        ]);
    }

    /**
     * Buat/inisialisasi dokumen APBDes Murni atau buat Perubahan (Clone & Freeze).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
                'jenis_dokumen' => ['required', Rule::in(['murni', 'perubahan'])],
                'nomor_perdes' => ['nullable', 'string', 'max:100'],
                'tanggal_penetapan' => ['nullable', 'date'],
            ],
            [
                'tahun_anggaran.required' => 'Tahun anggaran wajib diisi.',
                'tahun_anggaran.between' => 'Tahun anggaran harus berada di antara 2020 dan 2100.',
                'jenis_dokumen.required' => 'Jenis dokumen APBDes wajib dipilih.',
                'jenis_dokumen.in' => 'Jenis dokumen harus murni atau perubahan.',
            ]
        );

        $tahun = (int) $validated['tahun_anggaran'];
        $jenis = (string) $validated['jenis_dokumen'];

        if ($jenis === JenisDokumenApbdes::Perubahan->value) {
            $this->apbdesService->createPerubahanCloneAndFreeze(
                $tahun,
                $request->user()?->id,
                $validated['nomor_perdes'] ?? null,
                $validated['tanggal_penetapan'] ?? null
            );

            return back()->with('notification', [
                'type' => 'success',
                'message' => "Dokumen APBDes Perubahan TA {$tahun} berhasil dibuat. Rincian awal telah disalin dari APBDes Murni dan dokumen Murni kini dikunci sebagai arsip.",
            ]);
        }

        $this->apbdesService->initializeMurni(
            $tahun,
            $request->user()?->id,
            $validated['nomor_perdes'] ?? null,
            $validated['tanggal_penetapan'] ?? null
        );

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Dokumen APBDes Murni TA {$tahun} beserta 14 pos baku berhasil disiapkan.",
        ]);
    }

    /**
     * Endpoint khusus pembuatan APBDes Perubahan (Clone & Freeze).
     */
    public function createPerubahan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
            'nomor_perdes' => ['nullable', 'string', 'max:100'],
            'tanggal_penetapan' => ['nullable', 'date'],
        ]);

        $tahun = (int) $validated['tahun_anggaran'];

        $this->apbdesService->createPerubahanCloneAndFreeze(
            $tahun,
            $request->user()?->id,
            $validated['nomor_perdes'] ?? null,
            $validated['tanggal_penetapan'] ?? null
        );

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Dokumen APBDes Perubahan TA {$tahun} berhasil dibuat dan dokumen Murni telah dibekukan.",
        ]);
    }

    /**
     * Perbarui metadata dan/atau rincian pos anggaran pada dokumen APBDes.
     * Menolak modifikasi pada dokumen is_terkunci = true maupun pada baris bidang_2_fisik.
     */
    public function update(Request $request, Apbdes $apbdes): RedirectResponse
    {
        $validated = $request->validate([
            'nomor_perdes' => ['nullable', 'string', 'max:100'],
            'tanggal_penetapan' => ['nullable', 'date'],
            'rincian' => ['nullable', 'array'],
        ]);

        if (array_key_exists('nomor_perdes', $validated) || array_key_exists('tanggal_penetapan', $validated)) {
            $this->apbdesService->updateMetadata($apbdes, $validated);
        }

        if (! empty($validated['rincian']) && is_array($validated['rincian'])) {
            $this->apbdesService->updateBatchRincian($apbdes, $validated['rincian']);
        } else {
            // Pastikan tetap menolak jika dokumen terkunci walau rincian kosong
            $this->apbdesService->updateMetadata($apbdes, $validated);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Rincian anggaran APBDes berhasil disimpan.',
        ]);
    }

    /**
     * Perbarui satu baris rincian pos APBDes secara spesifik.
     */
    public function updateRincianRow(Request $request, ApbdesRincian $rincian): RedirectResponse
    {
        $validated = $request->validate([
            'pagu_anggaran' => ['required', 'integer', 'min:0'],
            'realisasi_tahap_1' => ['required', 'integer', 'min:0'],
            'realisasi_tahap_2' => ['required', 'integer', 'min:0'],
            'realisasi_tahap_3' => ['required', 'integer', 'min:0'],
        ], [
            'pagu_anggaran.min' => 'Pagu anggaran tidak boleh bernilai negatif.',
            'realisasi_tahap_1.min' => 'Realisasi Tahap I tidak boleh bernilai negatif.',
            'realisasi_tahap_2.min' => 'Realisasi Tahap II tidak boleh bernilai negatif.',
            'realisasi_tahap_3.min' => 'Realisasi Tahap III tidak boleh bernilai negatif.',
        ]);

        $this->apbdesService->updateSingleRincian($rincian, $validated);

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Pos anggaran \"{$rincian->nama_pos}\" berhasil diperbarui.",
        ]);
    }

    /**
     * Cetak / Unduh PDF Laporan Realisasi APBDes ukuran Kertas F4 Portrait.
     * Dapat diakses oleh Operator Desa maupun Kepala Desa.
     */
    public function cetakPdf(Request $request, ?Apbdes $apbdes = null): HttpResponse
    {
        $profilDesa = ProfilDesa::singleton();

        if ($apbdes === null) {
            $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
            $tahun = (int) ($request->input('tahun')
                ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));
            $jenis = $request->input('jenis_dokumen');

            $query = Apbdes::query()->with('rincian')->where('tahun_anggaran', $tahun);
            if ($jenis) {
                $query->where('jenis_dokumen', $jenis);
            } else {
                $query->orderByDesc('is_aktif_publik');
            }

            $apbdes = $query->first() ?? $this->apbdesService->initializeMurni($tahun, $request->user()?->id);
        } else {
            $apbdes->load('rincian');
        }

        $summary = $this->apbdesService->calculateSummaryAndBalance($apbdes);
        $logoBase64 = $this->resolveLogoBase64($profilDesa);

        $jenisVal = $apbdes->jenis_dokumen instanceof JenisDokumenApbdes
            ? $apbdes->jenis_dokumen->value
            : (string) $apbdes->jenis_dokumen;

        $filename = sprintf('Laporan_Realisasi_APBDes_%d_%s.pdf', $apbdes->tahun_anggaran, ucfirst($jenisVal));

        $pdf = PdfService::createF4Pdf('pdf.apbdes-realisasi', [
            'apbdes' => $apbdes,
            'summary' => $summary,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
        ], 'portrait');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformDocument(Apbdes $doc): array
    {
        $doc->loadMissing('rincian');
        $jenisVal = $doc->jenis_dokumen instanceof JenisDokumenApbdes
            ? $doc->jenis_dokumen->value
            : (string) $doc->jenis_dokumen;

        return [
            'id' => $doc->id,
            'tahun_anggaran' => $doc->tahun_anggaran,
            'jenis_dokumen' => $jenisVal,
            'jenis_label' => $doc->jenis_dokumen instanceof JenisDokumenApbdes
                ? $doc->jenis_dokumen->label()
                : ($jenisVal === 'perubahan' ? 'APBDes Perubahan (P-APBDes)' : 'APBDes Awal (Murni)'),
            'is_aktif_publik' => (bool) $doc->is_aktif_publik,
            'is_terkunci' => (bool) $doc->is_terkunci,
            'nomor_perdes' => $doc->nomor_perdes,
            'tanggal_penetapan' => $doc->tanggal_penetapan?->format('Y-m-d'),
            'rincian' => $doc->rincian->map(function (ApbdesRincian $r) {
                $kelompokVal = $r->kelompok instanceof KelompokPosApbdes
                    ? $r->kelompok->value
                    : (string) $r->kelompok;
                $sisa = (int) $r->pagu_anggaran - (int) $r->total_realisasi;
                $persentase = (int) $r->pagu_anggaran > 0
                    ? round(((int) $r->total_realisasi / (int) $r->pagu_anggaran) * 100, 2)
                    : 0.0;

                return [
                    'id' => $r->id,
                    'apbdes_id' => $r->apbdes_id,
                    'kelompok' => $kelompokVal,
                    'kode_pos' => $r->kode_pos,
                    'nama_pos' => $r->nama_pos,
                    'is_otomatis_modul_1' => (bool) $r->is_otomatis_modul_1,
                    'pagu_anggaran' => (int) $r->pagu_anggaran,
                    'realisasi_tahap_1' => (int) $r->realisasi_tahap_1,
                    'realisasi_tahap_2' => (int) $r->realisasi_tahap_2,
                    'realisasi_tahap_3' => (int) $r->realisasi_tahap_3,
                    'total_realisasi' => (int) $r->total_realisasi,
                    'sisa_anggaran' => $sisa,
                    'persentase' => $persentase,
                    'urutan' => (int) $r->urutan,
                ];
            })->values(),
        ];
    }

    protected function resolveLogoBase64(?ProfilDesa $profilDesa): ?string
    {
        return PdfService::resolveLogoBase64($profilDesa);
    }
}
