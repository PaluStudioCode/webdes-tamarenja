<?php

namespace Tests\Feature\Panel;

use App\Enums\KategoriKegiatan;
use App\Enums\StatusPublikasi;
use App\Enums\UserRole;
use App\Models\KegiatanDesa;
use App\Models\KegiatanDesaFoto;
use App\Models\ProfilDesa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KegiatanDesaSlugAndPhotoGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected User $kades;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->operator = User::factory()->create([
            'role' => UserRole::Operator,
            'is_active' => true,
        ]);

        $this->kades = User::factory()->create([
            'role' => UserRole::KepalaDesa,
            'is_active' => true,
        ]);

        ProfilDesa::create([
            'id' => 1,
            'nama_desa' => 'Tamarenja',
            'kecamatan' => 'Sindue Tobata',
            'kabupaten' => 'Donggala',
            'provinsi' => 'Sulawesi Tengah',
            'alamat_kantor' => 'Jl. Trans Palu - Sabang',
            'kode_desa_surat' => 'PEMDES-TMR',
            'nama_kepala_desa' => 'Moh. Rizal, S.Sos',
            'nama_sekdes' => 'Irfan, S.AP',
            'tahun_anggaran_aktif' => 2026,
            'daftar_dusun' => ['Dusun I', 'Dusun II', 'Dusun III'],
        ]);
    }

    #[Test]
    public function menolak_publikasi_published_tanpa_foto_dan_mengizinkan_draft_tanpa_foto(): void
    {
        // 1. Published tanpa foto -> Ditolak
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Penyaluran BLT Dana Desa Tahap I',
                'kategori' => KategoriKegiatan::PenyaluranBansos->value,
                'tanggal_kegiatan' => '2026-03-10',
                'lokasi_dusun' => 'Dusun I',
                'uraian' => 'Penyaluran bantuan langsung tunai di Balai Desa Tamarenja.',
                'status_publikasi' => StatusPublikasi::Published->value,
            ])
            ->assertSessionHasErrors('foto_baru');

        // 2. Draft tanpa foto -> Diizinkan
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Rencana Musdes RKPDes 2027',
                'kategori' => KategoriKegiatan::Musdes->value,
                'tanggal_kegiatan' => '2026-03-15',
                'lokasi_dusun' => 'Balai Desa',
                'uraian' => 'Persiapan musyawarah perencanaan pembangunan desa.',
                'status_publikasi' => StatusPublikasi::Draft->value,
            ])
            ->assertRedirect(route('panel.sosial.kegiatan.index'))
            ->assertSessionHasNoErrors();

        $draft = KegiatanDesa::query()->where('slug', 'rencana-musdes-rkpdes-2027')->firstOrFail();
        $this->assertSame(StatusPublikasi::Draft, $draft->status_publikasi);
        $this->assertNull($draft->published_at);
    }

    #[Test]
    public function memvalidasi_batas_maksimal_5_foto_saat_buat_dan_update(): void
    {
        $sixPhotos = [
            UploadedFile::fake()->image('foto1.jpg', 800, 600),
            UploadedFile::fake()->image('foto2.jpg', 800, 600),
            UploadedFile::fake()->image('foto3.jpg', 800, 600),
            UploadedFile::fake()->image('foto4.jpg', 800, 600),
            UploadedFile::fake()->image('foto5.jpg', 800, 600),
            UploadedFile::fake()->image('foto6.jpg', 800, 600),
        ];

        // 1. Unggah 6 foto sekaligus -> Ditolak
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Gotong Royong Saluran Air Dusun II',
                'kategori' => KategoriKegiatan::GotongRoyong->value,
                'tanggal_kegiatan' => '2026-03-12',
                'lokasi_dusun' => 'Dusun II',
                'uraian' => 'Kerja bakti warga membersihkan drainase.',
                'status_publikasi' => StatusPublikasi::Published->value,
                'foto_baru' => $sixPhotos,
            ])
            ->assertSessionHasErrors('foto_baru');

        // 2. Unggah 4 foto -> Berhasil
        $fourPhotos = array_slice($sixPhotos, 0, 4);
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Gotong Royong Saluran Air Dusun II',
                'kategori' => KategoriKegiatan::GotongRoyong->value,
                'tanggal_kegiatan' => '2026-03-12',
                'lokasi_dusun' => 'Dusun II',
                'uraian' => 'Kerja bakti warga membersihkan drainase.',
                'status_publikasi' => StatusPublikasi::Published->value,
                'foto_baru' => $fourPhotos,
            ])
            ->assertRedirect(route('panel.sosial.kegiatan.index'))
            ->assertSessionHasNoErrors();

        $kegiatan = KegiatanDesa::query()->firstOrFail();
        $this->assertCount(4, $kegiatan->foto);
        $this->assertNotNull($kegiatan->published_at);

        // 3. Saat update, mempertahankan 4 foto lama + mengunggah 2 foto baru (total 6) -> Ditolak
        $this->actingAs($this->operator)
            ->post("/panel/sosial/kegiatan/{$kegiatan->id}", [
                '_method' => 'PUT',
                'judul' => $kegiatan->judul,
                'kategori' => KategoriKegiatan::GotongRoyong->value,
                'tanggal_kegiatan' => '2026-03-12',
                'lokasi_dusun' => 'Dusun II',
                'uraian' => $kegiatan->uraian,
                'status_publikasi' => StatusPublikasi::Published->value,
                'existing_foto_ids' => $kegiatan->foto->pluck('id')->all(),
                'foto_baru' => [
                    UploadedFile::fake()->image('extra1.jpg', 800, 600),
                    UploadedFile::fake()->image('extra2.jpg', 800, 600),
                ],
            ])
            ->assertSessionHasErrors('foto_baru');
    }

    #[Test]
    public function pembuatan_slug_unik_dengan_with_trashed_menambahkan_sufiks_berurutan(): void
    {
        // Kegiatan pertama dibuat lalu di-soft-delete
        $first = KegiatanDesa::create([
            'judul' => 'Posyandu Balita Dusun I',
            'slug' => 'posyandu-balita-dusun-i',
            'kategori' => KategoriKegiatan::PosyanduKesehatan,
            'tanggal_kegiatan' => '2026-01-10',
            'lokasi_dusun' => 'Dusun I',
            'uraian' => 'Posyandu rutin Januari.',
            'status_publikasi' => StatusPublikasi::Draft,
            'created_by' => $this->operator->id,
        ]);
        $first->delete();
        $this->assertSoftDeleted('kegiatan_desa', ['id' => $first->id]);

        // Kegiatan kedua dengan judul persis sama harus menghasilkan slug `-2` karena yang pertama ada di withTrashed()
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Posyandu Balita Dusun I',
                'kategori' => KategoriKegiatan::PosyanduKesehatan->value,
                'tanggal_kegiatan' => '2026-02-10',
                'lokasi_dusun' => 'Dusun I',
                'uraian' => 'Posyandu rutin Februari.',
                'status_publikasi' => StatusPublikasi::Draft->value,
            ])
            ->assertRedirect();

        $second = KegiatanDesa::query()->latest('id')->firstOrFail();
        $this->assertSame('posyandu-balita-dusun-i-2', $second->slug);

        // Kegiatan ketiga dengan judul sama menghasilkan slug `-3`
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Posyandu Balita Dusun I',
                'kategori' => KategoriKegiatan::PosyanduKesehatan->value,
                'tanggal_kegiatan' => '2026-03-10',
                'lokasi_dusun' => 'Dusun I',
                'uraian' => 'Posyandu rutin Maret.',
                'status_publikasi' => StatusPublikasi::Draft->value,
            ])
            ->assertRedirect();

        $third = KegiatanDesa::query()->latest('id')->firstOrFail();
        $this->assertSame('posyandu-balita-dusun-i-3', $third->slug);
    }

    #[Test]
    public function transisi_draft_ke_published_dan_penghapusan_berkas_foto_dari_storage_serta_proteksi_rbac(): void
    {
        // Buat kegiatan Draft dengan 2 foto
        $this->actingAs($this->operator)
            ->post('/panel/sosial/kegiatan', [
                'judul' => 'Pelatihan Pengelolaan BUMDes Tamarenja',
                'kategori' => KategoriKegiatan::PelatihanBumdes->value,
                'tanggal_kegiatan' => '2026-03-18',
                'lokasi_dusun' => 'Balai Desa',
                'uraian' => 'Pelatihan pembukuan unit usaha desa.',
                'status_publikasi' => StatusPublikasi::Draft->value,
                'foto_baru' => [
                    UploadedFile::fake()->image('bumdes1.jpg', 1000, 700),
                    UploadedFile::fake()->image('bumdes2.jpg', 1000, 700),
                ],
            ])
            ->assertRedirect();

        $kegiatan = KegiatanDesa::query()->with('foto')->firstOrFail();
        $this->assertSame(StatusPublikasi::Draft, $kegiatan->status_publikasi);
        $this->assertCount(2, $kegiatan->foto);

        $fotoPertama = $kegiatan->foto[0];
        $fotoKedua = $kegiatan->foto[1];

        Storage::disk('public')->assertExists($fotoPertama->foto_path);
        Storage::disk('public')->assertExists($fotoKedua->foto_path);

        // 1. Transisi Draft -> Published
        $this->actingAs($this->operator)
            ->patch("/panel/sosial/kegiatan/{$kegiatan->id}/publikasi", [
                'status_publikasi' => StatusPublikasi::Published->value,
            ])
            ->assertRedirect();

        $kegiatan->refresh();
        $this->assertSame(StatusPublikasi::Published, $kegiatan->status_publikasi);
        $this->assertNotNull($kegiatan->published_at);

        // 2. Transisi Published -> Draft
        $this->actingAs($this->operator)
            ->patch("/panel/sosial/kegiatan/{$kegiatan->id}/publikasi", [
                'status_publikasi' => StatusPublikasi::Draft->value,
            ])
            ->assertRedirect();

        $kegiatan->refresh();
        $this->assertSame(StatusPublikasi::Draft, $kegiatan->status_publikasi);
        $this->assertNull($kegiatan->published_at);

        // 3. Update dengan menghapus foto pertama dari galeri -> berkas fisik foto pertama wajib terhapus dari storage
        $this->actingAs($this->operator)
            ->post("/panel/sosial/kegiatan/{$kegiatan->id}", [
                '_method' => 'PUT',
                'judul' => $kegiatan->judul,
                'kategori' => KategoriKegiatan::PelatihanBumdes->value,
                'tanggal_kegiatan' => '2026-03-18',
                'lokasi_dusun' => 'Balai Desa',
                'uraian' => $kegiatan->uraian,
                'status_publikasi' => StatusPublikasi::Published->value,
                'existing_foto_ids' => [$fotoKedua->id],
                'hapus_foto_ids' => [$fotoPertama->id],
            ])
            ->assertRedirect();

        Storage::disk('public')->assertMissing($fotoPertama->foto_path);
        Storage::disk('public')->assertExists($fotoKedua->foto_path);
        $this->assertSame(1, KegiatanDesaFoto::query()->findOrFail($fotoKedua->id)->urutan);

        // 4. Kepala Desa ditolak (403) pada aksi tulis kegiatan
        $this->actingAs($this->kades)
            ->get('/panel/sosial/kegiatan/buat')
            ->assertForbidden();

        $this->actingAs($this->kades)
            ->delete("/panel/sosial/kegiatan/{$kegiatan->id}")
            ->assertForbidden();
    }
}
