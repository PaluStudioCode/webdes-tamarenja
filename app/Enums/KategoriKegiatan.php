<?php

namespace App\Enums;

enum KategoriKegiatan: string
{
    case PenyaluranBansos = 'penyaluran_bansos';
    case PosyanduKesehatan = 'posyandu_kesehatan';
    case GotongRoyong = 'gotong_royong';
    case Musdes = 'musdes';
    case PelatihanBumdes = 'pelatihan_bumdes';

    public function label(): string
    {
        return match ($this) {
            self::PenyaluranBansos => 'Penyaluran Bantuan Sosial',
            self::PosyanduKesehatan => 'Posyandu & Kesehatan',
            self::GotongRoyong => 'Gotong Royong',
            self::Musdes => 'Musyawarah Desa (Musdes)',
            self::PelatihanBumdes => 'Pelatihan & BUMDes',
        };
    }
}
