<?php

namespace App\Models;

use App\Enums\KategoriKegiatan;
use App\Enums\StatusPublikasi;
use Database\Factories\KegiatanDesaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class KegiatanDesa extends Model
{
    /** @use HasFactory<KegiatanDesaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'kegiatan_desa';

    protected $fillable = [
        'judul',
        'slug',
        'kategori',
        'tanggal_kegiatan',
        'lokasi_dusun',
        'uraian',
        'status_publikasi',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriKegiatan::class,
            'tanggal_kegiatan' => 'date',
            'status_publikasi' => StatusPublikasi::class,
            'published_at' => 'datetime',
        ];
    }

    public function foto(): HasMany
    {
        return $this->hasMany(KegiatanDesaFoto::class, 'kegiatan_desa_id')->orderBy('urutan');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status_publikasi', StatusPublikasi::Published);
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->whereYear('tanggal_kegiatan', $tahun);
    }
}
