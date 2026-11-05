<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ProfilDesa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Tampilkan halaman login internal desa.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Tangani proses login pengguna internal (Operator Desa & Kepala Desa).
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if ($user) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->save();
        }

        if (! $request->session()->has('filter_tahun')) {
            $profil = ProfilDesa::singleton();
            $tahunDefault = (int) ($profil?->tahun_anggaran_aktif ?? now()->year);
            $request->session()->put('filter_tahun', $tahunDefault);
        }

        return redirect()->intended(route('panel.dashboard', absolute: false));
    }

    /**
     * Akhiri sesi login pengguna internal.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
