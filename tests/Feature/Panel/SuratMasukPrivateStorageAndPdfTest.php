<?php

namespace Tests\Feature\Panel;

use App\Enums\UserRole;
use App\Models\ProfilDesa;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SuratMasukPrivateStorageAndPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected User $kepalaDesa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
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

    public function test_urutan_otomatis_nomor_urut_agenda_lintas_tahun_dengan_with_trashed(): void
    {
        // Buat 1 surat masuk di tahun 2025 dengan nomor urut 8 lalu soft-delete
        $deleted2025 = SuratMasuk::factory()->create([
            'tahun_agenda' => 2025,
            'nomor_urut_agenda' => 8,
            'nomor_surat_masuk' => '005/120/KEC-ST/2025',
            'tanggal_terima' => '2025-12-10',
            'pengirim' => 'Kecamatan Sindue Tobata',
            'perihal' => 'Undangan Rapat Koordinasi',
            'created_by' => $this->operator->id,
        ]);
        $deleted2025->delete();

        // Catat surat masuk baru di tahun 2025 -> harus menjadi nomor_urut_agenda = 9 (withTrashed max + 1)
        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-masuk.store'), [
                'nomor_surat_masuk' => '005/125/KEC-ST/2025',
                'tanggal_surat' => '2025-12-27',
                'tanggal_terima' => '2025-12-28',
                'pengirim' => 'Kecamatan Sindue Tobata',
                'perihal' => 'Evaluasi APBDes Akhir Tahun',
                'tujuan_disposisi' => 'Sekretaris Desa',
                'catatan_disposisi' => 'Siapkan laporan realisasi',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $sm2025 = SuratMasuk::query()->where('tahun_agenda', 2025)->firstOrFail();
        $this->assertSame(9, $sm2025->nomor_urut_agenda);

        // Catat surat masuk pertama di tahun 2026 -> otomatis reset kembali ke 1
        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-masuk.store'), [
                'nomor_surat_masuk' => '400/002/DPMD-DGL/2026',
                'tanggal_surat' => '2026-01-08',
                'tanggal_terima' => '2026-01-10',
                'pengirim' => 'Dinas PMD Kabupaten Donggala',
                'perihal' => 'Pedoman Penyusunan APBDes 2026',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $sm2026 = SuratMasuk::query()->where('tahun_agenda', 2026)->firstOrFail();
        $this->assertSame(1, $sm2026->nomor_urut_agenda);
    }

    public function test_penyimpanan_berkas_di_private_disk_penghapusan_berkas_lama_dan_streaming_terautentikasi(): void
    {
        $pdfScanLama = UploadedFile::fake()->create('scan-undangan-lama.pdf', 512, 'application/pdf');

        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-masuk.store'), [
                'nomor_surat_masuk' => '005/045/BUP-DGL/2026',
                'tanggal_surat' => '2026-03-10',
                'tanggal_terima' => '2026-03-12',
                'pengirim' => 'Bupati Donggala',
                'perihal' => 'Edaran Musrenbang Kecamatan',
                'berkas_scan' => $pdfScanLama,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $suratMasuk = SuratMasuk::firstOrFail();
        $oldPath = $suratMasuk->berkas_scan_path;

        $this->assertNotNull($oldPath);
        $this->assertStringStartsWith('surat-masuk/2026/', $oldPath);
        Storage::disk('private')->assertExists($oldPath);
        Storage::disk('public')->assertMissing($oldPath);

        // 1. Operator & Kepala Desa berhasil mengakses streaming berkas private
        $streamOperator = $this->actingAs($this->operator)
            ->get(route('panel.persuratan.surat-masuk.berkas', $suratMasuk));
        $streamOperator->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $streamOperator->headers->get('content-type'));

        $streamKades = $this->actingAs($this->kepalaDesa)
            ->get(route('panel.persuratan.surat-masuk.berkas', $suratMasuk));
        $streamKades->assertOk();

        // 2. Pengunjung tanpa login (guest) ditolak dan diarahkan ke login
        auth()->logout();
        $this->get(route('panel.persuratan.surat-masuk.berkas', $suratMasuk))
            ->assertRedirect(route('login'));

        // 3. Ganti berkas scan dengan file baru -> berkas lama di private disk harus terhapus
        $pngScanBaru = UploadedFile::fake()->image('scan-revisi.png', 800, 1000);

        $this->actingAs($this->operator)
            ->put(route('panel.persuratan.surat-masuk.update', $suratMasuk), [
                'nomor_surat_masuk' => '005/045/BUP-DGL/2026',
                'tanggal_surat' => '2026-03-10',
                'tanggal_terima' => '2026-03-12',
                'pengirim' => 'Bupati Donggala',
                'perihal' => 'Edaran Musrenbang Kecamatan (Revisi)',
                'berkas_scan' => $pngScanBaru,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $suratMasuk->refresh();
        $this->assertNotSame($oldPath, $suratMasuk->berkas_scan_path);
        Storage::disk('private')->assertMissing($oldPath);
        Storage::disk('private')->assertExists($suratMasuk->berkas_scan_path);
    }

    public function test_cetak_pdf_f4_landscape_agenda_surat_masuk_dan_proteksi_rbac_kepala_desa(): void
    {
        SuratMasuk::factory()->create([
            'tahun_agenda' => 2026,
            'nomor_urut_agenda' => 1,
            'nomor_surat_masuk' => '140/010/KEC-ST/2026',
            'tanggal_terima' => '2026-02-01',
            'pengirim' => 'Camat Sindue Tobata',
            'perihal' => 'Monitoring Pembangunan Desa',
            'created_by' => $this->operator->id,
        ]);

        // Operator & Kepala Desa dapat melihat halaman index dan mencetak PDF F4 Landscape
        $this->actingAs($this->operator)
            ->get(route('panel.persuratan.surat-masuk.index'))
            ->assertOk();

        $this->actingAs($this->kepalaDesa)
            ->get(route('panel.persuratan.surat-masuk.index'))
            ->assertOk();

        $pdfKades = $this->actingAs($this->kepalaDesa)
            ->get(route('panel.persuratan.surat-masuk.cetak-agenda', ['tahun' => 2026]));
        $pdfKades->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfKades->headers->get('content-type'));

        // Kepala Desa ditolak 403 dari aksi tulis surat masuk
        $this->actingAs($this->kepalaDesa)
            ->post(route('panel.persuratan.surat-masuk.store'), [
                'nomor_surat_masuk' => '999/ILEGAL',
                'tanggal_terima' => '2026-02-05',
                'pengirim' => 'Pengirim',
                'perihal' => 'Perihal',
            ])
            ->assertForbidden();
    }
}
