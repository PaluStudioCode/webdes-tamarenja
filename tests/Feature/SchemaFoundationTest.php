<?php

namespace Tests\Feature;

use App\Enums\JenisBansos;
use App\Enums\JenisDokumenApbdes;
use App\Enums\JenisSurat;
use App\Enums\KategoriKegiatan;
use App\Enums\KelompokPosApbdes;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusProyek;
use App\Enums\StatusPublikasi;
use App\Enums\StatusSuratKeluar;
use App\Enums\SumberDanaProyek;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_13_php_backed_enums_match_prd_values(): void
    {
        $this->assertSame(
            ['operator', 'kepala_desa'],
            array_column(UserRole::cases(), 'value')
        );

        $this->assertSame(
            ['aktif', 'meninggal_dunia', 'pindah_keluar'],
            array_column(StatusKependudukan::cases(), 'value')
        );

        $this->assertSame(
            ['dtsen_desil_1', 'dtsen_desil_2', 'dtsen_desil_3_4', 'non_prioritas'],
            array_column(StatusKesejahteraan::cases(), 'value')
        );
        $this->assertTrue(StatusKesejahteraan::DtsenDesil1->isSasaranPrioritas());
        $this->assertFalse(StatusKesejahteraan::NonPrioritas->isSasaranPrioritas());

        $this->assertSame(
            ['dds', 'add', 'bantuan_keuangan', 'pades'],
            array_column(SumberDanaProyek::cases(), 'value')
        );

        $this->assertSame(
            ['belum_dimulai', 'sedang_dikerjakan', 'selesai'],
            array_column(StatusProyek::cases(), 'value')
        );

        $this->assertSame(
            ['murni', 'perubahan'],
            array_column(JenisDokumenApbdes::cases(), 'value')
        );

        $this->assertSame(
            ['pendapatan', 'belanja', 'pembiayaan_penerimaan', 'pembiayaan_pengeluaran'],
            array_column(KelompokPosApbdes::cases(), 'value')
        );

        $this->assertSame(
            [
                'sku',
                'domisili',
                'sktm',
                'pengantar_skck',
                'kematian',
                'kelahiran',
                'belum_menikah',
                'janda_duda',
                'kehilangan',
                'keterangan_umum',
            ],
            array_column(JenisSurat::cases(), 'value')
        );
        $this->assertSame(['400', '460'], JenisSurat::Sktm->allowedKodeKlasifikasi());
        $this->assertSame(['140', '000'], JenisSurat::KeteranganUmum->allowedKodeKlasifikasi());
        $this->assertSame('474.3', JenisSurat::Kematian->defaultKodeKlasifikasi());

        $this->assertSame(
            ['menunggu_verifikasi', 'terbit', 'ditolak', 'dibatalkan'],
            array_column(StatusSuratKeluar::cases(), 'value')
        );

        $this->assertSame(
            ['pkh', 'bpnt', 'blt_dd', 'rtlh', 'pbi_jkn', 'bantuan_daerah'],
            array_column(JenisBansos::cases(), 'value')
        );

        $this->assertSame(
            ['aktif', 'graduasi', 'pindah', 'meninggal_dunia'],
            array_column(StatusPesertaBansos::cases(), 'value')
        );

        $this->assertSame(
            ['penyaluran_bansos', 'posyandu_kesehatan', 'gotong_royong', 'musdes', 'pelatihan_bumdes'],
            array_column(KategoriKegiatan::cases(), 'value')
        );

        $this->assertSame(
            ['draft', 'published'],
            array_column(StatusPublikasi::cases(), 'value')
        );
    }

    public function test_foundation_tables_and_columns_exist_according_to_prd(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'name', 'email', 'password', 'role', 'is_active',
            'last_login_at', 'remember_token', 'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('profil_desa', [
            'id', 'nama_desa', 'kode_desa_surat', 'kecamatan', 'kabupaten',
            'provinsi', 'kode_wilayah', 'daftar_dusun', 'alamat_kantor',
            'jam_pelayanan', 'email_desa', 'no_wa_pelayanan', 'logo_desa_path',
            'nama_kepala_desa', 'nip_kepala_desa', 'foto_kepala_desa_path',
            'sambutan_kepala_desa', 'nama_sekdes', 'nip_sekdes', 'visi', 'misi',
            'sejarah_singkat', 'potensi_unggulan', 'tahun_anggaran_aktif',
            'created_at', 'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('kartu_keluarga', [
            'id', 'nomor_kk', 'dusun', 'rt', 'rw', 'alamat', 'status_kesejahteraan',
            'status_kepemilikan_rumah', 'kondisi_rumah', 'jenis_listrik', 'kamar_mandi',
            'penghasilan_bulanan', 'kepemilikan_usaha', 'kepemilikan_hewan_ternak',
            'kepemilikan_kendaraan_elektronik', 'kepemilikan_shml',
            'kepemilikan_tanah_bidang', 'kepemilikan_tanah_sawah', 'tanaman',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('penduduk', [
            'id', 'kartu_keluarga_id', 'nik', 'nama_lengkap', 'tempat_lahir',
            'tanggal_lahir', 'jenis_kelamin', 'agama', 'suku', 'kewarganegaraan',
            'pendidikan', 'peserta_kip_pip', 'kelas_sekolah', 'pekerjaan',
            'status_perkawinan', 'hubungan_keluarga', 'status_kesejahteraan',
            'jenis_bpjs', 'status_kependudukan', 'tanggal_mutasi', 'catatan_mutasi',
            'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    public function test_user_factory_states_and_casts_work_properly(): void
    {
        $operator = User::factory()->operator()->create();
        $kades = User::factory()->kepalaDesa()->inactive()->create();

        $this->assertSame(UserRole::Operator, $operator->role);
        $this->assertTrue($operator->is_active);
        $this->assertNull($operator->last_login_at);

        $this->assertSame(UserRole::KepalaDesa, $kades->role);
        $this->assertFalse($kades->is_active);
    }

    public function test_kartu_keluarga_cannot_be_hard_deleted_when_having_penduduk_fk_restrict(): void
    {
        $kkId = DB::table('kartu_keluarga')->insertGetId([
            'nomor_kk' => '7201011234560001',
            'dusun' => 'Dusun I',
            'rt' => '001',
            'rw' => '001',
            'alamat' => 'Jl. Poros Desa No. 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penduduk')->insert([
            'kartu_keluarga_id' => $kkId,
            'nik' => '7201010101900001',
            'nama_lengkap' => 'Budi Santoso',
            'tempat_lahir' => 'Palu',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_perkawinan' => 'kawin',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1->value,
            'status_kependudukan' => StatusKependudukan::Aktif->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('kartu_keluarga')->where('id', $kkId)->delete();
    }

    public function test_unique_constraints_on_nomor_kk_and_nik(): void
    {
        $kkId = DB::table('kartu_keluarga')->insertGetId([
            'nomor_kk' => '7201011234560002',
            'dusun' => 'Dusun II',
            'rt' => '002',
            'rw' => '001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::table('kartu_keluarga')->insert([
                'nomor_kk' => '7201011234560002',
                'dusun' => 'Dusun III',
                'rt' => '001',
                'rw' => '001',
            ]);
            $this->fail('Expected QueryException for duplicate nomor_kk was not thrown.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        DB::table('penduduk')->insert([
            'kartu_keluarga_id' => $kkId,
            'nik' => '7201010505950002',
            'nama_lengkap' => 'Siti Aminah',
            'tempat_lahir' => 'Donggala',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
            'status_perkawinan' => 'belum_kawin',
            'hubungan_keluarga' => 'anak',
        ]);

        $this->expectException(QueryException::class);

        DB::table('penduduk')->insert([
            'kartu_keluarga_id' => $kkId,
            'nik' => '7201010505950002',
            'nama_lengkap' => 'Siti Duplikat',
            'tempat_lahir' => 'Donggala',
            'tanggal_lahir' => '1995-05-05',
            'jenis_kelamin' => 'P',
            'agama' => 'Islam',
            'status_perkawinan' => 'belum_kawin',
            'hubungan_keluarga' => 'anak',
        ]);
    }

    public function test_composite_indexes_exist_on_penduduk(): void
    {
        $indexes = Schema::getIndexes('penduduk');
        $indexColumns = array_map(fn (array $idx) => $idx['columns'], $indexes);

        $this->assertContains(['nik', 'tanggal_lahir'], $indexColumns);
        $this->assertContains(['status_kependudukan', 'status_kesejahteraan'], $indexColumns);
        $this->assertContains(['nama_lengkap'], $indexColumns);
    }

    public function test_profil_desa_stores_json_and_enforces_unsigned_tahun(): void
    {
        $id = DB::table('profil_desa')->insertGetId([
            'nama_desa' => 'Tamarenja',
            'kode_desa_surat' => 'DS-TMR',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'daftar_dusun' => json_encode(['Dusun I', 'Dusun II', 'Dusun III']),
            'alamat_kantor' => 'Jl. Trans Sulawesi Desa Tamarenja',
            'nama_kepala_desa' => 'Ahmad Yani',
            'nama_sekdes' => 'Hendra Wijaya',
            'misi' => json_encode(['Pelayanan prima', 'Transparansi anggaran']),
            'tahun_anggaran_aktif' => 2026,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertGreaterThan(0, $id);

        $this->expectException(QueryException::class);

        DB::table('profil_desa')->where('id', $id)->update([
            'tahun_anggaran_aktif' => -1,
        ]);
    }
}
