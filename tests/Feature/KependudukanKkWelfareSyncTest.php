<?php

namespace Tests\Feature;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\User;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KependudukanKkWelfareSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EssentialDesaSeeder::class);
        $this->operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();
    }

    public function test_resident_automatically_inherits_status_kesejahteraan_from_kartu_keluarga(): void
    {
        // 1. Operator membuat KK dengan status_kesejahteraan = dtsen_desil_1
        $this->actingAs($this->operator)
            ->post('/panel/kependudukan/kartu-keluarga', [
                'nomor_kk' => '7203190101269901',
                'dusun' => 'Dusun I',
                'rt' => '001',
                'rw' => '001',
                'alamat' => 'Jl. Trans Sulawesi No. 99',
                'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1->value,
            ])
            ->assertRedirect();

        $kk = KartuKeluarga::query()->where('nomor_kk', '7203190101269901')->firstOrFail();
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $kk->status_kesejahteraan);

        // 2. Operator menambah Kepala Keluarga & Istri tanpa mengirim field status_kesejahteraan
        $this->actingAs($this->operator)
            ->post('/panel/kependudukan/penduduk', [
                'kartu_keluarga_id' => $kk->id,
                'nik' => '7203191001990101',
                'nama_lengkap' => 'Hendra Saputra',
                'tempat_lahir' => 'Donggala',
                'tanggal_lahir' => '1985-04-12',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'pendidikan' => 'SLTA',
                'pekerjaan' => 'Nelayan',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kependudukan' => StatusKependudukan::Aktif->value,
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->post('/panel/kependudukan/penduduk', [
                'kartu_keluarga_id' => $kk->id,
                'nik' => '7203195002990102',
                'nama_lengkap' => 'Nadia Lestari',
                'tempat_lahir' => 'Palu',
                'tanggal_lahir' => '1988-09-20',
                'jenis_kelamin' => 'P',
                'agama' => 'Islam',
                'pendidikan' => 'SLTA',
                'pekerjaan' => 'Mengurus Rumah Tangga',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'istri',
                'status_kependudukan' => StatusKependudukan::Aktif->value,
            ])
            ->assertRedirect();

        $kepala = Penduduk::query()->where('nik', '7203191001990101')->firstOrFail();
        $istri = Penduduk::query()->where('nik', '7203195002990102')->firstOrFail();

        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $kepala->status_kesejahteraan);
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $istri->status_kesejahteraan);
    }

    public function test_creating_kartu_keluarga_with_dynamic_anggota_saves_all_members_in_one_transaction(): void
    {
        $this->actingAs($this->operator)
            ->post('/panel/kependudukan/kartu-keluarga', [
                'nomor_kk' => '7203190101269905',
                'dusun' => 'Dusun I',
                'rt' => '002',
                'rw' => '001',
                'alamat' => 'Jl. Trans Sulawesi No. 105',
                'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2->value,
                'anggota' => [
                    [
                        'nik' => '7203191005800001',
                        'nama_lengkap' => 'Lukman Hakim',
                        'tempat_lahir' => 'Donggala',
                        'tanggal_lahir' => '1980-05-10',
                        'jenis_kelamin' => 'L',
                        'agama' => 'Islam',
                        'pendidikan' => 'SLTA',
                        'pekerjaan' => 'Petani',
                        'status_perkawinan' => 'kawin',
                        'hubungan_keluarga' => 'kepala_keluarga',
                    ],
                    [
                        'nik' => '7203195208840002',
                        'nama_lengkap' => 'Rahmawati',
                        'tempat_lahir' => 'Palu',
                        'tanggal_lahir' => '1984-08-12',
                        'jenis_kelamin' => 'P',
                        'agama' => 'Islam',
                        'pendidikan' => 'SLTA',
                        'pekerjaan' => 'IRT',
                        'status_perkawinan' => 'kawin',
                        'hubungan_keluarga' => 'istri',
                    ],
                ],
            ])
            ->assertRedirect();

        $kk = KartuKeluarga::query()->with('penduduk')->where('nomor_kk', '7203190101269905')->firstOrFail();
        $this->assertCount(2, $kk->penduduk);
        $this->assertSame(StatusKesejahteraan::DtsenDesil2, $kk->status_kesejahteraan);

        foreach ($kk->penduduk as $member) {
            $this->assertSame(StatusKesejahteraan::DtsenDesil2, $member->status_kesejahteraan);
            $this->assertSame(StatusKependudukan::Aktif, $member->status_kependudukan);
        }
    }

    public function test_updating_kartu_keluarga_status_kesejahteraan_syncs_all_family_members(): void
    {
        $kk = KartuKeluarga::factory()->desil1()->create([
            'nomor_kk' => '7203190101269902',
            'dusun' => 'Dusun II',
        ]);

        $kepala = Penduduk::factory()->kepalaKeluarga()->desil1()->aktif()->create([
            'kartu_keluarga_id' => $kk->id,
        ]);
        $istri = Penduduk::factory()->desil1()->aktif()->create([
            'kartu_keluarga_id' => $kk->id,
            'hubungan_keluarga' => 'istri',
        ]);
        $anak = Penduduk::factory()->desil1()->aktif()->create([
            'kartu_keluarga_id' => $kk->id,
            'hubungan_keluarga' => 'anak',
        ]);

        // Operator memperbarui status kesejahteraan KK dari Desil 1 menjadi Non-Prioritas
        $this->actingAs($this->operator)
            ->put("/panel/kependudukan/kartu-keluarga/{$kk->id}", [
                'nomor_kk' => $kk->nomor_kk,
                'dusun' => 'Dusun II',
                'rt' => $kk->rt,
                'rw' => $kk->rw,
                'alamat' => $kk->alamat,
                'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas->value,
            ])
            ->assertRedirect();

        $this->assertSame(StatusKesejahteraan::NonPrioritas, $kk->fresh()->status_kesejahteraan);
        $this->assertSame(StatusKesejahteraan::NonPrioritas, $kepala->fresh()->status_kesejahteraan);
        $this->assertSame(StatusKesejahteraan::NonPrioritas, $istri->fresh()->status_kesejahteraan);
        $this->assertSame(StatusKesejahteraan::NonPrioritas, $anak->fresh()->status_kesejahteraan);
    }

    public function test_moving_resident_to_another_kk_automatically_adopts_target_kk_status_kesejahteraan(): void
    {
        $kkAsal = KartuKeluarga::factory()->desil1()->create(['dusun' => 'Dusun I']);
        $kkTujuan = KartuKeluarga::factory()->desil34()->create(['dusun' => 'Dusun III']);

        $warga = Penduduk::factory()->desil1()->aktif()->create([
            'kartu_keluarga_id' => $kkAsal->id,
            'hubungan_keluarga' => 'anak',
        ]);

        $this->actingAs($this->operator)
            ->put("/panel/kependudukan/penduduk/{$warga->id}", [
                'kartu_keluarga_id' => $kkTujuan->id,
                'nik' => $warga->nik,
                'nama_lengkap' => $warga->nama_lengkap,
                'tempat_lahir' => $warga->tempat_lahir,
                'tanggal_lahir' => $warga->tanggal_lahir->format('Y-m-d'),
                'jenis_kelamin' => $warga->jenis_kelamin,
                'agama' => $warga->agama,
                'pendidikan' => $warga->pendidikan,
                'pekerjaan' => $warga->pekerjaan,
                'status_perkawinan' => $warga->status_perkawinan,
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kependudukan' => StatusKependudukan::Aktif->value,
            ])
            ->assertRedirect();

        $warga->refresh();
        $this->assertSame($kkTujuan->id, $warga->kartu_keluarga_id);
        $this->assertSame(StatusKesejahteraan::DtsenDesil34, $warga->status_kesejahteraan);
    }
}
