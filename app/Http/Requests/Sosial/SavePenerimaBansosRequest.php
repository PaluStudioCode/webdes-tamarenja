<?php

namespace App\Http\Requests\Sosial;

use App\Enums\JenisBansos;
use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Services\BansosService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePenerimaBansosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isOperator();
    }

    protected function prepareForValidation(): void
    {
        $rawIds = $this->input('penduduk_ids');
        $singleId = $this->input('penduduk_id');

        if (is_array($rawIds) && count($rawIds) > 0) {
            $normalizedIds = array_values(array_filter(array_map('intval', $rawIds), fn ($id) => $id > 0));
            $this->merge([
                'penduduk_ids' => $normalizedIds,
                'penduduk_id' => $singleId ? (int) $singleId : ($normalizedIds[0] ?? null),
            ]);
        } elseif ($singleId !== null && $singleId !== '') {
            $this->merge([
                'penduduk_ids' => [(int) $singleId],
                'penduduk_id' => (int) $singleId,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'penduduk_id' => ['required', 'integer', 'exists:penduduk,id'],
            'penduduk_ids' => ['required', 'array', 'min:1'],
            'penduduk_ids.*' => ['required', 'integer', 'exists:penduduk,id'],
            'jenis_bantuan' => ['required', Rule::enum(JenisBansos::class)],
            'nama_kelompok' => ['nullable', 'string', 'max:150'],
            'bentuk_nominal' => ['required', 'string', 'max:150'],
            'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
            'tahap_penyaluran' => ['required', 'string', 'max:50'],
            'status_kepesertaan' => ['nullable', Rule::enum(StatusPesertaBansos::class)],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'penduduk_id.required' => 'Warga penerima bantuan wajib dipilih.',
            'penduduk_id.exists' => 'Data penduduk yang dipilih tidak ditemukan.',
            'penduduk_ids.required' => 'Minimal satu warga penerima bantuan wajib dipilih.',
            'penduduk_ids.min' => 'Minimal satu warga penerima bantuan wajib dipilih.',
            'jenis_bantuan.required' => 'Jenis program bantuan wajib dipilih.',
            'bentuk_nominal.required' => 'Bentuk atau nominal bantuan wajib diisi (contoh: Rp 300.000 / Bulan).',
            'tahun_anggaran.required' => 'Tahun anggaran penyaluran wajib diisi.',
            'tahap_penyaluran.required' => 'Tahap penyaluran wajib diisi (contoh: Tahap I).',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('penduduk_id') || $validator->errors()->has('penduduk_ids')) {
                    return;
                }

                $jenisBantuan = (string) $this->input('jenis_bantuan');
                $enumJenis = JenisBansos::tryFrom($jenisBantuan);

                if ($enumJenis && $enumJenis->isKelompok() && trim((string) $this->input('nama_kelompok')) === '') {
                    $validator->errors()->add(
                        'nama_kelompok',
                        'Nama Kelompok Tani / Nama Usaha UMKM wajib diisi untuk kategori bantuan kelompok masyarakat.'
                    );

                    return;
                }

                /** @var list<int> $pendudukIds */
                $pendudukIds = array_map('intval', (array) $this->input('penduduk_ids', []));

                if (count($pendudukIds) !== count(array_unique($pendudukIds))) {
                    $validator->errors()->add(
                        'penduduk_id',
                        'Terdapat NIK warga yang dipilih ganda dalam daftar input yang sama.'
                    );

                    return;
                }

                $requestedStatus = (string) ($this->input('status_kepesertaan') ?: StatusPesertaBansos::Aktif->value);
                $tahunAnggaran = (int) $this->input('tahun_anggaran');
                $tahapPenyaluran = trim((string) $this->input('tahap_penyaluran'));
                $isPerKk = $enumJenis ? $enumJenis->isPerKk() : true;
                $prioritasDesils = BansosService::prioritasDesilValues();

                $currentId = null;
                $routeBansos = $this->route('bansos');
                if ($routeBansos instanceof PenerimaBansos) {
                    $currentId = $routeBansos->id;
                } elseif ($routeBansos) {
                    $currentId = (int) $routeBansos;
                }

                $seenKkInBatch = [];

                foreach ($pendudukIds as $pendudukId) {
                    /** @var Penduduk|null $penduduk */
                    $penduduk = Penduduk::query()->find($pendudukId);

                    if (! $penduduk) {
                        $validator->errors()->add('penduduk_id', 'Data penduduk tidak ditemukan.');

                        return;
                    }

                    $statusKependudukan = $penduduk->status_kependudukan instanceof StatusKependudukan
                        ? $penduduk->status_kependudukan->value
                        : (string) $penduduk->status_kependudukan;

                    if (
                        ($this->isMethod('post') || $requestedStatus === StatusPesertaBansos::Aktif->value) &&
                        $statusKependudukan !== StatusKependudukan::Aktif->value
                    ) {
                        $validator->errors()->add(
                            'penduduk_id',
                            "Warga atas nama {$penduduk->nama_lengkap} berstatus Meninggal Dunia atau Pindah Keluar sehingga tidak dapat didaftarkan sebagai penerima bantuan sosial aktif."
                        );

                        return;
                    }

                    // Validasi wajib Desil 1-5 saat menambah penerima baru
                    $statusKesejahteraan = $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
                        ? $penduduk->status_kesejahteraan->value
                        : (string) $penduduk->status_kesejahteraan;

                    if ($this->isMethod('post') && ! in_array($statusKesejahteraan, $prioritasDesils, true)) {
                        $validator->errors()->add(
                            'penduduk_id',
                            "Warga atas nama {$penduduk->nama_lengkap} (NIK: {$penduduk->nik}) bukan kategori Desil 1–5 sehingga tidak memenuhi syarat penerima bantuan sosial."
                        );

                        return;
                    }

                    // Validasi NIK tidak boleh sudah menerima bantuan yang sama pada tahun & tahap ini
                    $existingSameNikQuery = PenerimaBansos::query()
                        ->where('penduduk_id', $penduduk->id)
                        ->where('jenis_bantuan', $jenisBantuan)
                        ->where('tahun_anggaran', $tahunAnggaran)
                        ->where('tahap_penyaluran', $tahapPenyaluran);

                    if ($currentId) {
                        $existingSameNikQuery->where('id', '!=', $currentId);
                    }

                    if ($existingSameNikQuery->exists()) {
                        $labelProgram = $enumJenis ? $enumJenis->label() : $jenisBantuan;
                        $validator->errors()->add(
                            'penduduk_id',
                            "NIK {$penduduk->nik} ({$penduduk->nama_lengkap}) sudah terdaftar sebagai penerima {$labelProgram} pada {$tahapPenyaluran} TA {$tahunAnggaran}."
                        );

                        return;
                    }

                    // Cek larangan bantuan ganda dalam 1 KK khusus kategori Bantuan Sosial Per KK
                    if ($isPerKk && $requestedStatus === StatusPesertaBansos::Aktif->value && $penduduk->kartu_keluarga_id) {
                        if (isset($seenKkInBatch[$penduduk->kartu_keluarga_id])) {
                            $namaSebelumnya = $seenKkInBatch[$penduduk->kartu_keluarga_id];
                            $validator->errors()->add(
                                'penduduk_id',
                                "Bantuan ganda dalam 1 KK dilarang: {$penduduk->nama_lengkap} berada dalam 1 KK yang sama dengan {$namaSebelumnya} pada daftar input ini."
                            );

                            return;
                        }
                        $seenKkInBatch[$penduduk->kartu_keluarga_id] = $penduduk->nama_lengkap;

                        $duplicateQuery = PenerimaBansos::query()
                            ->whereHas('penduduk', function ($q) use ($penduduk) {
                                $q->where('kartu_keluarga_id', $penduduk->kartu_keluarga_id)
                                    ->where('id', '!=', $penduduk->id);
                            })
                            ->where('jenis_bantuan', $jenisBantuan)
                            ->where('tahun_anggaran', $tahunAnggaran)
                            ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value);

                        if ($currentId) {
                            $duplicateQuery->where('id', '!=', $currentId);
                        }

                        $duplicate = $duplicateQuery->with('penduduk')->first();

                        if ($duplicate) {
                            $namaAnggota = $duplicate->penduduk?->nama_lengkap ?? 'Anggota keluarga lain';
                            $labelProgram = $duplicate->jenis_bantuan instanceof JenisBansos
                                ? $duplicate->jenis_bantuan->label()
                                : (string) $duplicate->jenis_bantuan;

                            $validator->errors()->add(
                                'penduduk_id',
                                "Bantuan ganda dalam 1 KK dilarang: Anggota keluarga lain atas nama {$namaAnggota} sudah terdaftar aktif menerima {$labelProgram} pada tahun anggaran {$tahunAnggaran}."
                            );

                            return;
                        }
                    }
                }
            },
        ];
    }
}
