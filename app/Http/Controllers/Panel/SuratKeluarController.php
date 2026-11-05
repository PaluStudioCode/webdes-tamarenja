<?php

namespace App\Http\Controllers\Panel;

use App\Enums\JenisSurat;
use App\Enums\StatusSuratKeluar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Persuratan\StoreSuratKeluarLoketRequest;
use App\Models\Penduduk;
use App\Models\ProfilDesa;
use App\Models\SuratKeluar;
use App\Services\BansosKkHistoryService;
use App\Services\PdfService;
use App\Services\PhoneNormalizerService;
use App\Services\SuratKeluarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SuratKeluarController extends Controller
{
    public function __construct(
        protected SuratKeluarService $suratKeluarService,
        protected BansosKkHistoryService $bansosKkHistoryService
    ) {}

    /**
     * Tab 1 Persuratan: Antrean Permohonan Surat Online (/panel/persuratan/antrean).
     */
    public function antrean(Request $request): Response
    {
        $profilDesa = ProfilDesa::singleton();
        $search = trim((string) $request->input('search', ''));
        $statusFilter = trim((string) $request->input('status', ''));

        $query = SuratKeluar::query()
            ->with(['penduduk.kartuKeluarga'])
            ->where('sumber_pengajuan', 'online')
            ->orderByRaw("CASE WHEN status = 'menunggu_verifikasi' THEN 0 WHEN status = 'terbit' THEN 1 ELSE 2 END")
            ->orderByDesc('id');

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('keperluan', 'like', "%{$search}%")
                    ->orWhereHas('penduduk', function ($pq) use ($search) {
                        $pq->where('nik', 'like', "%{$search}%")
                            ->orWhere('nama_lengkap', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->get()->map(fn (SuratKeluar $s) => $this->transformSurat($s))->values();

        $jumlahMenunggu = SuratKeluar::query()
            ->where('status', StatusSuratKeluar::MenungguVerifikasi)
            ->count();

        return Inertia::render('Persuratan/Antrean', [
            'antrean' => $items,
            'jumlah_menunggu' => $jumlahMenunggu,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
            'opsi_jenis_surat' => $this->getJenisSuratOptions(),
            'pejabat_penandatangan' => $this->getPejabatOptions($profilDesa),
        ]);
    }

    /**
     * Tab 2 Persuratan: Buku Agenda Surat Keluar (/panel/persuratan/surat-keluar).
     */
    public function index(Request $request): Response
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahunSurat = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        if ($request->filled('tahun') && $request->hasSession()) {
            $request->session()->put('filter_tahun', $tahunSurat);
        }

        $search = trim((string) $request->input('search', ''));
        $jenisFilter = trim((string) $request->input('jenis_surat', ''));
        $statusFilter = trim((string) $request->input('status', ''));

        $query = SuratKeluar::query()
            ->with(['penduduk.kartuKeluarga'])
            ->where('tahun_surat', $tahunSurat)
            ->whereIn('status', [StatusSuratKeluar::Terbit->value, StatusSuratKeluar::Dibatalkan->value])
            ->orderByDesc('nomor_urut');

        if ($jenisFilter !== '') {
            $query->where('jenis_surat', $jenisFilter);
        }

        if ($statusFilter !== '') {
            $query->where('status', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('keperluan', 'like', "%{$search}%")
                    ->orWhereHas('penduduk', function ($pq) use ($search) {
                        $pq->where('nik', 'like', "%{$search}%")
                            ->orWhere('nama_lengkap', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->get()->map(fn (SuratKeluar $s) => $this->transformSurat($s))->values();

        $jumlahMenunggu = SuratKeluar::query()
            ->where('status', StatusSuratKeluar::MenungguVerifikasi)
            ->count();

        $cetakId = $request->filled('cetak_id') ? (int) $request->input('cetak_id') : null;

        return Inertia::render('Persuratan/SuratKeluar', [
            'tahun_surat' => $tahunSurat,
            'surat_keluar' => $items,
            'jumlah_menunggu' => $jumlahMenunggu,
            'cetak_id' => $cetakId,
            'cetak_pdf_url' => $cetakId ? route('panel.persuratan.surat-keluar.pdf', $cetakId) : null,
            'filters' => [
                'search' => $search,
                'jenis_surat' => $jenisFilter,
                'status' => $statusFilter,
            ],
            'opsi_jenis_surat' => $this->getJenisSuratOptions(),
            'pejabat_penandatangan' => $this->getPejabatOptions($profilDesa),
        ]);
    }

    /**
     * Halaman Layanan Kilat Loket 10 Template Surat (/panel/persuratan/surat-keluar/buat).
     */
    public function create(): Response
    {
        $profilDesa = ProfilDesa::singleton();

        return Inertia::render('Persuratan/Buat', [
            'opsi_jenis_surat' => $this->getJenisSuratOptions(),
            'pejabat_penandatangan' => $this->getPejabatOptions($profilDesa),
            'tanggal_hari_ini' => now()->toDateString(),
        ]);
    }

    /**
     * Terbitkan surat keluar baru dari Loket Internal dan redirect ke Buku Agenda dengan pemicu cetak PDF.
     */
    public function store(StoreSuratKeluarLoketRequest $request): RedirectResponse
    {
        $surat = $this->suratKeluarService->terbitkanLoket(
            $request->validated(),
            $request->user()?->id
        );

        return redirect()
            ->route('panel.persuratan.surat-keluar.index', [
                'tahun' => $surat->tahun_surat,
                'cetak_id' => $surat->id,
            ])
            ->with('notification', [
                'type' => 'success',
                'message' => "Surat resmi Nomor \"{$surat->nomor_surat}\" berhasil diterbitkan dan siap dicetak.",
            ]);
    }

    /**
     * Verifikasi permohonan surat online (`menunggu_verifikasi` -> `terbit`).
     */
    public function verifikasi(Request $request, SuratKeluar $suratKeluar): RedirectResponse
    {
        $validated = $request->validate([
            'kode_klasifikasi' => ['nullable', 'string', 'max:20'],
            'opsi_penandatangan' => ['nullable', Rule::in(['kepala_desa', 'sekdes_an'])],
            'tanggal_surat' => ['nullable', 'date'],
            'keperluan' => ['nullable', 'string', 'max:255'],
            'data_khusus' => ['nullable', 'array'],
        ]);

        $terbit = $this->suratKeluarService->verifikasiDanTerbitkan(
            $suratKeluar,
            $validated,
            $request->user()?->id
        );

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Permohonan surat online berhasil diverifikasi dan diterbitkan dengan Nomor \"{$terbit->nomor_surat}\".",
        ]);
    }

    /**
     * Tolak permohonan surat online (`menunggu_verifikasi` -> `ditolak`).
     */
    public function tolak(Request $request, SuratKeluar $suratKeluar): RedirectResponse
    {
        $validated = $request->validate(
            [
                'alasan_penolakan' => ['required', 'string', 'max:255'],
            ],
            [
                'alasan_penolakan.required' => 'Alasan penolakan permohonan surat wajib diisi.',
            ]
        );

        $this->suratKeluarService->tolakPengajuan(
            $suratKeluar,
            $validated['alasan_penolakan'],
            $request->user()?->id
        );

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Permohonan surat online telah ditolak.',
        ]);
    }

    /**
     * Batalkan surat keluar yang sudah `terbit` (`terbit` -> `dibatalkan`).
     */
    public function batalkan(Request $request, SuratKeluar $suratKeluar): RedirectResponse
    {
        $validated = $request->validate(
            [
                'alasan_pembatalan' => ['required', 'string', 'max:255'],
            ],
            [
                'alasan_pembatalan.required' => 'Alasan pembatalan surat wajib diisi.',
            ]
        );

        $this->suratKeluarService->batalkanSuratTerbit(
            $suratKeluar,
            $validated['alasan_pembatalan'],
            $request->user()?->id
        );

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Surat Nomor \"{$suratKeluar->nomor_surat}\" telah dibatalkan pada Buku Agenda Desa.",
        ]);
    }

    /**
     * Soft-delete antrean surat (`menunggu_verifikasi` / `ditolak`).
     * Menolak penghapusan untuk surat yang sudah `terbit`.
     */
    public function destroy(SuratKeluar $suratKeluar): RedirectResponse
    {
        $this->suratKeluarService->hapusAntrean($suratKeluar);

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Data antrean permohonan surat berhasil dihapus.',
        ]);
    }

    /**
     * Endpoint JSON pengecekan instan riwayat Bansos 1 KK untuk verifikasi SKTM di loket.
     */
    public function cekBansosKk(Penduduk $penduduk): JsonResponse
    {
        return response()->json(
            $this->bansosKkHistoryService->getHistoryForPenduduk($penduduk)
        );
    }

    /**
     * Cetak / Unduh PDF F4 Portrait untuk 1 surat keluar resmi (lengkap dengan QR Code SVG Base64).
     */
    public function cetakPdf(SuratKeluar $suratKeluar): HttpResponse
    {
        $suratKeluar->loadMissing(['penduduk.kartuKeluarga']);
        $profilDesa = ProfilDesa::singleton();

        $jenisVal = $suratKeluar->jenis_surat instanceof JenisSurat
            ? $suratKeluar->jenis_surat->value
            : (string) $suratKeluar->jenis_surat;

        $viewName = "pdf.surat.{$jenisVal}";
        if (! view()->exists($viewName)) {
            $viewName = 'pdf.surat.keterangan_umum';
        }

        $logoBase64 = $this->resolveLogoBase64($profilDesa);
        $qrSvgDataUri = $this->suratKeluarService->generateQrSvgDataUri($suratKeluar->qr_token);

        $safeNomor = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) ($suratKeluar->nomor_surat ?: $suratKeluar->id));
        $filename = sprintf('Surat_%s_%s.pdf', strtoupper($jenisVal), $safeNomor);

        $pdf = PdfService::createF4Pdf($viewName, [
            'surat' => $suratKeluar,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
            'qrSvgDataUri' => $qrSvgDataUri,
        ], 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Cetak / Unduh PDF Buku Agenda Surat Keluar ukuran F4 Landscape.
     */
    public function cetakAgendaPdf(Request $request): HttpResponse
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahun = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        $suratList = SuratKeluar::query()
            ->with('penduduk')
            ->where('tahun_surat', $tahun)
            ->whereIn('status', [StatusSuratKeluar::Terbit->value, StatusSuratKeluar::Dibatalkan->value])
            ->orderBy('nomor_urut')
            ->get();

        $logoBase64 = $this->resolveLogoBase64($profilDesa);
        $filename = sprintf('Buku_Agenda_Surat_Keluar_TA_%d.pdf', $tahun);

        $pdf = PdfService::createF4Pdf('pdf.agenda-surat-keluar', [
            'tahun' => $tahun,
            'suratList' => $suratList,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
        ], 'landscape');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformSurat(SuratKeluar $s): array
    {
        $jenisVal = $s->jenis_surat instanceof JenisSurat
            ? $s->jenis_surat->value
            : (string) $s->jenis_surat;
        $jenisLabel = $s->jenis_surat instanceof JenisSurat
            ? $s->jenis_surat->label()
            : $jenisVal;

        $statusVal = $s->status instanceof StatusSuratKeluar
            ? $s->status->value
            : (string) $s->status;
        $statusLabel = $s->status instanceof StatusSuratKeluar
            ? $s->status->label()
            : $statusVal;

        $namaWarga = $s->penduduk?->nama_lengkap ?? '-';
        $waMessage = sprintf(
            'Halo Bpk/Ibu %s, Surat %s Anda sudah selesai dicetak dan ditandatangani. Silakan diambil di Kantor Desa dengan membawa KTP/KK asli.',
            $namaWarga,
            $jenisLabel
        );
        $waUrl = $s->no_wa_pemohon
            ? PhoneNormalizerService::toWhatsappUrl($s->no_wa_pemohon, $waMessage)
            : null;

        return [
            'id' => $s->id,
            'qr_token' => $s->qr_token,
            'penduduk_id' => $s->penduduk_id,
            'nik_pemohon' => $s->penduduk?->nik,
            'nama_pemohon' => $namaWarga,
            'dusun_pemohon' => $s->penduduk?->kartuKeluarga?->dusun,
            'sumber_pengajuan' => $s->sumber_pengajuan,
            'jenis_surat' => $jenisVal,
            'jenis_surat_label' => $jenisLabel,
            'kode_klasifikasi' => $s->kode_klasifikasi,
            'tahun_surat' => $s->tahun_surat,
            'nomor_urut' => $s->nomor_urut,
            'nomor_surat' => $s->nomor_surat,
            'tanggal_surat' => $s->tanggal_surat?->format('Y-m-d'),
            'keperluan' => $s->keperluan,
            'data_khusus' => $s->data_khusus ?? [],
            'no_wa_pemohon' => $s->no_wa_pemohon,
            'wa_pickup_url' => $waUrl,
            'opsi_penandatangan' => $s->opsi_penandatangan,
            'nama_penandatangan' => $s->nama_penandatangan,
            'jabatan_penandatangan' => $s->jabatan_penandatangan,
            'nip_penandatangan' => $s->nip_penandatangan,
            'status' => $statusVal,
            'status_label' => $statusLabel,
            'alasan_penolakan' => $s->alasan_penolakan,
            'alasan_pembatalan' => $s->alasan_pembatalan,
            'pdf_url' => route('panel.persuratan.surat-keluar.pdf', $s->id),
            'created_at' => $s->created_at?->format('Y-m-d H:i'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getJenisSuratOptions(): array
    {
        return collect(JenisSurat::cases())->map(fn (JenisSurat $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'default_kode' => $case->defaultKodeKlasifikasi(),
            'allowed_kode' => $case->allowedKodeKlasifikasi(),
        ])->values()->all();
    }

    /**
     * @return array<int, array{value: string, label: string, nama: string, jabatan: string}>
     */
    protected function getPejabatOptions(?ProfilDesa $profilDesa): array
    {
        return [
            [
                'value' => 'kepala_desa',
                'label' => 'Kepala Desa (' . ($profilDesa?->nama_kepala_desa ?? 'Kepala Desa') . ')',
                'nama' => (string) ($profilDesa?->nama_kepala_desa ?? 'Kepala Desa'),
                'jabatan' => 'Kepala Desa',
            ],
            [
                'value' => 'sekdes_an',
                'label' => 'a.n. Kepala Desa - Sekretaris Desa (' . ($profilDesa?->nama_sekdes ?? 'Sekretaris Desa') . ')',
                'nama' => (string) ($profilDesa?->nama_sekdes ?? 'Sekretaris Desa'),
                'jabatan' => 'a.n. Kepala Desa - Sekretaris Desa',
            ],
        ];
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
