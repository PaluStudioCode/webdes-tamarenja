<?php

namespace App\Http\Requests\Kependudukan;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Models\KartuKeluarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePendudukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOperator() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nik')) {
            $raw = trim((string) $this->input('nik'));
            $cleaned = ltrim($raw, "'");
            $this->merge(['nik' => $cleaned]);
        }

        if (! $this->filled('kewarganegaraan')) {
            $this->merge(['kewarganegaraan' => 'WNI']);
        }

        if (! $this->filled('status_kependudukan')) {
            $this->merge(['status_kependudukan' => StatusKependudukan::Aktif->value]);
        }

        if (! $this->filled('status_kesejahteraan')) {
            $kk = $this->filled('kartu_keluarga_id')
                ? KartuKeluarga::query()->find($this->input('kartu_keluarga_id'))
                : null;

            if ($kk !== null) {
                $kkStatus = $kk->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $kk->status_kesejahteraan->value
                    : (string) $kk->status_kesejahteraan;

                $this->merge([
                    'status_kesejahteraan' => $kkStatus !== '' ? $kkStatus : StatusKesejahteraan::NonPrioritas->value,
                ]);
            } else {
                $this->merge(['status_kesejahteraan' => StatusKesejahteraan::NonPrioritas->value]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kartu_keluarga_id' => [
                'required',
                'integer',
                Rule::exists('kartu_keluarga', 'id')->whereNull('deleted_at'),
            ],
            'nik' => [
                'required',
                'string',
                'digits:16',
                'regex:/^[0-9]{16}$/',
                Rule::unique('penduduk', 'nik')->whereNull('deleted_at'),
            ],
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
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
            'status_kesejahteraan' => ['nullable', Rule::enum(StatusKesejahteraan::class)],
            'jenis_bpjs' => ['nullable', 'string', 'max:50'],
            'status_kependudukan' => ['required', Rule::enum(StatusKependudukan::class)],
            'tanggal_mutasi' => ['nullable', 'date', 'before_or_equal:today'],
            'catatan_mutasi' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kartu_keluarga_id.required' => 'Kartu Keluarga wajib dipilih.',
            'kartu_keluarga_id.exists' => 'Kartu Keluarga tidak valid atau sudah dihapus.',
            'nik.required' => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari tepat 16 digit angka.',
            'nik.regex' => 'NIK hanya boleh berisi 16 digit angka.',
            'nik.unique' => 'NIK sudah terdaftar pada data penduduk aktif.',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'tempat_lahir.required' => 'Tempat lahir wajib diisi.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'jenis_kelamin.required' => 'Jenis kelamin wajib dipilih.',
            'jenis_kelamin.in' => 'Jenis kelamin harus L (Laki-laki) atau P (Perempuan).',
            'agama.required' => 'Agama wajib diisi.',
            'status_perkawinan.required' => 'Status perkawinan wajib dipilih.',
            'hubungan_keluarga.required' => 'Hubungan dalam keluarga wajib dipilih.',
            'status_kesejahteraan.required' => 'Status kesejahteraan (DTSEN) wajib dipilih.',
            'status_kependudukan.required' => 'Status kependudukan wajib dipilih.',
        ];
    }
}
