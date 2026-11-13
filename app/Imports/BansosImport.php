<?php

namespace App\Imports;

use App\Enums\JenisBansos;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BansosImport implements ToCollection, WithHeadingRow
{
    protected int $successCount = 0;

    protected int $insertedCount = 0;

    protected int $updatedCount = 0;

    protected int $restoredCount = 0;

    /**
     * @var list<array{baris: int, nik: string, nama_lengkap: string, errors: list<string>, pesan: string}>
     */
    protected array $failedRows = [];

    public function __construct(
        protected ?int $defaultTahunAnggaran = null,
        protected ?int $userId = null
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
                    'nik' => ['required', 'string', 'digits:16', 'regex:/^[0-9]{16}$/'],
                    'jenis_bantuan' => ['required', Rule::enum(JenisBansos::class)],
                    'bentuk_nominal' => ['required', 'string', 'max:150'],
                    'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
                    'tahap_penyaluran' => ['required', 'string', 'max:50'],
                    'status_kepesertaan' => ['required', Rule::enum(StatusPesertaBansos::class)],
                    'keterangan' => ['nullable', 'string', 'max:255'],
                ],
                [
                    'nik.required' => 'NIK penerima wajib diisi.',
                    'nik.digits' => 'NIK harus tepat 16 digit angka.',
                    'nik.regex' => 'NIK hanya boleh berisi 16 digit angka.',
                    'jenis_bantuan.required' => 'Jenis bantuan wajib diisi.',
                    'jenis_bantuan.Illuminate\Validation\Rules\Enum' => 'Jenis bantuan tidak valid (gunakan pkh, bpnt, blt_dd, rtlh, pbi_jkn, atau bantuan_daerah).',
                    'bentuk_nominal.required' => 'Bentuk / nominal bantuan wajib diisi.',
                    'tahun_anggaran.required' => 'Tahun anggaran wajib diisi.',
                    'tahun_anggaran.between' => 'Tahun anggaran harus berada di antara 2020 hingga 2100.',
                    'tahap_penyaluran.required' => 'Tahap penyaluran wajib diisi.',
                    'status_kepesertaan.Illuminate\Validation\Rules\Enum' => 'Status kepesertaan tidak valid.',
                ]
            );

            if ($validator->fails()) {
                $errors = array_values($validator->errors()->all());
                $this->failedRows[] = [
                    'baris' => $rowNumber,
                    'nik' => $cleaned['nik'] !== '' ? $cleaned['nik'] : '-',
                    'nama_lengkap' => $cleaned['nama_lengkap'] !== '' ? $cleaned['nama_lengkap'] : '-',
                    'errors' => $errors,
                    'pesan' => implode(' ', $errors),
                ];

                continue;
            }

            /** @var Penduduk|null $penduduk */
            $penduduk = Penduduk::query()
                ->where('nik', $cleaned['nik'])
                ->first();

            if (! $penduduk) {
                $msg = 'NIK tidak ditemukan pada Master Data Penduduk Desa.';
                $this->failedRows[] = [
                    'baris' => $rowNumber,
                    'nik' => $cleaned['nik'],
                    'nama_lengkap' => $cleaned['nama_lengkap'] !== '' ? $cleaned['nama_lengkap'] : '-',
                    'errors' => [$msg],
                    'pesan' => $msg,
                ];

                continue;
            }

            $statusKependudukan = $penduduk->status_kependudukan instanceof StatusKependudukan
                ? $penduduk->status_kependudukan->value
                : (string) $penduduk->status_kependudukan;

            if ($statusKependudukan !== StatusKependudukan::Aktif->value) {
                $msg = 'Penduduk berstatus Meninggal Dunia atau Pindah Keluar tidak dapat diimpor sebagai penerima bantuan.';
                $this->failedRows[] = [
                    'baris' => $rowNumber,
                    'nik' => $cleaned['nik'],
                    'nama_lengkap' => $penduduk->nama_lengkap,
                    'errors' => [$msg],
                    'pesan' => $msg,
                ];

                continue;
            }

            if ($cleaned['status_kepesertaan'] === StatusPesertaBansos::Aktif->value && $penduduk->kartu_keluarga_id) {
                $duplicateKk = PenerimaBansos::query()
                    ->whereHas('penduduk', function ($q) use ($penduduk) {
                        $q->where('kartu_keluarga_id', $penduduk->kartu_keluarga_id)
                            ->where('id', '!=', $penduduk->id);
                    })
                    ->where('jenis_bantuan', $cleaned['jenis_bantuan'])
                    ->where('tahun_anggaran', (int) $cleaned['tahun_anggaran'])
                    ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value)
                    ->with('penduduk')
                    ->first();

                if ($duplicateKk) {
                    $namaAnggota = $duplicateKk->penduduk?->nama_lengkap ?? 'Anggota keluarga lain';
                    $msg = "Bantuan ganda dalam 1 KK dilarang: Anggota keluarga lain atas nama {$namaAnggota} sudah terdaftar aktif menerima bantuan sejenis pada tahun {$cleaned['tahun_anggaran']}.";
                    $this->failedRows[] = [
                        'baris' => $rowNumber,
                        'nik' => $cleaned['nik'],
                        'nama_lengkap' => $penduduk->nama_lengkap,
                        'errors' => [$msg],
                        'pesan' => $msg,
                    ];

                    continue;
                }
            }

            $snapshotEkonomi = $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
                ? $penduduk->status_kesejahteraan->value
                : (string) ($penduduk->status_kesejahteraan ?: StatusKesejahteraan::NonPrioritas->value);

            try {
                DB::transaction(function () use ($penduduk, $cleaned, $snapshotEkonomi) {
                    $existing = PenerimaBansos::withTrashed()
                        ->where('penduduk_id', $penduduk->id)
                        ->where('jenis_bantuan', $cleaned['jenis_bantuan'])
                        ->where('tahun_anggaran', (int) $cleaned['tahun_anggaran'])
                        ->where('tahap_penyaluran', $cleaned['tahap_penyaluran'])
                        ->lockForUpdate()
                        ->first();

                    $payload = [
                        'penduduk_id' => $penduduk->id,
                        'jenis_bantuan' => $cleaned['jenis_bantuan'],
                        'bentuk_nominal' => $cleaned['bentuk_nominal'],
                        'tahun_anggaran' => (int) $cleaned['tahun_anggaran'],
                        'tahap_penyaluran' => $cleaned['tahap_penyaluran'],
                        'kategori_ekonomi' => $snapshotEkonomi,
                        'status_kepesertaan' => $cleaned['status_kepesertaan'],
                        'keterangan' => $cleaned['keterangan'] !== '' ? $cleaned['keterangan'] : null,
                    ];

                    if ($existing !== null) {
                        if ($existing->trashed()) {
                            $existing->restore();
                            $this->restoredCount++;
                        } else {
                            $this->updatedCount++;
                        }

                        $existing->update($payload);
                    } else {
                        PenerimaBansos::create([
                            ...$payload,
                            'created_by' => $this->userId ?? auth()->id(),
                        ]);
                        $this->insertedCount++;
                    }

                    $this->successCount++;
                });
            } catch (\Throwable) {
                $this->failedRows[] = [
                    'baris' => $rowNumber,
                    'nik' => $cleaned['nik'],
                    'nama_lengkap' => $penduduk->nama_lengkap,
                    'errors' => ['Gagal menyimpan baris Bansos ke database.'],
                    'pesan' => 'Gagal menyimpan baris Bansos ke database.',
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
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function cleanAndNormalizeRow(array $row): array
    {
        $nik = $this->cleanNumericIdentifier($row['nik'] ?? '');
        $namaLengkap = trim((string) ($row['nama_lengkap'] ?? $row['nama'] ?? ''));
        $jenisBantuan = $this->normalizeJenisBantuan($row['jenis_bantuan'] ?? $row['program'] ?? '');
        $bentukNominal = trim((string) ($row['bentuk_nominal'] ?? $row['nominal'] ?? ''));

        $rawTahun = trim((string) ($row['tahun_anggaran'] ?? $row['tahun'] ?? ''));
        $tahunAnggaran = $rawTahun !== '' && is_numeric($rawTahun)
            ? (int) $rawTahun
            : ($this->defaultTahunAnggaran ?? (int) now()->year);

        $tahapPenyaluran = trim((string) ($row['tahap_penyaluran'] ?? $row['tahap'] ?? ''));
        $statusKepesertaan = $this->normalizeStatusKepesertaan($row['status_kepesertaan'] ?? $row['status'] ?? 'aktif');
        $keterangan = trim((string) ($row['keterangan'] ?? ''));

        return [
            'nik' => $nik,
            'nama_lengkap' => $namaLengkap,
            'jenis_bantuan' => $jenisBantuan,
            'bentuk_nominal' => $bentukNominal,
            'tahun_anggaran' => $tahunAnggaran,
            'tahap_penyaluran' => $tahapPenyaluran,
            'status_kepesertaan' => $statusKepesertaan,
            'keterangan' => $keterangan,
        ];
    }

    protected function cleanNumericIdentifier(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = trim((string) $value);
        $str = ltrim($str, "'` \t\n\r\0\x0B");

        return trim($str);
    }

    protected function normalizeJenisBantuan(mixed $value): string
    {
        $clean = strtolower(trim((string) ($value ?? '')));
        $snake = str_replace([' ', '-'], '_', $clean);

        return match ($snake) {
            'pkh', 'program_keluarga_harapan' => JenisBansos::Pkh->value,
            'bpnt', 'sembako', 'bantuan_pangan_non_tunai' => JenisBansos::Bpnt->value,
            'blt_dd', 'blt_dana_desa', 'blt' => JenisBansos::BltDd->value,
            'rtlh', 'bedah_rumah', 'rumah_tidak_layak_huni' => JenisBansos::Rtlh->value,
            'pbi_jkn', 'kis', 'bpjs_pbi' => JenisBansos::PbiJkn->value,
            'bantuan_daerah', 'bansos_daerah' => JenisBansos::BantuanDaerah->value,
            default => $snake,
        };
    }

    protected function normalizeStatusKepesertaan(mixed $value): string
    {
        $clean = strtolower(trim((string) ($value ?? '')));
        if ($clean === '') {
            return StatusPesertaBansos::Aktif->value;
        }

        $snake = str_replace([' ', '-'], '_', $clean);

        return match ($snake) {
            'aktif' => StatusPesertaBansos::Aktif->value,
            'graduasi', 'mandiri', 'lulus' => StatusPesertaBansos::Graduasi->value,
            'pindah', 'pindah_keluar' => StatusPesertaBansos::Pindah->value,
            'meninggal_dunia', 'meninggal', 'wafat' => StatusPesertaBansos::MeninggalDunia->value,
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
     *     failed_rows: list<array{baris: int, nik: string, nama_lengkap: string, errors: list<string>, pesan: string}>
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
        ];
    }
}
