@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Usaha')
@section('judul_surat', 'SURAT KETERANGAN USAHA')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Berdasarkan pengamatan kami dan data yang ada di Kantor Pemerintah Desa, nama tersebut di atas benar merupakan penduduk Desa {{ $profilDesa->nama_desa ?? 'Tamarenja' }} dan benar memiliki kegiatan usaha sebagai berikut:
    </p>
    <table class="biodata">
        <tr>
            <td class="label">Nama Usaha</td>
            <td class="sep">:</td>
            <td class="val"><strong>{{ $dk['nama_usaha'] ?? '-' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Jenis / Bidang Usaha</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['jenis_usaha'] ?? '-' }} ({{ $dk['bidang_usaha'] ?? '-' }})</td>
        </tr>
        <tr>
            <td class="label">Lama Usaha Berjalan</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['lama_usaha'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Alamat Tempat Usaha</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['alamat_usaha'] ?? '-' }}</td>
        </tr>
    </table>
@endsection
