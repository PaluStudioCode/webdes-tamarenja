<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ProfilDesa;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/panel/dashboard');

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserActive::class,
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $status = $response->getStatusCode();

            if (! in_array($status, [403, 404, 419, 500], true)) {
                return $response;
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return $response;
            }

            // Pastikan profil_desa ringkas tetap tersedia pada rute 404 yang tidak melewati middleware web
            if (Inertia::getShared('profil_desa') === null) {
                try {
                    $profil = ProfilDesa::singleton();
                    if ($profil) {
                        Inertia::share('profil_desa', [
                            'nama_desa' => $profil->nama_desa,
                            'kecamatan' => $profil->kecamatan,
                            'kabupaten' => $profil->kabupaten,
                            'provinsi' => $profil->provinsi,
                            'tahun_anggaran_aktif' => (int) $profil->tahun_anggaran_aktif,
                            'daftar_dusun' => $profil->daftar_dusun ?? [],
                        ]);
                    }
                } catch (Throwable) {
                    // Abaikan jika koneksi/tabel belum tersedia
                }
            }

            return Inertia::render('ErrorPage', [
                'status' => $status,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
