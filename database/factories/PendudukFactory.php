<?php

namespace Database\Factories;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Penduduk>
 */
class PendudukFactory extends Factory
{
    protected $model = Penduduk::class;

    public function definition(): array
    {
        return [
            'kartu_keluarga_id' => KartuKeluarga::factory(),
            'nik' => fake()->unique()->numerify('720101##########'),
            'nama_lengkap' => fake()->name(),
            'tempat_lahir' => fake()->randomElement(['Donggala', 'Palu', 'Sigi', 'Parigi']),
            'tanggal_lahir' => fake()->dateTimeBetween('-65 years', '-1 year')->format('Y-m-d'),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha']),
            'pendidikan' => fake()->randomElement(['SD', 'SLTP', 'SLTA', 'D3', 'S1', 'Tidak/Belum Sekolah']),
            'pekerjaan' => fake()->randomElement(['Petani/Pekebun', 'Nelayan', 'Wiraswasta', 'Mengurus Rumah Tangga', 'Pelajar/Mahasiswa', 'PNS']),
            'status_perkawinan' => fake()->randomElement(['belum_kawin', 'kawin', 'cerai_hidup', 'cerai_mati']),
            'hubungan_keluarga' => fake()->randomElement(['kepala_keluarga', 'istri', 'anak', 'lainnya']),
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
            'status_kependudukan' => StatusKependudukan::Aktif,
            'tanggal_mutasi' => null,
            'catatan_mutasi' => null,
        ];
    }

    public function aktif(): static
    {
        return $this->state(fn () => [
            'status_kependudukan' => StatusKependudukan::Aktif,
            'tanggal_mutasi' => null,
            'catatan_mutasi' => null,
        ]);
    }

    public function meninggal(): static
    {
        return $this->state(fn () => [
            'status_kependudukan' => StatusKependudukan::MeninggalDunia,
            'tanggal_mutasi' => now()->subDays(10)->format('Y-m-d'),
            'catatan_mutasi' => 'Wafat di Desa Tamarenja',
        ]);
    }

    public function pindah(): static
    {
        return $this->state(fn () => [
            'status_kependudukan' => StatusKependudukan::PindahKeluar,
            'tanggal_mutasi' => now()->subDays(20)->format('Y-m-d'),
            'catatan_mutasi' => 'Pindah domisili ke Kota Palu',
        ]);
    }

    public function desil1(): static
    {
        return $this->state(fn () => [
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);
    }

    public function desil2(): static
    {
        return $this->state(fn () => [
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
        ]);
    }

    public function desil34(): static
    {
        return $this->state(fn () => [
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil34,
        ]);
    }

    public function nonPrioritas(): static
    {
        return $this->state(fn () => [
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
        ]);
    }

    public function kepalaKeluarga(): static
    {
        return $this->state(fn () => [
            'hubungan_keluarga' => 'kepala_keluarga',
            'jenis_kelamin' => 'L',
            'status_perkawinan' => 'kawin',
        ]);
    }
}
