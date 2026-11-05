<?php

namespace Tests\Feature\Panel;

use App\Enums\JenisBansos;
use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Enums\UserRole;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProfilDesa;
use App\Models\SuratKeluar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuratKeluarEngineAndPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected User $kepalaDesa;

    protected KartuKeluarga $kk;

    protected Penduduk $kepalaKeluarga;

    protected Penduduk $istri;

    protected function setUp(): void
    {
        parent::setUp();

        ProfilDesa::create([
            'id' => 1,
            'nama_desa' => 'Tamarenja',
            'kode_desa_surat' => 'PEMDES-TMR',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'alamat_kantor' => 'Jl. Trans Sulawesi Desa Tamarenja',
            'nama_kepala_desa' => 'Moh. Rizal, S.AP',
            'nip_kepala_desa' => '197805102008011005',
            'nama_sekdes' => 'Siti Rahmawati, S.Sos',
            'nip_sekdes' => '198409122010012009',
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

        $this->kk = KartuKeluarga::factory()->create([
            'nomor_kk' => '7203180101200001',
            'dusun' => 'Dusun I',
        ]);

        $this->kepalaKeluarga = Penduduk::factory()->create([
            'kartu_keluarga_id' => $this->kk->id,
            'nik' => '7203181005800001',
            'nama_lengkap' => 'Ahmad Syarif',
            'jenis_kelamin' => 'L',
            'hubungan_keluarga' => 'Kepala Keluarga',
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);

        $this->istri = Penduduk::factory()->create([
            'kartu_keluarga_id' => $this->kk->id,
            'nik' => '7203185206840002',
            'nama_lengkap' => 'Fatimah Az-Zahra',
            'jenis_kelamin' => 'P',
            'hubungan_keluarga' => 'Istri',
            'status_kesejahteraan' => StatusKesejahteraan::DtsenDesil2,
            'status_kependudukan' => StatusKependudukan::Aktif,
        ]);
    }

    public function test_validasi_json_ke_10_template_surat_dan_kode_klasifikasi_sktm_serta_keterangan_umum(): void
    {
        // Uji gagal bila field wajib SKU kosong
        $this->actingAs($this->operator)
            ->from(route('panel.persuratan.surat-keluar.create'))
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Sku->value,
                'keperluan' => 'Pengajuan KUR Bank BRI',
                'data_khusus' => [
                    'nama_usaha' => 'Warung Berkah',
                    // jenis_usaha, bidang_usaha, lama_usaha, alamat_usaha kosong
                ],
            ])
            ->assertSessionHasErrors([
                'data_khusus.jenis_usaha',
                'data_khusus.bidang_usaha',
                'data_khusus.lama_usaha',
                'data_khusus.alamat_usaha',
            ]);

        // Uji keberhasilan penerbitan 9 template non-kematian (Kelahiran mengikat ke Ibu tanpa menambah baris penduduk bayi)
        $jumlahPendudukAwal = Penduduk::count();

        $templatesPayload = [
            [
                'jenis_surat' => JenisSurat::Sku->value,
                'kode_klasifikasi' => '500',
                'data_khusus' => [
                    'nama_usaha' => 'UD Kelapa Mandiri',
                    'jenis_usaha' => 'Perdagangan Hasil Bumi',
                    'bidang_usaha' => 'Pertanian & Perkebunan',
                    'lama_usaha' => '4 Tahun',
                    'alamat_usaha' => 'Dusun I Desa Tamarenja',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::Domisili->value,
                'kode_klasifikasi' => '470',
                'data_khusus' => [
                    'sejak_tahun' => '2012',
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::Sktm->value,
                'kode_klasifikasi' => '460', // Opsi ubah 460 pada SKTM
                'data_khusus' => [
                    'penghasilan_rata_rata' => 950000,
                    'jumlah_tanggungan' => 3,
                    'nama_tanggungan' => 'Fatimah, Rizki, Aisyah',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::PengantarSkck->value,
                'kode_klasifikasi' => '300',
                'data_khusus' => [
                    'catatan_kelakuan' => 'Berkelakuan baik di masyarakat',
                    'tujuan_skck' => 'Melamar Pekerjaan',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::Kelahiran->value,
                'kode_klasifikasi' => '474.1',
                'penduduk_id' => $this->istri->id,
                'data_khusus' => [
                    'nama_bayi' => 'Muhammad Al-Fatih',
                    'jenis_kelamin_bayi' => 'L',
                    'hari_lahir_bayi' => 'Jumat',
                    'tanggal_lahir_bayi' => '2026-05-01',
                    'jam_lahir_bayi' => '08:15 WITA',
                    'tempat_lahir_bayi' => 'Puskesmas Sindue Tobata',
                    'anak_ke' => 2,
                    'nama_ayah' => 'Ahmad Syarif',
                    'nik_ayah' => $this->kepalaKeluarga->nik,
                    'nama_ibu' => 'Fatimah Az-Zahra',
                    'nik_ibu' => $this->istri->nik,
                ],
            ],
            [
                'jenis_surat' => JenisSurat::BelumMenikah->value,
                'kode_klasifikasi' => '474.2',
                'data_khusus' => [
                    'pernyataan_belum_menikah' => 'Benar belum pernah menikah secara resmi maupun adat',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::JandaDuda->value,
                'kode_klasifikasi' => '474.2',
                'data_khusus' => [
                    'status_cerai' => 'cerai_mati',
                    'nama_mantan_pasangan' => 'Alm. Hasanuddin',
                    'sejak_tanggal_tahun' => 'Tahun 2023',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::Kehilangan->value,
                'kode_klasifikasi' => '300',
                'data_khusus' => [
                    'barang_hilang' => '1 Buah Buku Tabungan BRI & Kartu ATM',
                    'waktu_kehilangan' => 'Senin, 10 Agustus 2026 Pukul 10:00 WITA',
                    'lokasi_kehilangan' => 'Sekitar Pasar Tradisional Sindue',
                ],
            ],
            [
                'jenis_surat' => JenisSurat::KeteranganUmum->value,
                'kode_klasifikasi' => '000', // Opsi ubah 000 pada Surat Umum
                'data_khusus' => [
                    'judul_keterangan' => 'SURAT KETERANGAN BEDA NAMA',
                    'isi_keterangan' => 'Menerangkan bahwa nama Ahmad Syarif di KTP dan Akhmad Syarif di Buku Nikah adalah satu orang yang sama.',
                ],
            ],
        ];

        foreach ($templatesPayload as $payload) {
            $this->actingAs($this->operator)
                ->post(route('panel.persuratan.surat-keluar.store'), [
                    'penduduk_id' => $payload['penduduk_id'] ?? $this->kepalaKeluarga->id,
                    'jenis_surat' => $payload['jenis_surat'],
                    'kode_klasifikasi' => $payload['kode_klasifikasi'],
                    'tanggal_surat' => '2026-11-05',
                    'keperluan' => 'Administrasi Resmi Desa',
                    'opsi_penandatangan' => 'kepala_desa',
                    'data_khusus' => $payload['data_khusus'],
                ])
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }

        // Pastikan Surat Kelahiran tidak menyisipkan bayi nir-NIK ke tabel penduduk
        $this->assertSame($jumlahPendudukAwal, Penduduk::count());
        $this->assertSame(9, SuratKeluar::count());
    }

    public function test_endpoint_ringkasan_bansos_1_kk_untuk_verifikasi_sktm_di_loket(): void
    {
        PenerimaBansos::factory()->create([
            'penduduk_id' => $this->kepalaKeluarga->id,
            'jenis_bantuan' => JenisBansos::Pkh,
            'bentuk_nominal' => 'Rp 750.000 / Tahap',
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        $response = $this->actingAs($this->operator)
            ->getJson(route('panel.persuratan.cek-bansos-kk', $this->istri));

        $response->assertOk()
            ->assertJsonPath('nomor_kk', '7203180101200001')
            ->assertJsonPath('kepala_keluarga', 'Ahmad Syarif')
            ->assertJsonPath('jumlah_anggota_kk', 2)
            ->assertJsonPath('total_bantuan_aktif', 1)
            ->assertJsonPath('riwayat_bansos.0.nama_penerima', 'Ahmad Syarif');
    }

    public function test_penomoran_berurutan_with_trashed_auto_reset_tahunan_dan_snapshot_penandatangan(): void
    {
        // Buat 1 surat di tahun 2025 dengan nomor urut 12 lalu soft-delete (simulasi arsip lama)
        $deleted2025 = SuratKeluar::factory()->create([
            'penduduk_id' => $this->kepalaKeluarga->id,
            'tahun_surat' => 2025,
            'nomor_urut' => 12,
            'nomor_surat' => '500 / 012 / PEMDES-TMR / XII / 2025',
            'status' => StatusSuratKeluar::Ditolak,
            'processed_by' => $this->operator->id,
        ]);
        $deleted2025->delete();

        // Terbitkan surat di tahun 2025 -> harus melanjutkan dari withTrashed max (12 + 1 = 013)
        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Domisili->value,
                'tanggal_surat' => '2025-12-20',
                'keperluan' => 'Administrasi Akhir Tahun',
                'opsi_penandatangan' => 'sekdes_an',
                'data_khusus' => [
                    'sejak_tahun' => '2015',
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
            ])
            ->assertRedirect();

        $surat2025 = SuratKeluar::query()->where('tahun_surat', 2025)->firstOrFail();
        $this->assertSame(13, $surat2025->nomor_urut);
        $this->assertSame('470 / 013 / PEMDES-TMR / XII / 2025', $surat2025->nomor_surat);
        $this->assertSame('sekdes_an', $surat2025->opsi_penandatangan);
        $this->assertSame('Siti Rahmawati, S.Sos', $surat2025->nama_penandatangan);
        $this->assertSame('a.n. Kepala Desa - Sekretaris Desa', $surat2025->jabatan_penandatangan);
        $this->assertNotEmpty($surat2025->qr_token);

        // Terbitkan surat pertama di tahun 2026 -> otomatis reset kembali ke 001
        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Domisili->value,
                'tanggal_surat' => '2026-01-05',
                'keperluan' => 'Administrasi Tahun Baru',
                'opsi_penandatangan' => 'kepala_desa',
                'data_khusus' => [
                    'sejak_tahun' => '2015',
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
            ])
            ->assertRedirect();

        $surat2026 = SuratKeluar::query()->where('tahun_surat', 2026)->firstOrFail();
        $this->assertSame(1, $surat2026->nomor_urut);
        $this->assertSame('470 / 001 / PEMDES-TMR / I / 2026', $surat2026->nomor_surat);
        $this->assertSame('Moh. Rizal, S.AP', $surat2026->nama_penandatangan);
        $this->assertSame('Kepala Desa', $surat2026->jabatan_penandatangan);
    }

    public function test_trigger_atomik_surat_kematian_mengubah_status_penduduk_dan_bansos_serta_blokir_duplikasi(): void
    {
        $bansosAktif = PenerimaBansos::factory()->create([
            'penduduk_id' => $this->kepalaKeluarga->id,
            'jenis_bantuan' => JenisBansos::BltDd,
            'tahun_anggaran' => 2026,
            'tahap_penyaluran' => 'Tahap I',
            'status_kepesertaan' => StatusPesertaBansos::Aktif,
            'created_by' => $this->operator->id,
        ]);

        // Terbitkan Surat Kematian untuk Kepala Keluarga
        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Kematian->value,
                'tanggal_surat' => '2026-06-15',
                'keperluan' => 'Pengurusan Akta Kematian Disdukcapil',
                'opsi_penandatangan' => 'kepala_desa',
                'data_khusus' => [
                    'hari_meninggal' => 'Senin',
                    'tanggal_meninggal' => '2026-06-14',
                    'jam_meninggal' => '19:30 WITA',
                    'tempat_meninggal' => 'RSUD Kabelota Donggala',
                    'penyebab_meninggal' => 'Sakit',
                    'lokasi_makam' => 'TPU Desa Tamarenja',
                    'nama_pelapor' => 'Fatimah Az-Zahra',
                    'nik_pelapor' => $this->istri->nik,
                    'hubungan_pelapor' => 'Istri',
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        // Pastikan status penduduk & Bansos otomatis berubah ke meninggal_dunia
        $this->kepalaKeluarga->refresh();
        $bansosAktif->refresh();

        $this->assertSame(StatusKependudukan::MeninggalDunia, $this->kepalaKeluarga->status_kependudukan);
        $this->assertSame(StatusPesertaBansos::MeninggalDunia, $bansosAktif->status_kepesertaan);

        // Pastikan duplikasi Surat Kematian Terbit untuk warga yang sama ditolak
        $this->actingAs($this->operator)
            ->from(route('panel.persuratan.surat-keluar.create'))
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Kematian->value,
                'tanggal_surat' => '2026-06-16',
                'keperluan' => 'Pengurusan Klaim',
                'data_khusus' => [
                    'hari_meninggal' => 'Senin',
                    'tanggal_meninggal' => '2026-06-14',
                    'tempat_meninggal' => 'RSUD Kabelota Donggala',
                    'penyebab_meninggal' => 'Sakit',
                    'lokasi_makam' => 'TPU Desa Tamarenja',
                    'nama_pelapor' => 'Fatimah Az-Zahra',
                    'hubungan_pelapor' => 'Istri',
                ],
            ])
            ->assertSessionHasErrors(['penduduk_id']);

        // Pastikan pembuatan surat layanan lain (misal Domisili) untuk warga wafat ditolak
        $this->actingAs($this->operator)
            ->from(route('panel.persuratan.surat-keluar.create'))
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Domisili->value,
                'keperluan' => 'Perpanjangan KTP',
                'data_khusus' => [
                    'sejak_tahun' => '2010',
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
            ])
            ->assertSessionHasErrors(['penduduk_id']);
    }

    public function test_penolakan_soft_delete_pada_surat_terbit_dan_keberhasilan_status_dibatalkan_serta_cetak_pdf_f4(): void
    {
        $this->actingAs($this->operator)
            ->post(route('panel.persuratan.surat-keluar.store'), [
                'penduduk_id' => $this->kepalaKeluarga->id,
                'jenis_surat' => JenisSurat::Domisili->value,
                'tanggal_surat' => '2026-07-01',
                'keperluan' => 'Pembukaan Rekening Bank',
                'opsi_penandatangan' => 'kepala_desa',
                'data_khusus' => [
                    'sejak_tahun' => '2010',
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
            ])
            ->assertRedirect();

        $suratTerbit = SuratKeluar::firstOrFail();

        // 1. Cetak PDF F4 Portrait surat keluar (Operator & Kepala Desa berhasil)
        $pdfSurat = $this->actingAs($this->operator)
            ->get(route('panel.persuratan.surat-keluar.pdf', $suratTerbit));
        $pdfSurat->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfSurat->headers->get('content-type'));

        // 2. Cetak PDF F4 Landscape Buku Agenda Surat Keluar (Kepala Desa berhasil)
        $pdfAgenda = $this->actingAs($this->kepalaDesa)
            ->get(route('panel.persuratan.surat-keluar.cetak-agenda', ['tahun' => 2026]));
        $pdfAgenda->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfAgenda->headers->get('content-type'));

        // 3. Soft-delete pada surat berstatus `terbit` wajib ditolak
        $this->actingAs($this->operator)
            ->from(route('panel.persuratan.surat-keluar.index'))
            ->delete(route('panel.persuratan.surat-keluar.destroy', $suratTerbit))
            ->assertSessionHasErrors(['status']);

        $this->assertDatabaseHas('surat_keluar', [
            'id' => $suratTerbit->id,
            'deleted_at' => null,
        ]);

        // 4. Pembatalan surat `terbit` -> `dibatalkan` berhasil dan nomor urut tetap terjaga
        $this->actingAs($this->operator)
            ->patch(route('panel.persuratan.surat-keluar.batalkan', $suratTerbit), [
                'alasan_pembatalan' => 'Terdapat kekeliruan penulisan tahun domisili',
            ])
            ->assertSessionHasNoErrors();

        $suratTerbit->refresh();
        $this->assertSame(StatusSuratKeluar::Dibatalkan, $suratTerbit->status);
        $this->assertSame('Terdapat kekeliruan penulisan tahun domisili', $suratTerbit->alasan_pembatalan);

        // 5. Kepala Desa ditolak 403 dari aksi tulis/batalkan surat
        $this->actingAs($this->kepalaDesa)
            ->post(route('panel.persuratan.surat-keluar.store'), [])
            ->assertForbidden();
    }
}
