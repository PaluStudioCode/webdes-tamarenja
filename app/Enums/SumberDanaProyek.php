<?php

namespace App\Enums;

enum SumberDanaProyek: string
{
    case Dds = 'dds';
    case Add = 'add';
    case BantuanKeuangan = 'bantuan_keuangan';
    case Pades = 'pades';

    public function label(): string
    {
        return match ($this) {
            self::Dds => 'Dana Desa (DDS)',
            self::Add => 'Alokasi Dana Desa (ADD)',
            self::BantuanKeuangan => 'Bantuan Keuangan',
            self::Pades => 'Pendapatan Asli Desa (PADes)',
        };
    }
}
