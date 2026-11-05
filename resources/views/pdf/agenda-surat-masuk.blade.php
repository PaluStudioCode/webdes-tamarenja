<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Buku Agenda Surat Masuk TA {{ $tahun }}</title>
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
            border-bottom: 3px double #000;
            padding-bottom: 6px;
            margin-bottom: 10px;
        }
        .kop-logo {
            width: 65px;
            text-align: center;
            vertical-align: middle;
        }
        .kop-logo img {
            width: 52px;
            height: auto;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
        }
        .kop-kab {
            font-size: 11.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .kop-desa {
            font-size: 13.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 2px 0;
        }
        .kop-alamat {
            font-size: 8.5pt;
            margin: 0;
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
        .text-center {
            text-align: center;
        }
        .font-bold {
            font-weight: bold;
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
        $alamatKantor = $profilDesa->alamat_kantor ?? 'Jl. Trans Sulawesi Desa Tamarenja';
    @endphp

    <table class="kop-table">
        <tr>
            @if(!empty($logoBase64))
                <td class="kop-logo">
                    <img src="{{ $logoBase64 }}" alt="Logo Desa">
                </td>
            @endif
            <td class="kop-text">
                <p class="kop-kab">PEMERINTAH KABUPATEN {{ strtoupper($kabupaten) }}</p>
                <p class="kop-kab">KECAMATAN {{ strtoupper($kecamatan) }}</p>
                <p class="kop-desa">PEMERINTAH DESA {{ strtoupper($namaDesa) }}</p>
                <p class="kop-alamat">{{ $alamatKantor }}</p>
            </td>
        </tr>
    </table>

    <div class="judul-blok">
        <h1>BUKU AGENDA SURAT MASUK PEMERINTAH DESA {{ strtoupper($namaDesa) }}</h1>
        <p><strong>TAHUN ANGGARAN {{ $tahun }}</strong></p>
    </div>

    <table class="data">
        <thead>
            <tr>
                <th style="width: 6%;">No Urut</th>
                <th style="width: 10%;">Tanggal Terima</th>
                <th style="width: 18%;">Nomor &amp; Tanggal Surat Masuk</th>
                <th style="width: 18%;">Instansi / Pihak Pengirim</th>
                <th style="width: 26%;">Perihal / Ringkasan Isi Surat</th>
                <th style="width: 22%;">Tujuan &amp; Catatan Disposisi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($suratMasukList as $item)
                <tr>
                    <td class="text-center font-bold">{{ str_pad((string) $item->nomor_urut_agenda, 3, '0', STR_PAD_LEFT) }}</td>
                    <td class="text-center">{{ $item->tanggal_terima?->format('d/m/Y') }}</td>
                    <td>
                        <div class="font-bold">{{ $item->nomor_surat_masuk }}</div>
                        @if($item->tanggal_surat)
                            <div style="font-size: 7.5pt; color: #334155;">Tgl Surat: {{ $item->tanggal_surat->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td>{{ $item->pengirim }}</td>
                    <td>{{ $item->perihal }}</td>
                    <td>
                        @if($item->tujuan_disposisi)
                            <div class="font-bold">Kepada: {{ $item->tujuan_disposisi }}</div>
                        @endif
                        @if($item->catatan_disposisi)
                            <div>{{ $item->catatan_disposisi }}</div>
                        @endif
                        @if(!$item->tujuan_disposisi && !$item->catatan_disposisi)
                            <span>&mdash;</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center">Belum ada catatan pada Buku Agenda Surat Masuk Tahun {{ $tahun }}.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="ttd-table">
        <tr>
            <td>
                <p>Mengetahui,</p>
                <p><strong>Kepala Desa {{ $namaDesa }}</strong></p>
                <div class="ttd-space"></div>
                <p><strong><u>{{ $profilDesa->nama_kepala_desa ?? 'Kepala Desa' }}</u></strong></p>
            </td>
            <td>
                <p>{{ $namaDesa }}, {{ now()->translatedFormat('d F Y') }}</p>
                <p><strong>Sekretaris Desa {{ $namaDesa }}</strong></p>
                <div class="ttd-space"></div>
                <p><strong><u>{{ $profilDesa->nama_sekdes ?? 'Sekretaris Desa' }}</u></strong></p>
            </td>
        </tr>
    </table>
</body>
</html>
