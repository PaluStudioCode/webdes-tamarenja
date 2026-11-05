<?php

namespace App\Services;

class DataMaskingService
{
    /**
     * Menyamarkan nama warga berdasarkan standar UU PDP:
     * 1 huruf pertama tiap kata diikuti max(3, panjang_kata - 1) tanda bintang.
     *
     * Contoh:
     * - "Budi Santoso" -> "B*** S******"
     * - "M. Ridwan"    -> "M*** R*****"
     *
     * @param string|null $name
     * @return string
     */
    public static function maskName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '';
        }

        $words = preg_split('/\s+/', trim($name));
        $maskedWords = [];

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $firstChar = mb_substr($word, 0, 1);
            $wordLength = mb_strlen($word);
            $starCount = max(3, $wordLength - 1);
            $maskedWords[] = $firstChar . str_repeat('*', $starCount);
        }

        return implode(' ', $maskedWords);
    }

    /**
     * Menyamarkan NIK (menyisakan 6 digit pertama dan 4 digit terakhir jika diperlukan).
     *
     * @param string|null $nik
     * @return string
     */
    public static function maskNik(?string $nik): string
    {
        if ($nik === null || trim($nik) === '') {
            return '';
        }

        $clean = preg_replace('/\D/', '', $nik);
        $len = strlen($clean);

        if ($len < 8) {
            return str_repeat('*', $len);
        }

        return substr($clean, 0, 6) . str_repeat('*', max(0, $len - 10)) . substr($clean, -4);
    }
}
