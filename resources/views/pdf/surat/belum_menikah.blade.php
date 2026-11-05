@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Belum Menikah')
@section('judul_surat', 'SURAT KETERANGAN BELUM MENIKAH')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Berdasarkan data kependudukan yang ada pada Kantor Pemerintah Desa {{ $profilDesa->nama_desa ?? 'Tamarenja' }}, nama tersebut di atas benar berstatus <strong>Belum Pernah Menikah (Jejaka / Gadis)</strong>: {{ $dk['pernyataan_belum_menikah'] ?? 'Belum pernah melangsungkan pernikahan baik secara resmi maupun adat.' }}
    </p>
@endsection
