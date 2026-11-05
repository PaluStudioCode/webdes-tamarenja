<?php

namespace App\Services;

use App\Models\ProfilDesa;
use App\Models\SuratKeluar;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SuratNumberGenerator
{
    /**
     * Daftar angka Romawi untuk bulan 1 s/d 12.
     *
     * @var array<int, string>
     */
    protected const ROMAN_MONTHS = [
        1 => 'I',
        2 => 'II',
        3 => 'III',
        4 => 'IV',
        5 => 'V',
        6 => 'VI',
        7 => 'VII',
        8 => 'VIII',
        9 => 'IX',
        10 => 'X',
        11 => 'XI',
        12 => 'XII',
    ];

    /**
     * Hitung nomor urut berikutnya secara aman terhadap SoftDeletes & concurrency (lockForUpdate),
     * bentuk string nomor surat resmi, snapshot pejabat penandatangan, serta token QR UUID v4.
     *
     * Wajib dipanggil di dalam DB::transaction().
     *
     * @return array{
     *     tahun_surat: int,
     *     nomor_urut: int,
     *     nomor_urut_padded: string,
     *     nomor_surat: string,
     *     tanggal_surat: string,
     *     qr_token: string,
     *     opsi_penandatangan: string,
     *     nama_penandatangan: string,
     *     jabatan_penandatangan: string,
     *     nip_penandatangan: ?string
     * }
     */
    public function generateIssuanceAttributes(
        string $kodeKlasifikasi,
        string $opsiPenandatangan = 'kepala_desa',
        CarbonInterface|string|null $tanggalSurat = null,
        ?ProfilDesa $profilDesa = null
    ): array {
        $profil = $profilDesa ?? ProfilDesa::singleton();
        $date = $tanggalSurat instanceof CarbonInterface
            ? $tanggalSurat
            : ($tanggalSurat ? Carbon::parse($tanggalSurat) : now());

        $tahun = (int) $date->year;
        $bulan = (int) $date->month;
        $bulanRomawi = self::ROMAN_MONTHS[$bulan] ?? 'I';
        $kodeDesa = trim((string) ($profil?->kode_desa_surat ?: 'PEMDES-TMR'));

        $maxUrut = (int) SuratKeluar::withTrashed()
            ->where('tahun_surat', $tahun)
            ->lockForUpdate()
            ->max('nomor_urut');

        $nomorUrut = $maxUrut + 1;
        $nomorUrutPadded = str_pad((string) $nomorUrut, 3, '0', STR_PAD_LEFT);

        $nomorSurat = sprintf(
            '%s / %s / %s / %s / %d',
            trim($kodeKlasifikasi),
            $nomorUrutPadded,
            $kodeDesa,
            $bulanRomawi,
            $tahun
        );

        $signer = $this->resolveSignerSnapshot($opsiPenandatangan, $profil);

        return [
            'tahun_surat' => $tahun,
            'nomor_urut' => $nomorUrut,
            'nomor_urut_padded' => $nomorUrutPadded,
            'nomor_surat' => $nomorSurat,
            'tanggal_surat' => $date->toDateString(),
            'qr_token' => (string) Str::uuid(),
            'opsi_penandatangan' => $signer['opsi_penandatangan'],
            'nama_penandatangan' => $signer['nama_penandatangan'],
            'jabatan_penandatangan' => $signer['jabatan_penandatangan'],
            'nip_penandatangan' => $signer['nip_penandatangan'],
        ];
    }

    /**
     * Bentuk snapshot pejabat penandatangan (`kepala_desa` vs `sekdes_an`).
     *
     * @return array{
     *     opsi_penandatangan: string,
     *     nama_penandatangan: string,
     *     jabatan_penandatangan: string,
     *     nip_penandatangan: ?string
     * }
     */
    public function resolveSignerSnapshot(string $opsiPenandatangan, ?ProfilDesa $profilDesa = null): array
    {
        $profil = $profilDesa ?? ProfilDesa::singleton();

        if ($opsiPenandatangan === 'sekdes_an') {
            return [
                'opsi_penandatangan' => 'sekdes_an',
                'nama_penandatangan' => (string) ($profil?->nama_sekdes ?: 'Sekretaris Desa'),
                'jabatan_penandatangan' => 'a.n. Kepala Desa - Sekretaris Desa',
                'nip_penandatangan' => $profil?->nip_sekdes ?: null,
            ];
        }

        return [
            'opsi_penandatangan' => 'kepala_desa',
            'nama_penandatangan' => (string) ($profil?->nama_kepala_desa ?: 'Kepala Desa'),
            'jabatan_penandatangan' => 'Kepala Desa',
            'nip_penandatangan' => $profil?->nip_kepala_desa ?: null,
        ];
    }
}
