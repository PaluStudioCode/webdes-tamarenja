<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisBansos;
use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Enums\UserRole;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class KependudukanCrudAndMutasiTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $kades;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->kades = User::factory()->create([
            'role' => UserRole::KepalaDesa,
            'is_active' => true,
        ]);
    }

    public function test_operator_and_kepala_desa_can_access_kependudukan_index_and_read_endpoints(): void
    {
        $kk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101200001',
            'dusun' => 'Dusun I',
        ]);

        $penduduk = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081205850001',
            'nama_lengkap' => 'Ahmad Tamarenja',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        // Operator akses GET
        $this->actingAs($this->operator)
            ->get(route('panel.kependudukan.index'))
            ->assertOk();

        // Kepala Desa akses GET (Read-Only)
        $this->actingAs($this->kades)
            ->get(route('panel.kependudukan.index'))
            ->assertOk();

        $this->actingAs($this->kades)
            ->getJson(route('panel.kependudukan.kk.show', $kk))
            ->assertOk()
            ->assertJsonPath('data.nomor_kk', '7203080101200001');

        $this->actingAs($this->kades)
            ->getJson(route('panel.kependudukan.penduduk.show', $penduduk))
            ->assertOk()
            ->assertJsonPath('data.nik', '7203081205850001');

        $this->actingAs($this->kades)
            ->getJson(route('panel.kependudukan.cari-nik', ['q' => '720308']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_lengkap', 'Ahmad Tamarenja');
    }

    public function test_kepala_desa_is_forbidden_from_all_write_and_mutasi_endpoints(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101200002']);
        $penduduk = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081205850002',
        ]);

        $this->actingAs($this->kades)
            ->post(route('panel.kependudukan.kk.store'), [
                'nomor_kk' => '7203080101200099',
                'dusun' => 'Dusun I',
                'rt' => '001',
                'rw' => '001',
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->put(route('panel.kependudukan.kk.update', $kk), [
                'nomor_kk' => '7203080101200002',
                'dusun' => 'Dusun II',
                'rt' => '002',
                'rw' => '001',
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->delete(route('panel.kependudukan.kk.destroy', $kk))
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->post(route('panel.kependudukan.penduduk.store'), [
                'kartu_keluarga_id' => $kk->id,
                'nik' => '7203081205850099',
                'nama_lengkap' => 'Warga Baru',
                'tempat_lahir' => 'Palu',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas->value,
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->put(route('panel.kependudukan.penduduk.update', $penduduk), [
                'kartu_keluarga_id' => $kk->id,
                'nik' => $penduduk->nik,
                'nama_lengkap' => 'Ubah Nama',
                'tempat_lahir' => 'Palu',
                'tanggal_lahir' => '1990-01-01',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas->value,
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $penduduk), [
                'status_kependudukan' => StatusKependudukan::PindahKeluar->value,
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->delete(route('panel.kependudukan.penduduk.destroy', $penduduk))
            ->assertForbidden();
    }

    public function test_kartu_keluarga_crud_validation_and_soft_delete_restoration(): void
    {
        // 1. Tolak nomor_kk yang bukan 16 digit numerik
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.kk.store'), [
                'nomor_kk' => '12345ABC',
                'dusun' => 'Dusun I',
                'rt' => '001',
                'rw' => '001',
            ])
            ->assertSessionHasErrors('nomor_kk');

        // 2. Buat KK baru (termasuk pembersihan kutip tunggal pembuka '7203...)
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.kk.store'), [
                'nomor_kk' => "'7203080101260001",
                'dusun' => 'Dusun I',
                'rt' => '001',
                'rw' => '002',
                'alamat' => 'Jl. Trans Palu-Donggala No. 10',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notification.type', 'success');

        $kk = KartuKeluarga::where('nomor_kk', '7203080101260001')->firstOrFail();
        $this->assertSame('Dusun I', $kk->dusun);

        // 3. Tolak duplikasi nomor_kk yang masih aktif
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.kk.store'), [
                'nomor_kk' => '7203080101260001',
                'dusun' => 'Dusun II',
                'rt' => '002',
                'rw' => '002',
            ])
            ->assertSessionHasErrors('nomor_kk');

        // 4. Update KK (termasuk field hunian, ekonomi, dan aset keluarga)
        $this->actingAs($this->operator)
            ->put(route('panel.kependudukan.kk.update', $kk), [
                'nomor_kk' => '7203080101260001',
                'dusun' => 'Dusun III',
                'rt' => '003',
                'rw' => '001',
                'alamat' => 'Alamat Baru Dusun III',
                'status_kepemilikan_rumah' => 'Milik Sendiri',
                'kondisi_rumah' => 'Permanen',
                'jenis_listrik' => 'Manual 900 Watt',
                'kamar_mandi' => 'Ada',
                'penghasilan_bulanan' => 'Rp 2.500.000',
                'kepemilikan_usaha' => 'Warung Sembako',
                'kepemilikan_hewan_ternak' => 'Sapi 2 ekor, Ayam 15 ekor',
                'kepemilikan_kendaraan_elektronik' => 'Motor 1, TV 1, HP 2',
                'kepemilikan_shml' => '1',
                'kepemilikan_tanah_bidang' => '2',
                'kepemilikan_tanah_sawah' => '1 Bidang',
                'tanaman' => 'Kelapa 50 Pohon, Coklat 40 Pohon',
            ])
            ->assertSessionHasNoErrors();

        $freshKk = $kk->fresh();
        $this->assertSame('Dusun III', $freshKk->dusun);
        $this->assertSame('Milik Sendiri', $freshKk->status_kepemilikan_rumah);
        $this->assertSame('Permanen', $freshKk->kondisi_rumah);
        $this->assertSame('Manual 900 Watt', $freshKk->jenis_listrik);
        $this->assertSame('Ada', $freshKk->kamar_mandi);
        $this->assertSame('Rp 2.500.000', $freshKk->penghasilan_bulanan);
        $this->assertSame('Warung Sembako', $freshKk->kepemilikan_usaha);
        $this->assertSame('Sapi 2 ekor, Ayam 15 ekor', $freshKk->kepemilikan_hewan_ternak);
        $this->assertSame('Motor 1, TV 1, HP 2', $freshKk->kepemilikan_kendaraan_elektronik);
        $this->assertSame('1', $freshKk->kepemilikan_shml);
        $this->assertSame('2', $freshKk->kepemilikan_tanah_bidang);
        $this->assertSame('1 Bidang', $freshKk->kepemilikan_tanah_sawah);
        $this->assertSame('Kelapa 50 Pohon, Coklat 40 Pohon', $freshKk->tanaman);

        // 5. Soft delete KK tanpa anggota -> berhasil
        $this->actingAs($this->operator)
            ->delete(route('panel.kependudukan.kk.destroy', $kk))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('kartu_keluarga', ['id' => $kk->id]);

        // 6. Input ulang nomor_kk yang pernah di-soft-delete -> otomatis restore & update (withTrashed)
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.kk.store'), [
                'nomor_kk' => '7203080101260001',
                'dusun' => 'Dusun I Pulih',
                'rt' => '004',
                'rw' => '002',
                'alamat' => 'Alamat Setelah Dipulihkan',
            ])
            ->assertSessionHasNoErrors();

        $restoredKk = KartuKeluarga::withTrashed()->findOrFail($kk->id);
        $this->assertFalse($restoredKk->trashed());
        $this->assertSame('Dusun I Pulih', $restoredKk->dusun);
        $this->assertSame('004', $restoredKk->rt);
    }

    public function test_cannot_delete_kartu_keluarga_that_still_has_members(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101260005']);
        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081505800005',
        ]);

        $this->actingAs($this->operator)
            ->delete(route('panel.kependudukan.kk.destroy', $kk))
            ->assertSessionHasErrors('kartu_keluarga')
            ->assertSessionHas('notification.type', 'error');

        $this->assertNotSoftDeleted('kartu_keluarga', ['id' => $kk->id]);
    }

    public function test_penduduk_crud_validation_and_soft_delete_restoration(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101260010']);

        // 1. Tolak NIK bukan 16 digit numerik
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.penduduk.store'), [
                'kartu_keluarga_id' => $kk->id,
                'nik' => '720308123',
                'nama_lengkap' => 'Budi Santoso',
                'tempat_lahir' => 'Donggala',
                'tanggal_lahir' => '1988-05-12',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2->value,
            ])
            ->assertSessionHasErrors('nik');

        // 2. Buat penduduk baru (dengan kutip tunggal pembuka '7203... serta field suku, kewarganegaraan, KIP/PIP, kelas sekolah, dan BPJS)
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.penduduk.store'), [
                'kartu_keluarga_id' => $kk->id,
                'nik' => "'7203081205880010",
                'nama_lengkap' => 'Budi Santoso',
                'tempat_lahir' => 'Donggala',
                'tanggal_lahir' => '1988-05-12',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'suku' => 'Kaili',
                'kewarganegaraan' => 'WNI',
                'pendidikan' => 'SLTA/Sederajat',
                'peserta_kip_pip' => 'Tidak',
                'kelas_sekolah' => null,
                'pekerjaan' => 'Petani/Pekebun',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2->value,
                'jenis_bpjs' => 'Pemerintah',
            ])
            ->assertSessionHasNoErrors();

        $penduduk = Penduduk::where('nik', '7203081205880010')->firstOrFail();
        $this->assertSame(StatusKependudukan::Aktif, $penduduk->status_kependudukan);
        $this->assertSame(StatusKesejahteraan::DtsenDesil2, $penduduk->status_kesejahteraan);
        $this->assertSame('Kaili', $penduduk->suku);
        $this->assertSame('WNI', $penduduk->kewarganegaraan);
        $this->assertSame('Tidak', $penduduk->peserta_kip_pip);
        $this->assertSame('Pemerintah', $penduduk->jenis_bpjs);

        // 3. Soft delete penduduk
        $this->actingAs($this->operator)
            ->delete(route('panel.kependudukan.penduduk.destroy', $penduduk))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted('penduduk', ['id' => $penduduk->id]);

        // 4. Input ulang NIK yang sama -> otomatis restore & update (withTrashed)
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.penduduk.store'), [
                'kartu_keluarga_id' => $kk->id,
                'nik' => '7203081205880010',
                'nama_lengkap' => 'Budi Santoso Dipulihkan',
                'tempat_lahir' => 'Palu',
                'tanggal_lahir' => '1988-05-12',
                'jenis_kelamin' => 'L',
                'agama' => 'Islam',
                'pekerjaan' => 'Nelayan',
                'status_perkawinan' => 'kawin',
                'hubungan_keluarga' => 'kepala_keluarga',
                'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1->value,
            ])
            ->assertSessionHasNoErrors();

        $restored = Penduduk::withTrashed()->findOrFail($penduduk->id);
        $this->assertFalse($restored->trashed());
        $this->assertSame('Budi Santoso Dipulihkan', $restored->nama_lengkap);
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $restored->status_kesejahteraan);
    }

    public function test_mutasi_pindah_keluar_updates_active_bansos_and_reactivation_requires_catatan_mutasi(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101260020']);

        $kepala = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081010800020',
            'nama_lengkap' => 'Hasanuddin',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        $istri = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203085010820021',
            'nama_lengkap' => 'Siti Aminah',
            'hubungan_keluarga' => 'istri',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        // Buat 1 Bansos Aktif & 1 Bansos Graduasi milik Kepala Keluarga
        $bansosAktif = PenerimaBansos::create([
            'penduduk_id' => $kepala->id,
            'jenis_bantuan' => JenisBansos::Pkh,
            'bentuk_nominal' => 'Rp 750.000 / Tahap',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil1,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        $bansosGraduasi = PenerimaBansos::create([
            'penduduk_id' => $kepala->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => 2025,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil2,
            'status_kepesertaan' => StatusPesertaBansos::Graduasi,
            'created_by' => $this->operator->id,
        ]);

        // 1. Mutasi Aktif -> Pindah Keluar pada Kepala Keluarga
        $this->actingAs($this->operator)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $kepala), [
                'status_kependudukan' => StatusKependudukan::PindahKeluar->value,
                'tanggal_mutasi' => '2026-03-15',
                'catatan_mutasi' => 'Pindah domisili ke Kota Palu',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('kepala_keluarga_reminder');

        $this->assertSame(StatusKependudukan::PindahKeluar, $kepala->fresh()->status_kependudukan);
        $this->assertSame(StatusPesertaBansos::Pindah, $bansosAktif->fresh()->status_kepesertaan);
        // Riwayat graduasi lama tidak boleh ikut berubah
        $this->assertSame(StatusPesertaBansos::Graduasi, $bansosGraduasi->fresh()->status_kepesertaan);

        // Pastikan endpoint detail KK juga mendeteksi peringatan Kepala Keluarga non-aktif sementara Istri masih aktif
        $this->actingAs($this->operator)
            ->getJson(route('panel.kependudukan.kk.show', $kk))
            ->assertOk()
            ->assertJsonPath('data.perlu_update_kepala_keluarga', true);

        // 2. Reaktivasi Pindah Keluar -> Aktif TANPA catatan_mutasi -> DITOLAK
        $this->actingAs($this->operator)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $kepala), [
                'status_kependudukan' => StatusKependudukan::Aktif->value,
                'catatan_mutasi' => '   ',
            ])
            ->assertSessionHasErrors('catatan_mutasi');

        $this->assertSame(StatusKependudukan::PindahKeluar, $kepala->fresh()->status_kependudukan);

        // 3. Reaktivasi Pindah Keluar -> Aktif DENGAN catatan_mutasi -> BERHASIL
        $this->actingAs($this->operator)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $kepala), [
                'status_kependudukan' => StatusKependudukan::Aktif->value,
                'tanggal_mutasi' => '2026-04-01',
                'catatan_mutasi' => 'Kembali menetap di Desa Tamarenja berdasarkan SKPWNI',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusKependudukan::Aktif, $kepala->fresh()->status_kependudukan);
        $this->assertSame('Kembali menetap di Desa Tamarenja berdasarkan SKPWNI', $kepala->fresh()->catatan_mutasi);
    }

    public function test_mutasi_meninggal_dunia_updates_active_bansos_and_blocks_reactivation_if_terbit_surat_kematian_exists(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101260030']);

        $warga = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081111700030',
            'nama_lengkap' => 'Darwis',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
        ]);

        $bansosAktif = PenerimaBansos::create([
            'penduduk_id' => $warga->id,
            'jenis_bantuan' => JenisBansos::Bpnt,
            'bentuk_nominal' => 'Rp 200.000 / Bulan',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil2,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // 1. Mutasi manual Aktif -> Meninggal Dunia
        $this->actingAs($this->operator)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $warga), [
                'status_kependudukan' => StatusKependudukan::MeninggalDunia->value,
                'tanggal_mutasi' => '2026-03-20',
                'catatan_mutasi' => 'Wafat di RSUD Undata',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusKependudukan::MeninggalDunia, $warga->fresh()->status_kependudukan);
        $this->assertSame(StatusPesertaBansos::MeninggalDunia, $bansosAktif->fresh()->status_kepesertaan);

        // 2. Koreksi Meninggal Dunia -> Aktif ketika BELUM ada Surat Kematian terbit -> DIIZINKAN (Bansos lama tetap meninggal_dunia)
        $this->actingAs($this->operator)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $warga), [
                'status_kependudukan' => StatusKependudukan::Aktif->value,
                'catatan_mutasi' => 'Koreksi salah klik baris penduduk',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatusKependudukan::Aktif, $warga->fresh()->status_kependudukan);
        $this->assertSame(StatusPesertaBansos::MeninggalDunia, $bansosAktif->fresh()->status_kepesertaan);

        // 3. Ubah kembali ke Meninggal Dunia & buat Surat Kematian berstatus Terbit
        $warga->update(['status_kependudukan' => StatusKependudukan::MeninggalDunia]);

        SuratKeluar::create([
            'qr_token' => (string) Str::uuid(),
            'penduduk_id' => $warga->id,
            'sumber_pengajuan' => 'loket',
            'jenis_surat' => JenisSurat::Kematian,
            'kode_klasifikasi' => '474.3',
            'tahun_surat' => 2026,
            'nomor_urut' => 1,
            'nomor_surat' => '474.3/001/72.03.08.2011/III/2026',
            'tanggal_surat' => '2026-03-21',
            'keperluan' => 'Pengurusan Akta Kematian',
            'data_khusus' => [
                'hari_meninggal' => 'Jumat',
                'tanggal_meninggal' => '2026-03-20',
                'tempat_meninggal' => 'RSUD Undata',
                'penyebab_meninggal' => 'Sakit',
                'lokasi_makam' => 'TPU Desa Tamarenja',
                'nama_pelapor' => 'Anak Kandung',
                'hubungan_pelapor' => 'Anak',
            ],
            'status' => StatusSuratKeluar::Terbit,
            'processed_by' => $this->operator->id,
        ]);

        // 4. Koreksi Meninggal Dunia -> Aktif ketika ADA Surat Kematian berstatus Terbit -> WAJIB DITOLAK
        $this->actingAs($this->operator)
            ->patch(route('panel.kependudukan.penduduk.mutasi', $warga), [
                'status_kependudukan' => StatusKependudukan::Aktif->value,
                'catatan_mutasi' => 'Percobaan aktifkan kembali tanpa batalkan surat',
            ])
            ->assertSessionHasErrors('status_kependudukan');

        $this->assertSame(StatusKependudukan::MeninggalDunia, $warga->fresh()->status_kependudukan);
    }

    public function test_cari_nik_autocomplete_filters_active_by_default_and_supports_kematian_exception(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101260040']);

        $wargaAktif = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081001900041',
            'nama_lengkap' => 'Rizky Aktif',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $wargaMeninggalBelumSurat = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081001900042',
            'nama_lengkap' => 'Rizky Wafat Manual',
            'status_kependudukan' => StatusKependudukan::MeninggalDunia,
        ]);

        // Default pencarian hanya mengembalikan penduduk Aktif
        $this->actingAs($this->operator)
            ->getJson(route('panel.kependudukan.cari-nik', ['q' => 'Rizky']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nik', $wargaAktif->nik);

        // Dengan parameter jenis_surat=kematian, warga yang baru diubah manual ke Meninggal Dunia (belum ada Surat Kematian terbit) ikut muncul
        $this->actingAs($this->operator)
            ->getJson(route('panel.kependudukan.cari-nik', [
                'q' => 'Rizky',
                'jenis_surat' => 'kematian',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
