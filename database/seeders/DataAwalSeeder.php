<?php

namespace Database\Seeders;

use App\Enums\JenisDokumenApbdes;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
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
use App\Support\DummyAssetGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DataAwalSeeder extends Seeder
{
    /**
     * Tahun anggaran yang disimulasikan. Jika null, akan menggunakan tahun aktif saat ini.
     */
    protected ?int $targetTahun = null;

    public function __construct(?int $targetTahun = null)
    {
        $this->targetTahun = $targetTahun;
    }

    /**
     * Jalankan seeder untuk menyiapkan kondisi data awal / fresh periode baru.
     * Mempertahankan data master (Akun, Profil Desa, SOTK Perangkat Desa, Master Kependudukan)
     * dan mengosongkan seluruh data transaksional (APBDes, Proyek, Persuratan, Bansos, Kegiatan).
     */
    public function run(): void
    {
        $this->command?->info('Menyiapkan lingkungan data awal untuk simulasi periode baru...');

        // 1. Bersihkan seluruh data transaksional
        $this->cleanTransactionalData();

        // 2. Siapkan Akun Pengguna & Profil Desa
        $operator = $this->seedAccountsAndProfile();

        // 3. Siapkan Master SOTK Perangkat Desa & BPD
        $this->seedPerangkatDesa();

        // 4. Siapkan Master Data Kependudukan (KK & Penduduk)
        $this->seedMasterKependudukan();

        // 5. Inisialisasi APBDes Murni Awal Bersih (Pagu & Realisasi Rp 0)
        $this->seedFreshApbdes($operator);

        $this->command?->info('✓ Data awal berhasil disiapkan. Sistem siap digunakan untuk simulasi periode baru.');
    }

    /**
     * Bersihkan seluruh tabel data transaksional.
     */
    protected function cleanTransactionalData(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }

        ProyekPembangunan::query()->forceDelete();
        SuratKeluar::query()->forceDelete();
        SuratMasuk::query()->forceDelete();
        PenerimaBansos::query()->forceDelete();
        KegiatanDesaFoto::query()->forceDelete();
        KegiatanDesa::query()->forceDelete();
        DokumenPpid::query()->forceDelete();
        ApbdesRincian::query()->forceDelete();
        Apbdes::query()->forceDelete();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->command?->line('  - Data transaksional (APBDes, Proyek, Persuratan, Bansos, Kegiatan) telah dibersihkan.');
    }

    /**
     * Siapkan Akun Pengguna dan Profil Desa.
     */
    protected function seedAccountsAndProfile(): User
    {
        $existingOperator = User::query()->where('email', 'operator@desa.id')->first();
        $operator = User::query()->updateOrCreate(
            ['email' => 'operator@desa.id'],
            [
                'name' => $existingOperator?->name ?? 'Operator Desa Tamarenja',
                'password' => $existingOperator?->password ?? Hash::make('password'),
                'role' => UserRole::Operator,
                'is_active' => true,
                'email_verified_at' => $existingOperator?->email_verified_at ?? now(),
            ]
        );

        $existingKades = User::query()->where('email', 'kades@desa.id')->first();
        User::query()->updateOrCreate(
            ['email' => 'kades@desa.id'],
            [
                'name' => $existingKades?->name ?? 'Kepala Desa Tamarenja',
                'password' => $existingKades?->password ?? Hash::make('password'),
                'role' => UserRole::KepalaDesa,
                'is_active' => true,
                'email_verified_at' => $existingKades?->email_verified_at ?? now(),
            ]
        );

        $tahunAktif = $this->targetTahun ?? (int) date('Y');
        $existingProfil = ProfilDesa::query()->first();

        $logoPath = DummyAssetGenerator::putPng('logo/logo-desa-tamarenja.png', 'public');
        $fotoKadesPath = DummyAssetGenerator::putJpg('sotk/foto-kades-tamarenja.jpg', 'KEPALA DESA', 'public', 400, 500);

        ProfilDesa::query()->updateOrCreate(
            ['id' => $existingProfil?->id ?? 1],
            [
                'nama_desa' => $existingProfil?->nama_desa ?? 'Tamarenja',
                'kode_desa_surat' => $existingProfil?->kode_desa_surat ?? 'DS-TMR',
                'kecamatan' => $existingProfil?->kecamatan ?? 'Sindue Tobata',
                'kabupaten' => $existingProfil?->kabupaten ?? 'Donggala',
                'provinsi' => $existingProfil?->provinsi ?? 'Sulawesi Tengah',
                'kode_wilayah' => $existingProfil?->kode_wilayah ?? '72.03.19.2003',
                'daftar_dusun' => $existingProfil?->daftar_dusun ?? [
                    'Dusun I',
                    'Dusun II',
                    'Dusun III',
                    'Dusun IV',
                ],
                'alamat_kantor' => ($existingProfil && $existingProfil->alamat_kantor && ! str_contains($existingProfil->alamat_kantor, 'Jl. Trans Sulawesi'))
                    ? $existingProfil->alamat_kantor
                    : 'Jl. Hi. Paggaru No. 80 Tamarenja Kode Pos 94353',
                'logo_desa_path' => $logoPath,
                'foto_kepala_desa_path' => $fotoKadesPath,
                'jam_pelayanan' => $existingProfil?->jam_pelayanan ?? 'Senin - Jumat, 08.00 - 15.30 WITA',
                'email_desa' => $existingProfil?->email_desa ?? 'pemdes@tamarenja.desa.id',
                'no_wa_pelayanan' => $existingProfil?->no_wa_pelayanan ?? '6281245001122',
                'nama_kepala_desa' => ($existingProfil && $existingProfil->nama_kepala_desa !== 'H. Moh. Ridwan, S.Sos.')
                    ? $existingProfil->nama_kepala_desa
                    : 'Baswan Laramang',
                'nip_kepala_desa' => $existingProfil?->nip_kepala_desa,
                'sambutan_kepala_desa' => $existingProfil?->sambutan_kepala_desa ?? 'Selamat datang di Portal Resmi Pemerintah Desa Tamarenja sebagai wujud pelayanan cepat dan keterbukaan informasi publik bagi seluruh masyarakat.',
                'nama_sekdes' => ($existingProfil && $existingProfil->nama_sekdes !== 'Andi Saputra, S.AP.')
                    ? $existingProfil->nama_sekdes
                    : 'Arif Sandi',
                'nip_sekdes' => $existingProfil?->nip_sekdes ?? '198604122010011008',
                'visi' => $existingProfil?->visi ?? 'Terwujudnya Desa Tamarenja yang Mandiri, Transparan, Maju, dan Sejahtera Berlandaskan Gotong Royong.',
                'misi' => $existingProfil?->misi ?? [
                    'Meningkatkan kualitas pelayanan administrasi desa yang cepat, tepat, dan transparan.',
                    'Mempercepat pembangunan infrastruktur perdesaan yang merata di seluruh dusun.',
                    'Mendorong pemberdayaan ekonomi masyarakat tani, nelayan, dan UMKM desa.',
                    'Mewujudkan tata kelola anggaran desa dan penyaluran bantuan sosial yang berkeadilan.',
                ],
                'sejarah_singkat' => $existingProfil?->sejarah_singkat ?? 'Desa Tamarenja merupakan salah satu desa pesisir dan pertanian di Kecamatan Sindue Tobata, Kabupaten Donggala, Provinsi Sulawesi Tengah yang menjunjung tinggi budaya gotong royong.',
                'potensi_unggulan' => $existingProfil?->potensi_unggulan ?? 'Pertanian perkebunan kelapa & kakao, perikanan tangkap pesisir Selat Makassar, serta usaha mikro olahan hasil bumi.',
                'tahun_anggaran_aktif' => $tahunAktif,
            ]
        );

        $this->command?->line('  - Akun Pengguna (Operator, Kepala Desa) & Profil Desa siap.');

        return $operator;
    }

    /**
     * Siapkan 13 Aparatur Desa & BPD (SOTK).
     */
    protected function seedPerangkatDesa(): void
    {
        $daftarPerangkat = [
            ['urutan' => 1, 'nama' => 'Baswan Laramang', 'jabatan' => 'Kepala Desa', 'kelompok' => 'pimpinan', 'nip' => null],
            ['urutan' => 2, 'nama' => 'Arif Sandi', 'jabatan' => 'Sekretaris Desa', 'kelompok' => 'sekretariat', 'nip' => '198604122010011008'],
            ['urutan' => 3, 'nama' => 'Efendi', 'jabatan' => 'Kepala Urusan Keuangan', 'kelompok' => 'sekretariat', 'nip' => null],
            ['urutan' => 4, 'nama' => 'Andri', 'jabatan' => 'Kepala Urusan Tata Usaha dan Umum', 'kelompok' => 'sekretariat', 'nip' => null],
            ['urutan' => 5, 'nama' => 'Kisman', 'jabatan' => 'Kepala Urusan Perencanaan', 'kelompok' => 'sekretariat', 'nip' => null],
            ['urutan' => 6, 'nama' => 'Arifain', 'jabatan' => 'Kepala Seksi Pemerintahan', 'kelompok' => 'pelaksana_teknis', 'nip' => null],
            ['urutan' => 7, 'nama' => 'Alwin', 'jabatan' => 'Kepala Seksi Kesejahteraan', 'kelompok' => 'pelaksana_teknis', 'nip' => null],
            ['urutan' => 8, 'nama' => 'Sandra', 'jabatan' => 'Kepala Seksi Pelayanan', 'kelompok' => 'pelaksana_teknis', 'nip' => null],
            ['urutan' => 9, 'nama' => 'Sharil', 'jabatan' => 'Kepala Dusun I', 'kelompok' => 'kewilayahan_dusun', 'nip' => null],
            ['urutan' => 10, 'nama' => 'Moh. Irfan', 'jabatan' => 'Kepala Dusun II', 'kelompok' => 'kewilayahan_dusun', 'nip' => null],
            ['urutan' => 11, 'nama' => 'Taufik. E', 'jabatan' => 'Kepala Dusun III', 'kelompok' => 'kewilayahan_dusun', 'nip' => null],
            ['urutan' => 12, 'nama' => 'Drs. H. Mahmud Lamakarate', 'jabatan' => 'Ketua BPD', 'kelompok' => 'bpd', 'nip' => null],
            ['urutan' => 13, 'nama' => 'Hj. Rostina', 'jabatan' => 'Sekretaris BPD', 'kelompok' => 'bpd', 'nip' => null],
        ];

        foreach ($daftarPerangkat as $p) {
            $fotoSotk = DummyAssetGenerator::putJpg(
                "sotk/perangkat-{$p['urutan']}.jpg",
                strtoupper($p['jabatan']),
                'public',
                400,
                500
            );

            PerangkatDesa::query()->updateOrCreate(
                ['urutan' => $p['urutan']],
                [
                    'nama_lengkap' => $p['nama'],
                    'jabatan' => $p['jabatan'],
                    'kelompok_sotk' => $p['kelompok'],
                    'nip_niap' => $p['nip'],
                    'foto_path' => $fotoSotk,
                    'is_aktif' => true,
                ]
            );
        }

        $this->command?->line('  - Struktur Organisasi (13 Perangkat Desa & BPD) siap.');
    }

    /**
     * Siapkan Master Kependudukan (28 KK, ~100 Penduduk).
     */
    protected function seedMasterKependudukan(): void
    {
        $daftarDusun = ProfilDesa::singleton()?->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III', 'Dusun IV'];

        $namaKepala = [
            'Ahmad Fauzi', 'Baharuddin', 'Choirul Anwar', 'Darmawan', 'Edi Sucipto', 'Firmansyah',
            'Gunawan Wibowo', 'Hasanuddin', 'Irwan Setiawan', 'Joko Susilo', 'Kamaruddin', 'Lukman Hakim',
            'Muhammad Saleh', 'Nasrullah', 'Oki Pratama', 'Purnomo', 'Qomaruddin', 'Rahmat Hidayat',
            'Supardi', 'Taufik Ismail', 'Usman Ali', 'Viktor Tampubolon', 'Wahyudi', 'Yusuf Mansyur',
            'Zainal Abidin', 'Abdul Kadir', 'Bambang Sutrisno', 'Syamsul Bahri',
        ];

        $namaIstri = [
            'Siti Aminah', 'Nurhayati', 'Fatimah Zahra', 'Ratna Sari', 'Dewi Sartika', 'Sri Wahyuni',
            'Nurlaila', 'Hasnah', 'Indah Permata', 'Juariah', 'Khadijah', 'Lilis Suryani',
            'Maryam', 'Nurbaya', 'Oktaviani', 'Puji Astuti', 'Qoriah', 'Ramlah',
            'Sulastri', 'Tuti Alawiyah', 'Umi Kalsum', 'Veronika', 'Wati Ningsih', 'Yuniarti',
            'Zubaedah', 'Aisyah Putri', 'Bunga Citra', 'Sartika Dewi',
        ];

        for ($i = 1; $i <= 28; $i++) {
            $idx = $i - 1;
            $suffixKk = str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $dusun = $daftarDusun[$idx % count($daftarDusun)];

            $desil = match (true) {
                $i <= 6 => StatusKesejahteraan::DtsenDesil1,
                $i <= 12 => StatusKesejahteraan::DtsenDesil2,
                $i <= 18 => StatusKesejahteraan::DtsenDesil34,
                default => StatusKesejahteraan::NonPrioritas,
            };

            $kk = KartuKeluarga::withTrashed()->updateOrCreate(
                ['nomor_kk' => '720319010126' . $suffixKk],
                [
                    'dusun' => $dusun,
                    'rt' => str_pad((string) (($idx % 3) + 1), 3, '0', STR_PAD_LEFT),
                    'rw' => '001',
                    'alamat' => "Jl. Poros {$dusun} No. {$i}",
                    'status_kesejahteraan' => $desil,
                    'deleted_at' => null,
                ]
            );

            // Kepala Keluarga
            $usiaKepala = $i >= 25 ? 66 : 38;
            Penduduk::withTrashed()->updateOrCreate(
                ['nik' => '7203191001' . $suffixKk . '01'],
                [
                    'kartu_keluarga_id' => $kk->id,
                    'nama_lengkap' => $namaKepala[$idx],
                    'tempat_lahir' => 'Donggala',
                    'tanggal_lahir' => now()->subYears($usiaKepala)->subDays($i)->format('Y-m-d'),
                    'jenis_kelamin' => 'L',
                    'agama' => $i === 22 ? 'Kristen' : 'Islam',
                    'pendidikan' => $i <= 12 ? 'SLTP' : 'SLTA',
                    'pekerjaan' => $i % 3 === 0 ? 'Nelayan' : 'Petani/Pekebun',
                    'status_perkawinan' => 'kawin',
                    'hubungan_keluarga' => 'kepala_keluarga',
                    'status_kesejahteraan' => $desil,
                    'status_kependudukan' => StatusKependudukan::Aktif,
                    'tanggal_mutasi' => null,
                    'catatan_mutasi' => null,
                    'deleted_at' => null,
                ]
            );

            // Istri
            Penduduk::withTrashed()->updateOrCreate(
                ['nik' => '7203195002' . $suffixKk . '02'],
                [
                    'kartu_keluarga_id' => $kk->id,
                    'nama_lengkap' => $namaIstri[$idx],
                    'tempat_lahir' => 'Palu',
                    'tanggal_lahir' => now()->subYears(33)->subDays($i)->format('Y-m-d'),
                    'jenis_kelamin' => 'P',
                    'agama' => $i === 22 ? 'Kristen' : 'Islam',
                    'pendidikan' => 'SLTA',
                    'pekerjaan' => 'Mengurus Rumah Tangga',
                    'status_perkawinan' => 'kawin',
                    'hubungan_keluarga' => 'istri',
                    'status_kesejahteraan' => $desil,
                    'status_kependudukan' => StatusKependudukan::Aktif,
                    'tanggal_mutasi' => null,
                    'catatan_mutasi' => null,
                    'deleted_at' => null,
                ]
            );

            // Anak 1
            $usiaAnak1 = ($i % 2 === 0) ? 19 : 10;
            $statusAnak1 = match ($i) {
                20, 21 => StatusKependudukan::MeninggalDunia,
                22, 23 => StatusKependudukan::PindahKeluar,
                default => StatusKependudukan::Aktif,
            };

            Penduduk::withTrashed()->updateOrCreate(
                ['nik' => '7203191503' . $suffixKk . '03'],
                [
                    'kartu_keluarga_id' => $kk->id,
                    'nama_lengkap' => 'Putra Pratama ' . $namaKepala[$idx],
                    'tempat_lahir' => 'Tamarenja',
                    'tanggal_lahir' => now()->subYears($usiaAnak1)->subDays($i)->format('Y-m-d'),
                    'jenis_kelamin' => 'L',
                    'agama' => $i === 22 ? 'Kristen' : 'Islam',
                    'pendidikan' => $usiaAnak1 >= 18 ? 'SLTA' : 'SD',
                    'pekerjaan' => 'Pelajar/Mahasiswa',
                    'status_perkawinan' => 'belum_kawin',
                    'hubungan_keluarga' => 'anak',
                    'status_kesejahteraan' => $desil,
                    'status_kependudukan' => $statusAnak1,
                    'tanggal_mutasi' => $statusAnak1 === StatusKependudukan::Aktif ? null : now()->subDays(15)->format('Y-m-d'),
                    'catatan_mutasi' => match ($statusAnak1) {
                        StatusKependudukan::MeninggalDunia => 'Meninggal dunia karena sakit',
                        StatusKependudukan::PindahKeluar => 'Pindah domisili ke Kota Palu',
                        default => null,
                    },
                    'deleted_at' => null,
                ]
            );

            // Anggota 4 (khusus KK 1..16 agar total genap 100 warga)
            if ($i <= 16) {
                $isBalita = $i <= 10;
                $usiaKe4 = $isBalita ? 2 : 68;
                Penduduk::withTrashed()->updateOrCreate(
                    ['nik' => '7203195504' . $suffixKk . '04'],
                    [
                        'kartu_keluarga_id' => $kk->id,
                        'nama_lengkap' => ($isBalita ? 'Alya Zahra ' : 'Hj. Halimah ') . $namaKepala[$idx],
                        'tempat_lahir' => 'Tamarenja',
                        'tanggal_lahir' => now()->subYears($usiaKe4)->subDays($i)->format('Y-m-d'),
                        'jenis_kelamin' => 'P',
                        'agama' => 'Islam',
                        'pendidikan' => $isBalita ? 'Tidak/Belum Sekolah' : 'SD',
                        'pekerjaan' => $isBalita ? 'Belum/Tidak Bekerja' : 'Mengurus Rumah Tangga',
                        'status_perkawinan' => $isBalita ? 'belum_kawin' : 'cerai_mati',
                        'hubungan_keluarga' => $isBalita ? 'anak' : 'lainnya',
                        'status_kesejahteraan' => $desil,
                        'status_kependudukan' => StatusKependudukan::Aktif,
                        'tanggal_mutasi' => null,
                        'catatan_mutasi' => null,
                        'deleted_at' => null,
                    ]
                );
            }
        }

        $this->command?->line('  - Master Kependudukan (28 Kartu Keluarga, 100 Penduduk terdaftar) siap.');
    }

    /**
     * Inisialisasi APBDes Murni Awal dalam kondisi bersih (Pagu & Realisasi Rp 0).
     */
    protected function seedFreshApbdes(User $operator): void
    {
        $profil = ProfilDesa::singleton();
        $tahunAktif = $this->targetTahun ?? $profil?->tahun_anggaran_aktif ?? (int) date('Y');

        $apbdes = Apbdes::query()->create([
            'tahun_anggaran' => $tahunAktif,
            'jenis_dokumen' => JenisDokumenApbdes::Murni,
            'is_aktif_publik' => true,
            'is_terkunci' => false,
            'nomor_perdes' => null,
            'tanggal_penetapan' => null,
            'created_by' => $operator->id,
        ]);

        foreach (ApbdesPosCatalog::all() as $pos) {
            ApbdesRincian::query()->create([
                'apbdes_id' => $apbdes->id,
                'kelompok' => $pos['kelompok'],
                'kode_pos' => $pos['kode_pos'],
                'nama_pos' => $pos['nama_pos'],
                'is_otomatis_modul_1' => (bool) $pos['is_otomatis_modul_1'],
                'pagu_anggaran' => 0,
                'realisasi_tahap_1' => 0,
                'realisasi_tahap_2' => 0,
                'realisasi_tahap_3' => 0,
                'total_realisasi' => 0,
                'urutan' => (int) $pos['urutan'],
            ]);
        }

        $this->command?->line("  - Dokumen APBDes Murni TA {$tahunAktif} disiapkan dalam kondisi bersih (14 pos bernilai Rp 0).");
    }
}
