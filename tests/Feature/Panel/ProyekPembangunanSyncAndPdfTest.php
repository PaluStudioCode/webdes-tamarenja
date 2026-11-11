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

    public function test_input_proyek_berhasil_disimpan_secara_mandiri_sebagai_katalog_dokumentasi(): void
    {
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
        $this->assertSame('Pembangunan Rabat Beton Dusun I', $proyek->nama_kegiatan);
        $this->assertSame(0, $proyek->progres_fisik);
        $this->assertSame(StatusProyek::BelumDimulai, $proyek->status_proyek);
        $this->assertSame(180_000_000, (int) $proyek->pagu_anggaran);
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

    public function test_status_dan_progres_otomatis_mengikuti_unggahan_tiga_titik_foto(): void
    {
        // 1. Proyek awal dengan foto_0 -> progres = 0, status = belum_dimulai
        $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Talud Penahan Tanah Dusun III',
                'lokasi_dusun' => 'Dusun III',
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 0,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'foto_0' => UploadedFile::fake()->image('kondisi-0.jpg', 1200, 900),
            ])
            ->assertRedirect();

        $proyek = ProyekPembangunan::firstOrFail();
        $this->assertSame(0, (int) $proyek->progres_fisik);
        $this->assertSame(StatusProyek::BelumDimulai, $proyek->status_proyek);
        $this->assertNotNull($proyek->foto_0_path);
        Storage::disk('public')->assertExists($proyek->foto_0_path);

        // 2. Unggah foto_50 -> status otomatis = sedang_dikerjakan (50%)
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 100_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'foto_50' => UploadedFile::fake()->image('progres-50.jpg', 1200, 900),
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(50, (int) $proyek->progres_fisik);
        $this->assertSame(StatusProyek::SedangDikerjakan, $proyek->status_proyek);
        $this->assertNotNull($proyek->foto_50_path);
        Storage::disk('public')->assertExists($proyek->foto_50_path);

        // 3. Unggah foto_100 -> status otomatis = selesai (100%)
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyek), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => $proyek->nama_kegiatan,
                'lokasi_dusun' => $proyek->lokasi_dusun,
                'pagu_anggaran' => 200_000_000,
                'realisasi_anggaran' => 200_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'foto_100' => UploadedFile::fake()->image('selesai-100.jpg', 1200, 900),
            ])
            ->assertSessionHasNoErrors();

        $proyek->refresh();
        $this->assertSame(100, (int) $proyek->progres_fisik);
        $this->assertSame(StatusProyek::Selesai, $proyek->status_proyek);
        $this->assertNotNull($proyek->foto_100_path);
        Storage::disk('public')->assertExists($proyek->foto_100_path);
    }

    public function test_proyek_crud_berjalan_mandiri_tanpa_mengubah_data_apbdes(): void
    {
        // 1. Siapkan APBDes Murni 2026 dengan nilai manual
        $apbdesService = app(ApbdesService::class);
        $murni = $apbdesService->initializeMurni(2026, $this->operator->id, 'Perdes No 1/2026');
        $murniBidang2 = $murni->rincian()->where('kode_pos', 'bidang_2_fisik')->firstOrFail();
        $murniBidang2->update([
            'pagu_anggaran' => 500_000_000,
            'realisasi_tahap_1' => 200_000_000,
            'total_realisasi' => 200_000_000,
        ]);

        // 2. Tambah Proyek A di katalog pembangunan
        $this->actingAs($this->operator)
            ->post(route('panel.pembangunan.store'), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Proyek A (Katalog Dokumentasi)',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 150_000_000,
                'realisasi_anggaran' => 75_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        $proyekA = ProyekPembangunan::firstOrFail();

        // Nilai APBDes Bidang 2 tetap 500jt / 200jt (tidak tertimpa proyek)
        $murniBidang2->refresh();
        $this->assertSame(500_000_000, $murniBidang2->pagu_anggaran);
        $this->assertSame(200_000_000, $murniBidang2->total_realisasi);

        // 3. Update Proyek A
        $this->actingAs($this->operator)
            ->put(route('panel.pembangunan.update', $proyekA), [
                'tahun_anggaran' => 2026,
                'nama_kegiatan' => 'Proyek A (Update Realisasi)',
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 150_000_000,
                'realisasi_anggaran' => 150_000_000,
                'sumber_dana' => SumberDanaProyek::Dds->value,
                'progres_fisik' => 0,
            ])
            ->assertRedirect();

        $murniBidang2->refresh();
        $this->assertSame(500_000_000, $murniBidang2->pagu_anggaran);
        $this->assertSame(200_000_000, $murniBidang2->total_realisasi);

        // 4. Hapus Proyek A
        $this->actingAs($this->operator)
            ->delete(route('panel.pembangunan.destroy', $proyekA))
            ->assertRedirect();

        $this->assertSoftDeleted('proyek_pembangunan', ['id' => $proyekA->id]);

        $murniBidang2->refresh();
        $this->assertSame(500_000_000, $murniBidang2->pagu_anggaran);
        $this->assertSame(200_000_000, $murniBidang2->total_realisasi);
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
