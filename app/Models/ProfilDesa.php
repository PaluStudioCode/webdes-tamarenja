<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfilDesa extends Model
{
    protected $table = 'profil_desa';

    protected $fillable = [
        'nama_desa',
        'kode_desa_surat',
        'kecamatan',
        'kabupaten',
        'provinsi',
        'kode_wilayah',
        'daftar_dusun',
        'alamat_kantor',
        'jam_pelayanan',
        'email_desa',
        'no_wa_pelayanan',
        'logo_desa_path',
        'nama_kepala_desa',
        'nip_kepala_desa',
        'foto_kepala_desa_path',
        'sambutan_kepala_desa',
        'nama_sekdes',
        'nip_sekdes',
        'visi',
        'misi',
        'sejarah_singkat',
        'potensi_unggulan',
        'indikator_desa',
        'tahun_anggaran_aktif',
    ];

    protected function casts(): array
    {
        return [
            'daftar_dusun' => 'array',
            'misi' => 'array',
            'indikator_desa' => 'array',
            'tahun_anggaran_aktif' => 'integer',
        ];
    }

    /**
     * @return array{idm: array<string, string>, podeskel: array<string, string>}
     */
    public static function defaultIndikatorDesa(): array
    {
        return [
            'idm' => [
                'status' => 'Desa Maju',
                'skor' => '0,7143',
                'iks' => '0,8214',
                'ike' => '0,7550',
                'ikl' => '0,5667',
            ],
            'podeskel' => [
                'tingkat_perkembangan' => 'Desa Swasembada',
                'fasilitas_kesehatan' => 'Poskesdes & 3 Posyandu Dusun',
                'fasilitas_pendidikan' => 'PAUD/TK & SD Negeri Tamarenja',
                'modal_sosial' => 'Percontohan Nasional Kemendes (2015)',
                'komoditas_utama' => 'Kebun Salak Tamarenja seluas 300–350 Ha',
                'akses_pasar' => 'Pemasaran lintas provinsi (Sulbar, Sulsel, Sulteng, Sulut)',
                'potensi_ekonomi' => 'Potensi agrowisata petik buah perbukitan',
                'topografi' => 'Topografi 100–300 mdpl diapit dua sungai alami',
                'sumber_air' => 'Daerah Irigasi (D.I) Sinente & Katandona',
                'akses_wilayah' => 'Akses 3 dusun: Katandona, Sinente, Irigasi',
            ],
        ];
    }

    /**
     * @return array{idm: array<string, string>, podeskel: array<string, string>}
     */
    public function resolvedIndikatorDesa(): array
    {
        $defaults = self::defaultIndikatorDesa();
        $stored = is_array($this->indikator_desa) ? $this->indikator_desa : [];

        return [
            'idm' => array_merge(
                $defaults['idm'],
                array_filter(
                    is_array($stored['idm'] ?? null) ? $stored['idm'] : [],
                    fn ($v) => $v !== null && trim((string) $v) !== ''
                )
            ),
            'podeskel' => array_merge(
                $defaults['podeskel'],
                array_filter(
                    is_array($stored['podeskel'] ?? null) ? $stored['podeskel'] : [],
                    fn ($v) => $v !== null && trim((string) $v) !== ''
                )
            ),
        ];
    }

    public static function singleton(): ?self
    {
        return static::query()->first();
    }
}
