<?php

namespace Database\Factories;

use App\Enums\JenisBansos;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PenerimaBansos>
 */
class PenerimaBansosFactory extends Factory
{
    protected $model = PenerimaBansos::class;

    public function definition(): array
    {
        return [
            'penduduk_id' => Penduduk::factory()->desil1(),
            'jenis_bantuan' => fake()->randomElement(JenisBansos::cases()),
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => (int) date('Y'),
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil1,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'keterangan' => null,
            'created_by' => User::factory(),
        ];
    }

    public function graduasi(): static
    {
        return $this->state(fn () => [
            'status_kepesertaan' => StatusPesertaBansos::Graduasi,
        ]);
    }
}
