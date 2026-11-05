<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\SaveDokumenPpidRequest;
use App\Models\DokumenPpid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DokumenPpidController extends Controller
{
    /**
     * Tampilkan daftar dokumen resmi Pusat Unduhan PPID (/panel/pengaturan/ppid).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('manage-village-settings');

        $search = trim((string) $request->query('search', ''));
        $kategoriFilter = trim((string) $request->query('kategori', ''));

        $dokumenList = DokumenPpid::query()
            ->with('pengunggah:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('judul_dokumen', 'like', "%{$search}%");
            })
            ->when(array_key_exists($kategoriFilter, SaveDokumenPpidRequest::KATEGORI_OPTIONS), function ($query) use ($kategoriFilter) {
                $query->where('kategori', $kategoriFilter);
            })
            ->orderByDesc('tahun_dokumen')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (DokumenPpid $d) => [
                'id' => $d->id,
                'judul_dokumen' => $d->judul_dokumen,
                'kategori' => $d->kategori,
                'kategori_label' => SaveDokumenPpidRequest::KATEGORI_OPTIONS[$d->kategori] ?? $d->kategori,
                'tahun_dokumen' => $d->tahun_dokumen ? (int) $d->tahun_dokumen : null,
                'file_pdf_path' => $d->file_pdf_path,
                'file_pdf_url' => $d->file_pdf_path ? Storage::disk('public')->url($d->file_pdf_path) : null,
                'ukuran_file_kb' => (int) $d->ukuran_file_kb,
                'jumlah_unduhan' => (int) $d->jumlah_unduhan,
                'is_publik' => (bool) $d->is_publik,
                'pengunggah_nama' => $d->pengunggah?->name ?? 'Operator Desa',
                'created_at' => $d->created_at?->toIso8601String(),
            ]);

        $kategoriOptions = collect(SaveDokumenPpidRequest::KATEGORI_OPTIONS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();

        return Inertia::render('Pengaturan/Ppid', [
            'dokumenList' => $dokumenList,
            'kategoriOptions' => $kategoriOptions,
            'filters' => [
                'search' => $search,
                'kategori' => $kategoriFilter,
            ],
        ]);
    }

    /**
     * Unggah dokumen resmi PDF PPID baru (maks 10 MB, perhitungan otomatis ukuran_file_kb).
     */
    public function store(SaveDokumenPpidRequest $request): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $validated = $request->validated();
        /** @var UploadedFile $pdfFile */
        $pdfFile = $request->file('file_pdf');

        $tahunDir = (int) ($validated['tahun_dokumen'] ?: date('Y'));
        $ukuranKb = max(1, (int) ceil(((int) $pdfFile->getSize()) / 1024));
        $filename = Str::uuid() . '.pdf';
        $storedPath = $pdfFile->storeAs("ppid/{$tahunDir}", $filename, 'public');

        DokumenPpid::query()->create([
            'judul_dokumen' => $validated['judul_dokumen'],
            'kategori' => $validated['kategori'],
            'tahun_dokumen' => $validated['tahun_dokumen'] ?? null,
            'file_pdf_path' => $storedPath,
            'ukuran_file_kb' => $ukuranKb,
            'jumlah_unduhan' => 0,
            'is_publik' => (bool) $validated['is_publik'],
            'uploaded_by' => (int) $request->user()->id,
        ]);

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Dokumen PPID \"{$validated['judul_dokumen']}\" berhasil diunggah.",
        ]);
    }

    /**
     * Perbarui metadata atau berkas PDF dokumen PPID beserta penghapusan berkas lama.
     */
    public function update(SaveDokumenPpidRequest $request, DokumenPpid $dokumenPpid): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $validated = $request->validated();
        $oldPdfPath = $dokumenPpid->file_pdf_path;
        $newPdfPath = null;
        $newUkuranKb = null;

        DB::transaction(function () use ($request, $dokumenPpid, $validated, &$newPdfPath, &$newUkuranKb): void {
            if ($request->hasFile('file_pdf')) {
                /** @var UploadedFile $pdfFile */
                $pdfFile = $request->file('file_pdf');
                $tahunDir = (int) ($validated['tahun_dokumen'] ?: ($dokumenPpid->tahun_dokumen ?: date('Y')));
                $newUkuranKb = max(1, (int) ceil(((int) $pdfFile->getSize()) / 1024));
                $filename = Str::uuid() . '.pdf';
                $newPdfPath = $pdfFile->storeAs("ppid/{$tahunDir}", $filename, 'public');
            }

            $dokumenPpid->fill([
                'judul_dokumen' => $validated['judul_dokumen'],
                'kategori' => $validated['kategori'],
                'tahun_dokumen' => $validated['tahun_dokumen'] ?? null,
                'is_publik' => (bool) $validated['is_publik'],
            ]);

            if ($newPdfPath !== null && $newUkuranKb !== null) {
                $dokumenPpid->file_pdf_path = $newPdfPath;
                $dokumenPpid->ukuran_file_kb = $newUkuranKb;
            }

            $dokumenPpid->save();
        });

        if ($newPdfPath !== null && $oldPdfPath && $oldPdfPath !== $newPdfPath && Storage::disk('public')->exists($oldPdfPath)) {
            Storage::disk('public')->delete($oldPdfPath);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Dokumen PPID \"{$dokumenPpid->judul_dokumen}\" berhasil diperbarui.",
        ]);
    }

    /**
     * Toggle status tayang publik (is_publik) dokumen PPID.
     */
    public function togglePublikasi(DokumenPpid $dokumenPpid): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $nextPublik = ! $dokumenPpid->is_publik;
        $dokumenPpid->update(['is_publik' => $nextPublik]);

        $statusLabel = $nextPublik ? 'ditayangkan ke Pusat Unduhan publik' : 'disembunyikan dari publik';

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Dokumen \"{$dokumenPpid->judul_dokumen}\" berhasil {$statusLabel}.",
        ]);
    }

    /**
     * Soft delete dokumen PPID dan hapus berkas dari public storage.
     */
    public function destroy(DokumenPpid $dokumenPpid): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $judul = $dokumenPpid->judul_dokumen;
        $pdfPath = $dokumenPpid->file_pdf_path;

        $dokumenPpid->delete();

        if ($pdfPath && Storage::disk('public')->exists($pdfPath)) {
            Storage::disk('public')->delete($pdfPath);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Dokumen PPID \"{$judul}\" berhasil dihapus.",
        ]);
    }
}
