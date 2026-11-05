<?php

namespace Tests\Unit;

use App\Services\DataMaskingService;
use PHPUnit\Framework\TestCase;

class DataMaskingServiceTest extends TestCase
{
    public function test_mask_name_follows_prd_specification(): void
    {
        // Standar wajib PRD: 1 huruf pertama + max(3, panjang_kata - 1) bintang per kata
        $this->assertEquals('B*** S******', DataMaskingService::maskName('Budi Santoso'));
        $this->assertEquals('M*** R*****', DataMaskingService::maskName('M. Ridwan'));
    }

    public function test_mask_name_handles_various_name_lengths(): void
    {
        // 1 karakter kata -> 1 + max(3, 0) = 1 + 3 bintang -> 4 char
        $this->assertEquals('A***', DataMaskingService::maskName('A'));

        // Nama 3 kata
        $this->assertEquals('A*** S*** D***', DataMaskingService::maskName('Ali Siti Dewi'));

        // Nama panjang (Muhammad = 8 huruf -> M + 7 bintang)
        $this->assertEquals('M*******', DataMaskingService::maskName('Muhammad'));
    }

    public function test_mask_name_handles_empty_or_null_values(): void
    {
        $this->assertEquals('', DataMaskingService::maskName(null));
        $this->assertEquals('', DataMaskingService::maskName(''));
        $this->assertEquals('', DataMaskingService::maskName('   '));
    }

    public function test_mask_nik(): void
    {
        $this->assertEquals('320101******0001', DataMaskingService::maskNik('3201011234560001'));
        $this->assertEquals('', DataMaskingService::maskNik(null));
    }
}
