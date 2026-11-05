@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Pengantar SKCK')
@section('judul_surat', 'SURAT PENGANTAR CATATAN KEPOLISIAN (SKCK)')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Berdasarkan catatan administrasi kependudukan Desa {{ $profilDesa->nama_desa ?? 'Tamarenja' }}, warga yang bersangkutan berkelakuan baik di lingkungan masyarakat ({{ $dk['catatan_kelakuan'] ?? 'Berkelakuan baik dan tidak pernah terlibat tindak pidana' }}). Surat pengantar ini diberikan untuk keperluan pengurusan SKCK dengan tujuan: <strong>{{ $dk['tujuan_skck'] ?? $surat->keperluan }}</strong>.
    </p>
@endsection
