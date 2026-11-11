<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusSuratKeluar;
use App\Enums\UserRole;
use App\Exports\PendudukExport;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KependudukanExcelImportExportTest extends TestCase
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

    public function test_operator_can_download_template_and_export_excel_while_kepala_desa_is_forbidden(): void
    {
        $kk1 = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101260001',
            'dusun' => 'Dusun I',
        ]);
        $kk2 = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101260002',
            'dusun' => 'Dusun II',
        ]);

        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk1->id,
            'nik' => '7203081205850001',
            'nama_lengkap' => 'Warga Dusun Satu',
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk2->id,
            'nik' => '7203081205850002',
            'nama_lengkap' => 'Warga Dusun Dua',
            'status_kependudukan' => StatusKependudukan::PindahKeluar,
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
        ]);

        // Operator unduh template SIAK
        $this->actingAs($this->operator)
            ->get(route('panel.kependudukan.template-excel'))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Template_Import_Penduduk_SIAK.xlsx');

        // Operator export Excel dengan filter Dusun & Status
        $this->actingAs($this->operator)
            ->get(route('panel.kependudukan.export-excel', [
                'dusun' => 'Dusun I',
                'status_kependudukan' => 'aktif',
                'status_kesejahteraan' => 'dtsen_desil_1',
            ]))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Data_Penduduk_Tamarenja.xlsx');

        // Kepala Desa ditolak 403 untuk template, export, dan import Excel
        $this->actingAs($this->kades)
            ->get(route('panel.kependudukan.template-excel'))
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->get(route('panel.kependudukan.export-excel'))
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->post(route('panel.kependudukan.import-excel'), [])
            ->assertForbidden();
    }

    public function test_import_excel_handles_new_rows_idempotent_updates_soft_deleted_restoration_single_quotes_excel_serial_dates_and_row_error_summary(): void
    {
        Storage::fake('private');

        // 1. Siapkan 1 warga aktif untuk diuji pembaruan (update) idempoten
        $existingKk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101260101',
            'dusun' => 'Dusun I',
            'rt' => '001',
            'rw' => '001',
        ]);
        $existingPenduduk = Penduduk::factory()->create([
            'kartu_keluarga_id' => $existingKk->id,
            'nik' => '7203081001800101',
            'nama_lengkap' => 'Nama Sebelum Update',
            'pekerjaan' => 'Petani',
            'status_kesejahteraan' => StatusKesejahteraan::NonPrioritas,
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        // 2. Siapkan 1 KK & 1 warga yang sudah di-soft-delete untuk diuji pemulihan otomatis (restore withTrashed)
        $trashedKk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101260102',
            'dusun' => 'Dusun Lama',
        ]);
        $trashedPenduduk = Penduduk::factory()->create([
            'kartu_keluarga_id' => $trashedKk->id,
            'nik' => '7203082002820102',
            'nama_lengkap' => 'Warga Terhapus Lama',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);
        $trashedPenduduk->delete();
        $trashedKk->delete();

        $this->assertSoftDeleted('kartu_keluarga', ['id' => $trashedKk->id]);
        $this->assertSoftDeleted('penduduk', ['id' => $trashedPenduduk->id]);

        // Hitung nilai serial tanggal Excel untuk 1990-06-15
        $excelSerialDate = ExcelDate::PHPToExcel(strtotime('1990-06-15 UTC'));

        // 3. Buat berkas .xlsx asli di memori menggunakan PhpSpreadsheet
        $rows = [
            [
                'nomor_kk',
                'dusun',
                'rt',
                'rw',
                'alamat',
                'nik',
                'nama_lengkap',
                'tempat_lahir',
                'tanggal_lahir',
                'jenis_kelamin',
                'agama',
                'pendidikan',
                'pekerjaan',
                'status_perkawinan',
                'hubungan_keluarga',
                'status_kesejahteraan',
                'status_kependudukan',
            ],
            // Baris 2: Baris BARU + kutip tunggal pembuka ('7203...) + spasi tepi + tanggal serial Excel
            [
                "  '7203080101260103  ",
                '  Dusun III  ',
                '1',
                '2',
                'Jl. Poros Desa No. 5',
                " '7203081506900103 ",
                '  Andi Pratama Baru  ',
                'Donggala',
                $excelSerialDate,
                'Laki-laki',
                'Islam',
                'S1',
                'Wiraswasta',
                'Kawin',
                'Kepala Keluarga',
                'Desil 1',
                'Aktif',
            ],
            // Baris 3: Baris UPDATE pada warga aktif yang sudah ada
            [
                '7203080101260101',
                'Dusun I',
                '001',
                '001',
                'Alamat Diperbarui',
                '7203081001800101',
                'Nama Sesudah Update Excel',
                'Palu',
                '1980-01-10',
                'L',
                'Islam',
                'SLTA',
                'Nelayan',
                'kawin',
                'kepala_keluarga',
                'dtsen_desil_2',
                'aktif',
            ],
            // Baris 4: Baris RESTORE pada KK & NIK yang pernah di-soft-delete
            [
                "'7203080101260102",
                'Dusun II Pulih',
                '003',
                '001',
                'Alamat Pulih',
                "'7203082002820102",
                'Warga Berhasil Dipulihkan',
                'Donggala',
                '1982-02-20',
                'P',
                'Islam',
                'SMP',
                'Pedagang',
                'cerai_hidup',
                'kepala_keluarga',
                'dtsen_desil_3_4',
                'aktif',
            ],
            // Baris 5: Baris RUSAK (NIK hanya 10 digit & nama_lengkap kosong) -> wajib masuk daftar gagal tanpa merusak baris 2-4
            [
                '7203080101260103',
                'Dusun III',
                '001',
                '002',
                'Jl. Rusak',
                '7203089999',
                '',
                'Palu',
                '1995-01-01',
                'L',
                'Islam',
                'SMA',
                'Pelajar',
                'belum_kawin',
                'anak',
                'non_prioritas',
                'aktif',
            ],
        ];

        $uploadedFile = $this->createUploadedXlsx($rows, 'import_penduduk_siak.xlsx');

        // 4. Jalankan Import Pertama
        $response = $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.import-excel'), [
                'file' => $uploadedFile,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('notification.type', 'warning');

        $summary = session('import_summary');
        $this->assertNotNull($summary);
        $this->assertSame(3, $summary['berhasil']);
        $this->assertSame(1, $summary['ditambahkan']);
        $this->assertSame(1, $summary['diperbarui']);
        $this->assertSame(1, $summary['dipulihkan']);
        $this->assertSame(1, $summary['gagal']);
        $this->assertCount(1, $summary['failed_rows']);
        $this->assertSame(5, $summary['failed_rows'][0]['baris']);
        $this->assertStringContainsString('NIK harus tepat 16 digit angka.', $summary['failed_rows'][0]['pesan']);
        $this->assertStringContainsString('Nama lengkap wajib diisi.', $summary['failed_rows'][0]['pesan']);

        // Verifikasi Baris 2 (Baru + kutip tunggal dibersihkan + tanggal serial Excel -> 1990-06-15 + RT/RW 001/002)
        $newResident = Penduduk::where('nik', '7203081506900103')->firstOrFail();
        $this->assertSame('Andi Pratama Baru', $newResident->nama_lengkap);
        $this->assertSame('1990-06-15', $newResident->tanggal_lahir->format('Y-m-d'));
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $newResident->status_kesejahteraan);
        $this->assertSame('7203080101260103', $newResident->kartuKeluarga->nomor_kk);
        $this->assertSame('Dusun III', $newResident->kartuKeluarga->dusun);
        $this->assertSame('001', $newResident->kartuKeluarga->rt);
        $this->assertSame('002', $newResident->kartuKeluarga->rw);

        // Verifikasi Baris 3 (Update pada warga aktif)
        $this->assertSame('Nama Sesudah Update Excel', $existingPenduduk->fresh()->nama_lengkap);
        $this->assertSame('Nelayan', $existingPenduduk->fresh()->pekerjaan);
        $this->assertSame(StatusKesejahteraan::DtsenDesil2, $existingPenduduk->fresh()->status_kesejahteraan);

        // Verifikasi Baris 4 (Restore withTrashed pada KK & Penduduk)
        $this->assertFalse($trashedKk->fresh()->trashed());
        $this->assertSame('Dusun II Pulih', $trashedKk->fresh()->dusun);
        $this->assertFalse($trashedPenduduk->fresh()->trashed());
        $this->assertSame('Warga Berhasil Dipulihkan', $trashedPenduduk->fresh()->nama_lengkap);

        // Verifikasi file sementara di private disk telah dihapus bersih pasca-import
        $this->assertEmpty(Storage::disk('private')->files('temp-imports'));

        // 5. Unggah ulang baris valid yang sama untuk membuktikan Idempotensi (tidak ada error UNIQUE constraint)
        $validRowsOnly = array_slice($rows, 0, 4);
        $reuploadFile = $this->createUploadedXlsx($validRowsOnly, 'reupload_idempotent.xlsx');

        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.import-excel'), [
                'file' => $reuploadFile,
            ])
            ->assertRedirect()
            ->assertSessionHas('notification.type', 'success');

        $secondSummary = session('import_summary');
        $this->assertSame(3, $secondSummary['berhasil']);
        $this->assertSame(0, $secondSummary['ditambahkan']);
        $this->assertSame(3, $secondSummary['diperbarui']);
        $this->assertSame(0, $secondSummary['gagal']);
    }

    public function test_export_excel_formats_clean_indonesian_headings_labels_orders_by_family_hierarchy_and_supports_reimport(): void
    {
        Storage::fake('private');

        $kk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101260999',
            'dusun' => 'Dusun I',
            'rt' => '002',
            'rw' => '001',
            'alamat' => 'Jl. Trans Sulawesi No. 99',
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        // Buat Anak terlebih dahulu dengan nama berawalan huruf 'A'
        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081001100993',
            'nama_lengkap' => 'Ahmad Putra',
            'hubungan_keluarga' => 'anak',
            'status_perkawinan' => 'belum_kawin',
            'tanggal_lahir' => '2010-01-10',
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        // Buat Kepala Keluarga dengan nama berawalan huruf 'Z'
        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203081001800991',
            'nama_lengkap' => 'Zulkifli Kepala',
            'hubungan_keluarga' => 'kepala_keluarga',
            'status_perkawinan' => 'kawin',
            'tanggal_lahir' => '1980-01-10',
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $export = new PendudukExport;
        $headings = $export->headings();

        $this->assertContains('Nomor KK', $headings);
        $this->assertContains('Nama Lengkap', $headings);
        $this->assertContains('Hubungan Keluarga', $headings);
        $this->assertContains('Kategori DTSEN', $headings);
        $this->assertContains('Status Warga', $headings);

        $collection = $export->collection()->values();
        $this->assertCount(2, $collection);

        // Pastikan Kepala Keluarga berada di urutan pertama meskipun namanya huruf 'Z'
        $firstMapped = $export->map($collection[0]);
        $secondMapped = $export->map($collection[1]);

        $this->assertSame('Zulkifli Kepala', $firstMapped[6]);
        $this->assertSame('Kawin', $firstMapped[13]);
        $this->assertSame('Kepala Keluarga', $firstMapped[14]);
        $this->assertSame('Desil 1', $firstMapped[15]);
        $this->assertSame('Aktif', $firstMapped[16]);

        $this->assertSame('Ahmad Putra', $secondMapped[6]);
        $this->assertSame('Belum Kawin', $secondMapped[13]);
        $this->assertSame('Anak', $secondMapped[14]);
        $this->assertSame('Desil 1', $secondMapped[15]);
        $this->assertSame('Aktif', $secondMapped[16]);

        // Uji Round-Trip: Hasil Export di-import kembali ke sistem
        $roundTripFile = $this->createUploadedXlsx(
            [$headings, $firstMapped, $secondMapped],
            'roundtrip_export.xlsx'
        );

        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.import-excel'), [
                'file' => $roundTripFile,
            ])
            ->assertRedirect()
            ->assertSessionHas('notification.type', 'success');

        $summary = session('import_summary');
        $this->assertSame(2, $summary['berhasil']);
        $this->assertSame(0, $summary['gagal']);
    }

    public function test_import_excel_supports_preview_mode_preserves_kk_welfare_when_child_row_is_blank_and_blocks_reactivating_deceased_with_terbit_surat_kematian(): void
    {
        Storage::fake('private');

        $kkDeceased = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203080101260777',
            'dusun' => 'Dusun I',
        ]);
        $deceasedResident = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kkDeceased->id,
            'nik' => '7203081001600777',
            'nama_lengkap' => 'Almarhum Warga',
            'status_kependudukan' => StatusKependudukan::MeninggalDunia,
        ]);

        SuratKeluar::factory()->create([
            'penduduk_id' => $deceasedResident->id,
            'jenis_surat' => JenisSurat::Kematian,
            'status' => StatusSuratKeluar::Terbit,
        ]);

        $rows = [
            [
                'Nomor KK',
                'Dusun',
                'RT',
                'RW',
                'Alamat',
                'NIK',
                'Nama Lengkap',
                'Tempat Lahir',
                'Tanggal Lahir',
                'Jenis Kelamin',
                'Agama',
                'Pendidikan',
                'Pekerjaan',
                'Status Perkawinan',
                'Hubungan Keluarga',
                'Kategori DTSEN',
                'Status Warga',
            ],
            // Baris 2: Kepala Keluarga dengan Kategori DTSEN = Desil 1
            [
                '7203080101260888',
                'Dusun II',
                '001',
                '001',
                'Jl. Desa No. 8',
                '7203081001800881',
                'Kepala Keluarga Desil Satu',
                'Donggala',
                '1980-01-10',
                'L',
                'Islam',
                'SLTA',
                'Petani',
                'Kawin',
                'Kepala Keluarga',
                'Desil 1',
                'Aktif',
            ],
            // Baris 3: Anak di KK yang sama dengan kolom Kategori DTSEN dikosongkan -> tidak boleh menimpa Desil 1 KK menjadi Non-Prioritas
            [
                '7203080101260888',
                'Dusun II',
                '001',
                '001',
                'Jl. Desa No. 8',
                '7203081001100882',
                'Anak Mewarisi Desil Satu',
                'Donggala',
                '2010-01-10',
                'L',
                'Islam',
                'SD',
                'Pelajar',
                'Belum Kawin',
                'Anak',
                '',
                'Aktif',
            ],
            // Baris 4: Warga Meninggal Dunia yang sudah terbit Surat Kematian dicoba diubah menjadi Aktif -> wajib ditolak
            [
                '7203080101260777',
                'Dusun I',
                '001',
                '001',
                'Jl. Desa No. 7',
                '7203081001600777',
                'Almarhum Warga',
                'Donggala',
                '1960-01-10',
                'L',
                'Islam',
                'SLTA',
                'Petani',
                'Kawin',
                'Kepala Keluarga',
                'Non-Prioritas',
                'Aktif',
            ],
        ];

        // 1. Uji Tahap 1: Mode Preview (Dry-Run) -> Belum masuk ke database
        $previewFile = $this->createUploadedXlsx($rows, 'preview_test.xlsx');
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.import-excel'), [
                'file' => $previewFile,
                'mode' => 'preview',
            ])
            ->assertRedirect();

        $previewSummary = session('import_summary');
        $this->assertTrue($previewSummary['is_preview']);
        $this->assertSame(2, $previewSummary['berhasil']);
        $this->assertSame(2, $previewSummary['ditambahkan']);
        $this->assertSame(1, $previewSummary['gagal']);
        $this->assertCount(3, $previewSummary['preview_rows']);

        // Pastikan di mode preview belum ada KK 7203080101260888 di DB
        $this->assertDatabaseMissing('kartu_keluarga', [
            'nomor_kk' => '7203080101260888',
        ]);

        // 2. Uji Tahap 2: Mode Commit -> Simpan baris valid ke database
        $commitFile = $this->createUploadedXlsx($rows, 'commit_test.xlsx');
        $this->actingAs($this->operator)
            ->post(route('panel.kependudukan.import-excel'), [
                'file' => $commitFile,
                'mode' => 'commit',
            ])
            ->assertRedirect();

        $commitSummary = session('import_summary');
        $this->assertFalse($commitSummary['is_preview']);
        $this->assertSame(2, $commitSummary['berhasil']);
        $this->assertSame(1, $commitSummary['gagal']);
        $this->assertStringContainsString(
            'Surat Kematian Terbit',
            $commitSummary['failed_rows'][0]['pesan']
        );

        // Pastikan KK dan kedua anggota tetap berstatus Desil 1 (tidak tertimpa kosong oleh baris Anak)
        $savedKk = KartuKeluarga::where('nomor_kk', '7203080101260888')->firstOrFail();
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $savedKk->status_kesejahteraan);

        $savedChild = Penduduk::where('nik', '7203081001100882')->firstOrFail();
        $this->assertSame(StatusKesejahteraan::DtsenDesil1, $savedChild->status_kesejahteraan);

        // Pastikan warga almarhum tetap berstatus Meninggal Dunia
        $this->assertSame(
            StatusKependudukan::MeninggalDunia,
            $deceasedResident->fresh()->status_kependudukan
        );
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function createUploadedXlsx(array $rows, string $filename): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($rows, null, 'A1', true);

        $tempPath = tempnam(sys_get_temp_dir(), 'test_siak_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
