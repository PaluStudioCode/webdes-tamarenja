<?php

namespace Database\Factories;

use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SuratMasuk>
 */
class SuratMasukFactory extends Factory
{
    protected $model = SuratMasuk::class;

    protected static int $agendaSequence = 1000;

    public function definition(): array
    {
        $tahun = (int) date('Y');
        $urut = ++static::$agendaSequence;

        return [
            'tahun_agenda' => $tahun,
            'nomor_urut_agenda' => $urut,
            'nomor_surat_masuk' => "005/{$urut}/KEC-ST/{$tahun}",
            'tanggal_surat' => now()->subDays(2)->format('Y-m-d'),
            'tanggal_terima' => now()->format('Y-m-d'),
            'pengirim' => 'Pemerintah Kecamatan Sindue Tobata',
            'perihal' => 'Undangan Rapat Koordinasi Pemerintahan Desa',
            'tujuan_disposisi' => 'Sekretaris Desa',
            'catatan_disposisi' => 'Mohon dihadiri dan siapkan bahan laporan.',
            'berkas_scan_path' => null,
            'created_by' => User::factory(),
        ];
    }
}
