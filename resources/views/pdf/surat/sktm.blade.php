@extends('pdf.layouts.surat-f4-portrait')

@section('judul_dokumen', 'Surat Keterangan Tidak Mampu')
@section('judul_surat', 'SURAT KETERANGAN TIDAK MAMPU (SKTM)')

@section('isi_surat')
    @include('pdf.surat._biodata_warga')

    @php $dk = $surat->data_khusus ?? []; @endphp
    <p class="paragraf">
        Nama tersebut di atas benar merupakan penduduk Desa {{ $profilDesa->nama_desa ?? 'Tamarenja' }} yang tergolong keluarga kurang mampu / prasejahtera dengan rincian kondisi sosial ekonomi sebagai berikut:
    </p>
    <table class="biodata">
        <tr>
            <td class="label">Penghasilan Rata-Rata</td>
            <td class="sep">:</td>
            <td class="val">Rp {{ number_format((int) ($dk['penghasilan_rata_rata'] ?? 0), 0, ',', '.') }} / Bulan</td>
        </tr>
        <tr>
            <td class="label">Jumlah Tanggungan Keluarga</td>
            <td class="sep">:</td>
            <td class="val">{{ $dk['jumlah_tanggungan'] ?? 0 }} Orang</td>
        </tr>
        @if(!empty($dk['nama_tanggungan']))
            <tr>
                <td class="label">Nama Anggota Tanggungan</td>
                <td class="sep">:</td>
                <td class="val">{{ $dk['nama_tanggungan'] }}</td>
            </tr>
        @endif
    </table>
@endsection
