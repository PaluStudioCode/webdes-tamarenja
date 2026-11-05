<?php

namespace App\Models;

use App\Enums\JenisBansos;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use Database\Factories\PenerimaBansosFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PenerimaBansos extends Model
{
    /** @use HasFactory<PenerimaBansosFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'penerima_bansos';

    protected $fillable = [
        'penduduk_id',
        'jenis_bantuan',
        'bentuk_nominal',
        'tahun_anggaran',
        'tahap_penyaluran',
        'kategori_ekonomi',
        'status_kepesertaan',
        'keterangan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'jenis_bantuan' => JenisBansos::class,
            'tahun_anggaran' => 'integer',
            'kategori_ekonomi' => StatusKesejahteraan::class,
            'status_kepesertaan' => StatusPesertaBansos::class,
        ];
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_kepesertaan', StatusPesertaBansos::Aktif);
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_anggaran', $tahun);
    }
}
