<?php

namespace App\Http\Controllers\Publik;

use App\Enums\JenisDokumenApbdes;
use App\Enums\KategoriKegiatan;
use App\Enums\KelompokPosApbdes;
use App\Enums\StatusKependudukan;
use App\Enums\StatusProyek;
use App\Enums\SumberDanaProyek;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pengaturan\SaveDokumenPpidRequest;
use App\Http\Requests\Pengaturan\SavePerangkatDesaRequest;
use App\Models\Apbdes;
use App\Models\ApbdesRincian;
use App\Models\DokumenPpid;
use App\Models\KartuKeluarga;
use App\Models\KegiatanDesa;
use App\Models\Penduduk;
use App\Models\PerangkatDesa;
use App\Models\ProfilDesa;
use App\Models\ProyekPembangunan;
use App\Services\ApbdesService;
use App\Services\PdfService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalPublikController extends Controller
{
    public function __construct(
        private readonly ApbdesService $apbdesService,
    ) {}

    /**
     * 1. Beranda Publik (/)
     * Sorotan ringkas (sambutan singkat Kades, 3 metrik APBDes tahun aktif,
     * maksimal 3 proyek terbaru, maksimal 3 kegiatan terbaru, & CTA Layanan Mandiri).
     */
    public function beranda(): Response
    {
        $profil = ProfilDesa::singleton();
        $tahunAktif = (int) ($profil?->tahun_anggaran_aktif ?? date('Y'));

        // Ambil APBDes aktif tahun berjalan (prioritas Perubahan > Murni)
        $apbdesDoc = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', $tahunAktif)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->first()
            ?? Apbdes::query()
                ->with('rincian')
                ->where('tahun_anggaran', $tahunAktif)
                ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
                ->first();

        $summary = $apbdesDoc
            ? $this->apbdesService->calculateSummaryAndBalance($apbdesDoc)
            : [
                'total_pagu_pendapatan' => 0,
                'total_pagu_belanja' => 0,
                'total_realisasi_belanja' => 0,
                'persentase_serapan_belanja' => 0.0,
            ];

        // Maksimal 3 proyek terbaru
        $proyekSorotan = ProyekPembangunan::query()
            ->orderByDesc('tahun_anggaran')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (ProyekPembangunan $p) => $this->transformProyekPublik($p))
            ->values()
            ->all();

        // Maksimal 3 kegiatan berstatus published terbaru
        $kegiatanSorotan = KegiatanDesa::query()
            ->published()
            ->with('foto')
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (KegiatanDesa $k) => $this->transformKegiatanCard($k))
            ->values()
            ->all();

        return Inertia::render('Publik/Beranda', [
            'tahun_aktif' => $tahunAktif,
            'sambutan' => [
                'nama_kepala_desa' => $profil?->nama_kepala_desa ?? 'Kepala Desa Tamarenja',
                'sambutan_kepala_desa' => $profil?->sambutan_kepala_desa ?? 'Selamat datang di Portal Resmi Pemerintah Desa Tamarenja.',
                'foto_kepala_desa_url' => $profil?->foto_kepala_desa_path
                    ? Storage::disk('public')->url($profil->foto_kepala_desa_path)
                    : null,
            ],
            'sorotan_apbdes' => [
                'tahun_anggaran' => $tahunAktif,
                'jenis_dokumen' => $apbdesDoc
                    ? ($apbdesDoc->jenis_dokumen instanceof JenisDokumenApbdes ? $apbdesDoc->jenis_dokumen->value : (string) $apbdesDoc->jenis_dokumen)
                    : 'murni',
                'total_pagu_pendapatan' => (int) ($summary['total_pagu_pendapatan'] ?? 0),
                'total_pagu_belanja' => (int) ($summary['total_pagu_belanja'] ?? 0),
                'total_realisasi_belanja' => (int) ($summary['total_realisasi_belanja'] ?? 0),
                'persentase_serapan_belanja' => (float) ($summary['persentase_serapan_belanja'] ?? 0.0),
            ],
            'proyek_terbaru' => $proyekSorotan,
            'kegiatan_terbaru' => $kegiatanSorotan,
        ]);
    }

    /**
     * 2. Profil Desa & Struktur Organisasi SOTK (/profil)
     * Halaman terpadu bernavigasi seksi/tab untuk Identitas, Visi-Misi, Sejarah, Potensi, dan SOTK + BPD aktif.
     */
    public function profil(): Response
    {
        $profil = ProfilDesa::singleton();

        $perangkatAktif = PerangkatDesa::query()
            ->aktif()
            ->orderBy('urutan')
            ->orderBy('id')
            ->get()
            ->map(fn (PerangkatDesa $p) => [
                'id' => $p->id,
                'nama_lengkap' => $p->nama_lengkap,
                'jabatan' => $p->jabatan,
                'kelompok_sotk' => $p->kelompok_sotk,
                'kelompok_label' => SavePerangkatDesaRequest::KELOMPOK_OPTIONS[$p->kelompok_sotk] ?? $p->kelompok_sotk,
                'nip_niap' => $p->nip_niap,
                'foto_url' => $p->foto_path ? Storage::disk('public')->url($p->foto_path) : null,
                'urutan' => (int) $p->urutan,
            ])
            ->values();

        return Inertia::render('Publik/Profil', [
            'profil' => [
                'nama_desa' => $profil?->nama_desa ?? 'Tamarenja',
                'kecamatan' => $profil?->kecamatan ?? 'Sindue Tobata',
                'kabupaten' => $profil?->kabupaten ?? 'Donggala',
                'provinsi' => $profil?->provinsi ?? 'Sulawesi Tengah',
                'kode_wilayah' => $profil?->kode_wilayah,
                'daftar_dusun' => array_values($profil?->daftar_dusun ?? []),
                'alamat_kantor' => $profil?->alamat_kantor,
                'jam_pelayanan' => $profil?->jam_pelayanan,
                'email_desa' => $profil?->email_desa,
                'no_wa_pelayanan' => $profil?->no_wa_pelayanan,
                'nama_kepala_desa' => $profil?->nama_kepala_desa,
                'nip_kepala_desa' => $profil?->nip_kepala_desa,
                'foto_kepala_desa_url' => $profil?->foto_kepala_desa_path
                    ? Storage::disk('public')->url($profil->foto_kepala_desa_path)
                    : null,
                'sambutan_kepala_desa' => $profil?->sambutan_kepala_desa,
                'nama_sekdes' => $profil?->nama_sekdes,
                'nip_sekdes' => $profil?->nip_sekdes,
                'visi' => $profil?->visi,
                'misi' => array_values($profil?->misi ?? []),
                'sejarah_singkat' => $profil?->sejarah_singkat,
                'potensi_unggulan' => $profil?->potensi_unggulan,
            ],
            'sotk_pemerintah' => $perangkatAktif
                ->where('kelompok_sotk', '!=', 'bpd')
                ->values()
                ->all(),
            'sotk_bpd' => $perangkatAktif
                ->where('kelompok_sotk', 'bpd')
                ->values()
                ->all(),
        ]);
    }

    /**
     * 3. Transparansi APBDes Multi-Tahun & Generator Baliho (/transparansi/apbdes)
     */
    public function transparansiApbdes(Request $request): Response
    {
        $profil = ProfilDesa::singleton();
        $tahunDefault = (int) ($profil?->tahun_anggaran_aktif ?? date('Y'));
        $tahun = (int) $request->query('tahun', $tahunDefault);
        $requestedJenis = trim((string) $request->query('jenis', ''));

        $murni = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', $tahun)
            ->where('jenis_dokumen', JenisDokumenApbdes::Murni)
            ->first();

        $perubahan = Apbdes::query()
            ->with('rincian')
            ->where('tahun_anggaran', $tahun)
            ->where('jenis_dokumen', JenisDokumenApbdes::Perubahan)
            ->first();

        if ($requestedJenis === 'murni' && $murni) {
            $activeDoc = $murni;
        } elseif ($requestedJenis === 'perubahan' && $perubahan) {
            $activeDoc = $perubahan;
        } else {
            $activeDoc = $perubahan ?? $murni;
        }

        $transformedMurni = $murni ? $this->transformApbdesDoc($murni) : $this->emptyApbdesDoc($tahun, 'murni');
        $transformedPerubahan = $perubahan ? $this->transformApbdesDoc($perubahan) : null;
        $transformedAktif = $activeDoc
            ? $this->transformApbdesDoc($activeDoc)
            : $transformedMurni;

        $ringkasan = $activeDoc
            ? $this->apbdesService->calculateSummaryAndBalance($activeDoc)
            : $this->emptyApbdesSummary();

        $availableYears = Apbdes::query()
            ->select('tahun_anggaran')
            ->distinct()
            ->orderByDesc('tahun_anggaran')
            ->pluck('tahun_anggaran')
            ->map(fn ($y) => (int) $y)
            ->all();

        $availableYears = array_values(array_unique([$tahunDefault, $tahun, ...$availableYears]));
        rsort($availableYears);

        return Inertia::render('Publik/TransparansiApbdes', [
            'tahun_anggaran' => $tahun,
            'available_tahun' => $availableYears,
            'has_perubahan' => $perubahan !== null,
            'dokumen_aktif' => $transformedAktif,
            'dokumen_murni' => $transformedMurni,
            'dokumen_perubahan' => $transformedPerubahan,
            'ringkasan' => $ringkasan,
        ]);
    }

    /**
     * 4. Katalog Proyek Pembangunan Fisik Publik (/transparansi/pembangunan)
     */
    public function transparansiPembangunan(Request $request): Response
    {
        $profil = ProfilDesa::singleton();
        $tahunDefault = (int) ($profil?->tahun_anggaran_aktif ?? date('Y'));
        $tahun = (int) $request->query('tahun', $tahunDefault);
        $dusunFilter = trim((string) $request->query('dusun', ''));

        $proyekList = ProyekPembangunan::query()
            ->where('tahun_anggaran', $tahun)
            ->when($dusunFilter !== '', fn ($q) => $q->where('lokasi_dusun', $dusunFilter))
            ->orderByDesc('progres_fisik')
            ->orderBy('id')
            ->get()
            ->map(fn (ProyekPembangunan $p) => $this->transformProyekPublik($p))
            ->values()
            ->all();

        $availableYears = ProyekPembangunan::query()
            ->select('tahun_anggaran')
            ->distinct()
            ->orderByDesc('tahun_anggaran')
            ->pluck('tahun_anggaran')
            ->map(fn ($y) => (int) $y)
            ->all();

        $availableYears = array_values(array_unique([$tahunDefault, $tahun, ...$availableYears]));
        rsort($availableYears);

        return Inertia::render('Publik/TransparansiPembangunan', [
            'tahun_anggaran' => $tahun,
            'available_tahun' => $availableYears,
            'daftar_dusun' => array_values($profil?->daftar_dusun ?? []),
            'filters' => [
                'dusun' => $dusunFilter,
            ],
            'proyek' => $proyekList,
        ]);
    }

    /**
     * Endpoint Publik Cetak Rekapitulasi Proyek Pembangunan PDF F4 Landscape (/transparansi/pembangunan/cetak-pdf)
     * Sesuai matriks hak akses PRD 03 Seksi 2.3 (publik diizinkan mengunduh PDF rekap Modul 1).
     */
    public function cetakRekapPembangunanPdf(Request $request): HttpResponse
    {
        $profilDesa = ProfilDesa::singleton();
        $defaultTahun = (int) ($profilDesa?->tahun_anggaran_aktif ?? date('Y'));
        $tahun = (int) $request->query('tahun', $defaultTahun);

        $proyekList = ProyekPembangunan::query()
            ->where('tahun_anggaran', $tahun)
            ->orderBy('id')
            ->get();

        $logoBase64 = null;
        if ($profilDesa && ! empty($profilDesa->logo_desa_path) && Storage::disk('public')->exists($profilDesa->logo_desa_path)) {
            $bytes = Storage::disk('public')->get($profilDesa->logo_desa_path);
            if ($bytes) {
                $logoBase64 = 'data:image/png;base64,' . base64_encode($bytes);
            }
        }

        $filename = sprintf('Rekapitulasi_Proyek_Pembangunan_TA_%d.pdf', $tahun);

        $pdf = PdfService::createF4Pdf('pdf.proyek-rekap', [
            'tahun' => $tahun,
            'proyekList' => $proyekList,
            'profilDesa' => $profilDesa,
            'logoBase64' => $logoBase64,
        ], 'landscape');

        return $pdf->download($filename);
    }

    /**
     * 5. Statistik Penduduk Agregat Patuh UU PDP (/transparansi/statistik-penduduk)
     * 100% tanpa NIK, Nomor KK, Tanggal Lahir, atau nama warga individu.
     * Menggunakan whereBetween berbasis Carbon agar ramah indeks tanggal_lahir.
     */
    public function statistikPenduduk(): Response
    {
        $today = Carbon::today();

        $totalPendudukAktif = Penduduk::query()->aktif()->count();
        $totalKkAktif = KartuKeluarga::query()->aktif()->count();

        $lakiLakiCount = Penduduk::query()->aktif()->where('jenis_kelamin', 'L')->count();
        $perempuanCount = Penduduk::query()->aktif()->where('jenis_kelamin', 'P')->count();

        // 5 Kelompok Usia Baku berbasis rentang tanggal lahir (whereBetween / <=) untuk optimalisasi indeks
        // 1. 0–4 thn (Balita): lahir > today - 5 years
        $usia0To4 = Penduduk::query()
            ->aktif()
            ->whereBetween('tanggal_lahir', [
                $today->copy()->subYears(5)->addDay()->toDateString(),
                $today->toDateString(),
            ])
            ->count();

        // 2. 5–14 thn (Anak): lahir antara (today - 15 years + 1 day) s/d (today - 5 years)
        $usia5To14 = Penduduk::query()
            ->aktif()
            ->whereBetween('tanggal_lahir', [
                $today->copy()->subYears(15)->addDay()->toDateString(),
                $today->copy()->subYears(5)->toDateString(),
            ])
            ->count();

        // 3. 15–24 thn (Pemuda): lahir antara (today - 25 years + 1 day) s/d (today - 15 years)
        $usia15To24 = Penduduk::query()
            ->aktif()
            ->whereBetween('tanggal_lahir', [
                $today->copy()->subYears(25)->addDay()->toDateString(),
                $today->copy()->subYears(15)->toDateString(),
            ])
            ->count();

        // 4. 25–59 thn (Produktif): lahir antara (today - 60 years + 1 day) s/d (today - 25 years)
        $usia25To59 = Penduduk::query()
            ->aktif()
            ->whereBetween('tanggal_lahir', [
                $today->copy()->subYears(60)->addDay()->toDateString(),
                $today->copy()->subYears(25)->toDateString(),
            ])
            ->count();

        // 5. 60+ thn (Lansia): lahir <= (today - 60 years)
        $usia60Plus = Penduduk::query()
            ->aktif()
            ->whereDate('tanggal_lahir', '<=', $today->copy()->subYears(60)->toDateString())
            ->count();

        $kelompokUsia = [
            ['kode' => '0_4', 'label' => '0–4 thn (Balita)', 'jumlah' => $usia0To4],
            ['kode' => '5_14', 'label' => '5–14 thn (Anak)', 'jumlah' => $usia5To14],
            ['kode' => '15_24', 'label' => '15–24 thn (Pemuda)', 'jumlah' => $usia15To24],
            ['kode' => '25_59', 'label' => '25–59 thn (Produktif)', 'jumlah' => $usia25To59],
            ['kode' => '60_plus', 'label' => '60+ thn (Lansia)', 'jumlah' => $usia60Plus],
        ];

        // Agregasi Pekerjaan warga aktif
        $pekerjaanStats = Penduduk::query()
            ->aktif()
            ->selectRaw("COALESCE(NULLIF(TRIM(pekerjaan), ''), 'Belum / Tidak Bekerja') as label_pekerjaan, COUNT(*) as jumlah")
            ->groupBy('label_pekerjaan')
            ->orderByDesc('jumlah')
            ->get()
            ->map(fn ($row) => [
                'pekerjaan' => (string) $row->label_pekerjaan,
                'jumlah' => (int) $row->jumlah,
            ])
            ->values()
            ->all();

        // Agregasi Sebaran per Dusun khusus penduduk & KK aktif
        $dusunPendudukCounts = Penduduk::query()
            ->where('penduduk.status_kependudukan', StatusKependudukan::Aktif)
            ->join('kartu_keluarga', 'kartu_keluarga.id', '=', 'penduduk.kartu_keluarga_id')
            ->whereNull('kartu_keluarga.deleted_at')
            ->selectRaw('kartu_keluarga.dusun as nama_dusun, COUNT(penduduk.id) as jumlah_jiwa, COUNT(DISTINCT kartu_keluarga.id) as jumlah_kk')
            ->groupBy('kartu_keluarga.dusun')
            ->orderBy('kartu_keluarga.dusun')
            ->get()
            ->map(fn ($row) => [
                'dusun' => (string) $row->nama_dusun,
                'jumlah_jiwa' => (int) $row->jumlah_jiwa,
                'jumlah_kk' => (int) $row->jumlah_kk,
            ])
            ->values()
            ->all();

        return Inertia::render('Publik/StatistikPenduduk', [
            'ringkasan' => [
                'total_penduduk_aktif' => $totalPendudukAktif,
                'total_kk_aktif' => $totalKkAktif,
                'laki_laki_count' => $lakiLakiCount,
                'perempuan_count' => $perempuanCount,
            ],
            'kelompok_usia' => $kelompokUsia,
            'pekerjaan' => $pekerjaanStats,
            'sebaran_dusun' => $dusunPendudukCounts,
        ]);
    }

    /**
     * 6. Daftar Kegiatan Desa Publik (/kegiatan) — Khusus status_publikasi = 'published'
     */
    public function kegiatanIndex(Request $request): Response
    {
        $kategoriFilter = trim((string) $request->query('kategori', ''));

        $validKategoriValues = array_map(fn (KategoriKegiatan $c) => $c->value, KategoriKegiatan::cases());

        $kegiatanList = KegiatanDesa::query()
            ->published()
            ->with('foto')
            ->when(in_array($kategoriFilter, $validKategoriValues, true), function ($q) use ($kategoriFilter) {
                $q->where('kategori', $kategoriFilter);
            })
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('id')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (KegiatanDesa $k) => $this->transformKegiatanCard($k));

        $kategoriOptions = collect(KategoriKegiatan::cases())
            ->map(fn (KategoriKegiatan $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ])
            ->values()
            ->all();

        return Inertia::render('Publik/KegiatanIndex', [
            'kegiatan' => $kegiatanList,
            'kategori_options' => $kategoriOptions,
            'filters' => [
                'kategori' => $kategoriFilter,
            ],
        ]);
    }

    /**
     * 7. Baca Artikel Kegiatan Desa Lengkap (/kegiatan/{slug})
     * Mengembalikan 404 Not Found jika berstatus 'draft' atau tidak ditemukan.
     */
    public function kegiatanShow(string $slug): Response
    {
        $kegiatan = KegiatanDesa::query()
            ->published()
            ->with('foto')
            ->where('slug', $slug)
            ->firstOrFail();

        $kategoriVal = $kegiatan->kategori instanceof KategoriKegiatan
            ? $kegiatan->kategori->value
            : (string) $kegiatan->kategori;
        $kategoriLabel = $kegiatan->kategori instanceof KategoriKegiatan
            ? $kegiatan->kategori->label()
            : $kategoriVal;

        return Inertia::render('Publik/KegiatanDetail', [
            'kegiatan' => [
                'id' => $kegiatan->id,
                'judul' => $kegiatan->judul,
                'slug' => $kegiatan->slug,
                'kategori' => $kategoriVal,
                'kategori_label' => $kategoriLabel,
                'tanggal_kegiatan' => $kegiatan->tanggal_kegiatan?->format('Y-m-d'),
                'lokasi_dusun' => $kegiatan->lokasi_dusun,
                'uraian' => $kegiatan->uraian,
                'published_at' => $kegiatan->published_at?->toIso8601String(),
                'foto_galeri' => $kegiatan->foto->map(fn ($f) => [
                    'id' => $f->id,
                    'urutan' => (int) $f->urutan,
                    'foto_path' => $f->foto_path,
                    'foto_url' => Storage::disk('public')->url($f->foto_path),
                ])->values()->all(),
            ],
        ]);
    }

    /**
     * 8. Pusat Unduhan Dokumen Resmi PPID (/unduhan) — Khusus is_publik = true
     */
    public function unduhanIndex(Request $request): Response
    {
        $kategoriFilter = trim((string) $request->query('kategori', ''));
        $tahunFilter = $request->filled('tahun') ? (int) $request->query('tahun') : null;

        $dokumenList = DokumenPpid::query()
            ->published()
            ->when(array_key_exists($kategoriFilter, SaveDokumenPpidRequest::KATEGORI_OPTIONS), function ($q) use ($kategoriFilter) {
                $q->where('kategori', $kategoriFilter);
            })
            ->when($tahunFilter !== null, function ($q) use ($tahunFilter) {
                $q->where('tahun_dokumen', $tahunFilter);
            })
            ->orderByDesc('tahun_dokumen')
            ->orderByDesc('id')
            ->get()
            ->map(fn (DokumenPpid $d) => [
                'id' => $d->id,
                'judul_dokumen' => $d->judul_dokumen,
                'kategori' => $d->kategori,
                'kategori_label' => SaveDokumenPpidRequest::KATEGORI_OPTIONS[$d->kategori] ?? $d->kategori,
                'tahun_dokumen' => $d->tahun_dokumen ? (int) $d->tahun_dokumen : null,
                'ukuran_file_kb' => (int) $d->ukuran_file_kb,
                'jumlah_unduhan' => (int) $d->jumlah_unduhan,
                'download_url' => route('publik.unduhan.download', $d->id),
            ])
            ->values()
            ->all();

        $availableTahun = DokumenPpid::query()
            ->published()
            ->whereNotNull('tahun_dokumen')
            ->select('tahun_dokumen')
            ->distinct()
            ->orderByDesc('tahun_dokumen')
            ->pluck('tahun_dokumen')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        $kategoriOptions = collect(SaveDokumenPpidRequest::KATEGORI_OPTIONS)
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();

        return Inertia::render('Publik/Unduhan', [
            'dokumen' => $dokumenList,
            'kategori_options' => $kategoriOptions,
            'available_tahun' => $availableTahun,
            'filters' => [
                'kategori' => $kategoriFilter,
                'tahun' => $tahunFilter,
            ],
        ]);
    }

    /**
     * 9. Endpoint Unduh Berkas PDF PPID (/unduhan/{dokumenPpid}/file)
     * Wajib menolak 404 jika is_publik = false, dan menambahkan counter jumlah_unduhan.
     */
    public function unduhanDownload(DokumenPpid $dokumenPpid): StreamedResponse|HttpResponse
    {
        abort_unless((bool) $dokumenPpid->is_publik, 404);

        $dokumenPpid->increment('jumlah_unduhan');

        $safeName = Str::slug($dokumenPpid->judul_dokumen ?: 'dokumen-ppid') . '.pdf';

        if ($dokumenPpid->file_pdf_path && Storage::disk('public')->exists($dokumenPpid->file_pdf_path)) {
            return Storage::disk('public')->download($dokumenPpid->file_pdf_path, $safeName, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response('%PDF-1.4 Dokumen Resmi PPID Pemerintah Desa', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformProyekPublik(ProyekPembangunan $p): array
    {
        $sumberVal = $p->sumber_dana instanceof SumberDanaProyek
            ? $p->sumber_dana->value
            : (string) $p->sumber_dana;
        $sumberLabel = $p->sumber_dana instanceof SumberDanaProyek
            ? $p->sumber_dana->label()
            : strtoupper($sumberVal);

        $statusVal = $p->status_proyek instanceof StatusProyek
            ? $p->status_proyek->value
            : (string) $p->status_proyek;
        $statusLabel = $p->status_proyek instanceof StatusProyek
            ? $p->status_proyek->label()
            : $statusVal;

        $fotoSampulPath = $p->foto_100_path ?? $p->foto_50_path ?? $p->foto_0_path;

        return [
            'id' => $p->id,
            'tahun_anggaran' => (int) $p->tahun_anggaran,
            'nama_kegiatan' => $p->nama_kegiatan,
            'lokasi_dusun' => $p->lokasi_dusun,
            'sumber_dana' => $sumberVal,
            'sumber_dana_label' => $sumberLabel,
            'pagu_anggaran' => (int) $p->pagu_anggaran,
            'realisasi_anggaran' => (int) $p->realisasi_anggaran,
            'persentase_serapan' => $p->persentaseSerapan(),
            'progres_fisik' => (int) $p->progres_fisik,
            'status_proyek' => $statusVal,
            'status_label' => $statusLabel,
            'volume_fisik' => $p->volume_fisik,
            'waktu_pelaksanaan' => $p->waktu_pelaksanaan,
            'pelaksana_kegiatan' => $p->pelaksana_kegiatan,
            'latitude' => $p->latitude !== null ? (float) $p->latitude : null,
            'longitude' => $p->longitude !== null ? (float) $p->longitude : null,
            'foto_0_path' => $p->foto_0_path,
            'foto_50_path' => $p->foto_50_path,
            'foto_100_path' => $p->foto_100_path,
            'foto_0_url' => $p->foto_0_path ? Storage::disk('public')->url($p->foto_0_path) : null,
            'foto_50_url' => $p->foto_50_path ? Storage::disk('public')->url($p->foto_50_path) : null,
            'foto_100_url' => $p->foto_100_path ? Storage::disk('public')->url($p->foto_100_path) : null,
            'foto_sampul_url' => $fotoSampulPath ? Storage::disk('public')->url($fotoSampulPath) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformKegiatanCard(KegiatanDesa $k): array
    {
        $kategoriVal = $k->kategori instanceof KategoriKegiatan
            ? $k->kategori->value
            : (string) $k->kategori;
        $kategoriLabel = $k->kategori instanceof KategoriKegiatan
            ? $k->kategori->label()
            : $kategoriVal;

        $firstFoto = $k->foto->sortBy('urutan')->first();

        return [
            'id' => $kegiatanId = $k->id,
            'judul' => $k->judul,
            'slug' => $k->slug,
            'kategori' => $kategoriVal,
            'kategori_label' => $kategoriLabel,
            'tanggal_kegiatan' => $k->tanggal_kegiatan?->format('Y-m-d'),
            'lokasi_dusun' => $k->lokasi_dusun,
            'ringkasan' => Str::limit(strip_tags((string) $k->uraian), 140),
            'foto_sampul_url' => $firstFoto?->foto_path
                ? Storage::disk('public')->url($firstFoto->foto_path)
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformApbdesDoc(Apbdes $doc): array
    {
        $doc->loadMissing('rincian');
        $jenisVal = $doc->jenis_dokumen instanceof JenisDokumenApbdes
            ? $doc->jenis_dokumen->value
            : (string) $doc->jenis_dokumen;

        return [
            'id' => $doc->id,
            'tahun_anggaran' => (int) $doc->tahun_anggaran,
            'jenis_dokumen' => $jenisVal,
            'jenis_label' => $jenisVal === 'perubahan'
                ? 'APBDes Perubahan (P-APBDes)'
                : 'APBDes Awal (Murni)',
            'nomor_perdes' => $doc->nomor_perdes,
            'tanggal_penetapan' => $doc->tanggal_penetapan?->format('Y-m-d'),
            'rincian' => $doc->rincian->map(function (ApbdesRincian $r): array {
                $kelompokVal = $r->kelompok instanceof KelompokPosApbdes
                    ? $r->kelompok->value
                    : (string) $r->kelompok;
                $pagu = (int) $r->pagu_anggaran;
                $realisasi = (int) $r->total_realisasi;
                $persentase = $pagu > 0 ? round(($realisasi / $pagu) * 100, 2) : 0.0;

                return [
                    'kode_pos' => $r->kode_pos,
                    'nama_pos' => $r->nama_pos,
                    'kelompok' => $kelompokVal,
                    'pagu_anggaran' => $pagu,
                    'total_realisasi' => $realisasi,
                    'sisa_anggaran' => $pagu - $realisasi,
                    'persentase' => $persentase,
                    'urutan' => (int) $r->urutan,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyApbdesDoc(int $tahun, string $jenis): array
    {
        return [
            'id' => null,
            'tahun_anggaran' => $tahun,
            'jenis_dokumen' => $jenis,
            'jenis_label' => $jenis === 'perubahan' ? 'APBDes Perubahan (P-APBDes)' : 'APBDes Awal (Murni)',
            'nomor_perdes' => null,
            'tanggal_penetapan' => null,
            'rincian' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyApbdesSummary(): array
    {
        return [
            'total_pagu_pendapatan' => 0,
            'total_realisasi_pendapatan' => 0,
            'persentase_realisasi_pendapatan' => 0.0,
            'total_pagu_belanja' => 0,
            'total_realisasi_belanja' => 0,
            'sisa_anggaran_belanja' => 0,
            'persentase_serapan_belanja' => 0.0,
            'pembiayaan_netto_pagu' => 0,
            'pembiayaan_netto_realisasi' => 0,
            'lima_bidang' => [],
        ];
    }
}
