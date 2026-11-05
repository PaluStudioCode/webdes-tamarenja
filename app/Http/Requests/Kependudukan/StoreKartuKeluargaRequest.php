<?php

namespace App\Http\Requests\Kependudukan;

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
        ];
    }
}
