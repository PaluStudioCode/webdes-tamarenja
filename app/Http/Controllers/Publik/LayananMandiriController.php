<?php

namespace App\Http\Controllers\Publik;

use App\Enums\JenisBansos;
use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Publik\CekStatusBansosMandiriRequest;
use App\Http\Requests\Publik\SubmitPengajuanSuratOnlineRequest;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProfilDesa;
use App\Models\SuratKeluar;
use App\Services\DataMaskingService;
use App\Services\PhoneNormalizerService;
use App\Support\SuratTemplateSchemaValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LayananMandiriController extends Controller
{
    /**
     * Halaman Terpadu Layanan Mandiri Warga (/layanan-mandiri) dengan 2 Tab:
     * 1. Tab Pengajuan Surat Online
     * 2. Tab Cek Status Bansos Mandiri
     */
    public function index(Request $request): Response
    {
        $activeTab = in_array($request->query('tab'), ['pengajuan_surat', 'cek_bansos'], true)
            ? (string) $request->query('tab')
            : 'pengajuan_surat';

        return Inertia::render('LayananMandiri/Index', $this->buildPageProps(
            activeTab: $activeTab,
            konfirmasiPengajuan: session('konfirmasi_pengajuan'),
            hasilBansos: session('hasil_bansos'),
        ));
    }

    /**
     * Tab 1: Submit Pengajuan Surat Online (POST /layanan-mandiri/ajukan-surat)
     * Dilindungi middleware throttle:10,1.
     * Resolusi penduduk_id murni di sisi server berdasarkan kecocokan nik + tanggal_lahir berstatus aktif.
     */
    public function ajukanSurat(SubmitPengajuanSuratOnlineRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $penduduk = $this->resolveActivePendudukByNikAndBirthdate(
            (string) $validated['nik'],
            (string) $validated['tanggal_lahir'],
        );

        if ($penduduk === null) {
            throw ValidationException::withMessages([
                'nik' => SubmitPengajuanSuratOnlineRequest::ANTI_ENUMERATION_MESSAGE,
            ]);
        }

        $jenisSurat = JenisSurat::from((string) $validated['jenis_surat']);

        // Pastikan untuk Surat Kematian warga tersebut belum pernah memiliki Surat Kematian berstatus Terbit
        if ($jenisSurat === JenisSurat::Kematian) {
            $hasExistingKematianTerbit = SuratKeluar::query()
                ->where('penduduk_id', $penduduk->id)
                ->where('jenis_surat', JenisSurat::Kematian->value)
                ->where('status', StatusSuratKeluar::Terbit->value)
                ->exists();

            if ($hasExistingKematianTerbit) {
                throw ValidationException::withMessages([
                    'nik' => SubmitPengajuanSuratOnlineRequest::ANTI_ENUMERATION_MESSAGE,
                ]);
            }
        }

        // Cegah duplikasi pengajuan online selama jenis surat yang sama untuk penduduk tersebut masih menunggu_verifikasi
        $hasPendingDuplicate = SuratKeluar::query()
            ->where('penduduk_id', $penduduk->id)
            ->where('jenis_surat', $jenisSurat->value)
            ->where('status', StatusSuratKeluar::MenungguVerifikasi->value)
            ->exists();

        if ($hasPendingDuplicate) {
            throw ValidationException::withMessages([
                'jenis_surat' => 'Pengajuan untuk jenis surat ini sedang dalam antrean verifikasi Operator Desa. Silakan tunggu konfirmasi melalui WhatsApp.',
            ]);
        }

        $normalizedWa = PhoneNormalizerService::normalize((string) $validated['no_wa_pemohon']);
        $kodeKlasifikasi = SuratTemplateSchemaValidator::resolveKodeKlasifikasi($jenisSurat, null);

        $surat = SuratKeluar::query()->create([
            'qr_token' => null,
            'penduduk_id' => $penduduk->id,
            'sumber_pengajuan' => 'online',
            'jenis_surat' => $jenisSurat->value,
            'kode_klasifikasi' => $kodeKlasifikasi,
            'tahun_surat' => null,
            'nomor_urut' => null,
            'nomor_surat' => null,
            'tanggal_surat' => null,
            'keperluan' => trim((string) $validated['keperluan']),
            'data_khusus' => $validated['data_khusus'] ?? [],
            'no_wa_pemohon' => $normalizedWa,
            'opsi_penandatangan' => null,
            'nama_penandatangan' => null,
            'jabatan_penandatangan' => null,
            'nip_penandatangan' => null,
            'status' => StatusSuratKeluar::MenungguVerifikasi->value,
            'processed_by' => null,
        ]);

        $konfirmasiPayload = [
            'id' => $surat->id,
            'jenis_surat' => $jenisSurat->value,
            'jenis_surat_label' => $jenisSurat->label(),
            'nama_pemohon_tersamarkan' => DataMaskingService::maskName($penduduk->nama_lengkap),
            'keperluan' => $surat->keperluan,
            'no_wa_pemohon' => $normalizedWa,
            'status' => StatusSuratKeluar::MenungguVerifikasi->value,
            'status_label' => StatusSuratKeluar::MenungguVerifikasi->label(),
            'submitted_at' => now()->format('Y-m-d H:i'),
        ];

        return redirect()
            ->route('publik.layanan-mandiri.index')
            ->with('konfirmasi_pengajuan', $konfirmasiPayload)
            ->with('notification', [
                'type' => 'success',
                'title' => 'Pengajuan Surat Terkirim',
                'message' => 'Permohonan surat Anda telah masuk ke antrean verifikasi Operator Desa.',
            ]);
    }

    /**
     * Tab 2: Cek Status Kepesertaan Bansos Mandiri (POST /layanan-mandiri/cek-bansos)
     * Dilindungi middleware throttle:10,1.
     * Hanya mengembalikan Nama Tersamarkan (DataMaskingService), Dusun, Jenis Bantuan,
     * Tahun/Tahap, dan Status Kepesertaan terurut dari tahun_anggaran terbaru tanpa NIK & tanpa anggota KK lain.
     */
    public function cekBansos(CekStatusBansosMandiriRequest $request): Response
    {
        $validated = $request->validated();

        $penduduk = $this->resolveActivePendudukByNikAndBirthdate(
            (string) $validated['nik'],
            (string) $validated['tanggal_lahir'],
        );

        if ($penduduk === null) {
            throw ValidationException::withMessages([
                'nik' => CekStatusBansosMandiriRequest::ANTI_ENUMERATION_MESSAGE,
            ]);
        }

        $penduduk->loadMissing('kartuKeluarga');

        $riwayatList = PenerimaBansos::query()
            ->where('penduduk_id', $penduduk->id)
            ->orderByDesc('tahun_anggaran')
            ->orderByDesc('id')
            ->get()
            ->map(function (PenerimaBansos $item): array {
                $jenisVal = $item->jenis_bantuan instanceof JenisBansos
                    ? $item->jenis_bantuan->value
                    : (string) $item->jenis_bantuan;
                $jenisLabel = $item->jenis_bantuan instanceof JenisBansos
                    ? $item->jenis_bantuan->label()
                    : strtoupper($jenisVal);

                $statusVal = $item->status_kepesertaan instanceof StatusPesertaBansos
                    ? $item->status_kepesertaan->value
                    : (string) $item->status_kepesertaan;
                $statusLabel = $item->status_kepesertaan instanceof StatusPesertaBansos
                    ? $item->status_kepesertaan->label()
                    : ucfirst($statusVal);

                return [
                    'id' => $item->id,
                    'jenis_bantuan' => $jenisVal,
                    'jenis_bantuan_label' => $jenisLabel,
                    'bentuk_nominal' => $item->bentuk_nominal,
                    'tahun_anggaran' => (int) $item->tahun_anggaran,
                    'tahap_penyaluran' => $item->tahap_penyaluran,
                    'status_kepesertaan' => $statusVal,
                    'status_label' => $statusLabel,
                ];
            })
            ->values()
            ->all();

        $hasilBansos = [
            'nama_tersamarkan' => DataMaskingService::maskName($penduduk->nama_lengkap),
            'dusun' => $penduduk->kartuKeluarga?->dusun ?? '-',
            'total_riwayat' => count($riwayatList),
            'riwayat' => $riwayatList,
        ];

        return Inertia::render('LayananMandiri/Index', $this->buildPageProps(
            activeTab: 'cek_bansos',
            konfirmasiPengajuan: null,
            hasilBansos: $hasilBansos,
        ));
    }

    /**
     * Halaman Verifikasi Keaslian QR Code Surat (/layanan-mandiri/verifikasi-surat/{qr_token})
     * Berbasis token UUID v4 acak (pencegahan IDOR & anti-enumeration).
     */
    public function verifikasiSurat(string $qr_token): Response
    {
        $profil = ProfilDesa::singleton();
        $cleanToken = trim($qr_token);

        $surat = $cleanToken !== ''
            ? SuratKeluar::query()
                ->with('penduduk')
                ->where('qr_token', $cleanToken)
                ->first()
            : null;

        $statusEnum = $surat
            ? ($surat->status instanceof StatusSuratKeluar
                ? $surat->status
                : StatusSuratKeluar::tryFrom((string) $surat->status))
            : null;

        if ($surat !== null && $statusEnum === StatusSuratKeluar::Terbit) {
            $jenisLabel = $surat->jenis_surat instanceof JenisSurat
                ? $surat->jenis_surat->label()
                : (string) $surat->jenis_surat;

            $pejabatDisplay = trim(
                ($surat->jabatan_penandatangan ? $surat->jabatan_penandatangan . ' — ' : '')
                . ($surat->nama_penandatangan ?? '')
            );

            return Inertia::render('LayananMandiri/VerifikasiSurat', [
                'status_verifikasi' => 'terbit',
                'banner_judul' => 'SURAT ASLI & TERDAFTAR DI BUKU AGENDA DESA',
                'banner_deskripsi' => 'Dokumen surat ini sah, diterbitkan secara resmi oleh Pemerintah Desa, dan tercatat pada Buku Agenda Surat Keluar.',
                'dokumen' => [
                    'nomor_surat' => $surat->nomor_surat,
                    'jenis_surat' => $jenisLabel,
                    'tanggal_terbit' => $surat->tanggal_surat?->format('Y-m-d'),
                    'nama_warga_tersamarkan' => DataMaskingService::maskName($surat->penduduk?->nama_lengkap),
                    'pejabat_penandatangan' => $pejabatDisplay,
                    'nama_penandatangan' => $surat->nama_penandatangan,
                    'jabatan_penandatangan' => $surat->jabatan_penandatangan,
                ],
                'desa' => [
                    'nama_desa' => $profil?->nama_desa ?? 'Tamarenja',
                    'kecamatan' => $profil?->kecamatan ?? 'Sindue Tobata',
                    'kabupaten' => $profil?->kabupaten ?? 'Donggala',
                ],
            ]);
        }

        if ($surat !== null && $statusEnum === StatusSuratKeluar::Dibatalkan) {
            $jenisLabel = $surat->jenis_surat instanceof JenisSurat
                ? $surat->jenis_surat->label()
                : (string) $surat->jenis_surat;

            $pejabatDisplay = trim(
                ($surat->jabatan_penandatangan ? $surat->jabatan_penandatangan . ' — ' : '')
                . ($surat->nama_penandatangan ?? '')
            );

            return Inertia::render('LayananMandiri/VerifikasiSurat', [
                'status_verifikasi' => 'dibatalkan',
                'banner_judul' => 'SURAT DIBATALKAN & TIDAK BERLAKU',
                'banner_deskripsi' => 'Dokumen surat ini pernah diterbitkan namun telah dibatalkan secara resmi oleh Pemerintah Desa dan dinyatakan tidak berlaku lagi.',
                'dokumen' => [
                    'nomor_surat' => $surat->nomor_surat,
                    'jenis_surat' => $jenisLabel,
                    'tanggal_terbit' => $surat->tanggal_surat?->format('Y-m-d'),
                    'nama_warga_tersamarkan' => DataMaskingService::maskName($surat->penduduk?->nama_lengkap),
                    'pejabat_penandatangan' => $pejabatDisplay,
                    'alasan_pembatalan' => $surat->alasan_pembatalan,
                ],
                'desa' => [
                    'nama_desa' => $profil?->nama_desa ?? 'Tamarenja',
                    'kecamatan' => $profil?->kecamatan ?? 'Sindue Tobata',
                    'kabupaten' => $profil?->kabupaten ?? 'Donggala',
                ],
            ]);
        }

        return Inertia::render('LayananMandiri/VerifikasiSurat', [
            'status_verifikasi' => 'tidak_terdaftar',
            'banner_judul' => 'DOKUMEN TIDAK TERDAFTAR PADA BUKU AGENDA DESA',
            'banner_deskripsi' => 'Token QR Code yang dipindai tidak ditemukan atau tidak terdaftar pada Buku Agenda Surat Keluar Pemerintah Desa.',
            'dokumen' => null,
            'desa' => [
                'nama_desa' => $profil?->nama_desa ?? 'Tamarenja',
                'kecamatan' => $profil?->kecamatan ?? 'Sindue Tobata',
                'kabupaten' => $profil?->kabupaten ?? 'Donggala',
            ],
        ]);
    }

    /**
     * Resolusi penduduk_id murni di sisi server berdasarkan kecocokan NIK + Tanggal Lahir + Status Aktif.
     */
    private function resolveActivePendudukByNikAndBirthdate(string $nik, string $tanggalLahir): ?Penduduk
    {
        return Penduduk::query()
            ->where('nik', $nik)
            ->whereDate('tanggal_lahir', $tanggalLahir)
            ->where('status_kependudukan', StatusKependudukan::Aktif->value)
            ->first();
    }

    /**
     * @param  array<string, mixed>|null  $konfirmasiPengajuan
     * @param  array<string, mixed>|null  $hasilBansos
     * @return array<string, mixed>
     */
    private function buildPageProps(
        string $activeTab,
        ?array $konfirmasiPengajuan,
        ?array $hasilBansos,
    ): array {
        $profil = ProfilDesa::singleton();

        $jenisSuratOptions = collect(JenisSurat::cases())
            ->map(fn (JenisSurat $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'default_kode_klasifikasi' => $case->defaultKodeKlasifikasi(),
            ])
            ->values()
            ->all();

        return [
            'active_tab' => $activeTab,
            'jenis_surat_options' => $jenisSuratOptions,
            'profil' => [
                'nama_desa' => $profil?->nama_desa ?? 'Tamarenja',
                'jam_pelayanan' => $profil?->jam_pelayanan ?? 'Senin – Jumat, 08.00 – 15.00 WITA',
                'alamat_kantor' => $profil?->alamat_kantor ?? 'Kantor Desa Tamarenja',
                'no_wa_pelayanan' => $profil?->no_wa_pelayanan,
            ],
            'konfirmasi_pengajuan' => $konfirmasiPengajuan,
            'hasil_bansos' => $hasilBansos,
        ];
    }
}
