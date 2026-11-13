<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisBansos;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\UserRole;
use App\Exports\BansosTemplateExport;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProfilDesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BansosCrudExcelPemerataanAndPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected User $kades;

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

        ProfilDesa::create([
            'id' => 1,
            'nama_desa' => 'Tamarenja',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'alamat_kantor' => 'Jl. Trans Palu - Sabang',
            'kode_desa_surat' => 'PEMDES-TMR',
            'nama_kepala_desa' => 'Moh. Rizal, S.Sos',
            'nama_sekdes' => 'Irfan, S.AP',
            'tahun_anggaran_aktif' => 2026,
            'daftar_dusun' => ['Dusun I', 'Dusun II', 'Dusun III'],
        ]);
    }

    #[Test]
    public function menolak_bansos_baru_bagi_warga_wafat_atau_pindah_dan_menyimpan_snapshot_kategori_ekonomi_dengan_akurat(): void
    {
        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun I']);

        $wargaAktifDesil1 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        $wargaWafat = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'status_kependudukan' => StatusKependudukan::MeninggalDunia,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        $wargaPindah = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'status_kependudukan' => StatusKependudukan::PindahKeluar,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
        ]);

        // 1. Warga wafat ditolak
        $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos', [
                'penduduk_id' => $wargaWafat->id,
                'jenis_bantuan' => JenisBansos::BltDd->value,
                'bentuk_nominal' => 'Rp 300.000 / Bulan',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
            ])
            ->assertSessionHasErrors('penduduk_id');

        // 2. Warga pindah ditolak
        $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos', [
                'penduduk_id' => $wargaPindah->id,
                'jenis_bantuan' => JenisBansos::Pkh->value,
                'bentuk_nominal' => 'Rp 500.000 / Tahap',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
            ])
            ->assertSessionHasErrors('penduduk_id');

        // 3. Warga aktif berhasil & snapshot kategori_ekonomi terekam akurat sesuai penduduk.status_kesejahteraan
        $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos', [
                'penduduk_id' => $wargaAktifDesil1->id,
                'jenis_bantuan' => JenisBansos::BltDd->value,
                'bentuk_nominal' => 'Rp 300.000 / Bulan',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
                'keterangan' => 'Penyaluran Balai Desa',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $bansos = PenerimaBansos::query()->where('penduduk_id', $wargaAktifDesil1->id)->firstOrFail();
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $bansos->kategori_ekonomi);
        $this->assertSame(StatusPesertaBansos::Aktif, $bansos->status_kepesertaan);

        // Bila di kemudian hari status_kesejahteraan penduduk berubah ke non_prioritas, snapshot lama pada bansos tetap dtsen_desil_1
        $wargaAktifDesil1->update(['status_kesejahteraan' => StatusKesejahteraan::NonPrioritas]);
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $bansos->fresh()->kategori_ekonomi);
    }

    #[Test]
    public function ketepatan_filter_pemerataan_kk_dan_mode_individu_mengecualikan_kk_yang_kepala_keluarganya_sudah_menerima_atau_graduasi(): void
    {
        // KK 1: Kepala keluarga sudah menerima BLT-DD aktif di 2026 -> Istri & Anak Desil 1 di KK 1 TIDAK BOLEH muncul di Pemerataan KK
        $kk1 = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101200001', 'dusun' => 'Dusun I']);
        $kepalaKk1 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk1->id,
            'nama_lengkap' => 'Kepala KK Satu',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);
        $istriKk1 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk1->id,
            'nama_lengkap' => 'Istri KK Satu',
            'hubungan_keluarga' => 'istri',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);
        $anakKk1 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk1->id,
            'nama_lengkap' => 'Anak KK Satu',
            'hubungan_keluarga' => 'anak',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        PenerimaBansos::create([
            'penduduk_id' => $kepalaKk1->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'bentuk_nominal' => 'Rp 300.000',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil1,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // KK 2: Memiliki catatan penerima_bansos berstatus GRADUASI di tahun 2026 -> Tetap dikecualikan di tahun 2026
        $kk2 = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101200002', 'dusun' => 'Dusun I']);
        $kepalaKk2 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk2->id,
            'nama_lengkap' => 'Kepala KK Dua Graduasi',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
        ]);

        PenerimaBansos::create([
            'penduduk_id' => $kepalaKk2->id,
            'jenis_bantuan' => JenisBansos::Pkh,
            'bentuk_nominal' => 'Rp 500.000',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil2,
            'status_kepesertaan' => StatusPesertaBansos::Graduasi,
            'created_by' => $this->operator->id,
        ]);

        // KK 3: Desil 1 & Desil 3-4, belum menerima bantuan apa pun di tahun 2026 (hanya pernah di tahun 2025) -> WAJIB MUNCUL di Pemerataan KK
        $kk3 = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101200003', 'dusun' => 'Dusun II']);
        $kepalaKk3 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk3->id,
            'nama_lengkap' => 'Kepala KK Tiga Belum Terima',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil34,
        ]);

        PenerimaBansos::create([
            'penduduk_id' => $kepalaKk3->id,
            'jenis_bantuan' => JenisBansos::Bpnt,
            'bentuk_nominal' => 'Beras 10 Kg',
            'tahun_anggaran' => 2025,
            'tahap_penyaluran' => 'Tahap IV',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil34,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // KK 4: Non-prioritas (Mampu) -> TIDAK BOLEH muncul
        $kk4 = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101200004', 'dusun' => 'Dusun III']);
        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk4->id,
            'nama_lengkap' => 'Kepala KK Empat Mampu',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
        ]);

        $response = $this->actingAs($this->operator)
            ->get('/panel/sosial/bansos?tahun=2026');

        $response->assertOk();
        $props = $response->viewData('page')['props'];

        // Mode Berbasis KK: Hanya KK 3 yang boleh muncul! KK 1 (anak/istri), KK 2 (graduasi 2026), dan KK 4 (non_prioritas) dikecualikan.
        $pemerataanKk = collect($props['pemerataan_kk']);
        $this->assertCount(1, $pemerataanKk);
        $this->assertSame('7203080101200003', $pemerataanKk->first()['nomor_kk']);
        $this->assertSame('Kepala KK Tiga Belum Terima', $pemerataanKk->first()['nama_kepala_keluarga']);

        // Mode Per Individu: Istri KK 1, Anak KK 1, dan Kepala KK 3 belum punya baris penerima_bansos pribadi di 2026,
        // sedangkan Kepala KK 1 dan Kepala KK 2 (graduasi) sudah punya baris di 2026.
        $pemerataanIndividu = collect($props['pemerataan_individu']);
        $individuIds = $pemerataanIndividu->pluck('penduduk_id')->all();

        $this->assertContains($istriKk1->id, $individuIds);
        $this->assertContains($anakKk1->id, $individuIds);
        $this->assertContains($kepalaKk3->id, $individuIds);
        $this->assertNotContains($kepalaKk1->id, $individuIds);
        $this->assertNotContains($kepalaKk2->id, $individuIds);
    }

    #[Test]
    public function idempotensi_import_excel_restorasi_baris_soft_deleted_dan_pencatatan_error_baris(): void
    {
        Storage::fake('private');

        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun I']);
        $warga1 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081111110001',
            'nama_lengkap' => 'Warga Satu Aktif',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
        ]);
        $warga2 = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081111110002',
            'nama_lengkap' => 'Warga Dua Soft Deleted',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);
        $wargaWafat = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081111110003',
            'nama_lengkap' => 'Warga Tiga Wafat',
            'status_kependudukan' => StatusKependudukan::MeninggalDunia,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        // Buat baris soft-deleted untuk warga2 pada (blt_dd, 2026, Tahap I)
        $trashedRecord = PenerimaBansos::create([
            'penduduk_id' => $warga2->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'bentuk_nominal' => 'Rp 300.000',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil1,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);
        $trashedRecord->delete();
        $this->assertSoftDeleted('penerima_bansos', ['id' => $trashedRecord->id]);

        // Buat CSV dengan:
        // Baris 2: warga1 (insert baru, dengan tanda kutip tunggal di depan NIK)
        // Baris 3: warga2 (restore soft-deleted + update bentuk_nominal)
        // Baris 4: wargaWafat (gagal validasi karena wafat)
        $csvContent = implode("\n", [
            'nik,nama_lengkap,jenis_bantuan,bentuk_nominal,tahun_anggaran,tahap_penyaluran,status_kepesertaan,keterangan',
            "'7203081111110001,Warga Satu Aktif,pkh,Rp 600.000 / Tahap,2026,Tahap I,aktif,PKH Tahap I",
            '7203081111110002,Warga Dua Soft Deleted,blt_dd,Rp 900.000 / Triwulan,2026,Tahap I,aktif,Dipulihkan via Import',
            '7203081111110003,Warga Tiga Wafat,bpnt,Beras 10 Kg,2026,Tahap I,aktif,Harus ditolak',
        ]);

        $file = UploadedFile::fake()->createWithContent('import_bansos.csv', $csvContent);

        $response = $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos/import-excel', [
                'file' => $file,
                'tahun_anggaran' => 2026,
            ]);

        $response->assertRedirect();
        $summary = session('import_summary');
        $this->assertSame(2, $summary['berhasil']);
        $this->assertSame(1, $summary['ditambahkan']);
        $this->assertSame(1, $summary['dipulihkan']);
        $this->assertSame(1, $summary['gagal']);

        // Pastikan baris trashedRecord berhasil di-restore dan diperbarui tanpa duplicate key error
        $restored = PenerimaBansos::query()->findOrFail($trashedRecord->id);
        $this->assertNull($restored->deleted_at);
        $this->assertSame('Rp 900.000 / Triwulan', $restored->bentuk_nominal);

        // Pastikan unggah ulang file yang sama bersifat idempoten (update, tidak duplicate error)
        $fileRepeat = UploadedFile::fake()->createWithContent('import_bansos_repeat.csv', $csvContent);
        $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos/import-excel', [
                'file' => $fileRepeat,
                'tahun_anggaran' => 2026,
            ])
            ->assertRedirect();

        $repeatSummary = session('import_summary');
        $this->assertSame(2, $repeatSummary['berhasil']);
        $this->assertSame(2, $repeatSummary['diperbarui']);
    }

    #[Test]
    public function export_excel_cetak_pdf_f4_landscape_dan_proteksi_rbac_kepala_desa(): void
    {
        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun I']);
        $warga = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nama_lengkap' => 'Siti Rahmawati',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        $bansos = PenerimaBansos::create([
            'penduduk_id' => $warga->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil1,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // 1. Operator dapat mengunduh Template Excel & Export Excel
        $this->actingAs($this->operator)
            ->get('/panel/sosial/bansos/template-excel')
            ->assertOk();

        $this->actingAs($this->operator)
            ->get('/panel/sosial/bansos/export-excel?tahun=2026&jenis_bantuan=blt_dd')
            ->assertOk();

        // 2. Operator & Kepala Desa dapat mengunduh PDF Daftar Hadir & Tanda Terima F4 Landscape
        $pdfOp = $this->actingAs($this->operator)
            ->get('/panel/sosial/bansos/cetak-pdf?tahun=2026');
        $pdfOp->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfOp->headers->get('Content-Type'));

        $pdfKades = $this->actingAs($this->kades)
            ->get('/panel/sosial/bansos/cetak-pdf?tahun=2026');
        $pdfKades->assertOk();

        // 3. Kepala Desa ditolak (403) pada seluruh rute tulis / import / export Excel
        $this->actingAs($this->kades)
            ->post('/panel/sosial/bansos', [
                'penduduk_id' => $warga->id,
                'jenis_bantuan' => JenisBansos::Pkh->value,
                'bentuk_nominal' => 'Rp 500.000',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->put("/panel/sosial/bansos/{$bansos->id}", [
                'penduduk_id' => $warga->id,
                'jenis_bantuan' => JenisBansos::BltDd->value,
                'bentuk_nominal' => 'Rp 400.000',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
            ])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->delete("/panel/sosial/bansos/{$bansos->id}")
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->get('/panel/sosial/bansos/export-excel')
            ->assertForbidden();
    }

    #[Test]
    public function menolak_bantuan_ganda_sejenis_dalam_satu_kk_pada_tahun_anggaran_yang_sama(): void
    {
        $kk = KartuKeluarga::factory()->create(['nomor_kk' => '7203080101990001', 'dusun' => 'Dusun I']);

        $suami = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nama_lengkap' => 'Budi Suami',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        $istri = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nama_lengkap' => 'Siti Istri',
            'hubungan_keluarga' => 'istri',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        // 1. Daftarkan suami untuk BLT-DD 2026 -> Berhasil
        $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos', [
                'penduduk_id' => $suami->id,
                'jenis_bantuan' => JenisBansos::BltDd->value,
                'bentuk_nominal' => 'Rp 300.000 / Bulan',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // 2. Coba daftarkan istri untuk BLT-DD 2026 (program sama, 1 KK) -> DITOLAK
        $responseDuplicate = $this->actingAs($this->operator)
            ->post('/panel/sosial/bansos', [
                'penduduk_id' => $istri->id,
                'jenis_bantuan' => JenisBansos::BltDd->value,
                'bentuk_nominal' => 'Rp 300.000 / Bulan',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap II',
            ]);

        $responseDuplicate->assertSessionHasErrors('penduduk_id');
        $errorMessage = session('errors')->first('penduduk_id');
        $this->assertStringContainsString('Bantuan ganda dalam 1 KK dilarang', $errorMessage);
        $this->assertStringContainsString('Budi Suami', $errorMessage);

        // 3. Edit data suami sendiri untuk ganti nominal -> Harus berhasil (bukan duplikasi diri sendiri)
        $bansosSuami = PenerimaBansos::query()->where('penduduk_id', $suami->id)->firstOrFail();
        $this->actingAs($this->operator)
            ->put("/panel/sosial/bansos/{$bansosSuami->id}", [
                'penduduk_id' => $suami->id,
                'jenis_bantuan' => JenisBansos::BltDd->value,
                'bentuk_nominal' => 'Rp 600.000 / 2 Bulan',
                'tahun_anggaran' => 2026,
                'tahap_penyaluran' => 'Tahap I',
                'status_kepesertaan' => StatusPesertaBansos::Aktif->value,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Rp 600.000 / 2 Bulan', $bansosSuami->fresh()->bentuk_nominal);
    }
}
