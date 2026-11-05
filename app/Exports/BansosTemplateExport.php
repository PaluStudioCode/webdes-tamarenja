<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class BansosTemplateExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Import Bansos';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'nik',
            'nama_lengkap',
            'jenis_bantuan',
            'bentuk_nominal',
            'tahun_anggaran',
            'tahap_penyaluran',
            'status_kepesertaan',
            'keterangan',
        ];
    }

    public function collection(): Collection
    {
        return collect([
            [
                '7203081205900001',
                'Budi Santoso',
                'blt_dd',
                'Rp 300.000 / Bulan',
                now()->year,
                'Tahap I',
                'aktif',
                'Penerima BLT Dana Desa Tahap I',
            ],
        ]);
    }
}
