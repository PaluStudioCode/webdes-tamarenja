<?php

namespace App\Enums;

enum StatusKependudukan: string
{
    case Aktif = 'aktif';
    case MeninggalDunia = 'meninggal_dunia';
    case PindahKeluar = 'pindah_keluar';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::MeninggalDunia => 'Meninggal Dunia',
            self::PindahKeluar => 'Pindah Keluar',
        };
    }
}
