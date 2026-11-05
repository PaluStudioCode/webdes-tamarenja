<?php

namespace Database\Factories;

use App\Models\PerangkatDesa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerangkatDesa>
 */
class PerangkatDesaFactory extends Factory
{
    protected $model = PerangkatDesa::class;

    public function definition(): array
    {
        return [
            'nama_lengkap' => fake()->name(),
            'jabatan' => fake()->randomElement([
                'Kaur Tata Usaha & Umum',
                'Kaur Keuangan',
                'Kaur Perencanaan',
                'Kasi Pemerintahan',
                'Kasi Kesejahteraan',
                'Kasi Pelayanan',
                'Kepala Dusun I',
            ]),
            'kelompok_sotk' => fake()->randomElement([
                'pimpinan',
                'sekretariat',
                'pelaksana_teknis',
                'kewilayahan_dusun',
                'bpd',
            ]),
            'nip_niap' => null,
            'foto_path' => null,
            'urutan' => fake()->numberBetween(1, 15),
            'is_aktif' => true,
        ];
    }

    public function bpd(): static
    {
        return $this->state(fn () => [
            'jabatan' => 'Anggota BPD',
            'kelompok_sotk' => 'bpd',
        ]);
    }
}
