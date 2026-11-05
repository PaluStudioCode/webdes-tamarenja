<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisBansos;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusProyek;
use App\Enums\StatusSuratKeluar;
use App\Enums\SumberDanaProyek;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Services\ApbdesService;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardAggregationAndRbacTest extends TestCase
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

    public function test_dashboard_aggregates_murni_accurately_and_switches_to_perubahan_when_created(): void
    {
        /** @var ApbdesService $apbdesService */
        $apbdesService = app(ApbdesService::class);

        $tahun = 2026;
        $murni = $apbdesService->initializeMurni($tahun, $this->operator->id);

        // Isi pagu & realisasi pada APBDes Murni 2026
        $apbdesService->updateBatchRincian($murni, [
            'dds' => [
                'pagu_anggaran' => 800000000,
                'realisasi_tahap_1' => 400000000,
                'realisasi_tahap_2' => 200000000,
                'realisasi_tahap_3' => 0,
            ],
            'bidang_1' => [
                'pagu_anggaran' => 300000000,
                'realisasi_tahap_1' => 150000000,
                'realisasi_tahap_2' => 100000000,
                'realisasi_tahap_3' => 0,
            ],
        ]);

        // Buat 1 proyek fisik dengan foto dan kendala lapangan
        ProyekPembangunan::query()->create([
            'tahun_anggaran' => $tahun,
            'nama_kegiatan' => 'Pembangunan Drainase Dusun I',
            'lokasi_dusun' => 'Dusun I',
            'pagu_anggaran' => 120000000,
            'realisasi_anggaran' => 60000000,
            'sumber_dana' => SumberDanaProyek::Dds,
            'progres_fisik' => 55,
            'status_proyek' => StatusProyek::SedangDikerjakan,
            'foto_0_path' => 'proyek/2026/foto-0.webp',
            'foto_50_path' => 'proyek/2026/foto-50.webp',
            'catatan_kendala' => 'Curah hujan tinggi menghambat pengecoran.',
            'created_by' => $this->operator->id,
        ]);
        $apbdesService->syncBidang2FisikFromProjects($tahun, $this->operator->id);

        // Buat data penduduk, surat, dan bansos
        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun I']);
        $warga = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'status_kependudukan' => StatusKependudukan::Aktif,
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil1,
        ]);

        SuratKeluar::factory()->create([
            'penduduk_id' => $warga->id,
            'tahun_surat' => $tahun,
            'nomor_urut' => 1,
            'nomor_surat' => '470/001/DS-TMR/I/2026',
            'status' => StatusSuratKeluar::Terbit,
            'processed_by' => $this->operator->id,
        ]);

        SuratKeluar::factory()->create([
            'penduduk_id' => $warga->id,
            'status' => StatusSuratKeluar::MenungguVerifikasi,
        ]);

        SuratMasuk::factory()->create([
            'tahun_agenda' => $tahun,
            'nomor_urut_agenda' => 1,
            'created_by' => $this->operator->id,
        ]);

        PenerimaBansos::query()->create([
            'penduduk_id' => $warga->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => $tahun,
            'tahap_penyaluran' => 'Tahap I',
            'kategori_ekonomi' => StatusKesejahteraan::DtsenDesil1,
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // 1. Ketika hanya ada APBDes Murni -> Dashboard menampilkan angka Murni
        $this->actingAs($this->operator)
            ->withSession(['filter_tahun' => $tahun])
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Dashboard')
                ->where('tahun_anggaran', $tahun)
                ->where('apbdes.jenis_dokumen', 'murni')
                ->where('apbdes.has_perubahan', false)
                ->where('apbdes.total_pagu_belanja', 420000000)
                ->where('apbdes.total_realisasi_belanja', 310000000)
                ->where('pembangunan.total_proyek', 1)
                ->where('pembangunan.sedang_dikerjakan_count', 1)
                ->where('pembangunan.daftar_proyek.0.catatan_kendala', 'Curah hujan tinggi menghambat pengecoran.')
                ->where('pembangunan.daftar_proyek.0.foto_0_path', 'proyek/2026/foto-0.webp')
                ->where('pembangunan.daftar_proyek.0.foto_50_path', 'proyek/2026/foto-50.webp')
                ->where('persuratan.antrean_menunggu_count', 1)
                ->where('persuratan.surat_keluar_terbit_count', 1)
                ->where('persuratan.surat_masuk_count', 1)
                ->where('sosial.penerima_aktif_count', 1)
            );

        // 2. Buat APBDes Perubahan (Clone & Freeze) dan ubah pagu bidang_3 pada Perubahan
        $perubahan = $apbdesService->createPerubahanCloneAndFreeze(
            $tahun,
            $this->operator->id,
            'Perdes Perubahan No. 04 Tahun 2026',
            '2026-08-10'
        );

        $apbdesService->updateBatchRincian($perubahan, [
            'bidang_3' => [
                'pagu_anggaran' => 80000000,
                'realisasi_tahap_1' => 50000000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ],
        ]);

        // Dashboard wajib otomatis mengutamakan angka dari dokumen Perubahan
        $this->actingAs($this->kades)
            ->withSession(['filter_tahun' => $tahun])
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Panel/Dashboard')
                ->where('apbdes.jenis_dokumen', 'perubahan')
                ->where('apbdes.has_perubahan', true)
                ->where('apbdes.total_pagu_belanja', 500000000)
                ->where('apbdes.total_realisasi_belanja', 360000000)
            );
    }

    public function test_dashboard_respects_session_filter_tahun_and_is_accessible_by_operator_and_kepala_desa(): void
    {
        /** @var ApbdesService $apbdesService */
        $apbdesService = app(ApbdesService::class);
        $apbdes2025 = $apbdesService->initializeMurni(2025, $this->operator->id);
        $apbdesService->updateBatchRincian($apbdes2025, [
            'bidang_1' => [
                'pagu_anggaran' => 210000000,
                'realisasi_tahap_1' => 200000000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ],
        ]);

        // Operator melihat tahun 2025 sesuai session('filter_tahun')
        $this->actingAs($this->operator)
            ->withSession(['filter_tahun' => 2025])
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tahun_anggaran', 2025)
                ->where('apbdes.total_pagu_belanja', 210000000)
            );

        // Kepala Desa melihat tahun 2025 dengan akses penuh Read-Only
        $this->actingAs($this->kades)
            ->withSession(['filter_tahun' => 2025])
            ->get('/panel/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tahun_anggaran', 2025)
                ->where('apbdes.total_pagu_belanja', 210000000)
            );
    }
}
