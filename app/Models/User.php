<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isOperator(): bool
    {
        return $this->role === UserRole::Operator;
    }

    public function isKepalaDesa(): bool
    {
        return $this->role === UserRole::KepalaDesa;
    }

    public function proyekPembangunan(): HasMany
    {
        return $this->hasMany(ProyekPembangunan::class, 'created_by');
    }

    public function apbdes(): HasMany
    {
        return $this->hasMany(Apbdes::class, 'created_by');
    }

    public function suratKeluarDiproses(): HasMany
    {
        return $this->hasMany(SuratKeluar::class, 'processed_by');
    }

    public function suratMasuk(): HasMany
    {
        return $this->hasMany(SuratMasuk::class, 'created_by');
    }

    public function penerimaBansos(): HasMany
    {
        return $this->hasMany(PenerimaBansos::class, 'created_by');
    }

    public function kegiatanDesa(): HasMany
    {
        return $this->hasMany(KegiatanDesa::class, 'created_by');
    }

    public function dokumenPpid(): HasMany
    {
        return $this->hasMany(DokumenPpid::class, 'uploaded_by');
    }
}
