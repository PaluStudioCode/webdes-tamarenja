@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Janda / Duda')
@section('judul_surat', 'SURAT KETERANGAN JANDA / DUDA')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php
        $dk = $surat->data_khusus ?? [];
        $statusCeraiLabel = ($dk['status_cerai'] ?? '') === 'cerai_mati' ? 'Cerai Mati' : 'Cerai Hidup';
    @endphp
    <p class="paragraf">
        Nama tersebut di atas benar merupakan penduduk Desa {{ $profilDesa->nama_desa ?? 'Tamarenja' }} yang saat ini berstatus <strong>Janda / Duda ({{ $statusCeraiLabel }})</strong> dari mantan pasangan bernama <strong>{{ $dk['nama_mantan_pasangan'] ?? '-' }}</strong> sejak <strong>{{ $dk['sejak_tanggal_tahun'] ?? '-' }}</strong>.
    </p>
@endsection
