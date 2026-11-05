<?php

namespace Tests\Feature;

use App\Enums\JenisBansos;
use App\Enums\JenisDokumenApbdes;
use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusProyek;
use App\Enums\StatusSuratKeluar;
use App\Enums\SumberDanaProyek;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
use App\Models\DokumenPpid;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Database\Seeders\DummyDesaSeeder;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class EndToEndGovernanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $kades;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('private');

        $this->seed(EssentialDesaSeeder::class);

        $this->operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();
        $this->kades = User::query()->where('email', 'kades@desa.id')->firstOrFail();
    }

    public function test_complete_end_to_end_village_governance_lifecycle_across_all_modules(): void
    {
        // =========================================================================
        // 1. OPERATOR MENGIMPOR EXCEL KEPENDUDUKAN SIAK -> STATISTIK PUBLIK AGREGAT
        //    OTOMATIS TERBARUI TANPA MEMBOCORKAN DATA INDIVIDU
        // =========================================================================
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'nomor_kk', 'dusun', 'rt', 'rw', 'alamat',
            'nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin',
            'agama', 'pendidikan', 'pekerjaan', 'status_perkawinan', 'hubungan_keluarga',
            'status_kesejahteraan', 'status_kependudukan',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        $rows = [
            // KK 1 (Dusun I - DTSEN Desil 1): Kepala Keluarga + Istri
            [
                "'7203181000000001", 'Dusun I', '001', '001', 'Jl. Trans Sulawesi No. 1',
                "'7203182000000001", 'Anwar Sadat', 'Tamarenja', '1980-03-15', 'L',
                'Islam', 'SLTA', 'Nelayan/Perikanan', 'kawin', 'kepala_keluarga',
                StatusKesejahteraan::DtsenDesil1->value, StatusKependudukan::Aktif->value,
            ],
            [
                "'7203181000000001", 'Dusun I', '001', '001', 'Jl. Trans Sulawesi No. 1',
                "'7203182000000002", 'Rahmawati', 'Donggala', '1985-07-20', 'P',
                'Islam', 'SLTA', 'Mengurus Rumah Tangga', 'kawin', 'istri',
                StatusKesejahteraan::DtsenDesil1->value, StatusKependudukan::Aktif->value,
            ],
            // KK 2 (Dusun II - DTSEN Desil 2): Kepala Keluarga (belum pernah terima bansos)
            [
                "'7203181000000002", 'Dusun II', '002', '001', 'Jl. Pantai Barat No. 5',
                "'7203182000000003", 'Ruslan Hadi', 'Tamarenja', '1975-11-10', 'L',
                'Islam', 'SLTP', 'Petani/Pekebun', 'kawin', 'kepala_keluarga',
                StatusKesejahteraan::DtsenDesil2->value, StatusKependudukan::Aktif->value,
            ],
        ];
        $sheet->fromArray($rows, null, 'A2');

        $tmpExcelPath = tempnam(sys_get_temp_dir(), 'siak_e2e_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tmpExcelPath);
        $uploadedExcel = new UploadedFile(
            $tmpExcelPath,
            'data_penduduk_siak.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($this->operator)
            ->post('/panel/kependudukan/import-excel', ['file' => $uploadedExcel])
            ->assertRedirect();

        @unlink($tmpExcelPath);

        $this->assertSame(2, KartuKeluarga::query()->count());
        $this->assertSame(3, Penduduk::query()->aktif()->count());

        // Cek Statistik Publik Agregat -> terbarui menjadi 3 penduduk aktif & 2 KK aktif tanpa bocor NIK/Nama
        $statRes = $this->get('/transparansi/statistik-penduduk');
        $statRes->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/StatistikPenduduk')
                ->where('ringkasan.total_penduduk_aktif', 3)
                ->where('ringkasan.total_kk_aktif', 2)
                ->where('ringkasan.laki_laki_count', 2)
                ->where('ringkasan.perempuan_count', 1)
            );

        $statJson = json_encode($statRes->viewData('page')['props'] ?? []);
        $this->assertStringNotContainsString('7203182000000001', $statJson);
        $this->assertStringNotContainsString('Anwar Sadat', $statJson);

        // =========================================================================
        // 2. WARGA MENGAJUKAN SURAT ONLINE -> OPERATOR VERIFIKASI dgn PENANDATANGAN
        //    "a.n. Kepala Desa - Sekretaris Desa" & CETAK PDF F4 -> QR DIPINDAI PUBLIK
        // =========================================================================
        $this->post('/layanan-mandiri/ajukan-surat', [
            'jenis_surat' => JenisSurat::Sku->value,
            'nik' => '7203182000000001',
            'tanggal_lahir' => '1980-03-15',
            'no_wa_pemohon' => '0813-4567-8901',
            'keperluan' => 'Persyaratan Modal Usaha Perikanan',
            'data_khusus' => [
                'nama_usaha' => 'UD Nelayan Mandiri',
                'jenis_usaha' => 'Perikanan Tangkap',
                'bidang_usaha' => 'Hasil Laut Segar',
                'lama_usaha' => '5 Tahun',
                'alamat_usaha' => 'Dusun I Desa Tamarenja',
            ],
        ])->assertRedirect('/layanan-mandiri');

        $suratOnline = SuratKeluar::query()
            ->where('jenis_surat', JenisSurat::Sku->value)
            ->firstOrFail();

        $this->assertSame(StatusSuratKeluar::MenungguVerifikasi, $suratOnline->status);
        $this->assertSame('6281345678901', $suratOnline->no_wa_pemohon);

        // Muncul di antrean Operator
        $this->actingAs($this->operator)
            ->get('/panel/persuratan/antrean')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Persuratan/Antrean')
                ->has('antrean', 1)
            );

        // Operator memverifikasi dengan opsi penandatangan `sekdes_an` (a.n. Kepala Desa - Sekretaris Desa)
        $this->actingAs($this->operator)
            ->post("/panel/persuratan/antrean/{$suratOnline->id}/verifikasi", [
                'opsi_penandatangan' => 'sekdes_an',
                'tanggal_surat' => '2026-04-20',
            ])
            ->assertRedirect();

        $suratOnline->refresh();
        $this->assertSame(StatusSuratKeluar::Terbit, $suratOnline->status);
        $this->assertNotNull($suratOnline->qr_token);
        $this->assertNotNull($suratOnline->nomor_surat);
        $this->assertSame('sekdes_an', $suratOnline->opsi_penandatangan);
        $this->assertStringContainsString('a.n. Kepala Desa', (string) $suratOnline->jabatan_penandatangan);

        // Operator mencetak PDF F4 Surat Keluar
        $pdfSuratRes = $this->actingAs($this->operator)
            ->get("/panel/persuratan/surat-keluar/{$suratOnline->id}/pdf");
        $pdfSuratRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfSuratRes->headers->get('content-type'));

        // QR Code dipindai publik -> menampilkan status SURAT ASLI & TERDAFTAR DI BUKU AGENDA DESA + 5 atribut resmi dgn nama tersamarkan
        $qrRes = $this->get("/layanan-mandiri/verifikasi-surat/{$suratOnline->qr_token}");
        $qrRes->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LayananMandiri/VerifikasiSurat')
                ->where('status_verifikasi', 'terbit')
                ->where('banner_judul', 'SURAT ASLI & TERDAFTAR DI BUKU AGENDA DESA')
                ->where('dokumen.nomor_surat', $suratOnline->nomor_surat)
                ->where('dokumen.jenis_surat', JenisSurat::Sku->label())
                ->where('dokumen.tanggal_terbit', '2026-04-20')
                ->where('dokumen.nama_warga_tersamarkan', 'A**** S****')
                ->where('dokumen.jabatan_penandatangan', $suratOnline->jabatan_penandatangan)
            );

        // =========================================================================
        // 3. OPERATOR MENERBITKAN SURAT KETERANGAN KEMATIAN -> 1 TRANSAKSI DB WARGA
        //    BERUBAH KE meninggal_dunia, BANSOS AKTIF BERUBAH KE meninggal_dunia,
        //    DAN PENGAJUAN SURAT BARU UNTUK NIK TERSEBUT OTOMATIS DITOLAK
        // =========================================================================
        $anwar = Penduduk::query()->where('nik', '7203182000000001')->firstOrFail();

        // Tambahkan Bansos Aktif untuk Anwar Sadat terlebih dahulu
        $bansosAnwar = PenerimaBansos::query()->create([
            'penduduk_id' => $anwar->id,
            'jenis_bantuan' => JenisBansos::BltDd->value,
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'status_kesejahteraan_saat_terima' => StatusKesejahteraan::DtsenDesil1->value,
            'status_kepesertaan' => StatusPesertaBansos::Aktif->value,
            'created_by' => $this->operator->id,
        ]);

        // Operator menerbitkan Surat Keterangan Kematian untuk Anwar Sadat
        $this->actingAs($this->operator)
            ->post('/panel/persuratan/surat-keluar', [
                'penduduk_id' => $anwar->id,
                'jenis_surat' => JenisSurat::Kematian->value,
                'kode_klasifikasi' => '474.3',
                'tanggal_surat' => '2026-04-22',
                'keperluan' => 'Administrasi Akta Kematian Disdukcapil',
                'opsi_penandatangan' => 'kepala_desa',
                'data_khusus' => [
                    'hari_meninggal' => 'Rabu',
                    'tanggal_meninggal' => '2026-04-22',
                    'jam_meninggal' => '06:30 WITA',
                    'tempat_meninggal' => 'RSUD Kabelota Donggala',
                    'penyebab_meninggal' => 'Sakit',
                    'lokasi_makam' => 'TPU Desa Tamarenja',
                    'nama_pelapor' => 'Rahmawati',
                    'nik_pelapor' => '7203182000000002',
                    'hubungan_pelapor' => 'Istri',
                ],
            ])
            ->assertRedirect();

        // Pastikan status penduduk & bansos berubah ke meninggal_dunia dalam 1 transaksi
        $this->assertSame(StatusKependudukan::MeninggalDunia, $anwar->fresh()->status_kependudukan);
        $this->assertSame(StatusPesertaBansos::MeninggalDunia, $bansosAnwar->fresh()->status_kepesertaan);

        // Pengajuan surat baru untuk NIK Anwar Sadat otomatis ditolak (baik online maupun loket)
        $this->from('/layanan-mandiri')
            ->post('/layanan-mandiri/ajukan-surat', [
                'jenis_surat' => JenisSurat::Domisili->value,
                'nik' => $anwar->nik,
                'tanggal_lahir' => '1980-03-15',
                'no_wa_pemohon' => '081345678901',
                'keperluan' => 'Coba ajukan setelah wafat',
                'data_khusus' => [
                    'sejak_tahun' => '2010',
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
            ])
            ->assertRedirect('/layanan-mandiri')
            ->assertSessionHasErrors(['nik']);

        // =========================================================================
        // 4. OPERATOR MENGINPUT PROYEK FISIK MODUL 1 DARI 0% -> 50% -> 100% ->
        //    SINKRONISASI bidang_2_fisik APBDES MURNI -> SAAT PERUBAHAN DIBUAT,
        //    MURNI TERKUNCI UTUH & UPDATE PROYEK BERIKUTNYA HANYA MENGUBAH PERUBAHAN
        // =========================================================================
        $tahunAktif = 2026;

        // 4a. Buat proyek fisik 0% (Belum Dimulai)
        $this->actingAs($this->operator)
            ->post('/panel/pembangunan', [
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => 'Pembangunan Talud Penahan Ombak Dusun I',
                'lokasi_dusun' => 'Dusun I',
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'pagu_anggaran' => 200000000,
                'realisasi_anggaran' => 0,
                'progres_fisik' => 0,
                'latitude' => -0.4615,
                'longitude' => 119.7755,
            ])
            ->assertRedirect();

        $proyek = ProyekPembangunan::query()->where('tahun_anggaran', $tahunAktif)->firstOrFail();
        $this->assertSame(StatusProyek::BelumDimulai, $proyek->status_proyek);

        // 4b. Update proyek ke 50% (+ Foto 0% & Foto 50%)
        $this->actingAs($this->operator)
            ->post("/panel/pembangunan/{$proyek->id}", [
                '_method' => 'PUT',
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => 'Dusun I',
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'pagu_anggaran' => 200000000,
                'realisasi_anggaran' => 100000000,
                'progres_fisik' => 50,
                'foto_0' => UploadedFile::fake()->image('titik-0.jpg', 800, 600),
                'foto_50' => UploadedFile::fake()->image('titik-50.jpg', 800, 600),
            ])
            ->assertRedirect();

        $proyek->refresh();
        $this->assertSame(StatusProyek::SedangDikerjakan, $proyek->status_proyek);

        // 4c. Update proyek ke 100% (+ Foto 100%)
        $this->actingAs($this->operator)
            ->post("/panel/pembangunan/{$proyek->id}", [
                '_method' => 'PUT',
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => 'Dusun I',
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'pagu_anggaran' => 200000000,
                'realisasi_anggaran' => 195000000,
                'progres_fisik' => 100,
                'foto_100' => UploadedFile::fake()->image('titik-100.jpg', 800, 600),
            ])
            ->assertRedirect();

        $proyek->refresh();
        $this->assertSame(StatusProyek::Selesai, $proyek->status_proyek);

        $murni = Apbdes::query()
            ->where('tahun_anggaran', $tahunAktif)
            ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
            ->firstOrFail();

        $murniBidang2Fisik = ApbdesRincian::query()
            ->where('apbdes_id', $murni->id)
            ->where('kode_pos', 'bidang_2_fisik')
            ->firstOrFail();

        $this->assertSame(200000000, $murniBidang2Fisik->pagu_anggaran);
        $this->assertSame(195000000, $murniBidang2Fisik->total_realisasi);

        // 4d. Buat P-APBDes (Perubahan) -> Murni terkunci utuh
        $this->actingAs($this->operator)
            ->post('/panel/apbdes/buat-perubahan', [
                'tahun_anggaran' => $tahunAktif,
                'nomor_perdes' => 'Perdes No. 5 Tahun 2026',
                'tanggal_penetapan' => '2026-08-20',
            ])
            ->assertRedirect();

        $this->assertTrue($murni->fresh()->is_terkunci);

        // 4e. Tambah proyek baru pasca-Perubahan -> Hanya mengubah Perubahan, Murni tetap utuh 200jt/195jt
        $this->actingAs($this->operator)
            ->post('/panel/pembangunan', [
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => 'Rehab Jembatan Dusun II',
                'lokasi_dusun' => 'Dusun II',
                'sumber_dana' => SumberDanaProyek::Add->value,
                'pagu_anggaran' => 80000000,
                'realisasi_anggaran' => 0,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        $perubahan = Apbdes::query()
            ->where('tahun_anggaran', $tahunAktif)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->firstOrFail();

        $perubahanBidang2Fisik = ApbdesRincian::query()
            ->where('apbdes_id', $perubahan->id)
            ->where('kode_pos', 'bidang_2_fisik')
            ->firstOrFail();

        // Murni tetap utuh 200.000.000
        $this->assertSame(200000000, $murniBidang2Fisik->fresh()->pagu_anggaran);
        // Perubahan menjadi 280.000.000
        $this->assertSame(280000000, $perubahanBidang2Fisik->pagu_anggaran);

        // =========================================================================
        // 5. FILTER PEMERATAAN BANSOS BERBASIS KK, EXPORT EXCEL, BACKUP .SQL & 8-SHEET
        //    .XLSX, SERTA HAK AKSES KEPALA DESA (READ-ONLY + CETAK SELURUH PDF F4)
        // =========================================================================
        // KK 1 sudah memiliki catatan bansos di 2026 (meskipun sekarang berstatus meninggal_dunia),
        // sehingga Rahmawati (istri di KK 1) TIDAK boleh muncul di Pemerataan KK!
        // Hanya KK 2 (Ruslan Hadi) yang muncul di Tab Pemerataan Bantuan berbasis KK:
        $this->actingAs($this->operator)
            ->get('/panel/sosial/bansos?tahun=2026&mode_pemerataan=kk')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sosial/Bansos')
                ->has('pemerataan_kk', 1)
                ->where('pemerataan_kk.0.nik_kepala_keluarga', '7203182000000003')
                ->where('pemerataan_kk.0.nama_kepala_keluarga', 'Ruslan Hadi')
            );

        // Operator mengekspor Excel Bansos & mengunduh Backup .sql dan 8-Sheet .xlsx
        $this->actingAs($this->operator)
            ->get('/panel/sosial/bansos/export-excel?tahun=2026')
            ->assertOk();

        $sqlBackupRes = $this->actingAs($this->operator)
            ->post('/panel/pengaturan/backup/sql');
        $sqlBackupRes->assertOk();

        $xlsxBackupRes = $this->actingAs($this->operator)
            ->post('/panel/pengaturan/backup/excel');
        $xlsxBackupRes->assertOk();

        $excelFiles = Storage::disk('private')->files('backups');
        $xlsxFile = collect($excelFiles)->first(fn (string $f) => str_ends_with($f, '.xlsx'));
        $this->assertNotNull($xlsxFile);
        $loadedWorkbook = IOFactory::load(Storage::disk('private')->path($xlsxFile));
        $this->assertSame(8, $loadedWorkbook->getSheetCount());

        // Kepala Desa dapat memantau seluruh modul & mencetak seluruh PDF F4
        $kadesReadAndPdfRoutes = [
            '/panel/dashboard',
            '/panel/kependudukan',
            '/panel/pembangunan',
            '/panel/pembangunan/cetak-pdf?tahun=2026',
            '/panel/apbdes',
            '/panel/apbdes/cetak-pdf?tahun=2026',
            '/panel/persuratan/antrean',
            '/panel/persuratan/surat-keluar',
            '/panel/persuratan/surat-keluar/cetak-agenda?tahun=2026',
            '/panel/persuratan/surat-masuk',
            '/panel/persuratan/surat-masuk/cetak-agenda?tahun=2026',
            '/panel/sosial/bansos',
            '/panel/sosial/bansos/cetak-pdf?tahun=2026',
            '/panel/sosial/kegiatan',
        ];

        foreach ($kadesReadAndPdfRoutes as $route) {
            $this->actingAs($this->kades)->get($route)->assertOk();
        }

        // Kepala Desa ditolak 403 saat mencoba memodifikasi data atau mengakses Pengaturan/Backup
        $this->actingAs($this->kades)
            ->post('/panel/pembangunan', [])
            ->assertForbidden();
        $this->actingAs($this->kades)
            ->post('/panel/apbdes/buat-perubahan', ['tahun_anggaran' => 2026])
            ->assertForbidden();
        $this->actingAs($this->kades)
            ->post('/panel/persuratan/surat-keluar', [])
            ->assertForbidden();
        $this->actingAs($this->kades)
            ->get('/panel/pengaturan/backup')
            ->assertForbidden();
    }

    public function test_fresh_seed_with_essential_and_dummy_desa_seeder_renders_all_public_and_internal_pages_without_errors(): void
    {
        $this->seed(DummyDesaSeeder::class);

        $publicRoutes = [
            '/',
            '/profil',
            '/transparansi/apbdes',
            '/transparansi/pembangunan',
            '/transparansi/pembangunan/cetak-pdf',
            '/transparansi/statistik-penduduk',
            '/kegiatan',
            '/unduhan',
            '/layanan-mandiri',
        ];

        foreach ($publicRoutes as $route) {
            $this->get($route)->assertOk();
        }

        // Pastikan aset fisik kegiatan, PPID, proyek, dan surat masuk yang dibuat DummyDesaSeeder valid & tidak 404
        $publishedKegiatan = KegiatanDesa::query()->published()->firstOrFail();
        $this->get('/kegiatan/'.$publishedKegiatan->slug)->assertOk();

        $publicPpid = DokumenPpid::query()->published()->firstOrFail();
        $this->get("/unduhan/{$publicPpid->id}/file")->assertOk();

        $terbitSurat = SuratKeluar::query()->where('status', StatusSuratKeluar::Terbit)->whereNotNull('qr_token')->firstOrFail();
        $this->get("/layanan-mandiri/verifikasi-surat/{$terbitSurat->qr_token}")->assertOk();

        $internalRoutes = [
            '/panel/dashboard',
            '/panel/kependudukan',
            '/panel/pembangunan',
            '/panel/apbdes',
            '/panel/persuratan/antrean',
            '/panel/persuratan/surat-keluar',
            '/panel/persuratan/surat-masuk',
            '/panel/sosial/bansos',
            '/panel/sosial/kegiatan',
            '/panel/pengaturan/profil-desa',
            '/panel/pengaturan/sotk',
            '/panel/pengaturan/ppid',
            '/panel/pengaturan/pengguna',
            '/panel/pengaturan/backup',
        ];

        foreach ($internalRoutes as $route) {
            $this->actingAs($this->operator)->get($route)->assertOk();
        }

        $suratMasuk = SuratMasuk::query()->whereNotNull('berkas_scan_path')->first();
        if ($suratMasuk) {
            $this->actingAs($this->operator)
                ->get("/panel/persuratan/surat-masuk/{$suratMasuk->id}/berkas")
                ->assertOk();
        }
    }
}
