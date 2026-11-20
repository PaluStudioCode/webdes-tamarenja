<?php

namespace App\Http\Controllers\Panel;

use App\Enums\JenisBansos;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Exports\BansosExport;
use App\Exports\BansosTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sosial\SavePenerimaBansosRequest;
use App\Imports\BansosImport;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProfilDesa;
use App\Services\BansosKkHistoryService;
use App\Services\BansosService;
use App\Services\PdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PenerimaBansosController extends Controller
{
    public function __construct(
        protected BansosService $bansosService,
        protected BansosKkHistoryService $bansosKkHistoryService
    ) {}

    /**
     * Halaman Data Sosial (Bansos) & Pemerataan Bantuan (/panel/sosial/bansos) dengan 2 Tab.
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
        $kategoriFilter = trim((string) $request->input('kategori_bantuan', ''));
        $jenisFilter = trim((string) $request->input('jenis_bantuan', ''));
        $tahapFilter = trim((string) $request->input('tahap_penyaluran', ''));
        $statusFilter = trim((string) $request->input('status_kepesertaan', ''));
        $dusunFilter = trim((string) $request->input('dusun', ''));
        $desilFilter = trim((string) $request->input('status_kesejahteraan', ''));
        $modePemerataan = $request->input('mode_pemerataan') === 'per_individu' ? 'per_individu' : 'per_kk';

        $query = PenerimaBansos::query()
            ->with(['penduduk.kartuKeluarga'])
            ->orderByDesc('tahun_anggaran')
            ->orderByDesc('id');

        if ($request->filled('tahun_anggaran')) {
            $query->where('tahun_anggaran', (int) $request->input('tahun_anggaran'));
        }

        if ($kategoriFilter !== '') {
            $query->where('kategori_bantuan', $kategoriFilter);
        }

        if ($jenisFilter !== '') {
            $query->where('jenis_bantuan', $jenisFilter);
        }

        if ($tahapFilter !== '') {
            $query->where('tahap_penyaluran', $tahapFilter);
        }

        if ($statusFilter !== '') {
            $query->where('status_kepesertaan', $statusFilter);
        }

        if ($dusunFilter !== '') {
            $query->whereHas('penduduk.kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('dusun', $dusunFilter));
        }

        if ($search !== '') {
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('nama_kelompok', 'like', "%{$search}%")
                    ->orWhereHas('penduduk', function (Builder $pq) use ($search) {
                        $pq->where('nik', 'like', "%{$search}%")
                            ->orWhere('nama_lengkap', 'like', "%{$search}%")
                            ->orWhereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('nomor_kk', 'like', "%{$search}%"));
                    });
            });
        }

        $penerimaList = $query->get()->map(fn (PenerimaBansos $b) => $this->transformBansosRow($b))->values();

        $pemerataanFilters = [
            'dusun' => $dusunFilter,
            'status_kesejahteraan' => $desilFilter,
            'search' => $search,
        ];

        $pemerataanKk = $this->bansosService->getPemerataanBerbasisKk($tahunAnggaran, $pemerataanFilters);
        $pemerataanIndividu = $this->bansosService->getPemerataanPerIndividu($tahunAnggaran, $pemerataanFilters);

        $opsiTahap = PenerimaBansos::query()
            ->select('tahap_penyaluran')
            ->distinct()
            ->orderBy('tahap_penyaluran')
            ->pluck('tahap_penyaluran')
            ->values()
            ->all();

        if (empty($opsiTahap)) {
            $opsiTahap = ['Tahap I', 'Tahap II', 'Tahap III', 'Tahap IV'];
        }

        $dbTahun = PenerimaBansos::query()
            ->select('tahun_anggaran')
            ->distinct()
            ->pluck('tahun_anggaran')
            ->map(fn ($t) => (int) $t)
            ->all();

        $opsiTahun = collect([$tahunAnggaran - 1, $tahunAnggaran, $tahunAnggaran + 1, ...$dbTahun])
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return Inertia::render('Sosial/Bansos', [
            'tahun_anggaran' => $tahunAnggaran,
            'penerima_bansos' => $penerimaList,
            'pemerataan_kk' => $pemerataanKk,
            'pemerataan_individu' => $pemerataanIndividu,
            'mode_pemerataan' => $modePemerataan,
            'ringkasan' => [
                'total_penerima' => $penerimaList->count(),
                'total_per_kk' => $penerimaList->where('kategori_bantuan', 'per_kk')->count(),
                'total_perorangan' => $penerimaList->where('kategori_bantuan', 'perorangan')->count(),
                'total_kelompok' => $penerimaList->where('kategori_bantuan', 'kelompok')->count(),
                'total_aktif' => $penerimaList->where('status_kepesertaan', StatusPesertaBansos::Aktif->value)->count(),
                'total_graduasi' => $penerimaList->where('status_kepesertaan', StatusPesertaBansos::Graduasi->value)->count(),
                'jumlah_kk_belum_terima' => $pemerataanKk->count(),
                'jumlah_individu_belum_terima' => $pemerataanIndividu->count(),
            ],
            'filters' => [
                'search' => $search,
                'kategori_bantuan' => $kategoriFilter,
                'jenis_bantuan' => $jenisFilter,
                'tahap_penyaluran' => $tahapFilter,
                'status_kepesertaan' => $statusFilter,
                'dusun' => $dusunFilter,
                'status_kesejahteraan' => $desilFilter,
                'mode_pemerataan' => $modePemerataan,
            ],
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_jenis_bantuan' => collect(JenisBansos::cases())->map(fn (JenisBansos $c) => [
                'value' => $c->value,
                'label' => $c->label(),
                'kategori' => $c->kategori(),
                'kategori_label' => $c->kategoriLabel(),
                'default_nominal' => $c->defaultNominal(),
            ])->values()->all(),
            'opsi_status_kepesertaan' => collect(StatusPesertaBansos::cases())->map(fn (StatusPesertaBansos $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])->values()->all(),
            'opsi_tahap' => $opsiTahap,
            'opsi_tahun' => $opsiTahun,
        ]);
    }

    /**
     * Tambah atau pulihkan penerima Bansos baru (mendukung input banyak penerima sekaligus) dengan snapshot `kategori_ekonomi`.
     */
    public function store(SavePenerimaBansosRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $pendudukIds = $validated['penduduk_ids'] ?? [(int) $validated['penduduk_id']];
        $userId = $request->user()?->id;

        $count = 0;
        $anyRestored = false;

        \Illuminate\Support\Facades\DB::transaction(function () use ($pendudukIds, $validated, $userId, &$count, &$anyRestored) {
            foreach ($pendudukIds as $pid) {
                $rowPayload = [
                    ...$validated,
                    'penduduk_id' => (int) $pid,
                ];
                $result = $this->bansosService->storeOrRestore($rowPayload, $userId);
                $count++;
                if ($result['restored']) {
                    $anyRestored = true;
                }
            }
        });

        if ($count > 1) {
            $msg = sprintf('Berhasil menambahkan %d data penerima bantuan sosial baru.', $count);
        } else {
            $msg = $anyRestored
                ? 'Data penerima bantuan lama berhasil dipulihkan dan diperbarui.'
                : 'Data penerima bantuan sosial baru berhasil ditambahkan.';
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => $msg,
        ]);
    }

    /**
     * Perbarui data penerima Bansos.
     */
    public function update(SavePenerimaBansosRequest $request, PenerimaBansos $bansos): RedirectResponse
    {
        $this->bansosService->update($bansos, $request->validated());

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Data penerima bantuan sosial berhasil diperbarui.',
        ]);
    }

    /**
     * Soft-delete penerima Bansos.
     */
    public function destroy(PenerimaBansos $bansos): RedirectResponse
    {
        $bansos->delete();

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Data penerima bantuan sosial telah dihapus dari daftar aktif.',
        ]);
    }

    /**
     * Endpoint JSON pengecekan instan riwayat Bansos 1 KK (terintegrasi BansosKkHistoryService).
     */
    public function cekBansosKk(Penduduk $penduduk): JsonResponse
    {
        return response()->json(
            $this->bansosKkHistoryService->getHistoryForPenduduk($penduduk)
        );
    }

    /**
     * Unduh Template Excel Import Bansos.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new BansosTemplateExport,
            'Template_Import_Penerima_Bansos.xlsx'
        );
    }

    /**
     * Export Excel Data Penerima Bansos sesuai filter tahun/jenis/tahap/status/dusun.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahun = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        $filters = [
            'tahun_anggaran' => $tahun,
            'kategori_bantuan' => $request->input('kategori_bantuan', ''),
            'jenis_bantuan' => $request->input('jenis_bantuan', ''),
            'tahap_penyaluran' => $request->input('tahap_penyaluran', ''),
            'status_kepesertaan' => $request->input('status_kepesertaan', ''),
            'dusun' => $request->input('dusun', ''),
            'search' => $request->input('search', ''),
        ];

        $filename = sprintf('Data_Penerima_Bansos_TA_%d.xlsx', $tahun);

        return Excel::download(
            new BansosExport($filters),
            $filename
        );
    }

    /**
     * Import Excel Data Penerima Bansos (upsert with trashed + tabel error per baris).
     */
    public function importExcel(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
                'tahun_anggaran' => ['nullable', 'integer', 'between:2020,2100'],
            ],
            [
                'file.required' => 'Berkas Excel penerima Bansos wajib dipilih.',
                'file.mimes' => 'Berkas harus berformat .xlsx, .xls, atau .csv.',
                'file.max' => 'Ukuran berkas maksimal 5 MB.',
            ]
        );

        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($validated['tahun_anggaran']
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun') : null)
            ?? ($profilDesa?->tahun_anggaran_aktif ?? now()->year));

        /** @var \Illuminate\Http\UploadedFile $uploadedFile */
        $uploadedFile = $validated['file'];
        $ext = strtolower($uploadedFile->getClientOriginalExtension() ?: 'xlsx');
        $tempRelativePath = 'temp-imports/' . Str::uuid() . '.' . $ext;

        Storage::disk('private')->putFileAs(
            'temp-imports',
            $uploadedFile,
            basename($tempRelativePath)
        );

        $tempFullPath = Storage::disk('private')->path($tempRelativePath);
        $import = new BansosImport($defaultTahun, $request->user()?->id);

        try {
            Excel::import($import, $tempFullPath);
        } finally {
            Storage::disk('private')->delete($tempRelativePath);
            $realPath = $uploadedFile->getRealPath();
            if (is_string($realPath) && file_exists($realPath)) {
                @unlink($realPath);
            }
        }

        $summary = $import->getSummary();

        session()->flash('import_summary', $summary);
        session()->flash('import_errors', $summary['failed_rows']);

        if ($summary['gagal'] > 0) {
            $type = $summary['berhasil'] > 0 ? 'warning' : 'error';
            $message = sprintf(
                'Import Bansos selesai: %d baris berhasil diproses, %d baris gagal divalidasi.',
                $summary['berhasil'],
                $summary['gagal']
            );

            return back()->with('notification', [
                'type' => $type,
                'message' => $message,
            ]);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => sprintf(
                'Import Excel berhasil: %d data penerima bantuan telah diproses.',
                $summary['berhasil']
            ),
        ]);
    }

    /**
     * Cetak / Unduh PDF Daftar Hadir & Tanda Terima Penyaluran Bansos ukuran F4 Landscape.
     */
    public function cetakDaftarHadirPdf(Request $request): HttpResponse
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahun = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        $kategori = trim((string) $request->input('kategori_bantuan', ''));
        $jenis = trim((string) $request->input('jenis_bantuan', ''));
        $tahap = trim((string) $request->input('tahap_penyaluran', ''));
        $status = trim((string) $request->input('status_kepesertaan', ''));
        $dusun = trim((string) $request->input('dusun', ''));

        $query = PenerimaBansos::query()
            ->with(['penduduk.kartuKeluarga'])
            ->where('tahun_anggaran', $tahun);

        if ($kategori !== '') {
            $query->where('kategori_bantuan', $kategori);
        }

        if ($jenis !== '') {
            $query->where('jenis_bantuan', $jenis);
        }

        if ($tahap !== '') {
            $query->where('tahap_penyaluran', $tahap);
        }

        if ($status !== '') {
            $query->where('status_kepesertaan', $status);
        } else {
            // Default cetak Daftar Hadir menyaring peserta aktif kecuali diminta status tertentu
            $query->where('status_kepesertaan', StatusPesertaBansos::Aktif->value);
        }

        if ($dusun !== '') {
            $query->whereHas('penduduk.kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('dusun', $dusun));
        }

        $bansosList = $query->orderBy('jenis_bantuan')->orderBy('id')->get();

        $filterLabelKategori = match ($kategori) {
            'per_kk' => 'Bantuan Sosial Per KK',
            'perorangan' => 'Bantuan Sosial Perorangan',
            'kelompok' => 'Bantuan Kelompok Masyarakat',
            default => null,
        };

        $filterLabelJenis = null;
        if ($jenis !== '') {
            $enumJenis = JenisBansos::tryFrom($jenis);
            $filterLabelJenis = $enumJenis ? $enumJenis->label() : $jenis;
            if (! $filterLabelKategori && $enumJenis) {
                $filterLabelKategori = $enumJenis->kategoriLabel();
            }
        } elseif ($bansosList->isNotEmpty()) {
            $uniqueJenis = $bansosList->map(fn (PenerimaBansos $r) => $r->jenis_bantuan instanceof JenisBansos ? $r->jenis_bantuan->value : (string) $r->jenis_bantuan)->unique();
            if ($uniqueJenis->count() === 1) {
                $enumJenis = JenisBansos::tryFrom((string) $uniqueJenis->first());
                $filterLabelJenis = $enumJenis ? $enumJenis->label() : (string) $uniqueJenis->first();
                if (! $filterLabelKategori && $enumJenis) {
                    $filterLabelKategori = $enumJenis->kategoriLabel();
                }
            }
        }

        $resolvedTahap = $tahap !== '' ? $tahap : null;
        if (! $resolvedTahap && $bansosList->isNotEmpty()) {
            $uniqueTahap = $bansosList->pluck('tahap_penyaluran')->filter()->unique();
            if ($uniqueTahap->count() === 1) {
                $resolvedTahap = (string) $uniqueTahap->first();
            }
        }

        $logoBase64 = $this->resolveLogoBase64($profilDesa);
        $filename = sprintf('Daftar_Hadir_Penyaluran_Bansos_TA_%d.pdf', $tahun);

        $pdf = PdfService::createF4Pdf('pdf.bansos-daftar-hadir', [
            'tahun' => $tahun,
            'bansosList' => $bansosList,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
            'filterLabelKategori' => $filterLabelKategori,
            'filterLabelJenis' => $filterLabelJenis,
            'filterTahap' => $resolvedTahap,
            'filterDusun' => $dusun !== '' ? $dusun : null,
        ], 'landscape');

        return $pdf->download($filename);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformBansosRow(PenerimaBansos $b): array
    {
        $penduduk = $b->penduduk;
        $kk = $penduduk?->kartuKeluarga;

        $enumJenis = $b->jenis_bantuan instanceof JenisBansos
            ? $b->jenis_bantuan
            : JenisBansos::tryFrom((string) $b->jenis_bantuan);

        $jenisVal = $enumJenis ? $enumJenis->value : (string) $b->jenis_bantuan;
        $jenisLabel = $enumJenis ? $enumJenis->label() : $jenisVal;
        $kategoriVal = $enumJenis ? $enumJenis->kategori() : ($b->kategori_bantuan ?: 'per_kk');
        $kategoriLabel = $enumJenis ? $enumJenis->kategoriLabel() : 'Bantuan Sosial Per KK';

        $ekonomiVal = $b->kategori_ekonomi instanceof StatusKesejahteraan
            ? $b->kategori_ekonomi->value
            : (string) $b->kategori_ekonomi;
        $ekonomiLabel = $b->kategori_ekonomi instanceof StatusKesejahteraan
            ? $b->kategori_ekonomi->label()
            : $ekonomiVal;

        $statusVal = $b->status_kepesertaan instanceof StatusPesertaBansos
            ? $b->status_kepesertaan->value
            : (string) $b->status_kepesertaan;
        $statusLabel = $b->status_kepesertaan instanceof StatusPesertaBansos
            ? $b->status_kepesertaan->label()
            : $statusVal;

        return [
            'id' => $b->id,
            'penduduk_id' => $b->penduduk_id,
            'nik' => $penduduk?->nik,
            'nama_lengkap' => $penduduk?->nama_lengkap,
            'nomor_kk' => $kk?->nomor_kk,
            'dusun' => $kk?->dusun,
            'rt' => $kk?->rt,
            'rw' => $kk?->rw,
            'kategori_bantuan' => $kategoriVal,
            'kategori_bantuan_label' => $kategoriLabel,
            'nama_kelompok' => $b->nama_kelompok,
            'jenis_bantuan' => $jenisVal,
            'jenis_bantuan_label' => $jenisLabel,
            'bentuk_nominal' => $b->bentuk_nominal,
            'tahun_anggaran' => (int) $b->tahun_anggaran,
            'tahap_penyaluran' => $b->tahap_penyaluran,
            'kategori_ekonomi' => $ekonomiVal,
            'kategori_ekonomi_label' => $ekonomiLabel,
            'status_kepesertaan' => $statusVal,
            'status_kepesertaan_label' => $statusLabel,
            'keterangan' => $b->keterangan,
        ];
    }

    protected function resolveLogoBase64(?ProfilDesa $profilDesa): ?string
    {
        return PdfService::resolveLogoBase64($profilDesa);
    }
}
