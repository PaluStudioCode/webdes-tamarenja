<?php

namespace App\Http\Requests\Persuratan;

use App\Enums\JenisSurat;
use App\Support\SuratTemplateSchemaValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSuratKeluarLoketRequest extends FormRequest
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
        $jenisSurat = JenisSurat::tryFrom((string) $this->input('jenis_surat'));

        $allowedKode = $jenisSurat !== null
            ? $jenisSurat->allowedKodeKlasifikasi()
            : [];

        return array_merge(
            [
                'penduduk_id' => ['required', 'integer', 'exists:penduduk,id'],
                'jenis_surat' => ['required', Rule::enum(JenisSurat::class)],
                'kode_klasifikasi' => [
                    'nullable',
                    'string',
                    'max:20',
                    ...($allowedKode !== [] ? [Rule::in($allowedKode)] : []),
                ],
                'tanggal_surat' => ['nullable', 'date'],
                'keperluan' => ['required', 'string', 'max:255'],
                'opsi_penandatangan' => ['nullable', Rule::in(['kepala_desa', 'sekdes_an'])],
                'no_wa_pemohon' => ['nullable', 'string', 'max:25'],
                'data_khusus' => ['required', 'array'],
            ],
            SuratTemplateSchemaValidator::rulesFor($jenisSurat)
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(
            [
                'penduduk_id.required' => 'Warga pemohon/subjek surat wajib dipilih.',
                'penduduk_id.exists' => 'Data penduduk yang dipilih tidak ditemukan.',
                'jenis_surat.required' => 'Jenis template surat wajib dipilih.',
                'kode_klasifikasi.in' => 'Kode klasifikasi surat tidak sesuai dengan standar template surat terpilih.',
                'keperluan.required' => 'Keperluan pembuatan surat wajib diisi.',
                'data_khusus.required' => 'Rincian data khusus surat wajib dilengkapi.',
            ],
            SuratTemplateSchemaValidator::messages()
        );
    }
}
