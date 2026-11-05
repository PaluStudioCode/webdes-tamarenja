<?php

namespace App\Enums;

enum KelompokPosApbdes: string
{
    case Pendapatan = 'pendapatan';
    case Belanja = 'belanja';
    case PembiayaanPenerimaan = 'pembiayaan_penerimaan';
    case PembiayaanPengeluaran = 'pembiayaan_pengeluaran';

    public function label(): string
    {
        return match ($this) {
            self::Pendapatan => 'Pendapatan Desa',
            self::Belanja => 'Belanja Desa',
            self::PembiayaanPenerimaan => 'Penerimaan Pembiayaan',
            self::PembiayaanPengeluaran => 'Pengeluaran Pembiayaan',
        };
    }
}
