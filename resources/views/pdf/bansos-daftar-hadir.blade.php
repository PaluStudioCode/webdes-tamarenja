<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Hadir &amp; Tanda Terima Penyaluran Bansos TA {{ $tahun }}</title>
    <style>
        @page {
            margin: 12mm 15mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000000;
            margin: 0;
            padding: 0;
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
            margin: 0;
            font-size: 13.5pt;
            font-weight: normal;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .kop-kec {
            margin: 1px 0;
            font-size: 13.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .kop-desa {
            margin: 1px 0;
            font-size: 13.5pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .kop-alamat {
            margin: 2px 0 0 0;
            font-size: 9.5pt;
            font-weight: bold;
            line-height: 1.25;
        }
        .judul-dokumen {
            text-align: center;
            margin-bottom: 10px;
        }
        .judul-dokumen h3 {
            margin: 0;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .judul-dokumen p {
            margin: 3px 0 0 0;
            font-size: 10pt;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            font-size: 9.5pt;
        }
        table.data-table th,
        table.data-table td {
            border: 1px solid #000000;
            padding: 5px 6px;
            vertical-align: middle;
        }
        table.data-table th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .ttd-cell {
            height: 28px;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .footer-ttd {
            width: 100%;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        .footer-ttd td {
            vertical-align: top;
            text-align: center;
            width: 50%;
            font-size: 10.5pt;
        }
        .nama-pejabat {
            margin-top: 52px;
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    @php
        $rawAlamat = trim((string) ($profilDesa?->alamat_kantor ?? ''));
        $alamatKantor = ($rawAlamat !== '' && ! str_contains($rawAlamat, 'Jl. Trans Sulawesi'))
            ? $rawAlamat
            : 'Jl. Hi. Paggaru No. 80 Tamarenja Kode Pos 94353';
    @endphp
    <table class="kop-table">
        <tr>
            @if(!empty($logoBase64))
                <td class="kop-logo">
                    <img src="{{ $logoBase64 }}" alt="Logo Garuda">
                </td>
            @endif
            <td class="kop-text">
                <p class="kop-kab">PEMERINTAH KABUPATEN {{ strtoupper($profilDesa?->kabupaten ?? 'DONGGALA') }}</p>
                <p class="kop-kec">KECAMATAN {{ strtoupper($profilDesa?->kecamatan ?? 'SINDUE TOBATA') }}</p>
                <p class="kop-desa">DESA {{ strtoupper($profilDesa?->nama_desa ?? 'TAMARENJA') }}</p>
                <p class="kop-alamat">{{ $alamatKantor }}</p>
            </td>
        </tr>
    </table>

    <div class="judul-dokumen">
        <h3>DAFTAR HADIR &amp; TANDA TERIMA PENYALURAN BANTUAN SOSIAL (BANSOS)</h3>
        <p>
            TAHUN ANGGARAN {{ $tahun }}
            @if(!empty($filterLabelJenis))
                &bull; PROGRAM: {{ strtoupper($filterLabelJenis) }}
            @endif
            @if(!empty($filterTahap))
                &bull; PERIODE: {{ strtoupper($filterTahap) }}
            @endif
        </p>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 13%;">NIK Penerima</th>
                <th style="width: 13%;">Nomor KK</th>
                <th style="width: 18%;">Nama Lengkap Warga</th>
                <th style="width: 11%;">Dusun / RT-RW</th>
                <th style="width: 14%;">Program &amp; Tahap</th>
                <th style="width: 13%;">Bentuk / Nominal Bantuan</th>
                <th style="width: 14%;">Tanda Tangan / Cap Jempol Warga</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bansosList as $index => $row)
                @php
                    $nomor = $index + 1;
                    $penduduk = $row->penduduk;
                    $kk = $penduduk?->kartuKeluarga;
                    $jenisLabel = $row->jenis_bantuan instanceof \App\Enums\JenisBansos
                        ? $row->jenis_bantuan->label()
                        : strtoupper((string) $row->jenis_bantuan);
                @endphp
                <tr>
                    <td class="text-center">{{ $nomor }}</td>
                    <td class="text-center">{{ $penduduk?->nik ?? '-' }}</td>
                    <td class="text-center">{{ $kk?->nomor_kk ?? '-' }}</td>
                    <td><strong>{{ $penduduk?->nama_lengkap ?? '-' }}</strong></td>
                    <td class="text-center">
                        {{ $kk?->dusun ?? '-' }}
                        @if($kk?->rt || $kk?->rw)
                            <br><small>RT {{ $kk?->rt ?? '001' }}/RW {{ $kk?->rw ?? '001' }}</small>
                        @endif
                    </td>
                    <td>
                        {{ $jenisLabel }}<br>
                        <small>{{ $row->tahap_penyaluran }}</small>
                    </td>
                    <td><strong>{{ $row->bentuk_nominal }}</strong></td>
                    <td class="ttd-cell" style="text-align: {{ $nomor % 2 === 1 ? 'left' : 'center' }};">
                        {{ $nomor }}. ....................
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center" style="padding: 16px;">
                        Tidak terdapat data penerima bantuan sosial pada kriteria tahun/program ini.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="footer-ttd">
        <tr>
            <td>
                Mengetahui,<br>
                <strong>Kepala Desa {{ $profilDesa?->nama_desa ?? 'Tamarenja' }}</strong>
                <div class="nama-pejabat">
                    {{ $profilDesa?->nama_kepala_desa ?? 'Kepala Desa' }}
                </div>
                @if(!empty($profilDesa?->nip_kepala_desa))
                    <div>NIP. {{ $profilDesa->nip_kepala_desa }}</div>
                @endif
            </td>
            <td>
                {{ $profilDesa?->nama_desa ?? 'Tamarenja' }}, {{ now()->translatedFormat('d F Y') }}<br>
                <strong>Petugas / Pelaksana Penyaluran</strong>
                <div class="nama-pejabat">
                    {{ $profilDesa?->nama_sekdes ?? 'Sekretaris Desa' }}
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
