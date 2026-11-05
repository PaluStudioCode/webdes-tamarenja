<?php

namespace Tests\Unit;

use App\Services\PdfService;
use Tests\TestCase;

class PdfServiceTest extends TestCase
{
    public function test_f4_paper_dimensions(): void
    {
        $dimensions = PdfService::f4Paper();

        $this->assertCount(4, $dimensions);
        $this->assertEquals(0, $dimensions[0]);
        $this->assertEquals(0, $dimensions[1]);
        $this->assertEquals(609.45, $dimensions[2]);
        $this->assertEquals(935.43, $dimensions[3]);
    }

    public function test_creates_f4_pdf_in_portrait(): void
    {
        $pdf = PdfService::createF4FromHtml('<h1>Surat Keterangan Desa</h1>', 'portrait');
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function test_creates_f4_pdf_in_landscape(): void
    {
        $pdf = PdfService::createF4FromHtml('<h1>Laporan Rekapitulasi Pembangunan</h1>', 'landscape');
        $output = $pdf->output();

        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);
    }
}
