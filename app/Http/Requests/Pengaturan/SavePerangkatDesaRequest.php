<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SavePerangkatDesaRequest extends FormRequest
{
    public const KELOMPOK_OPTIONS = [
        'pimpinan' => 'Pimpinan Pemerintah Desa',
        'sekretariat' => 'Sekretariat Desa (Sekdes & Kaur)',
        'pelaksana_teknis' => 'Pelaksana Teknis (Kasi)',
        'kewilayahan_dusun' => 'Pelaksana Kewilayahan (Kepala Dusun)',
        'bpd' => 'Badan Permusyawaratan Desa (BPD)',
    ];

    public function authorize(): bool
    {
        return Gate::allows('manage-village-settings');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_aktif')) {
            $this->merge([
                'is_aktif' => filter_var($this->input('is_aktif'), FILTER_VALIDATE_BOOLEAN),
            ]);
        } else {
            $this->merge(['is_aktif' => true]);
        }

        if ($this->has('urutan') && $this->input('urutan') !== null && $this->input('urutan') !== '') {
            $this->merge(['urutan' => (int) $this->input('urutan')]);
        } else {
            $this->merge(['urutan' => 1]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_lengkap' => ['required', 'string', 'max:150'],
            'jabatan' => ['required', 'string', 'max:100'],
            'kelompok_sotk' => ['required', 'string', Rule::in(array_keys(self::KELOMPOK_OPTIONS))],
            'nip_niap' => ['nullable', 'string', 'max:50'],
            'urutan' => ['required', 'integer', 'min:0', 'max:999'],
            'is_aktif' => ['required', 'boolean'],
            'foto' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama_lengkap.required' => 'Nama lengkap perangkat desa / anggota BPD wajib diisi.',
            'jabatan.required' => 'Nama jabatan resmi wajib diisi.',
            'kelompok_sotk.required' => 'Kelompok struktur organisasi (SOTK) wajib dipilih.',
            'kelompok_sotk.in' => 'Kelompok struktur organisasi (SOTK) tidak valid.',
            'urutan.required' => 'Nomor urutan tampil wajib diisi.',
            'urutan.integer' => 'Nomor urutan tampil harus berupa angka bulat.',
            'foto.mimes' => 'Foto perangkat desa wajib berformat JPG, JPEG, PNG, atau WEBP.',
            'foto.mimetypes' => 'Foto perangkat desa wajib berupa berkas gambar yang valid (JPG/PNG/WEBP).',
            'foto.max' => 'Ukuran berkas foto perangkat desa maksimal 3 MB.',
        ];
    }
}
