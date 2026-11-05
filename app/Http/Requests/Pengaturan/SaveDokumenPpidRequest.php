<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveDokumenPpidRequest extends FormRequest
{
    public const KATEGORI_OPTIONS = [
        'perdes_apbdes' => 'Perdes APBDes',
        'lpj' => 'Laporan Pertanggungjawaban (LPJ)',
        'rkpdes' => 'RKPDes (Rencana Kerja Pemerintah Desa)',
        'rpjmdes' => 'RPJMDes (Rencana Pembangunan Jangka Menengah)',
        'sk_regulasi' => 'SK & Regulasi Desa',
        'panduan_layanan' => 'Panduan & Standar Layanan',
    ];

    public function authorize(): bool
    {
        return Gate::allows('manage-village-settings');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_publik')) {
            $this->merge([
                'is_publik' => filter_var($this->input('is_publik'), FILTER_VALIDATE_BOOLEAN),
            ]);
        } else {
            $this->merge(['is_publik' => true]);
        }

        if ($this->input('tahun_dokumen') === '' || $this->input('tahun_dokumen') === null) {
            $this->merge(['tahun_dokumen' => null]);
        } else {
            $this->merge(['tahun_dokumen' => (int) $this->input('tahun_dokumen')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $isUpdating = $this->route('dokumenPpid') !== null;

        return [
            'judul_dokumen' => ['required', 'string', 'max:200'],
            'kategori' => ['required', 'string', Rule::in(array_keys(self::KATEGORI_OPTIONS))],
            'tahun_dokumen' => ['nullable', 'integer', 'between:2000,2100'],
            'is_publik' => ['required', 'boolean'],
            'file_pdf' => [
                $isUpdating ? 'nullable' : 'required',
                'file',
                'mimes:pdf',
                'mimetypes:application/pdf',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'judul_dokumen.required' => 'Judul dokumen resmi PPID wajib diisi.',
            'judul_dokumen.max' => 'Judul dokumen maksimal 200 karakter.',
            'kategori.required' => 'Kategori dokumen PPID wajib dipilih.',
            'kategori.in' => 'Kategori dokumen PPID tidak valid.',
            'tahun_dokumen.between' => 'Tahun dokumen harus berada di antara tahun 2000 hingga 2100.',
            'file_pdf.required' => 'Berkas PDF dokumen resmi wajib diunggah.',
            'file_pdf.mimes' => 'Dokumen PPID wajib berformat berkas PDF (.pdf).',
            'file_pdf.mimetypes' => 'Dokumen PPID wajib berupa berkas PDF resmi (application/pdf).',
            'file_pdf.max' => 'Ukuran berkas PDF dokumen PPID maksimal 10 MB.',
        ];
    }
}
