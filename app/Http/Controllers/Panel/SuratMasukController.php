<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StatusSuratKeluar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Persuratan\SaveSuratMasukRequest;
use App\Models\ProfilDesa;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Services\PdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuratMasukController extends Controller
{
    /**
     * Tab 3 Persuratan: Buku Agenda Surat Masuk (/panel/persuratan/surat-masuk).
     */
    public function index(Request $request): Response
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahunAgenda = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        if ($request->filled('tahun') && $request->hasSession()) {
            $request->session()->put('filter_tahun', $tahunAgenda);
        }

        $search = trim((string) $request->input('search', ''));

        $query = SuratMasuk::query()
            ->where('tahun_agenda', $tahunAgenda)
            ->orderByDesc('nomor_urut_agenda');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat_masuk', 'like', "%{$search}%")
                    ->orWhere('pengirim', 'like', "%{$search}%")
                    ->orWhere('perihal', 'like', "%{$search}%")
                    ->orWhere('tujuan_disposisi', 'like', "%{$search}%");
            });
        }

        $items = $query->get()->map(fn (SuratMasuk $sm) => $this->transformSuratMasuk($sm))->values();

        $jumlahMenunggu = SuratKeluar::query()
            ->where('status', StatusSuratKeluar::MenungguVerifikasi)
            ->count();

        return Inertia::render('Persuratan/SuratMasuk', [
            'tahun_agenda' => $tahunAgenda,
            'surat_masuk' => $items,
            'jumlah_menunggu' => $jumlahMenunggu,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Catat surat masuk baru dengan penomoran otomatis withTrashed()->lockForUpdate()
     * dan simpan berkas scan ke dalam private disk.
     */
    public function store(SaveSuratMasukRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tahunAgenda = (int) Carbon::parse($validated['tanggal_terima'])->year;

        $scanPath = null;
        if ($request->hasFile('berkas_scan')) {
            $scanPath = $this->storePrivateScanFile($request->file('berkas_scan'), $tahunAgenda);
        }

        $suratMasuk = DB::transaction(function () use ($validated, $tahunAgenda, $scanPath, $request) {
            $maxUrut = (int) SuratMasuk::withTrashed()
                ->where('tahun_agenda', $tahunAgenda)
                ->lockForUpdate()
                ->max('nomor_urut_agenda');

            $nomorUrut = $maxUrut + 1;

            return SuratMasuk::create([
                'tahun_agenda' => $tahunAgenda,
                'nomor_urut_agenda' => $nomorUrut,
                'nomor_surat_masuk' => $validated['nomor_surat_masuk'],
                'tanggal_surat' => $validated['tanggal_surat'] ?? null,
                'tanggal_terima' => $validated['tanggal_terima'],
                'pengirim' => $validated['pengirim'],
                'perihal' => $validated['perihal'],
                'tujuan_disposisi' => $validated['tujuan_disposisi'] ?? null,
                'catatan_disposisi' => $validated['catatan_disposisi'] ?? null,
                'berkas_scan_path' => $scanPath,
                'created_by' => $request->user()?->id,
            ]);
        });

        $nomorPadded = str_pad((string) $suratMasuk->nomor_urut_agenda, 3, '0', STR_PAD_LEFT);

        return back()->with('notification', [
            'type' => 'success',
            'message' => "Surat masuk berhasil dicatat pada Buku Agenda dengan Nomor Urut #{$nomorPadded}.",
        ]);
    }

    /**
     * Perbarui catatan surat masuk, ganti berkas scan di private disk bila diunggah ulang,
     * dan hapus berkas scan lama setelah transaksi berhasil.
     */
    public function update(SaveSuratMasukRequest $request, SuratMasuk $suratMasuk): RedirectResponse
    {
        $validated = $request->validated();
        $oldTahun = (int) $suratMasuk->tahun_agenda;
        $newTahun = (int) Carbon::parse($validated['tanggal_terima'])->year;

        $oldScanToDelete = null;
        $newScanPath = $suratMasuk->berkas_scan_path;

        if ($request->hasFile('berkas_scan')) {
            if (! empty($suratMasuk->berkas_scan_path)) {
                $oldScanToDelete = $suratMasuk->berkas_scan_path;
            }
            $newScanPath = $this->storePrivateScanFile($request->file('berkas_scan'), $newTahun);
        }

        DB::transaction(function () use ($suratMasuk, $validated, $oldTahun, $newTahun, $newScanPath) {
            $nomorUrut = (int) $suratMasuk->nomor_urut_agenda;

            if ($newTahun !== $oldTahun) {
                $maxUrut = (int) SuratMasuk::withTrashed()
                    ->where('tahun_agenda', $newTahun)
                    ->lockForUpdate()
                    ->max('nomor_urut_agenda');
                $nomorUrut = $maxUrut + 1;
            }

            $suratMasuk->update([
                'tahun_agenda' => $newTahun,
                'nomor_urut_agenda' => $nomorUrut,
                'nomor_surat_masuk' => $validated['nomor_surat_masuk'],
                'tanggal_surat' => $validated['tanggal_surat'] ?? null,
                'tanggal_terima' => $validated['tanggal_terima'],
                'pengirim' => $validated['pengirim'],
                'perihal' => $validated['perihal'],
                'tujuan_disposisi' => $validated['tujuan_disposisi'] ?? null,
                'catatan_disposisi' => $validated['catatan_disposisi'] ?? null,
                'berkas_scan_path' => $newScanPath,
            ]);
        });

        if ($oldScanToDelete && Storage::disk('private')->exists($oldScanToDelete)) {
            Storage::disk('private')->delete($oldScanToDelete);
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Data surat masuk berhasil diperbarui.',
        ]);
    }

    /**
     * Soft-delete surat masuk (nomor_urut_agenda tetap terjaga di withTrashed()).
     */
    public function destroy(SuratMasuk $suratMasuk): RedirectResponse
    {
        $suratMasuk->delete();

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Catatan surat masuk telah dihapus dari daftar aktif.',
        ]);
    }

    /**
     * Streaming terproteksi berkas scan surat masuk dari private disk
     * khusus bagi pengguna terautentikasi (Operator Desa & Kepala Desa).
     */
    public function streamBerkas(SuratMasuk $suratMasuk): StreamedResponse|BinaryFileResponse|HttpResponse
    {
        $path = $suratMasuk->berkas_scan_path;

        if (empty($path) || ! Storage::disk('private')->exists($path)) {
            abort(404, 'Berkas scan surat masuk tidak ditemukan.');
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        $filename = basename($path);

        return Storage::disk('private')->response($path, $filename, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    /**
     * Cetak / Unduh PDF Buku Agenda Surat Masuk ukuran F4 Landscape.
     */
    public function cetakAgendaPdf(Request $request): HttpResponse
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);
        $tahun = (int) ($request->input('tahun')
            ?? ($request->hasSession() ? $request->session()->get('filter_tahun', $defaultTahun) : $defaultTahun));

        $suratMasukList = SuratMasuk::query()
            ->where('tahun_agenda', $tahun)
            ->orderBy('nomor_urut_agenda')
            ->get();

        $logoBase64 = $this->resolveLogoBase64($profilDesa);
        $filename = sprintf('Buku_Agenda_Surat_Masuk_TA_%d.pdf', $tahun);

        $pdf = PdfService::createF4Pdf('pdf.agenda-surat-masuk', [
            'tahun' => $tahun,
            'suratMasukList' => $suratMasukList,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
        ], 'landscape');

        return $pdf->download($filename);
    }

    protected function storePrivateScanFile(UploadedFile $file, int $tahun): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $filename = Str::uuid()->toString() . '.' . $ext;
        $directory = "surat-masuk/{$tahun}";

        return $file->storeAs($directory, $filename, 'private');
    }

    /**
     * @return array<string, mixed>
     */
    protected function transformSuratMasuk(SuratMasuk $sm): array
    {
        $ext = $sm->berkas_scan_path ? strtolower(pathinfo($sm->berkas_scan_path, PATHINFO_EXTENSION)) : null;

        return [
            'id' => $sm->id,
            'tahun_agenda' => (int) $sm->tahun_agenda,
            'nomor_urut_agenda' => (int) $sm->nomor_urut_agenda,
            'nomor_urut_padded' => str_pad((string) $sm->nomor_urut_agenda, 3, '0', STR_PAD_LEFT),
            'nomor_surat_masuk' => $sm->nomor_surat_masuk,
            'tanggal_surat' => $sm->tanggal_surat?->format('Y-m-d'),
            'tanggal_terima' => $sm->tanggal_terima?->format('Y-m-d'),
            'pengirim' => $sm->pengirim,
            'perihal' => $sm->perihal,
            'tujuan_disposisi' => $sm->tujuan_disposisi,
            'catatan_disposisi' => $sm->catatan_disposisi,
            'berkas_scan_path' => $sm->berkas_scan_path,
            'has_scan' => ! empty($sm->berkas_scan_path),
            'scan_type' => $ext === 'pdf' ? 'pdf' : ($ext ? 'image' : null),
            'scan_stream_url' => $sm->berkas_scan_path
                ? route('panel.persuratan.surat-masuk.berkas', $sm->id)
                : null,
        ];
    }

    protected function resolveLogoBase64(?ProfilDesa $profilDesa): ?string
    {
        return PdfService::resolveLogoBase64($profilDesa);
    }
}
