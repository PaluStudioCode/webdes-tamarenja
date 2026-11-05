<?php

namespace App\Models;

use Database\Factories\PerangkatDesaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerangkatDesa extends Model
{
    /** @use HasFactory<PerangkatDesaFactory> */
    use HasFactory;

    protected $table = 'perangkat_desa';

    protected $fillable = [
        'nama_lengkap',
        'jabatan',
        'kelompok_sotk',
        'nip_niap',
        'foto_path',
        'urutan',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'is_aktif' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }
}
