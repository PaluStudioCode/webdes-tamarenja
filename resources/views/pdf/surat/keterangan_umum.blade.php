@extends('pdf.layouts.surat-f4-portrait')

@php $dk = $surat->data_khusus ?? []; @endphp
@section('judul_dokumen', $dk['judul_keterangan'] ?? 'Surat Keterangan Umum')
@section('judul_surat', strtoupper($dk['judul_keterangan'] ?? 'SURAT KETERANGAN'))

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    <p class="paragraf">
        {{ $dk['isi_keterangan'] ?? '-' }}
    </p>
@endsection
