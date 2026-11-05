<?php

namespace App\Models;

use Database\Factories\KartuKeluargaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class KartuKeluarga extends Model
{
    /** @use HasFactory<KartuKeluargaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'kartu_keluarga';

    protected $fillable = [
        'nomor_kk',
        'dusun',
        'rt',
        'rw',
        'alamat',
    ];

    public function penduduk(): HasMany
    {
        return $this->hasMany(Penduduk::class, 'kartu_keluarga_id');
    }

    public function kepalaKeluarga(): HasOne
    {
        return $this->hasOne(Penduduk::class, 'kartu_keluarga_id')
            ->where('hubungan_keluarga', 'kepala_keluarga');
    }

    /**
     * KK dianggap aktif bila memiliki minimal 1 anggota penduduk berstatus aktif.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->whereHas('penduduk', fn (Builder $q) => $q->aktif());
    }
}
