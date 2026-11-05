<?php

namespace App\Enums;

enum StatusProyek: string
{
    case BelumDimulai = 'belum_dimulai';
    case SedangDikerjakan = 'sedang_dikerjakan';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::BelumDimulai => 'Belum Dimulai',
            self::SedangDikerjakan => 'Sedang Dikerjakan',
            self::Selesai => 'Selesai',
        };
    }
}
