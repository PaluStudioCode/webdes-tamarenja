<?php

namespace App\Exports;

use App\Models\ApbdesRincian;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\Penduduk;
use App\Models\PenerimaBansos;
use App\Models\ProyekPembangunan;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class FullDatabaseBackupExport implements Export, WithMultipleSheets
{
    public const SHEET_TITLES = [
        'Kartu Keluarga',
        'Penduduk',
        'Proyek Pembangunan',
        'APBDes',
        'Surat Keluar',
        'Surat Masuk',
        'Penerima Bansos',
        'Kegiatan Desa',
    ];

    /**
     * Hasilkan tepat 8 lembar kerja Excel sesuai spesifikasi PRD.
     *
     * @return array<int, object>
     */
    public function sheets(): array
    {
        return [
            $this->makeSheet(
                'Kartu Keluarga',
                ['ID', 'Nomor KK', 'Dusun', 'RT', 'RW', 'Alamat', 'Tanggal Dibuat'],
                fn (): Collection => KartuKeluarga::query()
                    ->orderBy('id')
                    ->get()
                    ->map(fn (KartuKeluarga $kk) => [
                        $kk->id,
                        "'" . $kk->nomor_kk,
                        $kk->dusun,
                        $kk->rt,
                        $kk->rw,
                        $kk->alamat ?? '-',
                        $kk->created_at?->format('Y-m-d H:i:s') ?? '-',
                    ])
            ),
            $this->makeSheet(
                'Penduduk',
                [
                    'ID',
                    'Nomor KK',
                    'NIK',
                    'Nama Lengkap',
                    'Tempat Lahir',
                    'Tanggal Lahir',
                    'Jenis Kelamin',
                    'Agama',
                    'Pendidikan',
                    'Pekerjaan',
                    'Status Perkawinan',
                    'Hubungan Keluarga',
                    'Status Kesejahteraan (DTSEN)',
                    'Status Kependudukan',
                    'Dusun',
                ],
                fn (): Collection => Penduduk::query()
                    ->with('kartuKeluarga:id,nomor_kk,dusun')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Penduduk $p) => [
                        $p->id,
                        "'" . ($p->kartuKeluarga?->nomor_kk ?? '-'),
                        "'" . $p->nik,
                        $p->nama_lengkap,
                        $p->tempat_lahir,
                        $p->tanggal_lahir?->format('Y-m-d') ?? '-',
                        $p->jenis_kelamin,
                        $p->agama,
                        $p->pendidikan ?? '-',
                        $p->pekerjaan ?? '-',
                        $p->status_perkawinan,
                        $p->hubungan_keluarga,
                        $p->status_kesejahteraan?->value ?? (string) $p->status_kesejahteraan,
                        $p->status_kependudukan?->value ?? (string) $p->status_kependudukan,
                        $p->kartuKeluarga?->dusun ?? '-',
                    ])
            ),
            $this->makeSheet(
                'Proyek Pembangunan',
                [
                    'ID',
                    'Tahun Anggaran',
                    'Nama Kegiatan',
                    'Lokasi Dusun',
                    'Sumber Dana',
                    'Pagu Anggaran (Rp)',
                    'Realisasi Anggaran (Rp)',
                    'Progres Fisik (%)',
                    'Status Proyek',
                    'Volume Fisik',
                    'Pelaksana Kegiatan',
                    'Catatan Kendala',
                ],
                fn (): Collection => ProyekPembangunan::query()
                    ->orderByDesc('tahun_anggaran')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ProyekPembangunan $pr) => [
                        $pr->id,
                        (int) $pr->tahun_anggaran,
                        $pr->nama_kegiatan,
                        $pr->lokasi_dusun,
                        $pr->sumber_dana?->value ?? (string) $pr->sumber_dana,
                        (int) $pr->pagu_anggaran,
                        (int) $pr->realisasi_anggaran,
                        (int) $pr->progres_fisik,
                        $pr->status_proyek?->value ?? (string) $pr->status_proyek,
                        $pr->volume_fisik ?? '-',
                        $pr->pelaksana_kegiatan ?? '-',
                        $pr->catatan_kendala ?? '-',
                    ])
            ),
            $this->makeSheet(
                'APBDes',
                [
                    'Tahun Anggaran',
                    'Jenis Dokumen',
                    'Nomor Perdes',
                    'Kelompok Pos',
                    'Kode Pos',
                    'Nama Pos',
                    'Pagu Anggaran (Rp)',
                    'Realisasi Tahap I (Rp)',
                    'Realisasi Tahap II (Rp)',
                    'Realisasi Tahap III (Rp)',
                    'Total Realisasi (Rp)',
                ],
                fn (): Collection => ApbdesRincian::query()
                    ->with('apbdes:id,tahun_anggaran,jenis_dokumen,nomor_perdes')
                    ->orderBy('apbdes_id')
                    ->orderBy('urutan')
                    ->get()
                    ->map(fn (ApbdesRincian $r) => [
                        (int) ($r->apbdes?->tahun_anggaran ?? 0),
                        $r->apbdes?->jenis_dokumen?->value ?? (string) ($r->apbdes?->jenis_dokumen ?? '-'),
                        $r->apbdes?->nomor_perdes ?? '-',
                        $r->kelompok?->value ?? (string) $r->kelompok,
                        $r->kode_pos,
                        $r->nama_pos,
                        (int) $r->pagu_anggaran,
                        (int) $r->realisasi_tahap_1,
                        (int) $r->realisasi_tahap_2,
                        (int) $r->realisasi_tahap_3,
                        (int) $r->total_realisasi,
                    ])
            ),
            $this->makeSheet(
                'Surat Keluar',
                [
                    'ID',
                    'Tahun Surat',
                    'Nomor Urut',
                    'Nomor Surat',
                    'Tanggal Surat',
                    'Jenis Surat',
                    'NIK Pemohon/Subjek',
                    'Nama Pemohon/Subjek',
                    'Keperluan',
                    'Pejabat Penandatangan',
                    'Status Surat',
                    'Sumber Pengajuan',
                ],
                fn (): Collection => SuratKeluar::query()
                    ->with('penduduk:id,nik,nama_lengkap')
                    ->orderByDesc('tahun_surat')
                    ->orderBy('nomor_urut')
                    ->get()
                    ->map(fn (SuratKeluar $sk) => [
                        $sk->id,
                        $sk->tahun_surat ?? '-',
                        $sk->nomor_urut ?? '-',
                        $sk->nomor_surat ?? '-',
                        $sk->tanggal_surat?->format('Y-m-d') ?? '-',
                        $sk->jenis_surat?->value ?? (string) $sk->jenis_surat,
                        "'" . ($sk->penduduk?->nik ?? '-'),
                        $sk->penduduk?->nama_lengkap ?? '-',
                        $sk->keperluan,
                        $sk->nama_penandatangan ?? '-',
                        $sk->status?->value ?? (string) $sk->status,
                        $sk->sumber_pengajuan,
                    ])
            ),
            $this->makeSheet(
                'Surat Masuk',
                [
                    'ID',
                    'Tahun Agenda',
                    'Nomor Urut Agenda',
                    'Nomor Surat Masuk',
                    'Tanggal Surat',
                    'Tanggal Terima',
                    'Pengirim',
                    'Perihal',
                    'Tujuan Disposisi',
                    'Catatan Disposisi',
                ],
                fn (): Collection => SuratMasuk::query()
                    ->orderByDesc('tahun_agenda')
                    ->orderBy('nomor_urut_agenda')
                    ->get()
                    ->map(fn (SuratMasuk $sm) => [
                        $sm->id,
                        (int) $sm->tahun_agenda,
                        (int) $sm->nomor_urut_agenda,
                        $sm->nomor_surat_masuk,
                        $sm->tanggal_surat?->format('Y-m-d') ?? '-',
                        $sm->tanggal_terima?->format('Y-m-d') ?? '-',
                        $sm->pengirim,
                        $sm->perihal,
                        $sm->tujuan_disposisi ?? '-',
                        $sm->catatan_disposisi ?? '-',
                    ])
            ),
            $this->makeSheet(
                'Penerima Bansos',
                [
                    'ID',
                    'Tahun Anggaran',
                    'Tahap Penyaluran',
                    'Jenis Bantuan',
                    'Bentuk / Nominal',
                    'NIK Penerima',
                    'Nama Penerima',
                    'Nomor KK',
                    'Dusun',
                    'Kategori Ekonomi (DTSEN)',
                    'Status Kepesertaan',
                    'Keterangan',
                ],
                fn (): Collection => PenerimaBansos::query()
                    ->with('penduduk.kartuKeluarga')
                    ->orderByDesc('tahun_anggaran')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (PenerimaBansos $b) => [
                        $b->id,
                        (int) $b->tahun_anggaran,
                        $b->tahap_penyaluran,
                        $b->jenis_bantuan?->value ?? (string) $b->jenis_bantuan,
                        $b->bentuk_nominal,
                        "'" . ($b->penduduk?->nik ?? '-'),
                        $b->penduduk?->nama_lengkap ?? '-',
                        "'" . ($b->penduduk?->kartuKeluarga?->nomor_kk ?? '-'),
                        $b->penduduk?->kartuKeluarga?->dusun ?? '-',
                        $b->kategori_ekonomi?->value ?? (string) $b->kategori_ekonomi,
                        $b->status_kepesertaan?->value ?? (string) $b->status_kepesertaan,
                        $b->keterangan ?? '-',
                    ])
            ),
            $this->makeSheet(
                'Kegiatan Desa',
                [
                    'ID',
                    'Tanggal Kegiatan',
                    'Judul Kegiatan',
                    'Kategori',
                    'Lokasi Dusun',
                    'Status Publikasi',
                    'Jumlah Foto',
                    'Uraian Singkat',
                ],
                fn (): Collection => KegiatanDesa::query()
                    ->withCount('foto')
                    ->orderByDesc('tanggal_kegiatan')
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (KegiatanDesa $k) => [
                        $k->id,
                        $k->tanggal_kegiatan?->format('Y-m-d') ?? '-',
                        $k->judul,
                        $k->kategori?->value ?? (string) $k->kategori,
                        $k->lokasi_dusun,
                        $k->status_publikasi?->value ?? (string) $k->status_publikasi,
                        (int) ($k->foto_count ?? 0),
                        $k->uraian,
                    ])
            ),
        ];
    }

    /**
     * @param list<string> $headings
     * @param \Closure(): Collection $resolver
     */
    private function makeSheet(string $title, array $headings, \Closure $resolver): object
    {
        return new class($title, $headings, $resolver) implements FromCollection, WithHeadings, WithTitle
        {
            /**
             * @param list<string> $headings
             */
            public function __construct(
                private readonly string $sheetTitle,
                private readonly array $headings,
                private readonly \Closure $resolver,
            ) {}

            public function title(): string
            {
                return $this->sheetTitle;
            }

            /**
             * @return list<string>
             */
            public function headings(): array
            {
                return $this->headings;
            }

            public function collection(): Collection
            {
                return ($this->resolver)();
            }
        };
    }
}
