<?php

namespace Database\Factories;

use App\Enums\StatusKesejahteraan;
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
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
            'status_kepemilikan_rumah' => fake()->randomElement(['Milik Sendiri', 'Menumpang', 'Sewa/Kontrak']),
            'kondisi_rumah' => fake()->randomElement(['Permanen', 'Semi Permanen', 'Rumah Panggung', 'Rumah Kayu']),
            'jenis_listrik' => fake()->randomElement(['Manual 450 watt', 'Manual 900 Watt', 'PLS 450 watt', 'PLS 900 watt', 'Nyalur']),
            'kamar_mandi' => fake()->randomElement(['Ada', 'Tidak Ada']),
            'penghasilan_bulanan' => fake()->randomElement(['Rp 1.000.000', 'Rp 1.500.000', 'Rp 2.500.000']),
            'kepemilikan_usaha' => fake()->randomElement([null, 'Warung Sembako', 'Jual Kue']),
            'kepemilikan_hewan_ternak' => fake()->randomElement([null, 'Ayam Kampung 15 ekor', 'Sapi Milik Sendiri (2)']),
            'kepemilikan_kendaraan_elektronik' => fake()->randomElement(['Motor 1, TV 1, HP 2', 'Motor 1, HP 1', 'TV 1, HP 1']),
            'kepemilikan_shml' => fake()->randomElement(['1', '2', null]),
            'kepemilikan_tanah_bidang' => fake()->randomElement(['1', '2', null]),
            'kepemilikan_tanah_sawah' => fake()->randomElement([null, '1 Bidang']),
            'tanaman' => fake()->randomElement([null, 'Kelapa 50 Pohon, Pisang 15 Pohon', 'Coklat 40 Pohon, Kelapa 30 Pohon']),
        ];
    }

    public function desil1(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);
    }

    public function desil2(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
        ]);
    }

    public function desil34(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil34,
        ]);
    }

    public function nonPrioritas(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
        ]);
    }
}
