<?php

namespace App\Enums;

enum JenisBansos: string
{
    case Pkh = 'pkh';
    case Bpnt = 'bpnt';
    case BltDd = 'blt_dd';
    case Rtlh = 'rtlh';
    case PbiJkn = 'pbi_jkn';
    case BantuanDaerah = 'bantuan_daerah';

    public function label(): string
    {
        return match ($this) {
            self::Pkh => 'Program Keluarga Harapan (PKH)',
            self::Bpnt => 'Bantuan Pangan Non-Tunai (BPNT)',
            self::BltDd => 'BLT Dana Desa (BLT-DD)',
            self::Rtlh => 'Rumah Tidak Layak Huni (RTLH)',
            self::PbiJkn => 'PBI Jaminan Kesehatan (PBI-JKN)',
            self::BantuanDaerah => 'Bantuan Pemerintah Daerah',
        };
    }
}
