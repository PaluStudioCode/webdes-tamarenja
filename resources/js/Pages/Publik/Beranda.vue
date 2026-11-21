<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    Camera,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Download,
    FileText,
    Hammer,
    ImageIcon,
    MapPin,
    Quote,
    User,
} from 'lucide-vue-next';
import BaseModal from '@/Components/BaseModal.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatDateId, formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    tahun_aktif: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
    statistik_penduduk: {
        type: Object,
        default: () => ({
            total_penduduk: 0,
            total_kk: 0,
            laki_laki: 0,
            perempuan: 0,
            pekerjaan_utama: [],
        }),
    },
    sambutan: {
        type: Object,
        default: () => ({
            nama_kepala_desa: 'Kepala Desa Tamarenja',
            sambutan_kepala_desa:
                'Selamat datang di Portal Resmi Pemerintah Desa Tamarenja sebagai wujud pelayanan cepat dan keterbukaan informasi publik.',
            foto_kepala_desa_url: null,
        }),
    },
    profil: {
        type: Object,
        default: () => ({
            nama_desa: 'Tamarenja',
            kecamatan: 'Sindue Tobata',
            kabupaten: 'Donggala',
            provinsi: 'Sulawesi Tengah',
            kode_wilayah: '72.03.19.2003',
            daftar_dusun: [],
            visi: '',
            misi: [],
            sejarah_singkat: '',
            potensi_unggulan: '',
        }),
    },
    sotk_pemerintah: {
        type: Array,
        default: () => [],
    },
    sotk_bpd: {
        type: Array,
        default: () => [],
    },
    proyek_terbaru: {
        type: Array,
        default: () => [],
    },
    kegiatan_terbaru: {
        type: Array,
        default: () => [],
    },
    dokumen_ppid_terbaru: {
        type: Array,
        default: () => [],
    },
    indikator_desa: {
        type: Object,
        default: () => ({
            idm: {
                status: 'Desa Maju',
                skor: '0,7143',
                iks: '0,8214',
                ike: '0,7550',
                ikl: '0,5667',
            },
            podeskel: {
                tingkat_perkembangan: 'Desa Swasembada',
                fasilitas_kesehatan: 'Poskesdes & 3 Posyandu Dusun',
                fasilitas_pendidikan: 'PAUD/TK & SD Negeri Tamarenja',
                modal_sosial: 'Percontohan Nasional Kemendes (2015)',
                komoditas_utama: 'Kebun Salak Tamarenja seluas 300–350 Ha',
                akses_pasar: 'Pemasaran lintas provinsi (Sulbar, Sulsel, Sulteng, Sulut)',
                potensi_ekonomi: 'Potensi agrowisata petik buah perbukitan',
                topografi: 'Topografi 100–300 mdpl diapit dua sungai alami',
                sumber_air: 'Daerah Irigasi (D.I) Sinente & Katandona',
                akses_wilayah: 'Akses 3 dusun: Katandona, Sinente, Irigasi',
            },
        }),
    },
});

const page = usePage();
const namaDesa = computed(
    () => props.profil?.nama_desa || page.props?.profil_desa?.nama_desa || 'Tamarenja',
);

const indikatorDesa = computed(() => ({
    idm: {
        status: props.indikator_desa?.idm?.status || 'Desa Maju',
        skor: props.indikator_desa?.idm?.skor || '0,7143',
        iks: props.indikator_desa?.idm?.iks || '0,8214',
        ike: props.indikator_desa?.idm?.ike || '0,7550',
        ikl: props.indikator_desa?.idm?.ikl || '0,5667',
    },
    podeskel: {
        tingkat_perkembangan: props.indikator_desa?.podeskel?.tingkat_perkembangan || 'Desa Swasembada',
        fasilitas_kesehatan: props.indikator_desa?.podeskel?.fasilitas_kesehatan || 'Poskesdes & 3 Posyandu Dusun',
        fasilitas_pendidikan: props.indikator_desa?.podeskel?.fasilitas_pendidikan || 'PAUD/TK & SD Negeri Tamarenja',
        modal_sosial: props.indikator_desa?.podeskel?.modal_sosial || 'Percontohan Nasional Kemendes (2015)',
        komoditas_utama: props.indikator_desa?.podeskel?.komoditas_utama || 'Kebun Salak Tamarenja seluas 300–350 Ha',
        akses_pasar: props.indikator_desa?.podeskel?.akses_pasar || 'Pemasaran lintas provinsi (Sulbar, Sulsel, Sulteng, Sulut)',
        potensi_ekonomi: props.indikator_desa?.podeskel?.potensi_ekonomi || 'Potensi agrowisata petik buah perbukitan',
        topografi: props.indikator_desa?.podeskel?.topografi || 'Topografi 100–300 mdpl diapit dua sungai alami',
        sumber_air: props.indikator_desa?.podeskel?.sumber_air || 'Daerah Irigasi (D.I) Sinente & Katandona',
        akses_wilayah: props.indikator_desa?.podeskel?.akses_wilayah || 'Akses 3 dusun: Katandona, Sinente, Irigasi',
    },
}));

// Pemetaan Hierarkis SOTK Permendagri No. 84/2015 ala Papan Struktur Baliho Kantor Desa
const kades = computed(() => {
    const list = props.sotk_pemerintah || [];
    return (
        list.find(
            (p) =>
                p.kelompok_sotk === 'pimpinan' ||
                (p.jabatan && /kepala desa|kades/i.test(p.jabatan)),
        ) ||
        list[0] ||
        null
    );
});

const bpdLeader = computed(() => {
    const list = props.sotk_bpd || [];
    return (
        list.find((p) => p.jabatan && /ketua/i.test(p.jabatan)) ||
        list[0] ||
        null
    );
});

const bpdAnggotaList = computed(() => {
    const list = props.sotk_bpd || [];
    return list.filter((p) => p.id !== bpdLeader.value?.id);
});

const sekdes = computed(() => {
    const list = props.sotk_pemerintah || [];
    return (
        list.find(
            (p) =>
                p.id !== kades.value?.id &&
                p.jabatan &&
                /sekretaris desa|sekdes/i.test(p.jabatan),
        ) || null
    );
});

const kaurList = computed(() => {
    const list = props.sotk_pemerintah || [];
    return list.filter(
        (p) =>
            p.id !== kades.value?.id &&
            p.id !== sekdes.value?.id &&
            (p.kelompok_sotk === 'sekretariat' ||
                /kaur|urusan/i.test(p.jabatan)),
    );
});

const kasiList = computed(() => {
    const list = props.sotk_pemerintah || [];
    return list.filter(
        (p) =>
            p.id !== kades.value?.id &&
            p.id !== sekdes.value?.id &&
            !kaurList.value.some((k) => k.id === p.id) &&
            (p.kelompok_sotk === 'pelaksana_teknis' ||
                /kasi|seksi/i.test(p.jabatan)),
    );
});

const kadusList = computed(() => {
    const list = props.sotk_pemerintah || [];
    return list.filter(
        (p) =>
            p.id !== kades.value?.id &&
            p.id !== sekdes.value?.id &&
            !kaurList.value.some((k) => k.id === p.id) &&
            !kasiList.value.some((k) => k.id === p.id) &&
            (p.kelompok_sotk === 'kewilayahan_dusun' ||
                /kadus|dusun/i.test(p.jabatan)),
    );
});

