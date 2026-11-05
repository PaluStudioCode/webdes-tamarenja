<?php

namespace App\Http\Requests\Sosial;

use App\Enums\KategoriKegiatan;
use App\Enums\StatusPublikasi;
use App\Models\KegiatanDesa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveKegiatanDesaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isOperator();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:200'],
            'kategori' => ['required', Rule::enum(KategoriKegiatan::class)],
            'tanggal_kegiatan' => ['required', 'date'],
            'lokasi_dusun' => ['required', 'string', 'max:150'],
            'uraian' => ['required', 'string'],
            'status_publikasi' => ['required', Rule::enum(StatusPublikasi::class)],
            'foto_baru' => ['nullable', 'array', 'max:5'],
            'foto_baru.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'existing_foto_ids' => ['nullable', 'array'],
            'existing_foto_ids.*' => ['integer'],
            'hapus_foto_ids' => ['nullable', 'array'],
            'hapus_foto_ids.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'judul.required' => 'Judul kegiatan desa wajib diisi.',
            'judul.max' => 'Judul kegiatan maksimal 200 karakter.',
            'kategori.required' => 'Kategori kegiatan wajib dipilih.',
            'tanggal_kegiatan.required' => 'Tanggal pelaksanaan kegiatan wajib diisi.',
            'lokasi_dusun.required' => 'Lokasi pelaksanaan kegiatan wajib diisi.',
            'uraian.required' => 'Uraian atau deskripsi kegiatan wajib diisi.',
            'status_publikasi.required' => 'Status publikasi kegiatan wajib dipilih.',
            'foto_baru.max' => 'Maksimal 5 berkas foto yang dapat diunggah.',
            'foto_baru.*.image' => 'Berkas yang diunggah harus berupa gambar.',
            'foto_baru.*.mimes' => 'Format foto harus berupa JPG, PNG, atau WEBP.',
            'foto_baru.*.max' => 'Ukuran setiap foto maksimal 3 MB.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $newFiles = $this->file('foto_baru', []);
                $newCount = is_array($newFiles) ? count($newFiles) : 0;

                /** @var KegiatanDesa|null $kegiatan */
                $kegiatan = $this->route('kegiatan');
                $remainingExistingCount = 0;

                if ($kegiatan instanceof KegiatanDesa) {
                    $allExistingIds = $kegiatan->foto()->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $hapusIds = collect($this->input('hapus_foto_ids', []))->map(fn ($id) => (int) $id)->all();

                    if ($this->has('existing_foto_ids') && is_array($this->input('existing_foto_ids'))) {
                        $keptIds = collect($this->input('existing_foto_ids'))
                            ->map(fn ($id) => (int) $id)
                            ->filter(fn ($id) => in_array($id, $allExistingIds, true) && ! in_array($id, $hapusIds, true))
                            ->unique()
                            ->values()
                            ->all();
                        $remainingExistingCount = count($keptIds);
                    } else {
                        $keptIds = array_diff($allExistingIds, $hapusIds);
                        $remainingExistingCount = count($keptIds);
                    }
                }

                $totalPhotos = $remainingExistingCount + $newCount;

                if ($totalPhotos > 5) {
                    $validator->errors()->add(
                        'foto_baru',
                        'Jumlah total foto dokumentasi kegiatan tidak boleh melebihi 5 foto.'
                    );
                }

                $statusPublikasi = (string) $this->input('status_publikasi');
                if ($statusPublikasi === StatusPublikasi::Published->value && $totalPhotos < 1) {
                    $validator->errors()->add(
                        'foto_baru',
                        'Minimal 1 foto dokumentasi wajib diunggah apabila kegiatan langsung diterbitkan (Published).'
                    );
                }
            },
        ];
    }
}
