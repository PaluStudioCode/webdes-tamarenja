<?php

namespace Tests\Feature;

use App\Enums\JenisDokumenApbdes;
use App\Enums\JenisSurat;
use App\Enums\KelompokPosApbdes;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPublikasi;
use App\Enums\StatusSuratKeluar;
use App\Models\Apbdes;
use App\Models\DokumenPpid;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\KegiatanDesaFoto;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\PerangkatDesa;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DummyDesaSeeder;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DummyDesaSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_does_not_seed_dummy_data_in_testing_environment(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, KartuKeluarga::count());
        $this->assertSame(0, Penduduk::count());
        $this->assertSame(0, ProyekPembangunan::count());
    }

    public function test_dummy_desa_seeder_satisfies_all_prd_invariants_files_and_idempotency(): void
    {
        Storage::fake('public');
        Storage::fake('private');

        // Eksekusi pertama
        $this->seed([EssentialDesaSeeder::class, DummyDesaSeeder::class]);

        $countsFirstRun = [
            'kk' => KartuKeluarga::count(),
            'penduduk' => Penduduk::count(),
            'proyek' => ProyekPembangunan::count(),
            'apbdes' => Apbdes::count(),
            'surat_keluar' => SuratKeluar::count(),
            'surat_masuk' => SuratMasuk::count(),
            'bansos' => PenerimaBansos::count(),
            'kegiatan' => KegiatanDesa::count(),
            'kegiatan_foto' => KegiatanDesaFoto::count(),
            'perangkat' => PerangkatDesa::count(),
            'ppid' => DokumenPpid::count(),
        ];

        // Eksekusi kedua untuk memastikan idempotensi penuh
        $this->seed([EssentialDesaSeeder::class, DummyDesaSeeder::class]);

        $this->assertSame($countsFirstRun, [
            'kk' => KartuKeluarga::count(),
            'penduduk' => Penduduk::count(),
            'proyek' => ProyekPembangunan::count(),
            'apbdes' => Apbdes::count(),
            'surat_keluar' => SuratKeluar::count(),
            'surat_masuk' => SuratMasuk::count(),
            'bansos' => PenerimaBansos::count(),
            'kegiatan' => KegiatanDesa::count(),
            'kegiatan_foto' => KegiatanDesaFoto::count(),
            'perangkat' => PerangkatDesa::count(),
            'ppid' => DokumenPpid::count(),
        ]);

        $profil = ProfilDesa::singleton();
        $tahunAktif = $profil->tahun_anggaran_aktif;
        $tahunLalu = $tahunAktif - 1;

        // 1. Verifikasi Kependudukan (25-30 KK, ~100 warga, 5 kelompok usia, 4 DTSEN, mutasi)
        $this->assertGreaterThanOrEqual(25, KartuKeluarga::count());
        $this->assertLessThanOrEqual(30, KartuKeluarga::count());
        $this->assertSame(100, Penduduk::count());

        $dusunTerpakai = KartuKeluarga::pluck('dusun')->unique()->values()->all();
        foreach ($dusunTerpakai as $dusun) {
            $this->assertContains($dusun, $profil->daftar_dusun);
        }

        foreach (StatusKesejahteraan::cases() as $desil) {
            $this->assertGreaterThan(0, Penduduk::where('status_kesejahteraan', $desil)->count());
        }
        $this->assertGreaterThan(0, Penduduk::where('status_kependudukan', StatusKependudukan::MeninggalDunia)->count());
        $this->assertGreaterThan(0, Penduduk::where('status_kependudukan', StatusKependudukan::PindahKeluar)->count());

        // Verifikasi 5 kelompok usia baku terwakili pada penduduk aktif
        $aktifList = Penduduk::aktif()->get();
        $this->assertTrue($aktifList->contains(fn (Penduduk $p) => $p->tanggal_lahir->age <= 4));
        $this->assertTrue($aktifList->contains(fn (Penduduk $p) => $p->tanggal_lahir->age >= 5 && $p->tanggal_lahir->age <= 14));
        $this->assertTrue($aktifList->contains(fn (Penduduk $p) => $p->tanggal_lahir->age >= 15 && $p->tanggal_lahir->age <= 24));
        $this->assertTrue($aktifList->contains(fn (Penduduk $p) => $p->tanggal_lahir->age >= 25 && $p->tanggal_lahir->age <= 59));
        $this->assertTrue($aktifList->contains(fn (Penduduk $p) => $p->tanggal_lahir->age >= 60));

        // 2. Verifikasi Sinkronisasi Proyek Fisik == bidang_2_fisik & Keseimbangan APBDes == 0
        $apbdesLaluMurni = Apbdes::where('tahun_anggaran', $tahunLalu)->where('jenis_dokumen', JenisDokumenApbdes::Murni)->firstOrFail();
        $apbdesLaluPerubahan = Apbdes::where('tahun_anggaran', $tahunLalu)->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)->firstOrFail();
        $apbdesAktifMurni = Apbdes::where('tahun_anggaran', $tahunAktif)->where('jenis_dokumen', JenisDokumenApbdes::Murni)->firstOrFail();

        $this->assertTrue($apbdesLaluMurni->is_terkunci);
        $this->assertFalse($apbdesLaluMurni->is_aktif_publik);
        $this->assertFalse($apbdesLaluPerubahan->is_terkunci);
        $this->assertTrue($apbdesLaluPerubahan->is_aktif_publik);
        $this->assertFalse($apbdesAktifMurni->is_terkunci);
        $this->assertTrue($apbdesAktifMurni->is_aktif_publik);

        // Cek kecocokan SUM proyek terhadap dokumen APBDes sasaran tiap tahun
        $sumPaguLalu = (int) ProyekPembangunan::tahun($tahunLalu)->sum('pagu_anggaran');
        $sumRealLalu = (int) ProyekPembangunan::tahun($tahunLalu)->sum('realisasi_anggaran');
        $fisikLalu = $apbdesLaluPerubahan->rincian()->where('kode_pos', 'bidang_2_fisik')->firstOrFail();
        $this->assertSame($sumPaguLalu, $fisikLalu->pagu_anggaran);
        $this->assertSame($sumRealLalu, $fisikLalu->total_realisasi);

        $sumPaguAktif = (int) ProyekPembangunan::tahun($tahunAktif)->sum('pagu_anggaran');
        $sumRealAktif = (int) ProyekPembangunan::tahun($tahunAktif)->sum('realisasi_anggaran');
        $fisikAktif = $apbdesAktifMurni->rincian()->where('kode_pos', 'bidang_2_fisik')->firstOrFail();
        $this->assertSame($sumPaguAktif, $fisikAktif->pagu_anggaran);
        $this->assertSame($sumRealAktif, $fisikAktif->total_realisasi);

        // Cek selisih rumus keseimbangan anggaran == Rp 0 di seluruh dokumen APBDes
        foreach ([$apbdesLaluMurni, $apbdesLaluPerubahan, $apbdesAktifMurni] as $dokumen) {
            $rincian = $dokumen->rincian;
            $pendapatan = (int) $rincian->where('kelompok', KelompokPosApbdes::Pendapatan)->sum('pagu_anggaran');
            $belanja = (int) $rincian->where('kelompok', KelompokPosApbdes::Belanja)->sum('pagu_anggaran');
            $penerimaanPembiayaan = (int) $rincian->where('kelompok', KelompokPosApbdes::PembiayaanPenerimaan)->sum('pagu_anggaran');
            $pengeluaranPembiayaan = (int) $rincian->where('kelompok', KelompokPosApbdes::PembiayaanPengeluaran)->sum('pagu_anggaran');

            $selisih = ($pendapatan + $penerimaanPembiayaan - $pengeluaranPembiayaan) - $belanja;
            $this->assertSame(0, $selisih, "Keseimbangan APBDes tahun {$dokumen->tahun_anggaran} ({$dokumen->jenis_dokumen->value}) harus Rp 0.");
        }

        // 3. Verifikasi Keutuhan Urutan Nomor Surat Keluar, Surat Kematian, Antrean Online & Surat Masuk
        $nomorUrutKeluar = SuratKeluar::withTrashed()
            ->where('tahun_surat', $tahunAktif)
            ->whereNotNull('nomor_urut')
            ->orderBy('nomor_urut')
            ->pluck('nomor_urut')
            ->all();
        $this->assertSame([1, 2, 3, 4, 5], $nomorUrutKeluar);

        $suratKematian = SuratKeluar::where('jenis_surat', JenisSurat::Kematian)
            ->where('status', StatusSuratKeluar::Terbit)
            ->firstOrFail();
        $this->assertSame(StatusKependudukan::MeninggalDunia, $suratKematian->penduduk->status_kependudukan);

        $this->assertSame(1, SuratKeluar::where('status', StatusSuratKeluar::Dibatalkan)->count());
        $antreanOnline = SuratKeluar::menungguVerifikasi()->get();
        $this->assertCount(3, $antreanOnline);
        foreach ($antreanOnline as $antrean) {
            $this->assertStringStartsWith('628', (string) $antrean->no_wa_pemohon);
        }

        $nomorAgendaMasuk = SuratMasuk::withTrashed()
            ->where('tahun_agenda', $tahunAktif)
            ->orderBy('nomor_urut_agenda')
            ->pluck('nomor_urut_agenda')
            ->all();
        $this->assertSame([1, 2, 3], $nomorAgendaMasuk);

        // 4. Verifikasi Ketersediaan KK Sasaran Pemerataan Bansos (>= 4 KK aktif DTSEN Desil 1-4 tanpa bantuan)
        $kkSasaranPemerataan = KartuKeluarga::query()
            ->whereHas('penduduk', fn ($q) => $q->aktif()->prioritasDtsen())
            ->whereDoesntHave('penduduk.penerimaBansos', fn ($q) => $q->where('tahun_anggaran', $tahunAktif))
            ->get();

        $this->assertGreaterThanOrEqual(4, $kkSasaranPemerataan->count());

        // 5. Verifikasi Keberadaan Seluruh Berkas Placeholder di Public Disk & Private Disk (Anti-404)
        Storage::disk('public')->assertExists($profil->logo_desa_path);
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", Storage::disk('public')->get($profil->logo_desa_path));
        Storage::disk('public')->assertExists($profil->foto_kepala_desa_path);

        foreach (ProyekPembangunan::all() as $proyek) {
            foreach (['foto_0_path', 'foto_50_path', 'foto_100_path'] as $col) {
                if ($proyek->{$col} !== null) {
                    Storage::disk('public')->assertExists($proyek->{$col});
                }
            }
        }

        $this->assertSame(5, KegiatanDesa::count());
        $this->assertGreaterThan(0, KegiatanDesa::where('status_publikasi', StatusPublikasi::Draft)->count());
        foreach (KegiatanDesaFoto::all() as $foto) {
            Storage::disk('public')->assertExists($foto->foto_path);
        }

        $this->assertGreaterThanOrEqual(8, PerangkatDesa::count());
        foreach (PerangkatDesa::whereNotNull('foto_path')->get() as $perangkat) {
            Storage::disk('public')->assertExists($perangkat->foto_path);
        }

        $this->assertSame(5, DokumenPpid::count());
        foreach (DokumenPpid::all() as $ppid) {
            Storage::disk('public')->assertExists($ppid->file_pdf_path);
            $this->assertStringStartsWith('%PDF-', Storage::disk('public')->get($ppid->file_pdf_path));
        }

        foreach (SuratMasuk::all() as $sm) {
            $this->assertNotNull($sm->berkas_scan_path);
            Storage::disk('private')->assertExists($sm->berkas_scan_path);
            Storage::disk('public')->assertMissing($sm->berkas_scan_path);
        }
    }
}
