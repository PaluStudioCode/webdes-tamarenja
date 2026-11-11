<?php

namespace App\Services;

use App\Models\ProfilDesa;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    /**
     * Path default logo Garuda Pancasila di storage public.
     */
    public const DEFAULT_GARUDA_LOGO_PATH = 'logo/logo-desa-tamarenja.png';

    /**
     * Dimensi baku kertas F4 / Folio (215 × 330 mm) dalam satuan points DomPDF:
     * [0, 0, 609.45, 935.43]
     */
    public const F4_PAPER = [0, 0, 609.45, 935.43];

    /**
     * Dapatkan bounds kertas F4 untuk DomPDF.
     *
     * @return array<int, float>
     */
    public static function f4Paper(): array
    {
        return config('pdf.f4.dimensions', self::F4_PAPER);
    }

    /**
     * Resolusi logo Garuda untuk Kop Surat PDF dalam bentuk Base64 Data URI.
     * Mengutamakan path pada profil desa, lalu fallback ke storage public `logo/logo-desa-tamarenja.png`
     * atau aset permanen seeder agar logo Garuda selalu tampil di PDF.
     */
    public static function resolveLogoBase64(?ProfilDesa $profilDesa = null): ?string
    {
        $candidatePaths = array_values(array_unique(array_filter([
            $profilDesa?->logo_desa_path,
            self::DEFAULT_GARUDA_LOGO_PATH,
        ])));

        foreach ($candidatePaths as $relativePath) {
            if (Storage::disk('public')->exists($relativePath)) {
                $bytes = Storage::disk('public')->get($relativePath);
                if (! empty($bytes)) {
                    return 'data:image/png;base64,' . base64_encode($bytes);
                }
            }
        }

        $fallbackFiles = [
            storage_path('app/public/' . self::DEFAULT_GARUDA_LOGO_PATH),
            base_path('database/seeders/assets/' . self::DEFAULT_GARUDA_LOGO_PATH),
        ];

        foreach ($fallbackFiles as $filePath) {
            if (is_file($filePath) && is_readable($filePath)) {
                $bytes = @file_get_contents($filePath);
                if ($bytes !== false && $bytes !== '') {
                    return 'data:image/png;base64,' . base64_encode($bytes);
                }
            }
        }

        return null;
    }

    /**
     * Buat wrapper PDF DomPDF dari HTML/View dengan kertas F4.
     *
     * @param string $view
     * @param array $data
     * @param string $orientation 'portrait'|'landscape'
     * @return DomPdfWrapper
     */
    public static function createF4Pdf(string $view, array $data = [], string $orientation = 'portrait'): DomPdfWrapper
    {
        if (empty($data['logoBase64'])) {
            $profilDesa = $data['profilDesa'] ?? null;
            $data['logoBase64'] = self::resolveLogoBase64($profilDesa instanceof ProfilDesa ? $profilDesa : null);
        }

        return Pdf::loadView($view, $data)
            ->setPaper(self::f4Paper(), $orientation);
    }

    /**
     * Buat wrapper PDF DomPDF langsung dari string HTML dengan kertas F4.
     *
     * @param string $html
     * @param string $orientation 'portrait'|'landscape'
     * @return DomPdfWrapper
     */
    public static function createF4FromHtml(string $html, string $orientation = 'portrait'): DomPdfWrapper
    {
        return Pdf::loadHTML($html)
            ->setPaper(self::f4Paper(), $orientation);
    }

    /**
     * Terapkan ukuran F4 pada wrapper DomPDF yang sudah ada.
     *
     * @param DomPdfWrapper $pdf
     * @param string $orientation
     * @return DomPdfWrapper
     */
    public static function setF4(DomPdfWrapper $pdf, string $orientation = 'portrait'): DomPdfWrapper
    {
        return $pdf->setPaper(self::f4Paper(), $orientation);
    }
}
