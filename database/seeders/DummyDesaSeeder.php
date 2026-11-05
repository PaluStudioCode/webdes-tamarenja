<?php

namespace Database\Seeders;

use App\Enums\JenisBansos;
use App\Enums\JenisDokumenApbdes;
use App\Enums\JenisSurat;
use App\Enums\KategoriKegiatan;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusProyek;
use App\Enums\StatusPublikasi;
use App\Enums\StatusSuratKeluar;
use App\Enums\SumberDanaProyek;
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

class DummyDesaSeeder extends Seeder
{
    /**
     * Seeder data demonstrasi realistis khusus environment local/staging.
     * Mematuhi seluruh invarian bisnis PRD 02 & 03 serta 100% idempoten.
     */
    public function run(): void
    {
        if (ProfilDesa::query()->count() === 0 || User::query()->where('email', 'operator@desa.id')->doesntExist()) {
            $this->call(EssentialDesaSeeder::class);
        }

        $profil = ProfilDesa::singleton();
        $operator = User::query()->where('email', 'operator@desa.id')->firstOrFail();
        $tahunAktif = $profil->tahun_anggaran_aktif;
        $tahunLalu = $tahunAktif - 1;

        // 1. Aset Logo Desa (.png) & Foto Kades (.jpg)
        $logoPath = DummyAssetGenerator::putPng('logo/logo-desa-tamarenja.png', 'public');
        $fotoKadesPath = DummyAssetGenerator::putJpg('sotk/foto-kades-tamarenja.jpg', 'KEPALA DESA', 'public', 400, 500);

        $profil->update([
            'logo_desa_path' => $logoPath,
            'foto_kepala_desa_path' => $fotoKadesPath,
        ]);

        DB::transaction(function () use ($profil, $operator, $tahunAktif, $tahunLalu): void {
            $kkRecords = $this->seedKependudukan($profil->daftar_dusun ?? ['Dusun I', 'Dusun II', 'Dusun III', 'Dusun IV']);
            $this->seedProyekDanApbdes($operator, $tahunLalu, $tahunAktif);
            $this->seedPersuratan($profil, $operator, $kkRecords, $tahunAktif);
            $this->seedBansos($operator, $kkRecords, $tahunAktif);
            $this->seedKegiatanSotkDanPpid($operator, $tahunAktif);
        });
    }

