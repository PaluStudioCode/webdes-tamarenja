<?php

namespace App\Enums;

enum StatusSuratKeluar: string
{
    case MenungguVerifikasi = 'menunggu_verifikasi';
    case Terbit = 'terbit';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::MenungguVerifikasi => 'Menunggu Verifikasi',
            self::Terbit => 'Terbit',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }
}
