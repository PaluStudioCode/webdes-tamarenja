<?php

namespace App\Http\Controllers\Panel;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kependudukan\StoreKartuKeluargaRequest;
use App\Http\Requests\Kependudukan\UpdateKartuKeluargaRequest;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use App\Services\KependudukanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class KartuKeluargaController extends Controller
{
    public function __construct(
        protected KependudukanService $kependudukanService
    ) {}

    /**
     * Ambil detail 1 Kartu Keluarga beserta daftar anggota & deteksi pengingat Kepala Keluarga.
     */
    public function show(KartuKeluarga $kartuKeluarga): JsonResponse
    {
        $kartuKeluarga->load([
            'penduduk' => fn ($q) => $q->orderByRaw("
                CASE hubungan_keluarga
                    WHEN 'kepala_keluarga' THEN 1
                    WHEN 'istri' THEN 2
                    WHEN 'anak' THEN 3
                    ELSE 4
                END ASC
            ")->orderBy('tanggal_lahir'),
        ]);

        $warning = $this->kependudukanService->getKepalaKeluargaWarning($kartuKeluarga);
        $kepala = $kartuKeluarga->penduduk->firstWhere('hubungan_keluarga', 'kepala_keluarga');
        $kkKesejahteraan = $kartuKeluarga->status_kesejahteraan instanceof StatusKesejahteraan
            ? $kartuKeluarga->status_kesejahteraan->value
            : (string) ($kartuKeluarga->status_kesejahteraan ?: StatusKesejahteraan::NonPrioritas->value);

        return response()->json([
            'data' => [
                'id' => $kartuKeluarga->id,
                'nomor_kk' => $kartuKeluarga->nomor_kk,
                'dusun' => $kartuKeluarga->dusun,
                'rt' => $kartuKeluarga->rt,
                'rw' => $kartuKeluarga->rw,
                'alamat' => $kartuKeluarga->alamat,
                'status_kesejahteraan' => $kkKesejahteraan,
                'kepala_keluarga' => $kepala ? [
                    'id' => $kepala->id,
                    'nik' => $kepala->nik,
                    'nama_lengkap' => $kepala->nama_lengkap,
                    'status_kependudukan' => $kepala->status_kependudukan instanceof StatusKependudukan
                        ? $kepala->status_kependudukan->value
                        : (string) $kepala->status_kependudukan,
                ] : null,
                'jumlah_anggota' => $kartuKeluarga->penduduk->count(),
                'jumlah_anggota_aktif' => $kartuKeluarga->penduduk->filter(function (Penduduk $p) {
                    $st = $p->status_kependudukan instanceof StatusKependudukan
                        ? $p->status_kependudukan->value
                        : (string) $p->status_kependudukan;

                    return $st === StatusKependudukan::Aktif->value;
                })->count(),
                'perlu_update_kepala_keluarga' => $warning !== null,
                'peringatan_kepala_keluarga' => $warning,
                'anggota' => $kartuKeluarga->penduduk->map(fn (Penduduk $p) => [
                    'id' => $p->id,
                    'kartu_keluarga_id' => $p->kartu_keluarga_id,
                    'nik' => $p->nik,
                    'nama_lengkap' => $p->nama_lengkap,
                    'tempat_lahir' => $p->tempat_lahir,
                    'tanggal_lahir' => $p->tanggal_lahir?->format('Y-m-d'),
                    'jenis_kelamin' => $p->jenis_kelamin,
                    'agama' => $p->agama,
                    'pendidikan' => $p->pendidikan,
                    'pekerjaan' => $p->pekerjaan,
                    'status_perkawinan' => $p->status_perkawinan,
                    'hubungan_keluarga' => $p->hubungan_keluarga,
                    'status_kesejahteraan' => $p->status_kesejahteraan instanceof StatusKesejahteraan
                        ? $p->status_kesejahteraan->value
                        : (string) $p->status_kesejahteraan,
                    'status_kependudukan' => $p->status_kependudukan instanceof StatusKependudukan
                        ? $p->status_kependudukan->value
                        : (string) $p->status_kependudukan,
                    'tanggal_mutasi' => $p->tanggal_mutasi?->format('Y-m-d'),
                    'catatan_mutasi' => $p->catatan_mutasi,
                ])->values(),
            ],
        ]);
    }

    /**
     * Simpan KK baru atau pulihkan KK yang pernah di-soft-delete.
     */
    public function store(StoreKartuKeluargaRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $result = $this->kependudukanService->storeOrRestoreKartuKeluarga($validated);
        $hasMembers = ! empty($validated['anggota']) && is_array($validated['anggota']);

        $message = $result['restored']
            ? 'Data Kartu Keluarga lama berhasil dipulihkan dan diperbarui.'
            : ($hasMembers
                ? 'Kartu Keluarga baru beserta anggota keluarga berhasil ditambahkan.'
                : 'Kartu Keluarga baru berhasil ditambahkan.');

        return back()->with('notification', [
            'type' => 'success',
            'message' => $message,
        ]);
    }

    /**
     * Perbarui data Kartu Keluarga (serta sinkronkan status kesejahteraan ke seluruh anggota KK).
     */
    public function update(UpdateKartuKeluargaRequest $request, KartuKeluarga $kartuKeluarga): RedirectResponse
    {
        $this->kependudukanService->updateKartuKeluarga($kartuKeluarga, $request->validated());

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Data Kartu Keluarga dan status kesejahteraan anggota keluarga berhasil diperbarui.',
        ]);
    }

    /**
     * Soft delete Kartu Keluarga (ditolak apabila masih memiliki anggota keluarga terdaftar).
     */
    public function destroy(KartuKeluarga $kartuKeluarga): RedirectResponse
    {
        if ($kartuKeluarga->penduduk()->exists()) {
            $errorMsg = 'Kartu Keluarga tidak dapat dihapus karena masih memiliki anggota keluarga terdaftar.';

            return back()
                ->withErrors(['kartu_keluarga' => $errorMsg])
                ->with('notification', [
                    'type' => 'error',
                    'message' => $errorMsg,
                ]);
        }

        $kartuKeluarga->delete();

        return back()->with('notification', [
            'type' => 'success',
            'message' => 'Kartu Keluarga berhasil dihapus.',
        ]);
    }
}
