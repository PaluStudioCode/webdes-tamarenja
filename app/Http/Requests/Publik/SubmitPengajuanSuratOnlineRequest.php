<?php

namespace App\Http\Requests\Publik;

use App\Enums\JenisSurat;
use App\Support\SuratTemplateSchemaValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitPengajuanSuratOnlineRequest extends FormRequest
{
    public const ANTI_ENUMERATION_MESSAGE = 'Data kependudukan tidak sesuai atau tidak aktif, silakan periksa kembali atau hubungi loket Kantor Desa.';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rawWa = (string) $this->input('no_wa_pemohon', '');
        $cleanedWa = preg_replace('/[\s\-\(\)]+/', '', trim($rawWa));

        $this->merge([
            'nik' => preg_replace('/\D/', '', (string) $this->input('nik', '')),
            'no_wa_pemohon' => $cleanedWa,
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
        $jenisSurat = JenisSurat::tryFrom((string) $this->input('jenis_surat'));

        $baseRules = [
            'jenis_surat' => ['required', Rule::enum(JenisSurat::class)],
            'nik' => ['required', 'string', 'digits:16', 'regex:/^[0-9]{16}$/'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'no_wa_pemohon' => ['required', 'string', 'regex:/^(08|628|\+628)[0-9]{7,13}$/'],
            'keperluan' => ['required', 'string', 'max:255'],
            'data_khusus' => ['required', 'array'],
        ];

        return array_merge($baseRules, SuratTemplateSchemaValidator::rulesFor($jenisSurat));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge([
            'jenis_surat.required' => 'Jenis surat layanan wajib dipilih.',
            'jenis_surat.Illuminate\Validation\Rules\Enum' => 'Jenis surat yang dipilih tidak valid.',
            'nik.required' => 'Nomor Induk Kependudukan (NIK) wajib diisi.',
            'nik.digits' => 'NIK wajib terdiri dari tepat 16 digit angka sesuai KTP/KK.',
            'nik.regex' => 'NIK hanya boleh berisi 16 digit angka.',
            'tanggal_lahir.required' => 'Tanggal lahir wajib diisi untuk verifikasi identitas.',
            'tanggal_lahir.date' => 'Format tanggal lahir tidak valid.',
            'tanggal_lahir.before_or_equal' => 'Tanggal lahir tidak boleh melebihi hari ini.',
            'no_wa_pemohon.required' => 'Nomor WhatsApp aktif wajib diisi untuk konfirmasi pengambilan surat.',
            'no_wa_pemohon.regex' => 'Format nomor WhatsApp tidak valid (gunakan awalan 08... atau 628...).',
            'keperluan.required' => 'Keperluan pengajuan surat wajib diisi.',
            'keperluan.max' => 'Keperluan surat maksimal 255 karakter.',
            'data_khusus.required' => 'Rincian keterangan khusus surat wajib dilengkapi.',
            'data_khusus.array' => 'Format rincian keterangan khusus surat tidak valid.',
        ], SuratTemplateSchemaValidator::messages());
    }
}
