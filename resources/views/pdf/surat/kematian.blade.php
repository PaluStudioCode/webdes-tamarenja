@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Kematian')
@section('judul_surat', 'SURAT KETERANGAN KEMATIAN')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Telah meninggal dunia pada:
    </p>
    <table class="biodata">
        <tr>
            <td class="label">Hari, Tanggal Meninggal</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['hari_meninggal'] ?? '-' }}, {{ $dk['tanggal_meninggal'] ?? '-' }} @if(!empty($dk['jam_meninggal'])) (Pukul {{ $dk['jam_meninggal'] }}) @endif</td>
        </tr>
        <tr>
            <td class="label">Tempat Meninggal</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['tempat_meninggal'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Penyebab Kematian</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['penyebab_meninggal'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Lokasi Pemakaman</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['lokasi_makam'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nama Pelapor</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['nama_pelapor'] ?? '-' }} ({{ $dk['hubungan_pelapor'] ?? '-' }})</td>
        </tr>
    </table>
@endsection
