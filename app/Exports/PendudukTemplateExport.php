<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class PendudukTemplateExport extends StringValueBinder implements FromArray, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithTitle
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
            'nomor_kk',
            'dusun',
            'rt',
            'rw',
            'alamat',
            'nik',
            'nama_lengkap',
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'agama',
            'pendidikan',
            'pekerjaan',
            'status_perkawinan',
            'hubungan_keluarga',
            'status_kesejahteraan',
            'status_kependudukan',
        ];
    }

    /**
     * @return list<list<string>>
     */
    public function array(): array
    {
        return [
            [
                "'7203080101260001",
                'Dusun I',
                '001',
                '001',
                'Jl. Trans Sulawesi No. 12',
                "'7203081205850001",
                'Budi Santoso',
                'Donggala',
                '1985-05-12',
                'L',
                'Islam',
                'SLTA/Sederajat',
                'Petani/Pekebun',
                'kawin',
                'kepala_keluarga',
                'dtsen_desil_2',
                'aktif',
            ],
            [
                "'7203080101260001",
                'Dusun I',
                '001',
                '001',
                'Jl. Trans Sulawesi No. 12',
                "'7203085508880002",
                'Siti Rahmawati',
                'Palu',
                '1988-08-15',
                'P',
                'Islam',
                'SLTA/Sederajat',
                'Mengurus Rumah Tangga',
                'kawin',
                'istri',
                'dtsen_desil_2',
                'aktif',
            ],
        ];
    }
}
