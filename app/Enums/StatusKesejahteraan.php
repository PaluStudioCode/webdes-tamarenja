<?php

namespace App\Enums;

enum StatusKesejahteraan: string
{
    case DtsenDesil1 = 'dtsen_desil_1';
    case DtsenDesil2 = 'dtsen_desil_2';
    case DtsenDesil34 = 'dtsen_desil_3_4';
    case NonPrioritas = 'non_prioritas';

    public function label(): string
    {
        return match ($this) {
            self::DtsenDesil1 => 'DTSEN Desil 1 (Miskin Ekstrem)',
            self::DtsenDesil2 => 'DTSEN Desil 2 (Miskin)',
            self::DtsenDesil34 => 'DTSEN Desil 3-4 (Rentan Miskin)',
            self::NonPrioritas => 'Non-Prioritas (Desil 5-10 / Mampu)',
        };
    }

    public function isSasaranPrioritas(): bool
    {
        return in_array($this, [
            self::DtsenDesil1,
            self::DtsenDesil2,
            self::DtsenDesil34,
        ], true);
    }
}