const stafLainList = computed(() => {
    const list = props.sotk_pemerintah || [];
    return list.filter(
        (p) =>
            p.id !== kades.value?.id &&
            p.id !== sekdes.value?.id &&
            !kaurList.value.some((k) => k.id === p.id) &&
            !kasiList.value.some((k) => k.id === p.id) &&
            !kadusList.value.some((k) => k.id === p.id),
    );
});

const topKegiatan = computed(() => (props.kegiatan_terbaru || []).slice(0, 3));

const topDokumenPpid = computed(() =>
    (props.dokumen_ppid_terbaru || []).slice(0, 4),
);

function formatUkuranKb(kb) {
    const val = Number(kb || 0);
    if (val >= 1024) {
        return `${(val / 1024).toFixed(1)} MB`;
    }
    return `${val || 1} KB`;
}

// Modal Slider Foto Progres Pembangunan (Titik 0%, 50%, 100%)
const sliderStages = [
    {
        percent: '0%',
        tabLabel: 'Titik 0%',
        label: 'Titik 0% (Kondisi Awal)',
        desc: 'Kondisi lokasi sebelum pekerjaan fisik dimulai.',
        key: 'foto_0_url',
    },
    {
        percent: '50%',
        tabLabel: 'Titik 50%',
        label: 'Titik 50% (Sedang Dikerjakan)',
        desc: 'Kondisi fisik saat pengerjaan berlangsung di lapangan.',
        key: 'foto_50_url',
    },
    {
        percent: '100%',
        tabLabel: 'Titik 100%',
        label: 'Titik 100% (Hasil Selesai)',
        desc: 'Hasil akhir pembangunan setelah pekerjaan tuntas.',
        key: 'foto_100_url',
    },
];

const activeModalProyek = ref(null);
const isModalOpen = ref(false);
const activeSlide = ref(0);

const activeStage = computed(() => sliderStages[activeSlide.value] || sliderStages[0]);

const activeSlidePhotoUrl = computed(() => {
    if (!activeModalProyek.value) {
        return null;
    }
    return activeModalProyek.value[activeStage.value.key] || null;
});

const modalSubtitle = computed(() => {
    if (!activeModalProyek.value) {
        return '';
    }
    const parts = [
        activeModalProyek.value.lokasi_dusun,
        activeModalProyek.value.tahun_anggaran ? `TA ${activeModalProyek.value.tahun_anggaran}` : null,
    ].filter(Boolean);
    return parts.join(' • ');
});

function getStatusLabel(proyek) {
    if (!proyek) {
        return '';
    }
    if (proyek.status_label) {
        return proyek.status_label;
    }
    const map = {
        rencana: 'Rencana',
        sedang_dikerjakan: 'Sedang Dikerjakan',
        selesai: 'Selesai',
        belum_dimulai: 'Belum Dimulai',
    };
    return map[proyek.status_proyek] || proyek.status_proyek || '-';
}

function openModalSlider(proyek, initialSlide = 0) {
    activeModalProyek.value = proyek;
    activeSlide.value = initialSlide;
    isModalOpen.value = true;
}

function closeModalSlider() {
    isModalOpen.value = false;
    activeModalProyek.value = null;
    activeSlide.value = 0;
}

function nextSlide() {
    activeSlide.value = (activeSlide.value + 1) % sliderStages.length;
}

function prevSlide() {
    activeSlide.value = (activeSlide.value - 1 + sliderStages.length) % sliderStages.length;
}

function handleSliderKeydown(event) {
    if (!isModalOpen.value) {
        return;
    }
    if (event.key === 'ArrowRight') {
        nextSlide();
    } else if (event.key === 'ArrowLeft') {
        prevSlide();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleSliderKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleSliderKeydown);
});
</script>

