<?php

namespace Tests\Feature\Panel;

use App\Exports\FullDatabaseBackupExport;
use App\Models\DokumenPpid;
use App\Models\PerangkatDesa;
use App\Models\ProfilDesa;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PengaturanProfilSotkPpidAndBackupTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $kades;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EssentialDesaSeeder::class);

        $this->operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();
        $this->kades = User::query()->where('email', 'kades@desa.id')->firstOrFail();
    }

    public function test_operator_can_update_profil_desa_and_convert_jpg_or_webp_logo_to_png_with_old_file_cleanup(): void
    {
        Storage::fake('public');

        // Unggah pertama menggunakan .jpg
        $jpgLogo = UploadedFile::fake()->image('logo-awal.jpg', 240, 240);
        $fotoKadesAwal = UploadedFile::fake()->image('kades-awal.jpg', 400, 500);

        $response = $this->actingAs($this->operator)->put('/panel/pengaturan/profil-desa', [
            'nama_desa' => 'Tamarenja Maju',
            'kode_desa_surat' => 'DS-TMR',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'kode_wilayah' => '72.03.19.2003',
            'daftar_dusun' => ['Dusun I', 'Dusun II', 'Dusun V Pesisir'],
            'alamat_kantor' => 'Jl. Trans Sulawesi No. 10, Desa Tamarenja',
            'jam_pelayanan' => 'Senin - Jumat, 08.00 - 15.30 WITA',
            'email_desa' => 'kontak@tamarenja.desa.id',
            'no_wa_pelayanan' => '081245009988',
            'nama_kepala_desa' => 'H. Moh. Ridwan, S.Sos.',
            'nip_kepala_desa' => '197501012005011002',
            'sambutan_kepala_desa' => 'Sambutan resmi Kepala Desa Tamarenja.',
            'nama_sekdes' => 'Andi Saputra, S.AP.',
            'nip_sekdes' => '198604122010011008',
            'visi' => 'Visi Desa Tamarenja Maju & Mandiri',
            'misi' => ['Misi Pertama', 'Misi Kedua', 'Misi Ketiga'],
            'sejarah_singkat' => 'Sejarah singkat Desa Tamarenja.',
            'potensi_unggulan' => 'Perkebunan kelapa dan perikanan pesisir.',
            'tahun_anggaran_aktif' => 2027,
            'logo_desa' => $jpgLogo,
            'foto_kepala_desa' => $fotoKadesAwal,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $profil = ProfilDesa::singleton();
        $this->assertNotNull($profil);
        $this->assertSame('Tamarenja Maju', $profil->nama_desa);
        $this->assertSame('6281245009988', $profil->no_wa_pelayanan);
        $this->assertSame(['Dusun I', 'Dusun II', 'Dusun V Pesisir'], $profil->daftar_dusun);
        $this->assertSame(['Misi Pertama', 'Misi Kedua', 'Misi Ketiga'], $profil->misi);
        $this->assertSame(2027, $profil->tahun_anggaran_aktif);

        $firstLogoPath = $profil->logo_desa_path;
        $firstFotoKadesPath = $profil->foto_kepala_desa_path;

        $this->assertNotNull($firstLogoPath);
        $this->assertStringEndsWith('.png', $firstLogoPath);
        Storage::disk('public')->assertExists($firstLogoPath);

        $this->assertNotNull($firstFotoKadesPath);
        Storage::disk('public')->assertExists($firstFotoKadesPath);

        // Unggah kedua menggunakan .webp -> wajib dikonversi ke .png dan menghapus berkas lama
        $webpLogo = UploadedFile::fake()->image('logo-baru.webp', 300, 300);
        $fotoKadesBaru = UploadedFile::fake()->image('kades-baru.png', 500, 600);

        $this->actingAs($this->operator)->put('/panel/pengaturan/profil-desa', [
            'nama_desa' => 'Tamarenja',
            'kode_desa_surat' => 'DS-TMR',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'daftar_dusun' => ['Dusun I', 'Dusun II'],
            'alamat_kantor' => 'Jl. Trans Sulawesi No. 10, Desa Tamarenja',
            'nama_kepala_desa' => 'H. Moh. Ridwan, S.Sos.',
            'nama_sekdes' => 'Andi Saputra, S.AP.',
            'tahun_anggaran_aktif' => 2026,
            'logo_desa' => $webpLogo,
            'foto_kepala_desa' => $fotoKadesBaru,
        ])->assertRedirect();

        $profil->refresh();
        $this->assertNotSame($firstLogoPath, $profil->logo_desa_path);
        $this->assertStringEndsWith('.png', (string) $profil->logo_desa_path);

        Storage::disk('public')->assertMissing($firstLogoPath);
        Storage::disk('public')->assertExists((string) $profil->logo_desa_path);

        Storage::disk('public')->assertMissing($firstFotoKadesPath);
        Storage::disk('public')->assertExists((string) $profil->foto_kepala_desa_path);
    }

    public function test_operator_can_crud_perangkat_desa_sotk_with_photo_cleanup(): void
    {
        Storage::fake('public');

        $fotoAwal = UploadedFile::fake()->image('kaur-keuangan.jpg', 400, 400);

        $this->actingAs($this->operator)->post('/panel/pengaturan/sotk', [
            'nama_lengkap' => 'Siti Aminah, S.E.',
            'jabatan' => 'Kaur Keuangan',
            'kelompok_sotk' => 'sekretariat',
            'nip_niap' => '199203102020122001',
            'urutan' => 2,
            'is_aktif' => true,
            'foto' => $fotoAwal,
        ])->assertRedirect();

        $perangkat = PerangkatDesa::query()->where('nama_lengkap', 'Siti Aminah, S.E.')->firstOrFail();
        $firstFoto = $perangkat->foto_path;
        $this->assertNotNull($firstFoto);
        Storage::disk('public')->assertExists($firstFoto);

        // Update dengan foto baru -> foto lama wajib dihapus
        $fotoBaru = UploadedFile::fake()->image('kaur-keuangan-baru.png', 500, 500);
        $this->actingAs($this->operator)->put("/panel/pengaturan/sotk/{$perangkat->id}", [
            'nama_lengkap' => 'Siti Aminah, S.E., M.Si.',
            'jabatan' => 'Kaur Keuangan',
            'kelompok_sotk' => 'sekretariat',
            'nip_niap' => '199203102020122001',
            'urutan' => 3,
            'is_aktif' => true,
            'foto' => $fotoBaru,
        ])->assertRedirect();

        $perangkat->refresh();
        $this->assertSame('Siti Aminah, S.E., M.Si.', $perangkat->nama_lengkap);
        $this->assertSame(3, $perangkat->urutan);
        Storage::disk('public')->assertMissing($firstFoto);
        Storage::disk('public')->assertExists((string) $perangkat->foto_path);

        $secondFoto = (string) $perangkat->foto_path;

        // Destroy -> foto dan baris database terhapus
        $this->actingAs($this->operator)
            ->delete("/panel/pengaturan/sotk/{$perangkat->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('perangkat_desa', ['id' => $perangkat->id]);
        Storage::disk('public')->assertMissing($secondFoto);
    }

    public function test_operator_can_crud_dokumen_ppid_and_validate_pdf_max_10mb_and_auto_calculate_size_kb(): void
    {
        Storage::fake('public');

        // 1. Validasi tolak jika berkas > 10 MB (10240 KB) atau bukan PDF
        $oversizedPdf = UploadedFile::fake()->create('perdes-besar.pdf', 10500, 'application/pdf');

        $this->actingAs($this->operator)->post('/panel/pengaturan/ppid', [
            'judul_dokumen' => 'Perdes Terlalu Besar',
            'kategori' => 'perdes_apbdes',
            'tahun_dokumen' => 2026,
            'is_publik' => true,
            'file_pdf' => $oversizedPdf,
        ])->assertSessionHasErrors('file_pdf');

        $nonPdf = UploadedFile::fake()->image('bukan-pdf.jpg', 200, 200);
        $this->actingAs($this->operator)->post('/panel/pengaturan/ppid', [
            'judul_dokumen' => 'Berkas Gambar',
            'kategori' => 'perdes_apbdes',
            'tahun_dokumen' => 2026,
            'is_publik' => true,
            'file_pdf' => $nonPdf,
        ])->assertSessionHasErrors('file_pdf');

        // 2. Unggah PDF valid (350 KB) -> ukuran_file_kb dihitung otomatis
        $validPdf = UploadedFile::fake()->create('perdes-apbdes-2026.pdf', 350, 'application/pdf');

        $this->actingAs($this->operator)->post('/panel/pengaturan/ppid', [
            'judul_dokumen' => 'Perdes APBDes Tahun Anggaran 2026',
            'kategori' => 'perdes_apbdes',
            'tahun_dokumen' => 2026,
            'is_publik' => true,
            'file_pdf' => $validPdf,
        ])->assertRedirect();

        $dokumen = DokumenPpid::query()
            ->where('judul_dokumen', 'Perdes APBDes Tahun Anggaran 2026')
            ->firstOrFail();

        $this->assertSame(350, $dokumen->ukuran_file_kb);
        $this->assertTrue($dokumen->is_publik);
        $this->assertSame($this->operator->id, $dokumen->uploaded_by);
        Storage::disk('public')->assertExists($dokumen->file_pdf_path);

        // 3. Toggle status publikasi
        $this->actingAs($this->operator)
            ->patch("/panel/pengaturan/ppid/{$dokumen->id}/publikasi")
            ->assertRedirect();

        $dokumen->refresh();
        $this->assertFalse($dokumen->is_publik);

        // 4. Update ganti PDF baru (512 KB) -> PDF lama dihapus
        $oldPdfPath = $dokumen->file_pdf_path;
        $replacementPdf = UploadedFile::fake()->create('perdes-revisi.pdf', 512, 'application/pdf');

        $this->actingAs($this->operator)->put("/panel/pengaturan/ppid/{$dokumen->id}", [
            'judul_dokumen' => 'Perdes Perubahan APBDes 2026',
            'kategori' => 'perdes_apbdes',
            'tahun_dokumen' => 2026,
            'is_publik' => true,
            'file_pdf' => $replacementPdf,
        ])->assertRedirect();

        $dokumen->refresh();
        $this->assertSame(512, $dokumen->ukuran_file_kb);
        $this->assertTrue($dokumen->is_publik);
        Storage::disk('public')->assertMissing($oldPdfPath);
        Storage::disk('public')->assertExists($dokumen->file_pdf_path);
    }

    public function test_pdo_sql_dumper_fallback_creates_valid_sql_backup_in_private_storage(): void
    {
        Storage::fake('private');

        /** @var DatabaseBackupService $backupService */
        $backupService = app(DatabaseBackupService::class);
        $result = $backupService->createSqlBackup(forcePdoFallback: true);

        $this->assertSame('pdo_fallback', $result['method']);
        Storage::disk('private')->assertExists($result['path']);

        $sqlContent = Storage::disk('private')->get($result['path']);
        $this->assertStringContainsString('Pure PHP PDO SQL Dumper', $sqlContent);
        $this->assertStringContainsString('CREATE TABLE', $sqlContent);
        $this->assertStringContainsString('profil_desa', $sqlContent);
        $this->assertStringContainsString('INSERT INTO `profil_desa`', $sqlContent);

        // Uji unduh melalui controller terautentikasi
        $response = $this->actingAs($this->operator)->get('/panel/pengaturan/backup/sql?force_pdo=1');
        $response->assertOk();
        $response->assertHeader('X-Backup-Method', 'pdo_fallback');
    }

    public function test_full_excel_backup_generates_exactly_8_sheets_in_private_storage(): void
    {
        Storage::fake('private');

        $response = $this->actingAs($this->operator)->get('/panel/pengaturan/backup/excel');
        $response->assertOk();

        $files = Storage::disk('private')->files('backups');
        $this->assertNotEmpty($files);

        $excelRelativePath = collect($files)->first(fn (string $f) => str_ends_with($f, '.xlsx'));
        $this->assertNotNull($excelRelativePath);

        $fullPath = Storage::disk('private')->path($excelRelativePath);
        $spreadsheet = IOFactory::load($fullPath);

        $this->assertCount(8, $spreadsheet->getAllSheets());
        $this->assertSame(
            FullDatabaseBackupExport::SHEET_TITLES,
            $spreadsheet->getSheetNames()
        );
    }

    public function test_kepala_desa_receives_403_forbidden_on_all_pengaturan_and_backup_routes(): void
    {
        $routes = [
            ['GET', '/panel/pengaturan/profil-desa'],
            ['PUT', '/panel/pengaturan/profil-desa'],
            ['GET', '/panel/pengaturan/sotk'],
            ['POST', '/panel/pengaturan/sotk'],
            ['GET', '/panel/pengaturan/ppid'],
            ['POST', '/panel/pengaturan/ppid'],
            ['GET', '/panel/pengaturan/pengguna'],
            ['GET', '/panel/pengaturan/backup'],
            ['GET', '/panel/pengaturan/backup/sql'],
            ['GET', '/panel/pengaturan/backup/excel'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->actingAs($this->kades)
                ->json($method, $uri)
                ->assertForbidden();
        }
    }
}
