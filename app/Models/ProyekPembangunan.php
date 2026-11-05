<?php

namespace App\Models;

use App\Enums\StatusProyek;
use App\Enums\SumberDanaProyek;
use Database\Factories\ProyekPembangunanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProyekPembangunan extends Model
{
    /** @use HasFactory<ProyekPembangunanFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'proyek_pembangunan';

    protected $fillable = [
        'tahun_anggaran',
        'nama_kegiatan',
        'lokasi_dusun',
        'pagu_anggaran',
        'realisasi_anggaran',
        'sumber_dana',
        'volume_fisik',
        'waktu_pelaksanaan',
        'pelaksana_kegiatan',
        'latitude',
        'longitude',
        'progres_fisik',
        'status_proyek',
        'foto_0_path',
        'foto_50_path',
        'foto_100_path',
        'catatan_kendala',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'pagu_anggaran' => 'integer',
            'realisasi_anggaran' => 'integer',
            'progres_fisik' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
            'sumber_dana' => SumberDanaProyek::class,
            'status_proyek' => StatusProyek::class,
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_anggaran', $tahun);
    }

    public function persentaseSerapan(): float
    {
        if ($this->pagu_anggaran <= 0) {
            return 0.0;
        }

        return round(($this->realisasi_anggaran / $this->pagu_anggaran) * 100, 2);
    }
}
