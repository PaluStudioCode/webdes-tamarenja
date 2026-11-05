<?php

namespace App\Enums;

enum JenisDokumenApbdes: string
{
    case Murni = 'murni';
    case Perubahan = 'perubahan';

    public function label(): string
    {
        return match ($this) {
            self::Murni => 'APBDes Murni',
            self::Perubahan => 'APBDes Perubahan',
        };
    }
}
