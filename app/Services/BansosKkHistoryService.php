<?php

namespace App\Services;

use App\Enums\JenisBansos;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;

class BansosKkHistoryService
{
    /**
     * Kembalikan ringkasan riwayat kepesertaan Bansos seluruh anggota dalam 1 Kartu Keluarga
     * sebagai rujukan verifikasi penerbitan SKTM maupun pengecekan di loket desa.
     *
     * @return array<string, mixed>
     */
    public function getHistoryForPenduduk(Penduduk $penduduk): array
    {
        $penduduk->loadMissing(['kartuKeluarga.penduduk']);
        $kk = $penduduk->kartuKeluarga;

        if (! $kk) {
            return [
                'penduduk_id' => $penduduk->id,
                'nomor_kk' => null,
                'kepala_keluarga' => null,
                'dusun' => null,
                'status_kesejahteraan' => $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $penduduk->status_kesejahteraan->value
                    : (string) $penduduk->status_kesejahteraan,
                'status_kesejahteraan_label' => $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $penduduk->status_kesejahteraan->label()
                    : (string) $penduduk->status_kesejahteraan,
                'jumlah_anggota_kk' => 1,
                'total_bantuan_aktif' => 0,
                'total_riwayat_bantuan' => 0,
                'riwayat_bansos' => [],
            ];
        }

        $anggotaIds = $kk->penduduk->pluck('id')->all();
        $kepalaKeluarga = $kk->penduduk->first(function (Penduduk $p) {
            return in_array(strtolower((string) $p->hubungan_keluarga), ['kepala keluarga', 'kepala_keluarga'], true);
        }) ?? $kk->penduduk->first();

        $bansosList = PenerimaBansos::query()
            ->with('penduduk:id,nik,nama_lengkap,hubungan_keluarga')
            ->whereIn('penduduk_id', $anggotaIds)
            ->orderByDesc('tahun_anggaran')
            ->orderByDesc('id')
            ->get();

        $riwayat = $bansosList->map(function (PenerimaBansos $item) {
            $jenisVal = $item->jenis_bantuan instanceof JenisBansos
                ? $item->jenis_bantuan->value
                : (string) $item->jenis_bantuan;
            $jenisLabel = $item->jenis_bantuan instanceof JenisBansos
                ? $item->jenis_bantuan->label()
                : $jenisVal;

            $statusVal = $item->status_kepesertaan instanceof StatusPesertaBansos
                ? $item->status_kepesertaan->value
                : (string) $item->status_kepesertaan;
            $statusLabel = $item->status_kepesertaan instanceof StatusPesertaBansos
                ? $item->status_kepesertaan->label()
                : $statusVal;

            return [
                'id' => $item->id,
                'penduduk_id' => $item->penduduk_id,
                'nik_penerima' => $item->penduduk?->nik,
                'nama_penerima' => $item->penduduk?->nama_lengkap,
                'hubungan_keluarga' => $item->penduduk?->hubungan_keluarga,
                'jenis_bantuan' => $jenisVal,
                'jenis_bantuan_label' => $jenisLabel,
                'bentuk_nominal' => $item->bentuk_nominal,
                'tahun_anggaran' => (int) $item->tahun_anggaran,
                'tahap_penyaluran' => $item->tahap_penyaluran,
                'status_kepesertaan' => $statusVal,
                'status_kepesertaan_label' => $statusLabel,
            ];
        })->values()->all();

        $totalAktif = collect($riwayat)
            ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value)
            ->count();

        $statusKesejahteraanVal = $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
            ? $penduduk->status_kesejahteraan->value
            : (string) $penduduk->status_kesejahteraan;
        $statusKesejahteraanLabel = $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
            ? $penduduk->status_kesejahteraan->label()
            : $statusKesejahteraanVal;

        return [
            'penduduk_id' => $penduduk->id,
            'nomor_kk' => $kk->nomor_kk,
            'kepala_keluarga' => $kepalaKeluarga?->nama_lengkap,
            'dusun' => $kk->dusun,
            'status_kesejahteraan' => $statusKesejahteraanVal,
            'status_kesejahteraan_label' => $statusKesejahteraanLabel,
            'jumlah_anggota_kk' => $kk->penduduk->count(),
            'total_bantuan_aktif' => $totalAktif,
            'total_riwayat_bantuan' => count($riwayat),
            'riwayat_bansos' => $riwayat,
        ];
    }
}
