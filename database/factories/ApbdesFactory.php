<?php

namespace Database\Factories;

use App\Enums\JenisDokumenApbdes;
use App\Models\Apbdes;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Apbdes>
 */
class ApbdesFactory extends Factory
{
    protected $model = Apbdes::class;

    public function definition(): array
    {
        $tahun = (int) date('Y');

        return [
            'tahun_anggaran' => $tahun,
            'jenis_dokumen' => JenisDokumenApbdes::Murni,
            'is_aktif_publik' => true,
            'is_terkunci' => false,
            'nomor_perdes' => 'Perdes No. 01 Tahun ' . $tahun,
            'tanggal_penetapan' => $tahun . '-01-15',
            'created_by' => User::factory(),
        ];
    }

    public function perubahan(): static
    {
        return $this->state(fn (array $attributes) => [
            'jenis_dokumen' => JenisDokumenApbdes::Perubahan,
            'is_aktif_publik' => true,
            'is_terkunci' => false,
            'nomor_perdes' => 'Perdes No. 05 Tahun ' . ($attributes['tahun_anggaran'] ?? date('Y')),
        ]);
    }

    public function terkunci(): static
    {
        return $this->state(fn () => [
            'is_aktif_publik' => false,
            'is_terkunci' => true,
        ]);
    }
}
