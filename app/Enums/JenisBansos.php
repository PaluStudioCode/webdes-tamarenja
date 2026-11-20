<?php

namespace App\Enums;

enum JenisBansos: string
{
    // 1. Bantuan Sosial Per KK
    case Pkh = 'pkh';
    case Bpnt = 'bpnt';
    case BltDd = 'blt_dd';
    case BantuanPanganBeras = 'bantuan_pangan_beras';
    case BantuanDaerah = 'bantuan_daerah';
    case Rtlh = 'rtlh';

    // 2. Bantuan Sosial Perorangan
    case Pip = 'pip';
    case PbiJkn = 'pbi_jkn';
    case Atensi = 'atensi';
    case BantuanLansia = 'bantuan_lansia';
    case BantuanDisabilitas = 'bantuan_disabilitas';
    case BantuanKorbanBencana = 'bantuan_korban_bencana';
    case BeasiswaDaerah = 'beasiswa_daerah';

    // 3. Bantuan Kelompok Masyarakat
    case BantuanKelompokTani = 'bantuan_kelompok_tani';
    case BantuanUmkm = 'bantuan_umkm';
    case BantuanSaranaPertanian = 'bantuan_sarana_pertanian';

    public function label(): string
    {
        return match ($this) {
            self::Pkh => 'Program Keluarga Harapan (PKH)',
            self::Bpnt => 'Bantuan Pangan Non-Tunai (BPNT)',
            self::BltDd => 'BLT Dana Desa (BLT-DD)',
            self::BantuanPanganBeras => 'Bantuan Pangan Beras',
            self::BantuanDaerah => 'Bantuan Pangan Daerah',
            self::Rtlh => 'Bantuan Rumah Tidak Layak Huni (RTLH)',
            self::Pip => 'Program Indonesia Pintar (PIP)',
            self::PbiJkn => 'PBI-JK (Jaminan Kesehatan / KIS)',
            self::Atensi => 'ATENSI (Asistensi Rehabilitasi Sosial)',
            self::BantuanLansia => 'Bantuan Lansia',
            self::BantuanDisabilitas => 'Bantuan Penyandang Disabilitas',
            self::BantuanKorbanBencana => 'Bantuan Korban Bencana',
            self::BeasiswaDaerah => 'Beasiswa Daerah',
            self::BantuanKelompokTani => 'Bantuan Kelompok Tani',
            self::BantuanUmkm => 'Bantuan UMKM',
            self::BantuanSaranaPertanian => 'Bantuan Sarana Pertanian',
        };
    }

    public function kategori(): string
    {
        return match ($this) {
            self::Pkh,
            self::Bpnt,
            self::BltDd,
            self::BantuanPanganBeras,
            self::BantuanDaerah,
            self::Rtlh => 'per_kk',

            self::Pip,
            self::PbiJkn,
            self::Atensi,
            self::BantuanLansia,
            self::BantuanDisabilitas,
            self::BantuanKorbanBencana,
            self::BeasiswaDaerah => 'perorangan',

            self::BantuanKelompokTani,
            self::BantuanUmkm,
            self::BantuanSaranaPertanian => 'kelompok',
        };
    }

    public function kategoriLabel(): string
    {
        return match ($this->kategori()) {
            'per_kk' => 'Bantuan Sosial Per KK',
            'perorangan' => 'Bantuan Sosial Perorangan',
            'kelompok' => 'Bantuan Kelompok Masyarakat',
            default => 'Bantuan Sosial',
        };
    }

    public function defaultNominal(): string
    {
        return match ($this) {
            self::Pkh => 'Sesuai Komponen PKH',
            self::Bpnt => 'Rp 200.000 / Bulan',
            self::BltDd => 'Rp 300.000 / Bulan',
            self::BantuanPanganBeras => 'Beras 10 Kg / Tahap',
            self::BantuanDaerah => 'Paket Pangan Daerah',
            self::Rtlh => 'Material Rehab Rumah',
            self::Pip => 'Bantuan Tunai Pendidikan',
            self::PbiJkn => 'Iuran BPJS Kesehatan Gratis',
            self::Atensi => 'Paket Nutrisi / Alat Bantu',
            self::BantuanLansia => 'Santunan / Permakanan Lansia',
            self::BantuanDisabilitas => 'Santunan / Alat Bantu Disabilitas',
            self::BantuanKorbanBencana => 'Santunan / Logistik Darurat',
            self::BeasiswaDaerah => 'Beasiswa Pendidikan Daerah',
            self::BantuanKelompokTani => 'Hibah / Bantuan Kelompok Tani',
            self::BantuanUmkm => 'Bantuan Alat / Modal UMKM',
            self::BantuanSaranaPertanian => 'Bibit / Pupuk / Alsintan',
        };
    }

    public function isPerKk(): bool
    {
        return $this->kategori() === 'per_kk';
    }

    public function isKelompok(): bool
    {
        return $this->kategori() === 'kelompok';
    }
}
