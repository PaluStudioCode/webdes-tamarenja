<?php

namespace App\Support;

use App\Enums\JenisSurat;
use Illuminate\Validation\Rule;

class SuratTemplateSchemaValidator
{
    /**
     * Kembalikan aturan validasi Laravel untuk kolom JSON `data_khusus.*`
     * sesuai spesifikasi Seksi 1.2.8 PRD 03.
     *
     * @return array<string, mixed>
     */
    public static function rulesFor(?JenisSurat $jenisSurat): array
    {
        if ($jenisSurat === null) {
            return [];
        }

        return match ($jenisSurat) {
            JenisSurat::Sku => [
                'data_khusus.nama_usaha' => ['required', 'string', 'max:150'],
                'data_khusus.jenis_usaha' => ['required', 'string', 'max:100'],
                'data_khusus.bidang_usaha' => ['required', 'string', 'max:100'],
                'data_khusus.lama_usaha' => ['required', 'string', 'max:100'],
                'data_khusus.alamat_usaha' => ['required', 'string', 'max:255'],
            ],
            JenisSurat::Domisili => [
                'data_khusus.sejak_tahun' => ['required', 'string', 'max:50'],
                'data_khusus.status_tempat_tinggal' => ['required', 'string', 'max:100'],
            ],
            JenisSurat::Sktm => [
                'data_khusus.penghasilan_rata_rata' => ['required', 'numeric', 'min:0'],
                'data_khusus.jumlah_tanggungan' => ['required', 'integer', 'min:0'],
                'data_khusus.nama_tanggungan' => ['nullable', 'string', 'max:255'],
            ],
            JenisSurat::PengantarSkck => [
                'data_khusus.catatan_kelakuan' => ['required', 'string', 'max:255'],
                'data_khusus.tujuan_skck' => ['required', 'string', 'max:255'],
            ],
            JenisSurat::Kematian => [
                'data_khusus.hari_meninggal' => ['required', 'string', 'max:30'],
                'data_khusus.tanggal_meninggal' => ['required', 'date', 'before_or_equal:today'],
                'data_khusus.jam_meninggal' => ['nullable', 'string', 'max:20'],
                'data_khusus.tempat_meninggal' => ['required', 'string', 'max:150'],
                'data_khusus.penyebab_meninggal' => ['required', 'string', 'max:150'],
                'data_khusus.lokasi_makam' => ['required', 'string', 'max:150'],
                'data_khusus.nama_pelapor' => ['required', 'string', 'max:150'],
                'data_khusus.nik_pelapor' => ['nullable', 'digits:16'],
                'data_khusus.hubungan_pelapor' => ['required', 'string', 'max:100'],
            ],
            JenisSurat::Kelahiran => [
                'data_khusus.nama_bayi' => ['required', 'string', 'max:150'],
                'data_khusus.jenis_kelamin_bayi' => ['required', Rule::in(['L', 'P'])],
                'data_khusus.hari_lahir_bayi' => ['required', 'string', 'max:30'],
                'data_khusus.tanggal_lahir_bayi' => ['required', 'date', 'before_or_equal:today'],
                'data_khusus.jam_lahir_bayi' => ['nullable', 'string', 'max:20'],
                'data_khusus.tempat_lahir_bayi' => ['required', 'string', 'max:150'],
                'data_khusus.anak_ke' => ['required', 'integer', 'min:1'],
                'data_khusus.nama_ayah' => ['required', 'string', 'max:150'],
                'data_khusus.nik_ayah' => ['nullable', 'digits:16'],
                'data_khusus.nama_ibu' => ['required', 'string', 'max:150'],
                'data_khusus.nik_ibu' => ['nullable', 'digits:16'],
            ],
            JenisSurat::BelumMenikah => [
                'data_khusus.pernyataan_belum_menikah' => ['required', 'string', 'max:255'],
            ],
            JenisSurat::JandaDuda => [
                'data_khusus.status_cerai' => ['required', Rule::in(['cerai_hidup', 'cerai_mati'])],
                'data_khusus.nama_mantan_pasangan' => ['required', 'string', 'max:150'],
                'data_khusus.sejak_tanggal_tahun' => ['required', 'string', 'max:100'],
            ],
            JenisSurat::Kehilangan => [
                'data_khusus.barang_hilang' => ['required', 'string', 'max:255'],
                'data_khusus.waktu_kehilangan' => ['required', 'string', 'max:150'],
                'data_khusus.lokasi_kehilangan' => ['required', 'string', 'max:200'],
            ],
            JenisSurat::KeteranganUmum => [
                'data_khusus.judul_keterangan' => ['nullable', 'string', 'max:150'],
                'data_khusus.isi_keterangan' => ['required', 'string', 'max:2000'],
            ],
        };
    }

