<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class DummyAssetGenerator
{
    /**
     * Cek apakah berkas aset asli tersedia di direktori permanen `database/seeders/assets/{path}`.
     */
    protected static function resolveSeedAssetBytes(string $path): ?string
    {
        $seedAssetPath = base_path('database/seeders/assets/' . ltrim($path, '/\\'));

        if (is_file($seedAssetPath) && is_readable($seedAssetPath)) {
            $contents = @file_get_contents($seedAssetPath);
            if ($contents !== false && $contents !== '') {
                return $contents;
            }
        }

        return null;
    }

    /**
     * Buat berkas gambar PNG valid ringan menggunakan ekstensi GD bawaan PHP,
     * atau salin dari `database/seeders/assets/{path}` bila tersedia.
     */
    public static function putPng(
        string $path,
        string $disk = 'public',
        int $width = 240,
        int $height = 240,
        array $rgb = [29, 78, 216]
    ): string {
        $seedBytes = self::resolveSeedAssetBytes($path);
        if ($seedBytes !== null) {
            Storage::disk($disk)->put($path, $seedBytes);

            return $path;
        }

        if (! Storage::disk($disk)->exists($path)) {
            $img = imagecreatetruecolor($width, $height);
            $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            $fg = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $bg);
            imagestring($img, 5, 20, (int) ($height / 2) - 8, 'DESA TAMARENJA', $fg);

            ob_start();
            imagepng($img);
            $bytes = (string) ob_get_clean();
            imagedestroy($img);

            Storage::disk($disk)->put($path, $bytes);
        }

        return $path;
    }

    /**
     * Buat berkas gambar JPEG valid ringan menggunakan ekstensi GD bawaan PHP,
     * atau salin dari `database/seeders/assets/{path}` bila tersedia.
     */
    public static function putJpg(
        string $path,
        string $label = 'DOKUMENTASI DESA',
        string $disk = 'public',
        int $width = 800,
        int $height = 500,
        array $rgb = [30, 64, 175]
    ): string {
        $seedBytes = self::resolveSeedAssetBytes($path);
        if ($seedBytes !== null) {
            Storage::disk($disk)->put($path, $seedBytes);

            return $path;
        }

        if (! Storage::disk($disk)->exists($path)) {
            $img = imagecreatetruecolor($width, $height);
            $bg = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
            $fg = imagecolorallocate($img, 255, 255, 255);
            imagefill($img, 0, 0, $bg);
            imagestring($img, 5, 30, (int) ($height / 2) - 10, $label, $fg);

            ob_start();
            imagejpeg($img, null, 75);
            $bytes = (string) ob_get_clean();
            imagedestroy($img);

            Storage::disk($disk)->put($path, $bytes);
        }

        return $path;
    }

    /**
     * Buat berkas dokumen PDF 1.4 minimalis yang valid dan ringan tanpa overhead render berat.
     *
     * @return array{path:string, size_kb:int}
     */
    public static function putPdf(string $path, string $title = 'Dokumen Resmi Desa Tamarenja', string $disk = 'public'): array
    {
        $seedBytes = self::resolveSeedAssetBytes($path);
        if ($seedBytes !== null) {
            Storage::disk($disk)->put($path, $seedBytes);
            $bytes = Storage::disk($disk)->size($path);

            return [
                'path' => $path,
                'size_kb' => max(1, (int) ceil($bytes / 1024)),
            ];
        }

        if (! Storage::disk($disk)->exists($path)) {
            $safeTitle = preg_replace('/[^A-Za-z0-9 \-_.\/]/', '', $title) ?: 'Dokumen Desa';
            $stream = "BT /F1 14 Tf 50 750 Td ({$safeTitle}) Tj ET";
            $len = strlen($stream);

            $pdf = "%PDF-1.4\n"
                . "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n"
                . "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n"
                . "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 609.45 935.43] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n"
                . "4 0 obj << /Length {$len} >> stream\n{$stream}\nendstream endobj\n"
                . "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n"
                . "xref\n0 6\n"
                . "0000000000 65535 f \n"
                . "0000000009 00000 n \n"
                . "0000000058 00000 n \n"
                . "0000000115 00000 n \n"
                . "0000000245 00000 n \n"
                . "0000000330 00000 n \n"
                . "trailer << /Size 6 /Root 1 0 R >>\n"
                . "startxref\n400\n%%EOF\n";

            Storage::disk($disk)->put($path, $pdf);
        }

        $bytes = Storage::disk($disk)->size($path);

        return [
            'path' => $path,
            'size_kb' => max(1, (int) ceil($bytes / 1024)),
        ];
    }
}
