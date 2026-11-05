<?php

namespace App\Http\Controllers\Panel;

use App\Enums\KategoriKegiatan;
use App\Enums\StatusPublikasi;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sosial\SaveKegiatanDesaRequest;
use App\Models\KegiatanDesa;
use App\Models\KegiatanDesaFoto;
use App\Models\ProfilDesa;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KegiatanDesaController extends Controller
{
    public function __construct(
        protected ImageUploadService $imageUploadService
    ) {}

    /**
     * Halaman Daftar Dokumentasi Kegiatan Desa (/panel/sosial/kegiatan).
     */
    public function index(Request $request): Response
    {
        $profilDesa = ProfilDesa::singleton();
        $search = trim((string) $request->input('search', ''));
        $kategoriFilter = trim((string) $request->input('kategori', ''));
        $statusFilter = trim((string) $request->input('status_publikasi', ''));

        $query = KegiatanDesa::query()
            ->with(['foto', 'pembuat:id,name'])
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('id');

        if ($kategoriFilter !== '') {
            $query->where('kategori', $kategoriFilter);
        }

        if ($statusFilter !== '') {
            $query->where('status_publikasi', $statusFilter);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhere('lokasi_dusun', 'like', "%{$search}%")
                    ->orWhere('uraian', 'like', "%{$search}%");
            });
        }

        $items = $query->get()->map(fn (KegiatanDesa $k) => $this->transformKegiatan($k))->values();

        return Inertia::render('Sosial/KegiatanIndex', [
            'kegiatan' => $items,
            'filters' => [
                'search' => $search,
                'kategori' => $kategoriFilter,
                'status_publikasi' => $statusFilter,
            ],
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_kategori' => $this->getKategoriOptions(),
            'opsi_status_publikasi' => $this->getStatusPublikasiOptions(),
        ]);
    }

    /**
     * Form Buat Dokumentasi Kegiatan Desa (/panel/sosial/kegiatan/buat).
     */
    public function create(): Response
    {
        $profilDesa = ProfilDesa::singleton();

        return Inertia::render('Sosial/KegiatanForm', [
            'is_edit' => false,
            'kegiatan' => null,
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_kategori' => $this->getKategoriOptions(),
            'opsi_status_publikasi' => $this->getStatusPublikasiOptions(),
            'tanggal_hari_ini' => now()->toDateString(),
        ]);
    }

    /**
     * Simpan dokumentasi kegiatan desa baru beserta unggahan 1-5 foto terkompresi.
     */
    public function store(SaveKegiatanDesaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tahun = (int) Carbon::parse($validated['tanggal_kegiatan'])->year;
        $statusPublikasi = (string) $validated['status_publikasi'];
        $newFiles = $request->file('foto_baru', []);

        $uploadedPaths = [];
        foreach ($newFiles as $file) {
            $uploadedPaths[] = $this->imageUploadService->uploadFieldPhoto(
                $file,
                "kegiatan/{$tahun}"
            );
        }

        try {
            DB::transaction(function () use ($validated, $statusPublikasi, $uploadedPaths, $request) {
                $slug = $this->generateUniqueSlug($validated['judul']);

                $kegiatan = KegiatanDesa::create([
                    'judul' => $validated['judul'],
                    'slug' => $slug,
                    'kategori' => $validated['kategori'],
                    'tanggal_kegiatan' => $validated['tanggal_kegiatan'],
                    'lokasi_dusun' => $validated['lokasi_dusun'],
                    'uraian' => $validated['uraian'],
                    'status_publikasi' => $statusPublikasi,
                    'published_at' => $statusPublikasi === StatusPublikasi::Published->value ? now() : null,
                    'created_by' => $request->user()?->id,
                ]);

                foreach ($uploadedPaths as $index => $path) {
                    KegiatanDesaFoto::create([
                        'kegiatan_desa_id' => $kegiatan->id,
                        'foto_path' => $path,
                        'urutan' => $index + 1,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($uploadedPaths as $path) {
                $this->imageUploadService->deleteIfExists($path, 'public');
            }

            throw $e;
        }

        $msg = $statusPublikasi === StatusPublikasi::Published->value
            ? 'Dokumentasi kegiatan berhasil diterbitkan ke Halaman Publik.'
            : 'Dokumentasi kegiatan berhasil disimpan sebagai Draft.';

        return redirect()
            ->route('panel.sosial.kegiatan.index')
            ->with('notification', [
                'type' => 'success',
                'message' => $msg,
            ]);
    }

    /**
     * Form Edit Dokumentasi Kegiatan Desa (/panel/sosial/kegiatan/{kegiatan}/edit).
     */
    public function edit(KegiatanDesa $kegiatan): Response
    {
        $kegiatan->load('foto');
        $profilDesa = ProfilDesa::singleton();

        return Inertia::render('Sosial/KegiatanForm', [
            'is_edit' => true,
            'kegiatan' => $this->transformKegiatan($kegiatan),
            'daftar_dusun' => $profilDesa?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III'],
            'opsi_kategori' => $this->getKategoriOptions(),
            'opsi_status_publikasi' => $this->getStatusPublikasiOptions(),
            'tanggal_hari_ini' => now()->toDateString(),
        ]);
    }

    /**
     * Perbarui dokumentasi kegiatan desa, kelola urutan/penghapusan foto lama,
     * unggah foto baru, dan bersihkan berkas foto yang dihapus dari storage.
     */
    public function update(SaveKegiatanDesaRequest $request, KegiatanDesa $kegiatan): RedirectResponse
    {
        $validated = $request->validated();
        $tahun = (int) Carbon::parse($validated['tanggal_kegiatan'])->year;
        $statusPublikasi = (string) $validated['status_publikasi'];
        $newFiles = $request->file('foto_baru', []);

        $existingPhotos = $kegiatan->foto()->orderBy('urutan')->get();
        $existingIds = $existingPhotos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $hapusIds = collect($request->input('hapus_foto_ids', []))->map(fn ($id) => (int) $id)->all();

        if ($request->has('existing_foto_ids') && is_array($request->input('existing_foto_ids'))) {
            $keptOrderedIds = collect($request->input('existing_foto_ids'))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $existingIds, true) && ! in_array($id, $hapusIds, true))
                ->unique()
                ->values()
                ->all();
        } else {
            $keptOrderedIds = array_values(array_diff($existingIds, $hapusIds));
        }

        $photosToDelete = $existingPhotos->filter(
            fn (KegiatanDesaFoto $foto) => ! in_array((int) $foto->id, $keptOrderedIds, true)
        );
        $pathsToDeleteAfterCommit = $photosToDelete->pluck('foto_path')->all();

        $uploadedNewPaths = [];
        foreach ($newFiles as $file) {
            $uploadedNewPaths[] = $this->imageUploadService->uploadFieldPhoto(
                $file,
                "kegiatan/{$tahun}"
            );
        }

        try {
            DB::transaction(function () use (
                $kegiatan,
                $validated,
                $statusPublikasi,
                $keptOrderedIds,
                $photosToDelete,
                $uploadedNewPaths
            ) {
                $slug = $kegiatan->judul !== $validated['judul']
                    ? $this->generateUniqueSlug($validated['judul'], $kegiatan->id)
                    : $kegiatan->slug;

                $publishedAt = $statusPublikasi === StatusPublikasi::Published->value
                    ? ($kegiatan->published_at ?? now())
                    : null;

                $kegiatan->update([
                    'judul' => $validated['judul'],
                    'slug' => $slug,
                    'kategori' => $validated['kategori'],
                    'tanggal_kegiatan' => $validated['tanggal_kegiatan'],
                    'lokasi_dusun' => $validated['lokasi_dusun'],
                    'uraian' => $validated['uraian'],
                    'status_publikasi' => $statusPublikasi,
                    'published_at' => $publishedAt,
                ]);

                if ($photosToDelete->isNotEmpty()) {
                    KegiatanDesaFoto::query()
                        ->whereIn('id', $photosToDelete->pluck('id')->all())
                        ->delete();
                }

                $urutan = 1;
                foreach ($keptOrderedIds as $fotoId) {
                    KegiatanDesaFoto::query()
                        ->where('id', $fotoId)
                        ->where('kegiatan_desa_id', $kegiatan->id)
                        ->update(['urutan' => $urutan++]);
                }

                foreach ($uploadedNewPaths as $path) {
                    KegiatanDesaFoto::create([
                        'kegiatan_desa_id' => $kegiatan->id,
                        'foto_path' => $path,
                        'urutan' => $urutan++,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($uploadedNewPaths as $path) {
                $this->imageUploadService->deleteIfExists($path, 'public');
            }

            throw $e;
        }

        foreach ($pathsToDeleteAfterCommit as $oldPath) {
            $this->imageUploadService->deleteIfExists($oldPath, 'public');
        }

        return redirect()
            ->route('panel.sosial.kegiatan.index')
            ->with('notification', [
                'type' => 'success',
                'message' => 'Dokumentasi kegiatan desa berhasil diperbarui.',
            ]);
    }

    /**
     * Transisi cepat status publikasi kegiatan (`draft` <-> `published`).
     */
    public function togglePublikasi(Request $request, KegiatanDesa $kegiatan): RedirectResponse
    {
        $validated = $request->validate([
            'status_publikasi' => ['nullable', Rule::enum(StatusPublikasi::class)],
        ]);

        $currentStatus = $kegiatan->status_publikasi instanceof StatusPublikasi
            ? $kegiatan->status_publikasi->value
            : (string) $kegiatan->status_publikasi;

        $targetStatus = $validated['status_publikasi']
            ?? ($currentStatus === StatusPublikasi::Published->value
                ? StatusPublikasi::Draft->value
                : StatusPublikasi::Published->value);

        if ($targetStatus === StatusPublikasi::Published->value && $kegiatan->foto()->count() < 1) {
            throw ValidationException::withMessages([
                'status_publikasi' => 'Minimal 1 foto dokumentasi wajib diunggah sebelum kegiatan diterbitkan ke publik.',
            ]);
        }

        $kegiatan->update([
            'status_publikasi' => $targetStatus,
            'published_at' => $targetStatus === StatusPublikasi::Published->value
                ? ($kegiatan->published_at ?? now())
                : null,
        ]);

        $msg = $targetStatus === StatusPublikasi::Published->value
            ? 'Kegiatan berhasil diterbitkan ke Halaman Publik.'
            : 'Kegiatan berhasil ditarik kembali ke status Draft.';

        return back()->with('notification', [
            'type' => 'success',
            'message' => $msg,
        ]);
    }

    /**
     * Soft-delete dokumentasi kegiatan desa beserta pembersihan berkas fisik foto galeri.
     */
    public function destroy(KegiatanDesa $kegiatan): RedirectResponse
    {
        $photoPaths = $kegiatan->foto()->pluck('foto_path')->all();

        DB::transaction(function () use ($kegiatan) {
            $kegiatan->foto()->delete();
            $kegiatan->delete();
        });

        foreach ($photoPaths as $path) {
            $this->imageUploadService->deleteIfExists($path, 'public');
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Dokumentasi kegiatan desa telah dihapus.',
        ]);
    }

    /**
     * Buat slug ramah SEO yang unik terhadap seluruh rekaman termasuk soft-deleted (`withTrashed()`).
     * Menambahkan sufiks `-2`, `-3`, dst. apabila sudah ada.
     */
    public function generateUniqueSlug(string $judul, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($judul);
        if ($baseSlug === '') {
            $baseSlug = 'kegiatan-desa';
        }

        $baseSlug = Str::limit($baseSlug, 200, '');
        $candidate = $baseSlug;
        $counter = 2;

        while (
            KegiatanDesa::withTrashed()
                ->where('slug', $candidate)
                ->when($ignoreId !== null, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformKegiatan(KegiatanDesa $k): array
    {
        $kategoriVal = $k->kategori instanceof KategoriKegiatan
            ? $k->kategori->value
            : (string) $k->kategori;
        $kategoriLabel = $k->kategori instanceof KategoriKegiatan
            ? $k->kategori->label()
            : $kategoriVal;

        $statusVal = $k->status_publikasi instanceof StatusPublikasi
            ? $k->status_publikasi->value
            : (string) $k->status_publikasi;
        $statusLabel = $k->status_publikasi instanceof StatusPublikasi
            ? $k->status_publikasi->label()
            : $statusVal;

        $fotoList = $k->relationLoaded('foto')
            ? $k->foto->sortBy('urutan')->values()
            : $k->foto()->orderBy('urutan')->get();

        $mappedFoto = $fotoList->map(fn (KegiatanDesaFoto $f) => [
            'id' => $f->id,
            'foto_path' => $f->foto_path,
            'foto_url' => Storage::disk('public')->url($f->foto_path),
            'urutan' => (int) $f->urutan,
        ])->values()->all();

        return [
            'id' => $k->id,
            'judul' => $k->judul,
            'slug' => $k->slug,
            'kategori' => $kategoriVal,
            'kategori_label' => $kategoriLabel,
            'tanggal_kegiatan' => $k->tanggal_kegiatan?->format('Y-m-d'),
            'lokasi_dusun' => $k->lokasi_dusun,
            'uraian' => $k->uraian,
            'status_publikasi' => $statusVal,
            'status_publikasi_label' => $statusLabel,
            'published_at' => $k->published_at?->format('Y-m-d H:i'),
            'sampul_url' => $mappedFoto[0]['foto_url'] ?? null,
            'jumlah_foto' => count($mappedFoto),
            'foto' => $mappedFoto,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function getKategoriOptions(): array
    {
        return collect(KategoriKegiatan::cases())->map(fn (KategoriKegiatan $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ])->values()->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function getStatusPublikasiOptions(): array
    {
        return collect(StatusPublikasi::cases())->map(fn (StatusPublikasi $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ])->values()->all();
    }
}