    /**
     * Pesan validasi Bahasa Indonesia untuk ke-10 skema surat.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'data_khusus.nama_usaha.required' => 'Nama usaha wajib diisi pada Surat Keterangan Usaha.',
            'data_khusus.jenis_usaha.required' => 'Jenis usaha wajib diisi pada Surat Keterangan Usaha.',
            'data_khusus.bidang_usaha.required' => 'Bidang usaha wajib diisi pada Surat Keterangan Usaha.',
            'data_khusus.lama_usaha.required' => 'Lama usaha wajib diisi pada Surat Keterangan Usaha.',
            'data_khusus.alamat_usaha.required' => 'Alamat lokasi usaha wajib diisi pada Surat Keterangan Usaha.',
            'data_khusus.sejak_tahun.required' => 'Tahun mulai menetap wajib diisi pada Surat Keterangan Domisili.',
            'data_khusus.status_tempat_tinggal.required' => 'Status tempat tinggal wajib diisi pada Surat Keterangan Domisili.',
            'data_khusus.penghasilan_rata_rata.required' => 'Penghasilan rata-rata keluarga wajib diisi pada SKTM.',
            'data_khusus.jumlah_tanggungan.required' => 'Jumlah tanggungan keluarga wajib diisi pada SKTM.',
            'data_khusus.catatan_kelakuan.required' => 'Catatan kelakuan warga wajib diisi pada Surat Pengantar SKCK.',
            'data_khusus.tujuan_skck.required' => 'Tujuan pengurusan SKCK wajib diisi.',
            'data_khusus.hari_meninggal.required' => 'Hari meninggal wajib diisi pada Surat Kematian.',
            'data_khusus.tanggal_meninggal.required' => 'Tanggal meninggal wajib diisi pada Surat Kematian.',
            'data_khusus.tempat_meninggal.required' => 'Tempat meninggal wajib diisi pada Surat Kematian.',
            'data_khusus.penyebab_meninggal.required' => 'Penyebab meninggal wajib diisi pada Surat Kematian.',
            'data_khusus.lokasi_makam.required' => 'Lokasi pemakaman wajib diisi pada Surat Kematian.',
            'data_khusus.nama_pelapor.required' => 'Nama pelapor wajib diisi pada Surat Kematian.',
            'data_khusus.hubungan_pelapor.required' => 'Hubungan pelapor dengan almarhum/ah wajib diisi.',
            'data_khusus.nama_bayi.required' => 'Nama bayi wajib diisi pada Surat Kelahiran.',
            'data_khusus.jenis_kelamin_bayi.required' => 'Jenis kelamin bayi wajib dipilih (L/P).',
            'data_khusus.hari_lahir_bayi.required' => 'Hari kelahiran bayi wajib diisi.',
            'data_khusus.tanggal_lahir_bayi.required' => 'Tanggal lahir bayi wajib diisi.',
            'data_khusus.tempat_lahir_bayi.required' => 'Tempat kelahiran bayi wajib diisi.',
            'data_khusus.anak_ke.required' => 'Urutan kelahiran anak ke-berapa wajib diisi.',
            'data_khusus.nama_ayah.required' => 'Nama ayah kandung wajib diisi pada Surat Kelahiran.',
            'data_khusus.nama_ibu.required' => 'Nama ibu kandung wajib diisi pada Surat Kelahiran.',
            'data_khusus.pernyataan_belum_menikah.required' => 'Keterangan pernyataan belum menikah wajib diisi.',
            'data_khusus.status_cerai.required' => 'Status perceraian (cerai hidup / cerai mati) wajib dipilih.',
            'data_khusus.nama_mantan_pasangan.required' => 'Nama mantan pasangan wajib diisi pada Surat Janda/Duda.',
            'data_khusus.sejak_tanggal_tahun.required' => 'Waktu sejak berstatus janda/duda wajib diisi.',
            'data_khusus.barang_hilang.required' => 'Rincian barang/dokumen yang hilang wajib diisi.',
            'data_khusus.waktu_kehilangan.required' => 'Perkiraan waktu kehilangan wajib diisi.',
            'data_khusus.lokasi_kehilangan.required' => 'Perkiraan lokasi kehilangan wajib diisi.',
            'data_khusus.isi_keterangan.required' => 'Uraian isi keterangan wajib diisi pada Surat Keterangan Umum.',
        ];
    }

    /**
     * Resolusi dan validasi kode klasifikasi surat berdasarkan jenis surat.
     */
    public static function resolveKodeKlasifikasi(JenisSurat $jenisSurat, ?string $requestedKode = null): string
    {
        $trimmed = trim((string) $requestedKode);
        if ($trimmed === '') {
            return $jenisSurat->defaultKodeKlasifikasi();
        }

        if (in_array($trimmed, $jenisSurat->allowedKodeKlasifikasi(), true)) {
            return $trimmed;
        }

        return $jenisSurat->defaultKodeKlasifikasi();
    }
}
