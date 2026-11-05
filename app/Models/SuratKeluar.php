<?php

namespace App\Models;

use App\Enums\JenisSurat;
use App\Enums\StatusSuratKeluar;
use Database\Factories\SuratKeluarFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SuratKeluar extends Model
{
    /** @use HasFactory<SuratKeluarFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'surat_keluar';

    protected $fillable = [
        'qr_token',
        'penduduk_id',
        'sumber_pengajuan',
        'jenis_surat',
        'kode_klasifikasi',
        'tahun_surat',
        'nomor_urut',
        'nomor_surat',
        'tanggal_surat',
        'keperluan',
        'data_khusus',
        'no_wa_pemohon',
        'opsi_penandatangan',
        'nama_penandatangan',
        'jabatan_penandatangan',
        'nip_penandatangan',
        'status',
        'alasan_penolakan',
        'alasan_pembatalan',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'jenis_surat' => JenisSurat::class,
            'tahun_surat' => 'integer',
            'nomor_urut' => 'integer',
            'tanggal_surat' => 'date',
            'data_khusus' => 'array',
            'status' => StatusSuratKeluar::class,
        ];
    }

    public function penduduk(): BelongsTo
    {
        return $this->belongsTo(Penduduk::class, 'penduduk_id');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopeTahun(Builder $query, int $tahun): Builder
    {
        return $query->where('tahun_surat', $tahun);
    }

    public function scopeTerbit(Builder $query): Builder
    {
        return $query->where('status', StatusSuratKeluar::Terbit);
    }

    public function scopeMenungguVerifikasi(Builder $query): Builder
    {
        return $query->where('status', StatusSuratKeluar::MenungguVerifikasi);
    }
}
