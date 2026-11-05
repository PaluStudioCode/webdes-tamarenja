<?php

namespace App\Exports;

use App\Enums\JenisBansos;
use App\Enums\StatusKesejahteraan;
use App\Enums\StatusPesertaBansos;
use App\Models\PenerimaBansos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class BansosExport extends StringValueBinder implements FromCollection, ShouldAutoSize, WithCustomValueBinder, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters = []
    ) {}

    public function title(): string
    {
        return 'Data Penerima Bansos';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'nomor_kk',
            'nik',
            'nama_lengkap',
            'dusun',
            'jenis_bantuan',
            'nama_program_bantuan',
            'bentuk_nominal',
            'tahun_anggaran',
            'tahap_penyaluran',
            'kategori_ekonomi',
            'status_kepesertaan',
            'keterangan',
        ];
    }

    public function collection(): Collection
    {
        $tahun = isset($this->filters['tahun_anggaran']) && (string) $this->filters['tahun_anggaran'] !== ''
            ? (int) $this->filters['tahun_anggaran']
            : null;
        $jenis = trim((string) ($this->filters['jenis_bantuan'] ?? ''));
        $tahap = trim((string) ($this->filters['tahap_penyaluran'] ?? ''));
        $status = trim((string) ($this->filters['status_kepesertaan'] ?? ''));
        $dusun = trim((string) ($this->filters['dusun'] ?? ''));
        $search = trim((string) ($this->filters['search'] ?? ''));

        return PenerimaBansos::query()
            ->with(['penduduk.kartuKeluarga'])
            ->when($tahun !== null, fn (Builder $q) => $q->where('tahun_anggaran', $tahun))
            ->when($jenis !== '', fn (Builder $q) => $q->where('jenis_bantuan', $jenis))
            ->when($tahap !== '', fn (Builder $q) => $q->where('tahap_penyaluran', $tahap))
            ->when($status !== '', fn (Builder $q) => $q->where('status_kepesertaan', $status))
            ->when($dusun !== '', function (Builder $q) use ($dusun) {
                $q->whereHas('penduduk.kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('dusun', $dusun));
            })
            ->when($search !== '', function (Builder $q) use ($search) {
                $q->whereHas('penduduk', function (Builder $pq) use ($search) {
                    $pq->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_lengkap', 'like', "%{$search}%")
                        ->orWhereHas('kartuKeluarga', fn (Builder $kkQ) => $kkQ->where('nomor_kk', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('tahun_anggaran')
            ->orderBy('jenis_bantuan')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  PenerimaBansos  $item
     * @return list<string|int|null>
     */
    public function map($item): array
    {
        $penduduk = $item->penduduk;
        $kk = $penduduk?->kartuKeluarga;

        $jenisVal = $item->jenis_bantuan instanceof JenisBansos
            ? $item->jenis_bantuan->value
            : (string) $item->jenis_bantuan;
        $jenisLabel = $item->jenis_bantuan instanceof JenisBansos
            ? $item->jenis_bantuan->label()
            : $jenisVal;

        $ekonomiVal = $item->kategori_ekonomi instanceof StatusKesejahteraan
            ? $item->kategori_ekonomi->value
            : (string) $item->kategori_ekonomi;

        $statusVal = $item->status_kepesertaan instanceof StatusPesertaBansos
            ? $item->status_kepesertaan->value
            : (string) $item->status_kepesertaan;

        return [
            (string) ($kk?->nomor_kk ?? ''),
            (string) ($penduduk?->nik ?? ''),
            (string) ($penduduk?->nama_lengkap ?? ''),
            (string) ($kk?->dusun ?? ''),
            $jenisVal,
            $jenisLabel,
            (string) $item->bentuk_nominal,
            (int) $item->tahun_anggaran,
            (string) $item->tahap_penyaluran,
            $ekonomiVal,
            $statusVal,
            $item->keterangan,
        ];
    }
}
