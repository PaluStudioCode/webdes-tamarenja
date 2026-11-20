<?php

namespace App\Enums;

enum StatusKesejahteraan: string
{
    case DtsenDesil1 = 'dtsen_desil_1';
    case DtsenDesil2 = 'dtsen_desil_2';
    case DtsenDesil3 = 'dtsen_desil_3';
    case DtsenDesil4 = 'dtsen_desil_4';
    case DtsenDesil5 = 'dtsen_desil_5';
    case NonPrioritas = 'non_prioritas';

    public const DtsenDesil34 = self::DtsenDesil3;

    public const DtsenDesil610 = self::NonPrioritas;

    public function label(): string
    {
        return match ($this) {
            self::DtsenDesil1 => 'Desil 1',
            self::DtsenDesil2 => 'Desil 2',
            self::DtsenDesil3 => 'Desil 3',
            self::DtsenDesil4 => 'Desil 4',
            self::DtsenDesil5 => 'Desil 5',
            self::NonPrioritas => 'Desil 6-10',
        };
    }

    public function isSasaranPrioritas(): bool
    {
        return in_array($this, [
            self::DtsenDesil1,
            self::DtsenDesil2,
            self::DtsenDesil3,
            self::DtsenDesil4,
            self::DtsenDesil5,
        ], true);
    }
}
