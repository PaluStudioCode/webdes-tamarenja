<?php

namespace Database\Factories;

use App\Enums\StatusProyek;
use App\Enums\SumberDanaProyek;
use App\Models\ProyekPembangunan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProyekPembangunan>
 */
class ProyekPembangunanFactory extends Factory
{
    protected $model = ProyekPembangunan::class;

    public function definition(): array
    {
        $pagu = fake()->numberBetween(50, 300) * 1_000_000;

        return [
            'tahun_anggaran' => (int) date('Y'),
            'nama_kegiatan' => 'Pembangunan Rabat Beton Jalan Lingkungan ' . fake()->numerify('RT 0#'),
            'lokasi_dusun' => fake()->randomElement(['Dusun I', 'Dusun II', 'Dusun III', 'Dusun IV']),
            'pagu_anggaran' => $pagu,
            'realisasi_anggaran' => (int) ($pagu * 0.5),
            'sumber_dana' => fake()->randomElement(SumberDanaProyek::cases()),
            'volume_fisik' => '250 m x 2,5 m',
            'waktu_pelaksanaan' => '90 Hari Kalender',
            'pelaksana_kegiatan' => 'TPK Desa Tamarenja',
            'latitude' => -0.4512345,
            'longitude' => 119.8123456,
            'progres_fisik' => 50,
            'status_proyek' => StatusProyek::SedangDikerjakan,
            'foto_0_path' => 'proyek/2026/sample-0.webp',
            'foto_50_path' => 'proyek/2026/sample-50.webp',
            'foto_100_path' => null,
            'catatan_kendala' => null,
            'created_by' => User::factory(),
        ];
    }

    public function belumDimulai(): static
    {
        return $this->state(fn () => [
            'realisasi_anggaran' => 0,
            'progres_fisik' => 0,
            'status_proyek' => StatusProyek::BelumDimulai,
            'foto_0_path' => null,
            'foto_50_path' => null,
            'foto_100_path' => null,
        ]);
    }

    public function selesai(): static
    {
        return $this->state(fn (array $attributes) => [
            'realisasi_anggaran' => $attributes['pagu_anggaran'] ?? 100_000_000,
            'progres_fisik' => 100,
            'status_proyek' => StatusProyek::Selesai,
            'foto_0_path' => 'proyek/2026/sample-0.webp',
            'foto_50_path' => 'proyek/2026/sample-50.webp',
            'foto_100_path' => 'proyek/2026/sample-100.webp',
        ]);
    }
}
