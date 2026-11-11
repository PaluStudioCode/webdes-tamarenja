<?php

namespace App\Services;

use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KependudukanService
{
    /**
     * Simpan atau pulihkan (restore) Kartu Keluarga yang pernah di-soft-delete berdasarkan nomor_kk.
     *
     * @param  array<string, mixed>  $data
     * @return array{model: KartuKeluarga, restored: bool}
     */
    public function storeOrRestoreKartuKeluarga(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $statusKesejahteraan = isset($data['status_kesejahteraan'])
                ? ($data['status_kesejahteraan'] instanceof StatusKesejahteraan
                    ? $data['status_kesejahteraan']->value
                    : (string) $data['status_kesejahteraan'])
                : StatusKesejahteraan::NonPrioritas->value;

            if ($statusKesejahteraan === '') {
                $statusKesejahteraan = StatusKesejahteraan::NonPrioritas->value;
            }

            $existing = KartuKeluarga::withTrashed()
                ->where('nomor_kk', $data['nomor_kk'])
                ->first();

            if ($existing !== null) {
                if (! $existing->trashed()) {
                    throw ValidationException::withMessages([
                        'nomor_kk' => 'Nomor Kartu Keluarga (KK) sudah terdaftar.',
                    ]);
                }

                $existing->restore();
                $existing->update([
                    'dusun' => $data['dusun'],
                    'rt' => $data['rt'],
                    'rw' => $data['rw'],
                    'alamat' => $data['alamat'] ?? null,
                    'status_kesejahteraan' => $statusKesejahteraan,
                ]);

                $existing->penduduk()->withTrashed()->update([
                    'status_kesejahteraan' => $statusKesejahteraan,
                ]);

                if (! empty($data['anggota']) && is_array($data['anggota'])) {
                    foreach ($data['anggota'] as $memberData) {
                        $this->storeOrRestorePenduduk(array_merge($memberData, [
                            'kartu_keluarga_id' => $existing->id,
                            'status_kesejahteraan' => $statusKesejahteraan,
                            'status_kependudukan' => $memberData['status_kependudukan'] ?? StatusKependudukan::Aktif->value,
                        ]));
                    }
                }

                return [
                    'model' => $existing->fresh(['penduduk']),
                    'restored' => true,
                ];
            }

            $kk = KartuKeluarga::create([
                'nomor_kk' => $data['nomor_kk'],
                'dusun' => $data['dusun'],
                'rt' => $data['rt'],
                'rw' => $data['rw'],
                'alamat' => $data['alamat'] ?? null,
                'status_kesejahteraan' => $statusKesejahteraan,
            ]);

            if (! empty($data['anggota']) && is_array($data['anggota'])) {
                foreach ($data['anggota'] as $memberData) {
                    $this->storeOrRestorePenduduk(array_merge($memberData, [
                        'kartu_keluarga_id' => $kk->id,
                        'status_kesejahteraan' => $statusKesejahteraan,
                        'status_kependudukan' => $memberData['status_kependudukan'] ?? StatusKependudukan::Aktif->value,
                    ]));
                }
            }

            return [
                'model' => $kk->fresh(['penduduk']),
                'restored' => false,
            ];
        });
    }

    /**
     * Perbarui Kartu Keluarga dan sinkronkan status_kesejahteraan ke seluruh anggota keluarga di KK tersebut.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateKartuKeluarga(KartuKeluarga $kk, array $data): KartuKeluarga
    {
        return DB::transaction(function () use ($kk, $data) {
            $statusKesejahteraan = isset($data['status_kesejahteraan'])
                ? ($data['status_kesejahteraan'] instanceof StatusKesejahteraan
                    ? $data['status_kesejahteraan']->value
                    : (string) $data['status_kesejahteraan'])
                : ($kk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $kk->status_kesejahteraan->value
                    : (string) $kk->status_kesejahteraan);

            if ($statusKesejahteraan === '') {
                $statusKesejahteraan = StatusKesejahteraan::NonPrioritas->value;
            }

            $kk->update([
                'nomor_kk' => $data['nomor_kk'] ?? $kk->nomor_kk,
                'dusun' => $data['dusun'] ?? $kk->dusun,
                'rt' => $data['rt'] ?? $kk->rt,
                'rw' => $data['rw'] ?? $kk->rw,
                'alamat' => array_key_exists('alamat', $data) ? $data['alamat'] : $kk->alamat,
                'status_kesejahteraan' => $statusKesejahteraan,
            ]);

            // Sinkronkan status_kesejahteraan ke seluruh anggota keluarga pada KK ini (1 KK = 1 Status Kesejahteraan)
            $kk->penduduk()->withTrashed()->update([
                'status_kesejahteraan' => $statusKesejahteraan,
            ]);

            return $kk->fresh();
        });
    }

    /**
     * Simpan atau pulihkan (restore) Penduduk yang pernah di-soft-delete berdasarkan NIK.
     *
     * @param  array<string, mixed>  $data
     * @return array{model: Penduduk, restored: bool, reminder: ?string}
     */
    public function storeOrRestorePenduduk(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $existing = Penduduk::withTrashed()
                ->where('nik', $data['nik'])
                ->first();

            $statusKependudukan = isset($data['status_kependudukan'])
                ? ($data['status_kependudukan'] instanceof StatusKependudukan
                    ? $data['status_kependudukan']->value
                    : (string) $data['status_kependudukan'])
                : StatusKependudukan::Aktif->value;

            $kk = KartuKeluarga::query()->find($data['kartu_keluarga_id']);
            if (isset($data['status_kesejahteraan']) && $data['status_kesejahteraan'] !== '') {
                $statusKesejahteraan = $data['status_kesejahteraan'] instanceof StatusKesejahteraan
                    ? $data['status_kesejahteraan']->value
                    : (string) $data['status_kesejahteraan'];

                // Sinkronkan KK & seluruh anggotanya agar tetap 1 KK = 1 Status Kesejahteraan
                if ($kk !== null) {
                    $kk->update(['status_kesejahteraan' => $statusKesejahteraan]);
                    $kk->penduduk()->withTrashed()->update(['status_kesejahteraan' => $statusKesejahteraan]);
                }
            } elseif ($kk !== null && $kk->status_kesejahteraan !== null) {
                $statusKesejahteraan = $kk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $kk->status_kesejahteraan->value
                    : (string) $kk->status_kesejahteraan;
            } else {
                $statusKesejahteraan = StatusKesejahteraan::NonPrioritas->value;
            }

            $payload = [
                'kartu_keluarga_id' => $data['kartu_keluarga_id'],
                'nik' => $data['nik'],
                'nama_lengkap' => $data['nama_lengkap'],
                'tempat_lahir' => $data['tempat_lahir'],
                'tanggal_lahir' => $data['tanggal_lahir'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'agama' => $data['agama'],
                'pendidikan' => $data['pendidikan'] ?? null,
                'pekerjaan' => $data['pekerjaan'] ?? null,
                'status_perkawinan' => $data['status_perkawinan'],
                'hubungan_keluarga' => $data['hubungan_keluarga'],
                'status_kesejahteraan' => $statusKesejahteraan,
                'status_kependudukan' => $statusKependudukan,
                'tanggal_mutasi' => $statusKependudukan === StatusKependudukan::Aktif->value
                    ? null
                    : ($data['tanggal_mutasi'] ?? now()->toDateString()),
                'catatan_mutasi' => $data['catatan_mutasi'] ?? null,
            ];

            if ($existing !== null) {
                if (! $existing->trashed()) {
                    throw ValidationException::withMessages([
                        'nik' => 'NIK sudah terdaftar pada data penduduk aktif.',
                    ]);
                }

                $existing->restore();
                $existing->update($payload);
                $penduduk = $existing->fresh(['kartuKeluarga']);
                $restored = true;
            } else {
                $penduduk = Penduduk::create($payload)->load('kartuKeluarga');
                $restored = false;
            }

            $reminder = $penduduk->kartuKeluarga
                ? $this->getKepalaKeluargaWarning($penduduk->kartuKeluarga)
                : null;

            return [
                'model' => $penduduk,
                'restored' => $restored,
                'reminder' => $reminder,
            ];
        });
    }

    /**
     * Perbarui biodata penduduk (dan jalankan aturan transisi status bila status_kependudukan ikut berubah).
     *
     * @param  array<string, mixed>  $data
     * @return array{model: Penduduk, reminder: ?string}
     */
    public function updatePenduduk(Penduduk $penduduk, array $data): array
    {
        return DB::transaction(function () use ($penduduk, $data) {
            $oldKkId = $penduduk->kartu_keluarga_id;
            $oldStatus = $penduduk->status_kependudukan instanceof StatusKependudukan
                ? $penduduk->status_kependudukan
                : StatusKependudukan::from((string) $penduduk->status_kependudukan);

            $newStatusValue = isset($data['status_kependudukan'])
                ? ($data['status_kependudukan'] instanceof StatusKependudukan
                    ? $data['status_kependudukan']->value
                    : (string) $data['status_kependudukan'])
                : $oldStatus->value;

            $newStatus = StatusKependudukan::from($newStatusValue);

            if ($oldStatus !== $newStatus) {
                $this->validateAndApplyStatusTransition(
                    $penduduk,
                    $oldStatus,
                    $newStatus,
                    $data['tanggal_mutasi'] ?? null,
                    $data['catatan_mutasi'] ?? null
                );
            }

            $targetKkId = $data['kartu_keluarga_id'] ?? $penduduk->kartu_keluarga_id;
            $targetKk = KartuKeluarga::query()->find($targetKkId);

            if ($targetKk !== null && $targetKk->status_kesejahteraan !== null) {
                $statusKesejahteraan = $targetKk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $targetKk->status_kesejahteraan->value
                    : (string) $targetKk->status_kesejahteraan;
            } else {
                $statusKesejahteraan = isset($data['status_kesejahteraan'])
                    ? ($data['status_kesejahteraan'] instanceof StatusKesejahteraan
                        ? $data['status_kesejahteraan']->value
                        : (string) $data['status_kesejahteraan'])
                    : ($penduduk->status_kesejahteraan instanceof StatusKesejahteraan
                        ? $penduduk->status_kesejahteraan->value
                        : (string) $penduduk->status_kesejahteraan);
            }

            $updatePayload = [
                'kartu_keluarga_id' => $targetKkId,
                'nik' => $data['nik'] ?? $penduduk->nik,
                'nama_lengkap' => $data['nama_lengkap'] ?? $penduduk->nama_lengkap,
                'tempat_lahir' => $data['tempat_lahir'] ?? $penduduk->tempat_lahir,
                'tanggal_lahir' => $data['tanggal_lahir'] ?? $penduduk->tanggal_lahir,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? $penduduk->jenis_kelamin,
                'agama' => $data['agama'] ?? $penduduk->agama,
                'pendidikan' => array_key_exists('pendidikan', $data) ? $data['pendidikan'] : $penduduk->pendidikan,
                'pekerjaan' => array_key_exists('pekerjaan', $data) ? $data['pekerjaan'] : $penduduk->pekerjaan,
                'status_perkawinan' => $data['status_perkawinan'] ?? $penduduk->status_perkawinan,
                'hubungan_keluarga' => $data['hubungan_keluarga'] ?? $penduduk->hubungan_keluarga,
                'status_kesejahteraan' => $statusKesejahteraan,
            ];

            if ($oldStatus === $newStatus) {
                if (array_key_exists('tanggal_mutasi', $data)) {
                    $updatePayload['tanggal_mutasi'] = $data['tanggal_mutasi'];
                }
                if (array_key_exists('catatan_mutasi', $data)) {
                    $updatePayload['catatan_mutasi'] = $data['catatan_mutasi'];
                }
            }

            $penduduk->update($updatePayload);
            $penduduk = $penduduk->fresh(['kartuKeluarga']);

            $reminder = $penduduk->kartuKeluarga
                ? $this->getKepalaKeluargaWarning($penduduk->kartuKeluarga)
                : null;

            if ($reminder === null && $oldKkId !== $penduduk->kartu_keluarga_id) {
                $oldKk = KartuKeluarga::find($oldKkId);
                if ($oldKk !== null) {
                    $reminder = $this->getKepalaKeluargaWarning($oldKk);
                }
            }

            return [
                'model' => $penduduk,
                'reminder' => $reminder,
            ];
        });
    }

    /**
     * Mutasi / Reaktivasi / Koreksi status kependudukan warga dalam transaksi atomik.
     *
     * @return array{model: Penduduk, reminder: ?string}
     */
    public function mutasiPenduduk(
        Penduduk $penduduk,
        StatusKependudukan|string $targetStatus,
        ?string $tanggalMutasi = null,
        ?string $catatanMutasi = null
    ): array {
        return DB::transaction(function () use ($penduduk, $targetStatus, $tanggalMutasi, $catatanMutasi) {
            $oldStatus = $penduduk->status_kependudukan instanceof StatusKependudukan
                ? $penduduk->status_kependudukan
                : StatusKependudukan::from((string) $penduduk->status_kependudukan);

            $newStatus = $targetStatus instanceof StatusKependudukan
                ? $targetStatus
                : StatusKependudukan::from((string) $targetStatus);

            if ($oldStatus === $newStatus) {
                throw ValidationException::withMessages([
                    'status_kependudukan' => 'Status kependudukan baru harus berbeda dari status saat ini.',
                ]);
            }

            $this->validateAndApplyStatusTransition(
                $penduduk,
                $oldStatus,
                $newStatus,
                $tanggalMutasi,
                $catatanMutasi
            );

            $penduduk = $penduduk->fresh(['kartuKeluarga']);
            $reminder = $penduduk->kartuKeluarga
                ? $this->getKepalaKeluargaWarning($penduduk->kartuKeluarga)
                : null;

            return [
                'model' => $penduduk,
                'reminder' => $reminder,
            ];
        });
    }

    /**
     * Validasi prasyarat state machine dan terapkan efek samping mutasi terhadap PenerimaBansos.
     */
    protected function validateAndApplyStatusTransition(
        Penduduk $penduduk,
        StatusKependudukan $oldStatus,
        StatusKependudukan $newStatus,
        ?string $tanggalMutasi,
        ?string $catatanMutasi
    ): void {
        $trimmedCatatan = $catatanMutasi !== null ? trim($catatanMutasi) : '';

        // 1. Koreksi dari Meninggal Dunia -> Aktif (atau Pindah Keluar): tolak bila ada Surat Kematian berstatus Terbit
        if ($oldStatus === StatusKependudukan::MeninggalDunia) {
            $hasTerbitSuratKematian = $penduduk->suratKeluar()
                ->where('jenis_surat', JenisSurat::Kematian)
                ->where('status', StatusSuratKeluar::Terbit)
                ->exists();

            if ($hasTerbitSuratKematian) {
                throw ValidationException::withMessages([
                    'status_kependudukan' => 'Status kependudukan tidak dapat dikembalikan ke Aktif karena warga ini telah memiliki Surat Keterangan Kematian berstatus Terbit.',
                ]);
            }
        }

        // 2. Reaktivasi Pindah Keluar -> Aktif: wajib mengisi catatan_mutasi
        if ($oldStatus === StatusKependudukan::PindahKeluar && $newStatus === StatusKependudukan::Aktif) {
            if ($trimmedCatatan === '') {
                throw ValidationException::withMessages([
                    'catatan_mutasi' => 'Catatan mutasi wajib diisi saat melakukan reaktivasi warga dari status Pindah Keluar ke Aktif.',
                ]);
            }
        }

        // 3. Efek samping terhadap PenerimaBansos berstatus Aktif
        if ($newStatus === StatusKependudukan::PindahKeluar) {
            $penduduk->penerimaBansos()
                ->where('status_kepesertaan', StatusPesertaBansos::Aktif)
                ->update([
                    'status_kepesertaan' => StatusPesertaBansos::Pindah->value,
                ]);
        } elseif ($newStatus === StatusKependudukan::MeninggalDunia) {
            $penduduk->penerimaBansos()
                ->where('status_kepesertaan', StatusPesertaBansos::Aktif)
                ->update([
                    'status_kepesertaan' => StatusPesertaBansos::MeninggalDunia->value,
                ]);
        }

        // 4. Perbarui status_kependudukan, tanggal_mutasi, dan catatan_mutasi pada penduduk
        $penduduk->update([
            'status_kependudukan' => $newStatus->value,
            'tanggal_mutasi' => $tanggalMutasi ?: now()->toDateString(),
            'catatan_mutasi' => $trimmedCatatan !== '' ? $trimmedCatatan : $penduduk->catatan_mutasi,
        ]);
    }

    /**
     * Deteksi apakah KK memiliki Kepala Keluarga non-aktif (Meninggal Dunia / Pindah Keluar)
     * sementara masih terdapat anggota keluarga berstatus Aktif di KK yang sama.
     */
    public function needsKepalaKeluargaUpdate(KartuKeluarga $kk): bool
    {
        return $this->getKepalaKeluargaWarning($kk) !== null;
    }

    /**
     * Kembalikan pesan peringatan jika Kepala Keluarga non-aktif sementara masih ada anggota aktif di KK yang sama.
     */
    public function getKepalaKeluargaWarning(KartuKeluarga $kk): ?string
    {
        $members = $kk->relationLoaded('penduduk')
            ? $kk->penduduk
            : $kk->penduduk()->get();

        if ($members->isEmpty()) {
            return null;
        }

        $hasActiveKepalaKeluarga = $members->contains(function (Penduduk $p) {
            $status = $p->status_kependudukan instanceof StatusKependudukan
                ? $p->status_kependudukan
                : StatusKependudukan::tryFrom((string) $p->status_kependudukan);

            return $p->hubungan_keluarga === 'kepala_keluarga'
                && $status === StatusKependudukan::Aktif;
        });

        if ($hasActiveKepalaKeluarga) {
            return null;
        }

        $nonActiveKepala = $members->first(function (Penduduk $p) {
            $status = $p->status_kependudukan instanceof StatusKependudukan
                ? $p->status_kependudukan
                : StatusKependudukan::tryFrom((string) $p->status_kependudukan);

            return $p->hubungan_keluarga === 'kepala_keluarga'
                && in_array($status, [StatusKependudukan::MeninggalDunia, StatusKependudukan::PindahKeluar], true);
        });

        if ($nonActiveKepala === null) {
            return null;
        }

        $hasOtherActiveMembers = $members->contains(function (Penduduk $p) use ($nonActiveKepala) {
            $status = $p->status_kependudukan instanceof StatusKependudukan
                ? $p->status_kependudukan
                : StatusKependudukan::tryFrom((string) $p->status_kependudukan);

            return $p->id !== $nonActiveKepala->id
                && $status === StatusKependudukan::Aktif;
        });

        if (! $hasOtherActiveMembers) {
            return null;
        }

        $statusEnum = $nonActiveKepala->status_kependudukan instanceof StatusKependudukan
            ? $nonActiveKepala->status_kependudukan
            : StatusKependudukan::from((string) $nonActiveKepala->status_kependudukan);

        return sprintf(
            'Pengingat: Kepala Keluarga (%s) pada KK %s kini berstatus %s, sementara masih ada anggota keluarga aktif. Silakan perbarui Kepala Keluarga pada KK tersebut.',
            $nonActiveKepala->nama_lengkap,
            $kk->nomor_kk,
            $statusEnum->label()
        );
    }
}
