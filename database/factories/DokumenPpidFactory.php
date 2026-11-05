<?php

namespace Database\Factories;

use App\Models\DokumenPpid;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DokumenPpid>
 */
class DokumenPpidFactory extends Factory
{
    protected $model = DokumenPpid::class;

    public function definition(): array
    {
        $tahun = (int) date('Y');

        return [
            'judul_dokumen' => 'Peraturan Desa Nomor ' . fake()->numberBetween(1, 10) . ' Tahun ' . $tahun,
            'kategori' => fake()->randomElement([
                'perdes_apbdes',
                'lpj',
                'rkpdes',
                'rpjmdes',
                'sk_regulasi',
                'panduan_layanan',
            ]),
            'tahun_dokumen' => $tahun,
            'file_pdf_path' => "ppid/{$tahun}/sample-dokumen.pdf",
            'ukuran_file_kb' => 245,
            'jumlah_unduhan' => 0,
            'is_publik' => true,
            'uploaded_by' => User::factory(),
        ];
    }

    public function nonPublik(): static
    {
        return $this->state(fn () => [
            'is_publik' => false,
        ]);
    }
}
