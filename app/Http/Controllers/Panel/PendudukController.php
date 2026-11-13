<?php

namespace App\Http\Controllers\Panel;

use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Exports\PendudukExport;
use App\Exports\PendudukTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kependudukan\MutasiPendudukRequest;
use App\Http\Requests\Kependudukan\StorePendudukRequest;
use App\Http\Requests\Kependudukan\UpdatePendudukRequest;
use App\Imports\PendudukImport;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\ProfilDesa;
use App\Services\KependudukanService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PendudukController extends Controller
{
    public function __construct(
        protected KependudukanService $kependudukanService
    ) {}

    /**
     * Halaman terpadu Master Data Kependudukan (/panel/kependudukan) dengan 2 Tab Kontekstual.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $dusun = trim((string) $request->input('dusun', ''));
        $statusKependudukan = trim((string) $request->input('status_kependudukan', ''));
        $statusKesejahteraan = trim((string) $request->input('status_kesejahteraan', ''));
        $activeTab = in_array($request->input('tab'), ['penduduk', 'kk'], true)
            ? (string) $request->input('tab')
            : 'penduduk';

        // 1. Query Daftar Penduduk
        $pendudukQuery = Penduduk::query()
            ->with([
                'kartuKeluarga.penduduk',
                'suratKeluar' => fn ($q) => $q
                    ->where('jenis_surat', JenisSurat::Kematian)
                    ->where('status', StatusSuratKeluar::Terbit),
            ])
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('nomor_kk', 'like', "%{$search}%"));
                });
            })
            ->when($dusun !== '', function (Builder $q) use ($dusun) {
                $q->whereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('dusun', $dusun));
            })
            ->when($statusKependudukan !== '', function (Builder $q) use ($statusKependudukan) {
                $q->where('status_kependudukan', $statusKependudukan);
            })
            ->when($statusKesejahteraan !== '', function (Builder $q) use ($statusKesejahteraan) {
                $q->where('status_kesejahteraan', $statusKesejahteraan);
            })
            ->orderBy('nama_lengkap');

        $pendudukPaginator = $pendudukQuery
            ->paginate(15, ['*'], 'penduduk_page')
            ->withQueryString()
            ->through(fn (Penduduk $p) => $this->transformPendudukRow($p));

        // 2. Query Daftar Kartu Keluarga
        $kkQuery = KartuKeluarga::query()
            ->with([
                'penduduk' => fn ($q) => $q->with([
                    'suratKeluar' => fn ($sq) => $sq
                        ->where('jenis_surat', JenisSurat::Kematian)
                        ->where('status', StatusSuratKeluar::Terbit),
                ])->orderByRaw("
                    CASE hubungan_keluarga
                        WHEN 'kepala_keluarga' THEN 1
                        WHEN 'istri' THEN 2
                        WHEN 'anak' THEN 3
                        ELSE 4
                    END ASC
                ")->orderBy('tanggal_lahir'),
            ])
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('nomor_kk', 'like', "%{$search}%")
                        ->orWhere('alamat', 'like', "%{$search}%")
                        ->orWhereHas('penduduk', fn (Builder $pQ) => $pQ
                            ->where('nama_lengkap', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%"));
                });
            })
            ->when($dusun !== '', fn (Builder $q) => $q->where('dusun', $dusun))
            ->when($statusKependudukan !== '', function (Builder $q) use ($statusKependudukan) {
                $q->whereHas('penduduk', fn (Builder $pQ) => $pQ->where('status_kependudukan', $statusKependudukan));
            })
            ->when($statusKesejahteraan !== '', function (Builder $q) use ($statusKesejahteraan) {
                $q->where(function (Builder $sub) use ($statusKesejahteraan) {
                    $sub->where('status_kesejahteraan', $statusKesejahteraan)
                        ->orWhereHas('penduduk', fn (Builder $pQ) => $pQ->where('status_kesejahteraan', $statusKesejahteraan));
                });
            })
            ->orderBy('nomor_kk');

        $kkPaginator = $kkQuery
            ->paginate(15, ['*'], 'kk_page')
            ->withQueryString()
            ->through(fn (KartuKeluarga $kk) => $this->transformKartuKeluargaRow($kk));

        // 3. Opsi KK untuk dropdown form Tambah/Edit Penduduk
        $kkOptions = KartuKeluarga::query()
            ->with('kepalaKeluarga')
            ->orderBy('nomor_kk')
            ->get()
            ->map(function (KartuKeluarga $kk) {
                $kkKesejahteraan = $kk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $kk->status_kesejahteraan->value
                    : (string) ($kk->status_kesejahteraan ?: StatusKesejahteraan::NonPrioritas->value);

                return [
                    'id' => $kk->id,
                    'nomor_kk' => $kk->nomor_kk,
                    'dusun' => $kk->dusun,
                    'rt' => $kk->rt,
                    'rw' => $kk->rw,
                    'alamat' => $kk->alamat,
                    'status_kesejahteraan' => $kkKesejahteraan,
                    'kepala_keluarga_nama' => $kk->kepalaKeluarga?->nama_lengkap,
                    'label' => $kk->kepalaKeluarga
                        ? "{$kk->nomor_kk} — {$kk->kepalaKeluarga->nama_lengkap} ({$kk->dusun})"
                        : "{$kk->nomor_kk} ({$kk->dusun})",
                ];
            })
            ->values();

        // 4. Daftar Dusun terstandarisasi
        $profilDusun = ProfilDesa::query()->value('daftar_dusun') ?? [];
        $kkDusun = KartuKeluarga::query()
            ->select('dusun')
            ->distinct()
            ->pluck('dusun')
            ->filter()
            ->values()
            ->all();
        $daftarDusun = array_values(array_unique(array_filter([...$profilDusun, ...$kkDusun])));

        // 5. Ringkasan Metrik Kependudukan
        $allKkWithMembers = KartuKeluarga::query()->with('penduduk')->get();
        $jumlahKkPerluUpdateKepala = $allKkWithMembers
            ->filter(fn (KartuKeluarga $kk) => $this->kependudukanService->needsKepalaKeluargaUpdate($kk))
            ->count();

        $ringkasan = [
            'total_penduduk_aktif' => Penduduk::query()->aktif()->count(),
            'total_kk_aktif' => KartuKeluarga::query()->aktif()->count(),
            'total_kk' => KartuKeluarga::query()->count(),
            'total_prioritas_dtsen_aktif' => Penduduk::query()->aktif()->prioritasDtsen()->count(),
            'total_mutasi' => Penduduk::query()
                ->whereIn('status_kependudukan', [
                    StatusKependudukan::MeninggalDunia,
                    StatusKependudukan::PindahKeluar,
                ])
                ->count(),
            'jumlah_kk_perlu_update_kepala' => $jumlahKkPerluUpdateKepala,
        ];

        return Inertia::render('Kependudukan/Index', [
            'tab' => $activeTab,
            'filters' => [
                'search' => $search,
                'dusun' => $dusun,
                'status_kependudukan' => $statusKependudukan,
                'status_kesejahteraan' => $statusKesejahteraan,
                'tab' => $activeTab,
            ],
            'penduduk' => $pendudukPaginator,
            'kartu_keluarga' => $kkPaginator,
            'kartu_keluarga_options' => $kkOptions,
            'daftar_dusun' => $daftarDusun,
            'ringkasan' => $ringkasan,
        ]);
    }

    /**
     * Detail lengkap 1 warga beserta anggota 1 KK & riwayat Bansos untuk SlideOverDrawer.
     */
    public function show(Penduduk $penduduk): JsonResponse
    {
        $penduduk->load([
            'kartuKeluarga.penduduk' => fn ($q) => $q->orderByRaw("
                CASE hubungan_keluarga
                    WHEN 'kepala_keluarga' THEN 1
                    WHEN 'istri' THEN 2
                    WHEN 'anak' THEN 3
                    ELSE 4
                END ASC
            ")->orderBy('tanggal_lahir'),
            'penerimaBansos' => fn ($q) => $q->orderByDesc('tahun_anggaran')->orderByDesc('id'),
            'suratKeluar' => fn ($q) => $q
                ->where('jenis_surat', JenisSurat::Kematian)
                ->where('status', StatusSuratKeluar::Terbit),
        ]);

        return response()->json([
            'data' => $this->transformPendudukRow($penduduk, true),
        ]);
    }

    /**
     * Endpoint pencarian cepat JSON untuk komponen NikSearchAutocomplete (/panel/kependudukan/cari-nik).
     */
    public function searchNik(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json(['data' => []]);
        }

        $statusParam = $request->input('status_kependudukan');
        $jenisSurat = (string) $request->input('jenis_surat', '');
        $includeMeninggal = $request->boolean('include_meninggal') || $jenisSurat === JenisSurat::Kematian->value;
        $allStatus = $request->boolean('all_status');

        $query = Penduduk::query()
            ->with(['kartuKeluarga.penduduk', 'penerimaBansos'])
            ->where(function (Builder $sub) use ($q) {
                $sub->where('nik', 'like', "%{$q}%")
                    ->orWhere('nama_lengkap', 'like', "%{$q}%")
                    ->orWhereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('nomor_kk', 'like', "%{$q}%"));
            });

        if (! $allStatus) {
            if (is_array($statusParam) && count($statusParam) > 0) {
                $query->whereIn('status_kependudukan', $statusParam);
            } elseif (is_string($statusParam) && trim($statusParam) !== '') {
                $statuses = array_map('trim', explode(',', $statusParam));
                $query->whereIn('status_kependudukan', $statuses);
            } elseif ($includeMeninggal) {
                // Loket Internal Surat Kematian mengizinkan subjek Aktif maupun Meninggal Dunia yang belum punya Surat Kematian Terbit
                $query->whereIn('status_kependudukan', [
                    StatusKependudukan::Aktif->value,
                    StatusKependudukan::MeninggalDunia->value,
                ])->whereDoesntHave('suratKeluar', function (Builder $skQ) {
                    $skQ->where('jenis_surat', JenisSurat::Kematian->value)
                        ->where('status', StatusSuratKeluar::Terbit->value);
                });
            } else {
                $query->where('status_kependudukan', StatusKependudukan::Aktif->value);
            }
        }

        $items = $query
            ->orderBy('nama_lengkap')
            ->limit(15)
            ->get()
            ->map(function (Penduduk $p) {
                $kk = $p->kartuKeluarga;
                $statusKesejahteraan = $p->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $p->status_kesejahteraan->value
                    : (string) $p->status_kesejahteraan;
                $statusKependudukan = $p->status_kependudukan instanceof StatusKependudukan
                    ? $p->status_kependudukan->value
                    : (string) $p->status_kependudukan;

                return [
                    'id' => $p->id,
                    'kartu_keluarga_id' => $p->kartu_keluarga_id,
                    'nik' => $p->nik,
                    'nama_lengkap' => $p->nama_lengkap,
                    'tempat_lahir' => $p->tempat_lahir,
                    'tanggal_lahir' => $p->tanggal_lahir?->format('Y-m-d'),
                    'jenis_kelamin' => $p->jenis_kelamin,
                    'agama' => $p->agama,
                    'suku' => $p->suku,
                    'kewarganegaraan' => $p->kewarganegaraan ?: 'WNI',
                    'pendidikan' => $p->pendidikan,
                    'peserta_kip_pip' => $p->peserta_kip_pip,
                    'kelas_sekolah' => $p->kelas_sekolah,
                    'pekerjaan' => $p->pekerjaan,
                    'status_perkawinan' => $p->status_perkawinan,
                    'hubungan_keluarga' => $p->hubungan_keluarga,
                    'status_kesejahteraan' => $statusKesejahteraan,
                    'jenis_bpjs' => $p->jenis_bpjs,
                    'status_kependudukan' => $statusKependudukan,
                    'nomor_kk' => $kk?->nomor_kk,
                    'dusun' => $kk?->dusun,
                    'rt' => $kk?->rt,
                    'rw' => $kk?->rw,
                    'alamat' => $kk?->alamat,
                    'kartu_keluarga' => $kk ? [
                        'id' => $kk->id,
                        'nomor_kk' => $kk->nomor_kk,
                        'dusun' => $kk->dusun,
                        'rt' => $kk->rt,
                        'rw' => $kk->rw,
                        'alamat' => $kk->alamat,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'data' => $items,
        ]);
    }

    /**
     * Simpan data penduduk baru atau pulihkan NIK yang pernah di-soft-delete.
     */
    public function store(StorePendudukRequest $request): RedirectResponse
    {
        $result = $this->kependudukanService->storeOrRestorePenduduk($request->validated());

        $baseMsg = $result['restored']
            ? 'Data penduduk lama berhasil dipulihkan dan diperbarui.'
            : 'Data penduduk baru berhasil ditambahkan.';

        if ($result['reminder'] !== null) {
            session()->flash('kepala_keluarga_reminder', $result['reminder']);

            return back()->with('notification', [
                'type' => 'warning',
                'message' => "{$baseMsg} {$result['reminder']}",
            ]);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => $baseMsg,
        ]);
    }

    /**
     * Perbarui biodata penduduk (beserta aturan transisi bila status_kependudukan diubah).
     */
    public function update(UpdatePendudukRequest $request, Penduduk $penduduk): RedirectResponse
    {
        $result = $this->kependudukanService->updatePenduduk($penduduk, $request->validated());

        $baseMsg = 'Data penduduk berhasil diperbarui.';

        if ($result['reminder'] !== null) {
            session()->flash('kepala_keluarga_reminder', $result['reminder']);

            return back()->with('notification', [
                'type' => 'warning',
                'message' => "{$baseMsg} {$result['reminder']}",
            ]);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => $baseMsg,
        ]);
    }

    /**
     * Endpoint khusus mutasi / reaktivasi / koreksi status kependudukan warga.
     */
    public function mutasi(MutasiPendudukRequest $request, Penduduk $penduduk): RedirectResponse
    {
        $validated = $request->validated();

        $result = $this->kependudukanService->mutasiPenduduk(
            $penduduk,
            $validated['status_kependudukan'],
            $validated['tanggal_mutasi'] ?? null,
            $validated['catatan_mutasi'] ?? null
        );

        $updated = $result['model'];
        $statusLabel = $updated->status_kependudukan instanceof StatusKependudukan
            ? $updated->status_kependudukan->label()
            : (string) $updated->status_kependudukan;

        $baseMsg = "Status kependudukan {$updated->nama_lengkap} berhasil diperbarui menjadi {$statusLabel}.";

        if ($result['reminder'] !== null) {
            session()->flash('kepala_keluarga_reminder', $result['reminder']);

            return back()->with('notification', [
                'type' => 'warning',
                'message' => "{$baseMsg} {$result['reminder']}",
            ]);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => $baseMsg,
        ]);
    }

    /**
     * Soft delete data penduduk.
     */
    public function destroy(Penduduk $penduduk): RedirectResponse
    {
        $kk = $penduduk->kartuKeluarga;
        $penduduk->delete();

        if ($kk !== null) {
            $kk->load('penduduk');
            $reminder = $this->kependudukanService->getKepalaKeluargaWarning($kk);
            if ($reminder !== null) {
                session()->flash('kepala_keluarga_reminder', $reminder);
            }
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Data penduduk berhasil dihapus.',
        ]);
    }

    /**
     * Unduh Template Excel standar SIAK/Prodeskel untuk Import Penduduk & KK.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return Excel::download(
            new PendudukTemplateExport,
            'Template_Import_Penduduk_SIAK.xlsx'
        );
    }

    /**
     * Export data penduduk ke Excel sesuai filter Dusun, Status Kependudukan, & Kesejahteraan DTSEN.
     */
    public function exportExcel(Request $request): BinaryFileResponse
    {
        $filters = [
            'search' => $request->input('search', ''),
            'dusun' => $request->input('dusun', ''),
            'status_kependudukan' => $request->input('status_kependudukan', ''),
            'status_kesejahteraan' => $request->input('status_kesejahteraan', ''),
        ];

        return Excel::download(
            new PendudukExport($filters),
            'Data_Penduduk_Tamarenja.xlsx'
        );
    }

    /**
     * Import massal Excel data Penduduk & KK (upsert with trashed + ringkasan baris gagal + hapus file sementara).
     */
    public function importExcel(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
                'mode' => ['nullable', 'string', 'in:preview,commit'],
            ],
            [
                'file.required' => 'Berkas Excel wajib dipilih.',
                'file.mimes' => 'Berkas harus berformat .xlsx, .xls, atau .csv.',
                'file.max' => 'Ukuran berkas maksimal 5 MB.',
            ]
        );

        $isPreview = ($validated['mode'] ?? 'commit') === 'preview';

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
        $import = new PendudukImport(dryRun: $isPreview);

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
        $summary['is_preview'] = $isPreview;

        session()->flash('import_summary', $summary);
        session()->flash('import_errors', $summary['failed_rows']);

        if ($isPreview) {
            return back();
        }

        if ($summary['gagal'] > 0) {
            $type = $summary['berhasil'] > 0 ? 'warning' : 'error';
            $message = sprintf(
                'Import selesai: %d baris berhasil diproses, %d baris gagal divalidasi.',
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
                'Import Excel berhasil: %d data penduduk telah diproses.',
                $summary['berhasil']
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformPendudukRow(Penduduk $p, bool $includeBansos = false): array
    {
        $kk = $p->kartuKeluarga;
        $statusKesejahteraan = $p->status_kesejahteraan instanceof StatusKesejahteraan
            ? $p->status_kesejahteraan->value
            : (string) $p->status_kesejahteraan;
        $statusKependudukan = $p->status_kependudukan instanceof StatusKependudukan
            ? $p->status_kependudukan->value
            : (string) $p->status_kependudukan;

        $hasTerbitSuratKematian = $p->relationLoaded('suratKeluar')
            ? $p->suratKeluar->isNotEmpty()
            : $p->suratKeluar()
                ->where('jenis_surat', JenisSurat::Kematian)
                ->where('status', StatusSuratKeluar::Terbit)
                ->exists();

        $kkWarning = $kk ? $this->kependudukanService->getKepalaKeluargaWarning($kk) : null;

        $row = [
            'id' => $p->id,
            'kartu_keluarga_id' => $p->kartu_keluarga_id,
            'nik' => $p->nik,
            'nama_lengkap' => $p->nama_lengkap,
            'tempat_lahir' => $p->tempat_lahir,
            'tanggal_lahir' => $p->tanggal_lahir?->format('Y-m-d'),
            'jenis_kelamin' => $p->jenis_kelamin,
            'agama' => $p->agama,
            'suku' => $p->suku,
            'kewarganegaraan' => $p->kewarganegaraan ?: 'WNI',
            'pendidikan' => $p->pendidikan,
            'peserta_kip_pip' => $p->peserta_kip_pip,
            'kelas_sekolah' => $p->kelas_sekolah,
            'pekerjaan' => $p->pekerjaan,
            'status_perkawinan' => $p->status_perkawinan,
            'hubungan_keluarga' => $p->hubungan_keluarga,
            'status_kesejahteraan' => $statusKesejahteraan,
            'jenis_bpjs' => $p->jenis_bpjs,
            'status_kependudukan' => $statusKependudukan,
            'tanggal_mutasi' => $p->tanggal_mutasi?->format('Y-m-d'),
            'catatan_mutasi' => $p->catatan_mutasi,
            'has_terbit_surat_kematian' => $hasTerbitSuratKematian,
            'nomor_kk' => $kk?->nomor_kk,
            'dusun' => $kk?->dusun,
            'rt' => $kk?->rt,
            'rw' => $kk?->rw,
            'alamat' => $kk?->alamat,
            'perlu_update_kepala_keluarga' => $kkWarning !== null,
            'peringatan_kepala_keluarga' => $kkWarning,
            'kartu_keluarga' => $kk ? $this->transformKartuKeluargaRow($kk) : null,
        ];

        if ($includeBansos && $p->relationLoaded('penerimaBansos')) {
            $row['riwayat_bansos'] = $p->penerimaBansos->map(fn ($b) => [
                'id' => $b->id,
                'jenis_bantuan' => $b->jenis_bantuan->value ?? (string) $b->jenis_bantuan,
                'bentuk_nominal' => $b->bentuk_nominal,
                'tahun_anggaran' => $b->tahun_anggaran,
                'tahap_penyaluran' => $b->tahap_penyaluran,
                'status_kepesertaan' => $b->status_kepesertaan instanceof StatusPesertaBansos
                    ? $b->status_kepesertaan->value
                    : (string) $b->status_kepesertaan,
            ])->values();
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformKartuKeluargaRow(KartuKeluarga $kk): array
    {
        $members = $kk->relationLoaded('penduduk') ? $kk->penduduk : $kk->penduduk()->get();
        $kepala = $members->firstWhere('hubungan_keluarga', 'kepala_keluarga');
        $warning = $this->kependudukanService->getKepalaKeluargaWarning($kk);
        $kkKesejahteraan = $kk->status_kesejahteraan instanceof StatusKesejahteraan
            ? $kk->status_kesejahteraan->value
            : (string) ($kk->status_kesejahteraan ?: StatusKesejahteraan::NonPrioritas->value);

        return [
            'id' => $kk->id,
            'nomor_kk' => $kk->nomor_kk,
            'dusun' => $kk->dusun,
            'rt' => $kk->rt,
            'rw' => $kk->rw,
            'alamat' => $kk->alamat,
            'status_kesejahteraan' => $kkKesejahteraan,
            'status_kepemilikan_rumah' => $kk->status_kepemilikan_rumah,
            'kondisi_rumah' => $kk->kondisi_rumah,
            'jenis_listrik' => $kk->jenis_listrik,
            'kamar_mandi' => $kk->kamar_mandi,
            'penghasilan_bulanan' => $kk->penghasilan_bulanan,
            'kepemilikan_usaha' => $kk->kepemilikan_usaha,
            'kepemilikan_hewan_ternak' => $kk->kepemilikan_hewan_ternak,
            'kepemilikan_kendaraan_elektronik' => $kk->kepemilikan_kendaraan_elektronik,
            'kepemilikan_shml' => $kk->kepemilikan_shml,
            'kepemilikan_tanah_bidang' => $kk->kepemilikan_tanah_bidang,
            'kepemilikan_tanah_sawah' => $kk->kepemilikan_tanah_sawah,
            'tanaman' => $kk->tanaman,
            'kepala_keluarga' => $kepala ? [
                'id' => $kepala->id,
                'nik' => $kepala->nik,
                'nama_lengkap' => $kepala->nama_lengkap,
                'status_kependudukan' => $kepala->status_kependudukan instanceof StatusKependudukan
                    ? $kepala->status_kependudukan->value
                    : (string) $kepala->status_kependudukan,
            ] : null,
            'jumlah_anggota' => $members->count(),
            'jumlah_anggota_aktif' => $members->filter(function (Penduduk $m) {
                $st = $m->status_kependudukan instanceof StatusKependudukan
                    ? $m->status_kependudukan->value
                    : (string) $m->status_kependudukan;

                return $st === StatusKependudukan::Aktif->value;
            })->count(),
            'perlu_update_kepala_keluarga' => $warning !== null,
            'peringatan_kepala_keluarga' => $warning,
            'anggota' => $members->map(fn (Penduduk $m) => [
                'id' => $m->id,
                'kartu_keluarga_id' => $m->kartu_keluarga_id,
                'nik' => $m->nik,
                'nama_lengkap' => $m->nama_lengkap,
                'tempat_lahir' => $m->tempat_lahir,
                'tanggal_lahir' => $m->tanggal_lahir?->format('Y-m-d'),
                'jenis_kelamin' => $m->jenis_kelamin,
                'agama' => $m->agama,
                'suku' => $m->suku,
                'kewarganegaraan' => $m->kewarganegaraan ?: 'WNI',
                'pendidikan' => $m->pendidikan,
                'peserta_kip_pip' => $m->peserta_kip_pip,
                'kelas_sekolah' => $m->kelas_sekolah,
                'pekerjaan' => $m->pekerjaan,
                'status_perkawinan' => $m->status_perkawinan,
                'hubungan_keluarga' => $m->hubungan_keluarga,
                'status_kesejahteraan' => $m->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $m->status_kesejahteraan->value
                    : (string) $m->status_kesejahteraan,
                'jenis_bpjs' => $m->jenis_bpjs,
                'status_kependudukan' => $m->status_kependudukan instanceof StatusKependudukan
                    ? $m->status_kependudukan->value
                    : (string) $m->status_kependudukan,
                'tanggal_mutasi' => $m->tanggal_mutasi?->format('Y-m-d'),
                'catatan_mutasi' => $m->catatan_mutasi,
                'has_terbit_surat_kematian' => $m->relationLoaded('suratKeluar')
                    ? $m->suratKeluar->isNotEmpty()
                    : $m->suratKeluar()
                        ->where('jenis_surat', JenisSurat::Kematian)
                        ->where('status', StatusSuratKeluar::Terbit)
                        ->exists(),
                'nomor_kk' => $kk->nomor_kk,
                'dusun' => $kk->dusun,
                'rt' => $kk->rt,
                'rw' => $kk->rw,
                'alamat' => $kk->alamat,
                'perlu_update_kepala_keluarga' => $warning !== null,
                'peringatan_kepala_keluarga' => $warning,
            ])->values(),
        ];
    }
}
