<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    /**
     * Tolak akses dan logout paksa apabila akun pengguna berstatus nonaktif (is_active === false).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => 'Akun pengguna telah dinonaktifkan.',
                ], 403);
            }

            return redirect()
                ->route('login')
                ->with('notification', [
                    'type' => 'error',
                    'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi Operator Desa.',
                ]);
        }

        return $next($request);
    }
}