    /**
     * 28 KK (~100 penduduk) tersebar di dusun resmi, mencakup 5 kelompok usia,
     * 4 kategori DTSEN, serta sampel mutasi meninggal_dunia dan pindah_keluar.
     *
     * @param list<string> $daftarDusun
     * @return array<int, array{kk:KartuKeluarga, kepala:Penduduk, anggota:list<Penduduk>}>
     */
    private function seedKependudukan(array $daftarDusun): array
    {
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

        $kkMap = [];

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
                    'deleted_at' => null,
                ]
            );

            $anggotaList = [];

            // Anggota 1: Kepala Keluarga (25-59 thn Produktif, atau 60+ Lansia pada KK 25..28)
            $usiaKepala = $i >= 25 ? 66 : 38;
            $kepala = Penduduk::withTrashed()->updateOrCreate(
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
            $anggotaList[] = $kepala;

            // Anggota 2: Istri (25-59 thn Produktif)
            $istri = Penduduk::withTrashed()->updateOrCreate(
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
            $anggotaList[] = $istri;

            // Anggota 3: Variasi kelompok usia (Anak 5-14 thn / Pemuda 15-24 thn) & sampel mutasi di KK 20..23
            $usiaAnak1 = ($i % 2 === 0) ? 19 : 10;
            $statusAnak1 = match ($i) {
                20, 21 => StatusKependudukan::MeninggalDunia,
                22, 23 => StatusKependudukan::PindahKeluar,
                default => StatusKependudukan::Aktif,
            };

            $anak1 = Penduduk::withTrashed()->updateOrCreate(
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
            $anggotaList[] = $anak1;

            // Anggota 4 (khusus KK 1..16 agar total genap 100 warga): Balita (0-4 thn) atau Lansia (60+ thn)
            if ($i <= 16) {
                $isBalita = $i <= 10;
                $usiaKe4 = $isBalita ? 2 : 68;
                $anggota4 = Penduduk::withTrashed()->updateOrCreate(
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
                $anggotaList[] = $anggota4;
            }

            $kkMap[$i] = [
                'kk' => $kk,
                'kepala' => $kepala,
                'anggota' => $anggotaList,
            ];
        }

        return $kkMap;
    }

    /**
     * 6 Proyek Fisik lintas tahun + 3 Dokumen APBDes (Tahun Lalu Murni terkunci + Perubahan,
     * Tahun Aktif Murni) dengan bidang_2_fisik == SUM proyek & selisih keseimbangan Rp 0.
     */
    private function seedProyekDanApbdes(User $operator, int $tahunLalu, int $tahunAktif): void
    {
        // --- PROYEK FISIK TAHUN LALU (2 proyek selesai = Pagu 300jt, Realisasi 298.5jt) ---
        $proyekLalu1Foto0 = DummyAssetGenerator::putJpg("proyek/{$tahunLalu}/jut-dusun1-0.jpg", 'JUT DUSUN I - 0%');
        $proyekLalu1Foto50 = DummyAssetGenerator::putJpg("proyek/{$tahunLalu}/jut-dusun1-50.jpg", 'JUT DUSUN I - 50%');
        $proyekLalu1Foto100 = DummyAssetGenerator::putJpg("proyek/{$tahunLalu}/jut-dusun1-100.jpg", 'JUT DUSUN I - 100%', 'public', 800, 500, [5, 150, 105]);

        ProyekPembangunan::withTrashed()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunLalu,
                'nama_kegiatan' => 'Pembangunan Jalan Usaha Tani Dusun I',
            ],
            [
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 180_000_000,
                'realisasi_anggaran' => 180_000_000,
                'sumber_dana' => SumberDanaProyek::Dds,
                'volume_fisik' => '400 m x 3 m',
                'waktu_pelaksanaan' => '90 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja',
                'latitude' => -0.4511200,
                'longitude' => 119.8114500,
                'progres_fisik' => 100,
                'status_proyek' => StatusProyek::Selesai,
                'foto_0_path' => $proyekLalu1Foto0,
                'foto_50_path' => $proyekLalu1Foto50,
                'foto_100_path' => $proyekLalu1Foto100,
                'catatan_kendala' => null,
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );

        $proyekLalu2Foto0 = DummyAssetGenerator::putJpg("proyek/{$tahunLalu}/drainase-dusun2-0.jpg", 'DRAINASE DUSUN II - 0%');
        $proyekLalu2Foto50 = DummyAssetGenerator::putJpg("proyek/{$tahunLalu}/drainase-dusun2-50.jpg", 'DRAINASE DUSUN II - 50%');
        $proyekLalu2Foto100 = DummyAssetGenerator::putJpg("proyek/{$tahunLalu}/drainase-dusun2-100.jpg", 'DRAINASE DUSUN II - 100%', 'public', 800, 500, [5, 150, 105]);

        ProyekPembangunan::withTrashed()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunLalu,
                'nama_kegiatan' => 'Rehabilitasi Saluran Drainase Lingkungan Dusun II',
            ],
            [
                'lokasi_dusun' => 'Dusun II',
                'pagu_anggaran' => 120_000_000,
                'realisasi_anggaran' => 118_500_000,
                'sumber_dana' => SumberDanaProyek::Dds,
                'volume_fisik' => '280 Meter',
                'waktu_pelaksanaan' => '60 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja',
                'latitude' => -0.4528500,
                'longitude' => 119.8132100,
                'progres_fisik' => 100,
                'status_proyek' => StatusProyek::Selesai,
                'foto_0_path' => $proyekLalu2Foto0,
                'foto_50_path' => $proyekLalu2Foto50,
                'foto_100_path' => $proyekLalu2Foto100,
                'catatan_kendala' => 'Efisiensi sisa material Rp 1.500.000 menjadi SiLPA.',
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );

        // --- PROYEK FISIK TAHUN AKTIF (4 proyek variasi 0%, 35%, 65%, 100% = Pagu 550jt, Realisasi 325jt) ---
        // 1) Progres 0% (belum_dimulai)
        ProyekPembangunan::withTrashed()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => 'Pembangunan Poskamling Terpadu Dusun IV',
            ],
            [
                'lokasi_dusun' => 'Dusun IV',
                'pagu_anggaran' => 65_000_000,
                'realisasi_anggaran' => 0,
                'sumber_dana' => SumberDanaProyek::Add,
                'volume_fisik' => '1 Unit (4 m x 6 m)',
                'waktu_pelaksanaan' => '45 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja',
                'latitude' => -0.4561000,
                'longitude' => 119.8168000,
                'progres_fisik' => 0,
                'status_proyek' => StatusProyek::BelumDimulai,
                'foto_0_path' => null,
                'foto_50_path' => null,
                'foto_100_path' => null,
                'catatan_kendala' => 'Menunggu pencairan ADD Tahap berikutnya.',
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );

        // 2) Progres 35% (wajib foto_0_path)
        $talud0 = DummyAssetGenerator::putJpg("proyek/{$tahunAktif}/talud-dusun3-0.jpg", 'TALUD DUSUN III - 0%', 'public', 800, 500, [180, 83, 9]);
        ProyekPembangunan::withTrashed()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => 'Pembangunan Talud Penahan Tanah Dusun III',
            ],
            [
                'lokasi_dusun' => 'Dusun III',
                'pagu_anggaran' => 150_000_000,
                'realisasi_anggaran' => 60_000_000,
                'sumber_dana' => SumberDanaProyek::Dds,
                'volume_fisik' => '120 Meter',
                'waktu_pelaksanaan' => '75 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja / PKTD',
                'latitude' => -0.4543200,
                'longitude' => 119.8149000,
                'progres_fisik' => 35,
                'status_proyek' => StatusProyek::SedangDikerjakan,
                'foto_0_path' => $talud0,
                'foto_50_path' => null,
                'foto_100_path' => null,
                'catatan_kendala' => 'Curah hujan sore hari sedikit memperlambat penggalian fondasi.',
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );

        // 3) Progres 65% (wajib foto_0_path & foto_50_path)
        $rabat0 = DummyAssetGenerator::putJpg("proyek/{$tahunAktif}/rabat-dusun2-0.jpg", 'RABAT BETON DUSUN II - 0%');
        $rabat50 = DummyAssetGenerator::putJpg("proyek/{$tahunAktif}/rabat-dusun2-50.jpg", 'RABAT BETON DUSUN II - 50%', 'public', 800, 500, [217, 119, 6]);
        ProyekPembangunan::withTrashed()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => 'Peningkatan Rabat Beton Jalan Lingkungan Dusun II',
            ],
            [
                'lokasi_dusun' => 'Dusun II',
                'pagu_anggaran' => 210_000_000,
                'realisasi_anggaran' => 140_000_000,
                'sumber_dana' => SumberDanaProyek::Dds,
                'volume_fisik' => '350 m x 2,5 m',
                'waktu_pelaksanaan' => '90 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja / PKTD',
                'latitude' => -0.4530500,
                'longitude' => 119.8126000,
                'progres_fisik' => 65,
                'status_proyek' => StatusProyek::SedangDikerjakan,
                'foto_0_path' => $rabat0,
                'foto_50_path' => $rabat50,
                'foto_100_path' => null,
                'catatan_kendala' => null,
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );

        // 4) Progres 100% (wajib foto_0_path, foto_50_path, & foto_100_path)
        $sab0 = DummyAssetGenerator::putJpg("proyek/{$tahunAktif}/sab-dusun1-0.jpg", 'AIR BERSIH DUSUN I - 0%');
        $sab50 = DummyAssetGenerator::putJpg("proyek/{$tahunAktif}/sab-dusun1-50.jpg", 'AIR BERSIH DUSUN I - 50%');
        $sab100 = DummyAssetGenerator::putJpg("proyek/{$tahunAktif}/sab-dusun1-100.jpg", 'AIR BERSIH DUSUN I - 100%', 'public', 800, 500, [5, 150, 105]);
        ProyekPembangunan::withTrashed()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunAktif,
                'nama_kegiatan' => 'Rehabilitasi Jaringan Sarana Air Bersih Dusun I',
            ],
            [
                'lokasi_dusun' => 'Dusun I',
                'pagu_anggaran' => 125_000_000,
                'realisasi_anggaran' => 125_000_000,
                'sumber_dana' => SumberDanaProyek::BantuanKeuangan,
                'volume_fisik' => '1 Paket Perpipaan & Bak Penampung',
                'waktu_pelaksanaan' => '60 Hari Kalender',
                'pelaksana_kegiatan' => 'TPK Desa Tamarenja',
                'latitude' => -0.4516000,
                'longitude' => 119.8109000,
                'progres_fisik' => 100,
                'status_proyek' => StatusProyek::Selesai,
                'foto_0_path' => $sab0,
                'foto_50_path' => $sab50,
                'foto_100_path' => $sab100,
                'catatan_kendala' => null,
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );

        // --- DOKUMEN APBDES TAHUN LALU: MURNI (Arsip Terkunci, Keseimbangan = Rp 0) ---
        $apbdesLaluMurni = Apbdes::query()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunLalu,
                'jenis_dokumen' => JenisDokumenApbdes::Murni,
            ],
            [
                'is_aktif_publik' => false,
                'is_terkunci' => true,
                'nomor_perdes' => "Perdes No. 01 Tahun {$tahunLalu}",
                'tanggal_penetapan' => "{$tahunLalu}-01-12",
                'created_by' => $operator->id,
            ]
        );

        // Total Pendapatan = 1.240M + SiLPA 10M - Penyertaan Modal 50M = Belanja 1.200M (Selisih = 0)
        $this->seedApbdesRincian($apbdesLaluMurni, [
            'dds' => [800_000_000, 320_000_000, 320_000_000, 160_000_000],
            'add' => [350_000_000, 150_000_000, 100_000_000, 100_000_000],
            'pades' => [15_000_000, 15_000_000, 0, 0],
            'bhprd' => [20_000_000, 20_000_000, 0, 0],
            'bankeu' => [50_000_000, 50_000_000, 0, 0],
            'pendapatan_lain' => [5_000_000, 5_000_000, 0, 0],
            'bidang_1' => [420_000_000, 170_000_000, 140_000_000, 105_000_000],
            'bidang_2_fisik' => [280_000_000, 280_000_000, 0, 0],
            'bidang_2_non_fisik' => [120_000_000, 50_000_000, 40_000_000, 28_000_000],
            'bidang_3' => [100_000_000, 40_000_000, 35_000_000, 23_000_000],
            'bidang_4' => [130_000_000, 50_000_000, 50_000_000, 28_000_000],
            'bidang_5' => [150_000_000, 50_000_000, 50_000_000, 50_000_000],
            'silpa' => [10_000_000, 10_000_000, 0, 0],
            'penyertaan_modal' => [50_000_000, 50_000_000, 0, 0],
        ]);

        // --- DOKUMEN APBDES TAHUN LALU: PERUBAHAN (Aktif Publik Tahun Lalu, bidang_2_fisik = 300M / 298.5M, Keseimbangan = Rp 0) ---
        $apbdesLaluPerubahan = Apbdes::query()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunLalu,
                'jenis_dokumen' => JenisDokumenApbdes::Perubahan,
            ],
            [
                'is_aktif_publik' => true,
                'is_terkunci' => false,
                'nomor_perdes' => "Perdes No. 05 Tahun {$tahunLalu}",
                'tanggal_penetapan' => "{$tahunLalu}-09-20",
                'created_by' => $operator->id,
            ]
        );

        // Total Pendapatan = 1.260M + SiLPA 15M - Penyertaan Modal 50M = Belanja 1.225M (Selisih = 0)
        $this->seedApbdesRincian($apbdesLaluPerubahan, [
            'dds' => [800_000_000, 320_000_000, 320_000_000, 160_000_000],
            'add' => [350_000_000, 150_000_000, 100_000_000, 100_000_000],
            'pades' => [15_000_000, 15_000_000, 0, 0],
            'bhprd' => [20_000_000, 20_000_000, 0, 0],
            'bankeu' => [70_000_000, 70_000_000, 0, 0],
            'pendapatan_lain' => [5_000_000, 5_000_000, 0, 0],
            'bidang_1' => [425_000_000, 170_000_000, 145_000_000, 107_000_000],
            'bidang_2_fisik' => [300_000_000, 298_500_000, 0, 0],
            'bidang_2_non_fisik' => [120_000_000, 50_000_000, 40_000_000, 29_000_000],
            'bidang_3' => [100_000_000, 40_000_000, 35_000_000, 24_000_000],
            'bidang_4' => [130_000_000, 50_000_000, 50_000_000, 29_000_000],
            'bidang_5' => [150_000_000, 50_000_000, 50_000_000, 50_000_000],
            'silpa' => [15_000_000, 15_000_000, 0, 0],
            'penyertaan_modal' => [50_000_000, 50_000_000, 0, 0],
        ]);

        // --- DOKUMEN APBDES TAHUN AKTIF: MURNI (bidang_2_fisik = 550M / 325M, Keseimbangan = Rp 0) ---
        $apbdesAktifMurni = Apbdes::query()->updateOrCreate(
            [
                'tahun_anggaran' => $tahunAktif,
                'jenis_dokumen' => JenisDokumenApbdes::Murni,
            ],
            [
                'is_aktif_publik' => true,
                'is_terkunci' => false,
                'nomor_perdes' => "Perdes No. 01 Tahun {$tahunAktif}",
                'tanggal_penetapan' => "{$tahunAktif}-01-15",
                'created_by' => $operator->id,
            ]
        );

        // Total Pendapatan = 1.460M + SiLPA 20M - Penyertaan Modal 50M = Belanja 1.430M (Selisih = 0)
        $this->seedApbdesRincian($apbdesAktifMurni, [
            'dds' => [900_000_000, 360_000_000, 360_000_000, 0],
            'add' => [380_000_000, 190_000_000, 100_000_000, 0],
            'pades' => [20_000_000, 12_000_000, 5_000_000, 0],
            'bhprd' => [25_000_000, 15_000_000, 0, 0],
            'bankeu' => [125_000_000, 125_000_000, 0, 0],
            'pendapatan_lain' => [10_000_000, 5_000_000, 0, 0],
            'bidang_1' => [450_000_000, 180_000_000, 150_000_000, 0],
            'bidang_2_fisik' => [550_000_000, 325_000_000, 0, 0],
            'bidang_2_non_fisik' => [110_000_000, 45_000_000, 30_000_000, 0],
            'bidang_3' => [95_000_000, 40_000_000, 25_000_000, 0],
            'bidang_4' => [105_000_000, 50_000_000, 20_000_000, 0],
            'bidang_5' => [120_000_000, 40_000_000, 40_000_000, 0],
            'silpa' => [20_000_000, 20_000_000, 0, 0],
            'penyertaan_modal' => [50_000_000, 50_000_000, 0, 0],
        ]);
    }

    /**
     * @param array<string, array{0:int, 1:int, 2:int, 3:int}> $nilaiPerKode
     */
    private function seedApbdesRincian(Apbdes $apbdes, array $nilaiPerKode): void
    {
        foreach (ApbdesPosCatalog::all() as $pos) {
            [$pagu, $t1, $t2, $t3] = $nilaiPerKode[$pos['kode_pos']] ?? [0, 0, 0, 0];

            ApbdesRincian::query()->updateOrCreate(
                [
                    'apbdes_id' => $apbdes->id,
                    'kode_pos' => $pos['kode_pos'],
                ],
                [
                    'kelompok' => $pos['kelompok'],
                    'nama_pos' => $pos['nama_pos'],
                    'is_otomatis_modul_1' => $pos['is_otomatis_modul_1'],
                    'urutan' => $pos['urutan'],
                    'pagu_anggaran' => $pagu,
                    'realisasi_tahap_1' => $t1,
                    'realisasi_tahap_2' => $t2,
                    'realisasi_tahap_3' => $t3,
                    'total_realisasi' => $t1 + $t2 + $t3,
                ]
            );
        }
    }

    /**
     * Surat Keluar ber-nomor urut 1..5 utuh tanpa bolong + 3 antrean online + 3 Surat Masuk ber-scan PDF.
     *
     * @param array<int, array{kk:KartuKeluarga, kepala:Penduduk, anggota:list<Penduduk>}> $kkRecords
     */
    private function seedPersuratan(ProfilDesa $profil, User $operator, array $kkRecords, int $tahunAktif): void
    {
        $kodeDesa = $profil->kode_desa_surat;
        $wargaWafatKk20 = $kkRecords[20]['anggota'][2];

        $agendaKeluar = [
            1 => [
                'qr_token' => '11111111-1111-4111-8111-111111111101',
                'penduduk_id' => $kkRecords[1]['kepala']->id,
                'sumber_pengajuan' => 'loket',
                'jenis_surat' => JenisSurat::Sku,
                'kode_klasifikasi' => '500',
                'nomor_surat' => "500 / 001 / {$kodeDesa} / X / {$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-05",
                'keperluan' => 'Persyaratan Pengajuan Kredit Usaha Rakyat (KUR) BRI',
                'data_khusus' => [
                    'nama_usaha' => 'Warung Sembako Berkah Tamarenja',
                    'jenis_usaha' => 'Perdagangan Eceran',
                    'bidang_usaha' => 'Sembako & Kebutuhan Harian',
                    'lama_usaha' => '4 Tahun',
                    'alamat_usaha' => 'Jl. Poros Dusun I Desa Tamarenja',
                ],
                'no_wa_pemohon' => '6281245100001',
                'opsi_penandatangan' => 'kepala_desa',
                'nama_penandatangan' => $profil->nama_kepala_desa,
                'jabatan_penandatangan' => 'Kepala Desa ' . $profil->nama_desa,
                'nip_penandatangan' => $profil->nip_kepala_desa,
                'status' => StatusSuratKeluar::Terbit,
                'alasan_pembatalan' => null,
            ],
            2 => [
                'qr_token' => '11111111-1111-4111-8111-111111111102',
                'penduduk_id' => $kkRecords[2]['kepala']->id,
                'sumber_pengajuan' => 'online',
                'jenis_surat' => JenisSurat::Domisili,
                'kode_klasifikasi' => '470',
                'nomor_surat' => "470 / 002 / {$kodeDesa} / X / {$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-12",
                'keperluan' => 'Pembukaan Rekening Tabungan Bank Sulteng',
                'data_khusus' => [
                    'sejak_tahun' => 2012,
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
                'no_wa_pemohon' => '6281245100002',
                'opsi_penandatangan' => 'sekdes_an',
                'nama_penandatangan' => $profil->nama_sekdes,
                'jabatan_penandatangan' => 'a.n. Kepala Desa ' . $profil->nama_desa . ' - Sekretaris Desa',
                'nip_penandatangan' => $profil->nip_sekdes,
                'status' => StatusSuratKeluar::Terbit,
                'alasan_pembatalan' => null,
            ],
            3 => [
                'qr_token' => '11111111-1111-4111-8111-111111111103',
                'penduduk_id' => $wargaWafatKk20->id,
                'sumber_pengajuan' => 'loket',
                'jenis_surat' => JenisSurat::Kematian,
                'kode_klasifikasi' => '474.3',
                'nomor_surat' => "474.3 / 003 / {$kodeDesa} / X / {$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-20",
                'keperluan' => 'Pengurusan Akta Kematian Disdukcapil Kab. Donggala',
                'data_khusus' => [
                    'hari_meninggal' => 'Senin',
                    'tanggal_meninggal' => $wargaWafatKk20->tanggal_mutasi?->format('Y-m-d') ?? "{$tahunAktif}-10-19",
                    'jam_meninggal' => '06:30 WITA',
                    'tempat_meninggal' => 'Rumah Kediaman Desa Tamarenja',
                    'penyebab_meninggal' => 'Sakit',
                    'lokasi_makam' => 'TPU Desa Tamarenja Dusun IV',
                    'nama_pelapor' => $kkRecords[20]['kepala']->nama_lengkap,
                    'nik_pelapor' => $kkRecords[20]['kepala']->nik,
                    'hubungan_pelapor' => 'Ayah Kandung',
                ],
                'no_wa_pemohon' => '6281245100020',
                'opsi_penandatangan' => 'kepala_desa',
                'nama_penandatangan' => $profil->nama_kepala_desa,
                'jabatan_penandatangan' => 'Kepala Desa ' . $profil->nama_desa,
                'nip_penandatangan' => $profil->nip_kepala_desa,
                'status' => StatusSuratKeluar::Terbit,
                'alasan_pembatalan' => null,
            ],
            4 => [
                'qr_token' => '11111111-1111-4111-8111-111111111104',
                'penduduk_id' => $kkRecords[3]['kepala']->id,
                'sumber_pengajuan' => 'loket',
                'jenis_surat' => JenisSurat::Sktm,
                'kode_klasifikasi' => '400',
                'nomor_surat' => "400 / 004 / {$kodeDesa} / X / {$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-25",
                'keperluan' => 'Keringanan Biaya Pendidikan Sekolah',
                'data_khusus' => [
                    'penghasilan_rata_rata' => 900_000,
                    'jumlah_tanggungan' => 3,
                    'nama_tanggungan' => 'Putra Pratama Choirul Anwar',
                ],
                'no_wa_pemohon' => '6281245100003',
                'opsi_penandatangan' => 'kepala_desa',
                'nama_penandatangan' => $profil->nama_kepala_desa,
                'jabatan_penandatangan' => 'Kepala Desa ' . $profil->nama_desa,
                'nip_penandatangan' => $profil->nip_kepala_desa,
                'status' => StatusSuratKeluar::Dibatalkan,
                'alasan_pembatalan' => 'Terdapat kekeliruan penulisan nama sekolah tujuan oleh pemohon.',
            ],
            5 => [
                'qr_token' => '11111111-1111-4111-8111-111111111105',
                'penduduk_id' => $kkRecords[4]['kepala']->id,
                'sumber_pengajuan' => 'loket',
                'jenis_surat' => JenisSurat::PengantarSkck,
                'kode_klasifikasi' => '300',
                'nomor_surat' => "300 / 005 / {$kodeDesa} / XI / {$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-11-02",
                'keperluan' => 'Melamar Pekerjaan Swasta',
                'data_khusus' => [
                    'catatan_kelakuan' => 'Berkelakuan baik dan tidak pernah terlibat tindak pidana.',
                    'tujuan_skck' => 'Polsek Sindue / Polres Donggala',
                ],
                'no_wa_pemohon' => '6281245100004',
                'opsi_penandatangan' => 'sekdes_an',
                'nama_penandatangan' => $profil->nama_sekdes,
                'jabatan_penandatangan' => 'a.n. Kepala Desa ' . $profil->nama_desa . ' - Sekretaris Desa',
                'nip_penandatangan' => $profil->nip_sekdes,
                'status' => StatusSuratKeluar::Terbit,
                'alasan_pembatalan' => null,
            ],
        ];

        foreach ($agendaKeluar as $nomorUrut => $item) {
            SuratKeluar::withTrashed()->updateOrCreate(
                [
                    'tahun_surat' => $tahunAktif,
                    'nomor_urut' => $nomorUrut,
                ],
                array_merge($item, [
                    'alasan_penolakan' => null,
                    'processed_by' => $operator->id,
                    'deleted_at' => null,
                ])
            );
        }

        // 3 Antrean Online Menunggu Verifikasi (ber-WA 628...)
        $antreanOnline = [
            [
                'penduduk_id' => $kkRecords[5]['kepala']->id,
                'jenis_surat' => JenisSurat::Sktm,
                'kode_klasifikasi' => '400',
                'keperluan' => 'Pengajuan Beasiswa PIP Anak Sekolah',
                'data_khusus' => [
                    'penghasilan_rata_rata' => 850_000,
                    'jumlah_tanggungan' => 3,
                    'nama_tanggungan' => 'Putra Pratama Edi Sucipto',
                ],
                'no_wa_pemohon' => '6281345200005',
            ],
            [
                'penduduk_id' => $kkRecords[6]['kepala']->id,
                'jenis_surat' => JenisSurat::Sku,
                'kode_klasifikasi' => '500',
                'keperluan' => 'Kelengkapan Administrasi Bantuan UMKM Perikanan',
                'data_khusus' => [
                    'nama_usaha' => 'Nelayan Mandiri Bahari',
                    'jenis_usaha' => 'Perikanan Tangkap',
                    'bidang_usaha' => 'Hasil Laut Segar',
                    'lama_usaha' => '6 Tahun',
                    'alamat_usaha' => 'Pesisir Dusun II Desa Tamarenja',
                ],
                'no_wa_pemohon' => '6281345200006',
            ],
            [
                'penduduk_id' => $kkRecords[7]['kepala']->id,
                'jenis_surat' => JenisSurat::Domisili,
                'kode_klasifikasi' => '470',
                'keperluan' => 'Persyaratan Pendaftaran BPJS Mandiri',
                'data_khusus' => [
                    'sejak_tahun' => 2010,
                    'status_tempat_tinggal' => 'Milik Sendiri',
                ],
                'no_wa_pemohon' => '6281345200007',
            ],
        ];

        foreach ($antreanOnline as $antrean) {
            SuratKeluar::withTrashed()->updateOrCreate(
                [
                    'penduduk_id' => $antrean['penduduk_id'],
                    'jenis_surat' => $antrean['jenis_surat'],
                    'status' => StatusSuratKeluar::MenungguVerifikasi,
                ],
                [
                    'qr_token' => null,
                    'sumber_pengajuan' => 'online',
                    'kode_klasifikasi' => $antrean['kode_klasifikasi'],
                    'tahun_surat' => null,
                    'nomor_urut' => null,
                    'nomor_surat' => null,
                    'tanggal_surat' => null,
                    'keperluan' => $antrean['keperluan'],
                    'data_khusus' => $antrean['data_khusus'],
                    'no_wa_pemohon' => $antrean['no_wa_pemohon'],
                    'opsi_penandatangan' => null,
                    'nama_penandatangan' => null,
                    'jabatan_penandatangan' => null,
                    'nip_penandatangan' => null,
                    'alasan_penolakan' => null,
                    'alasan_pembatalan' => null,
                    'processed_by' => null,
                    'deleted_at' => null,
                ]
            );
        }

        // 3 Agenda Surat Masuk ber-scan PDF di private disk
        $daftarSuratMasuk = [
            1 => [
                'nomor_surat_masuk' => "005/114/KEC-ST/X/{$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-03",
                'tanggal_terima' => "{$tahunAktif}-10-04",
                'pengirim' => 'Pemerintah Kecamatan Sindue Tobata',
                'perihal' => 'Undangan Rapat Evaluasi Realisasi APBDes Semester II',
                'tujuan_disposisi' => 'Sekretaris Desa & Kaur Keuangan',
                'catatan_disposisi' => 'Siapkan dokumen realisasi Siskeudes & foto proyek.',
            ],
            2 => [
                'nomor_surat_masuk' => "412.2/289/DPMD/{$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-15",
                'tanggal_terima' => "{$tahunAktif}-10-17",
                'pengirim' => 'Dinas Pemberdayaan Masyarakat dan Desa Kab. Donggala',
                'perihal' => 'Pedoman Penyusunan RKPDes Tahun Anggaran Berikutnya',
                'tujuan_disposisi' => 'Kaur Perencanaan',
                'catatan_disposisi' => 'Segera jadwalkan pra-Musdes bersama BPD.',
            ],
            3 => [
                'nomor_surat_masuk' => "460/092/DINSOS/{$tahunAktif}",
                'tanggal_surat' => "{$tahunAktif}-10-24",
                'tanggal_terima' => "{$tahunAktif}-10-26",
                'pengirim' => 'Dinas Sosial Kabupaten Donggala',
                'perihal' => 'Pemadanan Data Sasaran DTSEN Penerima Bantuan Sosial',
                'tujuan_disposisi' => 'Kasi Kesejahteraan',
                'catatan_disposisi' => 'Cocokkan dengan daftar pemerataan KK di sistem desa.',
            ],
        ];

        foreach ($daftarSuratMasuk as $urutAgenda => $sm) {
            $scan = DummyAssetGenerator::putPdf(
                "surat-masuk/{$tahunAktif}/scan-surat-masuk-0{$urutAgenda}.pdf",
                $sm['perihal'],
                'private'
            );

            SuratMasuk::withTrashed()->updateOrCreate(
                [
                    'tahun_agenda' => $tahunAktif,
                    'nomor_urut_agenda' => $urutAgenda,
                ],
                array_merge($sm, [
                    'berkas_scan_path' => $scan['path'],
                    'created_by' => $operator->id,
                    'deleted_at' => null,
                ])
            );
        }
    }

    /**
     * Penerima Bansos lintas jenis yang menyisakan 7 KK aktif prioritas DTSEN Desil 1-4
     * (KK 5, 6, 11, 12, 16, 17, 18) tanpa bantuan sama sekali di tahun aktif.
     *
     * @param array<int, array{kk:KartuKeluarga, kepala:Penduduk, anggota:list<Penduduk>}> $kkRecords
     */
    private function seedBansos(User $operator, array $kkRecords, int $tahunAktif): void
    {
        $daftarPenyaluran = [
            ['kk' => 1, 'jenis' => JenisBansos::BltDd, 'nominal' => 'Rp 300.000 / Bulan', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 2, 'jenis' => JenisBansos::BltDd, 'nominal' => 'Rp 300.000 / Bulan', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 3, 'jenis' => JenisBansos::Pkh, 'nominal' => 'Rp 750.000 / Tahap', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 4, 'jenis' => JenisBansos::Bpnt, 'nominal' => 'Rp 200.000 / Bulan', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            // KK 5 & 6 (Desil 1) SENGAJA dikosongkan untuk uji Pemerataan Bantuan!

            ['kk' => 7, 'jenis' => JenisBansos::Pkh, 'nominal' => 'Rp 600.000 / Tahap', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 8, 'jenis' => JenisBansos::Bpnt, 'nominal' => 'Beras 10 Kg', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 9, 'jenis' => JenisBansos::PbiJkn, 'nominal' => 'Iuran JKN Kelas 3 Gratis', 'tahap' => 'Tahunan', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 10, 'jenis' => JenisBansos::Rtlh, 'nominal' => 'Material Rehab Rumah Rp 20.000.000', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            // KK 11 & 12 (Desil 2) SENGAJA dikosongkan untuk uji Pemerataan Bantuan!

            ['kk' => 13, 'jenis' => JenisBansos::BantuanDaerah, 'nominal' => 'Paket Sarana Nelayan', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 14, 'jenis' => JenisBansos::PbiJkn, 'nominal' => 'Iuran JKN Kelas 3 Gratis', 'tahap' => 'Tahunan', 'status' => StatusPesertaBansos::Aktif],
            ['kk' => 15, 'jenis' => JenisBansos::Pkh, 'nominal' => 'Rp 500.000 / Tahap', 'tahap' => 'Tahap I', 'status' => StatusPesertaBansos::Graduasi],
            // KK 16, 17, 18 (Desil 3-4) SENGAJA dikosongkan untuk uji Pemerataan Bantuan!
        ];

        foreach ($daftarPenyaluran as $row) {
            $kepala = $kkRecords[$row['kk']]['kepala'];

            PenerimaBansos::withTrashed()->updateOrCreate(
                [
                    'penduduk_id' => $kepala->id,
                    'jenis_bantuan' => $row['jenis'],
                    'tahun_anggaran' => $tahunAktif,
                    'tahap_penyaluran' => $row['tahap'],
                ],
                [
                    'bentuk_nominal' => $row['nominal'],
                    'kategori_ekonomi' => $kepala->status_kesejahteraan,
                    'status_kepesertaan' => $row['status'],
                    'keterangan' => $row['status'] === StatusPesertaBansos::Graduasi ? 'Keluarga telah mandiri usaha warung.' : null,
                    'created_by' => $operator->id,
                    'deleted_at' => null,
                ]
            );
        }

        // Sampel histori peserta wafat (selaras dengan warga wafat KK 20)
        $wargaWafat = $kkRecords[20]['anggota'][2];
        PenerimaBansos::withTrashed()->updateOrCreate(
            [
                'penduduk_id' => $wargaWafat->id,
                'jenis_bantuan' => JenisBansos::PbiJkn,
                'tahun_anggaran' => $tahunAktif,
                'tahap_penyaluran' => 'Tahunan',
            ],
            [
                'bentuk_nominal' => 'Iuran JKN Kelas 3 Gratis',
                'kategori_ekonomi' => $wargaWafat->status_kesejahteraan,
                'status_kepesertaan' => StatusPesertaBansos::MeninggalDunia,
                'keterangan' => 'Dinonaktifkan otomatis karena penerima wafat.',
                'created_by' => $operator->id,
                'deleted_at' => null,
            ]
        );
    }

    /**
     * 5 Kegiatan Desa (4 published + 1 draft), 9 Perangkat Desa + BPD, & 5 Dokumen PPID.
     */
    private function seedKegiatanSotkDanPpid(User $operator, int $tahunAktif): void
    {
        $daftarKegiatan = [
            [
                'slug' => "penyaluran-blt-dana-desa-tahap-i-{$tahunAktif}",
                'judul' => "Penyaluran BLT Dana Desa Tahap I Tahun {$tahunAktif}",
                'kategori' => KategoriKegiatan::PenyaluranBansos,
                'tanggal_kegiatan' => "{$tahunAktif}-04-10",
                'lokasi_dusun' => 'Balai Desa Tamarenja',
                'uraian' => 'Pemerintah Desa Tamarenja menyalurkan Bantuan Langsung Tunai Dana Desa secara terbuka disaksikan BPD dan pendamping desa.',
                'status_publikasi' => StatusPublikasi::Published,
            ],
            [
                'slug' => "posyandu-balita-dan-lansia-rutin-{$tahunAktif}",
                'judul' => 'Pelayanan Posyandu Balita, Ibu Hamil, dan Lansia Terpadu',
                'kategori' => KategoriKegiatan::PosyanduKesehatan,
                'tanggal_kegiatan' => "{$tahunAktif}-06-14",
                'lokasi_dusun' => 'Polindes Dusun II',
                'uraian' => 'Kegiatan penimbangan balita, pemberian makanan tambahan (PMT) pencegahan stunting, dan pemeriksaan kesehatan lansia.',
                'status_publikasi' => StatusPublikasi::Published,
            ],
            [
                'slug' => "gotong-royong-pembersihan-saluran-desa-{$tahunAktif}",
                'judul' => 'Gotong Royong Jumat Bersih Saluran Drainase & Pantai Desa',
                'kategori' => KategoriKegiatan::GotongRoyong,
                'tanggal_kegiatan' => "{$tahunAktif}-08-09",
                'lokasi_dusun' => 'Dusun I & Dusun II',
                'uraian' => 'Warga bersama perangkat desa melaksanakan kerja bakti membersihkan drainase jalan poros dan pesisir pantai.',
                'status_publikasi' => StatusPublikasi::Published,
            ],
            [
                'slug' => "musyawarah-desa-rkpdes-tamarenja-{$tahunAktif}",
                'judul' => 'Musyawarah Desa (Musdes) Penyusunan RKPDes Tahun Berikutnya',
                'kategori' => KategoriKegiatan::Musdes,
                'tanggal_kegiatan' => "{$tahunAktif}-09-25",
                'lokasi_dusun' => 'Aula Kantor Desa Tamarenja',
                'uraian' => 'BPD bersama Pemerintah Desa dan tokoh masyarakat menyepakati prioritas pembangunan infrastruktur dan pemberdayaan desa.',
                'status_publikasi' => StatusPublikasi::Published,
            ],
            [
                'slug' => "pelatihan-pengolahan-hasil-perikanan-bumdes-{$tahunAktif}",
                'judul' => 'Konsep Pelatihan Pengolahan Ikan Asap & Kemasan UMKM BUMDes',
                'kategori' => KategoriKegiatan::PelatihanBumdes,
                'tanggal_kegiatan' => "{$tahunAktif}-11-12",
                'lokasi_dusun' => 'Balai Desa Tamarenja',
                'uraian' => 'Dokumentasi persiapan pelatihan peningkatan nilai tambah hasil tangkapan nelayan bersama pengurus BUMDes.',
                'status_publikasi' => StatusPublikasi::Draft,
            ],
        ];

        foreach ($daftarKegiatan as $idx => $item) {
            $kegiatan = KegiatanDesa::withTrashed()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'judul' => $item['judul'],
                    'kategori' => $item['kategori'],
                    'tanggal_kegiatan' => $item['tanggal_kegiatan'],
                    'lokasi_dusun' => $item['lokasi_dusun'],
                    'uraian' => $item['uraian'],
                    'status_publikasi' => $item['status_publikasi'],
                    'published_at' => $item['status_publikasi'] === StatusPublikasi::Published
                        ? $item['tanggal_kegiatan'] . ' 10:00:00'
                        : null,
                    'created_by' => $operator->id,
                    'deleted_at' => null,
                ]
            );

            $foto1 = DummyAssetGenerator::putJpg(
                "kegiatan/{$tahunAktif}/kegiatan-{$idx}-1.jpg",
                'KEGIATAN DESA ' . ($idx + 1)
            );

            KegiatanDesaFoto::query()->updateOrCreate(
                ['kegiatan_desa_id' => $kegiatan->id, 'urutan' => 1],
                ['foto_path' => $foto1]
            );

            if ($item['status_publikasi'] === StatusPublikasi::Published) {
                $foto2 = DummyAssetGenerator::putJpg(
                    "kegiatan/{$tahunAktif}/kegiatan-{$idx}-2.jpg",
                    'DOKUMENTASI WARGA ' . ($idx + 1)
                );

                KegiatanDesaFoto::query()->updateOrCreate(
                    ['kegiatan_desa_id' => $kegiatan->id, 'urutan' => 2],
                    ['foto_path' => $foto2]
                );
            }
        }

        // 10 Perangkat Desa & BPD
        $daftarPerangkat = [
            ['urutan' => 1, 'nama' => 'H. Moh. Ridwan, S.Sos.', 'jabatan' => 'Kepala Desa', 'kelompok' => 'pimpinan', 'nip' => null],
            ['urutan' => 2, 'nama' => 'Andi Saputra, S.AP.', 'jabatan' => 'Sekretaris Desa', 'kelompok' => 'sekretariat', 'nip' => '198604122010011008'],
            ['urutan' => 3, 'nama' => 'Siti Rahmawati, S.E.', 'jabatan' => 'Kaur Keuangan', 'kelompok' => 'sekretariat', 'nip' => null],
            ['urutan' => 4, 'nama' => 'Moh. Fadli, A.Md.', 'jabatan' => 'Kaur Tata Usaha & Umum', 'kelompok' => 'sekretariat', 'nip' => null],
            ['urutan' => 5, 'nama' => 'Rizky Pratama, S.Kom.', 'jabatan' => 'Kaur Perencanaan', 'kelompok' => 'sekretariat', 'nip' => null],
            ['urutan' => 6, 'nama' => 'Syahrul Ramadhan', 'jabatan' => 'Kasi Pemerintahan', 'kelompok' => 'pelaksana_teknis', 'nip' => null],
            ['urutan' => 7, 'nama' => 'Nurlina, S.Sos.', 'jabatan' => 'Kasi Kesejahteraan & Pelayanan', 'kelompok' => 'pelaksana_teknis', 'nip' => null],
            ['urutan' => 8, 'nama' => 'Ruslan Abdul Gani', 'jabatan' => 'Kepala Dusun I', 'kelompok' => 'kewilayahan_dusun', 'nip' => null],
            ['urutan' => 9, 'nama' => 'Drs. H. Mahmud Lamakarate', 'jabatan' => 'Ketua BPD', 'kelompok' => 'bpd', 'nip' => null],
            ['urutan' => 10, 'nama' => 'Hj. Rostina', 'jabatan' => 'Sekretaris BPD', 'kelompok' => 'bpd', 'nip' => null],
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
                [
                    'urutan' => $p['urutan'],
                    'jabatan' => $p['jabatan'],
                ],
                [
                    'nama_lengkap' => $p['nama'],
                    'kelompok_sotk' => $p['kelompok'],
                    'nip_niap' => $p['nip'],
                    'foto_path' => $fotoSotk,
                    'is_aktif' => true,
                ]
            );
        }

        // 5 Dokumen Resmi PPID (4 publik + 1 non-publik)
        $daftarPpid = [
            [
                'path' => "ppid/{$tahunAktif}/perdes-apbdes-{$tahunAktif}.pdf",
                'judul' => "Peraturan Desa tentang APBDes Tahun Anggaran {$tahunAktif}",
                'kategori' => 'perdes_apbdes',
                'tahun' => $tahunAktif,
                'is_publik' => true,
            ],
            [
                'path' => 'ppid/' . ($tahunAktif - 1) . '/lpj-realisasi-apbdes-' . ($tahunAktif - 1) . '.pdf',
                'judul' => 'Laporan Pertanggungjawaban Realisasi APBDes Tahun ' . ($tahunAktif - 1),
                'kategori' => 'lpj',
                'tahun' => $tahunAktif - 1,
                'is_publik' => true,
            ],
            [
                'path' => "ppid/{$tahunAktif}/rkpdes-tamarenja-{$tahunAktif}.pdf",
                'judul' => "Rencana Kerja Pemerintah Desa (RKPDes) Tahun {$tahunAktif}",
                'kategori' => 'rkpdes',
                'tahun' => $tahunAktif,
                'is_publik' => true,
            ],
            [
                'path' => "ppid/{$tahunAktif}/standar-pelayanan-surat-desa.pdf",
                'judul' => 'Panduan Standar Pelayanan Persuratan & Layanan Mandiri Warga',
                'kategori' => 'panduan_layanan',
                'tahun' => $tahunAktif,
                'is_publik' => true,
            ],
            [
                'path' => "ppid/{$tahunAktif}/draft-sk-tim-verifikasi-internal.pdf",
                'judul' => 'Draft SK Internal Tim Verifikasi Data Desa (Arsip Internal)',
                'kategori' => 'sk_regulasi',
                'tahun' => $tahunAktif,
                'is_publik' => false,
            ],
        ];

        foreach ($daftarPpid as $doc) {
            $pdf = DummyAssetGenerator::putPdf($doc['path'], $doc['judul'], 'public');

            DokumenPpid::withTrashed()->updateOrCreate(
                ['file_pdf_path' => $pdf['path']],
                [
                    'judul_dokumen' => $doc['judul'],
                    'kategori' => $doc['kategori'],
                    'tahun_dokumen' => $doc['tahun'],
                    'ukuran_file_kb' => $pdf['size_kb'],
                    'jumlah_unduhan' => $doc['is_publik'] ? 12 : 0,
                    'is_publik' => $doc['is_publik'],
                    'uploaded_by' => $operator->id,
                    'deleted_at' => null,
                ]
            );
        }
    }
}
