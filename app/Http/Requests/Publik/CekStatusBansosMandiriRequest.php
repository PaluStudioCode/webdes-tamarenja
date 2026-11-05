<?php

namespace App\Http\Requests\Publik;

use Illuminate\Foundation\Http\FormRequest;

class CekStatusBansosMandiriRequest extends FormRequest
{
    public const ANTI_ENUMERATION_MESSAGE = 'Data kependudukan tidak sesuai atau tidak aktif, silakan periksa kembali atau hubungi loket Kantor Desa.';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nik' => preg_replace('/\D/', '', (string) $this->input('nik', '')),
        ]);

        // Pencegahan IDOR: hapus paksa input penduduk_id dari browser jika dikirim
        $this->request->remove('penduduk_id');
        $this->offsetUnset('penduduk_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nik' => ['required', 'string', 'digits:16', 'regex:/^[0-9]{16}$/'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nik.required' => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'nik.digits' => 'NIK wajib terdiri dari tepat 16 digit angka sesuai KTP/KK.',
            'nik.regex' => 'NIK hanya boleh berisi 16 digit angka.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi untuk verifikasi identitas.',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
        ];
    }
}
