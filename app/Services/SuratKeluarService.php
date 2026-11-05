<?php

namespace App\Services;

use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\SuratKeluar;
use App\Models\User;
use App\Support\SuratTemplateSchemaValidator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SuratKeluarService
{
    public function __construct(
        protected SuratNumberGenerator $suratNumberGenerator
    ) {}

    /**
     * Validasi kelayakan subjek penduduk untuk pembuatan/penerbitan surat keluar.
     */
    public function validateSubjectPenduduk(
        Penduduk $penduduk,
        JenisSurat $jenisSurat,
        string $sumberPengajuan = 'loket',
        ?int $ignoreSuratId = null
    ): void {
        $statusWarga = $penduduk->status_kependudukan instanceof StatusKependudukan
            ? $penduduk->status_kependudukan
            : StatusKependudukan::from((string) $penduduk->status_kependudukan);

        if ($jenisSurat === JenisSurat::Kematian) {
            // Cek apakah warga tersebut sudah pernah memiliki Surat Kematian berstatus Terbit
            $hasExistingKematianTerbit = SuratKeluar::query()
                ->where('penduduk_id', $penduduk->id)
                ->where('jenis_surat', JenisSurat::Kematian)
                ->where('status', StatusSuratKeluar::Terbit)
                ->when($ignoreSuratId !== null, fn ($q) => $q->where('id', '!=', $ignoreSuratId))
                ->exists();

            if ($hasExistingKematianTerbit) {
                throw ValidationException::withMessages([
                    'penduduk_id' => 'Warga ini telah memiliki Surat Keterangan Kematian berstatus Terbit pada Buku Agenda Desa.',
                ]);
            }

            if ($sumberPengajuan === 'online' && $statusWarga !== StatusKependudukan::Aktif) {
                throw ValidationException::withMessages([
                    'penduduk_id' => 'Data kependudukan tidak sesuai atau tidak aktif, silakan periksa kembali atau hubungi loket Kantor Desa.',
                ]);
            }

            if ($statusWarga === StatusKependudukan::PindahKeluar) {
                throw ValidationException::withMessages([
                    'penduduk_id' => 'Warga berstatus Pindah Keluar tidak dapat diterbitkan Surat Keterangan Kematian.',
                ]);
            }

            return;
        }

        // Untuk 9 jenis surat lainnya, subjek (atau Ibu/Ayah pemohon pada Surat Kelahiran) wajib berstatus Aktif
        if ($statusWarga !== StatusKependudukan::Aktif) {
            throw ValidationException::withMessages([
                'penduduk_id' => 'Pembuatan surat layanan baru hanya diizinkan bagi penduduk berstatus Aktif.',
            ]);
        }
    }

    /**
     * Terbitkan surat keluar baru secara langsung dari Loket Internal dalam 1 transaksi DB.
     *
     * @param  array<string, mixed>  $data
     */
    public function terbitkanLoket(array $data, ?int $actorId = null): SuratKeluar
    {
        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');
        $jenisSurat = $data['jenis_surat'] instanceof JenisSurat
            ? $data['jenis_surat']
            : JenisSurat::from((string) $data['jenis_surat']);

        return DB::transaction(function () use ($data, $jenisSurat, $userId) {
            $penduduk = Penduduk::query()->lockForUpdate()->findOrFail((int) $data['penduduk_id']);

            $this->validateSubjectPenduduk($penduduk, $jenisSurat, 'loket');

            $kodeKlasifikasi = SuratTemplateSchemaValidator::resolveKodeKlasifikasi(
                $jenisSurat,
                $data['kode_klasifikasi'] ?? null
            );

            $opsiPenandatangan = in_array($data['opsi_penandatangan'] ?? '', ['kepala_desa', 'sekdes_an'], true)
                ? (string) $data['opsi_penandatangan']
                : 'kepala_desa';

            $issuance = $this->suratNumberGenerator->generateIssuanceAttributes(
                $kodeKlasifikasi,
                $opsiPenandatangan,
                $data['tanggal_surat'] ?? null
            );

            $noWa = ! empty($data['no_wa_pemohon'])
                ? PhoneNormalizerService::normalize((string) $data['no_wa_pemohon'])
                : null;

            $surat = SuratKeluar::create([
                'qr_token' => $issuance['qr_token'],
                'penduduk_id' => $penduduk->id,
                'sumber_pengajuan' => 'loket',
                'jenis_surat' => $jenisSurat->value,
                'kode_klasifikasi' => $kodeKlasifikasi,
                'tahun_surat' => $issuance['tahun_surat'],
                'nomor_urut' => $issuance['nomor_urut'],
                'nomor_surat' => $issuance['nomor_surat'],
                'tanggal_surat' => $issuance['tanggal_surat'],
                'keperluan' => $data['keperluan'],
                'data_khusus' => $data['data_khusus'] ?? [],
                'no_wa_pemohon' => $noWa,
                'opsi_penandatangan' => $issuance['opsi_penandatangan'],
                'nama_penandatangan' => $issuance['nama_penandatangan'],
                'jabatan_penandatangan' => $issuance['jabatan_penandatangan'],
                'nip_penandatangan' => $issuance['nip_penandatangan'],
                'status' => StatusSuratKeluar::Terbit->value,
                'processed_by' => $userId,
            ]);

            if ($jenisSurat === JenisSurat::Kematian) {
                $this->triggerMutasiKematian(
                    $penduduk,
                    $data['data_khusus'] ?? [],
                    $issuance['tanggal_surat'],
                    $issuance['nomor_surat']
                );
            }

            return $surat->fresh(['penduduk.kartuKeluarga']);
        });
    }

    /**
     * Verifikasi permohonan surat online (`menunggu_verifikasi`) dan terbitkan nomor surat resmi + QR UUID.
     *
     * @param  array<string, mixed>  $data
     */
    public function verifikasiDanTerbitkan(SuratKeluar $surat, array $data = [], ?int $actorId = null): SuratKeluar
    {
        $currentStatus = $surat->status instanceof StatusSuratKeluar
            ? $surat->status
            : StatusSuratKeluar::from((string) $surat->status);

        if ($currentStatus !== StatusSuratKeluar::MenungguVerifikasi) {
            throw ValidationException::withMessages([
                'status' => 'Hanya permohonan berstatus Menunggu Verifikasi yang dapat diterbitkan.',
            ]);
        }

        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');
        $jenisSurat = $surat->jenis_surat instanceof JenisSurat
            ? $surat->jenis_surat
            : JenisSurat::from((string) $surat->jenis_surat);

        return DB::transaction(function () use ($surat, $data, $jenisSurat, $userId) {
            $penduduk = Penduduk::query()->lockForUpdate()->findOrFail((int) $surat->penduduk_id);

            $this->validateSubjectPenduduk($penduduk, $jenisSurat, 'loket', $surat->id);

            $kodeKlasifikasi = SuratTemplateSchemaValidator::resolveKodeKlasifikasi(
                $jenisSurat,
                $data['kode_klasifikasi'] ?? $surat->kode_klasifikasi
            );

            $opsiPenandatangan = in_array($data['opsi_penandatangan'] ?? '', ['kepala_desa', 'sekdes_an'], true)
                ? (string) $data['opsi_penandatangan']
                : 'kepala_desa';

            $issuance = $this->suratNumberGenerator->generateIssuanceAttributes(
                $kodeKlasifikasi,
                $opsiPenandatangan,
                $data['tanggal_surat'] ?? null
            );

            $mergedDataKhusus = isset($data['data_khusus']) && is_array($data['data_khusus'])
                ? array_merge($surat->data_khusus ?? [], $data['data_khusus'])
                : ($surat->data_khusus ?? []);

            $surat->update([
                'qr_token' => $issuance['qr_token'],
                'kode_klasifikasi' => $kodeKlasifikasi,
                'tahun_surat' => $issuance['tahun_surat'],
                'nomor_urut' => $issuance['nomor_urut'],
                'nomor_surat' => $issuance['nomor_surat'],
                'tanggal_surat' => $issuance['tanggal_surat'],
                'keperluan' => $data['keperluan'] ?? $surat->keperluan,
                'data_khusus' => $mergedDataKhusus,
                'opsi_penandatangan' => $issuance['opsi_penandatangan'],
                'nama_penandatangan' => $issuance['nama_penandatangan'],
                'jabatan_penandatangan' => $issuance['jabatan_penandatangan'],
                'nip_penandatangan' => $issuance['nip_penandatangan'],
                'status' => StatusSuratKeluar::Terbit->value,
                'processed_by' => $userId,
            ]);

            if ($jenisSurat === JenisSurat::Kematian) {
                $this->triggerMutasiKematian(
                    $penduduk,
                    $mergedDataKhusus,
                    $issuance['tanggal_surat'],
                    $issuance['nomor_surat']
                );
            }

            return $surat->fresh(['penduduk.kartuKeluarga']);
        });
    }

    /**
     * Tolak permohonan surat online (`menunggu_verifikasi` -> `ditolak`).
     */
    public function tolakPengajuan(SuratKeluar $surat, string $alasanPenolakan, ?int $actorId = null): SuratKeluar
    {
        $currentStatus = $surat->status instanceof StatusSuratKeluar
            ? $surat->status
            : StatusSuratKeluar::from((string) $surat->status);

        if ($currentStatus !== StatusSuratKeluar::MenungguVerifikasi) {
            throw ValidationException::withMessages([
                'status' => 'Hanya permohonan berstatus Menunggu Verifikasi yang dapat ditolak.',
            ]);
        }

        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');

        $surat->update([
            'status' => StatusSuratKeluar::Ditolak->value,
            'alasan_penolakan' => trim($alasanPenolakan),
            'processed_by' => $userId,
        ]);

        return $surat->fresh();
    }

    /**
     * Batalkan surat yang sudah `terbit` (`terbit` -> `dibatalkan`) tanpa menghapus baris
     * agar nomor urut Buku Agenda Surat Keluar tidak bolong.
     */
    public function batalkanSuratTerbit(SuratKeluar $surat, string $alasanPembatalan, ?int $actorId = null): SuratKeluar
    {
        $currentStatus = $surat->status instanceof StatusSuratKeluar
            ? $surat->status
            : StatusSuratKeluar::from((string) $surat->status);

        if ($currentStatus !== StatusSuratKeluar::Terbit) {
            throw ValidationException::withMessages([
                'status' => 'Hanya surat keluar yang sudah berstatus Terbit yang dapat dibatalkan.',
            ]);
        }

        $userId = $actorId ?? auth()->id() ?? User::query()->value('id');

        $surat->update([
            'status' => StatusSuratKeluar::Dibatalkan->value,
            'alasan_pembatalan' => trim($alasanPembatalan),
            'processed_by' => $userId,
        ]);

        return $surat->fresh();
    }

    /**
     * Soft-delete hanya diizinkan untuk permohonan berstatus `menunggu_verifikasi` atau `ditolak`.
     * Surat yang sudah `terbit` (atau `dibatalkan`) dilarang di-soft-delete.
     */
    public function hapusAntrean(SuratKeluar $surat): void
    {
        $currentStatus = $surat->status instanceof StatusSuratKeluar
            ? $surat->status
            : StatusSuratKeluar::from((string) $surat->status);

        if (! in_array($currentStatus, [StatusSuratKeluar::MenungguVerifikasi, StatusSuratKeluar::Ditolak], true)) {
            throw ValidationException::withMessages([
                'status' => 'Surat yang sudah terbit pada Buku Agenda Desa dilarang dihapus agar nomor urut tidak bolong. Gunakan fitur Batalkan Surat.',
            ]);
        }

        $surat->delete();
    }

    /**
     * Trigger atomik saat Surat Keterangan Kematian diterbitkan:
     * 1. Ubah status_kependudukan warga menjadi `meninggal_dunia`
     * 2. Ubah seluruh kepesertaan Bansos `aktif` warga tersebut menjadi `meninggal_dunia`
     *
     * @param  array<string, mixed>  $dataKhusus
     */
    protected function triggerMutasiKematian(
        Penduduk $penduduk,
        array $dataKhusus,
        string $tanggalSurat,
        string $nomorSurat
    ): void {
        $tanggalMeninggal = ! empty($dataKhusus['tanggal_meninggal'])
            ? (string) $dataKhusus['tanggal_meninggal']
            : $tanggalSurat;

        $penduduk->update([
            'status_kependudukan' => StatusKependudukan::MeninggalDunia->value,
            'tanggal_mutasi' => $tanggalMeninggal,
            'catatan_mutasi' => "Otomatis dari penerbitan Surat Keterangan Kematian Nomor: {$nomorSurat}",
        ]);

        PenerimaBansos::query()
            ->where('penduduk_id', $penduduk->id)
            ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value)
            ->update([
                'status_kepesertaan' => StatusPesertaBansos::MeninggalDunia->value,
            ]);
    }

    /**
     * Hasilkan QR Code format SVG Base64 Data-URI dari `simple-qrcode`
     * agar render DomPDF tidak memerlukan ekstensi `imagick`.
     */
    public function generateQrSvgDataUri(?string $qrToken): ?string
    {
        if (empty($qrToken)) {
            return null;
        }

        $verificationUrl = url("/layanan-mandiri/verifikasi-surat/{$qrToken}");
        $svg = (string) QrCode::format('svg')
            ->size(92)
            ->margin(0)
            ->errorCorrection('M')
            ->generate($verificationUrl);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
