<?php

namespace App\Http\Middleware;

use App\Enums\StatusSuratKeluar;
use App\Enums\UserRole;
use App\Models\Apbdes;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $profilDesa = $this->resolveProfilDesa();
        $tahunAktif = (int) ($profilDesa?->tahun_anggaran_aktif ?? now()->year);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role instanceof UserRole ? $user->role->value : (string) $user->role,
                    'role_label' => $user->role instanceof UserRole ? $user->role->label() : (string) $user->role,
                    'is_active' => (bool) $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                ] : null,
            ],
            'profil_desa' => $profilDesa ? [
                'nama_desa' => $profilDesa->nama_desa,
                'kode_desa_surat' => $profilDesa->kode_desa_surat,
                'kecamatan' => $profilDesa->kecamatan,
                'kabupaten' => $profilDesa->kabupaten,
                'provinsi' => $profilDesa->provinsi,
                'alamat_kantor' => $profilDesa->alamat_kantor,
                'jam_pelayanan' => $profilDesa->jam_pelayanan,
                'email_desa' => $profilDesa->email_desa,
                'no_wa_pelayanan' => $profilDesa->no_wa_pelayanan,
                'logo_desa_path' => $profilDesa->logo_desa_path,
                'logo_url' => $profilDesa->logo_desa_path
                    ? Storage::disk('public')->url($profilDesa->logo_desa_path)
                    : null,
                'tahun_anggaran_aktif' => $tahunAktif,
                'daftar_dusun' => $profilDesa->daftar_dusun ?? [],
            ] : null,
            'antrean_surat_count' => fn (): int => $user ? $this->resolveAntreanSuratCount() : 0,
            'flash' => [
                'notification' => fn () => $request->hasSession()
                    ? $request->session()->get('notification')
                    : null,
                'import_summary' => fn () => $request->hasSession()
                    ? $request->session()->get('import_summary')
                    : null,
                'import_errors' => fn () => $request->hasSession()
                    ? $request->session()->get('import_errors')
                    : null,
                'kepala_keluarga_reminder' => fn () => $request->hasSession()
                    ? $request->session()->get('kepala_keluarga_reminder')
                    : null,
            ],
            'filter_tahun' => fn (): int => $request->hasSession()
                ? (int) $request->session()->get('filter_tahun', $tahunAktif)
                : $tahunAktif,
            'available_tahun' => fn (): array => $user
                ? $this->resolveAvailableTahun($tahunAktif)
                : [$tahunAktif],
        ];
    }

    private function resolveProfilDesa(): ?ProfilDesa
    {
        try {
            return ProfilDesa::singleton();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAntreanSuratCount(): int
    {
        try {
            return SuratKeluar::query()
                ->where('status', StatusSuratKeluar::MenungguVerifikasi)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<int>
     */
    private function resolveAvailableTahun(int $tahunAktif): array
    {
        try {
            $apbdesYears = Apbdes::query()
                ->select('tahun_anggaran')
                ->distinct()
                ->pluck('tahun_anggaran')
                ->map(fn ($yr) => (int) $yr)
                ->all();

            $proyekYears = ProyekPembangunan::query()
                ->select('tahun_anggaran')
                ->distinct()
                ->pluck('tahun_anggaran')
                ->map(fn ($yr) => (int) $yr)
                ->all();

            $years = array_unique([
                $tahunAktif + 1,
                $tahunAktif,
                $tahunAktif - 1,
                ...$apbdesYears,
                ...$proyekYears,
            ]);

            rsort($years);

            return array_values($years);
        } catch (\Throwable) {
            return [$tahunAktif + 1, $tahunAktif, $tahunAktif - 1];
        }
    }
}
