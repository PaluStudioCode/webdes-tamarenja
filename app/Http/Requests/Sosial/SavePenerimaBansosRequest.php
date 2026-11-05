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
                }
            },
        ];
    }
}
