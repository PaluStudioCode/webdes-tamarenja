<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\SavePerangkatDesaRequest;
use App\Models\PerangkatDesa;
use App\Services\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PerangkatDesaController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService,
    ) {}

    /**
     * Tampilkan daftar perangkat desa & BPD (/panel/pengaturan/sotk).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('manage-village-settings');

        $search = trim((string) $request->query('search', ''));
        $kelompokFilter = trim((string) $request->query('kelompok_sotk', ''));

        $perangkatList = PerangkatDesa::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%")
                        ->orWhere('nip_niap', 'like', "%{$search}%");
                });
            })
            ->when(array_key_exists($kelompokFilter, SavePerangkatDesaRequest::KELOMPOK_OPTIONS), function ($query) use ($kelompokFilter) {
                $query->where('kelompok_sotk', $kelompokFilter);
            })
            ->orderBy('urutan')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PerangkatDesa $p) => [
                'id' => $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'jabatan' => $p->jabatan,
                'kelompok_sotk' => $p->kelompok_sotk,
                'kelompok_label' => SavePerangkatDesaRequest::KELOMPOK_OPTIONS[$p->kelompok_sotk] ?? $p->kelompok_sotk,
                'nip_niap' => $p->nip_niap,
                'foto_path' => $p->foto_path,
                'foto_url' => $p->foto_path ? Storage::disk('public')->url($p->foto_path) : null,
                'urutan' => (int) $p->urutan,
                'is_aktif' => (bool) $p->is_aktif,
            ]);

        $kelompokOptions = collect(SavePerangkatDesaRequest::KELOMPOK_OPTIONS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();

        return Inertia::render('Pengaturan/Sotk', [
            'perangkatList' => $perangkatList,
            'kelompokOptions' => $kelompokOptions,
            'filters' => [
                'search' => $search,
                'kelompok_sotk' => $kelompokFilter,
            ],
        ]);
    }

    /**
     * Tambah data perangkat desa / anggota BPD baru.
     */
    public function store(SavePerangkatDesaRequest $request): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $fotoPath = null;
            if ($request->hasFile('foto')) {
                $fotoPath = $this->imageUploadService->uploadFieldPhoto(
                    $request->file('foto'),
                    'sotk',
                    'webp',
                    1600,
                    80,
                    'public'
                );
            }

            PerangkatDesa::query()->create([
                'nama_lengkap' => $validated['nama_lengkap'],
                'jabatan' => $validated['jabatan'],
                'kelompok_sotk' => $validated['kelompok_sotk'],
                'nip_niap' => $validated['nip_niap'] ?? null,
                'foto_path' => $fotoPath,
                'urutan' => (int) $validated['urutan'],
                'is_aktif' => (bool) $validated['is_aktif'],
            ]);
        });

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Data perangkat SOTK {$validated['nama_lengkap']} berhasil ditambahkan.",
        ]);
    }

    /**
     * Perbarui data perangkat desa / anggota BPD beserta pembersihan foto lama.
     */
    public function update(SavePerangkatDesaRequest $request, PerangkatDesa $perangkatDesa): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $validated = $request->validated();
        $oldFotoPath = $perangkatDesa->foto_path;
        $newFotoPath = null;

        DB::transaction(function () use ($request, $perangkatDesa, $validated, &$newFotoPath): void {
            if ($request->hasFile('foto')) {
                $newFotoPath = $this->imageUploadService->uploadFieldPhoto(
                    $request->file('foto'),
                    'sotk',
                    'webp',
                    1600,
                    80,
                    'public'
                );
            }

            $perangkatDesa->fill([
                'nama_lengkap' => $validated['nama_lengkap'],
                'jabatan' => $validated['jabatan'],
                'kelompok_sotk' => $validated['kelompok_sotk'],
                'nip_niap' => $validated['nip_niap'] ?? null,
                'urutan' => (int) $validated['urutan'],
                'is_aktif' => (bool) $validated['is_aktif'],
            ]);

            if ($newFotoPath !== null) {
                $perangkatDesa->foto_path = $newFotoPath;
            }

            $perangkatDesa->save();
        });

        if ($newFotoPath !== null && $oldFotoPath && $oldFotoPath !== $newFotoPath) {
            $this->imageUploadService->deleteIfExists($oldFotoPath, 'public');
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Data perangkat SOTK {$perangkatDesa->nama_lengkap} berhasil diperbarui.",
        ]);
    }

    /**
     * Hapus data perangkat desa / anggota BPD beserta foto dari storage.
     */
    public function destroy(PerangkatDesa $perangkatDesa): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $nama = $perangkatDesa->nama_lengkap;
        $fotoPath = $perangkatDesa->foto_path;

        $perangkatDesa->delete();

        if ($fotoPath) {
            $this->imageUploadService->deleteIfExists($fotoPath, 'public');
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Data perangkat SOTK {$nama} berhasil dihapus.",
        ]);
    }
}
