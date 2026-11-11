<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Rekapitulasi Proyek Pembangunan Fisik TA {{ $tahun }}</title>
    <style>
        @page {
            margin: 15mm 14mm 15mm 14mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 9.5pt;
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
            padding-right: 40px;
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
            margin-bottom: 10px;
        }
        .judul-blok h1 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 2px 0;
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
            vertical-align: top;
        }
        table.data th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
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
        .bg-subtotal {
            background-color: #f8fafc;
            font-weight: bold;
        }
        .meta-sub {
            font-size: 7.5pt;
            color: #334155;
            margin-top: 2px;
        }
        .ttd-table {
            width: 100%;
            margin-top: 16px;
            font-size: 9.5pt;
        }
        .ttd-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .ttd-space {
            height: 52px;
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

        $rp = fn ($v) => number_format((int) $v, 0, ',', '.');
        $pct = fn ($real, $pagu) => (int) $pagu > 0
            ? number_format(((int) $real / (int) $pagu) * 100, 1, ',', '.') . '%'
            : '0,0%';

        $totalPagu = (int) $proyekList->sum('pagu_anggaran');
        $totalRealisasi = (int) $proyekList->sum('realisasi_anggaran');
        $avgFisik = $proyekList->count() > 0 ? round((float) $proyekList->avg('progres_fisik'), 1) : 0.0;
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
        <h1>LAPORAN REKAPITULASI PELAKSANAAN PEMBANGUNAN FISIK DESA</h1>
        <p><strong>TAHUN ANGGARAN {{ $tahun }}</strong></p>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 3%;">No</th>
                <th style="width: 22%;">Nama Kegiatan &amp; Volume Fisik</th>
                <th style="width: 10%;">Lokasi Dusun</th>
                <th style="width: 11%;">Sumber Dana</th>
                <th style="width: 13%;">Pelaksana Kegiatan &amp; Waktu</th>
                <th style="width: 11%;">Pagu Anggaran (Rp)</th>
                <th style="width: 11%;">Realisasi Dana (Rp)</th>
                <th style="width: 5%;">Serapan</th>
                <th style="width: 5%;">Fisik</th>
                <th style="width: 9%;">Status &amp; Ket.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($proyekList as $proyek)
                @php
                    $sumberLabel = $proyek->sumber_dana instanceof \App\Enums\SumberDanaProyek
                        ? $proyek->sumber_dana->label()
                        : (string) $proyek->sumber_dana;
                    $statusLabel = $proyek->status_proyek instanceof \App\Enums\StatusProyek
                        ? $proyek->status_proyek->label()
                        : (string) $proyek->status_proyek;
                @endphp
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td>
                        <div class="font-bold">{{ $proyek->nama_kegiatan }}</div>
                        @if($proyek->volume_fisik)
                            <div class="meta-sub">Volume: {{ $proyek->volume_fisik }}</div>
                        @endif
                    </td>
                    <td>{{ $proyek->lokasi_dusun }}</td>
                    <td>{{ $sumberLabel }}</td>
                    <td>
                        <div>{{ $proyek->pelaksana_kegiatan ?: 'TPK Desa' }}</div>
                        @if($proyek->waktu_pelaksanaan)
                            <div class="meta-sub">{{ $proyek->waktu_pelaksanaan }}</div>
                        @endif
                    </td>
                    <td class="text-right">{{ $rp($proyek->pagu_anggaran) }}</td>
                    <td class="text-right font-bold">{{ $rp($proyek->realisasi_anggaran) }}</td>
                    <td class="text-right">{{ $pct($proyek->realisasi_anggaran, $proyek->pagu_anggaran) }}</td>
                    <td class="text-center font-bold">{{ (int) $proyek->progres_fisik }}%</td>
                    <td>
                        <div>{{ $statusLabel }}</div>
                        @if($proyek->catatan_kendala)
                            <div class="meta-sub">{{ $proyek->catatan_kendala }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">Belum ada data kegiatan pembangunan fisik pada Tahun Anggaran {{ $tahun }}.</td>
                </tr>
            @endforelse
            <tr class="bg-subtotal">
                <td colspan="5" class="text-right">TOTAL ANGGARAN &amp; RATA-RATA CAPAIAN PEMBANGUNAN FISIK</td>
                <td class="text-right">{{ $rp($totalPagu) }}</td>
                <td class="text-right">{{ $rp($totalRealisasi) }}</td>
                <td class="text-right">{{ $pct($totalRealisasi, $totalPagu) }}</td>
                <td class="text-center">{{ number_format($avgFisik, 1, ',', '.') }}%</td>
                <td></td>
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
                <p><strong>Pelaksana Kegiatan (TPK Desa {{ $namaDesa }})</strong></p>
                <div class="ttd-space"></div>
                <p><strong><u>{{ $proyekList->first()?->pelaksana_kegiatan ?: 'Ketua TPK Desa ' . $namaDesa }}</u></strong></p>
            </td>
        </tr>
    </table>
</body>
</html>
