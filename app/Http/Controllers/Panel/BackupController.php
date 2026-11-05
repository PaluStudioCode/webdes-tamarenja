<?php

namespace App\Http\Controllers\Panel;

use App\Exports\FullDatabaseBackupExport;
use App\Http\Controllers\Controller;
use App\Models\Apbdes;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Services\DatabaseBackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
    ) {}

    /**
     * Tampilkan halaman Backup Data 1x Klik (/panel/pengaturan/backup).
     */
    public function index(): Response
    {
        Gate::authorize('execute-system-backup');

        $moduleStats = [
            ['sheet' => 'Kartu Keluarga', 'count' => KartuKeluarga::query()->count()],
            ['sheet' => 'Penduduk', 'count' => Penduduk::query()->count()],
            ['sheet' => 'Proyek Pembangunan', 'count' => ProyekPembangunan::query()->count()],
            ['sheet' => 'APBDes', 'count' => Apbdes::query()->count()],
            ['sheet' => 'Surat Keluar', 'count' => SuratKeluar::query()->count()],
            ['sheet' => 'Surat Masuk', 'count' => SuratMasuk::query()->count()],
            ['sheet' => 'Penerima Bansos', 'count' => PenerimaBansos::query()->count()],
            ['sheet' => 'Kegiatan Desa', 'count' => KegiatanDesa::query()->count()],
        ];

        return Inertia::render('Pengaturan/Backup', [
            'moduleStats' => $moduleStats,
            'sheetTitles' => FullDatabaseBackupExport::SHEET_TITLES,
        ]);
    }

    /**
     * Buat dan alirkan unduhan backup database (.sql) dari private disk.
     */
    public function downloadSql(Request $request): StreamedResponse
    {
        Gate::authorize('execute-system-backup');

        $forcePdo = $request->boolean('force_pdo', false);
        $result = $this->backupService->createSqlBackup($forcePdo);
        $downloadName = 'backup-database-desa-tamarenja-' . now()->format('Y-m-d') . '.sql';

        return Storage::disk('private')->download($result['path'], $downloadName, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'X-Backup-Method' => $result['method'],
        ]);
    }

    /**
     * Buat dan alirkan unduhan export data lengkap 8 lembar kerja (.xlsx) dari private disk.
     */
    public function downloadExcel(): StreamedResponse
    {
        Gate::authorize('execute-system-backup');

        $result = $this->backupService->createExcelBackup();
        $downloadName = 'backup-8-modul-desa-tamarenja-' . now()->format('Y-m-d') . '.xlsx';

        return Storage::disk('private')->download($result['path'], $downloadName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
