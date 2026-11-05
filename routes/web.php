<?php

use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Panel\ApbdesController;
use App\Http\Controllers\Panel\BackupController;
use App\Http\Controllers\Panel\DashboardController;
use App\Http\Controllers\Panel\DokumenPpidController;
use App\Http\Controllers\Panel\FilterTahunController;
use App\Http\Controllers\Panel\KartuKeluargaController;
use App\Http\Controllers\Panel\KegiatanDesaController;
use App\Http\Controllers\Panel\PendudukController;
use App\Http\Controllers\Panel\PenerimaBansosController;
use App\Http\Controllers\Panel\PenggunaController;
use App\Http\Controllers\Panel\PerangkatDesaController;
use App\Http\Controllers\Panel\ProfilDesaController;
use App\Http\Controllers\Panel\ProyekPembangunanController;
use App\Http\Controllers\Panel\SuratKeluarController;
use App\Http\Controllers\Panel\SuratMasukController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Publik\LayananMandiriController;
use App\Http\Controllers\Publik\PortalPublikController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rute Portal Halaman Publik Desa (Tanpa Login — Patuh UU PDP No. 27/2022)
Route::get('/', [PortalPublikController::class, 'beranda'])
    ->name('beranda');

Route::get('/profil', [PortalPublikController::class, 'profil'])
    ->name('publik.profil');

Route::prefix('transparansi')
    ->name('publik.transparansi.')
    ->group(function () {
        Route::get('/apbdes', [PortalPublikController::class, 'transparansiApbdes'])
            ->name('apbdes');

        Route::get('/pembangunan', [PortalPublikController::class, 'transparansiPembangunan'])
            ->name('pembangunan');

        Route::get('/pembangunan/cetak-pdf', [PortalPublikController::class, 'cetakRekapPembangunanPdf'])
            ->name('pembangunan.pdf');

        Route::get('/statistik-penduduk', [PortalPublikController::class, 'statistikPenduduk'])
            ->name('statistik-penduduk');
    });

Route::get('/kegiatan', [PortalPublikController::class, 'kegiatanIndex'])
    ->name('publik.kegiatan.index');

Route::get('/kegiatan/{slug}', [PortalPublikController::class, 'kegiatanShow'])
    ->name('publik.kegiatan.show');

Route::get('/unduhan', [PortalPublikController::class, 'unduhanIndex'])
    ->name('publik.unduhan.index');

Route::get('/unduhan/{dokumenPpid}/file', [PortalPublikController::class, 'unduhanDownload'])
    ->whereNumber('dokumenPpid')
    ->name('publik.unduhan.download');

Route::prefix('layanan-mandiri')
    ->name('publik.layanan-mandiri.')
    ->group(function () {
        Route::get('/', [LayananMandiriController::class, 'index'])
            ->name('index');

        Route::post('/ajukan-surat', [LayananMandiriController::class, 'ajukanSurat'])
            ->middleware('throttle:10,1')
            ->name('ajukan-surat');

        Route::post('/cek-bansos', [LayananMandiriController::class, 'cekBansos'])
            ->middleware('throttle:10,1')
            ->name('cek-bansos');

        Route::get('/verifikasi-surat/{qr_token}', [LayananMandiriController::class, 'verifikasiSurat'])
            ->name('verifikasi-surat');
    });

