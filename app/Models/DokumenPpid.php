<?php

namespace App\Models;

use Database\Factories\DokumenPpidFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenPpid extends Model
{
    /** @use HasFactory<DokumenPpidFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'dokumen_ppid';

    protected $fillable = [
        'judul_dokumen',
        'kategori',
        'tahun_dokumen',
        'file_pdf_path',
        'ukuran_file_kb',
        'jumlah_unduhan',
        'is_publik',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'tahun_dokumen' => 'integer',
            'ukuran_file_kb' => 'integer',
            'jumlah_unduhan' => 'integer',
            'is_publik' => 'boolean',
        ];
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_publik', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_publik', true);
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_dokumen', $tahun);
    }
}
