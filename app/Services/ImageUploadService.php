<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageUploadService
{
    protected ImageManager $manager;

    public function __construct(?ImageManager $manager = null)
    {
        $this->manager = $manager ?? new ImageManager(new Driver());
    }

    /**
     * Unggah dan konversi Logo Desa / Kop Surat secara paksa ke format PNG.
     * Standar PRD: wajib berformat PNG demi kompatibilitas 100% render DomPDF.
     *
     * @param UploadedFile|string $file
     * @param string $disk
     * @return string Path relatif berkas pada disk (misal: "logo/{uuid}.png")
     */
    public function uploadLogo(UploadedFile|string $file, string $disk = 'public'): string
    {
        $source = is_string($file) ? $file : $file->getRealPath();
        $image = $this->manager->read($source);

        $encoded = $image->toPng();
        $filename = 'logo/' . Str::uuid() . '.png';

        Storage::disk($disk)->put($filename, (string) $encoded);

        return $filename;
    }

    /**
     * Unggah foto lapangan (proyek 0%/50%/100%, galeri kegiatan, foto SOTK)
     * dengan kompresi dan batas resolusi maksimal lebar 1600px.
     *
     * @param UploadedFile|string $file
     * @param string $directory Sub-direktori tujuan (misal: "proyek/2026", "kegiatan/2026", "sotk")
     * @param string $format Target ekstensi (default: "webp", mendukung "jpg", "png")
     * @param int $maxWidth Lebar maksimal gambar (default: 1600px)
     * @param int $quality Kualitas kompresi (default: 80)
     * @param string $disk Disk tujuan (default: "public")
     * @return string Path relatif berkas pada disk
     */
    public function uploadFieldPhoto(
        UploadedFile|string $file,
        string $directory = 'proyek',
        string $format = 'webp',
        int $maxWidth = 1600,
        int $quality = 80,
        string $disk = 'public'
    ): string {
        $source = is_string($file) ? $file : $file->getRealPath();
        $image = $this->manager->read($source);

        if ($image->width() > $maxWidth) {
            $image->scaleDown(width: $maxWidth);
        }

        $format = strtolower($format);
        $encoded = match ($format) {
            'jpg', 'jpeg' => $image->toJpeg(quality: $quality),
            'png' => $image->toPng(),
            default => $image->toWebp(quality: $quality),
        };

        $ext = ($format === 'jpeg') ? 'jpg' : $format;
        $cleanDir = trim($directory, '/');
        $filename = $cleanDir . '/' . Str::uuid() . '.' . $ext;

        Storage::disk($disk)->put($filename, (string) $encoded);

        return $filename;
    }

    /**
     * Hapus berkas lama jika ada di storage sebelum berkas baru disimpan.
     *
     * @param string|null $path
     * @param string $disk
     * @return bool
     */
    public function deleteIfExists(?string $path, string $disk = 'public'): bool
    {
        if ($path && Storage::disk($disk)->exists($path)) {
            return Storage::disk($disk)->delete($path);
        }

        return false;
    }
}
