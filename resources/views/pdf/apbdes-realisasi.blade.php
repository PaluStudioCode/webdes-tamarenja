<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Realisasi APBDes TA {{ $apbdes->tahun_anggaran }}</title>
    <style>
        @page {
            margin: 18mm 15mm 18mm 15mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 10pt;
            color: #0f172a;
            line-height: 1.3;
        }
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 4px double #000000;
            margin-bottom: 12px;
        }
        .kop-table td {
            padding-bottom: 6px;
        }
        .kop-logo {
            width: 105px;
            text-align: left;
            vertical-align: middle;
        }
        .kop-logo img {
            width: 92px;
            height: auto;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
            padding-right: 24px;
        }
        .kop-kab {
            font-size: 13.5pt;
            font-weight: normal;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 0;
        }
        .kop-kec {
            font-size: 13.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 1px 0;
        }
        .kop-desa {
            font-size: 13.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 1px 0;
        }
        .kop-alamat {
            font-size: 9.5pt;
            font-weight: bold;
            line-height: 1.25;
            margin: 2px 0 0 0;
        }
        .judul-blok {
            text-align: center;
            margin-bottom: 12px;
        }
        .judul-blok h1 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 3px 0;
        }
        .judul-blok p {
            font-size: 9.5pt;
            margin: 1px 0;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 14px;
        }
        table.data th, table.data td {
            border: 1px solid #1e293b;
            padding: 4px 5px;
        }
        table.data th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
        }
        .bg-group {
            background-color: #e2e8f0;
            font-weight: bold;
        }
        .bg-subtotal {
            background-color: #f8fafc;
            font-weight: bold;
        }
        .ttd-table {
            width: 100%;
            margin-top: 18px;
            font-size: 9.5pt;
        }
        .ttd-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .ttd-space {
            height: 55px;
        }
    </style>
