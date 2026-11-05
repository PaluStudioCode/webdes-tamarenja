<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Batasi akses rute berdasarkan peran pengguna internal (operator / kepala_desa).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        $allowedRoles = collect($roles)
            ->flatMap(fn (string $roleGroup) => explode(',', $roleGroup))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->values()
            ->all();

        $currentRole = $user->role instanceof UserRole
            ? $user->role->value
            : (string) $user->role;

        if (! in_array($currentRole, $allowedRoles, true)) {
            abort(403, 'Akses Dibatasi Sesuai Peran');
        }

        return $next($request);
    }
}
