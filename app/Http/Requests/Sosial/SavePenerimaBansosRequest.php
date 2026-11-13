<?php

namespace App\Http\Requests\Sosial;

use App\Enums\JenisBansos;
use App\Enums\StatusKependudukan;
use App\Enums\StatusPesertaBansos;
use App\Models\Penduduk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePenerimaBansosRequest extends FormRequest
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
            'penduduk_id' => ['required', 'integer', 'exists:penduduk,id'],
            'jenis_bantuan' => ['required', Rule::enum(JenisBansos::class)],
            'bentuk_nominal' => ['required', 'string', 'max:150'],
            'tahun_anggaran' => ['required', 'integer', 'between:2020,2100'],
            'tahap_penyaluran' => ['required', 'string', 'max:50'],
            'status_kepesertaan' => ['nullable', Rule::enum(StatusPesertaBansos::class)],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'penduduk_id.required' => 'Warga penerima bantuan wajib dipilih.',
            'penduduk_id.exists' => 'Data penduduk yang dipilih tidak ditemukan.',
            'jenis_bantuan.required' => 'Jenis program bantuan wajib dipilih.',
            'bentuk_nominal.required' => 'Bentuk atau nominal bantuan wajib diisi (contoh: Rp 300.000 / Bulan).',
            'tahun_anggaran.required' => 'Tahun anggaran penyaluran wajib diisi.',
            'tahap_penyaluran.required' => 'Tahap penyaluran wajib diisi (contoh: Tahap I).',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('penduduk_id')) {
                    return;
                }

                $pendudukId = $this->input('penduduk_id');
                /** @var Penduduk|null $penduduk */
                $penduduk = Penduduk::query()->find($pendudukId);

                if (! $penduduk) {
                    $validator->errors()->add('penduduk_id', 'Data penduduk tidak ditemukan.');

                    return;
                }

                $statusKependudukan = $penduduk->status_kependudukan instanceof StatusKependudukan
                    ? $penduduk->status_kependudukan->value
                    : (string) $penduduk->status_kependudukan;

                $requestedStatus = (string) ($this->input('status_kepesertaan') ?: StatusPesertaBansos::Aktif->value);

                // Saat menambah penerima baru (POST) atau mengatur status_kepesertaan = aktif, penduduk wajib aktif
                if (
                    ($this->isMethod('post') || $requestedStatus === StatusPesertaBansos::Aktif->value) &&
                    $statusKependudukan !== StatusKependudukan::Aktif->value
                ) {
                    $validator->errors()->add(
                        'penduduk_id',
                        'Warga yang berstatus Meninggal Dunia atau Pindah Keluar tidak dapat didaftarkan sebagai penerima bantuan sosial aktif.'
                    );

                    return;
                }

                // Cek larangan bantuan ganda dalam 1 KK pada program & tahun yang sama
                if ($requestedStatus === StatusPesertaBansos::Aktif->value && $penduduk->kartu_keluarga_id) {
                    $jenisBantuan = $this->input('jenis_bantuan');
                    $tahunAnggaran = (int) $this->input('tahun_anggaran');

                    $currentId = null;
                    $routeBansos = $this->route('bansos');
                    if ($routeBansos instanceof \App\Models\PenerimaBansos) {
                        $currentId = $routeBansos->id;
                    } elseif ($routeBansos) {
                        $currentId = (int) $routeBansos;
                    }

                    $duplicateQuery = \App\Models\PenerimaBansos::query()
                        ->whereHas('penduduk', function ($q) use ($penduduk) {
                            $q->where('kartu_keluarga_id', $penduduk->kartu_keluarga_id)
                                ->where('id', '!=', $penduduk->id);
                        })
                        ->where('jenis_bantuan', $jenisBantuan)
                        ->where('tahun_anggaran', $tahunAnggaran)
                        ->where('status_kepesertaan', StatusPesertaBansos::Aktif->value);

                    if ($currentId) {
                        $duplicateQuery->where('id', '!=', $currentId);
                    }

                    $duplicate = $duplicateQuery->with('penduduk')->first();

                    if ($duplicate) {
                        $namaAnggota = $duplicate->penduduk?->nama_lengkap ?? 'Anggota keluarga lain';
                        $labelProgram = $duplicate->jenis_bantuan instanceof JenisBansos
                            ? $duplicate->jenis_bantuan->label()
                            : (string) $duplicate->jenis_bantuan;

                        $validator->errors()->add(
                            'penduduk_id',
                            "Bantuan ganda dalam 1 KK dilarang: Anggota keluarga lain atas nama {$namaAnggota} sudah terdaftar aktif menerima {$labelProgram} pada tahun anggaran {$tahunAnggaran}."
                        );
                    }
                }
            },
        ];
    }
}
