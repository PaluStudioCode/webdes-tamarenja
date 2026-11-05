<?php

namespace Database\Factories;

use App\Models\KartuKeluarga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KartuKeluarga>
 */
class KartuKeluargaFactory extends Factory
{
    protected $model = KartuKeluarga::class;

    public function definition(): array
    {
        return [
            'nomor_kk' => fake()->unique()->numerify('720101##########'),
            'dusun' => fake()->randomElement(['Dusun I', 'Dusun II', 'Dusun III', 'Dusun IV']),
            'rt' => str_pad((string) fake()->numberBetween(1, 5), 3, '0', STR_PAD_LEFT),
            'rw' => str_pad((string) fake()->numberBetween(1, 3), 3, '0', STR_PAD_LEFT),
            'alamat' => 'Jl. Poros Desa Tamarenja No. ' . fake()->numberBetween(1, 120),
        ];
    }
}
