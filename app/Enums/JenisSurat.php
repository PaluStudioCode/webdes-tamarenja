<?php

namespace App\Enums;

enum JenisSurat: string
{
    case Sku = 'sku';
    case Domisili = 'domisili';
    case Sktm = 'sktm';
    case PengantarSkck = 'pengantar_skck';
    case Kematian = 'kematian';
    case Kelahiran = 'kelahiran';
    case BelumMenikah = 'belum_menikah';
    case JandaDuda = 'janda_duda';
    case Kehilangan = 'kehilangan';
    case KeteranganUmum = 'keterangan_umum';

    public function label(): string
    {
        return match ($this) {
            self::Sku => 'Surat Keterangan Usaha (SKU)',
            self::Domisili => 'Surat Keterangan Domisili',
            self::Sktm => 'Surat Keterangan Tidak Mampu (SKTM)',
            self::PengantarSkck => 'Surat Pengantar SKCK',
            self::Kematian => 'Surat Keterangan Kematian',
            self::Kelahiran => 'Surat Keterangan Kelahiran',
            self::BelumMenikah => 'Surat Keterangan Belum Menikah',
            self::JandaDuda => 'Surat Keterangan Janda / Duda',
            self::Kehilangan => 'Surat Pengantar Kehilangan',
            self::KeteranganUmum => 'Surat Keterangan Umum',
        };
    }

    public function defaultKodeKlasifikasi(): string
    {
        return match ($this) {
            self::Sku => '500',
            self::Domisili => '470',
            self::Sktm => '400',
            self::PengantarSkck => '300',
            self::Kematian => '474.3',
            self::Kelahiran => '474.1',
            self::BelumMenikah => '474.2',
            self::JandaDuda => '474.2',
            self::Kehilangan => '300',
            self::KeteranganUmum => '140',
        };
    }

    /**
     * @return list<string>
     */
    public function allowedKodeKlasifikasi(): array
    {
        return match ($this) {
            self::Sktm => ['400', '460'],
            self::KeteranganUmum => ['140', '000'],
            default => [$this->defaultKodeKlasifikasi()],
        };
    }
}
