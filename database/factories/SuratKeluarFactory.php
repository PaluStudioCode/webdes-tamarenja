<?php

namespace Database\Factories;

use App\Enums\JenisSurat;
use App\Enums\StatusSuratKeluar;
use App\Models\Penduduk;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SuratKeluar>
 */
class SuratKeluarFactory extends Factory
{
    protected $model = SuratKeluar::class;

    protected static int $urutSequence = 1000;

    public function definition(): array
    {
        $tahun = (int) date('Y');
        $urut = ++static::$urutSequence;
        $padded = str_pad((string) $urut, 3, '0', STR_PAD_LEFT);

        return [
            'qr_token' => (string) Str::uuid(),
            'penduduk_id' => Penduduk::factory(),
            'sumber_pengajuan' => 'loket',
            'jenis_surat' => JenisSurat::Domisili,
            'kode_klasifikasi' => JenisSurat::Domisili->defaultKodeKlasifikasi(),
            'tahun_surat' => $tahun,
            'nomor_urut' => $urut,
            'nomor_surat' => "470 / {$padded} / DS-TMR / I / {$tahun}",
            'tanggal_surat' => now()->format('Y-m-d'),
            'keperluan' => 'Persyaratan Administrasi Perbankan',
            'data_khusus' => [
                'sejak_tahun' => 2015,
                'status_tempat_tinggal' => 'Milik Sendiri',
            ],
            'no_wa_pemohon' => '6281234567890',
            'opsi_penandatangan' => 'kepala_desa',
            'nama_penandatangan' => 'Ahmad Yani',
            'jabatan_penandatangan' => 'Kepala Desa Tamarenja',
            'nip_penandatangan' => null,
            'status' => StatusSuratKeluar::Terbit,
            'alasan_penolakan' => null,
            'alasan_pembatalan' => null,
            'processed_by' => User::factory(),
        ];
    }

    public function menungguVerifikasi(): static
    {
        return $this->state(fn () => [
            'qr_token' => null,
            'sumber_pengajuan' => 'online',
            'tahun_surat' => null,
            'nomor_urut' => null,
            'nomor_surat' => null,
            'tanggal_surat' => null,
            'opsi_penandatangan' => null,
            'nama_penandatangan' => null,
            'jabatan_penandatangan' => null,
            'nip_penandatangan' => null,
            'status' => StatusSuratKeluar::MenungguVerifikasi,
            'processed_by' => null,
        ]);
    }

    public function dibatalkan(): static
    {
        return $this->state(fn () => [
            'status' => StatusSuratKeluar::Dibatalkan,
            'alasan_pembatalan' => 'Kesalahan input data keperluan pemohon',
        ]);
    }
}
