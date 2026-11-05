@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Pengantar Kehilangan')
@section('judul_surat', 'SURAT PENGANTAR LAPORAN KEHILANGAN')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Berdasarkan keterangan yang bersangkutan, benar telah mengalami kehilangan barang/dokumen penting dengan rincian sebagai berikut:
    </p>
    <table class="biodata">
        <tr>
            <td class="label">Barang / Dokumen Hilang</td>
            <td class="sep">:</td>
            <td class="val"><strong>{{ $dk['barang_hilang'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Perkiraan Waktu Kehilangan</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['waktu_kehilangan'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Perkiraan Lokasi Kehilangan</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['lokasi_kehilangan'] ?? '-' }}</td>
        </tr>
    </table>
@endsection
