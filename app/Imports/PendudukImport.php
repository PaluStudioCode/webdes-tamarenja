<?php

namespace App\Imports;

use App\Enums\JenisSurat;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Enums\StatusSuratKeluar;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class PendudukImport implements ToCollection, WithHeadingRow
{
    protected int $successCount = 0;

    protected int $insertedCount = 0;

    protected int $updatedCount = 0;

    protected int $restoredCount = 0;

    /**
     * @var list<array{baris: int, nik: string, nama_lengkap: string, errors: list<string>, pesan: string}>
     */
    protected array $failedRows = [];

    /**
     * @var list<array{
     *     baris: int,
     *     nomor_kk: string,
     *     dusun: string,
     *     nik: string,
     *     nama_lengkap: string,
     *     hubungan_keluarga: string,
     *     status_kesejahteraan: string,
     *     status_kependudukan: string,
     *     aksi: string,
     *     pesan: string|null
     * }>
     */
    protected array $previewRows = [];

    /**
     * @var array<string, string>
     */
    protected array $seenKkWelfare = [];

    public function __construct(
        protected bool $dryRun = false
    ) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $rawRow) {
            $rowNumber = (int) $index + 2; // Baris 1 adalah heading
            $rowArray = $rawRow instanceof Collection ? $rawRow->toArray() : (array) $rawRow;

            if ($this->isRowEmpty($rowArray)) {
                continue;
            }

            $cleaned = $this->cleanAndNormalizeRow($rowArray);

            $validator = Validator::make(
                $cleaned,
                [
                    'nomor_kk' => ['required', 'string', 'digits:16', 'regex:/^[0-9]{16}$/'],
                    'dusun' => ['required', 'string', 'max:100'],
                    'rt' => ['required', 'string', 'max:5'],
                    'rw' => ['required', 'string', 'max:5'],
                    'alamat' => ['nullable', 'string', 'max:500'],
                    'nik' => ['required', 'string', 'digits:16', 'regex:/^[0-9]{16}$/'],
                    'nama_lengkap' => ['required', 'string', 'max:150'],
                    'tempat_lahir' => ['required', 'string', 'max:100'],
                    'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                    'jenis_kelamin' => ['required', 'string', Rule::in(['L', 'P'])],
                    'agama' => ['required', 'string', 'max:30'],
                    'suku' => ['nullable', 'string', 'max:50'],
                    'kewarganegaraan' => ['nullable', 'string', Rule::in(['WNI', 'WNA'])],
                    'pendidikan' => ['nullable', 'string', 'max:50'],
                    'peserta_kip_pip' => ['nullable', 'string', 'max:50'],
                    'kelas_sekolah' => ['nullable', 'string', 'max:50'],
                    'pekerjaan' => ['nullable', 'string', 'max:100'],
                    'status_perkawinan' => [
                        'required',
                        'string',
                        Rule::in(['belum_kawin', 'kawin', 'cerai_hidup', 'cerai_mati']),
                    ],
                    'hubungan_keluarga' => [
                        'required',
                        'string',
                        Rule::in(['kepala_keluarga', 'istri', 'anak', 'lainnya']),
                    ],
                    'status_kesejahteraan' => ['required', Rule::enum(StatusKesejahteraan::class)],
                    'jenis_bpjs' => ['nullable', 'string', 'max:50'],
                    'status_kependudukan' => ['required', Rule::enum(StatusKependudukan::class)],
                    'status_kepemilikan_rumah' => ['nullable', 'string', 'max:50'],
                    'kondisi_rumah' => ['nullable', 'string', 'max:50'],
                    'jenis_listrik' => ['nullable', 'string', 'max:50'],
                    'kamar_mandi' => ['nullable', 'string', 'max:30'],
                    'penghasilan_bulanan' => ['nullable', 'string', 'max:100'],
                    'kepemilikan_usaha' => ['nullable', 'string', 'max:150'],
                    'kepemilikan_hewan_ternak' => ['nullable', 'string', 'max:150'],
                    'kepemilikan_kendaraan_elektronik' => ['nullable', 'string', 'max:200'],
                    'kepemilikan_shml' => ['nullable', 'string', 'max:50'],
                    'kepemilikan_tanah_bidang' => ['nullable', 'string', 'max:50'],
                    'kepemilikan_tanah_sawah' => ['nullable', 'string', 'max:50'],
                    'tanaman' => ['nullable', 'string', 'max:1000'],
                ],
                [
                    'nomor_kk.required' => 'Nomor KK wajib diisi.',
                    'nomor_kk.digits' => 'Nomor KK harus tepat 16 digit angka.',
                    'nomor_kk.regex' => 'Nomor KK hanya boleh berisi 16 digit angka.',
                    'dusun.required' => 'Kolom Dusun wajib diisi.',
                    'rt.required' => 'Kolom RT wajib diisi.',
                    'rw.required' => 'Kolom RW wajib diisi.',
                    'nik.required' => 'NIK wajib diisi.',
                    'nik.digits' => 'NIK harus tepat 16 digit angka.',
                    'nik.regex' => 'NIK hanya boleh berisi 16 digit angka.',
                    'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
                    'tempat_lahir.required' => 'Tempat lahir wajib diisi.',
                    'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
                    'tanggal_lahir.date_format' => 'Format tanggal lahir tidak valid (gunakan YYYY-MM-DD atau tanggal Excel).',
                    'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.',
                    'jenis_kelamin.required' => 'Jenis kelamin wajib diisi (L/P).',
                    'jenis_kelamin.in' => 'Jenis kelamin harus L atau P.',
                    'agama.required' => 'Agama wajib diisi.',
                    'status_perkawinan.in' => 'Status perkawinan tidak valid.',
                    'hubungan_keluarga.in' => 'Hubungan keluarga tidak valid.',
                    'status_kesejahteraan.Illuminate\Validation\Rules\Enum' => 'Status kesejahteraan (DTSEN) tidak valid.',
                    'status_kependudukan.Illuminate\Validation\Rules\Enum' => 'Status kependudukan tidak valid.',
                ]
            );

            if ($validator->fails()) {
                $errors = array_values($validator->errors()->all());
                $pesan = implode(' ', $errors);
                $this->failedRows[] = [
                    'baris' => $rowNumber,
                    'nik' => $cleaned['nik'] !== '' ? $cleaned['nik'] : '-',
                    'nama_lengkap' => $cleaned['nama_lengkap'] !== '' ? $cleaned['nama_lengkap'] : '-',
                    'errors' => $errors,
                    'pesan' => $pesan,
                ];
                $this->previewRows[] = [
                    'baris' => $rowNumber,
                    'nomor_kk' => $cleaned['nomor_kk'] !== '' ? $cleaned['nomor_kk'] : '-',
                    'dusun' => $cleaned['dusun'] !== '' ? $cleaned['dusun'] : '-',
                    'nik' => $cleaned['nik'] !== '' ? $cleaned['nik'] : '-',
                    'nama_lengkap' => $cleaned['nama_lengkap'] !== '' ? $cleaned['nama_lengkap'] : '-',
                    'hubungan_keluarga' => $cleaned['hubungan_keluarga'],
                    'status_kesejahteraan' => $cleaned['status_kesejahteraan'],
                    'status_kependudukan' => $cleaned['status_kependudukan'],
                    'aksi' => 'gagal',
                    'pesan' => $pesan,
                ];

                continue;
            }

            // Cek apakah warga sudah ada di DB dan apakah terkena proteksi Surat Kematian Terbit
            $existingPenduduk = Penduduk::withTrashed()
                ->where('nik', $cleaned['nik'])
                ->first();

            if ($existingPenduduk !== null) {
                $currentStatus = $existingPenduduk->status_kependudukan instanceof StatusKependudukan
                    ? $existingPenduduk->status_kependudukan->value
                    : (string) $existingPenduduk->status_kependudukan;

                if (
                    $currentStatus === StatusKependudukan::MeninggalDunia->value &&
                    $cleaned['status_kependudukan'] !== StatusKependudukan::MeninggalDunia->value
                ) {
                    $hasTerbitSuratKematian = $existingPenduduk->suratKeluar()
                        ->where('jenis_surat', JenisSurat::Kematian)
                        ->where('status', StatusSuratKeluar::Terbit)
                        ->exists();

                    if ($hasTerbitSuratKematian) {
                        $errMsg = 'Warga telah berstatus Meninggal Dunia dengan Surat Kematian Terbit sehingga tidak dapat diaktifkan kembali.';
                        $this->failedRows[] = [
                            'baris' => $rowNumber,
                            'nik' => $cleaned['nik'],
                            'nama_lengkap' => $cleaned['nama_lengkap'],
                            'errors' => [$errMsg],
                            'pesan' => $errMsg,
                        ];
                        $this->previewRows[] = [
                            'baris' => $rowNumber,
                            'nomor_kk' => $cleaned['nomor_kk'],
                            'dusun' => $cleaned['dusun'],
                            'nik' => $cleaned['nik'],
                            'nama_lengkap' => $cleaned['nama_lengkap'],
                            'hubungan_keluarga' => $cleaned['hubungan_keluarga'],
                            'status_kesejahteraan' => $cleaned['status_kesejahteraan'],
                            'status_kependudukan' => $cleaned['status_kependudukan'],
                            'aksi' => 'gagal',
                            'pesan' => $errMsg,
                        ];

                        continue;
                    }
                }
            }

            // Tentukan status_kesejahteraan KK secara cerdas (baris anggota kosong tidak menimpa Kepala Keluarga)
            $existingKk = KartuKeluarga::withTrashed()
                ->where('nomor_kk', $cleaned['nomor_kk'])
                ->first();

            $existingKkWelfare = $this->seenKkWelfare[$cleaned['nomor_kk']]
                ?? ($existingKk?->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $existingKk->status_kesejahteraan->value
                    : ($existingKk ? (string) $existingKk->status_kesejahteraan : null));

            $shouldUpdateKkWelfare = $existingKkWelfare === null
                || $cleaned['hubungan_keluarga'] === 'kepala_keluarga'
                || $cleaned['has_explicit_kesejahteraan'] === '1';

            $resolvedKkWelfare = $shouldUpdateKkWelfare
                ? $cleaned['status_kesejahteraan']
                : $existingKkWelfare;

            $this->seenKkWelfare[$cleaned['nomor_kk']] = $resolvedKkWelfare;

            $aksi = $existingPenduduk === null
                ? 'ditambahkan'
                : ($existingPenduduk->trashed() ? 'dipulihkan' : 'diperbarui');

            if ($this->dryRun) {
                if ($aksi === 'ditambahkan') {
                    $this->insertedCount++;
                } elseif ($aksi === 'dipulihkan') {
                    $this->restoredCount++;
                } else {
                    $this->updatedCount++;
                }
                $this->successCount++;

                $this->previewRows[] = [
                    'baris' => $rowNumber,
                    'nomor_kk' => $cleaned['nomor_kk'],
                    'dusun' => $cleaned['dusun'],
                    'nik' => $cleaned['nik'],
                    'nama_lengkap' => $cleaned['nama_lengkap'],
                    'hubungan_keluarga' => $cleaned['hubungan_keluarga'],
                    'status_kesejahteraan' => $resolvedKkWelfare,
                    'status_kependudukan' => $cleaned['status_kependudukan'],
                    'aksi' => $aksi,
                    'pesan' => null,
                ];

                continue;
            }

            try {
                DB::transaction(function () use ($cleaned, $resolvedKkWelfare, $rowNumber, $aksi) {
                    // 1. Upsert with trashed untuk Kartu Keluarga
                    $kk = KartuKeluarga::withTrashed()
                        ->where('nomor_kk', $cleaned['nomor_kk'])
                        ->first();

                    if ($kk !== null) {
                        if ($kk->trashed()) {
                            $kk->restore();
                        }
                        $kk->update([
                            'dusun' => $cleaned['dusun'],
                            'rt' => $cleaned['rt'],
                            'rw' => $cleaned['rw'],
                            'alamat' => $cleaned['alamat'] !== '' ? $cleaned['alamat'] : $kk->alamat,
                            'status_kesejahteraan' => $resolvedKkWelfare,
                            'status_kepemilikan_rumah' => $cleaned['status_kepemilikan_rumah'] !== '' ? $cleaned['status_kepemilikan_rumah'] : $kk->status_kepemilikan_rumah,
                            'kondisi_rumah' => $cleaned['kondisi_rumah'] !== '' ? $cleaned['kondisi_rumah'] : $kk->kondisi_rumah,
                            'jenis_listrik' => $cleaned['jenis_listrik'] !== '' ? $cleaned['jenis_listrik'] : $kk->jenis_listrik,
                            'kamar_mandi' => $cleaned['kamar_mandi'] !== '' ? $cleaned['kamar_mandi'] : $kk->kamar_mandi,
                            'penghasilan_bulanan' => $cleaned['penghasilan_bulanan'] !== '' ? $cleaned['penghasilan_bulanan'] : $kk->penghasilan_bulanan,
                            'kepemilikan_usaha' => $cleaned['kepemilikan_usaha'] !== '' ? $cleaned['kepemilikan_usaha'] : $kk->kepemilikan_usaha,
                            'kepemilikan_hewan_ternak' => $cleaned['kepemilikan_hewan_ternak'] !== '' ? $cleaned['kepemilikan_hewan_ternak'] : $kk->kepemilikan_hewan_ternak,
                            'kepemilikan_kendaraan_elektronik' => $cleaned['kepemilikan_kendaraan_elektronik'] !== '' ? $cleaned['kepemilikan_kendaraan_elektronik'] : $kk->kepemilikan_kendaraan_elektronik,
                            'kepemilikan_shml' => $cleaned['kepemilikan_shml'] !== '' ? $cleaned['kepemilikan_shml'] : $kk->kepemilikan_shml,
                            'kepemilikan_tanah_bidang' => $cleaned['kepemilikan_tanah_bidang'] !== '' ? $cleaned['kepemilikan_tanah_bidang'] : $kk->kepemilikan_tanah_bidang,
                            'kepemilikan_tanah_sawah' => $cleaned['kepemilikan_tanah_sawah'] !== '' ? $cleaned['kepemilikan_tanah_sawah'] : $kk->kepemilikan_tanah_sawah,
                            'tanaman' => $cleaned['tanaman'] !== '' ? $cleaned['tanaman'] : $kk->tanaman,
                        ]);
                    } else {
                        $kk = KartuKeluarga::create([
                            'nomor_kk' => $cleaned['nomor_kk'],
                            'dusun' => $cleaned['dusun'],
                            'rt' => $cleaned['rt'],
                            'rw' => $cleaned['rw'],
                            'alamat' => $cleaned['alamat'] !== '' ? $cleaned['alamat'] : null,
                            'status_kesejahteraan' => $resolvedKkWelfare,
                            'status_kepemilikan_rumah' => $cleaned['status_kepemilikan_rumah'] !== '' ? $cleaned['status_kepemilikan_rumah'] : null,
                            'kondisi_rumah' => $cleaned['kondisi_rumah'] !== '' ? $cleaned['kondisi_rumah'] : null,
                            'jenis_listrik' => $cleaned['jenis_listrik'] !== '' ? $cleaned['jenis_listrik'] : null,
                            'kamar_mandi' => $cleaned['kamar_mandi'] !== '' ? $cleaned['kamar_mandi'] : null,
                            'penghasilan_bulanan' => $cleaned['penghasilan_bulanan'] !== '' ? $cleaned['penghasilan_bulanan'] : null,
                            'kepemilikan_usaha' => $cleaned['kepemilikan_usaha'] !== '' ? $cleaned['kepemilikan_usaha'] : null,
                            'kepemilikan_hewan_ternak' => $cleaned['kepemilikan_hewan_ternak'] !== '' ? $cleaned['kepemilikan_hewan_ternak'] : null,
                            'kepemilikan_kendaraan_elektronik' => $cleaned['kepemilikan_kendaraan_elektronik'] !== '' ? $cleaned['kepemilikan_kendaraan_elektronik'] : null,
                            'kepemilikan_shml' => $cleaned['kepemilikan_shml'] !== '' ? $cleaned['kepemilikan_shml'] : null,
                            'kepemilikan_tanah_bidang' => $cleaned['kepemilikan_tanah_bidang'] !== '' ? $cleaned['kepemilikan_tanah_bidang'] : null,
                            'kepemilikan_tanah_sawah' => $cleaned['kepemilikan_tanah_sawah'] !== '' ? $cleaned['kepemilikan_tanah_sawah'] : null,
                            'tanaman' => $cleaned['tanaman'] !== '' ? $cleaned['tanaman'] : null,
                        ]);
                    }

                    $kkStatusKesejahteraan = $kk->status_kesejahteraan instanceof StatusKesejahteraan
                        ? $kk->status_kesejahteraan->value
                        : (string) $kk->status_kesejahteraan;

                    // 2. Upsert with trashed untuk Penduduk
                    $penduduk = Penduduk::withTrashed()
                        ->where('nik', $cleaned['nik'])
                        ->first();

                    $payload = [
                        'kartu_keluarga_id' => $kk->id,
                        'nik' => $cleaned['nik'],
                        'nama_lengkap' => $cleaned['nama_lengkap'],
                        'tempat_lahir' => $cleaned['tempat_lahir'],
                        'tanggal_lahir' => $cleaned['tanggal_lahir'],
                        'jenis_kelamin' => $cleaned['jenis_kelamin'],
                        'agama' => $cleaned['agama'],
                        'suku' => $cleaned['suku'] !== '' ? $cleaned['suku'] : ($penduduk?->suku ?? null),
                        'kewarganegaraan' => $cleaned['kewarganegaraan'] !== '' ? $cleaned['kewarganegaraan'] : ($penduduk?->kewarganegaraan ?? 'WNI'),
                        'pendidikan' => $cleaned['pendidikan'] !== '' ? $cleaned['pendidikan'] : null,
                        'peserta_kip_pip' => $cleaned['peserta_kip_pip'] !== '' ? $cleaned['peserta_kip_pip'] : ($penduduk?->peserta_kip_pip ?? null),
                        'kelas_sekolah' => $cleaned['kelas_sekolah'] !== '' ? $cleaned['kelas_sekolah'] : ($penduduk?->kelas_sekolah ?? null),
                        'pekerjaan' => $cleaned['pekerjaan'] !== '' ? $cleaned['pekerjaan'] : null,
                        'status_perkawinan' => $cleaned['status_perkawinan'],
                        'hubungan_keluarga' => $cleaned['hubungan_keluarga'],
                        'status_kesejahteraan' => $kkStatusKesejahteraan,
                        'jenis_bpjs' => $cleaned['jenis_bpjs'] !== '' ? $cleaned['jenis_bpjs'] : ($penduduk?->jenis_bpjs ?? null),
                        'status_kependudukan' => $cleaned['status_kependudukan'],
                        'tanggal_mutasi' => $cleaned['status_kependudukan'] === StatusKependudukan::Aktif->value
                            ? null
                            : ($penduduk?->tanggal_mutasi?->format('Y-m-d') ?? now()->toDateString()),
                    ];

                    if ($penduduk !== null) {
                        $wasTrashed = $penduduk->trashed();
                        if ($wasTrashed) {
                            $penduduk->restore();
                            $this->restoredCount++;
                        } else {
                            $this->updatedCount++;
                        }

                        $penduduk->update($payload);
                    } else {
                        $penduduk = Penduduk::create($payload);
                        $this->insertedCount++;
                    }

                    // Seragamkan seluruh anggota dalam KK yang sama agar konsisten 1 KK = 1 Status Kesejahteraan
                    $kk->penduduk()->withTrashed()->update([
                        'status_kesejahteraan' => $kkStatusKesejahteraan,
                    ]);

                    // Sinkronkan Bansos aktif bila status_kependudukan di-import sebagai non-aktif
                    if ($cleaned['status_kependudukan'] === StatusKependudukan::PindahKeluar->value) {
                        $penduduk->penerimaBansos()
                            ->where('status_kepesertaan', StatusPesertaBansos::Aktif)
                            ->update(['status_kepesertaan' => StatusPesertaBansos::Pindah->value]);
                    } elseif ($cleaned['status_kependudukan'] === StatusKependudukan::MeninggalDunia->value) {
                        $penduduk->penerimaBansos()
                            ->where('status_kepesertaan', StatusPesertaBansos::Aktif)
                            ->update(['status_kepesertaan' => StatusPesertaBansos::MeninggalDunia->value]);
                    }

                    $this->successCount++;
                    $this->previewRows[] = [
                        'baris' => $rowNumber,
                        'nomor_kk' => $cleaned['nomor_kk'],
                        'dusun' => $cleaned['dusun'],
                        'nik' => $cleaned['nik'],
                        'nama_lengkap' => $cleaned['nama_lengkap'],
                        'hubungan_keluarga' => $cleaned['hubungan_keluarga'],
                        'status_kesejahteraan' => $kkStatusKesejahteraan,
                        'status_kependudukan' => $cleaned['status_kependudukan'],
                        'aksi' => $aksi,
                        'pesan' => null,
                    ];
                });
            } catch (\Throwable $e) {
                $errMsg = 'Gagal menyimpan baris ke database.';
                $this->failedRows[] = [
                    'baris' => $rowNumber,
                    'nik' => $cleaned['nik'] !== '' ? $cleaned['nik'] : '-',
                    'nama_lengkap' => $cleaned['nama_lengkap'] !== '' ? $cleaned['nama_lengkap'] : '-',
                    'errors' => [$errMsg],
                    'pesan' => $errMsg,
                ];
                $this->previewRows[] = [
                    'baris' => $rowNumber,
                    'nomor_kk' => $cleaned['nomor_kk'] !== '' ? $cleaned['nomor_kk'] : '-',
                    'dusun' => $cleaned['dusun'] !== '' ? $cleaned['dusun'] : '-',
                    'nik' => $cleaned['nik'] !== '' ? $cleaned['nik'] : '-',
                    'nama_lengkap' => $cleaned['nama_lengkap'] !== '' ? $cleaned['nama_lengkap'] : '-',
                    'hubungan_keluarga' => $cleaned['hubungan_keluarga'],
                    'status_kesejahteraan' => $cleaned['status_kesejahteraan'],
                    'status_kependudukan' => $cleaned['status_kependudukan'],
                    'aksi' => 'gagal',
                    'pesan' => $errMsg,
                ];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Bersihkan spasi, kutip tunggal pembuka pada NIK/KK, serial date Excel, serta normalisasi enum SIAK/Prodeskel.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    protected function cleanAndNormalizeRow(array $row): array
    {
        $rawKesejahteraanCell = trim((string) ($row['status_kesejahteraan'] ?? $row['kategori_dtsen'] ?? $row['desil'] ?? ''));

        $nomorKk = $this->cleanNumericIdentifier($row['nomor_kk'] ?? $row['no_kk'] ?? '');
        $nik = $this->cleanNumericIdentifier($row['nik'] ?? '');
        $dusun = trim((string) ($row['dusun'] ?? $row['nama_dusun'] ?? ''));
        $rt = $this->cleanRtRw($row['rt'] ?? '001');
        $rw = $this->cleanRtRw($row['rw'] ?? '001');
        $alamat = trim((string) ($row['alamat'] ?? ''));
        $namaLengkap = trim((string) ($row['nama_lengkap'] ?? $row['nama'] ?? ''));
        $tempatLahir = trim((string) ($row['tempat_lahir'] ?? ''));
        $tanggalLahir = $this->parseExcelDate($row['tanggal_lahir'] ?? $row['tgl_lahir'] ?? null);
        $jenisKelamin = $this->normalizeJenisKelamin($row['jenis_kelamin'] ?? $row['jk'] ?? '');
        $agama = trim((string) ($row['agama'] ?? 'Islam'));
        $suku = trim((string) ($row['suku'] ?? ''));
        $rawKewarganegaraan = strtoupper(trim((string) ($row['kewarganegaraan'] ?? 'WNI')));
        $kewarganegaraan = in_array($rawKewarganegaraan, ['WNI', 'WNA'], true) ? $rawKewarganegaraan : 'WNI';
        $pendidikan = trim((string) ($row['pendidikan'] ?? ''));
        $pesertaKipPip = trim((string) ($row['peserta_kip_pip'] ?? $row['peserta_kippip'] ?? ''));
        $kelasSekolah = trim((string) ($row['kelas_sekolah'] ?? $row['anak_sekolah_paud_tk_sd_smp_sma'] ?? ''));
        $pekerjaan = trim((string) ($row['pekerjaan'] ?? ''));
        $statusPerkawinan = $this->normalizeStatusPerkawinan($row['status_perkawinan'] ?? $row['status_kawin'] ?? 'belum_kawin');
        $hubunganKeluarga = $this->normalizeHubunganKeluarga($row['hubungan_keluarga'] ?? $row['shdk'] ?? 'lainnya');
        $statusKesejahteraan = $this->normalizeStatusKesejahteraan($rawKesejahteraanCell !== '' ? $rawKesejahteraanCell : 'non_prioritas');
        $jenisBpjs = trim((string) ($row['jenis_bpjs'] ?? ''));
        $statusKependudukan = $this->normalizeStatusKependudukan($row['status_kependudukan'] ?? $row['status_warga'] ?? $row['status'] ?? 'aktif');

        $statusKepemilikanRumah = trim((string) ($row['status_kepemilikan_rumah'] ?? ''));
        $kondisiRumah = trim((string) ($row['kondisi_rumah'] ?? $row['kondisi_rumah_permanen_semi_permanen_kayu'] ?? ''));
        $jenisListrik = trim((string) ($row['jenis_listrik'] ?? $row['jenis_listrik_yg_di_gunakan'] ?? ''));
        $kamarMandi = trim((string) ($row['kamar_mandi'] ?? ''));
        $penghasilanBulanan = trim((string) ($row['penghasilan_bulanan'] ?? $row['penghasilan_rata2_bln'] ?? $row['penghasilan_rata2bln'] ?? ''));
        $kepemilikanUsaha = trim((string) ($row['kepemilikan_usaha'] ?? ''));
        $kepemilikanHewanTernak = trim((string) ($row['kepemilikan_hewan_ternak'] ?? ''));
        $kepemilikanKendaraanElektronik = trim((string) ($row['kepemilikan_kendaraan_elektronik'] ?? $row['kepemilikan_kendaraanelektronik'] ?? ''));
        $kepemilikanShml = trim((string) ($row['kepemilikan_shml'] ?? $row['kepemilikan_shml_jumlah'] ?? ''));
        $kepemilikanTanahBidang = trim((string) ($row['kepemilikan_tanah_bidang'] ?? $row['kepemilikan_tanah_bidang_jumlah'] ?? ''));
        $kepemilikanTanahSawah = trim((string) ($row['kepemilikan_tanah_sawah'] ?? ''));
        $tanaman = trim((string) ($row['tanaman'] ?? ''));

        return [
            'nomor_kk' => $nomorKk,
            'dusun' => $dusun,
            'rt' => $rt,
            'rw' => $rw,
            'alamat' => $alamat,
            'nik' => $nik,
            'nama_lengkap' => $namaLengkap,
            'tempat_lahir' => $tempatLahir,
            'tanggal_lahir' => $tanggalLahir,
            'jenis_kelamin' => $jenisKelamin,
            'agama' => $agama,
            'suku' => $suku,
            'kewarganegaraan' => $kewarganegaraan,
            'pendidikan' => $pendidikan,
            'peserta_kip_pip' => $pesertaKipPip,
            'kelas_sekolah' => $kelasSekolah,
            'pekerjaan' => $pekerjaan,
            'status_perkawinan' => $statusPerkawinan,
            'hubungan_keluarga' => $hubunganKeluarga,
            'status_kesejahteraan' => $statusKesejahteraan,
            'jenis_bpjs' => $jenisBpjs,
            'has_explicit_kesejahteraan' => $rawKesejahteraanCell !== '' ? '1' : '0',
            'status_kependudukan' => $statusKependudukan,
            'status_kepemilikan_rumah' => $statusKepemilikanRumah,
            'kondisi_rumah' => $kondisiRumah,
            'jenis_listrik' => $jenisListrik,
            'kamar_mandi' => $kamarMandi,
            'penghasilan_bulanan' => $penghasilanBulanan,
            'kepemilikan_usaha' => $kepemilikanUsaha,
            'kepemilikan_hewan_ternak' => $kepemilikanHewanTernak,
            'kepemilikan_kendaraan_elektronik' => $kepemilikanKendaraanElektronik,
            'kepemilikan_shml' => $kepemilikanShml,
            'kepemilikan_tanah_bidang' => $kepemilikanTanahBidang,
            'kepemilikan_tanah_sawah' => $kepemilikanTanahSawah,
            'tanaman' => $tanaman,
        ];
    }

    protected function cleanNumericIdentifier(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = trim((string) $value);
        // Hapus tanda kutip tunggal pembuka ('3201... atau `3201...) dan spasi di sekitarnya
        $str = ltrim($str, "'` \t\n\r\0\x0B");

        return trim($str);
    }

    protected function cleanRtRw(mixed $value): string
    {
        $str = trim(ltrim((string) ($value ?? ''), "'"));
        if ($str === '') {
            return '001';
        }

        if (ctype_digit($str) && strlen($str) < 3) {
            return str_pad($str, 3, '0', STR_PAD_LEFT);
        }

        return substr($str, 0, 5);
    }

    protected function parseExcelDate(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        $raw = trim(ltrim((string) $value, "'"));
        if ($raw === '') {
            return '';
        }

        // Konversi serial number tanggal Excel (contoh: 31179 -> 1985-05-12)
        if (is_numeric($raw) && (float) $raw > 100 && (float) $raw < 100000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d');
            } catch (\Throwable) {
                return $raw;
            }
        }

        // Coba format YYYY-MM-DD, DD/MM/YYYY, atau DD-MM-YYYY
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $raw);
                if ($parsed !== false && $parsed->format($format) === $raw) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Lanjut ke format berikutnya
            }
        }

        try {
            return Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable) {
            return $raw;
        }
    }

    protected function normalizeJenisKelamin(mixed $value): string
    {
        $clean = strtoupper(trim((string) ($value ?? '')));

        return match ($clean) {
            'L', 'LK', 'LAKI-LAKI', 'LAKI LAKI', 'PRIA' => 'L',
            'P', 'PR', 'PEREMPUAN', 'WANITA' => 'P',
            default => $clean,
        };
    }

    protected function normalizeStatusPerkawinan(mixed $value): string
    {
        $clean = strtolower(trim((string) ($value ?? '')));
        $snake = str_replace([' ', '-'], '_', $clean);

        return match ($snake) {
            'belum_kawin', 'belum_menikah', 'tidak_kawin' => 'belum_kawin',
            'kawin', 'menikah', 'kawin_tercatat', 'kawin_belum_tercatat' => 'kawin',
            'cerai_hidup' => 'cerai_hidup',
            'cerai_mati' => 'cerai_mati',
            default => $snake !== '' ? $snake : 'belum_kawin',
        };
    }

    protected function normalizeHubunganKeluarga(mixed $value): string
    {
        $clean = strtolower(trim((string) ($value ?? '')));
        $snake = str_replace([' ', '-'], '_', $clean);

        return match ($snake) {
            'kepala_keluarga', 'kk' => 'kepala_keluarga',
            'istri', 'isteri', 'suami' => 'istri',
            'anak', 'anak_kandung', 'anak_angkat', 'anak_tiri' => 'anak',
            'lainnya', 'famili_lain', 'orang_tua', 'mertua', 'cucu' => 'lainnya',
            default => $snake !== '' ? $snake : 'lainnya',
        };
    }

    protected function normalizeStatusKesejahteraan(mixed $value): string
    {
        $clean = strtolower(trim((string) ($value ?? '')));
        if ($clean === '') {
            return StatusKesejahteraan::NonPrioritas->value;
        }

        return match ($clean) {
            'dtsen_desil_1', 'desil_1', 'desil 1', '1', 'miskin ekstrem' => StatusKesejahteraan::DtsenDesil1->value,
            'dtsen_desil_2', 'desil_2', 'desil 2', '2', 'miskin' => StatusKesejahteraan::DtsenDesil2->value,
            'dtsen_desil_3_4', 'desil_3_4', 'desil 3-4', 'desil 3', 'desil 4', '3', '4', 'rentan miskin' => StatusKesejahteraan::DtsenDesil34->value,
            'non_prioritas', 'non-prioritas', 'non prioritas', 'mampu', 'desil 5-10' => StatusKesejahteraan::NonPrioritas->value,
            default => $clean,
        };
    }

    protected function normalizeStatusKependudukan(mixed $value): string
    {
        $clean = strtolower(trim((string) ($value ?? '')));
        if ($clean === '') {
            return StatusKependudukan::Aktif->value;
        }

        $snake = str_replace([' ', '-'], '_', $clean);

        return match ($snake) {
            'aktif' => StatusKependudukan::Aktif->value,
            'meninggal_dunia', 'meninggal', 'wafat' => StatusKependudukan::MeninggalDunia->value,
            'pindah_keluar', 'pindah' => StatusKependudukan::PindahKeluar->value,
            default => $snake,
        };
    }

    /**
     * @return array{
     *     berhasil: int,
     *     ditambahkan: int,
     *     diperbarui: int,
     *     dipulihkan: int,
     *     gagal: int,
     *     failed_rows: list<array{baris: int, nik: string, nama_lengkap: string, errors: list<string>, pesan: string}>,
     *     preview_rows: list<array{baris: int, nomor_kk: string, dusun: string, nik: string, nama_lengkap: string, hubungan_keluarga: string, status_kesejahteraan: string, status_kependudukan: string, aksi: string, pesan: string|null}>
     * }
     */
    public function getSummary(): array
    {
        return [
            'berhasil' => $this->successCount,
            'ditambahkan' => $this->insertedCount,
            'diperbarui' => $this->updatedCount,
            'dipulihkan' => $this->restoredCount,
            'gagal' => count($this->failedRows),
            'failed_rows' => $this->failedRows,
            'preview_rows' => $this->previewRows,
        ];
    }
}
