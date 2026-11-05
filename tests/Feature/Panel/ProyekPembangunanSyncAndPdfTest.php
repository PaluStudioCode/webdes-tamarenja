<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisDokumenApbdes;
use App\Enums\StatusProyek;
use App\Enums\SumberDanaProyek;
use App\Enums\UserRole;
use App\Models\Apbdes;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Models\User;
use App\Services\ApbdesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProyekPembangunanSyncAndPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected User $kepalaDesa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        ProfilDesa::create([
            'id' => 1,
            'nama_desa' => 'Tamarenja',
            'kode_desa_surat' => 'PEMDES-TMR',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'alamat_kantor' => 'Jl. Trans Sulawesi Desa Tamarenja',
            'nama_kepala_desa' => 'Moh. Rizal, S.AP',
            'nama_sekdes' => 'Siti Rahmawati, S.Sos',
            'daftar_dusun' => ['Dusun I', 'Dusun II', 'Dusun III'],
            'tahun_anggaran_aktif' => 2026,
        ]);

        $this->operator = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->kepalaDesa = User::factory()->create([
            'role' => UserRole::KepalaDesa,
            'is_active' => true,
        ]);
    }

    public function test_input_proyek_pertama_kali_menginisialisasi_otomatis_apbdes_murni_dan_sinkron_bidang_2_fisik(): void
    {
        $this->assertDatabaseMissing('apbdes', ['tahun_anggaran' => 2026]);

        $response = $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Pembangunan Rabat Beton Dusun I',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 180_000_000,
                'realisasi_anggaran' => 0,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'volume_fisik' => '250 Meter x 3 Meter',
                'waktu_pelaksanaan' => '90 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja',
                'latitude' => -0.583512,
                'longitude' => 119.793215,
                'progres_fisik' => 0,
            ]);

        $response->assertRedirect(route('panel.pembangunan.index', ['tahun' => 2026]));

        $proyek = ProyekPembangunan::firstOrFail();
        $this->assertSame(0, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::BelumDimulai, $proyek->status_proyek);

        // Pastikan APBDes Murni 2026 otomatis dibuat beserta 14 pos baku
        $murni = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', 2026)
            ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
            ->firstOrFail();

        $this->assertCount(14, $murni->rincian);

        $bidang2 = $murni->rincian->firstWhere('kode_pos', 'bidang_2_fisik');
        $this->assertNotNull($bidang2);
        $this->assertSame(180_000_000, $bidang2->pagu_anggaran);
        $this->assertSame(0, $bidang2->total_realisasi);
    }

    public function test_validasi_realisasi_melebihi_pagu_dan_koordinat_di_luar_batas_ditolak(): void
    {
        $response = $this->actingAs($this->operator)
            ->from(route('panel.pembangunan.create'))
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Drainase Dusun II',
                'lokasi_dusun' => 'Dusun II',
                'pagu_anggaran' => 100_000_000,
                'realisasi_anggaran' => 125_000_000, // Melebihi pagu
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'latitude' => -95.5, // Di luar -90..90
                'longitude' => 190.2, // Di luar -180..180
                'progres_fisik' => 0,
            ]);

        $response->assertRedirect(route('panel.pembangunan.create'));
        $response->assertSessionHasErrors(['realisasi_anggaran', 'latitude', 'longitude']);
    }

    public function test_seluruh_batas_validasi_bertahap_3_titik_foto_0_1_49_50_99_100_persen_dan_status_otomatis(): void
    {
        // 1. Batas 0%: foto opsional -> berhasil, status = belum_dimulai
        $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Talud Penahan Tanah Dusun III',
                'lokasi_dusun' => 'Dusun III',
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 0,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        $proyek = ProyekPembangunan::firstOrFail();
        $this->assertSame(StatusProyek::BelumDimulai, $proyek->status_proyek);

        // 2. Batas 1% tanpa foto_0 -> gagal validasi foto_0
        $this->actingAs($this->operator)
            ->from(route('panel.pembangunan.edit', $proyek))
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 20_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 1,
            ])
            ->assertSessionHasErrors(['foto_0']);

        // 3. Batas 1% dengan foto_0 -> berhasil, status = sedang_dikerjakan
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 20_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 1,
                'foto_0' => UploadedFile::fake()->image('kondisi-0.jpg', 1200, 900),
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(1, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::SedangDikerjakan, $proyek->status_proyek);
        $this->assertNotNull($proyek->foto_0_path);
        Storage::disk('public')->assertExists($proyek->foto_0_path);

        // 4. Batas 49% (hanya foto_0 yang sudah tersimpan, tanpa foto_50) -> berhasil
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 90_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 49,
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(49, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::SedangDikerjakan, $proyek->status_proyek);

        // 5. Batas 50% tanpa foto_50 -> gagal validasi foto_50
        $this->actingAs($this->operator)
            ->from(route('panel.pembangunan.edit', $proyek))
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 100_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 50,
            ])
            ->assertSessionHasErrors(['foto_50']);

        // 6. Batas 50% dengan foto_50 -> berhasil, status = sedang_dikerjakan
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 100_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 50,
                'foto_50' => UploadedFile::fake()->image('progres-50.jpg', 1200, 900),
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(50, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::SedangDikerjakan, $proyek->status_proyek);
        $this->assertNotNull($proyek->foto_50_path);
        Storage::disk('public')->assertExists($proyek->foto_50_path);

        // 7. Batas 99% (memiliki foto_0 & foto_50, tanpa foto_100) -> berhasil
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 190_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 99,
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(99, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::SedangDikerjakan, $proyek->status_proyek);

        // 8. Batas 100% tanpa foto_100 -> gagal validasi foto_100
        $this->actingAs($this->operator)
            ->from(route('panel.pembangunan.edit', $proyek))
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 200_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 100,
            ])
            ->assertSessionHasErrors(['foto_100']);

        // 9. Batas 100% dengan foto_100 -> berhasil, status = selesai
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 200_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 100,
                'foto_100' => UploadedFile::fake()->image('selesai-100.jpg', 1200, 900),
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(100, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::Selesai, $proyek->status_proyek);
        $this->assertNotNull($proyek->foto_100_path);
        Storage::disk('public')->assertExists($proyek->foto_100_path);
    }

    public function test_sinkronisasi_hanya_memperbarui_perubahan_tanpa_merusak_histori_murni_yang_terkunci(): void
    {
        // 1. Tambah Proyek A saat baru ada Murni
        $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Proyek A (Masa Murni)',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 150_000_000,
                'realisasi_anggaran' => 75_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        $proyekA = ProyekPembangunan::firstOrFail();

        // 2. Buat APBDes Perubahan (Clone & Freeze Murni)
        $apbdesService = app(ApbdesService::class);
        $perubahan = $apbdesService->createPerubahanCloneAndFreeze(2026, $this->operator->id, 'Perdes No 5/2026');

        $murni = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', 2026)
            ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
            ->firstOrFail();

        $this->assertTrue($murni->is_terkunci);
        $this->assertSame(150_000_000, $murni->rincian->firstWhere('kode_pos', 'bidang_2_fisik')->pagu_anggaran);
        $this->assertSame(75_000_000, $murni->rincian->firstWhere('kode_pos', 'bidang_2_fisik')->total_realisasi);

        // 3. Tambah Proyek B dan perbarui Proyek A setelah Perubahan ada
        $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Proyek B (Masa Perubahan)',
                'lokasi_dusun' => 'Dusun II',
                'pagu_anggaran' => 100_000_000,
                'realisasi_anggaran' => 40_000_000,
                'sumber_dana' => SumberDanaProyek::Add->value,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyekA), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Proyek A (Masa Murni - Update Realisasi)',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 150_000_000,
                'realisasi_anggaran' => 150_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        // Pastikan Murni tetap utuh 150 jt / 75 jt (tidak tersentuh)
        $murni->refresh();
        $murniBidang2 = $murni->rincian()->where('kode_pos', 'bidang_2_fisik')->firstOrFail();
        $this->assertSame(150_000_000, $murniBidang2->pagu_anggaran);
        $this->assertSame(75_000_000, $murniBidang2->total_realisasi);

        // Pastikan Perubahan terupdate menjadi 250 jt / 190 jt
        $perubahanBidang2 = $perubahan->rincian()->where('kode_pos', 'bidang_2_fisik')->firstOrFail();
        $this->assertSame(250_000_000, $perubahanBidang2->pagu_anggaran);
        $this->assertSame(190_000_000, $perubahanBidang2->total_realisasi);

        // 4. Soft-delete Proyek A -> Perubahan harus berkurang menjadi 100 jt / 40 jt, Murni tetap 150 jt / 75 jt
        $this->actingAs($this->operator)
            ->delete(route('panel.pembangunan.destroy', $proyekA))
            ->assertRedirect();

        $this->assertSoftDeleted('proyek_pembangunan', ['id' => $proyekA->id]);

        $murniBidang2->refresh();
        $perubahanBidang2->refresh();

        $this->assertSame(150_000_000, $murniBidang2->pagu_anggaran);
        $this->assertSame(75_000_000, $murniBidang2->total_realisasi);
        $this->assertSame(100_000_000, $perubahanBidang2->pagu_anggaran);
        $this->assertSame(40_000_000, $perubahanBidang2->total_realisasi);
    }

    public function test_penghapusan_berkas_foto_lama_di_disk_saat_diganti_foto_baru(): void
    {
        $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Posyandu Dusun I',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 95_000_000,
                'realisasi_anggaran' => 30_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 20,
                'foto_0' => UploadedFile::fake()->image('foto-0-lama.jpg', 800, 600),
            ])
            ->assertRedirect();

        $proyek = ProyekPembangunan::firstOrFail();
        $oldPath = $proyek->foto_0_path;
        $this->assertNotNull($oldPath);
        Storage::disk('public')->assertExists($oldPath);

        // Ganti foto_0 dengan file baru
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Posyandu Dusun I',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 95_000_000,
                'realisasi_anggaran' => 30_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 20,
                'foto_0' => UploadedFile::fake()->image('foto-0-baru.jpg', 1000, 750),
            ])
            ->assertRedirect();

        $proyek->refresh();
        $this->assertNotSame($oldPath, $proyek->foto_0_path);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($proyek->foto_0_path);
    }

    public function test_cetak_pdf_f4_landscape_berhasil_bagi_operator_dan_kepala_desa_serta_tolak_tulis_bagi_kepala_desa(): void
    {
        ProyekPembangunan::factory()->create([
            'tahun_anggaran' => 2026,
            'nama_kegiatan' => 'Jembatan Tani Dusun II',
            'pagu_anggaran' => 120_000_000,
            'realisasi_anggaran' => 60_000_000,
            'progres_fisik' => 50,
            'status_proyek' => StatusProyek::SedangDikerjakan,
            'created_by' => $this->operator->id,
        ]);

        // Operator dapat melihat index & mengunduh PDF F4 Landscape
        $this->actingAs($this->operator)
            ->get(route('panel.pembangunan.index'))
            ->assertOk();

        $pdfOperator = $this->actingAs($this->operator)
            ->get(route('panel.pembangunan.cetak-pdf', ['tahun' => 2026]));
        $pdfOperator->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfOperator->headers->get('content-type'));

        // Kepala Desa dapat melihat index & mengunduh PDF F4 Landscape
        $this->actingAs($this->kepalaDesa)
            ->get(route('panel.pembangunan.index'))
            ->assertOk();

        $pdfKades = $this->actingAs($this->kepalaDesa)
            ->get(route('panel.pembangunan.cetak-pdf', ['tahun' => 2026]));
        $pdfKades->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfKades->headers->get('content-type'));

        // Kepala Desa ditolak 403 untuk tambah/simpan proyek
        $this->actingAs($this->kepalaDesa)
            ->get(route('panel.pembangunan.create'))
            ->assertForbidden();

        $this->actingAs($this->kepalaDesa)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Proyek Ilegal',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 50_000_000,
                'realisasi_anggaran' => 0,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 0,
            ])
            ->assertForbidden();
    }
}
