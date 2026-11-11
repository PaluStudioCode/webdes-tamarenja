<?php

namespace App\Console\Commands;

use Database\Seeders\DataAwalSeeder;
use Illuminate\Console\Command;

class DesaSimulasiPeriodeBaruCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'desa:simulasi-periode-baru
                            {--tahun= : Tahun anggaran simulasi (default: tahun aktif saat ini)}
                            {--force : Lewati konfirmasi reset data transaksional}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Siapkan kondisi data awal untuk simulasi periode baru (menjaga akun & data master, mereset data transaksional)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tahun = $this->option('tahun') ? (int) $this->option('tahun') : (int) date('Y');

        if (! $this->option('force') && $this->input->isInteractive()) {
            $confirmed = $this->confirm(
                "Perintah ini akan mereset seluruh data transaksional (APBDes, Proyek, Persuratan, Bansos, Kegiatan) untuk simulasi periode baru TA {$tahun}. Data master (Akun, Profil, SOTK, Penduduk) tetap tersimpan. Lanjutkan?",
                true
            );

            if (! $confirmed) {
                $this->info('Operasi dibatalkan.');

                return self::SUCCESS;
            }
        }

        $seeder = new DataAwalSeeder($tahun);
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info("✓ Sistem berhasil disiapkan untuk simulasi periode baru TA {$tahun}.");
        $this->line('  • Akun Login: operator@desa.id & kades@desa.id (password: password)');
        $this->line('  • Profil Desa & SOTK: Utuh');
        $this->line('  • Master Kependudukan: 28 KK & 100 Penduduk aktif');
        $this->line("  • APBDes TA {$tahun}: Murni baru (Pagu & Realisasi Rp 0)");
        $this->line('  • Proyek, Persuratan, & Bansos: Bersih (0 transaksi)');

        return self::SUCCESS;
    }
}
