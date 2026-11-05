<?php

namespace App\Http\Requests\Kependudukan;

use App\Enums\StatusKependudukan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MutasiPendudukRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOperator() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'status_kependudukan.required' => 'Status kependudukan baru wajib dipilih.',
            'tanggal_mutasi.date' => 'Format tanggal mutasi tidak valid.',
            'tanggal_mutasi.before_or_equal' => 'Tanggal mutasi tidak boleh melebihi hari ini.',
            'catatan_mutasi.max' => 'Catatan mutasi maksimal 255 karakter.',
        ];
    }
}
