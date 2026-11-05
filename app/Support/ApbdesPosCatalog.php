<?php

namespace App\Support;

class ApbdesPosCatalog
{
    public const KODE_BIDANG_2_FISIK = 'bidang_2_fisik';

    /**
     * Kembalikan seluruh 14 pos anggaran baku APBDes sesuai urutan resmi PRD 03.
     *
     * @return list<array{urutan:int, kelompok:string, kode_pos:string, nama_pos:string, is_otomatis_modul_1:bool}>
     */
    public static function all(): array
    {
        return config('apbdes.katalog_pos', []);
    }

    /**
     * Daftar ke-14 string kode_pos baku.
     *
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::all(), 'kode_pos');
    }

    /**
     * Cari definisi satu pos berdasarkan kode_pos.
     *
     * @return array{urutan:int, kelompok:string, kode_pos:string, nama_pos:string, is_otomatis_modul_1:bool}|null
     */
    public static function find(string $kodePos): ?array
    {
        foreach (self::all() as $pos) {
            if ($pos['kode_pos'] === $kodePos) {
                return $pos;
            }
        }

        return null;
    }
}
