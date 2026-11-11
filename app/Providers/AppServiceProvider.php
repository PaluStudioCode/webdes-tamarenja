<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        if (class_exists(\Illuminate\Foundation\Console\ServeCommand::class)) {
            \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables = array_merge(
                \Illuminate\Foundation\Console\ServeCommand::$passthroughVariables,
                ['Path', 'PATH', 'SystemRoot', 'SYSTEMROOT', 'windir', 'TEMP', 'TMP', 'LOCALAPPDATA', 'APPDATA', 'USERPROFILE']
            );
        }

        $this->registerAuthorizationGates();
    }

    /**
     * Daftarkan 6 Gate khusus sesuai PRD 03 Seksi 2.6.
     */
    private function registerAuthorizationGates(): void
    {
        Gate::define('manage-master-penduduk', fn (User $user): bool => $user->is_active && $user->isOperator());

        Gate::define('issue-official-letter', fn (User $user): bool => $user->is_active && $user->isOperator());

        Gate::define('manage-budget-and-projects', fn (User $user): bool => $user->is_active && $user->isOperator());

        Gate::define(
            'print-official-reports',
            fn (User $user): bool => $user->is_active && ($user->isOperator() || $user->isKepalaDesa()),
        );

        Gate::define('manage-village-settings', fn (User $user): bool => $user->is_active && $user->isOperator());

        Gate::define('execute-system-backup', fn (User $user): bool => $user->is_active && $user->isOperator());
    }
}