<template>
    <PublicLayout>
        <Head :title="`Portal Resmi Pemerintah Desa ${namaDesa}`" />

        <template #banner>
            <!-- Seksi 1: Hero Modern Profesional dengan Background Gambar Desa & Wadah Foto Pejabat Cut-out -->
            <section
                id="beranda"
                data-testid="beranda-hero"
                class="relative overflow-hidden bg-slate-900 text-white"
            >
                <!-- Gambar Background Panorama Desa -->
                <img
                    src="/images/hero-desa.jpg"
                    :alt="`Panorama Desa ${namaDesa}`"
                    data-testid="beranda-hero-bg-image"
                    class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center"
                />

                <!-- Overlay Gradasi agar Teks Tetap Kontras & Mudah Dibaca -->
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-r from-slate-950/90 via-slate-900/75 to-slate-900/45"
                ></div>

                <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="max-w-3xl space-y-6 py-14 sm:py-20 lg:py-24">
                        <!-- Lokasi Administratif -->
                        <p class="text-xs font-semibold uppercase tracking-widest text-blue-300 sm:text-sm">
                            Pemerintah Desa {{ namaDesa }} &bull; Kec. {{ profil.kecamatan || 'Sindue Tobata' }}, Kab. {{ profil.kabupaten || 'Donggala' }}
                        </p>

                        <!-- Headline Utama -->
                        <h1 class="text-3xl font-bold leading-tight tracking-tight text-white sm:text-4xl lg:text-5xl">
                            Portal Resmi Pemerintah Desa {{ namaDesa }}
                        </h1>

                        <!-- Sambutan Singkat -->
                        <blockquote class="border-l-4 border-blue-500 pl-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                            "{{ sambutan.sambutan_kepala_desa }}"
                        </blockquote>

                        <!-- CTA Buttons -->
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <a
                                href="/layanan-mandiri"
                                data-testid="cta-layanan-mandiri"
                                class="inline-flex h-11 min-h-[44px] items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-slate-900"
                            >
                                <span>Ajukan Surat &amp; Cek Bansos</span>
                            </a>

                            <Link
                                href="/unduhan"
                                data-testid="hero-link-unduhan"
                                class="inline-flex h-11 min-h-[44px] items-center justify-center gap-1.5 rounded-md px-3 text-sm font-medium text-blue-300 transition-colors hover:text-white hover:underline"
                            >
                                <span>Pusat Unduhan PPID</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <div class="space-y-12">
            <!-- Seksi 2: Profil Desa, Data Demografi & Visi-Misi -->
            <section
                id="profil"
                data-testid="beranda-profil-section"
                class="scroll-mt-24 space-y-8"
            >
                <!-- Section Header -->
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Profil Desa
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Profil Desa {{ namaDesa }}
                    </h2>
                </div>

                <!-- Narasi Wilayah & 2 Card Visi-Misi -->
                <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-12 lg:gap-10">
                    <!-- Kolom Kiri (7 Cols) -->
                    <div class="space-y-6 lg:col-span-7">
                        <div class="space-y-4 text-sm leading-relaxed text-slate-600 sm:text-base">
                            <p>
                                {{
                                    profil.sejarah_singkat ||
                                    `Desa ${namaDesa} merupakan desa pedalaman berbasis perkebunan yang terletak di kawasan lembah perbukitan Kecamatan ${profil.kecamatan || 'Sindue Tobata'}, Kabupaten ${profil.kabupaten || 'Donggala'}, diapit oleh dua aliran sungai besar pada ketinggian 100–300 mdpl. Diresmikan sebagai desa definitif pada tahun 1961, perekonomian desa mengalami transformasi besar sejak masuknya bibit salak pada tahun 1982 yang kemudian berkembang pesat menjadi komoditas unggulan utama daerah. Berkat kerja keras dan gotong royong warganya dalam mengelola potensi perkebunan, Desa Tamarenja berhasil meraih predikat Desa Terbaik se-Sulawesi Tengah dan Desa Percontohan Nasional Kementerian Desa pada tahun 2015.`
                                }}
                            </p>
                            <p>
                                {{
                                    profil.potensi_unggulan ||
                                    'Sentra perkebunan Salak Tamarenja seluas 300–350 hektare dengan cita rasa manis renyah yang dipasarkan lintas provinsi, potensi agrowisata petik salak di lembah perbukitan asri, komoditas perkebunan pisang dan kelapa, serta ketersediaan sumber air melimpah dari dua sungai besar desa.'
                                }}
                            </p>
                        </div>

                        <!-- Data Administratif & Demografi Terbuka -->
                        <div class="space-y-6 border-t border-slate-200 pt-6">
                            <!-- Kelompok 1: Wilayah Administratif -->
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kecamatan</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-900 sm:text-base">
                                        {{ profil.kecamatan || 'Sindue Tobata' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kabupaten</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-900 sm:text-base">
                                        {{ profil.kabupaten || 'Donggala' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Wilayah Kewilayahan</dt>
                                    <dd class="mt-1 text-sm font-semibold tabular-nums text-slate-900 sm:text-base">
                                        {{
                                            profil.daftar_dusun?.length
                                                ? `${profil.daftar_dusun.length} Dusun Terdaftar`
                                                : '3 Dusun Administratif'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kode Wilayah Kemendagri</dt>
                                    <dd class="mt-1 font-mono text-sm font-medium tabular-nums text-slate-700 sm:text-base">
                                        {{ profil.kode_wilayah || '72.03.25.2004' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Provinsi</dt>
                                    <dd class="mt-1 text-sm font-semibold text-slate-900 sm:text-base">
                                        {{ profil.provinsi || 'Sulawesi Tengah' }}
                                    </dd>
                                </div>
                            </dl>

                            <!-- Kelompok 2: Data Demografi Warga -->
                            <div data-testid="beranda-statistik-section" class="border-t border-slate-200 pt-5">
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Total Penduduk</dt>
                                        <dd class="mt-1 text-sm font-bold tabular-nums text-slate-900 sm:text-base">
                                            {{ Number(statistik_penduduk.total_penduduk || 0).toLocaleString('id-ID') }} <span class="text-xs font-normal text-slate-500">Jiwa</span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Kepala Keluarga (KK)</dt>
                                        <dd class="mt-1 text-sm font-bold tabular-nums text-slate-900 sm:text-base">
                                            {{ Number(statistik_penduduk.total_kk || 0).toLocaleString('id-ID') }} <span class="text-xs font-normal text-slate-500">KK</span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-500">Komposisi Gender</dt>
                                        <dd class="mt-1 text-sm font-semibold tabular-nums text-slate-900 sm:text-base">
                                            {{ Number(statistik_penduduk.laki_laki || 0).toLocaleString('id-ID') }} L <span class="text-slate-300">/</span> {{ Number(statistik_penduduk.perempuan || 0).toLocaleString('id-ID') }} P
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan (5 Cols): Visi & Misi -->
                    <div class="space-y-5 lg:col-span-5">
                        <!-- Card 1: Visi Utama Pemerintah Desa -->
                        <div class="relative overflow-hidden rounded-lg border border-slate-800 bg-gradient-to-br from-slate-900 via-slate-900 to-blue-950 p-6 text-white shadow-sm">
                            <div class="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-blue-500/20 blur-2xl"></div>

                            <div class="relative space-y-3">
                                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-blue-300">
                                    <Quote class="h-4 w-4" />
                                    <span>Visi Pemerintah Desa</span>
                                </div>
                                <blockquote class="text-base font-medium leading-snug text-white sm:text-lg">
                                    "{{
                                        profil.visi ||
                                        `Terwujudnya Desa ${namaDesa} yang Maju, Mandiri, Sejahtera, dan Berkeadilan melalui Pelayanan Transparan.`
                                    }}"
                                </blockquote>
                            </div>
                        </div>

                        <!-- Card 2: Misi Pembangunan Desa -->
                        <div class="rounded-lg border border-slate-200 bg-white p-6">
                            <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">
                                Misi Pembangunan Desa
                            </h3>
                            <ol
                                v-if="profil.misi && profil.misi.length > 0"
                                class="mt-4 space-y-3"
                            >
                                <li
                                    v-for="(butir, idx) in profil.misi"
                                    :key="idx"
                                    class="flex items-start gap-3"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-blue-50 text-xs font-semibold tabular-nums text-blue-700">
                                        {{ idx + 1 }}
                                    </span>
                                    <span class="text-sm leading-relaxed text-slate-700">
                                        {{ butir }}
                                    </span>
                                </li>
                            </ol>
                            <ol v-else class="mt-4 space-y-3">
                                <li class="flex items-start gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-blue-50 text-xs font-semibold tabular-nums text-blue-700">1</span>
                                    <span class="text-sm leading-relaxed text-slate-700">Meningkatkan tata kelola pemerintahan yang bersih, melayani, dan partisipatif.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-blue-50 text-xs font-semibold tabular-nums text-blue-700">2</span>
                                    <span class="text-sm leading-relaxed text-slate-700">Mendorong percepatan pembangunan infrastruktur dan fasilitas umum desa yang merata.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-blue-50 text-xs font-semibold tabular-nums text-blue-700">3</span>
                                    <span class="text-sm leading-relaxed text-slate-700">Memberdayakan potensi ekonomi kerakyatan melalui pertanian, perkebunan, dan UMKM.</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Kartu Indikator IDM & PODESKEL (Standar, Bersih & Profesional) -->
                <div
                    data-testid="beranda-indikator-desa"
                    class="rounded-lg border border-slate-200 bg-slate-50/70 p-5 sm:p-6"
                >
                    <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">
                                Indikator Kemandirian &amp; Perkembangan Desa
                            </h3>
                            <p class="text-xs text-slate-500">
                                Pengukuran resmi Indeks Desa Membangun (Kemendes PDTT) &amp; Profil Potensi Desa (Kemendagri)
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                IDM: {{ indikatorDesa.idm.status }} ({{ indikatorDesa.idm.skor }})
                            </span>
                            <span class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">
                                Prodeskel: {{ indikatorDesa.podeskel.tingkat_perkembangan }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <!-- Pilar 1: Indeks Ketahanan Sosial (IKS) -->
                        <div class="rounded-md border border-slate-200/80 bg-white p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Ketahanan Sosial (IKS)</span>
                                <span class="text-xs font-semibold tabular-nums text-emerald-700">Skor {{ indikatorDesa.idm.iks }}</span>
                            </div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                Fasilitas Dasar &amp; Modal Sosial
                            </div>
                            <ul class="mt-2 space-y-1.5 text-xs text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>Layanan: {{ indikatorDesa.podeskel.fasilitas_kesehatan }}</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>Pendidikan: {{ indikatorDesa.podeskel.fasilitas_pendidikan }}</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>Prestasi: {{ indikatorDesa.podeskel.modal_sosial }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Pilar 2: Indeks Ketahanan Ekonomi (IKE) -->
                        <div class="rounded-md border border-slate-200/80 bg-white p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Ketahanan Ekonomi (IKE)</span>
                                <span class="text-xs font-semibold tabular-nums text-emerald-700">Skor {{ indikatorDesa.idm.ike }}</span>
                            </div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                Sentra Salak &amp; Agribisnis
                            </div>
                            <ul class="mt-2 space-y-1.5 text-xs text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>{{ indikatorDesa.podeskel.komoditas_utama }}</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>{{ indikatorDesa.podeskel.akses_pasar }}</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>{{ indikatorDesa.podeskel.potensi_ekonomi }}</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Pilar 3: Ketahanan Lingkungan & Sarpras (IKL) -->
                        <div class="rounded-md border border-slate-200/80 bg-white p-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium uppercase tracking-wider text-slate-500">Lingkungan &amp; Sarpras (IKL)</span>
                                <span class="text-xs font-semibold tabular-nums text-emerald-700">Skor {{ indikatorDesa.idm.ikl }}</span>
                            </div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                Lembah Perbukitan &amp; Dua Sungai
                            </div>
                            <ul class="mt-2 space-y-1.5 text-xs text-slate-600">
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>{{ indikatorDesa.podeskel.topografi }}</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>{{ indikatorDesa.podeskel.sumber_air }}</span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-slate-400">&bull;</span>
                                    <span>{{ indikatorDesa.podeskel.akses_wilayah }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Seksi Baru: Potensi & Komoditas Desa (#potensi) -->
            <section
                id="potensi"
                data-testid="beranda-potensi-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Potensi Wilayah
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Potensi &amp; Komoditas Desa
                    </h2>
                </div>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Kartu 1: Sentra Perkebunan Salak -->
                    <article
                        data-testid="beranda-potensi-card"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6"
                    >
                        <div class="space-y-2.5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">
                                Komoditas Utama
                            </p>
                            <h3 class="text-lg font-semibold text-slate-900">
                                Sentra Salak Tamarenja
                            </h3>
                            <p class="text-sm leading-relaxed text-slate-600">
                                Budidaya salak yang dirintis sejak tahun 1982 telah berkembang menjadi sentra perkebunan utama dengan karakter buah manis dan renyah yang menjadi penopang ekonomi keluarga petani.
                            </p>
                        </div>

                        <div class="mt-5 border-t border-slate-100 pt-3.5 text-xs font-medium text-slate-700">
                            {{ indikatorDesa.podeskel.komoditas_utama }}
                        </div>
                    </article>

                    <!-- Kartu 2: Agrowisata & Ekologi Lembah -->
                    <article
                        data-testid="beranda-potensi-card"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6"
                    >
                        <div class="space-y-2.5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                                Agrowisata &amp; Ekologi
                            </p>
                            <h3 class="text-lg font-semibold text-slate-900">
                                Kawasan Lembah &amp; Kebun Salak
                            </h3>
                            <p class="text-sm leading-relaxed text-slate-600">
                                Bentang alam lembah perbukitan yang sejuk dan diapit dua aliran sungai besar dengan ketersediaan air melimpah, mendukung pengembangan kawasan agrowisata petik buah langsung dari kebun.
                            </p>
                        </div>

                        <div class="mt-5 border-t border-slate-100 pt-3.5 text-xs font-medium text-slate-700">
                            {{ indikatorDesa.podeskel.potensi_ekonomi }}
                        </div>
                    </article>

                    <!-- Kartu 3: Distribusi & Pertanian Pendukung -->
                    <article
                        data-testid="beranda-potensi-card"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6"
                    >
                        <div class="space-y-2.5">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-600">
                                Perdagangan &amp; Pertanian
                            </p>
                            <h3 class="text-lg font-semibold text-slate-900">
                                Distribusi Lintas Provinsi
                            </h3>
                            <p class="text-sm leading-relaxed text-slate-600">
                                Rantai pasok panen salak menjangkau pasar regional antardaerah di Pulau Sulawesi secara berkelanjutan, dilengkapi hasil bumi pendukung seperti pisang, kelapa, dan tanaman pangan.
                            </p>
                        </div>

                        <div class="mt-5 border-t border-slate-100 pt-3.5 text-xs font-medium text-slate-700">
                            {{ indikatorDesa.podeskel.akses_pasar }}
                        </div>
                    </article>
                </div>
            </section>

            <!-- Seksi 3: Papan Struktur Organisasi (Section Sendiri) -->
            <section
                id="sotk"
                data-testid="beranda-sotk-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Pemerintahan Desa
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Struktur Organisasi Desa
                    </h2>
                </div>

                <div
                    v-if="!kades && !sekdes && !bpdLeader && kaurList.length === 0 && kasiList.length === 0 && kadusList.length === 0 && stafLainList.length === 0"
                    class="rounded-lg border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
                >
                    Belum ada data struktur organisasi pemerintahan desa yang dipublikasikan.
                </div>

                <div v-else class="space-y-3">
                    <!-- Petunjuk Geser Horizontal di Mobile -->
                    <div class="flex items-center justify-end gap-1.5 text-xs text-slate-500 sm:hidden">
                        <span>&larr; Geser untuk melihat bagan lengkap &rarr;</span>
                    </div>

                    <!-- Papan Kanvas SOTK -->
                    <div class="relative overflow-x-auto rounded-lg border border-slate-200 bg-slate-100/60 p-6 sm:p-8">
                        <div class="mx-auto flex min-w-[960px] flex-col items-center">
                            
                            <!-- ================= BAGIAN 1: KEPALA DESA, BPD BESERTA TURUNANNYA, & SEKDES ================= -->
                            <div class="flex items-start justify-center">
                                
                                <!-- PENYEIMBANG KIRI: Ukuran simetris dengan Sayap Kanan (Garis + BPD) agar Kades & Sekdes tepat di sumbu tengah -->
                                <div v-if="bpdLeader" class="w-72 shrink-0"></div>

                                <!-- POROS TENGAH: EKSEKUTIF UTAMA (KEPALA DESA & SEKRETARIS DESA) -->
                                <div class="flex flex-col items-center">
                                    <!-- KARTU KEPALA DESA (APEX) -->
                                    <article
                                        v-if="kades"
                                        data-testid="beranda-sotk-card"
                                        class="group relative w-60 overflow-hidden rounded-lg border-2 border-blue-700 bg-white shadow-sm transition-all hover:shadow-md"
                                    >
                                        <div class="bg-blue-700 py-1.5 text-center text-xs font-bold uppercase tracking-wider text-white">
                                            Kepala Desa
                                        </div>
                                        <div class="relative flex h-48 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-3 pt-3">
                                            <img
                                                v-if="kades.foto_url"
                                                :src="kades.foto_url"
                                                :alt="kades.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-md transition-transform duration-300 group-hover:scale-105"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                    <User class="h-8 w-8" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-100 p-3.5 text-center bg-white">
                                            <p class="text-sm font-bold text-slate-900 leading-tight">
                                                {{ kades.nama_lengkap }}
                                            </p>
                                            <p class="mt-0.5 text-xs font-semibold text-blue-700">
                                                {{ kades.jabatan }}
                                            </p>
                                            <p v-if="kades.nip_niap" class="mt-0.5 font-mono text-[10px] text-slate-400">
                                                NIP: {{ kades.nip_niap }}
                                            </p>
                                        </div>
                                    </article>

                                    <!-- GARIS KOMANDO VERTIKAL KADES -> SEKDES -->
                                    <div class="h-8 w-0.5 bg-slate-400"></div>

                                    <!-- KARTU SEKRETARIS DESA -->
                                    <article
                                        v-if="sekdes"
                                        data-testid="beranda-sotk-card"
                                        class="group relative w-56 overflow-hidden rounded-lg border border-slate-300 bg-white shadow-sm transition-all hover:border-blue-400 hover:shadow-md"
                                    >
                                        <div class="bg-slate-100 py-1 text-center text-xs font-bold uppercase tracking-wider text-slate-700">
                                            Sekretaris Desa
                                        </div>
                                        <div class="relative flex h-40 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="sekdes.foto_url"
                                                :src="sekdes.foto_url"
                                                :alt="sekdes.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-sm transition-transform duration-300 group-hover:scale-105"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                    <User class="h-6 w-6" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-100 p-2.5 text-center bg-white">
                                            <p class="truncate text-xs font-bold text-slate-900 sm:text-sm" :title="sekdes.nama_lengkap">
                                                {{ sekdes.nama_lengkap }}
                                            </p>
                                            <p class="mt-0.5 text-[11px] font-semibold text-blue-700">
                                                {{ sekdes.jabatan }}
                                            </p>
                                            <p v-if="sekdes.nip_niap" class="mt-0.5 font-mono text-[10px] text-slate-400">
                                                NIP: {{ sekdes.nip_niap }}
                                            </p>
                                        </div>
                                    </article>
                                </div>

                                <!-- SAYAP KANAN: GARIS KOORDINASI KADES - BPD & KELEMBAGAAN BPD -->
                                <div v-if="bpdLeader" class="flex items-start w-72 shrink-0">
                                    <!-- GARIS PENGHUBUNG SOLID TEGAS LANGSUNG MENEMPEL DI ANTARA KADES & KETUA BPD -->
                                    <div class="w-16 h-0.5 bg-slate-400 shrink-0 mt-32"></div>

                                    <!-- STRUKTUR BPD (KETUA & TURUNAN BPD) -->
                                    <div class="flex flex-col items-center w-56">
                                        <!-- KARTU KETUA BPD -->
                                        <article
                                            data-testid="beranda-sotk-card"
                                            class="group relative w-56 overflow-hidden rounded-lg border-2 border-slate-700 bg-white shadow-sm transition-all hover:shadow-md"
                                        >
                                            <div class="bg-slate-800 py-1.5 text-center text-xs font-bold uppercase tracking-wider text-white">
                                                Badan Permusyawaratan Desa
                                            </div>
                                            <div class="relative flex h-48 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-3 pt-3">
                                                <img
                                                    v-if="bpdLeader.foto_url"
                                                    :src="bpdLeader.foto_url"
                                                    :alt="bpdLeader.nama_lengkap"
                                                    class="h-full w-auto object-contain object-bottom drop-shadow-md transition-transform duration-300 group-hover:scale-105"
                                                />
                                                <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                        <User class="h-8 w-8" />
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="border-t border-slate-100 p-3.5 text-center bg-white">
                                                <p class="text-sm font-bold text-slate-900 leading-tight">
                                                    {{ bpdLeader.nama_lengkap }}
                                                </p>
                                                <p class="mt-0.5 text-xs font-semibold text-slate-700">
                                                    {{ bpdLeader.jabatan }}
                                                </p>
                                                <p v-if="bpdLeader.nip_niap" class="mt-0.5 font-mono text-[10px] text-slate-400">
                                                    NIP: {{ bpdLeader.nip_niap }}
                                                </p>
                                            </div>
                                        </article>

                                        <!-- TURUNAN BPD (SEKRETARIS & ANGGOTA BPD) -->
                                        <div v-if="bpdAnggotaList.length > 0" class="flex flex-col items-center">
                                            <!-- Garis Turun Solid dari Ketua BPD -->
                                            <div class="h-6 w-0.5 bg-slate-400"></div>

                                            <!-- Kartu Turunan BPD -->
                                            <div class="flex items-start justify-center">
                                                <div
                                                    v-for="(anggota, idx) in bpdAnggotaList"
                                                    :key="anggota.id"
                                                    class="relative flex flex-col items-center px-2"
                                                >
                                                    <!-- Garis Horizontal jika > 1 anggota -->
                                                    <div
                                                        v-if="bpdAnggotaList.length > 1"
                                                        :class="[
                                                            'absolute top-0 h-0.5 bg-slate-400',
                                                            idx === 0 ? 'left-1/2 -right-0.5' : '',
                                                            idx === bpdAnggotaList.length - 1 ? '-left-0.5 right-1/2' : '',
                                                            idx > 0 && idx < bpdAnggotaList.length - 1 ? '-left-0.5 -right-0.5' : '',
                                                        ]"
                                                    ></div>
                                                    <div v-if="bpdAnggotaList.length > 1" class="h-4 w-0.5 bg-slate-400"></div>

                                                    <article
                                                        data-testid="beranda-sotk-card"
                                                        class="group w-48 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition-all hover:border-blue-300 hover:shadow-md sm:w-52"
                                                    >
                                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                                            <img
                                                                v-if="anggota.foto_url"
                                                                :src="anggota.foto_url"
                                                                :alt="anggota.nama_lengkap"
                                                                class="h-full w-auto object-contain object-bottom drop-shadow-sm transition-transform duration-200 group-hover:scale-105"
                                                            />
                                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                                    <User class="h-5 w-5" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="border-t border-slate-100 p-2.5 text-center">
                                                            <p class="truncate text-xs font-bold text-slate-900" :title="anggota.nama_lengkap">
                                                                {{ anggota.nama_lengkap }}
                                                            </p>
                                                            <p class="mt-0.5 truncate text-[11px] font-medium text-slate-600" :title="anggota.jabatan">
                                                                {{ anggota.jabatan }}
                                                            </p>
                                                        </div>
                                                    </article>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- GARIS KOMANDO VERTIKAL PENGHUBUNG SEKDES -> BATANG DISTRIBUTOR KAUR -->
                            <div v-if="kaurList.length > 0" class="relative flex flex-col items-center">
                                <div class="h-8 w-0.5 bg-slate-400"></div>
                                <span class="absolute top-1/2 -translate-y-1/2 left-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                                    Kepala Urusan (Kaur)
                                </span>
                            </div>

                            <!-- ================= BAGIAN 2: UNSUR STAF PEMBANTU (KAUR) ================= -->
                            <div v-if="kaurList.length > 0" class="flex items-start justify-center">
                                <div
                                    v-for="(kaur, idx) in kaurList"
                                    :key="kaur.id"
                                    class="relative flex flex-col items-center px-3 sm:px-4"
                                >
                                    <!-- Garis horizontal distributor solid tanpa terputus -->
                                    <div
                                        v-if="kaurList.length > 1"
                                        :class="[
                                            'absolute top-0 h-0.5 bg-slate-400',
                                            idx === 0 ? 'left-1/2 -right-0.5' : '',
                                            idx === kaurList.length - 1 ? '-left-0.5 right-1/2' : '',
                                            idx > 0 && idx < kaurList.length - 1 ? '-left-0.5 -right-0.5' : '',
                                        ]"
                                    ></div>
                                    <!-- Garis vertikal drop ke kartu kaur -->
                                    <div class="h-6 w-0.5 bg-slate-400"></div>

                                    <article
                                        data-testid="beranda-sotk-card"
                                        class="group flex h-[216px] w-48 flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition-all hover:border-blue-300 hover:shadow-md sm:w-52"
                                    >
                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="kaur.foto_url"
                                                :src="kaur.foto_url"
                                                :alt="kaur.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-sm transition-transform duration-200 group-hover:scale-105"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                    <User class="h-5 w-5" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-100 p-2.5 text-center">
                                            <p class="truncate text-xs font-bold text-slate-900" :title="kaur.nama_lengkap">
                                                {{ kaur.nama_lengkap }}
                                            </p>
                                            <p class="mt-0.5 truncate text-[11px] font-medium text-slate-600" :title="kaur.jabatan">
                                                {{ kaur.jabatan }}
                                            </p>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <!-- GARIS KOMANDO VERTIKAL PENGHUBUNG KAUR -> BATANG DISTRIBUTOR KASI -->
                            <div v-if="kasiList.length > 0" class="relative flex flex-col items-center">
                                <div class="h-8 w-0.5 bg-slate-400"></div>
                                <span class="absolute top-1/2 -translate-y-1/2 left-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                                    Kepala Seksi (Kasi)
                                </span>
                            </div>

                            <!-- ================= BAGIAN 3: UNSUR PELAKSANA TEKNIS (KASI) ================= -->
                            <div v-if="kasiList.length > 0" class="flex items-start justify-center">
                                <div
                                    v-for="(kasi, idx) in kasiList"
                                    :key="kasi.id"
                                    class="relative flex flex-col items-center px-3 sm:px-4"
                                >
                                    <!-- Garis horizontal distributor solid tanpa terputus -->
                                    <div
                                        v-if="kasiList.length > 1"
                                        :class="[
                                            'absolute top-0 h-0.5 bg-slate-400',
                                            idx === 0 ? 'left-1/2 -right-0.5' : '',
                                            idx === kasiList.length - 1 ? '-left-0.5 right-1/2' : '',
                                            idx > 0 && idx < kasiList.length - 1 ? '-left-0.5 -right-0.5' : '',
                                        ]"
                                    ></div>
                                    <!-- Garis vertikal drop ke kartu kasi -->
                                    <div class="h-6 w-0.5 bg-slate-400"></div>

                                    <article
                                        data-testid="beranda-sotk-card"
                                        class="group flex h-[216px] w-48 flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition-all hover:border-blue-300 hover:shadow-md sm:w-52"
                                    >
                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="kasi.foto_url"
                                                :src="kasi.foto_url"
                                                :alt="kasi.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-sm transition-transform duration-200 group-hover:scale-105"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                    <User class="h-5 w-5" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-100 p-2.5 text-center">
                                            <p class="truncate text-xs font-bold text-slate-900" :title="kasi.nama_lengkap">
                                                {{ kasi.nama_lengkap }}
                                            </p>
                                            <p class="mt-0.5 truncate text-[11px] font-medium text-slate-600" :title="kasi.jabatan">
                                                {{ kasi.jabatan }}
                                            </p>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <!-- GARIS KOMANDO VERTIKAL PENGHUBUNG KASI -> BATANG DISTRIBUTOR KADUS -->
                            <div v-if="kadusList.length > 0" class="relative flex flex-col items-center">
                                <div class="h-8 w-0.5 bg-slate-400"></div>
                                <span class="absolute top-1/2 -translate-y-1/2 left-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                                    Kepala Dusun (Kadus)
                                </span>
                            </div>

                            <!-- ================= BAGIAN 4: UNSUR PELAKSANA KEWILAYAHAN (KADUS) ================= -->
                            <div v-if="kadusList.length > 0" class="flex items-start justify-center">
                                <div
                                    v-for="(kadus, idx) in kadusList"
                                    :key="kadus.id"
                                    class="relative flex flex-col items-center px-3 sm:px-4"
                                >
                                    <!-- Garis horizontal distributor solid tanpa terputus -->
                                    <div
                                        v-if="kadusList.length > 1"
                                        :class="[
                                            'absolute top-0 h-0.5 bg-slate-400',
                                            idx === 0 ? 'left-1/2 -right-0.5' : '',
                                            idx === kadusList.length - 1 ? '-left-0.5 right-1/2' : '',
                                            idx > 0 && idx < kadusList.length - 1 ? '-left-0.5 -right-0.5' : '',
                                        ]"
                                    ></div>
                                    <!-- Garis vertikal drop ke kartu kadus -->
                                    <div class="h-6 w-0.5 bg-slate-400"></div>

                                    <article
                                        data-testid="beranda-sotk-card"
                                        class="group flex h-[216px] w-48 flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm transition-all hover:border-blue-300 hover:shadow-md sm:w-52"
                                    >
                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="kadus.foto_url"
                                                :src="kadus.foto_url"
                                                :alt="kadus.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-sm transition-transform duration-200 group-hover:scale-105"
                                            />
                                            <div v-else class="flex h-full w-full items-center justify-center text-slate-300">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                                                    <User class="h-5 w-5" />
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-100 p-2.5 text-center">
                                            <p class="truncate text-xs font-bold text-slate-900" :title="kadus.nama_lengkap">
                                                {{ kadus.nama_lengkap }}
                                            </p>
                                            <p class="mt-0.5 truncate text-[11px] font-medium text-slate-600" :title="kadus.jabatan">
                                                {{ kadus.jabatan }}
                                            </p>
                                        </div>
                                    </article>
                                </div>
                            </div>

                            <!-- ================= BAGIAN 5: STAF LAINNYA JIKA ADA ================= -->
                            <div v-if="stafLainList.length > 0" class="relative flex flex-col items-center">
                                <div class="h-8 w-0.5 bg-slate-400"></div>
                                <span class="absolute top-1/2 -translate-y-1/2 left-4 text-[10px] font-bold uppercase tracking-wider text-slate-400 whitespace-nowrap">
                                    Staf Pendukung Administrasi
                                </span>
                            </div>
                            <div v-if="stafLainList.length > 0" class="flex flex-wrap items-center justify-center gap-4 pt-2">
                                <article
                                    v-for="staf in stafLainList"
                                    :key="staf.id"
                                    data-testid="beranda-sotk-card"
                                    class="group w-48 overflow-hidden rounded-lg border border-slate-200 bg-white p-3 text-center shadow-sm"
                                >
                                    <p class="truncate text-xs font-bold text-slate-900">{{ staf.nama_lengkap }}</p>
                                    <p class="truncate text-[11px] text-slate-500">{{ staf.jabatan }}</p>
                                </article>
                            </div>

                        </div>
                    </div>
                </div>
            </section>

            <!-- Seksi 4: Dokumentasi Pembangunan Desa -->
            <section
                id="pembangunan"
                data-testid="beranda-proyek-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Pembangunan Fisik
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Dokumentasi Pembangunan Desa
                    </h2>
                </div>

                <div
                    v-if="(proyek_terbaru || []).length === 0"
                    class="rounded-lg border border-dashed border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
                >
                    Belum ada dokumentasi proyek pembangunan fisik yang dipublikasikan.
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="proyek in proyek_terbaru"
                        :key="proyek.id"
                        data-testid="beranda-proyek-card"
                        class="group flex cursor-pointer flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white transition-all hover:border-blue-300 hover:shadow-sm"
                        @click="openModalSlider(proyek)"
                    >
                        <div>
                            <!-- Foto Sampul Proyek -->
                            <div class="relative flex h-48 w-full items-center justify-center overflow-hidden bg-slate-100">
                                <img
                                    v-if="proyek.foto_100_url || proyek.foto_50_url || proyek.foto_0_url || proyek.foto_sampul_url"
                                    :src="proyek.foto_100_url || proyek.foto_50_url || proyek.foto_0_url || proyek.foto_sampul_url"
                                    :alt="proyek.nama_kegiatan"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center gap-1.5 text-slate-400"
                                >
                                    <Hammer class="h-8 w-8 text-slate-300" />
                                    <span class="text-xs font-medium">Foto belum diunggah</span>
                                </div>
                            </div>

                            <!-- Isi Kartu Simpel -->
                            <div class="space-y-1.5 p-5">
                                <p class="text-xs font-medium tabular-nums text-slate-500">
                                    TA {{ proyek.tahun_anggaran }}
                                </p>

                                <h3 class="text-base font-semibold leading-snug text-slate-900 transition-colors group-hover:text-blue-700">
                                    {{ proyek.nama_kegiatan }}
                                </h3>
                            </div>
                        </div>

                        <!-- Ringkasan Anggaran, Sumber Dana & Tombol Modal Slider -->
                        <div class="space-y-4 border-t border-slate-100 px-5 py-4">
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <div>
                                    <span class="block text-[11px] text-slate-500">Pagu Anggaran</span>
                                    <span class="mt-0.5 block font-semibold tabular-nums text-slate-900">
                                        {{ formatRupiah(proyek.pagu_anggaran) }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="block text-[11px] text-slate-500">Sumber Dana</span>
                                    <span class="mt-0.5 block font-semibold text-slate-900">
                                        {{ proyek.sumber_dana_label || proyek.sumber_dana || '-' }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                data-testid="btn-lihat-foto-progres"
                                class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-blue-200 bg-blue-50 px-4 text-xs font-semibold text-blue-700 transition-colors group-hover:border-blue-700 group-hover:bg-blue-700 group-hover:text-white"
                                @click.stop="openModalSlider(proyek)"
                            >
                                <Camera class="h-4 w-4" />
                                <span>Lihat Foto Progres (0%, 50%, 100%)</span>
                            </button>
                        </div>
                    </article>
                </div>

                <!-- Modal Slider Foto Progres Pembangunan (Titik 0%, 50%, 100%) -->
                <BaseModal
                    :show="isModalOpen"
                    :title="activeModalProyek?.nama_kegiatan || 'Dokumentasi Foto Progres'"
                    :subtitle="modalSubtitle"
                    max-width="2xl"
                    @close="closeModalSlider"
                >
                    <div
                        v-if="activeModalProyek"
                        data-testid="beranda-proyek-modal"
                        class="space-y-4"
                    >
                        <!-- Ringkasan Status & Wilayah Dusun Proyek di Modal -->
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-900">
                                    {{ getStatusLabel(activeModalProyek) }}
                                </span>
                                <span
                                    v-if="activeModalProyek.lokasi_dusun"
                                    class="inline-flex items-center gap-1 font-medium text-slate-600"
                                >
                                    <MapPin class="h-3.5 w-3.5 text-slate-400" />
                                    <span>{{ activeModalProyek.lokasi_dusun }}</span>
                                </span>
                            </div>
                            <div class="tabular-nums text-slate-500">
                                TA {{ activeModalProyek.tahun_anggaran }}
                                <span v-if="activeModalProyek.sumber_dana_label || activeModalProyek.sumber_dana">
                                    &bull; <span>{{ activeModalProyek.sumber_dana_label || activeModalProyek.sumber_dana }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Viewport Foto Bersih & Terang -->
                        <div class="relative flex h-72 w-full items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-100 sm:h-96">
                            <img
                                v-if="activeSlidePhotoUrl"
                                :src="activeSlidePhotoUrl"
                                :alt="`${activeModalProyek.nama_kegiatan} - ${activeStage.label}`"
                                data-testid="slider-active-image"
                                class="h-full w-full object-contain"
                            />
                            <div
                                v-else
                                data-testid="slider-empty-photo"
                                class="flex flex-col items-center justify-center space-y-2 p-6 text-center"
                            >
                                <Camera class="h-8 w-8 text-slate-300" />
                                <p class="text-sm font-medium text-slate-600">
                                    Foto belum didokumentasikan untuk tahap ini
                                </p>
                                <p class="text-xs text-slate-400">
                                    {{ activeStage.label }}
                                </p>
                            </div>
                        </div>

                        <!-- Kontrol Navigasi Standar: Sebelumnya / Info Tahap / Berikutnya -->
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3">
                            <button
                                type="button"
                                aria-label="Foto Sebelumnya"
                                data-testid="slider-prev-btn"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                @click="prevSlide"
                            >
                                <ChevronLeft class="h-4 w-4" />
                                <span class="hidden sm:inline">Sebelumnya</span>
                            </button>

                            <div class="min-w-0 text-center">
                                <p
                                    data-testid="slider-stage-label"
                                    class="text-xs font-bold text-slate-900"
                                >
                                    {{ activeStage.label }}
                                </p>
                                <p class="truncate text-[11px] text-slate-500">
                                    {{ activeStage.desc }}
                                </p>
                            </div>

                            <button
                                type="button"
                                aria-label="Foto Berikutnya"
                                data-testid="slider-next-btn"
                                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                @click="nextSlide"
                            >
                                <span class="hidden sm:inline">Berikutnya</span>
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>

                        <!-- Keterangan Pelaksana & Volume -->
                        <div
                            v-if="activeModalProyek.pelaksana_kegiatan || activeModalProyek.volume_fisik"
                            class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2 text-xs text-slate-500"
                        >
                            <span v-if="activeModalProyek.pelaksana_kegiatan">
                                Pelaksana: <strong class="font-medium text-slate-700">{{ activeModalProyek.pelaksana_kegiatan }}</strong>
                            </span>
                            <span v-if="activeModalProyek.volume_fisik">
                                Volume: <strong class="font-medium text-slate-700">{{ activeModalProyek.volume_fisik }}</strong>
                            </span>
                        </div>
                    </div>
                </BaseModal>
            </section>

            <!-- Seksi 5: Kabar & Kegiatan Desa Terkini (Section Sendiri) -->
            <section
                id="kegiatan"
                data-testid="beranda-kegiatan-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Kabar Desa
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Kegiatan &amp; Informasi Desa
                    </h2>
                </div>

                <!-- Keadaan Kosong (Empty State) -->
                <div
                    v-if="topKegiatan.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-white p-8 text-center"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                        <Calendar class="h-5 w-5" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-900">
                        Belum Ada Publikasi Kegiatan
                    </p>
                </div>

                <!-- Grid 3 Kolom Kartu Warta -->
                <div
                    v-else
                    class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <article
                        v-for="item in topKegiatan"
                        :key="item.id"
                        data-testid="beranda-kegiatan-card"
                        class="group flex flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white transition-all hover:border-blue-300 hover:shadow-sm"
                    >
                        <div>
                            <!-- Foto Sampul Kegiatan -->
                            <div class="h-48 w-full overflow-hidden bg-slate-100">
                                <img
                                    v-if="item.foto_sampul_url"
                                    :src="item.foto_sampul_url"
                                    :alt="item.judul"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full flex-col items-center justify-center gap-1.5 text-slate-400"
                                >
                                    <ImageIcon class="h-8 w-8 text-slate-300" />
                                    <span class="text-xs font-medium">Dokumentasi Foto</span>
                                </div>
                            </div>

                            <!-- Konten Kartu -->
                            <div class="space-y-2.5 p-5">
                                <!-- Tanggal Kegiatan -->
                                <div
                                    v-if="item.tanggal_kegiatan"
                                    class="text-xs text-slate-500"
                                >
                                    <span class="inline-flex items-center gap-1.5 font-medium tabular-nums">
                                        <Calendar class="h-3.5 w-3.5 text-slate-400" />
                                        {{ formatDateId(item.tanggal_kegiatan) }}
                                    </span>
                                </div>

                                <!-- Judul Kegiatan -->
                                <h3 class="text-base font-semibold leading-snug text-slate-900 transition-colors group-hover:text-blue-700">
                                    <Link
                                        :href="`/kegiatan/${item.slug}`"
                                        class="line-clamp-2 focus:outline-none"
                                        :title="item.judul"
                                    >
                                        {{ item.judul }}
                                    </Link>
                                </h3>

                                <!-- Ringkasan / Snippet Uraian -->
                                <p
                                    v-if="item.ringkasan"
                                    class="line-clamp-2 text-xs leading-relaxed text-slate-600 sm:text-sm"
                                >
                                    {{ item.ringkasan }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer Kartu: Tautan Baca Selengkapnya -->
                        <div class="border-t border-slate-100 bg-slate-50 px-5 py-3.5">
                            <Link
                                :href="`/kegiatan/${item.slug}`"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 transition-colors hover:text-blue-800"
                            >
                                <span>Baca Dokumentasi Lengkap</span>
                                <ArrowRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        </div>
                    </article>
                </div>

                <div class="flex justify-center">
                    <Link
                        href="/kegiatan"
                        data-testid="link-selengkapnya-kegiatan"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900"
                    >
                        <span>Lihat Semua Kegiatan</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </section>

            <!-- Seksi Baru: Dokumen Publik PPID Terbaru (#ppid) -->
            <section
                id="ppid"
                data-testid="beranda-ppid-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Keterbukaan Informasi
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Dokumen Publik PPID
                    </h2>
                </div>

                <div
                    v-if="topDokumenPpid.length === 0"
                    class="flex flex-col items-center justify-center rounded-lg border border-dashed border-slate-200 bg-white p-8 text-center"
                >
                    <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-slate-100 text-slate-400">
                        <FileText class="h-5 w-5" />
                    </div>
                    <p class="mt-3 text-sm font-semibold text-slate-900">
                        Belum Ada Dokumen PPID Publik
                    </p>
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-4 md:grid-cols-2"
                >
                    <article
                        v-for="dok in topDokumenPpid"
                        :key="dok.id"
                        data-testid="beranda-ppid-card"
                        class="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-5 sm:flex-row sm:items-center"
                    >
                        <div class="min-w-0 flex-1 space-y-1">
                            <p class="text-xs font-medium text-slate-500">
                                <span>{{ dok.kategori_label }}</span>
                                <span v-if="dok.tahun_dokumen"> &bull; Tahun {{ dok.tahun_dokumen }}</span>
                                <span> &bull; {{ formatUkuranKb(dok.ukuran_file_kb) }}</span>
                            </p>
                            <h3 class="truncate text-base font-semibold text-slate-900" :title="dok.judul_dokumen">
                                {{ dok.judul_dokumen }}
                            </h3>
                        </div>

                        <div class="shrink-0">
                            <a
                                :href="dok.download_url"
                                data-testid="beranda-ppid-download-btn"
                                class="inline-flex h-10 items-center justify-center gap-1.5 rounded-md border border-slate-300 bg-white px-3.5 text-xs font-semibold text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900"
                            >
                                <Download class="h-3.5 w-3.5" />
                                <span>Unduh PDF</span>
                            </a>
                        </div>
                    </article>
                </div>

                <div class="flex justify-center">
                    <Link
                        href="/unduhan"
                        data-testid="link-selengkapnya-ppid"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 hover:text-slate-900"
                    >
                        <span>Lihat Semua Dokumen PPID</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </section>

            <!-- Seksi 6: Layanan Mandiri Warga & Cek Bansos Desa -->
            <section
                id="layanan-mandiri"
                data-testid="beranda-layanan-mandiri-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-1.5 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Layanan Publik
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                        Layanan Mandiri Warga
                    </h2>
                </div>

                <!-- 2 Kartu Layanan Unggulan -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Kartu 1: Pengajuan Surat Online -->
                    <div
                        data-testid="card-layanan-surat"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6 transition-all hover:border-blue-300 hover:shadow-sm"
                    >
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold text-slate-900">
                                    Pengajuan Surat Online
                                </h3>
                                <p class="text-sm leading-relaxed text-slate-600">
                                    Ajukan permohonan Surat Keterangan Usaha (SKU), Domisili, SKTM, Pengantar SKCK, dan surat resmi lainnya secara mandiri hanya dengan NIK dan tanggal lahir.
                                </p>
                            </div>

                            <!-- Poin Keunggulan -->
                            <ul class="space-y-2.5 border-t border-slate-100 pt-4 text-sm text-slate-600">
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-blue-700" />
                                    <span>Verifikasi instan identitas kependudukan terpadu</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-blue-700" />
                                    <span>Dilengkapi QR Code verifikasi legalitas dokumen resmi</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-blue-700" />
                                    <span>Notifikasi dan koordinasi cepat petugas via WhatsApp</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-6">
                            <Link
                                href="/layanan-mandiri?tab=pengajuan_surat"
                                class="inline-flex h-11 min-h-[44px] w-full items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-1"
                            >
                                Ajukan Surat Sekarang
                            </Link>
                        </div>
                    </div>

                    <!-- Kartu 2: Cek Bansos Mandiri -->
                    <div
                        data-testid="card-layanan-bansos"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6 transition-all hover:border-blue-300 hover:shadow-sm"
                    >
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <h3 class="text-lg font-semibold text-slate-900">
                                    Cek Bantuan Sosial Desa
                                </h3>
                                <p class="text-sm leading-relaxed text-slate-600">
                                    Periksa status kepesertaan keluarga Anda pada program BLT Dana Desa, PKH, BPNT, dan bantuan sosial pemerintah secara transparan dan akurat.
                                </p>
                            </div>

                            <!-- Poin Keunggulan -->
                            <ul class="space-y-2.5 border-t border-slate-100 pt-4 text-sm text-slate-600">
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>Pencocokan data riil kependudukan desa terverifikasi</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>Penyamaran NIK &amp; nama warga sesuai UU PDP No. 27/2022</span>
                                </li>
                                <li class="flex items-start gap-2.5">
                                    <CheckCircle2 class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>Akses publik terbuka tanpa pungutan biaya</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-6">
                            <Link
                                href="/layanan-mandiri?tab=cek_bansos"
                                class="inline-flex h-11 min-h-[44px] w-full items-center justify-center rounded-md bg-emerald-600 px-5 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-1"
                            >
                                Cek Status Bansos
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
