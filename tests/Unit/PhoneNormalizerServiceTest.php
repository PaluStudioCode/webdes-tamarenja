<?php

namespace Tests\Unit;

use App\Services\PhoneNormalizerService;
use PHPUnit\Framework\TestCase;

class PhoneNormalizerServiceTest extends TestCase
{
    public function test_normalizes_standard_indonesian_phone_numbers(): void
    {
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('081234567890'));
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('+6281234567890'));
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('6281234567890'));
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('81234567890'));
    }

    public function test_normalizes_phone_numbers_with_spaces_dashes_and_parentheses(): void
    {
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('0812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('+62 812-3456-7890'));
        $this->assertEquals('6281234567890', PhoneNormalizerService::normalize('(0812) 3456 7890'));
    }

    public function test_handles_empty_or_null(): void
    {
        $this->assertEquals('', PhoneNormalizerService::normalize(null));
        $this->assertEquals('', PhoneNormalizerService::normalize(''));
        $this->assertEquals('', PhoneNormalizerService::normalize('   '));
    }

    public function test_generates_valid_whatsapp_url(): void
    {
        $this->assertEquals('https://wa.me/6281234567890', PhoneNormalizerService::toWhatsAppUrl('081234567890'));

        $urlWithMessage = PhoneNormalizerService::toWhatsAppUrl('081234567890', 'Halo Bpk/Ibu, Surat sudah selesai.');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $urlWithMessage);
        $this->assertStringContainsString('Halo%20Bpk%2FIbu', $urlWithMessage);
    }
}
