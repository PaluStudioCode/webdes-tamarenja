<?php

namespace App\Exports;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Models\KartuKeluarga;
use App\Models\Penduduk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PendudukExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters = []
    ) {}

    public function title(): string
    {
        return 'Data Penduduk';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Nomor KK',
            'Dusun',
            'RT',
            'RW',
            'Alamat',
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
            'Kategori DTSEN',
            'Status Warga',
            'Suku',
            'Kewarganegaraan',
            'Peserta KIP/PIP',
            'Kelas Sekolah',
            'Jenis BPJS',
            'Status Kepemilikan Rumah',
            'Kondisi Rumah',
            'Jenis Listrik',
            'Kamar Mandi',
            'Penghasilan Bulanan',
            'Kepemilikan Usaha',
            'Kepemilikan Hewan Ternak',
            'Kepemilikan Kendaraan/Elektronik',
            'Kepemilikan SHML',
            'Kepemilikan Tanah Bidang',
            'Kepemilikan Tanah Sawah',
            'Tanaman',
        ];
    }

    public function collection(): Collection
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $dusun = trim((string) ($this->filters['dusun'] ?? ''));
        $statusKependudukan = trim((string) ($this->filters['status_kependudukan'] ?? ''));
        $statusKesejahteraan = trim((string) ($this->filters['status_kesejahteraan'] ?? ''));

        return Penduduk::query()
            ->with('kartuKeluarga')
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->where(function (Builder $sub) use ($search) {
                    $sub->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('nomor_kk', 'like', "%{$search}%"));
                });
            })
            ->when($dusun !== '', function (Builder $q) use ($dusun) {
                $q->whereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('dusun', $dusun));
            })
            ->when($statusKependudukan !== '', function (Builder $q) use ($statusKependudukan) {
                $q->where('status_kependudukan', $statusKependudukan);
            })
            ->when($statusKesejahteraan !== '', function (Builder $q) use ($statusKesejahteraan) {
                $q->where('status_kesejahteraan', $statusKesejahteraan);
            })
            ->orderBy(
                KartuKeluarga::select('nomor_kk')
                    ->whereColumn('kartu_keluarga.id', 'penduduk.kartu_keluarga_id')
                    ->limit(1)
            )
            ->orderByRaw("
                CASE hubungan_keluarga
                    WHEN 'kepala_keluarga' THEN 1
                    WHEN 'istri' THEN 2
                    WHEN 'anak' THEN 3
                    ELSE 4
                END ASC
            ")
            ->orderBy('tanggal_lahir')
            ->orderBy('nama_lengkap')
            ->get();
    }

    /**
     * @param  Penduduk  $penduduk
     * @return list<string|null>
     */
    public function map($penduduk): array
    {
        $kk = $penduduk->kartuKeluarga;

        $statusPerkawinanLabel = match ((string) $penduduk->status_perkawinan) {
            'belum_kawin' => 'Belum Kawin',
            'kawin' => 'Kawin',
            'cerai_hidup' => 'Cerai Hidup',
            'cerai_mati' => 'Cerai Mati',
            default => (string) $penduduk->status_perkawinan,
        };

        $hubunganKeluargaLabel = match ((string) $penduduk->hubungan_keluarga) {
            'kepala_keluarga' => 'Kepala Keluarga',
            'istri' => 'Istri',
            'anak' => 'Anak',
            'lainnya' => 'Famili Lain',
            default => (string) $penduduk->hubungan_keluarga,
        };

        $rawKesejahteraan = $kk?->status_kesejahteraan ?? $penduduk->status_kesejahteraan;
        $statusKesejahteraanVal = $rawKesejahteraan instanceof StatusKesejahteraan
            ? $rawKesejahteraan->value
            : (string) $rawKesejahteraan;
        $statusKesejahteraanLabel = match ($statusKesejahteraanVal) {
            StatusKesejahteraan::DtsenDesil1->value => 'Desil 1',
            StatusKesejahteraan::DtsenDesil2->value => 'Desil 2',
            StatusKesejahteraan::DtsenDesil34->value => 'Desil 3-4',
            default => 'Non-Prioritas',
        };

        $statusKependudukanVal = $penduduk->status_kependudukan instanceof StatusKependudukan
            ? $penduduk->status_kependudukan->value
            : (string) $penduduk->status_kependudukan;
        $statusKependudukanLabel = match ($statusKependudukanVal) {
            StatusKependudukan::Aktif->value => 'Aktif',
            StatusKependudukan::PindahKeluar->value => 'Pindah Keluar',
            StatusKependudukan::MeninggalDunia->value => 'Meninggal Dunia',
            default => $statusKependudukanVal,
        };

        return [
            (string) ($kk?->nomor_kk ?? ''),
            (string) ($kk?->dusun ?? ''),
            (string) ($kk?->rt ?? '001'),
            (string) ($kk?->rw ?? '001'),
            $kk?->alamat,
            (string) $penduduk->nik,
            (string) $penduduk->nama_lengkap,
            (string) $penduduk->tempat_lahir,
            $penduduk->tanggal_lahir?->format('Y-m-d'),
            (string) $penduduk->jenis_kelamin,
            (string) $penduduk->agama,
            $penduduk->pendidikan,
            $penduduk->pekerjaan,
            $statusPerkawinanLabel,
            $hubunganKeluargaLabel,
            $statusKesejahteraanLabel,
            $statusKependudukanLabel,
            $penduduk->suku,
            (string) ($penduduk->kewarganegaraan ?: 'WNI'),
            $penduduk->peserta_kip_pip,
            $penduduk->kelas_sekolah,
            $penduduk->jenis_bpjs,
            $kk?->status_kepemilikan_rumah,
            $kk?->kondisi_rumah,
            $kk?->jenis_listrik,
            $kk?->kamar_mandi,
            $kk?->penghasilan_bulanan,
            $kk?->kepemilikan_usaha,
            $kk?->kepemilikan_hewan_ternak,
            $kk?->kepemilikan_kendaraan_elektronik,
            $kk?->kepemilikan_shml,
            $kk?->kepemilikan_tanah_bidang,
            $kk?->kepemilikan_tanah_sawah,
            $kk?->tanaman,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size' => 10,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '1E3A8A'],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = max(1, $sheet->getHighestRow());
                $highestCol = $sheet->getHighestColumn();

                $sheet->getRowDimension(1)->setRowHeight(24);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:{$highestCol}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                foreach (['C', 'D', 'I', 'J'] as $col) {
                    $sheet->getStyle("{$col}1:{$col}{$highestRow}")
                        ->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            },
        ];
    }
}
