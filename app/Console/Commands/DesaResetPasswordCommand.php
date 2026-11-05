<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DesaResetPasswordCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'desa:reset-password
                            {email : Alamat email akun pengguna internal desa}
                            {--password= : Kata sandi baru opsional (minimal 8 karakter)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setel ulang kata sandi akun pengguna internal desa secara darurat melalui CLI tanpa memerlukan SMTP';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("Akun pengguna dengan email [{$email}] tidak ditemukan.");

            return self::FAILURE;
        }

        $plainPassword = (string) ($this->option('password') ?? '');

        if ($plainPassword === '' && $this->input->isInteractive()) {
            $plainPassword = (string) $this->secret(
                'Masukkan kata sandi baru (minimal 8 karakter, kosongkan untuk membuat otomatis)'
            );
        }

        if ($plainPassword === '') {
            $plainPassword = Str::random(12);
        }

        if (mb_strlen($plainPassword) < 8) {
            $this->error('Kata sandi baru minimal harus terdiri dari 8 karakter.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => Hash::make($plainPassword),
            'is_active' => true,
        ])->save();

        $this->info("Kata sandi untuk [{$user->name} <{$user->email}>] berhasil disetel ulang.");
        $this->line("Kata Sandi Baru: {$plainPassword}");

        return self::SUCCESS;
    }
}
