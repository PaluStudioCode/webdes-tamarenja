<?php

namespace Database\Factories;

use App\Enums\KategoriKegiatan;
use App\Enums\StatusPublikasi;
use App\Models\KegiatanDesa;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KegiatanDesa>
 */
class KegiatanDesaFactory extends Factory
{
    protected $model = KegiatanDesa::class;

    public function definition(): array
    {
        $judul = 'Kegiatan Desa ' . fake()->unique()->words(3, true);

        return [
            'judul' => ucwords($judul),
            'slug' => Str::slug($judul) . '-' . fake()->unique()->numberBetween(100, 9999),
            'kategori' => fake()->randomElement(KategoriKegiatan::cases()),
            'tanggal_kegiatan' => now()->subDays(fake()->numberBetween(1, 30))->format('Y-m-d'),
            'lokasi_dusun' => fake()->randomElement(['Balai Desa Tamarenja', 'Dusun I', 'Dusun II', 'Dusun III']),
            'uraian' => fake()->paragraphs(2, true),
            'status_publikasi' => StatusPublikasi::Published,
            'published_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status_publikasi' => StatusPublikasi::Draft,
            'published_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status_publikasi' => StatusPublikasi::Published,
            'published_at' => now(),
        ]);
    }
}
