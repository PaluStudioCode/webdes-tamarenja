<?php

namespace App\Services;

use App\Enums\StatusProyek;
use App\Enums\SumberDanaProyek;
use App\Models\Apbdes;
use App\Models\ProyekPembangunan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ProyekSyncService
{
    public function __construct(
        protected ApbdesService $apbdesService,
        protected ImageUploadService $imageUploadService
    ) {}

    /**
     * Tentukan status_proyek secara otomatis dari persentase progres_fisik:
     * - 0%       -> belum_dimulai
     * - 1% - 99% -> sedang_dikerjakan
     * - 100%     -> selesai
     */
    public function resolveStatusProyek(int $progresFisik): StatusProyek
    {
        if ($progresFisik <= 0) {
            return StatusProyek::BelumDimulai;
        }

        if ($progresFisik >= 100) {
            return StatusProyek::Selesai;
        }

        return StatusProyek::SedangDikerjakan;
    }

    /**
     * Hitung ulang total Pagu & Realisasi bidang_2_fisik pada dokumen APBDes sasaran tahun T
     * (mengarah ke Perubahan jika sudah ada, selain itu Murni — termasuk auto-init Murni bila tahun T belum ada).
     */
    public function syncTahun(int $tahunAnggaran, ?int $actorId = null): Apbdes
    {
        return $this->apbdesService->syncBidang2FisikFromProjects($tahunAnggaran, $actorId);
    }

    /**
     * Buat proyek pembangunan fisik baru dalam DB::transaction() dan sinkronkan ke APBDes Bidang 2 Fisik.
     *
     * @param  array<string, mixed>  $data
     * @param  array{foto_0?: ?UploadedFile, foto_50?: ?UploadedFile, foto_100?: ?UploadedFile}  $photoFiles
     */
    public function createProject(array $data, array $photoFiles = [], ?int $actorId = null): ProyekPembangunan
    {
        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');
        $tahun = (int) $data['tahun_anggaran'];
        $progres = (int) ($data['progres_fisik'] ?? 0);
        $status = $this->resolveStatusProyek($progres);

        $directory = "proyek/{$tahun}";

        $foto0Path = isset($photoFiles['foto_0']) && $photoFiles['foto_0'] instanceof UploadedFile
            ? $this->imageUploadService->uploadFieldPhoto($photoFiles['foto_0'], $directory)
            : ($data['foto_0_path'] ?? null);

        $foto50Path = isset($photoFiles['foto_50']) && $photoFiles['foto_50'] instanceof UploadedFile
            ? $this->imageUploadService->uploadFieldPhoto($photoFiles['foto_50'], $directory)
            : ($data['foto_50_path'] ?? null);

        $foto100Path = isset($photoFiles['foto_100']) && $photoFiles['foto_100'] instanceof UploadedFile
            ? $this->imageUploadService->uploadFieldPhoto($photoFiles['foto_100'], $directory)
            : ($data['foto_100_path'] ?? null);

        return DB::transaction(function () use ($data, $tahun, $progres, $status, $foto0Path, $foto50Path, $foto100Path, $userId) {
            $sumberDana = $data['sumber_dana'] instanceof SumberDanaProyek
                ? $data['sumber_dana']->value
                : (string) $data['sumber_dana'];

            $proyek = ProyekPembangunan::create([
                'tahun_anggaran' => $tahun,
                'nama_kegiatan' => $data['nama_kegiatan'],
                'lokasi_dusun' => $data['lokasi_dusun'],
                'pagu_anggaran' => (int) $data['pagu_anggaran'],
                'realisasi_anggaran' => (int) ($data['realisasi_anggaran'] ?? 0),
                'sumber_dana' => $sumberDana,
                'volume_fisik' => $data['volume_fisik'] ?? null,
                'waktu_pelaksanaan' => $data['waktu_pelaksanaan'] ?? null,
                'pelaksana_kegiatan' => $data['pelaksana_kegiatan'] ?? null,
                'latitude' => isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
                'progres_fisik' => $progres,
                'status_proyek' => $status->value,
                'foto_0_path' => $foto0Path,
                'foto_50_path' => $foto50Path,
                'foto_100_path' => $foto100Path,
                'catatan_kendala' => $data['catatan_kendala'] ?? null,
                'created_by' => $userId,
            ]);

            $this->syncTahun($tahun, $userId);

            return $proyek->fresh();
        });
    }

    /**
     * Perbarui proyek pembangunan fisik dalam DB::transaction(), sinkronkan APBDes, dan bersihkan foto lama yang diganti.
     *
     * @param  array<string, mixed>  $data
     * @param  array{foto_0?: ?UploadedFile, foto_50?: ?UploadedFile, foto_100?: ?UploadedFile}  $photoFiles
     */
    public function updateProject(
        ProyekPembangunan $proyek,
        array $data,
        array $photoFiles = [],
        ?int $actorId = null
    ): ProyekPembangunan {
        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');
        $oldTahun = (int) $proyek->tahun_anggaran;
        $newTahun = isset($data['tahun_anggaran']) ? (int) $data['tahun_anggaran'] : $oldTahun;
        $progres = isset($data['progres_fisik']) ? (int) $data['progres_fisik'] : (int) $proyek->progres_fisik;
        $status = $this->resolveStatusProyek($progres);

        $directory = "proyek/{$newTahun}";
        $oldPhotosToDelete = [];

        $foto0Path = $proyek->foto_0_path;
        if (isset($photoFiles['foto_0']) && $photoFiles['foto_0'] instanceof UploadedFile) {
            if ($proyek->foto_0_path) {
                $oldPhotosToDelete[] = $proyek->foto_0_path;
            }
            $foto0Path = $this->imageUploadService->uploadFieldPhoto($photoFiles['foto_0'], $directory);
        } elseif (array_key_exists('foto_0_path', $data) && $data['foto_0_path'] !== null) {
            $foto0Path = $data['foto_0_path'];
        }

        $foto50Path = $proyek->foto_50_path;
        if (isset($photoFiles['foto_50']) && $photoFiles['foto_50'] instanceof UploadedFile) {
            if ($proyek->foto_50_path) {
                $oldPhotosToDelete[] = $proyek->foto_50_path;
            }
            $foto50Path = $this->imageUploadService->uploadFieldPhoto($photoFiles['foto_50'], $directory);
        } elseif (array_key_exists('foto_50_path', $data) && $data['foto_50_path'] !== null) {
            $foto50Path = $data['foto_50_path'];
        }

        $foto100Path = $proyek->foto_100_path;
        if (isset($photoFiles['foto_100']) && $photoFiles['foto_100'] instanceof UploadedFile) {
            if ($proyek->foto_100_path) {
                $oldPhotosToDelete[] = $proyek->foto_100_path;
            }
            $foto100Path = $this->imageUploadService->uploadFieldPhoto($photoFiles['foto_100'], $directory);
        } elseif (array_key_exists('foto_100_path', $data) && $data['foto_100_path'] !== null) {
            $foto100Path = $data['foto_100_path'];
        }

        $updated = DB::transaction(function () use (
            $proyek,
            $data,
            $oldTahun,
            $newTahun,
            $progres,
            $status,
            $foto0Path,
            $foto50Path,
            $foto100Path,
            $userId
        ) {
            $sumberDana = isset($data['sumber_dana'])
                ? ($data['sumber_dana'] instanceof SumberDanaProyek ? $data['sumber_dana']->value : (string) $data['sumber_dana'])
                : ($proyek->sumber_dana instanceof SumberDanaProyek ? $proyek->sumber_dana->value : (string) $proyek->sumber_dana);

            $proyek->update([
                'tahun_anggaran' => $newTahun,
                'nama_kegiatan' => $data['nama_kegiatan'] ?? $proyek->nama_kegiatan,
                'lokasi_dusun' => $data['lokasi_dusun'] ?? $proyek->lokasi_dusun,
                'pagu_anggaran' => isset($data['pagu_anggaran']) ? (int) $data['pagu_anggaran'] : (int) $proyek->pagu_anggaran,
                'realisasi_anggaran' => isset($data['realisasi_anggaran']) ? (int) $data['realisasi_anggaran'] : (int) $proyek->realisasi_anggaran,
                'sumber_dana' => $sumberDana,
                'volume_fisik' => array_key_exists('volume_fisik', $data) ? $data['volume_fisik'] : $proyek->volume_fisik,
                'waktu_pelaksanaan' => array_key_exists('waktu_pelaksanaan', $data) ? $data['waktu_pelaksanaan'] : $proyek->waktu_pelaksanaan,
                'pelaksana_kegiatan' => array_key_exists('pelaksana_kegiatan', $data) ? $data['pelaksana_kegiatan'] : $proyek->pelaksana_kegiatan,
                'latitude' => array_key_exists('latitude', $data)
                    ? ($data['latitude'] !== null && $data['latitude'] !== '' ? (float) $data['latitude'] : null)
                    : $proyek->latitude,
                'longitude' => array_key_exists('longitude', $data)
                    ? ($data['longitude'] !== null && $data['longitude'] !== '' ? (float) $data['longitude'] : null)
                    : $proyek->longitude,
                'progres_fisik' => $progres,
                'status_proyek' => $status->value,
                'foto_0_path' => $foto0Path,
                'foto_50_path' => $foto50Path,
                'foto_100_path' => $foto100Path,
                'catatan_kendala' => array_key_exists('catatan_kendala', $data) ? $data['catatan_kendala'] : $proyek->catatan_kendala,
            ]);

            $this->syncTahun($newTahun, $userId);
            if ($oldTahun !== $newTahun) {
                $this->syncTahun($oldTahun, $userId);
            }

            return $proyek->fresh();
        });

        // Hapus berkas foto lama dari public disk setelah transaksi database sukses
        foreach ($oldPhotosToDelete as $oldPath) {
            $this->imageUploadService->deleteIfExists($oldPath, 'public');
        }

        return $updated;
    }

    /**
     * Soft-delete proyek pembangunan fisik dalam DB::transaction() dan hitung ulang APBDes Bidang 2 Fisik.
     */
    public function deleteProject(ProyekPembangunan $proyek, ?int $actorId = null): void
    {
        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');
        $tahun = (int) $proyek->tahun_anggaran;

        DB::transaction(function () use ($proyek, $tahun, $userId) {
            $proyek->delete();
            $this->syncTahun($tahun, $userId);
        });
    }
}
