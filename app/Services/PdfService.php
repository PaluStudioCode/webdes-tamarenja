<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfWrapper;

class PdfService
{
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
     * Buat wrapper PDF DomPDF dari HTML/View dengan kertas F4.
     *
     * @param string $view
     * @param array $data
     * @param string $orientation 'portrait'|'landscape'
     * @return DomPdfWrapper
     */
    public static function createF4Pdf(string $view, array $data = [], string $orientation = 'portrait'): DomPdfWrapper
    {
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
