<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Briefcase,
    Calendar,
    Camera,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    Compass,
    FileCheck2,
    Hammer,
    HandHeart,
    Home,
    ImageIcon,
    MapPin,
    MessageCircle,
    Newspaper,
    Quote,
    ShieldCheck,
    User,
    Users,
} from 'lucide-vue-next';
import BaseModal from '@/Components/BaseModal.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
});

const page = usePage();
const namaDesa = computed(
    () => props.profil?.nama_desa || page.props?.profil_desa?.nama_desa || 'Tamarenja',
);

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

function countAvailablePhotos(proyek) {
    if (!proyek) {
        return 0;
    }
    return ['foto_0_url', 'foto_50_url', 'foto_100_url'].filter((key) => Boolean(proyek[key])).length;
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
                    <div class="max-w-3xl py-14 sm:py-20 lg:py-24 space-y-6">
                        <!-- Lokasi Administratif (Clean Typography, Bebas Badge Padat) -->
                        <p class="text-xs font-medium tracking-widest uppercase text-blue-300 sm:text-sm">
                            Pemerintah Desa {{ namaDesa }} &bull; Kec. Sindue Tobata, Kab. Donggala
                        </p>

                        <!-- Headline Utama -->
                        <h1 class="text-3xl font-bold tracking-tight text-white sm:text-4xl lg:text-5xl leading-tight">
                            Portal Resmi Informasi &amp; Layanan Publik Desa
                        </h1>

                        <!-- Sambutan Singkat -->
                        <blockquote class="border-l-3 border-blue-500 pl-4 text-sm leading-relaxed text-slate-300 sm:text-base">
                            "{{ sambutan.sambutan_kepala_desa }}"
                        </blockquote>

                        <!-- CTA Buttons -->
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <a
                                href="/layanan-mandiri"
                                data-testid="cta-layanan-mandiri"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 focus:ring-offset-slate-900"
                            >
                                <FileCheck2 class="h-4 w-4" />
                                <span>Ajukan Surat &amp; Cek Bansos</span>
                            </a>

                            <a
                                href="#profil"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-slate-700 bg-slate-800/80 px-4 text-sm font-medium text-slate-200 transition hover:bg-slate-700/80 hover:text-white"
                            >
                                <span>Profil Desa</span>
                            </a>

                            <Link
                                href="/unduhan"
                                data-testid="hero-link-unduhan"
                                class="inline-flex h-11 items-center justify-center gap-1.5 px-3 text-sm font-medium text-blue-300 transition hover:text-white hover:underline"
                            >
                                <span>Pusat Unduhan PPID</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </template>

        <div class="space-y-14">
            <!-- Seksi 2: Profil Desa, Visi-Misi & Bagan SOTK Bergaris Hubung ala Baliho Kantor Desa -->
            <section
                id="profil"
                data-testid="beranda-profil-section"
                class="scroll-mt-20 space-y-12"
            >
                <!-- Section Header (Clean, Modern, Centered) -->
                <div class="mx-auto max-w-3xl space-y-2 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Profil Pemerintahan Desa
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                        Mengenal Lebih Dekat Desa {{ namaDesa }}
                    </h2>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Gambaran umum wilayah, visi-misi pembangunan berkelanjutan, serta bagan struktur organisasi kepemimpinan desa.
                    </p>
                </div>

                <!-- Bagian 1: Narasi Wilayah & 2 Card Visi-Misi yang Disetujui -->
                <div class="grid grid-cols-1 items-start gap-10 lg:grid-cols-12 lg:gap-12">
                    <!-- Kolom Kiri (7 Cols): Frameless Editorial Layout (Bebas dari Wadah Card) -->
                    <div class="space-y-6 lg:col-span-7">
                        <div class="space-y-3">
                            <p class="text-xs font-bold uppercase tracking-widest text-blue-600">
                                Sejarah &amp; Karakteristik Wilayah
                            </p>
                            <h3 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl leading-snug">
                                Membangun Kemandirian &amp; Kesejahteraan Desa {{ namaDesa }}
                            </h3>
                            <div class="space-y-4 text-sm leading-relaxed text-slate-600 sm:text-base">
                                <p>
                                    {{
                                        profil.sejarah_singkat ||
                                        `Desa ${namaDesa} merupakan salah satu desa di wilayah pesisir Kecamatan ${profil.kecamatan || 'Sindue Tobata'}, Kabupaten ${profil.kabupaten || 'Donggala'}, Provinsi ${profil.provinsi || 'Sulawesi Tengah'}. Berdiri di atas landasan kebersamaan dan kearifan lokal yang kuat, desa kami terus bergerak maju melalui tata kelola pemerintahan yang partisipatif, transparan, dan berkeadilan.`
                                    }}
                                </p>
                                <p>
                                    {{
                                        profil.potensi_unggulan ||
                                        'Dengan bentang alam strategis yang menghubungkan perbukitan subur dan pesisir Selat Makassar, komoditas unggulan desa bertumpu pada perkebunan kelapa, cengkeh, tanaman pangan hortikultura, serta potensi perikanan tangkap yang menjadi urat nadi perekonomian masyarakat.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <!-- Data Administratif & Demografi Terbuka (Hairline Dividers, Bukan Card) -->
                        <div class="space-y-6 border-t border-slate-200/80 pt-6">
                            <!-- Kelompok 1: Wilayah Administratif -->
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Kecamatan</dt>
                                    <dd class="mt-1 text-sm sm:text-base font-semibold text-slate-900">
                                        {{ profil.kecamatan || 'Sindue Tobata' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Kabupaten</dt>
                                    <dd class="mt-1 text-sm sm:text-base font-semibold text-slate-900">
                                        {{ profil.kabupaten || 'Donggala' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Wilayah Kewilayahan</dt>
                                    <dd class="mt-1 text-sm sm:text-base font-semibold text-slate-900">
                                        {{
                                            profil.daftar_dusun?.length
                                                ? `${profil.daftar_dusun.length} Dusun Terdaftar`
                                                : '3 Dusun Administratif'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Kode Wilayah Kemendagri</dt>
                                    <dd class="mt-1 font-mono text-sm sm:text-base font-medium text-slate-700">
                                        {{ profil.kode_wilayah || '72.03.11.2001' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Provinsi</dt>
                                    <dd class="mt-1 text-sm sm:text-base font-semibold text-slate-900">
                                        {{ profil.provinsi || 'Sulawesi Tengah' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Jam Pelayanan Kantor</dt>
                                    <dd class="mt-1 text-sm sm:text-base font-medium text-slate-700">
                                        {{ profil.jam_pelayanan || 'Senin - Jumat (08.00 - 15.00)' }}
                                    </dd>
                                </div>
                            </dl>

                            <!-- Kelompok 2: Data Demografi Warga & Mata Pencaharian (Frameless) -->
                            <div data-testid="beranda-statistik-section" class="border-t border-slate-100 pt-5">
                                <dl class="grid grid-cols-2 gap-x-6 gap-y-5 sm:grid-cols-3">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Total Penduduk</dt>
                                        <dd class="mt-1 text-sm sm:text-base font-bold text-slate-900">
                                            {{ Number(statistik_penduduk.total_penduduk || 0).toLocaleString('id-ID') }} <span class="text-xs font-normal text-slate-500">Jiwa</span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Kepala Keluarga (KK)</dt>
                                        <dd class="mt-1 text-sm sm:text-base font-bold text-slate-900">
                                            {{ Number(statistik_penduduk.total_kk || 0).toLocaleString('id-ID') }} <span class="text-xs font-normal text-slate-500">KK</span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Komposisi Gender</dt>
                                        <dd class="mt-1 text-sm sm:text-base font-semibold text-slate-900">
                                            {{ Number(statistik_penduduk.laki_laki || 0).toLocaleString('id-ID') }} L <span class="text-slate-300">/</span> {{ Number(statistik_penduduk.perempuan || 0).toLocaleString('id-ID') }} P
                                        </dd>
                                    </div>
                                    <div class="col-span-2 sm:col-span-3">
                                        <dt class="text-xs font-medium uppercase tracking-wider text-slate-400">Mata Pencaharian Utama</dt>
                                        <dd class="mt-1 text-sm text-slate-800">
                                            <span v-if="statistik_penduduk.pekerjaan_utama && statistik_penduduk.pekerjaan_utama.length > 0" class="leading-relaxed">
                                                {{ statistik_penduduk.pekerjaan_utama.map(p => `${p.nama} (${p.jumlah})`).join(' • ') }}
                                            </span>
                                            <span v-else class="text-slate-400 italic">Belum ada data mata pencaharian</span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan (5 Cols): 2 Card yang Disetujui (Visi & Misi) -->
                    <div class="space-y-5 lg:col-span-5">
                        <!-- Card 1: Visi Utama Pemerintah Desa (Executive Deep Navy) -->
                        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900 to-blue-950 p-6 sm:p-7 text-white shadow-md">
                            <!-- Subtle Ambient Glow -->
                            <div class="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-blue-500/20 blur-2xl"></div>

                            <div class="relative space-y-3">
                                <div class="flex items-center gap-2 text-xs font-semibold tracking-wider uppercase text-blue-300">
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

                        <!-- Card 2: 5 Misi Pembangunan Desa (Crisp Numbered List) -->
                        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-2xs">
                            <h4 class="text-xs font-semibold tracking-wider uppercase text-slate-500">
                                Misi Pembangunan Desa
                            </h4>
                            <ol
                                v-if="profil.misi && profil.misi.length > 0"
                                class="mt-4 space-y-3"
                            >
                                <li
                                    v-for="(butir, idx) in profil.misi"
                                    :key="idx"
                                    class="flex items-start gap-3"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">
                                        {{ idx + 1 }}
                                    </span>
                                    <span class="text-sm leading-relaxed text-slate-700">
                                        {{ butir }}
                                    </span>
                                </li>
                            </ol>
                            <ol v-else class="mt-4 space-y-3">
                                <li class="flex items-start gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">1</span>
                                    <span class="text-sm leading-relaxed text-slate-700">Meningkatkan tata kelola pemerintahan yang bersih, melayani, dan partisipatif.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">2</span>
                                    <span class="text-sm leading-relaxed text-slate-700">Mendorong percepatan pembangunan infrastruktur dan fasilitas umum desa yang merata.</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs font-bold text-blue-700">3</span>
                                    <span class="text-sm leading-relaxed text-slate-700">Memberdayakan potensi ekonomi kerakyatan melalui pertanian, perkebunan, dan UMKM.</span>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Bagan SOTK Bergaris Hubung ala Baliho Kantor Desa (Permendagri No. 84/2015) -->
                <div class="space-y-6 pt-6">
                    <div class="border-b border-slate-200 pb-4">
                        <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                            Papan Struktur Organisasi (Permendagri No. 84/2015)
                        </p>
                        <h3 class="mt-1 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                            Bagan Struktur Kepemimpinan &amp; Lembaga Desa
                        </h3>
                    </div>

                    <!-- Petunjuk Geser Horizontal di Mobile -->
                    <div class="flex items-center justify-end gap-1.5 text-xs text-slate-400 sm:hidden">
                        <span>&larr; Geser untuk melihat bagan lengkap &rarr;</span>
                    </div>

                    <!-- Papan Kanvas SOTK (Scrollable Canvas ala Cetak Biru / Baliho Desa) -->
                    <div class="relative overflow-x-auto rounded-2xl border border-slate-200/90 bg-slate-50/70 p-6 sm:p-10 shadow-2xs">
                        <div class="min-w-[960px] mx-auto flex flex-col items-center">
                            
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
                                        class="group relative w-60 overflow-hidden rounded-2xl border-2 border-blue-600 bg-white shadow-md transition-all hover:shadow-lg"
                                    >
                                        <div class="bg-blue-600 py-1.5 text-center text-xs font-bold uppercase tracking-wider text-white">
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
                                        class="group relative w-56 overflow-hidden rounded-xl border border-slate-300 bg-white shadow-xs transition-all hover:border-blue-500 hover:shadow-md"
                                    >
                                        <div class="bg-slate-100 py-1 text-center text-xs font-bold uppercase tracking-wider text-slate-700">
                                            Sekretaris Desa
                                        </div>
                                        <div class="relative flex h-40 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="sekdes.foto_url"
                                                :src="sekdes.foto_url"
                                                :alt="sekdes.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-xs transition-transform duration-300 group-hover:scale-105"
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
                                            class="group relative w-56 overflow-hidden rounded-2xl border-2 border-slate-700 bg-white shadow-md transition-all hover:shadow-lg"
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
                                                        class="group w-48 sm:w-52 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition-all hover:border-slate-300 hover:shadow-xs"
                                                    >
                                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                                            <img
                                                                v-if="anggota.foto_url"
                                                                :src="anggota.foto_url"
                                                                :alt="anggota.nama_lengkap"
                                                                class="h-full w-auto object-contain object-bottom drop-shadow-xs transition-transform duration-200 group-hover:scale-105"
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
                                        class="group w-48 sm:w-52 h-[216px] flex flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition-all hover:border-slate-300 hover:shadow-xs"
                                    >
                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="kaur.foto_url"
                                                :src="kaur.foto_url"
                                                :alt="kaur.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-xs transition-transform duration-200 group-hover:scale-105"
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
                                        class="group w-48 sm:w-52 h-[216px] flex flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition-all hover:border-blue-400 hover:shadow-xs"
                                    >
                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="kasi.foto_url"
                                                :src="kasi.foto_url"
                                                :alt="kasi.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-xs transition-transform duration-200 group-hover:scale-105"
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
                                        class="group w-48 sm:w-52 h-[216px] flex flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition-all hover:border-amber-400 hover:shadow-xs"
                                    >
                                        <div class="relative flex h-36 w-full items-end justify-center overflow-hidden bg-gradient-to-t from-slate-100 via-slate-50 to-white px-2 pt-2">
                                            <img
                                                v-if="kadus.foto_url"
                                                :src="kadus.foto_url"
                                                :alt="kadus.nama_lengkap"
                                                class="h-full w-auto object-contain object-bottom drop-shadow-xs transition-transform duration-200 group-hover:scale-105"
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
                                    class="group w-48 overflow-hidden rounded-xl border border-slate-200 bg-white p-3 text-center shadow-2xs"
                                >
                                    <p class="truncate text-xs font-bold text-slate-900">{{ staf.nama_lengkap }}</p>
                                    <p class="truncate text-[11px] text-slate-500">{{ staf.jabatan }}</p>
                                </article>
                            </div>

                        </div>
                    </div>
                </div>
            </section>

            <!-- Seksi 3: Dokumentasi Pembangunan Desa (Simpel + Modal Slider Foto 0%, 50%, 100%) -->
            <section
                id="pembangunan"
                data-testid="beranda-proyek-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200/80 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-2 text-center">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-700">
                        Dokumentasi Infrastruktur Desa
                    </p>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                        Dokumentasi Pembangunan Desa
                    </h2>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Informasi proyek pembangunan fisik serta dokumentasi foto progres lapangan di Desa {{ namaDesa }}.
                    </p>
                </div>

                <div
                    v-if="(proyek_terbaru || []).length === 0"
                    class="rounded-xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500"
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
                        class="group flex cursor-pointer flex-col justify-between overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs transition-all hover:border-blue-300 hover:shadow-md"
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
                                    <span class="text-[11px] font-medium">Foto belum diunggah</span>
                                </div>

                                <!-- Indikator Jumlah Foto Progres (Kanan Bawah) -->
                                <span
                                    v-if="countAvailablePhotos(proyek) > 0"
                                    class="absolute bottom-3 right-3 inline-flex items-center gap-1 rounded-full bg-slate-900/75 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur-xs"
                                >
                                    <Camera class="h-3 w-3 text-blue-300" />
                                    <span>{{ countAvailablePhotos(proyek) }}/3 Foto</span>
                                </span>
                            </div>

                            <!-- Isi Kartu Simpel -->
                            <div class="space-y-2 p-5">
                                <p class="text-xs font-medium tabular-nums text-slate-500">
                                    TA {{ proyek.tahun_anggaran }}
                                </p>

                                <h3 class="text-base font-bold leading-snug text-slate-900 transition-colors group-hover:text-blue-700">
                                    {{ proyek.nama_kegiatan }}
                                </h3>
                            </div>
                        </div>

                        <!-- Ringkasan Anggaran, Sumber Dana & Tombol Modal Slider -->
                        <div class="space-y-4 border-t border-slate-100 px-5 py-4">
                            <div class="flex items-start justify-between gap-3 text-xs">
                                <div>
                                    <span class="block text-[11px] text-slate-400">Pagu Anggaran</span>
                                    <span class="mt-0.5 block font-bold tabular-nums text-slate-900">
                                        {{ formatRupiah(proyek.pagu_anggaran) }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="block text-[11px] text-slate-400">Sumber Dana</span>
                                    <span class="mt-0.5 inline-block rounded bg-blue-50 px-2 py-0.5 text-[11px] font-semibold text-blue-700">
                                        {{ proyek.sumber_dana_label || proyek.sumber_dana || '-' }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                data-testid="btn-lihat-foto-progres"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-blue-50 px-4 py-2.5 text-xs font-semibold text-blue-700 transition-colors group-hover:bg-blue-700 group-hover:text-white"
                                @click.stop="openModalSlider(proyek)"
                            >
                                <Camera class="h-4 w-4" />
                                <span>Lihat Foto Progres (0%, 50%, 100%)</span>
                            </button>
                        </div>
                    </article>
                </div>
            </section>

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
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <StatusBadge :status="activeModalProyek.status_proyek" />
                            <span
                                v-if="activeModalProyek.lokasi_dusun"
                                class="inline-flex items-center gap-1 text-xs font-medium text-slate-600"
                            >
                                <MapPin class="h-3.5 w-3.5 text-blue-600" />
                                <span>{{ activeModalProyek.lokasi_dusun }}</span>
                            </span>
                        </div>
                        <div class="text-xs text-slate-500">
                            TA {{ activeModalProyek.tahun_anggaran }}
                            <span v-if="activeModalProyek.sumber_dana_label || activeModalProyek.sumber_dana">
                                &bull; <span class="font-medium text-blue-700">{{ activeModalProyek.sumber_dana_label || activeModalProyek.sumber_dana }}</span>
                            </span>
                        </div>
                    </div>

                    <!-- Tab Pilihan Tahap Progres -->
                    <div class="grid grid-cols-3 gap-2 rounded-lg bg-slate-100 p-1">
                        <button
                            v-for="(stage, idx) in sliderStages"
                            :key="stage.percent"
                            type="button"
                            :data-testid="`slider-tab-${idx}`"
                            :class="[
                                'rounded-md px-3 py-2 text-xs font-semibold transition-all',
                                activeSlide === idx
                                    ? 'bg-white text-blue-700 shadow-xs'
                                    : 'text-slate-600 hover:text-slate-900',
                            ]"
                            @click="activeSlide = idx"
                        >
                            {{ stage.tabLabel }}
                        </button>
                    </div>

                    <!-- Viewport Gambar Slider + Kontrol Panah Kiri/Kanan -->
                    <div class="relative flex h-64 w-full items-center justify-center overflow-hidden rounded-xl bg-slate-950 sm:h-96">
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
                            <Camera class="h-10 w-10 text-slate-600" />
                            <p class="text-sm font-medium text-slate-300">
                                Foto belum didokumentasikan untuk tahap ini
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ activeStage.label }}
                            </p>
                        </div>

                        <button
                            type="button"
                            aria-label="Foto Sebelumnya"
                            data-testid="slider-prev-btn"
                            class="absolute left-3 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-slate-900/75 text-white backdrop-blur-xs transition hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-white"
                            @click="prevSlide"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>

                        <button
                            type="button"
                            aria-label="Foto Berikutnya"
                            data-testid="slider-next-btn"
                            class="absolute right-3 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-slate-900/75 text-white backdrop-blur-xs transition hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-white"
                            @click="nextSlide"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>

                        <!-- Indikator Titik Slide Bawah -->
                        <div class="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full bg-slate-900/75 px-3 py-1.5 backdrop-blur-xs">
                            <button
                                v-for="(stage, idx) in sliderStages"
                                :key="stage.percent"
                                type="button"
                                :aria-label="`Pilih ${stage.tabLabel}`"
                                :class="[
                                    'h-2 rounded-full transition-all',
                                    activeSlide === idx ? 'w-5 bg-white' : 'w-2 bg-white/40 hover:bg-white/75',
                                ]"
                                @click="activeSlide = idx"
                            />
                        </div>
                    </div>

                    <!-- Keterangan Tahap & Pelaksana -->
                    <div class="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center">
                        <div>
                            <p
                                data-testid="slider-stage-label"
                                class="text-xs font-bold uppercase tracking-wider text-blue-700"
                            >
                                {{ activeStage.label }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                {{ activeStage.desc }}
                            </p>
                        </div>
                        <div class="text-xs text-slate-500 sm:text-right">
                            <p v-if="activeModalProyek.pelaksana_kegiatan">
                                Pelaksana:
                                <span class="font-semibold text-slate-800">{{ activeModalProyek.pelaksana_kegiatan }}</span>
                            </p>
                            <p v-if="activeModalProyek.volume_fisik">
                                Volume:
                                <span class="font-semibold text-slate-800">{{ activeModalProyek.volume_fisik }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </BaseModal>

            <!-- Seksi 4: Kabar & Dokumentasi Kegiatan Desa Terkini -->
            <section
                id="kegiatan"
                data-testid="beranda-kegiatan-section"
                class="space-y-6"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-700">
                            <Newspaper class="h-3.5 w-3.5" />
                            Agenda &amp; Warta Desa
                        </span>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                            Kabar &amp; Kegiatan Desa Terkini
                        </h2>
                        <p class="mt-1 text-sm text-slate-600">
                            Dokumentasi musyawarah desa, gotong royong, pembangunan sosial, dan agenda pelayanan kemasyarakatan.
                        </p>
                    </div>

                    <Link
                        href="/kegiatan"
                        data-testid="link-selengkapnya-kegiatan"
                        class="group inline-flex items-center gap-1.5 text-sm font-semibold text-blue-700 transition-colors hover:text-blue-800"
                    >
                        <span>Lihat Semua Kegiatan</span>
                        <ArrowRight class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                    </Link>
                </div>

                <!-- Keadaan Kosong (Empty State) -->
                <div
                    v-if="topKegiatan.length === 0"
                    class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-white p-12 text-center shadow-2xs"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                        <Calendar class="h-6 w-6" />
                    </div>
                    <h3 class="mt-4 text-base font-semibold text-slate-900">
                        Belum Ada Publikasi Kegiatan
                    </h3>
                    <p class="mt-1 max-w-md text-xs text-slate-500 sm:text-sm">
                        Dokumentasi agenda dan kegiatan desa yang telah dipublikasikan akan ditampilkan secara terpusat di sini.
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
                        class="group flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-slate-300 hover:shadow-md"
                    >
                        <div>
                            <!-- Foto Sampul Kegiatan dengan Zoom Hover -->
                            <div class="relative h-48 sm:h-52 w-full overflow-hidden bg-slate-100">
                                <img
                                    v-if="item.foto_sampul_url"
                                    :src="item.foto_sampul_url"
                                    :alt="item.judul"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                    loading="lazy"
                                />
                                <div
                                    v-else
                                    class="flex h-full w-full flex-col items-center justify-center gap-2 bg-gradient-to-br from-slate-100 to-slate-200 text-slate-400"
                                >
                                    <ImageIcon class="h-10 w-10 text-slate-300" />
                                    <span class="text-xs font-medium text-slate-400">Dokumentasi Foto</span>
                                </div>

                                <!-- Badge Kategori Tersemat di Kiri Atas Foto -->
                                <span
                                    v-if="item.kategori_label"
                                    class="absolute left-3.5 top-3.5 inline-flex items-center rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold text-blue-700 shadow-2xs backdrop-blur-xs"
                                >
                                    {{ item.kategori_label }}
                                </span>
                            </div>

                            <!-- Konten Kartu -->
                            <div class="space-y-3 p-5 sm:p-6">
                                <!-- Meta Tanggal & Lokasi Dusun -->
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500">
                                    <span
                                        v-if="item.tanggal_kegiatan"
                                        class="inline-flex items-center gap-1.5 font-medium tabular-nums"
                                    >
                                        <Calendar class="h-3.5 w-3.5 text-slate-400" />
                                        {{ formatDateId(item.tanggal_kegiatan) }}
                                    </span>
                                    <span
                                        v-if="item.lokasi_dusun"
                                        class="inline-flex items-center gap-1 font-medium text-slate-600"
                                    >
                                        <MapPin class="h-3.5 w-3.5 text-blue-600" />
                                        {{ item.lokasi_dusun }}
                                    </span>
                                </div>

                                <!-- Judul Kegiatan -->
                                <h3 class="text-base sm:text-lg font-bold leading-snug text-slate-900 transition-colors group-hover:text-blue-700">
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
                                    class="line-clamp-2 text-xs sm:text-sm leading-relaxed text-slate-600"
                                >
                                    {{ item.ringkasan }}
                                </p>
                            </div>
                        </div>

                        <!-- Footer Kartu: Tautan Baca Selengkapnya -->
                        <div class="border-t border-slate-100 bg-slate-50/50 px-5 py-3.5 sm:px-6">
                            <Link
                                :href="`/kegiatan/${item.slug}`"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 transition-colors hover:text-blue-800"
                            >
                                <span>Baca Dokumentasi Lengkap</span>
                                <ArrowRight class="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" />
                            </Link>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Seksi 5: Layanan Mandiri Warga & Cek Bansos Desa -->
            <section
                id="layanan-mandiri"
                data-testid="beranda-layanan-mandiri-section"
                class="scroll-mt-24 space-y-8 border-t border-slate-200/80 pt-12"
            >
                <div class="mx-auto max-w-3xl space-y-2 text-center">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-700">
                        <ShieldCheck class="h-3.5 w-3.5" />
                        Pelayanan Cepat &amp; Transparan
                    </span>
                    <h2 class="mt-2 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                        Layanan Mandiri Warga Desa {{ namaDesa }}
                    </h2>
                    <p class="text-sm leading-relaxed text-slate-600">
                        Akses pengurusan surat administrasi resmi dan pengecekan status bantuan sosial secara mandiri, cepat, dan terlindungi sesuai ketentuan UU PDP.
                    </p>
                </div>

                <!-- 2 Kartu Layanan Unggulan -->
                <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <!-- Kartu 1: Pengajuan Surat Online -->
                    <div
                        data-testid="card-layanan-surat"
                        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-blue-200 hover:shadow-md"
                    >
                        <div class="space-y-5">
                            <div class="flex items-center justify-between">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                    <FileCheck2 class="h-6 w-6" />
                                </div>
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                    10 Jenis Surat
                                </span>
                            </div>

                            <div class="space-y-2">
                                <h3 class="text-xl font-bold text-slate-900 transition-colors group-hover:text-blue-700">
                                    Pengajuan Surat Online
                                </h3>
                                <p class="text-sm leading-relaxed text-slate-600">
                                    Ajukan permohonan Surat Keterangan Usaha (SKU), Domisili, SKTM, Pengantar SKCK, dan surat resmi lainnya secara mandiri hanya dengan NIK dan tanggal lahir.
                                </p>
                            </div>

                            <!-- Poin Keunggulan -->
                            <ul class="space-y-2.5 border-t border-slate-100 pt-4 text-xs sm:text-sm text-slate-600">
                                <li class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 shrink-0 text-blue-600" />
                                    <span>Verifikasi instan identitas kependudukan terpadu</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 shrink-0 text-blue-600" />
                                    <span>Dilengkapi QR Code verifikasi legalitas dokumen resmi</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 shrink-0 text-blue-600" />
                                    <span>Notifikasi dan koordinasi cepat petugas via WhatsApp</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-6">
                            <Link
                                href="/layanan-mandiri?tab=pengajuan_surat"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            >
                                <span>Ajukan Surat Sekarang</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>

                    <!-- Kartu 2: Cek Bansos Mandiri -->
                    <div
                        data-testid="card-layanan-bansos"
                        class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-md"
                    >
                        <div class="space-y-5">
                            <div class="flex items-center justify-between">
                                <div class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                                    <HandHeart class="h-6 w-6" />
                                </div>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    Transparan &amp; Terlindungi
                                </span>
                            </div>

                            <div class="space-y-2">
                                <h3 class="text-xl font-bold text-slate-900 transition-colors group-hover:text-emerald-700">
                                    Cek Bantuan Sosial Desa
                                </h3>
                                <p class="text-sm leading-relaxed text-slate-600">
                                    Periksa status kepesertaan keluarga Anda pada program BLT Dana Desa, PKH, BPNT, dan bantuan sosial pemerintah secara transparan dan akurat.
                                </p>
                            </div>

                            <!-- Poin Keunggulan -->
                            <ul class="space-y-2.5 border-t border-slate-100 pt-4 text-xs sm:text-sm text-slate-600">
                                <li class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>Pencocokan data riil kependudukan desa terverifikasi</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>Penyamaran NIK &amp; nama warga sesuai UU PDP No. 27/2022</span>
                                </li>
                                <li class="flex items-center gap-2">
                                    <CheckCircle2 class="h-4 w-4 shrink-0 text-emerald-600" />
                                    <span>Akses publik terbuka tanpa pungutan biaya</span>
                                </li>
                            </ul>
                        </div>

                        <div class="pt-6">
                            <Link
                                href="/layanan-mandiri?tab=cek_bansos"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-xs transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                            >
                                <span>Cek Status Bansos</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Bar Informasi Pendukung: Jam Pelayanan & Bantuan Warga -->
                <div class="flex flex-col items-center justify-between gap-4 rounded-2xl border border-slate-200/80 bg-slate-50/70 p-5 sm:p-6 sm:flex-row">
                    <div class="flex flex-wrap items-center gap-y-2 gap-x-6 text-xs sm:text-sm text-slate-600">
                        <div class="inline-flex items-center gap-2">
                            <Clock class="h-4 w-4 shrink-0 text-slate-400" />
                            <span>{{ profil.jam_pelayanan || 'Senin – Jumat, 08.00 – 15.00 WITA' }}</span>
                        </div>
                        <div class="inline-flex items-center gap-2">
                            <MapPin class="h-4 w-4 shrink-0 text-slate-400" />
                            <span>{{ profil.alamat_kantor || `Kantor Desa ${namaDesa}` }}</span>
                        </div>
                    </div>

                    <a
                        v-if="profil.no_wa_pelayanan"
                        :href="`https://wa.me/${profil.no_wa_pelayanan.replace(/[^0-9]/g, '')}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 text-xs sm:text-sm font-semibold text-emerald-700 hover:text-emerald-800"
                    >
                        <MessageCircle class="h-4 w-4" />
                        <span>Bantuan Operasional Pelayanan</span>
                    </a>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
