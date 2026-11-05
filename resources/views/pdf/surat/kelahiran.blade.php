@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Kelahiran')
@section('judul_surat', 'SURAT KETERANGAN KELAHIRAN')

@section('isi_surat')
    @php
        $dk = $surat->data_khusus ?? [];
        $warga = $surat->penduduk;
        $kk = $warga?->kartuKeluarga;
    @endphp
    <p class="paragraf">
        Berdasarkan laporan keluarga pada Kartu Keluarga Nomor <strong>{{ $kk?->nomor_kk ?? '-' }}</strong> ({{ $kk?->alamat ?? $kk?->alamat_lengkap }}, {{ $kk?->dusun }}), telah lahir seorang anak dengan rincian sebagai berikut:
    </p>
    <table class="biodata">
        <tr>
            <td class="label">Nama Lengkap Bayi</td>
            <td class="sep">:</td>
            <td class="val"><strong>{{ $dk['nama_bayi'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Jenis Kelamin</td>
            <td class="sep">:</td>
            <td class="val">{{ ($dk['jenis_kelamin_bayi'] ?? 'L') === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
        </tr>
        <tr>
            <td class="label">Hari, Tanggal Lahir</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['hari_lahir_bayi'] ?? '-' }}, {{ $dk['tanggal_lahir_bayi'] ?? '-' }} @if(!empty($dk['jam_lahir_bayi'])) (Pukul {{ $dk['jam_lahir_bayi'] }}) @endif</td>
        </tr>
        <tr>
            <td class="label">Tempat Kelahiran</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['tempat_lahir_bayi'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Anak Ke-</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['anak_ke'] ?? 1 }}</td>
        </tr>
        <tr>
            <td class="label">Nama Ayah Kandung</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['nama_ayah'] ?? '-' }} @if(!empty($dk['nik_ayah'])) (NIK: {{ $dk['nik_ayah'] }}) @endif</td>
        </tr>
        <tr>
            <td class="label">Nama Ibu Kandung</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['nama_ibu'] ?? '-' }} @if(!empty($dk['nik_ibu'])) (NIK: {{ $dk['nik_ibu'] }}) @endif</td>
        </tr>
    </table>
@endsection
