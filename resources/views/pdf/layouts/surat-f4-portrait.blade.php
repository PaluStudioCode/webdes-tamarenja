<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>@yield('judul_dokumen', 'Surat Resmi Desa') - {{ $surat->nomor_surat }}</title>
    <style>
        @page {
            margin: 18mm 20mm 18mm 20mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            color: #000000;
            line-height: 1.45;
        }
        .kop-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 4px double #000000;
            margin-bottom: 18px;
        }
        .kop-table td {
            padding-bottom: 6px;
        }
        .kop-logo {
            width: 108px;
            text-align: left;
            vertical-align: middle;
        }
        .kop-logo img {
            width: 98px;
            height: auto;
        }
        .kop-text {
            text-align: center;
            vertical-align: middle;
            padding-right: 24px;
        }
        .kop-kab {
            font-size: 14pt;
            font-weight: normal;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 0;
        }
        .kop-kec {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 1px 0;
        }
        .kop-desa {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.15;
            margin: 1px 0;
        }
        .kop-alamat {
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.25;
            margin: 3px 0 0 0;
        }
        .judul-surat {
            text-align: center;
            margin-bottom: 16px;
        }
        .judul-surat h1 {
            font-size: 12.5pt;
            font-weight: bold;
            text-transform: uppercase;
            text-decoration: underline;
            margin: 0 0 3px 0;
        }
        .judul-surat p {
            font-size: 11pt;
            margin: 0;
        }
        .paragraf {
            text-align: justify;
            text-indent: 28px;
            margin: 8px 0;
        }
        table.biodata {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0 12px 16px;
        }
        table.biodata td {
            padding: 2px 4px;
            vertical-align: top;
            font-size: 11pt;
        }
        table.biodata td.label {
            width: 34%;
        }
        table.biodata td.sep {
            width: 3%;
            text-align: center;
        }
        table.biodata td.val {
            width: 63%;
        }
        .ttd-footer {
            width: 100%;
            margin-top: 24px;
        }
        .ttd-footer td {
            vertical-align: top;
        }
        .qr-box {
            width: 48%;
            text-align: left;
            font-size: 8.5pt;
            color: #1e293b;
        }
        .qr-box img {
            width: 88px;
            height: 88px;
            border: 1px solid #cbd5e1;
            padding: 3px;
        }
        .ttd-box {
            width: 52%;
            text-align: center;
            font-size: 11pt;
        }
        .ttd-space {
            height: 60px;
        }
        .watermark-batal {
            border: 2px solid #dc2626;
            color: #dc2626;
            padding: 6px 10px;
            text-align: center;
            font-weight: bold;
            margin-bottom: 12px;
            text-transform: uppercase;
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
        $warga = $surat->penduduk;
        $kk = $warga?->kartuKeluarga;
        $dk = $surat->data_khusus ?? [];
        $statusVal = $surat->status instanceof \App\Enums\StatusSuratKeluar
            ? $surat->status->value
            : (string) $surat->status;
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

    @if($statusVal === 'dibatalkan')
        <div class="watermark-batal">
            DOKUMEN INI TELAH DIBATALKAN ({{ $surat->alasan_pembatalan }})
        </div>
    @endif

    <div class="judul-surat">
        <h1>@yield('judul_surat')</h1>
        <p>Nomor: {{ $surat->nomor_surat }}</p>
    </div>

    <p class="paragraf">
        Yang bertanda tangan di bawah ini {{ $surat->jabatan_penandatangan ?: 'Kepala Desa ' . $namaDesa }}, Kecamatan {{ $kecamatan }}, Kabupaten {{ $kabupaten }}, dengan ini menerangkan bahwa:
    </p>

    @yield('isi_surat')

    <p class="paragraf">
        Demikian surat keterangan ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya ({{ $surat->keperluan }}).
    </p>

    <table class="ttd-footer">
        <tr>
            <td class="qr-box">
                @if(!empty($qrSvgDataUri))
                    <img src="{{ $qrSvgDataUri }}" alt="QR Code Verifikasi Surat">
                    <div style="margin-top: 4px;">
                        <strong>Verifikasi Keaslian Dokumen:</strong><br>
                        Pindai QR Code ini untuk memeriksa keaslian surat pada Buku Agenda Resmi Pemerintah Desa {{ $namaDesa }}.
                    </div>
                @endif
            </td>
            <td class="ttd-box">
                <p>{{ $namaDesa }}, {{ ($surat->tanggal_surat ?? now())->translatedFormat('d F Y') }}</p>
                <p><strong>{{ $surat->jabatan_penandatangan ?: 'Kepala Desa ' . $namaDesa }}</strong></p>
                <div class="ttd-space"></div>
                <p><strong><u>{{ $surat->nama_penandatangan ?: ($profilDesa->nama_kepala_desa ?? 'Kepala Desa') }}</u></strong></p>
                @if(!empty($surat->nip_penandatangan))
                    <p>NIP/NIAP. {{ $surat->nip_penandatangan }}</p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
