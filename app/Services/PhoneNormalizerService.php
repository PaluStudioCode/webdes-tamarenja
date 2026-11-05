<?php

namespace App\Services;

class PhoneNormalizerService
{
    /**
     * Menormalisasi nomor telepon/WhatsApp ke format internasional Indonesia baku (628...).
     *
     * Contoh:
     * - "081234567890"        -> "6281234567890"
     * - "+6281234567890"       -> "6281234567890"
     * - "6281234567890"        -> "6281234567890"
     * - "0812-3456-7890"       -> "6281234567890"
     * - "+62 812-3456-7890"    -> "6281234567890"
     *
     * @param string|null $phone
     * @return string
     */
    public static function normalize(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        // Hapus semua karakter selain angka
        $digits = preg_replace('/\D/', '', $phone);

        if ($digits === '') {
            return '';
        }

        // Jika diawali 08..., ganti 0 dengan 62
        if (str_starts_with($digits, '0')) {
            return '62' . substr($digits, 1);
        }

        // Jika diawali 8..., tambahkan 62 di depan
        if (str_starts_with($digits, '8')) {
            return '62' . $digits;
        }

        // Jika sudah diawali 62..., kembalikan langsung
        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Menghasilkan URL WhatsApp (wa.me) valid beserta pesan opsional yang di-encode.
     *
     * @param string $phone
     * @param string|null $message
     * @return string
     */
    public static function toWhatsAppUrl(string $phone, ?string $message = null): string
    {
        $normalized = self::normalize($phone);

        if ($normalized === '') {
            return '';
        }

        $url = 'https://wa.me/' . $normalized;

        if ($message !== null && trim($message) !== '') {
            $url .= '?text=' . rawurlencode($message);
        }

        return $url;
    }
}