</head>
<body>
    @php
        $namaDesa = $profilDesa->nama_desa ?? 'Tamarenja';
        $kecamatan = $profilDesa->kecamatan ?? 'Sindue Tobata';
        $kabupaten = $profilDesa->kabupaten ?? 'Donggala';
        $rawAlamat = trim((string) ($profilDesa->alamat_kantor ?? ''));
        $alamatKantor = ($rawAlamat !== '' && ! str_contains($rawAlamat, 'Jl. Trans Sulawesi'))
            ? $rawAlamat
            : 'Jl. Hi. Paggaru No. 80 Tamarenja Kode Pos 94353';
        $jenisVal = $apbdes->jenis_dokumen instanceof \App\Enums\JenisDokumenApbdes
            ? $apbdes->jenis_dokumen->value
            : (string) $apbdes->jenis_dokumen;
        $jenisLabel = $jenisVal === 'perubahan'
            ? 'APBDes Perubahan (P-APBDes)'
            : 'APBDes Awal (Murni)';

        $rp = fn ($v) => number_format((int) $v, 0, ',', '.');
        $pct = fn ($real, $pagu) => (int) $pagu > 0 ? number_format(((int) $real / (int) $pagu) * 100, 1, ',', '.') . '%' : '0,0%';

        $rincian = $apbdes->rincian;
        $pendapatan = $rincian->filter(fn ($r) => ($r->kelompok->value ?? $r->kelompok) === 'pendapatan');
        $belanja = $rincian->filter(fn ($r) => ($r->kelompok->value ?? $r->kelompok) === 'belanja');
        $penerimaan = $rincian->filter(fn ($r) => ($r->kelompok->value ?? $r->kelompok) === 'pembiayaan_penerimaan');
        $pengeluaran = $rincian->filter(fn ($r) => ($r->kelompok->value ?? $r->kelompok) === 'pembiayaan_pengeluaran');
    @endphp

    <table class="kop-table">
        <tr>
            @if(!empty($logoBase64))
                <td class="kop-logo">
                    <img src="{{ $logoBase64 }}" alt="Logo Garuda">
                </td>
            @endif
            <td class="kop-text">
                <p class="kop-kab">PEMERINTAH KABUPATEN {{ strtoupper($kabupaten) }}</p>
                <p class="kop-kec">KECAMATAN {{ strtoupper($kecamatan) }}</p>
                <p class="kop-desa">DESA {{ strtoupper($namaDesa) }}</p>
                <p class="kop-alamat">{{ $alamatKantor }}</p>
            </td>
        </tr>
    </table>

    <div class="judul-blok">
        <h1>LAPORAN REALISASI PELAKSANAAN APBDes</h1>
        <p><strong>TAHUN ANGGARAN {{ $apbdes->tahun_anggaran }} &mdash; {{ strtoupper($jenisLabel) }}</strong></p>
        @if($apbdes->nomor_perdes)
            <p>Nomor Perdes: {{ $apbdes->nomor_perdes }} @if($apbdes->tanggal_penetapan) ({{ $apbdes->tanggal_penetapan->format('d/m/Y') }}) @endif</p>
        @endif
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 30%;">Uraian Pos Anggaran</th>
                <th style="width: 12%;">Pagu (Rp)</th>
                <th style="width: 10%;">Tahap I (Rp)</th>
                <th style="width: 10%;">Tahap II (Rp)</th>
                <th style="width: 10%;">Tahap III (Rp)</th>
                <th style="width: 12%;">Total Realisasi (Rp)</th>
                <th style="width: 7%;">%</th>
            </tr>
        </thead>
        <tbody>
            <!-- 1. PENDAPATAN DESA -->
            <tr class="bg-group">
                <td class="text-center">I</td>
                <td colspan="7">PENDAPATAN DESA</td>
            </tr>
            @foreach($pendapatan as $idx => $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>{{ $row->nama_pos }}</td>
                    <td class="text-right">{{ $rp($row->pagu_anggaran) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_1) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_2) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_3) }}</td>
                    <td class="text-right font-bold">{{ $rp($row->total_realisasi) }}</td>
                    <td class="text-right">{{ $pct($row->total_realisasi, $row->pagu_anggaran) }}</td>
                </tr>
            @endforeach
            <tr class="bg-subtotal">
                <td></td>
                <td>JUMLAH PENDAPATAN DESA</td>
                <td class="text-right">{{ $rp($summary['total_pagu_pendapatan']) }}</td>
                <td class="text-right">{{ $rp($pendapatan->sum('realisasi_tahap_1')) }}</td>
                <td class="text-right">{{ $rp($pendapatan->sum('realisasi_tahap_2')) }}</td>
                <td class="text-right">{{ $rp($pendapatan->sum('realisasi_tahap_3')) }}</td>
                <td class="text-right">{{ $rp($summary['total_realisasi_pendapatan']) }}</td>
                <td class="text-right">{{ $pct($summary['total_realisasi_pendapatan'], $summary['total_pagu_pendapatan']) }}</td>
            </tr>

            <!-- 2. BELANJA DESA -->
            <tr class="bg-group">
                <td class="text-center">II</td>
                <td colspan="7">BELANJA DESA (5 BIDANG)</td>
            </tr>
            @foreach($belanja as $row)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>
                        {{ $row->nama_pos }}
                        @if($row->is_otomatis_modul_1)
                            <em>(Otomatis Modul 1)</em>
                        @endif
                    </td>
                    <td class="text-right">{{ $rp($row->pagu_anggaran) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_1) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_2) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_3) }}</td>
                    <td class="text-right font-bold">{{ $rp($row->total_realisasi) }}</td>
                    <td class="text-right">{{ $pct($row->total_realisasi, $row->pagu_anggaran) }}</td>
                </tr>
            @endforeach
            <tr class="bg-subtotal">
                <td></td>
                <td>JUMLAH BELANJA DESA</td>
                <td class="text-right">{{ $rp($summary['total_pagu_belanja']) }}</td>
                <td class="text-right">{{ $rp($belanja->sum('realisasi_tahap_1')) }}</td>
                <td class="text-right">{{ $rp($belanja->sum('realisasi_tahap_2')) }}</td>
                <td class="text-right">{{ $rp($belanja->sum('realisasi_tahap_3')) }}</td>
                <td class="text-right">{{ $rp($summary['total_realisasi_belanja']) }}</td>
                <td class="text-right">{{ $pct($summary['total_realisasi_belanja'], $summary['total_pagu_belanja']) }}</td>
            </tr>

            <!-- 3. PEMBIAYAAN DESA -->
            <tr class="bg-group">
                <td class="text-center">III</td>
                <td colspan="7">PEMBIAYAAN DESA</td>
            </tr>
            @foreach($penerimaan as $row)
                <tr>
                    <td class="text-center">3.1</td>
                    <td>Penerimaan Pembiayaan: {{ $row->nama_pos }}</td>
                    <td class="text-right">{{ $rp($row->pagu_anggaran) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_1) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_2) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_3) }}</td>
                    <td class="text-right font-bold">{{ $rp($row->total_realisasi) }}</td>
                    <td class="text-right">{{ $pct($row->total_realisasi, $row->pagu_anggaran) }}</td>
                </tr>
            @endforeach
            @foreach($pengeluaran as $row)
                <tr>
                    <td class="text-center">3.2</td>
                    <td>Pengeluaran Pembiayaan: {{ $row->nama_pos }}</td>
                    <td class="text-right">{{ $rp($row->pagu_anggaran) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_1) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_2) }}</td>
                    <td class="text-right">{{ $rp($row->realisasi_tahap_3) }}</td>
                    <td class="text-right font-bold">{{ $rp($row->total_realisasi) }}</td>
                    <td class="text-right">{{ $pct($row->total_realisasi, $row->pagu_anggaran) }}</td>
                </tr>
            @endforeach
            <tr class="bg-subtotal">
                <td></td>
                <td>PEMBIAYAAN NETTO (Penerimaan &minus; Pengeluaran)</td>
                <td class="text-right">{{ $rp($summary['pembiayaan_netto_pagu']) }}</td>
                <td colspan="3" class="text-center">&mdash;</td>
                <td class="text-right">{{ $rp($summary['pembiayaan_netto_realisasi']) }}</td>
                <td class="text-center">&mdash;</td>
            </tr>
            <tr class="bg-subtotal">
                <td></td>
                <td>SISA ANGGARAN BELANJA (Pagu Belanja &minus; Realisasi Belanja)</td>
                <td colspan="4" class="text-center">&mdash;</td>
                <td class="text-right">{{ $rp($summary['sisa_anggaran_belanja']) }}</td>
                <td class="text-right">{{ number_format((float) $summary['persentase_serapan_belanja'], 1, ',', '.') }}%</td>
            </tr>
        </tbody>
    </table>

    <table class="ttd-table">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p><strong>Kepala Desa {{ $namaDesa }}</strong></p>
                <div class="ttd-space"></div>
                <p><strong><u>{{ $profilDesa->nama_kepala_desa ?? 'Kepala Desa' }}</u></strong></p>
                @if(!empty($profilDesa->nip_kepala_desa))
                    <p>NIP/NIAP. {{ $profilDesa->nip_kepala_desa }}</p>
                @endif
            </td>
            <td>
                <p>{{ $namaDesa }}, {{ now()->translatedFormat('d F Y') }}</p>
                <p><strong>Sekretaris Desa {{ $namaDesa }}</strong></p>
                <div class="ttd-space"></div>
                <p><strong><u>{{ $profilDesa->nama_sekdes ?? 'Sekretaris Desa' }}</u></strong></p>
                @if(!empty($profilDesa->nip_sekdes))
                    <p>NIP/NIAP. {{ $profilDesa->nip_sekdes }}</p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
