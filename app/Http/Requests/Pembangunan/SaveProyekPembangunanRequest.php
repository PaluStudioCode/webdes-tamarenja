<?php

namespace App\Http\Requests\Pembangunan;

use App\Enums\SumberDanaProyek;
use App\Models\ProyekPembangunan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProyekPembangunanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOperator() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('realisasi_anggaran') || $this->input('realisasi_anggaran') === '') {
            $this->merge(['realisasi_anggaran' => 0]);
        }

        if (! $this->has('progres_fisik') || $this->input('progres_fisik') === '') {
            $this->merge(['progres_fisik' => 0]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
            'nama_kegiatan' => ['required', 'string', 'max:200'],
            'lokasi_dusun' => ['required', 'string', 'max:150'],
            'pagu_anggaran' => ['required', 'integer', 'min:1'],
            'realisasi_anggaran' => ['required', 'integer', 'min:0', 'lte:pagu_anggaran'],
            'sumber_dana' => ['required', Rule::enum(SumberDanaProyek::class)],
            'volume_fisik' => ['nullable', 'string', 'max:150'],
            'waktu_pelaksanaan' => ['nullable', 'string', 'max:100'],
            'pelaksana_kegiatan' => ['nullable', 'string', 'max:150'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'progres_fisik' => ['required', 'integer', 'between:0,100'],
            'catatan_kendala' => ['nullable', 'string', 'max:2000'],
            'foto_0' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
            ],
            'foto_50' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
            ],
            'foto_100' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:3072',
            ],
        ];
    }

    /**
     * Validasi bertahap 3 titik foto berdasarkan progres_fisik:
     * - 0%       : foto opsional
     * - 1% - 49% : wajib memiliki foto_0
     * - 50% - 99%: wajib memiliki foto_0 & foto_50
     * - 100%     : wajib memiliki foto_0, foto_50, & foto_100
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $progres = (int) $this->input('progres_fisik', 0);
                if ($progres <= 0) {
                    return;
                }

                $routeProyek = $this->route('proyek');
                $existing = $routeProyek instanceof ProyekPembangunan ? $routeProyek : null;

                $hasFoto0 = $this->hasFile('foto_0')
                    || filled($existing?->foto_0_path)
                    || filled($this->input('foto_0_path'));

                $hasFoto50 = $this->hasFile('foto_50')
                    || filled($existing?->foto_50_path)
                    || filled($this->input('foto_50_path'));

                $hasFoto100 = $this->hasFile('foto_100')
                    || filled($existing?->foto_100_path)
                    || filled($this->input('foto_100_path'));

                if ($progres >= 1 && ! $hasFoto0) {
                    $validator->errors()->add(
                        'foto_0',
                        'Foto kondisi awal (0%) wajib diunggah untuk proyek dengan progres fisik 1% ke atas.'
                    );
                }

                if ($progres >= 50 && ! $hasFoto50) {
                    $validator->errors()->add(
                        'foto_50',
                        'Foto pengerjaan berjalan (50%) wajib diunggah untuk proyek dengan progres fisik 50% ke atas.'
                    );
                }

                if ($progres >= 100 && ! $hasFoto100) {
                    $validator->errors()->add(
                        'foto_100',
                        'Foto hasil akhir (100%) wajib diunggah untuk menyelesaikan proyek (100%).'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tahun_anggaran.required' => 'Tahun anggaran proyek wajib diisi.',
            'nama_kegiatan.required' => 'Nama kegiatan proyek fisik wajib diisi.',
            'lokasi_dusun.required' => 'Lokasi dusun wajib diisi.',
            'pagu_anggaran.required' => 'Pagu anggaran wajib diisi.',
            'pagu_anggaran.min' => 'Pagu anggaran harus lebih besar dari Rp 0.',
            'realisasi_anggaran.lte' => 'Realisasi anggaran tidak boleh melebihi Pagu Anggaran.',
            'sumber_dana.required' => 'Sumber dana proyek wajib dipilih.',
            'progres_fisik.between' => 'Progres fisik harus berada di antara 0% sampai 100%.',
            'latitude.between' => 'Koordinat lintang (latitude) harus berada di antara -90 sampai 90.',
            'longitude.between' => 'Koordinat bujur (longitude) harus berada di antara -180 sampai 180.',
            'foto_0.max' => 'Ukuran foto kondisi awal (0%) maksimal 3 MB.',
            'foto_50.max' => 'Ukuran foto pengerjaan (50%) maksimal 3 MB.',
            'foto_100.max' => 'Ukuran foto akhir (100%) maksimal 3 MB.',
        ];
    }
}
