<?php

namespace App\Exports;

use App\Enums\StatusKependudukan;
use App\Enums\StatusKesejahteraan;
use App\Models\Penduduk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class PendudukExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithTitle
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
            ->orderBy('kartu_keluarga_id')
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
        $statusKesejahteraan = $penduduk->status_kesejahteraan instanceof StatusKesejahteraan
            ? $penduduk->status_kesejahteraan->value
            : (string) $penduduk->status_kesejahteraan;
        $statusKependudukan = $penduduk->status_kependudukan instanceof StatusKependudukan
            ? $penduduk->status_kependudukan->value
            : (string) $penduduk->status_kependudukan;

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
            (string) $penduduk->status_perkawinan,
            (string) $penduduk->hubungan_keluarga,
            $statusKesejahteraan,
            $statusKependudukan,
        ];
    }
}
