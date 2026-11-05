@php
    $warga = $surat->penduduk;
    $kk = $warga?->kartuKeluarga;
@endphp
<table class="biodata">
    <tr>
        <td class="label">Nama Lengkap</td>
        <td class="sep">:</td>
        <td class="val"><strong>{{ $warga?->nama_lengkap }}</strong></td>
    </tr>
    <tr>
        <td class="label">NIK</td>
        <td class="sep">:</td>
        <td class="val">{{ $warga?->nik }}</td>
    </tr>
    <tr>
        <td class="label">Nomor Kartu Keluarga</td>
        <td class="sep">:</td>
        <td class="val">{{ $kk?->nomor_kk ?? '-' }}</td>
    </tr>
    <tr>
        <td class="label">Tempat, Tanggal Lahir</td>
        <td class="sep">:</td>
        <td class="val">{{ $warga?->tempat_lahir }}, {{ $warga?->tanggal_lahir?->translatedFormat('d F Y') }}</td>
    </tr>
    <tr>
        <td class="label">Jenis Kelamin</td>
        <td class="sep">:</td>
        <td class="val">{{ $warga?->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan' }}</td>
    </tr>
    <tr>
        <td class="label">Agama</td>
        <td class="sep">:</td>
        <td class="val">{{ $warga?->agama }}</td>
    </tr>
    <tr>
        <td class="label">Pekerjaan</td>
        <td class="sep">:</td>
        <td class="val">{{ $warga?->pekerjaan }}</td>
    </tr>
    <tr>
        <td class="label">Alamat Domisili</td>
        <td class="sep">:</td>
        <td class="val">{{ $kk?->alamat ?? $kk?->alamat_lengkap }} ({{ $kk?->dusun }}, RT {{ $kk?->rt }} / RW {{ $kk?->rw }})</td>
    </tr>
</table>
