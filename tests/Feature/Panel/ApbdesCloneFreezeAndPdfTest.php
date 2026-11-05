<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisDokumenApbdes;
use App\Enums\UserRole;
use App\Models\Apbdes;
use App\Models\ProfilDesa;
use App\Models\User;
use App\Services\ApbdesService;
use App\Support\ApbdesPosCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApbdesCloneFreezeAndPdfTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    private User $kades;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->kades = User::factory()->create([
            'role' => UserRole::KepalaDesa,
            'is_active' => true,
        ]);

        ProfilDesa::create([
            'nama_desa' => 'Tamarenja',
            'kode_desa_surat' => '72.03.08.2011',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'alamat_kantor' => 'Jl. Trans Sulawesi Desa Tamarenja',
            'nama_kepala_desa' => 'H. Moh. Rizal',
            'nama_sekdes' => 'Abd. Rahman, S.Sos',
            'tahun_anggaran_aktif' => 2026,
            'daftar_dusun' => ['Dusun I', 'Dusun II', 'Dusun III'],
        ]);
    }

    public function test_initializes_murni_with_14_standard_pos_and_validates_belanja_ceiling_vs_pendapatan_overachievement(): void
    {
        // 1. Akses GET /panel/apbdes otomatis menginisialisasi Murni TA 2026 beserta 14 pos baku
        $this->actingAs($this->operator)
            ->get(route('panel.apbdes.index', ['tahun' => 2026]))
            ->assertOk();

        $murni = Apbdes::where('tahun_anggaran', 2026)
            ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
            ->firstOrFail();

        $this->assertCount(14, $murni->rincian);
        $this->assertTrue($murni->is_aktif_publik);
        $this->assertFalse($murni->is_terkunci);

        $posPades = $murni->rincian()->where('kode_pos', 'pades')->firstOrFail();
        $posSilpa = $murni->rincian()->where('kode_pos', 'silpa')->firstOrFail();
        $posBidang1 = $murni->rincian()->where('kode_pos', 'bidang_1')->firstOrFail();
        $posPenyertaanModal = $murni->rincian()->where('kode_pos', 'penyertaan_modal')->firstOrFail();

        // 2. Kelompok Pendapatan (PADes) & Penerimaan Pembiayaan (SiLPA) DIIZINKAN melampaui pagu (> 100%)
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $posPades), [
                'pagu_anggaran' => 50_000_000,
                'realisasi_tahap_1' => 30_000_000,
                'realisasi_tahap_2' => 25_000_000,
                'realisasi_tahap_3' => 10_000_000, // Total 65 jt > 50 jt (130%)
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(65_000_000, $posPades->fresh()->total_realisasi);

        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $posSilpa), [
                'pagu_anggaran' => 20_000_000,
                'realisasi_tahap_1' => 25_000_000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(25_000_000, $posSilpa->fresh()->total_realisasi);

        // 3. Kelompok Belanja (Bidang 1) & Pengeluaran Pembiayaan WAJIB DITOLAK bila total_realisasi > pagu_anggaran
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $posBidang1), [
                'pagu_anggaran' => 100_000_000,
                'realisasi_tahap_1' => 50_000_000,
                'realisasi_tahap_2' => 40_000_000,
                'realisasi_tahap_3' => 20_000_000, // Total 110 jt > 100 jt
            ])
            ->assertSessionHasErrors('realisasi');

        $this->assertSame(0, $posBidang1->fresh()->total_realisasi);

        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $posPenyertaanModal), [
                'pagu_anggaran' => 30_000_000,
                'realisasi_tahap_1' => 35_000_000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ])
            ->assertSessionHasErrors('realisasi');

        // 4. Kelompok Belanja dengan total_realisasi <= pagu_anggaran -> BERHASIL
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $posBidang1), [
                'pagu_anggaran' => 100_000_000,
                'realisasi_tahap_1' => 40_000_000,
                'realisasi_tahap_2' => 30_000_000,
                'realisasi_tahap_3' => 25_000_000, // Total 95 jt <= 100 jt
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(95_000_000, $posBidang1->fresh()->total_realisasi);
    }

    public function test_rejects_manual_edit_of_bidang_2_fisik(): void
    {
        $service = app(ApbdesService::class);
        $murni = $service->initializeMurni(2026, $this->operator->id);

        $posBidang2Fisik = $murni->rincian()
            ->where('kode_pos', ApbdesPosCatalog::KODE_BIDANG_2_FISIK)
            ->firstOrFail();

        // 1. Tolak via endpoint single row
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $posBidang2Fisik), [
                'pagu_anggaran' => 250_000_000,
                'realisasi_tahap_1' => 100_000_000,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
            ])
            ->assertSessionHasErrors('bidang_2_fisik');

        // 2. Tolak via endpoint batch update apabila nilai bidang_2_fisik dicoba diubah manual
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.update', $murni), [
                'rincian' => [
                    'bidang_2_fisik' => [
                        'kode_pos' => 'bidang_2_fisik',
                        'pagu_anggaran' => 250_000_000,
                        'realisasi_tahap_1' => 50_000_000,
                        'realisasi_tahap_2' => 0,
                        'realisasi_tahap_3' => 0,
                    ],
                ],
            ])
            ->assertSessionHasErrors('bidang_2_fisik');

        $this->assertSame(0, $posBidang2Fisik->fresh()->pagu_anggaran);
    }

    public function test_clone_and_freeze_copies_murni_to_perubahan_and_locks_murni_permanently(): void
    {
        $service = app(ApbdesService::class);
        $murni = $service->initializeMurni(2026, $this->operator->id, 'Perdes No. 01/2026', '2026-01-10');

        // Isi beberapa pos di Murni
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.update', $murni), [
                'rincian' => [
                    'dds' => [
                        'kode_pos' => 'dds',
                        'pagu_anggaran' => 800_000_000,
                        'realisasi_tahap_1' => 400_000_000,
                        'realisasi_tahap_2' => 200_000_000,
                        'realisasi_tahap_3' => 0,
                    ],
                    'bidang_1' => [
                        'kode_pos' => 'bidang_1',
                        'pagu_anggaran' => 300_000_000,
                        'realisasi_tahap_1' => 150_000_000,
                        'realisasi_tahap_2' => 100_000_000,
                        'realisasi_tahap_3' => 0,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        // Buat dokumen Perubahan (Clone & Freeze)
        $this->actingAs($this->operator)
            ->post(route('panel.apbdes.buat-perubahan'), [
                'tahun_anggaran' => 2026,
                'nomor_perdes' => 'Perdes Perubahan No. 05/2026',
                'tanggal_penetapan' => '2026-08-15',
            ])
            ->assertSessionHasNoErrors();

        $murniFresh = $murni->fresh();
        $this->assertTrue($murniFresh->is_terkunci);
        $this->assertFalse($murniFresh->is_aktif_publik);

        $perubahan = Apbdes::where('tahun_anggaran', 2026)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->firstOrFail();

        $this->assertTrue($perubahan->is_aktif_publik);
        $this->assertFalse($perubahan->is_terkunci);
        $this->assertCount(14, $perubahan->rincian);

        // Pastikan nilai pagu & realisasi telah tersalin (Clone) dari Murni ke Perubahan
        $perubahanDds = $perubahan->rincian()->where('kode_pos', 'dds')->firstOrFail();
        $this->assertSame(800_000_000, $perubahanDds->pagu_anggaran);
        $this->assertSame(600_000_000, $perubahanDds->total_realisasi);

        // Pastikan segala percobaan modifikasi terhadap dokumen Murni yang telah terkunci DITOLAK!
        $murniBidang1 = $murniFresh->rincian()->where('kode_pos', 'bidang_1')->firstOrFail();

        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $murniBidang1), [
                'pagu_anggaran' => 350_000_000,
                'realisasi_tahap_1' => 150_000_000,
                'realisasi_tahap_2' => 100_000_000,
                'realisasi_tahap_3' => 0,
            ])
            ->assertSessionHasErrors('apbdes');

        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.update', $murniFresh), [
                'nomor_perdes' => 'Perdes Coba Ubah Murni Terkunci',
            ])
            ->assertSessionHasErrors('apbdes');

        $this->assertSame(300_000_000, $murniBidang1->fresh()->pagu_anggaran);
        $this->assertSame('Perdes No. 01/2026', $murniFresh->fresh()->nomor_perdes);

        // Sebaliknya, dokumen Perubahan dapat diperbarui dengan normal
        $perubahanBidang1 = $perubahan->rincian()->where('kode_pos', 'bidang_1')->firstOrFail();
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.rincian.update', $perubahanBidang1), [
                'pagu_anggaran' => 350_000_000,
                'realisasi_tahap_1' => 150_000_000,
                'realisasi_tahap_2' => 100_000_000,
                'realisasi_tahap_3' => 80_000_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(350_000_000, $perubahanBidang1->fresh()->pagu_anggaran);
        $this->assertSame(330_000_000, $perubahanBidang1->fresh()->total_realisasi);
    }

    public function test_calculates_budget_balance_formula_and_allows_f4_portrait_pdf_download_for_operator_and_kepala_desa(): void
    {
        $service = app(ApbdesService::class);
        $murni = $service->initializeMurni(2026, $this->operator->id);

        // Set Pendapatan = 1.000.000.000, SiLPA = 50.000.000, Penyertaan Modal = 25.000.000
        // Ketersediaan Dana = 1.000.000.000 + 50.000.000 - 25.000.000 = 1.025.000.000
        // Set Belanja = 1.000.000.000 -> Selisih Keseimbangan = +25.000.000 (belum seimbang)
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.update', $murni), [
                'rincian' => [
                    'dds' => [
                        'kode_pos' => 'dds',
                        'pagu_anggaran' => 1_000_000_000,
                        'realisasi_tahap_1' => 500_000_000,
                        'realisasi_tahap_2' => 0,
                        'realisasi_tahap_3' => 0,
                    ],
                    'silpa' => [
                        'kode_pos' => 'silpa',
                        'pagu_anggaran' => 50_000_000,
                        'realisasi_tahap_1' => 50_000_000,
                        'realisasi_tahap_2' => 0,
                        'realisasi_tahap_3' => 0,
                    ],
                    'penyertaan_modal' => [
                        'kode_pos' => 'penyertaan_modal',
                        'pagu_anggaran' => 25_000_000,
                        'realisasi_tahap_1' => 25_000_000,
                        'realisasi_tahap_2' => 0,
                        'realisasi_tahap_3' => 0,
                    ],
                    'bidang_1' => [
                        'kode_pos' => 'bidang_1',
                        'pagu_anggaran' => 1_000_000_000,
                        'realisasi_tahap_1' => 400_000_000,
                        'realisasi_tahap_2' => 0,
                        'realisasi_tahap_3' => 0,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $summaryUnbalanced = $service->calculateSummaryAndBalance($murni->fresh('rincian'));
        $this->assertSame(1_025_000_000, $summaryUnbalanced['ketersediaan_dana_pagu']);
        $this->assertSame(1_000_000_000, $summaryUnbalanced['total_pagu_belanja']);
        $this->assertSame(25_000_000, $summaryUnbalanced['selisih_keseimbangan']);
        $this->assertFalse($summaryUnbalanced['is_seimbang']);

        // Tambah 25.000.000 pada bidang_2_non_fisik agar seimbang (selisih = Rp 0)
        $this->actingAs($this->operator)
            ->put(route('panel.apbdes.update', $murni), [
                'rincian' => [
                    'bidang_2_non_fisik' => [
                        'kode_pos' => 'bidang_2_non_fisik',
                        'pagu_anggaran' => 25_000_000,
                        'realisasi_tahap_1' => 10_000_000,
                        'realisasi_tahap_2' => 0,
                        'realisasi_tahap_3' => 0,
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();

        $summaryBalanced = $service->calculateSummaryAndBalance($murni->fresh('rincian'));
        $this->assertSame(0, $summaryBalanced['selisih_keseimbangan']);
        $this->assertTrue($summaryBalanced['is_seimbang']);

        // Cetak PDF F4 Portrait untuk Operator & Kepala Desa
        $pdfOp = $this->actingAs($this->operator)
            ->get(route('panel.apbdes.dokumen.cetak-pdf', $murni));
        $pdfOp->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfOp->headers->get('content-type'));

        $pdfKades = $this->actingAs($this->kades)
            ->get(route('panel.apbdes.cetak-pdf', ['tahun' => 2026]));
        $pdfKades->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfKades->headers->get('content-type'));

        // Kepala Desa ditolak 403 untuk seluruh aksi tulis APBDes
        $this->actingAs($this->kades)
            ->post(route('panel.apbdes.buat-perubahan'), ['tahun_anggaran' => 2026])
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->put(route('panel.apbdes.update', $murni), ['nomor_perdes' => 'Ditolak'])
            ->assertForbidden();
    }
}
