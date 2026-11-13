<?php

namespace App\Models;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use Database\Factories\PendudukFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penduduk extends Model
{
    /** @use HasFactory<PendudukFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'penduduk';

    protected $fillable = [
        'kartu_keluarga_id',
        'nik',
        'nama_lengkap',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'suku',
        'kewarganegaraan',
        'pendidikan',
        'peserta_kip_pip',
        'kelas_sekolah',
        'pekerjaan',
        'status_perkawinan',
        'hubungan_keluarga',
        'status_kesejahteraan',
        'jenis_bpjs',
        'status_kependudukan',
        'tanggal_mutasi',
        'catatan_mutasi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'tanggal_mutasi' => 'date',
            'status_kesejahteraan' => StatusKesejahteraan::class,
            'status_kependudukan' => StatusKependudukan::class,
        ];
    }

    public function kartuKeluarga(): BelongsTo
    {
        return $this->belongsTo(KartuKeluarga::class, 'kartu_keluarga_id');
    }

    public function suratKeluar(): HasMany
    {
        return $this->hasMany(SuratKeluar::class, 'penduduk_id');
    }

    public function penerimaBansos(): HasMany
    {
        return $this->hasMany(PenerimaBansos::class, 'penduduk_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_kependudukan', StatusKependudukan::Aktif);
    }

    public function scopePrioritasDtsen(Builder $query): Builder
    {
        return $query->whereIn('status_kesejahteraan', [
            StatusKesejahteraan::DtsenDesil1,
            StatusKesejahteraan::DtsenDesil2,
            StatusKesejahteraan::DtsenDesil34,
        ]);
    }
}
