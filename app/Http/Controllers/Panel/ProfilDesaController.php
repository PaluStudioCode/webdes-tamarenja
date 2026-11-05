<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\UpdateProfilDesaRequest;
use App\Models\ProfilDesa;
use App\Services\ImageUploadService;
use App\Services\PhoneNormalizerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfilDesaController extends Controller
{
    public function __construct(
        private readonly ImageUploadService $imageUploadService,
    ) {}

    /**
     * Tampilkan formulir pengaturan profil desa, kop surat, pejabat penandatangan & tahun aktif.
     */
    public function edit(): Response
    {
        Gate::authorize('manage-village-settings');

        $profil = $this->resolveOrCreateProfil();

        return Inertia::render('Pengaturan/ProfilDesa', [
            'profil' => [
                'id' => $profil->id,
                'nama_desa' => $profil->nama_desa,
                'kode_desa_surat' => $profil->kode_desa_surat,
                'kecamatan' => $profil->kecamatan,
                'kabupaten' => $profil->kabupaten,
                'provinsi' => $profil->provinsi,
                'kode_wilayah' => $profil->kode_wilayah,
                'daftar_dusun' => array_values($profil->daftar_dusun ?? ['Dusun I']),
                'alamat_kantor' => $profil->alamat_kantor,
                'jam_pelayanan' => $profil->jam_pelayanan,
                'email_desa' => $profil->email_desa,
                'no_wa_pelayanan' => $profil->no_wa_pelayanan,
                'logo_desa_path' => $profil->logo_desa_path,
                'logo_url' => $profil->logo_desa_path
                    ? Storage::disk('public')->url($profil->logo_desa_path)
                    : null,
                'nama_kepala_desa' => $profil->nama_kepala_desa,
                'nip_kepala_desa' => $profil->nip_kepala_desa,
                'foto_kepala_desa_path' => $profil->foto_kepala_desa_path,
                'foto_kepala_desa_url' => $profil->foto_kepala_desa_path
                    ? Storage::disk('public')->url($profil->foto_kepala_desa_path)
                    : null,
                'sambutan_kepala_desa' => $profil->sambutan_kepala_desa,
                'nama_sekdes' => $profil->nama_sekdes,
                'nip_sekdes' => $profil->nip_sekdes,
                'visi' => $profil->visi,
                'misi' => array_values($profil->misi ?? []),
                'sejarah_singkat' => $profil->sejarah_singkat,
                'potensi_unggulan' => $profil->potensi_unggulan,
                'tahun_anggaran_aktif' => (int) $profil->tahun_anggaran_aktif,
            ],
        ]);
    }

    /**
     * Perbarui identitas desa, daftar dusun, misi, pejabat, tahun aktif, serta logo (.png) & foto Kades.
     */
    public function update(UpdateProfilDesaRequest $request): RedirectResponse
    {
        Gate::authorize('manage-village-settings');

        $profil = $this->resolveOrCreateProfil();
        $validated = $request->validated();

        $oldLogoPath = $profil->logo_desa_path;
        $oldFotoKadesPath = $profil->foto_kepala_desa_path;
        $newLogoPath = null;
        $newFotoKadesPath = null;

        DB::transaction(function () use ($request, $profil, $validated, &$newLogoPath, &$newFotoKadesPath): void {
            if ($request->hasFile('logo_desa')) {
                $newLogoPath = $this->imageUploadService->uploadLogo(
                    $request->file('logo_desa'),
                    'public'
                );
            }

            if ($request->hasFile('foto_kepala_desa')) {
                $newFotoKadesPath = $this->imageUploadService->uploadFieldPhoto(
                    $request->file('foto_kepala_desa'),
                    'sotk',
                    'webp',
                    1600,
                    80,
                    'public'
                );
            }

            $noWa = ! empty($validated['no_wa_pelayanan'])
                ? PhoneNormalizerService::normalize((string) $validated['no_wa_pelayanan'])
                : null;

            $profil->fill([
                'nama_desa' => $validated['nama_desa'],
                'kode_desa_surat' => $validated['kode_desa_surat'],
                'kecamatan' => $validated['kecamatan'],
                'kabupaten' => $validated['kabupaten'],
                'provinsi' => $validated['provinsi'],
                'kode_wilayah' => $validated['kode_wilayah'] ?? null,
                'daftar_dusun' => array_values($validated['daftar_dusun']),
                'alamat_kantor' => $validated['alamat_kantor'],
                'jam_pelayanan' => $validated['jam_pelayanan'] ?? null,
                'email_desa' => $validated['email_desa'] ?? null,
                'no_wa_pelayanan' => $noWa,
                'nama_kepala_desa' => $validated['nama_kepala_desa'],
                'nip_kepala_desa' => $validated['nip_kepala_desa'] ?? null,
                'sambutan_kepala_desa' => $validated['sambutan_kepala_desa'] ?? null,
                'nama_sekdes' => $validated['nama_sekdes'],
                'nip_sekdes' => $validated['nip_sekdes'] ?? null,
                'visi' => $validated['visi'] ?? null,
                'misi' => array_values($validated['misi'] ?? []),
                'sejarah_singkat' => $validated['sejarah_singkat'] ?? null,
                'potensi_unggulan' => $validated['potensi_unggulan'] ?? null,
                'tahun_anggaran_aktif' => (int) $validated['tahun_anggaran_aktif'],
            ]);

            if ($newLogoPath !== null) {
                $profil->logo_desa_path = $newLogoPath;
            }

            if ($newFotoKadesPath !== null) {
                $profil->foto_kepala_desa_path = $newFotoKadesPath;
            }

            $profil->save();
        });

        // Hapus berkas lama setelah transaksi database berhasil
        if ($newLogoPath !== null && $oldLogoPath && $oldLogoPath !== $newLogoPath) {
            $this->imageUploadService->deleteIfExists($oldLogoPath, 'public');
        }

        if ($newFotoKadesPath !== null && $oldFotoKadesPath && $oldFotoKadesPath !== $newFotoKadesPath) {
            $this->imageUploadService->deleteIfExists($oldFotoKadesPath, 'public');
        }

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Pengaturan profil desa, kop surat, dan pejabat penandatangan berhasil disimpan.',
        ]);
    }

    private function resolveOrCreateProfil(): ProfilDesa
    {
        $profil = ProfilDesa::singleton();

        if ($profil) {
            return $profil;
        }

        return ProfilDesa::query()->create([
            'nama_desa' => 'Tamarenja',
            'kode_desa_surat' => 'DS-TMR',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'kode_wilayah' => '72.03.19.2003',
            'daftar_dusun' => ['Dusun I', 'Dusun II', 'Dusun III', 'Dusun IV'],
            'alamat_kantor' => 'Jl. Trans Sulawesi Pantai Barat, Desa Tamarenja',
            'jam_pelayanan' => 'Senin - Jumat, 08.00 - 15.30 WITA',
            'nama_kepala_desa' => 'Kepala Desa Tamarenja',
            'nama_sekdes' => 'Sekretaris Desa Tamarenja',
            'misi' => [],
            'tahun_anggaran_aktif' => (int) date('Y'),
        ]);
    }
}
