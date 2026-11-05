<?php

namespace App\Http\Requests\Pengaturan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateProfilDesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-village-settings');
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('daftar_dusun'))) {
            $cleanedDusun = array_values(array_filter(
                array_map(fn ($item) => trim((string) $item), $this->input('daftar_dusun')),
                fn ($item) => $item !== ''
            ));
            $this->merge(['daftar_dusun' => $cleanedDusun]);
        }

        if (is_array($this->input('misi'))) {
            $cleanedMisi = array_values(array_filter(
                array_map(fn ($item) => trim((string) $item), $this->input('misi')),
                fn ($item) => $item !== ''
            ));
            $this->merge(['misi' => $cleanedMisi]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nama_desa' => ['required', 'string', 'max:100'],
            'kode_desa_surat' => ['required', 'string', 'max:30'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'kabupaten' => ['required', 'string', 'max:100'],
            'provinsi' => ['required', 'string', 'max:100'],
            'kode_wilayah' => ['nullable', 'string', 'max:20'],
            'daftar_dusun' => ['required', 'array', 'min:1'],
            'daftar_dusun.*' => ['required', 'string', 'max:100'],
            'alamat_kantor' => ['required', 'string'],
            'jam_pelayanan' => ['nullable', 'string', 'max:150'],
            'email_desa' => ['nullable', 'email', 'max:150'],
            'no_wa_pelayanan' => ['nullable', 'string', 'max:20', 'regex:/^(08|628|\+628)[0-9]{7,13}$/'],
            'nama_kepala_desa' => ['required', 'string', 'max:150'],
            'nip_kepala_desa' => ['nullable', 'string', 'max:50'],
            'sambutan_kepala_desa' => ['nullable', 'string'],
            'nama_sekdes' => ['required', 'string', 'max:150'],
            'nip_sekdes' => ['nullable', 'string', 'max:50'],
            'visi' => ['nullable', 'string'],
            'misi' => ['nullable', 'array'],
            'misi.*' => ['required', 'string', 'max:500'],
            'sejarah_singkat' => ['nullable', 'string'],
            'potensi_unggulan' => ['nullable', 'string'],
            'tahun_anggaran_aktif' => ['required', 'integer', 'between:2020,2100'],
            'logo_desa' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
            ],
            'foto_kepala_desa' => [
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
            'nama_desa.required' => 'Nama resmi desa wajib diisi.',
            'kode_desa_surat.required' => 'Kode desa untuk penomoran surat wajib diisi.',
            'kecamatan.required' => 'Nama kecamatan wajib diisi.',
            'kabupaten.required' => 'Nama kabupaten wajib diisi.',
            'provinsi.required' => 'Nama provinsi wajib diisi.',
            'daftar_dusun.required' => 'Minimal 1 nama dusun resmi desa wajib diisi.',
            'daftar_dusun.min' => 'Minimal 1 nama dusun resmi desa wajib diisi.',
            'alamat_kantor.required' => 'Alamat lengkap kantor desa wajib diisi.',
            'email_desa.email' => 'Format alamat email resmi desa tidak valid.',
            'no_wa_pelayanan.regex' => 'Nomor WhatsApp pelayanan harus diawali 08 atau 628 dengan panjang digit valid.',
            'nama_kepala_desa.required' => 'Nama lengkap Kepala Desa aktif wajib diisi.',
            'nama_sekdes.required' => 'Nama lengkap Sekretaris Desa aktif wajib diisi.',
            'tahun_anggaran_aktif.required' => 'Tahun anggaran aktif default wajib diisi.',
            'tahun_anggaran_aktif.between' => 'Tahun anggaran aktif harus berada di antara tahun 2020 hingga 2100.',
            'logo_desa.mimes' => 'Logo desa wajib berformat gambar JPG, JPEG, PNG, atau WEBP.',
            'logo_desa.mimetypes' => 'Logo desa wajib berupa berkas gambar yang valid (JPG/PNG/WEBP).',
            'logo_desa.max' => 'Ukuran berkas Logo Desa maksimal 3 MB.',
            'foto_kepala_desa.mimes' => 'Foto Kepala Desa wajib berformat gambar JPG, JPEG, PNG, atau WEBP.',
            'foto_kepala_desa.mimetypes' => 'Foto Kepala Desa wajib berupa berkas gambar yang valid (JPG/PNG/WEBP).',
            'foto_kepala_desa.max' => 'Ukuran berkas Foto Kepala Desa maksimal 3 MB.',
        ];
    }
}