// Rute Panel Operasional Internal Desa
Route::prefix('panel')
    ->name('panel.')
    ->middleware(['auth', 'active'])
    ->group(function () {
        // Rute Baca, Profil Akun Pribadi & Sesi Bersama (Operator Desa & Kepala Desa)
        Route::middleware('role:operator,kepala_desa')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index'])
                ->name('dashboard');

            Route::post('/filter-tahun', FilterTahunController::class)
                ->name('filter-tahun');

            Route::get('/akun', [ProfileController::class, 'edit'])
                ->name('akun.edit');

            Route::patch('/akun', [ProfileController::class, 'update'])
                ->name('akun.update');

            Route::put('/akun/password', [PasswordController::class, 'update'])
                ->name('akun.password.update');

            // Master Data Kependudukan — Rute Baca & Autocomplete (Operator & Kepala Desa)
            Route::prefix('kependudukan')
                ->name('kependudukan.')
                ->group(function () {
                    Route::get('/', [PendudukController::class, 'index'])
                        ->name('index');

                    Route::get('/cari-nik', [PendudukController::class, 'searchNik'])
                        ->name('cari-nik');

                    Route::get('/kartu-keluarga/{kartuKeluarga}', [KartuKeluargaController::class, 'show'])
                        ->name('kk.show');

                    Route::get('/penduduk/{penduduk}', [PendudukController::class, 'show'])
                        ->name('penduduk.show');
                });

            // Modul 1: Monitoring Pembangunan Fisik — Rute Baca & Cetak PDF F4 Landscape (Operator & Kepala Desa)
            Route::prefix('pembangunan')
                ->name('pembangunan.')
                ->group(function () {
                    Route::get('/', [ProyekPembangunanController::class, 'index'])
                        ->name('index');

                    Route::get('/cetak-pdf', [ProyekPembangunanController::class, 'cetakPdf'])
                        ->name('cetak-pdf');
                });

            // Modul 2: APBDes Multi-Tahun — Rute Baca & Cetak PDF F4 Portrait (Operator & Kepala Desa)
            Route::prefix('apbdes')
                ->name('apbdes.')
                ->group(function () {
                    Route::get('/', [ApbdesController::class, 'index'])
                        ->name('index');

                    Route::get('/cetak-pdf', [ApbdesController::class, 'cetakPdf'])
                        ->name('cetak-pdf');

                    Route::get('/{apbdes}/cetak-pdf', [ApbdesController::class, 'cetakPdf'])
                        ->name('dokumen.cetak-pdf');
                });

            // Modul 3: Administrasi & Persuratan — Rute Baca & Cetak PDF F4 (Operator & Kepala Desa)
            Route::prefix('persuratan')
                ->name('persuratan.')
                ->group(function () {
                    Route::get('/antrean', [SuratKeluarController::class, 'antrean'])
                        ->name('antrean');

                    Route::get('/surat-keluar', [SuratKeluarController::class, 'index'])
                        ->name('surat-keluar.index');

                    Route::get('/surat-keluar/cetak-agenda', [SuratKeluarController::class, 'cetakAgendaPdf'])
                        ->name('surat-keluar.cetak-agenda');

                    Route::get('/surat-keluar/{suratKeluar}/pdf', [SuratKeluarController::class, 'cetakPdf'])
                        ->whereNumber('suratKeluar')
                        ->name('surat-keluar.pdf');

                    Route::get('/cek-bansos-kk/{penduduk}', [SuratKeluarController::class, 'cekBansosKk'])
                        ->whereNumber('penduduk')
                        ->name('cek-bansos-kk');

                    Route::get('/surat-masuk', [SuratMasukController::class, 'index'])
                        ->name('surat-masuk.index');

                    Route::get('/surat-masuk/cetak-agenda', [SuratMasukController::class, 'cetakAgendaPdf'])
                        ->name('surat-masuk.cetak-agenda');

                    Route::get('/surat-masuk/{suratMasuk}/berkas', [SuratMasukController::class, 'streamBerkas'])
                        ->whereNumber('suratMasuk')
                        ->name('surat-masuk.berkas');
                });

            // Modul 4: Data Sosial (Bansos) & Kegiatan Desa — Rute Baca & Cetak Daftar Hadir F4 Landscape (Operator & Kepala Desa)
            Route::prefix('sosial')
                ->name('sosial.')
                ->group(function () {
                    Route::get('/bansos', [PenerimaBansosController::class, 'index'])
                        ->name('bansos.index');

                    Route::get('/bansos/cetak-pdf', [PenerimaBansosController::class, 'cetakDaftarHadirPdf'])
                        ->name('bansos.cetak-pdf');

                    Route::get('/bansos/cek-kk/{penduduk}', [PenerimaBansosController::class, 'cekBansosKk'])
                        ->whereNumber('penduduk')
                        ->name('bansos.cek-kk');

                    Route::get('/kegiatan', [KegiatanDesaController::class, 'index'])
                        ->name('kegiatan.index');
                });
        });

        // Modul 4: Data Sosial (Bansos) & Kegiatan Desa — Rute Tulis & Import/Export Excel (Khusus Operator Desa)
        Route::prefix('sosial')
            ->name('sosial.')
            ->middleware('role:operator')
            ->group(function () {
                Route::post('/bansos', [PenerimaBansosController::class, 'store'])
                    ->name('bansos.store');

                Route::put('/bansos/{bansos}', [PenerimaBansosController::class, 'update'])
                    ->whereNumber('bansos')
                    ->name('bansos.update');

                Route::delete('/bansos/{bansos}', [PenerimaBansosController::class, 'destroy'])
                    ->whereNumber('bansos')
                    ->name('bansos.destroy');

                Route::get('/bansos/template-excel', [PenerimaBansosController::class, 'downloadTemplate'])
                    ->name('bansos.template-excel');

                Route::get('/bansos/export-excel', [PenerimaBansosController::class, 'exportExcel'])
                    ->name('bansos.export-excel');

                Route::post('/bansos/import-excel', [PenerimaBansosController::class, 'importExcel'])
                    ->name('bansos.import-excel');

                Route::get('/kegiatan/buat', [KegiatanDesaController::class, 'create'])
                    ->name('kegiatan.create');

                Route::post('/kegiatan', [KegiatanDesaController::class, 'store'])
                    ->name('kegiatan.store');

                Route::get('/kegiatan/{kegiatan}/edit', [KegiatanDesaController::class, 'edit'])
                    ->whereNumber('kegiatan')
                    ->name('kegiatan.edit');

                Route::match(['put', 'post'], '/kegiatan/{kegiatan}', [KegiatanDesaController::class, 'update'])
                    ->whereNumber('kegiatan')
                    ->name('kegiatan.update');

                Route::patch('/kegiatan/{kegiatan}/publikasi', [KegiatanDesaController::class, 'togglePublikasi'])
                    ->whereNumber('kegiatan')
                    ->name('kegiatan.toggle-publikasi');

                Route::delete('/kegiatan/{kegiatan}', [KegiatanDesaController::class, 'destroy'])
                    ->whereNumber('kegiatan')
                    ->name('kegiatan.destroy');
            });

        // Modul 3: Administrasi & Persuratan — Rute Tulis / Verifikasi / Pembatalan (Khusus Operator Desa)
        Route::prefix('persuratan')
            ->name('persuratan.')
            ->middleware('role:operator')
            ->group(function () {
                Route::get('/surat-keluar/buat', [SuratKeluarController::class, 'create'])
                    ->name('surat-keluar.create');

                Route::post('/surat-keluar', [SuratKeluarController::class, 'store'])
                    ->name('surat-keluar.store');

                Route::post('/antrean/{suratKeluar}/verifikasi', [SuratKeluarController::class, 'verifikasi'])
                    ->whereNumber('suratKeluar')
                    ->name('antrean.verifikasi');

                Route::patch('/antrean/{suratKeluar}/tolak', [SuratKeluarController::class, 'tolak'])
                    ->whereNumber('suratKeluar')
                    ->name('antrean.tolak');

                Route::patch('/surat-keluar/{suratKeluar}/batalkan', [SuratKeluarController::class, 'batalkan'])
                    ->whereNumber('suratKeluar')
                    ->name('surat-keluar.batalkan');

                Route::delete('/surat-keluar/{suratKeluar}', [SuratKeluarController::class, 'destroy'])
                    ->whereNumber('suratKeluar')
                    ->name('surat-keluar.destroy');

                Route::post('/surat-masuk', [SuratMasukController::class, 'store'])
                    ->name('surat-masuk.store');

                Route::match(['put', 'post'], '/surat-masuk/{suratMasuk}', [SuratMasukController::class, 'update'])
                    ->whereNumber('suratMasuk')
                    ->name('surat-masuk.update');

                Route::delete('/surat-masuk/{suratMasuk}', [SuratMasukController::class, 'destroy'])
                    ->whereNumber('suratMasuk')
                    ->name('surat-masuk.destroy');
            });

        // Modul 1: Monitoring Pembangunan Fisik — Rute Tulis & Sinkronisasi APBDes (Khusus Operator Desa)
        Route::prefix('pembangunan')
            ->name('pembangunan.')
            ->middleware('role:operator')
            ->group(function () {
                Route::get('/tambah', [ProyekPembangunanController::class, 'create'])
                    ->name('create');

                Route::post('/', [ProyekPembangunanController::class, 'store'])
                    ->name('store');

                Route::get('/{proyek}/edit', [ProyekPembangunanController::class, 'edit'])
                    ->name('edit');

                Route::match(['put', 'post'], '/{proyek}', [ProyekPembangunanController::class, 'update'])
                    ->name('update');

                Route::delete('/{proyek}', [ProyekPembangunanController::class, 'destroy'])
                    ->name('destroy');
            });

        // Modul 2: APBDes Multi-Tahun — Rute Tulis / Clone & Freeze (Khusus Operator Desa)
        Route::prefix('apbdes')
            ->name('apbdes.')
            ->middleware('role:operator')
            ->group(function () {
                Route::post('/', [ApbdesController::class, 'store'])
                    ->name('store');

                Route::post('/buat-perubahan', [ApbdesController::class, 'createPerubahan'])
                    ->name('buat-perubahan');

                Route::put('/{apbdes}', [ApbdesController::class, 'update'])
                    ->name('update');

                Route::put('/rincian/{rincian}', [ApbdesController::class, 'updateRincianRow'])
                    ->name('rincian.update');
            });

        // Master Data Kependudukan — Rute Tulis / Mutasi (Khusus Operator Desa)
        Route::prefix('kependudukan')
            ->name('kependudukan.')
            ->middleware('role:operator')
            ->group(function () {
                Route::post('/kartu-keluarga', [KartuKeluargaController::class, 'store'])
                    ->name('kk.store');

                Route::put('/kartu-keluarga/{kartuKeluarga}', [KartuKeluargaController::class, 'update'])
                    ->name('kk.update');

                Route::delete('/kartu-keluarga/{kartuKeluarga}', [KartuKeluargaController::class, 'destroy'])
                    ->name('kk.destroy');

                Route::post('/penduduk', [PendudukController::class, 'store'])
                    ->name('penduduk.store');

                Route::put('/penduduk/{penduduk}', [PendudukController::class, 'update'])
                    ->name('penduduk.update');

                Route::patch('/penduduk/{penduduk}/mutasi', [PendudukController::class, 'mutasi'])
                    ->name('penduduk.mutasi');

                Route::delete('/penduduk/{penduduk}', [PendudukController::class, 'destroy'])
                    ->name('penduduk.destroy');

                Route::get('/template-excel', [PendudukController::class, 'downloadTemplate'])
                    ->name('template-excel');

                Route::get('/export-excel', [PendudukController::class, 'exportExcel'])
                    ->name('export-excel');

                Route::post('/import-excel', [PendudukController::class, 'importExcel'])
                    ->name('import-excel');
            });

        // Rute Pengaturan Sistem (Khusus Operator Desa)
        Route::prefix('pengaturan')
            ->name('pengaturan.')
            ->middleware('role:operator')
            ->group(function () {
                Route::redirect('/', '/panel/pengaturan/profil-desa')
                    ->name('index');

                // Tab 1: Profil Desa, Kop Surat, Daftar Dusun, Pejabat & Tahun Aktif
                Route::get('/profil-desa', [ProfilDesaController::class, 'edit'])
                    ->name('profil-desa.edit');

                Route::match(['put', 'post'], '/profil-desa', [ProfilDesaController::class, 'update'])
                    ->name('profil-desa.update');

                // Tab 2: Struktur Organisasi & Tata Kerja (SOTK & BPD)
                Route::get('/sotk', [PerangkatDesaController::class, 'index'])
                    ->name('sotk.index');

                Route::post('/sotk', [PerangkatDesaController::class, 'store'])
                    ->name('sotk.store');

                Route::match(['put', 'post'], '/sotk/{perangkatDesa}', [PerangkatDesaController::class, 'update'])
                    ->whereNumber('perangkatDesa')
                    ->name('sotk.update');

                Route::delete('/sotk/{perangkatDesa}', [PerangkatDesaController::class, 'destroy'])
                    ->whereNumber('perangkatDesa')
                    ->name('sotk.destroy');

                // Tab 3: Dokumen Resmi Pusat Unduhan (PPID)
                Route::get('/ppid', [DokumenPpidController::class, 'index'])
                    ->name('ppid.index');

                Route::post('/ppid', [DokumenPpidController::class, 'store'])
                    ->name('ppid.store');

                Route::match(['put', 'post'], '/ppid/{dokumenPpid}', [DokumenPpidController::class, 'update'])
                    ->whereNumber('dokumenPpid')
                    ->name('ppid.update');

                Route::patch('/ppid/{dokumenPpid}/publikasi', [DokumenPpidController::class, 'togglePublikasi'])
                    ->whereNumber('dokumenPpid')
                    ->name('ppid.toggle-publikasi');

                Route::delete('/ppid/{dokumenPpid}', [DokumenPpidController::class, 'destroy'])
                    ->whereNumber('dokumenPpid')
                    ->name('ppid.destroy');

                // Tab 4: Manajemen Pengguna Internal
                Route::get('/pengguna', [PenggunaController::class, 'index'])
                    ->name('pengguna.index');

                Route::post('/pengguna', [PenggunaController::class, 'store'])
                    ->name('pengguna.store');

                Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])
                    ->name('pengguna.update');

                Route::patch('/pengguna/{pengguna}/toggle-status', [PenggunaController::class, 'toggleStatus'])
                    ->name('pengguna.toggle-status');

                Route::delete('/pengguna/{pengguna}', [PenggunaController::class, 'destroy'])
                    ->name('pengguna.destroy');

                // Tab 5: Backup Data 1x Klik (.sql & .xlsx 8 Sheet)
                Route::get('/backup', [BackupController::class, 'index'])
                    ->name('backup.index');

                Route::match(['get', 'post'], '/backup/sql', [BackupController::class, 'downloadSql'])
                    ->name('backup.sql');

                Route::match(['get', 'post'], '/backup/excel', [BackupController::class, 'downloadExcel'])
                    ->name('backup.excel');
            });
    });

require __DIR__.'/auth.php';
