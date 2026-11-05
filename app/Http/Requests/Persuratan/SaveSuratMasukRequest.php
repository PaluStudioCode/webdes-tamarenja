<?php

namespace App\Http\Requests\Persuratan;

use Illuminate\Foundation\Http\FormRequest;

class SaveSuratMasukRequest extends FormRequest
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
            'nomor_surat_masuk' => ['required', 'string', 'max:100'],
            'tanggal_surat' => ['nullable', 'date'],
            'tanggal_terima' => ['required', 'date'],
            'pengirim' => ['required', 'string', 'max:150'],
            'perihal' => ['required', 'string', 'max:255'],
            'tujuan_disposisi' => ['nullable', 'string', 'max:150'],
            'catatan_disposisi' => ['nullable', 'string', 'max:2000'],
            'berkas_scan' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'mimetypes:application/pdf,image/jpeg,image/png',
                'max:5120',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nomor_surat_masuk.required' => 'Nomor surat masuk dari instansi pengirim wajib diisi.',
            'tanggal_terima.required' => 'Tanggal surat diterima di kantor desa wajib diisi.',
            'pengirim.required' => 'Nama instansi/pihak pengirim wajib diisi.',
            'perihal.required' => 'Perihal / ringkasan isi surat masuk wajib diisi.',
            'berkas_scan.mimes' => 'Format berkas scan surat masuk harus berupa PDF, JPG, atau PNG.',
            'berkas_scan.mimetypes' => 'Tipe berkas scan surat masuk harus berupa PDF, JPG, atau PNG.',
            'berkas_scan.max' => 'Ukuran berkas scan surat masuk maksimal 5 MB.',
        ];
    }
}
