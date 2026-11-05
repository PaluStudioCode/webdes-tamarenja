<?php

namespace App\Enums;

enum StatusPesertaBansos: string
{
    case Aktif = 'aktif';
    case Graduasi = 'graduasi';
    case Pindah = 'pindah';
    case MeninggalDunia = 'meninggal_dunia';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Graduasi => 'Graduasi (Mandiri)',
            self::Pindah => 'Pindah Keluar',
            self::MeninggalDunia => 'Meninggal Dunia',
        };
    }
}
