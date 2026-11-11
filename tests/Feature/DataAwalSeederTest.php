<?php

namespace Tests\Feature;

use App\Enums\JenisDokumenApbdes;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\PerangkatDesa;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Database\Seeders\DataAwalSeeder;
use Database\Seeders\DummyDesaSeeder;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataAwalSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_awal_seeder_cleans_transactional_data_and_keeps_master_data(): void
    {
        Storage::fake('public');

        // Pertama, isi database dengan data dummy lengkap
        $this->seed([EssentialDesaSeeder::class, DummyDesaSeeder::class]);

        $this->assertGreaterThan(0, ProyekPembangunan::count());
        $this->assertGreaterThan(0, SuratKeluar::count());
        $this->assertGreaterThan(0, SuratMasuk::count());
        $this->assertGreaterThan(0, PenerimaBansos::count());
        $this->assertGreaterThan(0, KegiatanDesa::count());
        $this->assertGreaterThan(1, Apbdes::count());

        // Jalankan DataAwalSeeder
        $this->seed(DataAwalSeeder::class);

        // Pastikan seluruh data transaksional telah bersih (0 records)
        $this->assertSame(0, ProyekPembangunan::count(), 'Proyek pembangunan harus 0');
        $this->assertSame(0, SuratKeluar::count(), 'Surat keluar harus 0');
        $this->assertSame(0, SuratMasuk::count(), 'Surat masuk harus 0');
        $this->assertSame(0, PenerimaBansos::count(), 'Penerima bansos harus 0');
        $this->assertSame(0, KegiatanDesa::count(), 'Kegiatan desa harus 0');

        // Pastikan data master tetap ada
        $this->assertSame(2, User::count(), 'User operator dan kades harus tetap ada');
        $this->assertSame(1, ProfilDesa::count(), 'Profil desa harus tetap ada');
        $this->assertSame(13, PerangkatDesa::count(), 'Perangkat desa (13 SOTK) harus tetap ada');
        $this->assertSame(28, KartuKeluarga::count(), 'Kartu keluarga harus tetap 28');
        $this->assertSame(100, Penduduk::count(), 'Penduduk harus tetap 100');

        // Pastikan hanya ada 1 dokumen APBDes Murni baru dengan nilai 0
        $this->assertSame(1, Apbdes::count(), 'Hanya ada 1 dokumen APBDes aktif');
        $apbdes = Apbdes::with('rincian')->firstOrFail();
        $this->assertSame(JenisDokumenApbdes::Murni, $apbdes->jenis_dokumen);
        $this->assertFalse($apbdes->is_terkunci);
        $this->assertTrue($apbdes->is_aktif_publik);
        $this->assertNull($apbdes->nomor_perdes);
        $this->assertNull($apbdes->tanggal_penetapan);

        // 14 Pos baku terbuat dengan nilai 0
        $this->assertCount(14, $apbdes->rincian);
        $this->assertSame(0, (int) $apbdes->rincian->sum('pagu_anggaran'));
        $this->assertSame(0, (int) $apbdes->rincian->sum('total_realisasi'));
    }

    public function test_artisan_command_simulasi_periode_baru_executes_successfully(): void
    {
        Storage::fake('public');

        $this->artisan('desa:simulasi-periode-baru --tahun=2027 --force')
            ->assertSuccessful();

        $apbdes = Apbdes::firstOrFail();
        $this->assertSame(2027, $apbdes->tahun_anggaran);
        $this->assertSame(JenisDokumenApbdes::Murni, $apbdes->jenis_dokumen);
        $this->assertSame(0, (int) ApbdesRincian::sum('pagu_anggaran'));
        $this->assertSame(28, KartuKeluarga::count());
        $this->assertSame(13, PerangkatDesa::count());
    }
}
