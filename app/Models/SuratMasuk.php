<?php

namespace App\Models;

use Database\Factories\SuratMasukFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratMasuk extends Model
{
    /** @use HasFactory<SuratMasukFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'surat_masuk';

    protected $fillable = [
        'tahun_agenda',
        'nomor_urut_agenda',
        'nomor_surat_masuk',
        'tanggal_surat',
        'tanggal_terima',
        'pengirim',
        'perihal',
        'tujuan_disposisi',
        'catatan_disposisi',
        'berkas_scan_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun_agenda' => 'integer',
            'nomor_urut_agenda' => 'integer',
            'tanggal_surat' => 'date',
            'tanggal_terima' => 'date',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_agenda', $tahun);
    }
}
