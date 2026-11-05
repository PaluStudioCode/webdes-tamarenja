<?php

namespace Tests\Feature\Publik;

use App\Enums\KategoriKegiatan;
use App\Enums\StatusKependudukan;
use App\Enums\StatusProyek;
use App\Enums\StatusPublikasi;
use App\Enums\SumberDanaProyek;
use App\Models\DokumenPpid;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\KegiatanDesaFoto;
use App\Models\Penduduk;
use App\Models\PerangkatDesa;
use App\Models\ProyekPembangunan;
use App\Models\User;
use App\Services\ApbdesService;
use Carbon\Carbon;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalInformasiTerbukaAndUuPdpAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(EssentialDesaSeeder::class);

        $this->operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();
    }

    public function test_strict_uu_pdp_audit_ensures_no_nik_kk_birthdate_or_resident_names_leak_across_all_public_routes(): void
    {
        $kk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203180101019999',
            'dusun' => 'Dusun I',
        ]);

        Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203181122334455',
            'nama_lengkap' => 'WARGA RAHASIA SATU',
            'tanggal_lahir' => '1991-07-17',
            'jenis_kelamin' => 'L',
            'pekerjaan' => 'Petani/Pekebun',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $routes = [
            '/',
            '/profil',
            '/transparansi/apbdes',
            '/transparansi/pembangunan',
            '/transparansi/statistik-penduduk',
            '/kegiatan',
            '/unduhan',
        ];

        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertOk();

            $jsonPayload = json_encode($response->viewData('page')['props'] ?? []);

            $this->assertStringNotContainsString('7203181122334455', $jsonPayload, "NIK bocor pada rute publik {$route}");
            $this->assertStringNotContainsString('7203180101019999', $jsonPayload, "Nomor KK bocor pada rute publik {$route}");
            $this->assertStringNotContainsString('1991-07-17', $jsonPayload, "Tanggal lahir warga bocor pada rute publik {$route}");
            $this->assertStringNotContainsString('WARGA RAHASIA SATU', $jsonPayload, "Nama warga bocor pada rute publik {$route}");
            $this->assertStringNotContainsString('"nik"', $jsonPayload, "Key nik ditemukan pada rute publik {$route}");
            $this->assertStringNotContainsString('"nomor_kk"', $jsonPayload, "Key nomor_kk ditemukan pada rute publik {$route}");
            $this->assertStringNotContainsString('"tanggal_lahir"', $jsonPayload, "Key tanggal_lahir ditemukan pada rute publik {$route}");
        }
    }

    public function test_beranda_limits_proyek_terbaru_and_kegiatan_terbaru_to_maximum_3_items(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            ProyekPembangunan::query()->create([
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => "Proyek Fisik {$i}",
                'lokasi_dusun' => 'Dusun I',
                'sumber_dana' => SumberDanaProyek::Dds,
                'pagu_anggaran' => 100000000,
                'realisasi_anggaran' => 50000000,
                'progres_fisik' => 50,
                'status_proyek' => StatusProyek::SedangDikerjakan,
                'created_by' => $this->operator->id,
            ]);

            $kegiatan = KegiatanDesa::query()->create([
                'judul' => "Kegiatan Desa {$i}",
                'slug' => "kegiatan-desa-{$i}",
                'kategori' => KategoriKegiatan::GotongRoyong,
                'tanggal_kegiatan' => '2026-04-0'.$i,
                'lokasi_dusun' => 'Dusun I',
                'uraian' => "Uraian kegiatan desa {$i}",
                'status_publikasi' => StatusPublikasi::Published,
                'published_at' => now(),
                'created_by' => $this->operator->id,
            ]);

            KegiatanDesaFoto::query()->create([
                'kegiatan_desa_id' => $kegiatan->id,
                'foto_path' => "kegiatan/2026/foto-{$i}.webp",
                'urutan' => 1,
            ]);
        }

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Beranda')
                ->has('proyek_terbaru', 3)
                ->has('kegiatan_terbaru', 3)
                ->where('tahun_aktif', 2026)
            );
    }

    public function test_profil_separates_active_pemerintah_and_bpd_perangkat_and_excludes_inactive_officials(): void
    {
        PerangkatDesa::query()->delete();

        PerangkatDesa::query()->create([
            'nama_lengkap' => 'Moh. Rizal, S.AP',
            'jabatan' => 'Kepala Desa',
            'kelompok_sotk' => 'pimpinan',
            'urutan' => 1,
            'is_aktif' => true,
        ]);

        PerangkatDesa::query()->create([
            'nama_lengkap' => 'Ketua BPD Aktif',
            'jabatan' => 'Ketua BPD',
            'kelompok_sotk' => 'bpd',
            'urutan' => 2,
            'is_aktif' => true,
        ]);

        PerangkatDesa::query()->create([
            'nama_lengkap' => 'Mantan Perangkat Nonaktif',
            'jabatan' => 'Kasi Pemerintahan',
            'kelompok_sotk' => 'pelaksana_teknis',
            'urutan' => 3,
            'is_aktif' => false,
        ]);

        $this->get('/profil')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/Profil')
                ->has('sotk_pemerintah', 1)
                ->has('sotk_bpd', 1)
                ->where('sotk_pemerintah.0.nama_lengkap', 'Moh. Rizal, S.AP')
                ->where('sotk_bpd.0.nama_lengkap', 'Ketua BPD Aktif')
            );
    }

    public function test_statistik_penduduk_accurately_calculates_5_age_groups_and_excludes_deceased_and_moved_residents(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 6, 15, 10, 0, 0));

        $kkAktif = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203180000000001',
            'dusun' => 'Dusun I',
        ]);

        $kkNonAktif = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203180000000002',
            'dusun' => 'Dusun II',
        ]);

        $createResident = function (KartuKeluarga $kk, string $nikSuffix, string $birthDate, string $gender, StatusKependudukan $status): void {
            Penduduk::factory()->create([
                'kartu_keluarga_id' => $kk->id,
                'nik' => '720318000000'.$nikSuffix,
                'nama_lengkap' => 'Warga '.$nikSuffix,
                'tanggal_lahir' => $birthDate,
                'jenis_kelamin' => $gender,
                'pekerjaan' => 'Petani/Pekebun',
                'status_kependudukan' => $status,
            ]);
        };

        // 1 resident in each of the 5 standard age brackets (all aktif):
        // 0-4 years old (age 2 in 2026)
        $createResident($kkAktif, '0101', '2024-01-10', 'L', StatusKependudukan::Aktif);
        // 5-14 years old (age 10 in 2026)
        $createResident($kkAktif, '0102', '2016-03-10', 'P', StatusKependudukan::Aktif);
        // 15-24 years old (age 20 in 2026)
        $createResident($kkAktif, '0103', '2006-05-10', 'L', StatusKependudukan::Aktif);
        // 25-59 years old (age 40 in 2026)
        $createResident($kkAktif, '0104', '1986-07-10', 'P', StatusKependudukan::Aktif);
        // 60+ years old (age 68 in 2026)
        $createResident($kkAktif, '0105', '1958-02-10', 'L', StatusKependudukan::Aktif);

        // Deceased & Moved residents (MUST BE EXCLUDED from all statistics & KK count)
        $createResident($kkNonAktif, '0201', '1970-01-01', 'L', StatusKependudukan::MeninggalDunia);
        $createResident($kkNonAktif, '0202', '1995-01-01', 'P', StatusKependudukan::PindahKeluar);

        $this->get('/transparansi/statistik-penduduk')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/StatistikPenduduk')
                ->where('ringkasan.total_penduduk_aktif', 5)
                ->where('ringkasan.total_kk_aktif', 1)
                ->where('ringkasan.laki_laki_count', 3)
                ->where('ringkasan.perempuan_count', 2)
                ->where('kelompok_usia.0.kode', '0_4')
                ->where('kelompok_usia.0.jumlah', 1)
                ->where('kelompok_usia.1.kode', '5_14')
                ->where('kelompok_usia.1.jumlah', 1)
                ->where('kelompok_usia.2.kode', '15_24')
                ->where('kelompok_usia.2.jumlah', 1)
                ->where('kelompok_usia.3.kode', '25_59')
                ->where('kelompok_usia.3.jumlah', 1)
                ->where('kelompok_usia.4.kode', '60_plus')
                ->where('kelompok_usia.4.jumlah', 1)
            );

        Carbon::setTestNow();
    }

    public function test_public_can_download_f4_landscape_pdf_rekap_pembangunan_without_authentication(): void
    {
        ProyekPembangunan::query()->create([
            'tahun_anggaran' => 2026,
            'nama_kegiatan' => 'Drainase Dusun I',
            'lokasi_dusun' => 'Dusun I',
            'sumber_dana' => SumberDanaProyek::Dds,
            'pagu_anggaran' => 120000000,
            'realisasi_anggaran' => 120000000,
            'progres_fisik' => 100,
            'status_proyek' => StatusProyek::Selesai,
            'created_by' => $this->operator->id,
        ]);

        $response = $this->get('/transparansi/pembangunan/cetak-pdf?tahun=2026');

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_transparansi_apbdes_provides_murni_and_perubahan_comparison(): void
    {
        /** @var ApbdesService $apbdesService */
        $apbdesService = app(ApbdesService::class);

        $murni = $apbdesService->initializeMurni(2026, $this->operator->id);
        $apbdesService->updateBatchRincian($murni, [
            'dds' => [
                'pagu_anggaran' => 800000000,
                'realisasi_tahap_1' => 800000000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ],
        ]);

        $perubahan = $apbdesService->createPerubahanCloneAndFreeze(2026, $this->operator->id);
        $apbdesService->updateBatchRincian($perubahan, [
            'dds' => [
                'pagu_anggaran' => 950000000,
                'realisasi_tahap_1' => 900000000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ],
        ]);

        $this->get('/transparansi/apbdes?tahun=2026')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Publik/TransparansiApbdes')
                ->where('has_perubahan', true)
                ->where('dokumen_aktif.jenis_dokumen', 'perubahan')
                ->where('ringkasan.total_pagu_pendapatan', 950000000)
            );
    }

    public function test_draft_kegiatan_and_non_public_ppid_return_404_while_public_ppid_increments_jumlah_unduhan(): void
    {
        $draftKegiatan = KegiatanDesa::query()->create([
            'judul' => 'Draft Rapat Internal',
            'slug' => 'draft-rapat-internal',
            'kategori' => KategoriKegiatan::Musdes,
            'tanggal_kegiatan' => '2026-05-01',
            'lokasi_dusun' => 'Kantor Desa',
            'uraian' => 'Rapat internal belum dipublikasi',
            'status_publikasi' => StatusPublikasi::Draft,
            'created_by' => $this->operator->id,
        ]);

        $this->get('/kegiatan/'.$draftKegiatan->slug)->assertNotFound();

        Storage::disk('public')->put('ppid/2026/rahasia.pdf', '%PDF-1.4 rahasia');
        Storage::disk('public')->put('ppid/2026/perdes-apbdes.pdf', '%PDF-1.4 publik');

        $privatePpid = DokumenPpid::query()->create([
            'judul_dokumen' => 'Dokumen Internal Non Publik',
            'kategori' => 'sk_kades',
            'tahun_dokumen' => 2026,
            'file_pdf_path' => 'ppid/2026/rahasia.pdf',
            'ukuran_file_kb' => 120,
            'is_publik' => false,
            'jumlah_unduhan' => 0,
            'uploaded_by' => $this->operator->id,
        ]);

        $publicPpid = DokumenPpid::query()->create([
            'judul_dokumen' => 'Perdes APBDes Tahun 2026',
            'kategori' => 'perdes',
            'tahun_dokumen' => 2026,
            'file_pdf_path' => 'ppid/2026/perdes-apbdes.pdf',
            'ukuran_file_kb' => 250,
            'is_publik' => true,
            'jumlah_unduhan' => 3,
            'uploaded_by' => $this->operator->id,
        ]);

        // Non-public PPID returns 404
        $this->get("/unduhan/{$privatePpid->id}/file")->assertNotFound();

        // Public PPID downloads and increments counter
        $downloadResponse = $this->get("/unduhan/{$publicPpid->id}/file");
        $downloadResponse->assertOk();

        $this->assertSame(4, $publicPpid->fresh()->jumlah_unduhan);
    }
}
