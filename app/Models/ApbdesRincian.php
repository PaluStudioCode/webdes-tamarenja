<?php

namespace App\Models;

use App\Enums\KelompokPosApbdes;
use App\Support\ApbdesPosCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApbdesRincian extends Model
{
    protected $table = 'apbdes_rincian';

    protected $fillable = [
        'apbdes_id',
        'kelompok',
        'kode_pos',
        'nama_pos',
        'is_otomatis_modul_1',
        'pagu_anggaran',
        'realisasi_tahap_1',
        'realisasi_tahap_2',
        'realisasi_tahap_3',
        'total_realisasi',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'kelompok' => KelompokPosApbdes::class,
            'is_otomatis_modul_1' => 'boolean',
            'pagu_anggaran' => 'integer',
            'realisasi_tahap_1' => 'integer',
            'realisasi_tahap_2' => 'integer',
            'realisasi_tahap_3' => 'integer',
            'total_realisasi' => 'integer',
            'urutan' => 'integer',
        ];
    }

    public function apbdes(): BelongsTo
    {
        return $this->belongsTo(Apbdes::class, 'apbdes_id');
    }

    /**
     * @return list<array{urutan:int, kelompok:string, kode_pos:string, nama_pos:string, is_otomatis_modul_1:bool}>
     */
    public static function katalogPosBaku(): array
    {
        return ApbdesPosCatalog::all();
    }
}
