<?php

namespace App\Http\Requests\Kependudukan;

use App\Enums\StatusKesejahteraan;
use App\Models\KartuKeluarga;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKartuKeluargaRequest extends FormRequest
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
            $kkParam = $this->route('kartuKeluarga');
            $existingVal = $kkParam instanceof KartuKeluarga
                ? ($kkParam->status_kesejahteraan instanceof StatusKesejahteraan
                    ? $kkParam->status_kesejahteraan->value
                    : (string) $kkParam->status_kesejahteraan)
                : StatusKesejahteraan::NonPrioritas->value;

            $this->merge([
                'status_kesejahteraan' => $existingVal !== '' ? $existingVal : StatusKesejahteraan::NonPrioritas->value,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $kkParam = $this->route('kartuKeluarga');
        $kkId = $kkParam instanceof KartuKeluarga ? $kkParam->id : $kkParam;

        return [
            'nomor_kk' => [
                'required',
                'string',
                'digits:16',
                'regex:/^[0-9]{16}$/',
                Rule::unique('kartu_keluarga', 'nomor_kk')->ignore($kkId),
            ],
            'dusun' => ['required', 'string', 'max:100'],
            'rt' => ['required', 'string', 'max:5'],
            'rw' => ['required', 'string', 'max:5'],
            'alamat' => ['nullable', 'string', 'max:500'],
            'status_kesejahteraan' => ['required', Rule::enum(StatusKesejahteraan::class)],
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
        ];
    }
}
