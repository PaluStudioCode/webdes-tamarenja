<?php

namespace App\Models;

use App\Enums\JenisDokumenApbdes;
use Database\Factories\ApbdesFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Apbdes extends Model
{
    /** @use HasFactory<ApbdesFactory> */
    use HasFactory;

    protected $table = 'apbdes';

    protected $fillable = [
        'tahun_anggaran',
        'jenis_dokumen',
        'is_aktif_publik',
        'is_terkunci',
        'nomor_perdes',
        'tanggal_penetapan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun_anggaran' => 'integer',
            'jenis_dokumen' => JenisDokumenApbdes::class,
            'is_aktif_publik' => 'boolean',
            'is_terkunci' => 'boolean',
            'tanggal_penetapan' => 'date',
        ];
    }

    public function rincian(): HasMany
    {
        return $this->hasMany(ApbdesRincian::class, 'apbdes_id')->orderBy('urutan');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_anggaran', $tahun);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif_publik', true);
    }
}
