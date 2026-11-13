<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PendudukTemplateExport extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithEvents, WithHeadings, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'Template Penduduk SIAK';
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

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            [
                '7203080101260001',
                'Dusun I',
                '001',
                '001',
                'Jl. Trans Sulawesi No. 12',
                '7203081205850001',
                'Budi Santoso',
                'Donggala',
                '1985-05-12',
                'L',
                'Islam',
                'SLTA/Sederajat',
                'Petani/Pekebun',
                'Kawin',
                'Kepala Keluarga',
                'Desil 2',
                'Aktif',
                'Kaili',
                'WNI',
                'Tidak',
                '',
                'Pemerintah',
                'Milik Sendiri',
                'Semi Permanen',
                'PLS 450 watt',
                'Ada',
                'Rp 1.250.000',
                'Warung Sembako',
                'Ayam Kampung 15 ekor',
                'Motor 1, TV 1, HP 1',
                '1',
                '2',
                '',
                'Kelapa 40 Pohon, Coklat 30 Pohon',
            ],
            [
                '7203080101260001',
                'Dusun I',
                '001',
                '001',
                'Jl. Trans Sulawesi No. 12',
                '7203085508880002',
                'Siti Rahmawati',
                'Palu',
                '1988-08-15',
                'P',
                'Islam',
                'SLTA/Sederajat',
                'Mengurus Rumah Tangga',
                'Kawin',
                'Istri',
                'Desil 2',
                'Aktif',
                'Kaili',
                'WNI',
                'Tidak',
                '',
                'Pemerintah',
                'Milik Sendiri',
                'Semi Permanen',
                'PLS 450 watt',
                'Ada',
                'Rp 1.250.000',
                'Warung Sembako',
                'Ayam Kampung 15 ekor',
                'Motor 1, TV 1, HP 1',
                '1',
                '2',
                '',
                'Kelapa 40 Pohon, Coklat 30 Pohon',
            ],
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
