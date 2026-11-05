<?php

namespace Tests\Feature\Publik;

use App\Enums\JenisBansos;
use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Http\Requests\Publik\SubmitPengajuanSuratOnlineRequest;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\SuratKeluar;
use App\Models\User;
use Database\Seeders\EssentialDesaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LayananMandiriAndVerifikasiQrTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EssentialDesaSeeder::class);
        $this->operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();
    }

    public function test_anti_enumeration_returns_identical_error_message_for_unregistered_nik_wrong_birthdate_and_deceased_resident(): void
    {
        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun I']);

        $wargaAktif = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203181111110001',
            'nama_lengkap' => 'Budi Santoso',
            'tanggal_lahir' => '1990-05-15',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $wargaWafat = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203181111110002',
            'nama_lengkap' => 'Almarhum Warga',
            'tanggal_lahir' => '1965-08-20',
            'status_kependudukan' => StatusKependudukan::MeninggalDunia,
        ]);

        $basePayload = [
            'jenis_surat' => JenisSurat::Domisili->value,
            'no_wa_pemohon' => '081234567890',
            'keperluan' => 'Administrasi Perbankan',
            'data_khusus' => [
                'sejak_tahun' => '2015',
                'status_tempat_tinggal' => 'Milik Sendiri',
            ],
        ];

        // 1. NIK tidak terdaftar
        $resUnregistered = $this->from('/layanan-mandiri')->post('/layanan-mandiri/ajukan-surat', array_merge($basePayload, [
            'nik' => '7203189999999999',
            'tanggal_lahir' => '1990-05-15',
        ]));
        $resUnregistered->assertRedirect('/layanan-mandiri');
        $errUnregistered = session('errors')->first('nik');

        // 2. NIK terdaftar tetapi Tanggal Lahir salah
        $resWrongDob = $this->from('/layanan-mandiri')->post('/layanan-mandiri/ajukan-surat', array_merge($basePayload, [
            'nik' => $wargaAktif->nik,
            'tanggal_lahir' => '1990-05-16',
        ]));
        $resWrongDob->assertRedirect('/layanan-mandiri');
        $errWrongDob = session('errors')->first('nik');

        // 3. NIK & Tanggal Lahir cocok tetapi berstatus Meninggal Dunia
        $resDeceased = $this->from('/layanan-mandiri')->post('/layanan-mandiri/ajukan-surat', array_merge($basePayload, [
            'nik' => $wargaWafat->nik,
            'tanggal_lahir' => '1965-08-20',
        ]));
        $resDeceased->assertRedirect('/layanan-mandiri');
        $errDeceased = session('errors')->first('nik');

        $expectedMsg = SubmitPengajuanSuratOnlineRequest::ANTI_ENUMERATION_MESSAGE;
        $this->assertSame($expectedMsg, $errUnregistered);
        $this->assertSame($errUnregistered, $errWrongDob);
        $this->assertSame($errWrongDob, $errDeceased);

        // Verifikasi juga kesamaan pesan anti-enumerasi pada endpoint Cek Bansos Mandiri
        $bansosUnregistered = $this->from('/layanan-mandiri')->post('/layanan-mandiri/cek-bansos', [
            'nik' => '7203189999999999',
            'tanggal_lahir' => '1990-05-15',
        ]);
        $bansosUnregistered->assertSessionHasErrors(['nik' => $expectedMsg]);

        $bansosDeceased = $this->from('/layanan-mandiri')->post('/layanan-mandiri/cek-bansos', [
            'nik' => $wargaWafat->nik,
            'tanggal_lahir' => '1965-08-20',
        ]);
        $bansosDeceased->assertSessionHasErrors(['nik' => $expectedMsg]);
    }

    public function test_idor_prevention_ignores_browser_penduduk_id_normalizes_whatsapp_and_blocks_duplicate_pending_submission(): void
    {
        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun II']);

        $wargaPemohon = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203182222220001',
            'nama_lengkap' => 'Siti Aminah',
            'tanggal_lahir' => '1994-11-10',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $wargaLain = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203182222220002',
            'nama_lengkap' => 'Target IDOR',
            'tanggal_lahir' => '1988-02-02',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $payload = [
            'penduduk_id' => $wargaLain->id, // Percobaan injeksi IDOR dari browser
            'jenis_surat' => JenisSurat::Sku->value,
            'nik' => $wargaPemohon->nik,
            'tanggal_lahir' => '1994-11-10',
            'no_wa_pemohon' => '0812-3456-7890',
            'keperluan' => 'Pengajuan KUR Mikro',
            'data_khusus' => [
                'nama_usaha' => 'Warung Sembako Berkah',
                'jenis_usaha' => 'Perdagangan',
                'bidang_usaha' => 'Sembako Harian',
                'lama_usaha' => '4 Tahun',
                'alamat_usaha' => 'Dusun II Desa Tamarenja',
            ],
        ];

        $response = $this->post('/layanan-mandiri/ajukan-surat', $payload);
        $response->assertRedirect('/layanan-mandiri');
        $response->assertSessionHas('konfirmasi_pengajuan');

        $surat = SuratKeluar::query()->firstOrFail();

        // Pastikan penduduk_id yang tersimpan murni milik $wargaPemohon (bukan $wargaLain)
        $this->assertSame($wargaPemohon->id, $surat->penduduk_id);
        $this->assertNotSame($wargaLain->id, $surat->penduduk_id);
        $this->assertSame('6281234567890', $surat->no_wa_pemohon);
        $this->assertSame(StatusSuratKeluar::MenungguVerifikasi, $surat->status);
        $this->assertSame('online', $surat->sumber_pengajuan);

        // Pengajuan kedua dengan jenis surat yang sama saat masih menunggu_verifikasi wajib ditolak
        $duplicateResponse = $this->from('/layanan-mandiri')->post('/layanan-mandiri/ajukan-surat', $payload);
        $duplicateResponse->assertRedirect('/layanan-mandiri');
        $duplicateResponse->assertSessionHasErrors(['jenis_surat']);
        $this->assertSame(1, SuratKeluar::query()->count());
    }

    public function test_cek_bansos_mandiri_masks_resident_name_excludes_nik_and_excludes_other_family_members(): void
    {
        $kk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203185555550001',
            'dusun' => 'Dusun III',
        ]);

        $kepalaKeluarga = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203185555550002',
            'nama_lengkap' => 'Budi Santoso',
            'tanggal_lahir' => '1982-04-12',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $anggotaLain = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203185555550003',
            'nama_lengkap' => 'Rina Wati',
            'tanggal_lahir' => '1986-09-25',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        // Riwayat bansos milik Budi Santoso pada 2 tahun berbeda
        PenerimaBansos::query()->create([
            'penduduk_id' => $kepalaKeluarga->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'bentuk_nominal' => 'Rp 300.000 / Bulan',
            'tahun_anggaran' => 2025,
            'tahap_penyaluran' => 'Tahap I',
            'status_kesejahteraan_saat_terima' => 'dtsen_desil_1',
            'status_kepesertaan' => StatusPesertaBansos::Graduasi,
            'created_by' => $this->operator->id,
        ]);

        PenerimaBansos::query()->create([
            'penduduk_id' => $kepalaKeluarga->id,
            'jenis_bantuan' => JenisBansos::Pkh,
            'bentuk_nominal' => 'Rp 750.000 / Tahap',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap II',
            'status_kesejahteraan_saat_terima' => 'dtsen_desil_1',
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // Bansos milik anggota keluarga lain (TIDAK BOLEH bocor saat Budi Santoso melakukan cek mandiri)
        PenerimaBansos::query()->create([
            'penduduk_id' => $anggotaLain->id,
            'jenis_bantuan' => JenisBansos::Bpnt,
            'bentuk_nominal' => 'Sembako Pangan',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'status_kesejahteraan_saat_terima' => 'dtsen_desil_2',
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        $response = $this->post('/layanan-mandiri/cek-bansos', [
            'nik' => '7203185555550002',
            'tanggal_lahir' => '1982-04-12',
        ]);

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LayananMandiri/Index')
                ->where('active_tab', 'cek_bansos')
                ->where('hasil_bansos.nama_tersamarkan', 'B*** S******')
                ->where('hasil_bansos.dusun', 'Dusun III')
                ->has('hasil_bansos.riwayat', 2)
                // Terurut dari tahun_anggaran terbaru (2026 dulu baru 2025)
                ->where('hasil_bansos.riwayat.0.tahun_anggaran', 2026)
                ->where('hasil_bansos.riwayat.0.jenis_bantuan', 'pkh')
                ->where('hasil_bansos.riwayat.1.tahun_anggaran', 2025)
                ->where('hasil_bansos.riwayat.1.jenis_bantuan', 'blt_dd')
            );

        $jsonPayload = json_encode($response->viewData('page')['props'] ?? []);
        $this->assertStringNotContainsString('7203185555550002', $jsonPayload, '16 digit NIK tidak boleh dikirim kembali ke layar hasil Cek Bansos');
        $this->assertStringNotContainsString('7203185555550001', $jsonPayload, 'Nomor KK tidak boleh bocor');
        $this->assertStringNotContainsString('Budi Santoso', $jsonPayload, 'Nama lengkap utuh tidak boleh bocor');
        $this->assertStringNotContainsString('Rina Wati', $jsonPayload, 'Anggota keluarga lain tidak boleh bocor');
    }

    public function test_verifikasi_qr_surat_handles_terbit_dibatalkan_and_tidak_terdaftar_scenarios_accurately(): void
    {
        $kk = KartuKeluarga::factory()->create(['dusun' => 'Dusun I']);
        $warga = Penduduk::factory()->create([
            'kartu_keluarga_id' => $kk->id,
            'nik' => '7203187777770001',
            'nama_lengkap' => 'M. Ridwan',
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $tokenTerbit = (string) Str::uuid();
        SuratKeluar::factory()->create([
            'qr_token' => $tokenTerbit,
            'penduduk_id' => $warga->id,
            'jenis_surat' => JenisSurat::Domisili,
            'nomor_surat' => '470 / 005 / DS-TMR / IV / 2026',
            'tanggal_surat' => '2026-04-15',
            'nama_penandatangan' => 'Moh. Rizal, S.AP',
            'jabatan_penandatangan' => 'Kepala Desa Tamarenja',
            'status' => StatusSuratKeluar::Terbit,
            'processed_by' => $this->operator->id,
        ]);

        $tokenDibatalkan = (string) Str::uuid();
        SuratKeluar::factory()->dibatalkan()->create([
            'qr_token' => $tokenDibatalkan,
            'penduduk_id' => $warga->id,
            'jenis_surat' => JenisSurat::Sku,
            'nomor_surat' => '503 / 006 / DS-TMR / IV / 2026',
            'tanggal_surat' => '2026-04-16',
            'alasan_pembatalan' => 'Kekeliruan penulisan nama usaha pemohon',
            'processed_by' => $this->operator->id,
        ]);

        // 1. Skenario Terbit: Banner SURAT ASLI & 5 atribut resmi dengan nama tersamarkan
        $resTerbit = $this->get("/layanan-mandiri/verifikasi-surat/{$tokenTerbit}");
        $resTerbit->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LayananMandiri/VerifikasiSurat')
                ->where('status_verifikasi', 'terbit')
                ->where('banner_judul', 'SURAT ASLI & TERDAFTAR DI BUKU AGENDA DESA')
                ->where('dokumen.nomor_surat', '470 / 005 / DS-TMR / IV / 2026')
                ->where('dokumen.jenis_surat', JenisSurat::Domisili->label())
                ->where('dokumen.tanggal_terbit', '2026-04-15')
                ->where('dokumen.nama_warga_tersamarkan', 'M*** R*****')
                ->where('dokumen.pejabat_penandatangan', 'Kepala Desa Tamarenja — Moh. Rizal, S.AP')
            );

        $jsonTerbit = json_encode($resTerbit->viewData('page')['props'] ?? []);
        $this->assertStringNotContainsString('7203187777770001', $jsonTerbit);
        $this->assertStringNotContainsString('M. Ridwan', $jsonTerbit);

        // 2. Skenario Dibatalkan: Peringatan Surat Dibatalkan
        $this->get("/layanan-mandiri/verifikasi-surat/{$tokenDibatalkan}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LayananMandiri/VerifikasiSurat')
                ->where('status_verifikasi', 'dibatalkan')
                ->where('dokumen.alasan_pembatalan', 'Kekeliruan penulisan nama usaha pemohon')
            );

        // 3. Skenario Token Tidak Terdaftar
        $tokenAsing = (string) Str::uuid();
        $this->get("/layanan-mandiri/verifikasi-surat/{$tokenAsing}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('LayananMandiri/VerifikasiSurat')
                ->where('status_verifikasi', 'tidak_terdaftar')
                ->where('dokumen', null)
            );
    }

    public function test_layanan_mandiri_post_endpoints_enforce_throttle_10_requests_per_minute(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $res = $this->from('/layanan-mandiri')->post('/layanan-mandiri/cek-bansos', [
                'nik' => '7203180000000000',
                'tanggal_lahir' => '1990-01-01',
            ]);
            $this->assertNotEquals(429, $res->getStatusCode());
        }

        // Permintaan ke-11 dalam 1 menit wajib diblokir 429 Too Many Requests
        $eleventh = $this->from('/layanan-mandiri')->post('/layanan-mandiri/cek-bansos', [
            'nik' => '7203180000000000',
            'tanggal_lahir' => '1990-01-01',
        ]);
        $eleventh->assertStatus(429);
    }
}
