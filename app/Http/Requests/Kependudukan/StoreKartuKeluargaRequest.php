<?php

namespace App\Http\Requests\Kependudukan;

use App\Enums\StatusKesejahteraan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKartuKeluargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOperator() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nomor_kk')) {
            $raw = trim((string) $this->input('nomor_kk'));
            $cleaned = ltrim($raw, "'");
            $this->merge(['nomor_kk' => $cleaned]);
        }

        if (! $this->filled('status_kesejahteraan')) {
            $this->merge(['status_kesejahteraan' => StatusKesejahteraan::NonPrioritas->value]);
        }

        if (is_array($this->input('anggota'))) {
            $cleanedAnggota = array_map(function ($member) {
                if (! is_array($member)) {
                    return $member;
                }
                if (isset($member['nik'])) {
                    $member['nik'] = ltrim(trim((string) $member['nik']), "'");
                }

                return $member;
            }, $this->input('anggota'));

            $this->merge(['anggota' => $cleanedAnggota]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nomor_kk' => [
                'required',
                'string',
                'digits:16',
                'regex:/^[0-9]{16}$/',
                Rule::unique('kartu_keluarga', 'nomor_kk')->whereNull('deleted_at'),
            ],
            'dusun' => ['required', 'string', 'max:100'],
            'rt' => ['required', 'string', 'max:5'],
            'rw' => ['required', 'string', 'max:5'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'status_kesejahteraan' => ['required', Rule::enum(StatusKesejahteraan::class)],
            'anggota' => ['nullable', 'array'],
            'anggota.*.nik' => [
                'required',
                'string',
                'digits:16',
                'regex:/^[0-9]{16}$/',
                'distinct',
                Rule::unique('penduduk', 'nik')->whereNull('deleted_at'),
            ],
            'anggota.*.nama_lengkap' => ['required', 'string', 'max:150'],
            'anggota.*.tempat_lahir' => ['required', 'string', 'max:100'],
            'anggota.*.tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'anggota.*.jenis_kelamin' => ['required', 'string', Rule::in(['L', 'P'])],
            'anggota.*.agama' => ['required', 'string', 'max:30'],
            'anggota.*.pendidikan' => ['nullable', 'string', 'max:50'],
            'anggota.*.pekerjaan' => ['nullable', 'string', 'max:100'],
            'anggota.*.status_perkawinan' => [
                'required',
                'string',
                Rule::in(['belum_kawin', 'kawin', 'cerai_hidup', 'cerai_mati']),
            ],
            'anggota.*.hubungan_keluarga' => [
                'required',
                'string',
                Rule::in(['kepala_keluarga', 'istri', 'anak', 'lainnya']),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor_kk.required' => 'Nomor Kartu Keluarga (KK) wajib diisi.',
            'nomor_kk.digits' => 'Nomor Kartu Keluarga (KK) harus terdiri dari tepat 16 digit angka.',
            'nomor_kk.regex' => 'Nomor Kartu Keluarga (KK) hanya boleh berisi 16 digit angka.',
            'nomor_kk.unique' => 'Nomor Kartu Keluarga (KK) sudah terdaftar.',
            'dusun.required' => 'Nama Dusun wajib diisi.',
            'rt.required' => 'Nomor RT wajib diisi.',
            'rw.required' => 'Nomor RW wajib diisi.',
            'status_kesejahteraan.required' => 'Status kesejahteraan (DTSEN) wajib dipilih.',
            'anggota.*.nik.required' => 'NIK anggota keluarga wajib diisi.',
            'anggota.*.nik.digits' => 'NIK anggota keluarga harus terdiri dari tepat 16 digit angka.',
            'anggota.*.nik.regex' => 'NIK anggota keluarga hanya boleh berisi 16 digit angka.',
            'anggota.*.nik.distinct' => 'Terdapat NIK anggota keluarga yang sama di dalam satu form KK.',
            'anggota.*.nik.unique' => 'NIK anggota keluarga sudah terdaftar pada warga aktif lain.',
            'anggota.*.nama_lengkap.required' => 'Nama lengkap anggota keluarga wajib diisi.',
            'anggota.*.tempat_lahir.required' => 'Tempat lahir anggota keluarga wajib diisi.',
            'anggota.*.tanggal_lahir.required' => 'Tanggal lahir anggota keluarga wajib diisi.',
            'anggota.*.tanggal_lahir.before_or_equal' => 'Tanggal lahir anggota keluarga tidak boleh di masa depan.',
            'anggota.*.jenis_kelamin.required' => 'Jenis kelamin anggota keluarga wajib dipilih.',
            'anggota.*.agama.required' => 'Agama anggota keluarga wajib diisi.',
            'anggota.*.status_perkawinan.required' => 'Status perkawinan anggota keluarga wajib dipilih.',
            'anggota.*.hubungan_keluarga.required' => 'Hubungan dalam keluarga wajib dipilih.',
        ];
    }
}
