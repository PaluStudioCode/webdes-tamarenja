<?php

namespace Tests\Feature;

use App\Enums\JenisDokumenApbdes;
use App\Enums\KelompokPosApbdes;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusProyek;
use App\Enums\StatusPublikasi;
use App\Enums\StatusSuratKeluar;
use App\Enums\UserRole;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
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
use App\Models\User;
use App\Support\ApbdesPosCatalog;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelAndSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_katalog_14_kode_pos_apbdes_matches_prd_specification(): void
    {
        $katalog = ApbdesRincian::katalogPosBaku();

        $this->assertCount(14, $katalog);
        $this->assertSame([
            'dds',
            'add',
            'pades',
            'bhprd',
            'bankeu',
            'pendapatan_lain',
            'bidang_1',
            'bidang_2_fisik',
            'bidang_2_non_fisik',
            'bidang_3',
            'bidang_4',
            'bidang_5',
            'silpa',
            'penyertaan_modal',
        ], ApbdesPosCatalog::codes());

        $otomatis = array_values(array_filter($katalog, fn (array $p) => $p['is_otomatis_modul_1'] === true));
        $this->assertCount(1, $otomatis);
        $this->assertSame('bidang_2_fisik', $otomatis[0]['kode_pos']);
        $this->assertSame(8, $otomatis[0]['urutan']);
    }

    public function test_essential_desa_seeder_is_idempotent_on_repeated_runs(): void
    {
        // Jalankan 3 kali berturut-turut (termasuk via DatabaseSeeder di env testing)
        $this->seed(DatabaseSeeder::class);
        $this->seed(EssentialDesaSeeder::class);
        $this->seed(EssentialDesaSeeder::class);

        $this->assertSame(2, User::count());

        $operator = User::where('email', 'operator@desa.id')->firstOrFail();
        $kades = User::where('email', 'kades@desa.id')->firstOrFail();

        $this->assertSame(UserRole::Operator, $operator->role);
        $this->assertTrue($operator->isOperator());
        $this->assertFalse($operator->isKepalaDesa());
        $this->assertTrue($operator->is_active);

        $this->assertSame(UserRole::KepalaDesa, $kades->role);
        $this->assertTrue($kades->isKepalaDesa());
        $this->assertTrue($kades->is_active);

        $this->assertSame(1, ProfilDesa::count());
        $profil = ProfilDesa::singleton();
        $this->assertNotNull($profil);
        $this->assertIsArray($profil->daftar_dusun);
        $this->assertNotEmpty($profil->daftar_dusun);
        $this->assertIsArray($profil->misi);
        $this->assertNotEmpty($profil->misi);
        $this->assertSame('DS-TMR', $profil->kode_desa_surat);

        $this->assertSame(1, Apbdes::count());
        $apbdes = Apbdes::aktif()->tahun($profil->tahun_anggaran_aktif)->firstOrFail();
        $this->assertSame(JenisDokumenApbdes::Murni, $apbdes->jenis_dokumen);
        $this->assertFalse($apbdes->is_terkunci);

        $this->assertSame(14, ApbdesRincian::count());
        $this->assertCount(14, $apbdes->rincian);

        $posFisik = $apbdes->rincian->firstWhere('kode_pos', 'bidang_2_fisik');
        $this->assertNotNull($posFisik);
        $this->assertSame(KelompokPosApbdes::Belanja, $posFisik->kelompok);
        $this->assertTrue($posFisik->is_otomatis_modul_1);
        $this->assertSame(0, $posFisik->pagu_anggaran);
        $this->assertSame(0, $posFisik->total_realisasi);
    }

    public function test_all_eloquent_models_factories_relations_soft_deletes_and_scopes(): void
    {
        $operator = User::factory()->operator()->create();

        // Kependudukan: KK + Penduduk berbagai state & scopes
        $kkAktif = KartuKeluarga::factory()->create();
        $kepala = Penduduk::factory()->kepalaKeluarga()->desil1()->aktif()->create([
            'kartu_keluarga_id' => $kkAktif->id,
        ]);
        $anggotaPindah = Penduduk::factory()->pindah()->desil2()->create([
            'kartu_keluarga_id' => $kkAktif->id,
        ]);

        $kkNonAktif = KartuKeluarga::factory()->create();
        $wargaWafat = Penduduk::factory()->meninggal()->desil34()->create([
            'kartu_keluarga_id' => $kkNonAktif->id,
        ]);

        $this->assertCount(2, $kkAktif->penduduk);
        $this->assertSame($kepala->id, $kkAktif->kepalaKeluarga->id);
        $this->assertSame(1, KartuKeluarga::aktif()->count());
        $this->assertSame(1, Penduduk::aktif()->count());
        $this->assertSame(1, Penduduk::aktif()->prioritasDtsen()->count());
        $this->assertSame(StatusKependudukan::MeninggalDunia, $wargaWafat->status_kependudukan);
        $this->assertSame(StatusKesejahteraan::DtsenDesil34, $wargaWafat->status_kesejahteraan);

        // SoftDeletes + withTrashed + restore pada Penduduk
        $anggotaPindah->delete();
        $this->assertSoftDeleted('penduduk', ['id' => $anggotaPindah->id]);
        $this->assertNotNull(Penduduk::withTrashed()->find($anggotaPindah->id));
        $anggotaPindah->restore();
        $this->assertNull($anggotaPindah->fresh()->deleted_at);

        // Modul 1: Proyek Pembangunan
        $proyek = ProyekPembangunan::factory()->selesai()->create([
            'tahun_anggaran' => 2026,
            'pagu_anggaran' => 200_000_000,
            'realisasi_anggaran' => 150_000_000,
            'created_by' => $operator->id,
        ]);
        $this->assertSame(StatusProyek::Selesai, $proyek->status_proyek);
        $this->assertSame(75.0, $proyek->persentaseSerapan());
        $this->assertSame($operator->id, $proyek->pembuat->id);
        $this->assertSame(1, ProyekPembangunan::tahun(2026)->count());

        // Modul 3: Surat Keluar & Surat Masuk
        $suratTerbit = SuratKeluar::factory()->create([
            'penduduk_id' => $kepala->id,
            'tahun_surat' => 2026,
            'processed_by' => $operator->id,
        ]);
        $suratOnline = SuratKeluar::factory()->menungguVerifikasi()->create([
            'penduduk_id' => $kepala->id,
        ]);
        $suratBatal = SuratKeluar::factory()->dibatalkan()->create([
            'penduduk_id' => $kepala->id,
            'tahun_surat' => 2026,
            'processed_by' => $operator->id,
        ]);

        $this->assertSame(1, SuratKeluar::terbit()->count());
        $this->assertSame(1, SuratKeluar::menungguVerifikasi()->count());
        $this->assertSame(StatusSuratKeluar::Dibatalkan, $suratBatal->status);
        $this->assertSame($kepala->id, $suratTerbit->penduduk->id);
        $this->assertSame($operator->id, $suratTerbit->pemroses->id);
        $this->assertCount(3, $kepala->suratKeluar);
        $this->assertNull($suratOnline->qr_token);

        $suratMasuk = SuratMasuk::factory()->create([
            'tahun_agenda' => 2026,
            'created_by' => $operator->id,
        ]);
        $this->assertSame(1, SuratMasuk::tahun(2026)->count());
        $this->assertSame($operator->id, $suratMasuk->pembuat->id);

        // Modul 4: Bansos & Kegiatan Desa
        $bansosAktif = PenerimaBansos::factory()->create([
            'penduduk_id' => $kepala->id,
            'tahun_anggaran' => 2026,
            'created_by' => $operator->id,
        ]);
        $bansosGraduasi = PenerimaBansos::factory()->graduasi()->create([
            'penduduk_id' => $kepala->id,
            'tahun_anggaran' => 2025,
            'created_by' => $operator->id,
        ]);

        $this->assertSame(1, PenerimaBansos::aktif()->count());
        $this->assertSame(1, PenerimaBansos::tahun(2026)->count());
        $this->assertSame(StatusPesertaBansos::Graduasi, $bansosGraduasi->status_kepesertaan);
        $this->assertSame($kepala->id, $bansosAktif->penduduk->id);

        $kegiatanPublik = KegiatanDesa::factory()->published()->create([
            'tanggal_kegiatan' => '2026-10-10',
            'created_by' => $operator->id,
        ]);
        KegiatanDesaFoto::create([
            'kegiatan_desa_id' => $kegiatanPublik->id,
            'foto_path' => 'kegiatan/2026/foto-sampul.webp',
            'urutan' => 1,
        ]);
        KegiatanDesa::factory()->draft()->create([
            'tanggal_kegiatan' => '2026-10-12',
            'created_by' => $operator->id,
        ]);

        $this->assertSame(1, KegiatanDesa::published()->count());
        $this->assertSame(2, KegiatanDesa::tahun(2026)->count());
        $this->assertSame(StatusPublikasi::Published, $kegiatanPublik->status_publikasi);
        $this->assertCount(1, $kegiatanPublik->foto);

        // SOTK & PPID
        PerangkatDesa::factory()->bpd()->create(['is_aktif' => true]);
        PerangkatDesa::factory()->create(['is_aktif' => false]);
        $this->assertSame(1, PerangkatDesa::aktif()->count());

        $ppidPublik = DokumenPpid::factory()->create([
            'tahun_dokumen' => 2026,
            'uploaded_by' => $operator->id,
        ]);
        DokumenPpid::factory()->nonPublik()->create([
            'tahun_dokumen' => 2026,
            'uploaded_by' => $operator->id,
        ]);

        $this->assertSame(1, DokumenPpid::published()->count());
        $this->assertSame(2, DokumenPpid::tahun(2026)->count());
        $this->assertSame($operator->id, $ppidPublik->pengunggah->id);

        // Relasi balik dari User
        $this->assertCount(1, $operator->proyekPembangunan);
        $this->assertCount(2, $operator->suratKeluarDiproses);
        $this->assertCount(1, $operator->suratMasuk);
        $this->assertCount(2, $operator->penerimaBansos);
        $this->assertCount(2, $operator->kegiatanDesa);
        $this->assertCount(2, $operator->dokumenPpid);
    }
}
