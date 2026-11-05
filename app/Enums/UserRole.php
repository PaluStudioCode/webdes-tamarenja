<?php

namespace App\Enums;

enum UserRole: string
{
    case Operator = 'operator';
    case KepalaDesa = 'kepala_desa';

    public function label(): string
    {
        return match ($this) {
            self::Operator => 'Operator Desa',
            self::KepalaDesa => 'Kepala Desa',
        };
    }
}
