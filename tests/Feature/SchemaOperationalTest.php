<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SchemaOperationalTest extends TestCase
{
    use RefreshDatabase;

    private function createSamplePenduduk(string $kkSuffix = '0001', string $nikSuffix = '0001'): int
    {
        $kkId = DB::table('kartu_keluarga')->insertGetId([
            'nomor_kk' => '720101123456' . $kkSuffix,
            'dusun' => 'Dusun I',
            'rt' => '001',
            'rw' => '001',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('penduduk')->insertGetId([
            'kartu_keluarga_id' => $kkId,
            'nik' => '720101010190' . $nikSuffix,
            'nama_lengkap' => 'Warga Uji ' . $nikSuffix,
            'tempat_lahir' => 'Donggala',
            'tanggal_lahir' => '1990-01-01',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'status_perkawinan' => 'kawin',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kesejahteraan' => 'dtsen_desil_1',
            'status_kependudukan' => 'aktif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_all_operational_and_portal_tables_have_exact_prd_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('proyek_pembangunan', [
            'id', 'tahun_anggaran', 'nama_kegiatan', 'lokasi_dusun',
            'pagu_anggaran', 'realisasi_anggaran', 'sumber_dana', 'volume_fisik',
            'waktu_pelaksanaan', 'pelaksana_kegiatan', 'latitude', 'longitude',
            'progres_fisik', 'status_proyek', 'foto_0_path', 'foto_50_path',
            'foto_100_path', 'catatan_kendala', 'created_by',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('apbdes', [
            'id', 'tahun_anggaran', 'jenis_dokumen', 'is_aktif_publik',
            'is_terkunci', 'nomor_perdes', 'tanggal_penetapan', 'created_by',
            'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('apbdes', 'deleted_at'));

        $this->assertTrue(Schema::hasColumns('apbdes_rincian', [
            'id', 'apbdes_id', 'kelompok', 'kode_pos', 'nama_pos',
            'is_otomatis_modul_1', 'pagu_anggaran', 'realisasi_tahap_1',
            'realisasi_tahap_2', 'realisasi_tahap_3', 'total_realisasi',
            'urutan', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('apbdes_rincian', 'deleted_at'));

        $this->assertTrue(Schema::hasColumns('surat_keluar', [
            'id', 'qr_token', 'penduduk_id', 'sumber_pengajuan', 'jenis_surat',
            'kode_klasifikasi', 'tahun_surat', 'nomor_urut', 'nomor_surat',
            'tanggal_surat', 'keperluan', 'data_khusus', 'no_wa_pemohon',
            'opsi_penandatangan', 'nama_penandatangan', 'jabatan_penandatangan',
            'nip_penandatangan', 'status', 'alasan_penolakan', 'alasan_pembatalan',
            'processed_by', 'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('surat_masuk', [
            'id', 'tahun_agenda', 'nomor_urut_agenda', 'nomor_surat_masuk',
            'tanggal_surat', 'tanggal_terima', 'pengirim', 'perihal',
            'tujuan_disposisi', 'catatan_disposisi', 'berkas_scan_path',
            'created_by', 'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('penerima_bansos', [
            'id', 'penduduk_id', 'jenis_bantuan', 'bentuk_nominal',
            'tahun_anggaran', 'tahap_penyaluran', 'kategori_ekonomi',
            'status_kepesertaan', 'keterangan', 'created_by',
            'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('kegiatan_desa', [
            'id', 'judul', 'slug', 'kategori', 'tanggal_kegiatan',
            'lokasi_dusun', 'uraian', 'status_publikasi', 'published_at',
            'created_by', 'created_at', 'updated_at', 'deleted_at',
        ]));

        $this->assertTrue(Schema::hasColumns('kegiatan_desa_foto', [
            'id', 'kegiatan_desa_id', 'foto_path', 'urutan',
            'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('kegiatan_desa_foto', 'deleted_at'));

        $this->assertTrue(Schema::hasColumns('perangkat_desa', [
            'id', 'nama_lengkap', 'jabatan', 'kelompok_sotk', 'nip_niap',
            'foto_path', 'urutan', 'is_aktif', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('perangkat_desa', 'deleted_at'));

        $this->assertTrue(Schema::hasColumns('dokumen_ppid', [
            'id', 'judul_dokumen', 'kategori', 'tahun_dokumen', 'file_pdf_path',
            'ukuran_file_kb', 'jumlah_unduhan', 'is_publik', 'uploaded_by',
            'created_at', 'updated_at', 'deleted_at',
        ]));
    }

    public function test_unsigned_constraints_reject_negative_values(): void
    {
        $user = User::factory()->create();

        try {
            DB::table('proyek_pembangunan')->insert([
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Rabat Beton',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => -50000000,
                'realisasi_anggaran' => 0,
                'sumber_dana' => 'dds',
                'progres_fisik' => 0,
                'status_proyek' => 'belum_dimulai',
                'created_by' => $user->id,
            ]);
            $this->fail('Expected QueryException for negative pagu_anggaran.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $apbdesId = DB::table('apbdes')->insertGetId([
            'tahun_anggaran' => 2026,
            'jenis_dokumen' => 'murni',
            'created_by' => $user->id,
        ]);

        $this->expectException(QueryException::class);

        DB::table('apbdes_rincian')->insert([
            'apbdes_id' => $apbdesId,
            'kelompok' => 'pendapatan',
            'kode_pos' => 'dds',
            'nama_pos' => 'Dana Desa',
            'pagu_anggaran' => 100000000,
            'realisasi_tahap_1' => -1,
        ]);
    }

    public function test_proyek_coordinates_precision_and_user_restrict_fk(): void
    {
        $user = User::factory()->create();

        $proyekId = DB::table('proyek_pembangunan')->insertGetId([
            'tahun_anggaran' => 2026,
            'nama_kegiatan' => 'Drainase Lingkungan',
            'lokasi_dusun' => 'Dusun II',
            'pagu_anggaran' => 125000000,
            'realisasi_anggaran' => 60000000,
            'sumber_dana' => 'dds',
            'latitude' => -0.4512345,
            'longitude' => 119.8123456,
            'progres_fisik' => 50,
            'status_proyek' => 'sedang_dikerjakan',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('proyek_pembangunan')->where('id', $proyekId)->first();
        $this->assertSame('-0.4512345', (string) $row->latitude);
        $this->assertSame('119.8123456', (string) $row->longitude);

        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $user->id)->delete();
    }

    public function test_apbdes_unique_and_rincian_cascade_delete(): void
    {
        $user = User::factory()->create();

        $apbdesId = DB::table('apbdes')->insertGetId([
            'tahun_anggaran' => 2026,
            'jenis_dokumen' => 'murni',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Duplikasi (tahun_anggaran, jenis_dokumen) harus ditolak
        try {
            DB::table('apbdes')->insert([
                'tahun_anggaran' => 2026,
                'jenis_dokumen' => 'murni',
                'created_by' => $user->id,
            ]);
            $this->fail('Expected QueryException for duplicate (tahun_anggaran, jenis_dokumen).');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        DB::table('apbdes_rincian')->insert([
            'apbdes_id' => $apbdesId,
            'kelompok' => 'belanja',
            'kode_pos' => 'bidang_2_fisik',
            'nama_pos' => 'Pelaksanaan Pembangunan Desa - Komponen Fisik',
            'is_otomatis_modul_1' => true,
            'pagu_anggaran' => 200000000,
            'urutan' => 8,
        ]);

        // Duplikasi (apbdes_id, kode_pos) harus ditolak
        try {
            DB::table('apbdes_rincian')->insert([
                'apbdes_id' => $apbdesId,
                'kelompok' => 'belanja',
                'kode_pos' => 'bidang_2_fisik',
                'nama_pos' => 'Duplikat Pos',
            ]);
            $this->fail('Expected QueryException for duplicate (apbdes_id, kode_pos).');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // Hapus apbdes -> apbdes_rincian ikut terhapus otomatis (CASCADE)
        DB::table('apbdes')->where('id', $apbdesId)->delete();
        $this->assertSame(0, DB::table('apbdes_rincian')->where('apbdes_id', $apbdesId)->count());
    }

    public function test_surat_keluar_and_surat_masuk_unique_and_penduduk_restrict(): void
    {
        $user = User::factory()->create();
        $pendudukId = $this->createSamplePenduduk('0010', '0010');
        $token = (string) Str::uuid();

        // Dua antrean online ber-qr_token NULL & nomor_surat NULL diizinkan
        DB::table('surat_keluar')->insert([
            [
                'penduduk_id' => $pendudukId,
                'sumber_pengajuan' => 'online',
                'jenis_surat' => 'domisili',
                'kode_klasifikasi' => '470',
                'keperluan' => 'Persyaratan Bank',
                'data_khusus' => json_encode(['sejak_tahun' => 2015, 'status_tempat_tinggal' => 'Milik Sendiri']),
                'status' => 'menunggu_verifikasi',
            ],
            [
                'penduduk_id' => $pendudukId,
                'sumber_pengajuan' => 'online',
                'jenis_surat' => 'sktm',
                'kode_klasifikasi' => '400',
                'keperluan' => 'Beasiswa',
                'data_khusus' => json_encode(['penghasilan_rata_rata' => 800000, 'jumlah_tanggungan' => 3]),
                'status' => 'menunggu_verifikasi',
            ],
        ]);

        // Surat terbit ber-qr_token & nomor_urut resmi
        DB::table('surat_keluar')->insert([
            'qr_token' => $token,
            'penduduk_id' => $pendudukId,
            'sumber_pengajuan' => 'loket',
            'jenis_surat' => 'sku',
            'kode_klasifikasi' => '500',
            'tahun_surat' => 2026,
            'nomor_urut' => 1,
            'nomor_surat' => '500 / 001 / DS-TMR / XI / 2026',
            'tanggal_surat' => '2026-11-05',
            'keperluan' => 'KUR Mikro',
            'data_khusus' => json_encode(['nama_usaha' => 'Warung Berkah']),
            'status' => 'terbit',
            'processed_by' => $user->id,
        ]);

        // Duplikasi qr_token ditolak
        try {
            DB::table('surat_keluar')->insert([
                'qr_token' => $token,
                'penduduk_id' => $pendudukId,
                'jenis_surat' => 'sku',
                'kode_klasifikasi' => '500',
                'tahun_surat' => 2026,
                'nomor_urut' => 2,
                'nomor_surat' => '500 / 002 / DS-TMR / XI / 2026',
                'keperluan' => 'Tes',
                'data_khusus' => json_encode([]),
            ]);
            $this->fail('Expected QueryException for duplicate qr_token.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // Duplikasi (tahun_surat, nomor_urut) ditolak
        try {
            DB::table('surat_keluar')->insert([
                'qr_token' => (string) Str::uuid(),
                'penduduk_id' => $pendudukId,
                'jenis_surat' => 'sku',
                'kode_klasifikasi' => '500',
                'tahun_surat' => 2026,
                'nomor_urut' => 1,
                'nomor_surat' => '500 / 999 / DS-TMR / XI / 2026',
                'keperluan' => 'Tes',
                'data_khusus' => json_encode([]),
            ]);
            $this->fail('Expected QueryException for duplicate (tahun_surat, nomor_urut).');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // Duplikasi surat_masuk (tahun_agenda, nomor_urut_agenda) ditolak
        DB::table('surat_masuk')->insert([
            'tahun_agenda' => 2026,
            'nomor_urut_agenda' => 1,
            'nomor_surat_masuk' => '005/12/KEC/2026',
            'tanggal_terima' => '2026-11-05',
            'pengirim' => 'Kecamatan Sindue Tobata',
            'perihal' => 'Undangan Rapat Koordinasi',
            'created_by' => $user->id,
        ]);

        try {
            DB::table('surat_masuk')->insert([
                'tahun_agenda' => 2026,
                'nomor_urut_agenda' => 1,
                'nomor_surat_masuk' => '005/13/KEC/2026',
                'tanggal_terima' => '2026-11-05',
                'pengirim' => 'Dinas PMD',
                'perihal' => 'Duplikat Nomor Agenda',
                'created_by' => $user->id,
            ]);
            $this->fail('Expected QueryException for duplicate (tahun_agenda, nomor_urut_agenda).');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // Hard delete penduduk ber-arsip surat_keluar wajib gagal karena FK RESTRICT
        $this->expectException(QueryException::class);
        DB::table('penduduk')->where('id', $pendudukId)->delete();
    }

    public function test_penerima_bansos_unique_and_penduduk_restrict(): void
    {
        $user = User::factory()->create();
        $pendudukId = $this->createSamplePenduduk('0020', '0020');

        DB::table('penerima_bansos')->insert([
            'penduduk_id' => $pendudukId,
            'jenis_bantuan' => 'blt_dd',
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => 'dtsen_desil_1',
            'status_kepesertaan' => 'aktif',
            'created_by' => $user->id,
        ]);

        // Kunci komposit kembar ditolak
        try {
            DB::table('penerima_bansos')->insert([
                'penduduk_id' => $pendudukId,
                'jenis_bantuan' => 'blt_dd',
                'bentuk_nominal' => 'Rp 300.000 / Bulan',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
                'kategori_ekonomi' => 'dtsen_desil_1',
                'status_kepesertaan' => 'aktif',
                'created_by' => $user->id,
            ]);
            $this->fail('Expected QueryException for duplicate penerima_bansos composite key.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        // Hard delete penduduk ber-arsip Bansos wajib gagal karena FK RESTRICT
        $this->expectException(QueryException::class);
        DB::table('penduduk')->where('id', $pendudukId)->delete();
    }

    public function test_kegiatan_desa_slug_unique_and_foto_cascade_delete(): void
    {
        $user = User::factory()->create();

        $kegiatanId = DB::table('kegiatan_desa')->insertGetId([
            'judul' => ' Kerja Bakti Bersih Desa ',
            'slug' => 'kerja-bakti-bersih-desa',
            'kategori' => 'gotong_royong',
            'tanggal_kegiatan' => '2026-11-05',
            'lokasi_dusun' => 'Dusun I',
            'uraian' => 'Pelaksanaan gotong royong bersama warga.',
            'status_publikasi' => 'published',
            'published_at' => now(),
            'created_by' => $user->id,
        ]);

        try {
            DB::table('kegiatan_desa')->insert([
                'judul' => 'Kerja Bakti Lain',
                'slug' => 'kerja-bakti-bersih-desa',
                'kategori' => 'gotong_royong',
                'tanggal_kegiatan' => '2026-11-05',
                'lokasi_dusun' => 'Dusun II',
                'uraian' => 'Duplikat slug.',
                'created_by' => $user->id,
            ]);
            $this->fail('Expected QueryException for duplicate kegiatan_desa.slug.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        DB::table('kegiatan_desa_foto')->insert([
            [
                'kegiatan_desa_id' => $kegiatanId,
                'foto_path' => 'kegiatan/2026/foto-1.webp',
                'urutan' => 1,
            ],
            [
                'kegiatan_desa_id' => $kegiatanId,
                'foto_path' => 'kegiatan/2026/foto-2.webp',
                'urutan' => 2,
            ],
        ]);

        $this->assertSame(2, DB::table('kegiatan_desa_foto')->where('kegiatan_desa_id', $kegiatanId)->count());

        // Hard delete kegiatan_desa -> kegiatan_desa_foto terhapus otomatis (CASCADE)
        DB::table('kegiatan_desa')->where('id', $kegiatanId)->delete();
        $this->assertSame(0, DB::table('kegiatan_desa_foto')->where('kegiatan_desa_id', $kegiatanId)->count());
    }
}
