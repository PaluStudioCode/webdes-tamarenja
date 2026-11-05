@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Domisili')
@section('judul_surat', 'SURAT KETERANGAN DOMISILI')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Nama tersebut di atas benar berdomisili dan bertempat tinggal di wilayah Desa {{ $profilDesa->nama_desa ?? 'Tamarenja' }} sejak tahun <strong>{{ $dk['sejak_tahun'] ?? '-' }}</strong> dengan status tempat tinggal: <strong>{{ $dk['status_tempat_tinggal'] ?? '-' }}</strong>.
    </p>
@endsection
