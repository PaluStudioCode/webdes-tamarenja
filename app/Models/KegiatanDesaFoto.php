<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KegiatanDesaFoto extends Model
{
    protected $table = 'kegiatan_desa_foto';

    protected $fillable = [
        'kegiatan_desa_id',
        'foto_path',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    public function kegiatanDesa(): BelongsTo
    {
        return $this->belongsTo(KegiatanDesa::class, 'kegiatan_desa_id');
    }
}
