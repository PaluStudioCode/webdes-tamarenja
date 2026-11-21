<?php

namespace Database\Seeders;

use App\Enums\JenisDokumenApbdes;
use App\Enums\UserRole;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
use App\Models\ProfilDesa;
use App\Models\User;
use App\Support\ApbdesPosCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EssentialDesaSeeder extends Seeder
{
    /**
     * Seeder wajib lintas environment (Production, Testing, Local).
     * Bersifat idempoten menggunakan updateOrCreate.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
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

            $tahunDefault = (int) date('Y');
            $existingProfil = ProfilDesa::query()->first();

            $profil = ProfilDesa::query()->updateOrCreate(
                ['id' => $existingProfil?->id ?? 1],
                [
                    'nama_desa' => $existingProfil?->nama_desa ?? 'Tamarenja',
                    'kode_desa_surat' => $existingProfil?->kode_desa_surat ?? 'DS-TMR',
                    'kecamatan' => $existingProfil?->kecamatan ?? 'Sindue Tobata',
                    'kabupaten' => $existingProfil?->kabupaten ?? 'Donggala',
                    'provinsi' => $existingProfil?->provinsi ?? 'Sulawesi Tengah',
                    'kode_wilayah' => $existingProfil?->kode_wilayah ?? '72.03.25.2004',
                    'daftar_dusun' => $existingProfil?->daftar_dusun ?? [
                        'Dusun I (Katandona)',
                        'Dusun II (Sinente)',
                        'Dusun III (Irigasi)',
                    ],
                    'alamat_kantor' => ($existingProfil && $existingProfil->alamat_kantor && ! str_contains($existingProfil->alamat_kantor, 'Jl. Trans Sulawesi'))
                        ? $existingProfil->alamat_kantor
                        : 'Jl. Hi. Paggaru No. 80 Tamarenja Kode Pos 94343',
                    'logo_desa_path' => $existingProfil?->logo_desa_path ?? 'logo/logo-desa-tamarenja.png',
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
                    'visi' => $existingProfil?->visi ?? 'Terwujudnya Desa Tamarenja yang Mandiri, Maju, dan Sejahtera Berbasis Potensi Pertanian dan Agrowisata Salak yang Berkelanjutan.',
                    'misi' => $existingProfil?->misi ?? [
                        'Meningkatkan kualitas pelayanan administrasi desa yang cepat, transparan, dan berkeadilan bagi seluruh warga.',
                        'Membangun dan memelihara infrastruktur jalan usaha tani serta sarana publik desa secara merata.',
                        'Mengembangkan potensi perkebunan salak dan komoditas lokal menuju kemandirian ekonomi desa.',
                        'Memperkuat tata kelola pemerintahan desa yang akuntabel dan berlandaskan gotong royong.',
                    ],
                    'sejarah_singkat' => ($existingProfil && ! str_contains($existingProfil->sejarah_singkat ?? '', 'pesisir dan pertanian'))
                        ? $existingProfil->sejarah_singkat
                        : 'Desa Tamarenja merupakan desa pedalaman berbasis perkebunan yang terletak di kawasan lembah perbukitan Kecamatan Sindue Tobata, Kabupaten Donggala, diapit oleh dua aliran sungai besar pada ketinggian 100–300 mdpl. Diresmikan sebagai desa definitif pada tahun 1961, perekonomian desa mengalami transformasi besar sejak masuknya bibit salak pada tahun 1982 yang kemudian berkembang pesat menjadi komoditas unggulan utama daerah. Berkat kerja keras dan gotong royong warganya dalam mengelola potensi perkebunan, Desa Tamarenja berhasil meraih predikat Desa Terbaik se-Sulawesi Tengah dan Desa Percontohan Nasional Kementerian Desa pada tahun 2015.',
                    'potensi_unggulan' => ($existingProfil && ! str_contains($existingProfil->potensi_unggulan ?? '', 'Selat Makassar'))
                        ? $existingProfil->potensi_unggulan
                        : 'Sentra perkebunan Salak Tamarenja seluas 300–350 hektare dengan cita rasa manis renyah yang dipasarkan lintas provinsi, potensi agrowisata petik salak di lembah perbukitan asri, komoditas perkebunan pisang dan kelapa, serta ketersediaan sumber air melimpah dari dua sungai besar desa.',
                    'indikator_desa' => $existingProfil?->indikator_desa ?? ProfilDesa::defaultIndikatorDesa(),
                    'tahun_anggaran_aktif' => $existingProfil?->tahun_anggaran_aktif ?? $tahunDefault,
                ]
            );

            $tahunAktif = $profil->tahun_anggaran_aktif;
            $existingApbdes = Apbdes::query()
                ->where('tahun_anggaran', $tahunAktif)
                ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
                ->first();

            $apbdes = Apbdes::query()->updateOrCreate(
                [
                    'tahun_anggaran' => $tahunAktif,
                    'jenis_dokumen' => JenisDokumenApbdes::Murni,
                ],
                [
                    'is_aktif_publik' => $existingApbdes?->is_aktif_publik ?? true,
                    'is_terkunci' => $existingApbdes?->is_terkunci ?? false,
                    'nomor_perdes' => $existingApbdes?->nomor_perdes ?? "Perdes No. 01 Tahun {$tahunAktif}",
                    'tanggal_penetapan' => $existingApbdes?->tanggal_penetapan ?? "{$tahunAktif}-01-15",
                    'created_by' => $existingApbdes?->created_by ?? $operator->id,
                ]
            );

            foreach (ApbdesPosCatalog::all() as $pos) {
                $existingRincian = ApbdesRincian::query()
                    ->where('apbdes_id', $apbdes->id)
                    ->where('kode_pos', $pos['kode_pos'])
                    ->first();

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
                        'pagu_anggaran' => $existingRincian?->pagu_anggaran ?? 0,
                        'realisasi_tahap_1' => $existingRincian?->realisasi_tahap_1 ?? 0,
                        'realisasi_tahap_2' => $existingRincian?->realisasi_tahap_2 ?? 0,
                        'realisasi_tahap_3' => $existingRincian?->realisasi_tahap_3 ?? 0,
                        'total_realisasi' => $existingRincian?->total_realisasi ?? 0,
                    ]
                );
            }
        });
    }
}
