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
        'tahun_anggaran_aktif',
    ];

    protected function casts(): array
    {
        return [
            'daftar_dusun' => 'array',
            'misi' => 'array',
            'tahun_anggaran_aktif' => 'integer',
        ];
    }

    public static function singleton(): ?self
    {
        return static::query()->first();
    }
}
