<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    FileCheck2,
    Hammer,
    MapPin,
    Wallet,
} from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatDateId, formatPercentage, formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    tahun_aktif: {
        type: Number,
        default: () => new Date().getFullYear(),
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
    sorotan_apbdes: {
        type: Object,
        default: () => ({
            tahun_anggaran: new Date().getFullYear(),
            jenis_dokumen: 'murni',
            total_pagu_pendapatan: 0,
            total_pagu_belanja: 0,
            total_realisasi_belanja: 0,
            persentase_serapan_belanja: 0,
        }),
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
    () => page.props?.profil_desa?.nama_desa || 'Tamarenja',
);

// Pastikan batas maksimal 3 kartu proyek & 3 kegiatan sesuai aturan Anti-Duplikasi Beranda
const topProyek = computed(() => (props.proyek_terbaru || []).slice(0, 3));
const topKegiatan = computed(() => (props.kegiatan_terbaru || []).slice(0, 3));
</script>

<template>
    <PublicLayout>
        <Head :title="`Portal Resmi Pemerintah Desa ${namaDesa}`" />

        <div class="space-y-10">
            <!-- Seksi 1: Hero Identitas, Sambutan Singkat Kades & CTA Layanan Mandiri -->
            <section
                data-testid="beranda-hero"
                class="rounded-lg border border-slate-200 bg-white p-6 sm:p-8"
            >
                <div
                    class="grid grid-cols-1 items-center gap-8 lg:grid-cols-12"
                >
                    <div class="space-y-4 lg:col-span-8">
                        <span
                            class="inline-flex items-center rounded-md border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wider text-blue-700"
                        >
                            Portal Resmi Pemerintahan &amp; Transparansi Desa
                        </span>

                        <h1
                            class="text-2xl font-semibold tracking-tight text-slate-900 sm:text-3xl"
                        >
                            Pemerintah Desa {{ namaDesa }}
                        </h1>

                        <blockquote
                            class="border-l-4 border-blue-700 pl-4 text-sm leading-relaxed text-slate-600"
                        >
                            "{{ sambutan.sambutan_kepala_desa }}"
                            <footer
                                class="mt-2 text-xs font-semibold text-slate-900"
                            >
                                &mdash; {{ sambutan.nama_kepala_desa }} (Kepala
                                Desa {{ namaDesa }})
                            </footer>
                        </blockquote>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <BaseButton
                                href="/layanan-mandiri"
                                variant="primary"
                                size="lg"
                                data-testid="cta-layanan-mandiri"
                            >
                                <FileCheck2 class="h-4 w-4" />
                                <span>Ajukan Surat &amp; Cek Bansos</span>
                            </BaseButton>

                            <BaseButton
                                href="/profil"
                                variant="secondary"
                                size="lg"
                            >
                                <span>Lihat Profil &amp; SOTK Desa</span>
                            </BaseButton>
                        </div>
                    </div>

                    <div
                        class="flex flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-50 p-5 text-center lg:col-span-4"
                    >
                        <div
                            class="mb-3 flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-2 border-blue-700 bg-white"
                        >
                            <img
                                v-if="sambutan.foto_kepala_desa_url"
                                :src="sambutan.foto_kepala_desa_url"
                                :alt="sambutan.nama_kepala_desa"
                                class="h-full w-full object-cover"
                            />
                            <span
                                v-else
                                class="text-sm font-bold text-blue-700"
                            >
                                KADES
                            </span>
                        </div>
                        <p class="text-sm font-semibold text-slate-900">
                            {{ sambutan.nama_kepala_desa }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Kepala Desa {{ namaDesa }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- Seksi 2: Sorotan 3 Metrik APBDes Tahun Aktif -->
            <section data-testid="beranda-sorotan-apbdes" class="space-y-4">
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Sorotan Anggaran APBDes Tahun
                            {{ sorotan_apbdes.tahun_anggaran }}
                        </h2>
                        <p class="text-xs text-slate-500">
                            Ringkasan keterbukaan anggaran tahun berjalan
                        </p>
                    </div>

                    <Link
                        href="/transparansi/apbdes"
                        data-testid="link-selengkapnya-apbdes"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline"
                    >
                        <span>Lihat Infografis &amp; Baliho APBDes</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Target Pendapatan Desa"
                        :value="
                            formatRupiah(
                                sorotan_apbdes.total_pagu_pendapatan || 0,
                            )
                        "
                        :subtext="`Tahun Anggaran ${sorotan_apbdes.tahun_anggaran}`"
                        tone="blue"
                        :icon="Wallet"
                    />

                    <StatCard
                        label="Pagu Belanja Desa"
                        :value="
                            formatRupiah(sorotan_apbdes.total_pagu_belanja || 0)
                        "
                        :subtext="
                            sorotan_apbdes.jenis_dokumen === 'perubahan'
                                ? 'Acuan APBDes Perubahan'
                                : 'Acuan APBDes Murni'
                        "
                        tone="neutral"
                        :icon="Wallet"
                    />

                    <StatCard
                        label="Realisasi Belanja Desa"
                        :value="
                            formatRupiah(
                                sorotan_apbdes.total_realisasi_belanja || 0,
                            )
                        "
                        :subtext="`Serapan ${formatPercentage(sorotan_apbdes.persentase_serapan_belanja || 0)}`"
                        tone="emerald"
                        :icon="Wallet"
                    />
                </div>
            </section>

            <!-- Seksi 3: Sorotan 3 Proyek Pembangunan Fisik Terbaru -->
            <section data-testid="beranda-proyek-section" class="space-y-4">
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Proyek Pembangunan Fisik Terbaru
                        </h2>
                        <p class="text-xs text-slate-500">
                            Pantauan pelaksanaan infrastruktur desa di lapangan
                        </p>
                    </div>

                    <Link
                        href="/transparansi/pembangunan"
                        data-testid="link-selengkapnya-pembangunan"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline"
                    >
                        <span>Lihat Selengkapnya</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>

                <div
                    v-if="topProyek.length === 0"
                    class="rounded-lg border border-slate-200 bg-white p-6 text-center text-sm text-slate-500"
                >
                    Belum ada proyek pembangunan fisik yang dipublikasikan.
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-5 md:grid-cols-3"
                >
                    <article
                        v-for="proyek in topProyek"
                        :key="proyek.id"
                        data-testid="beranda-proyek-card"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-5"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex items-start justify-between gap-2"
                            >
                                <StatusBadge :status="proyek.status_proyek" />
                                <span
                                    class="text-xs font-medium tabular-nums text-slate-500"
                                >
                                    TA {{ proyek.tahun_anggaran }}
                                </span>
                            </div>

                            <h3 class="text-sm font-semibold text-slate-900">
                                {{ proyek.nama_kegiatan }}
                            </h3>

                            <p
                                class="inline-flex items-center gap-1 text-xs text-slate-500"
                            >
                                <MapPin class="h-3.5 w-3.5" />
                                <span>{{ proyek.lokasi_dusun }}</span>
                            </p>
                        </div>

                        <div
                            class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs"
                        >
                            <div class="flex justify-between tabular-nums">
                                <span class="text-slate-500">Pagu Anggaran</span>
                                <span class="font-semibold text-slate-900">
                                    {{ formatRupiah(proyek.pagu_anggaran) }}
                                </span>
                            </div>
                            <div class="flex justify-between tabular-nums">
                                <span class="text-slate-500"
                                    >Progres Fisik</span
                                >
                                <span class="font-semibold text-emerald-700">
                                    {{ proyek.progres_fisik }}%
                                </span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Seksi 4: Sorotan 3 Kegiatan Desa Terbaru -->
            <section data-testid="beranda-kegiatan-section" class="space-y-4">
                <div
                    class="flex flex-wrap items-center justify-between gap-2"
                >
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">
                            Kabar &amp; Dokumentasi Kegiatan Desa
                        </h2>
                        <p class="text-xs text-slate-500">
                            Publikasi kegiatan kemasyarakatan, Musdes, dan
                            penyaluran bantuan
                        </p>
                    </div>

                    <Link
                        href="/kegiatan"
                        data-testid="link-selengkapnya-kegiatan"
                        class="inline-flex items-center gap-1 text-sm font-semibold text-blue-700 hover:underline"
                    >
                        <span>Lihat Selengkapnya</span>
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>

                <div
                    v-if="topKegiatan.length === 0"
                    class="rounded-lg border border-slate-200 bg-white p-6 text-center text-sm text-slate-500"
                >
                    Belum ada berita kegiatan desa yang diterbitkan.
                </div>

                <div
                    v-else
                    class="grid grid-cols-1 gap-5 md:grid-cols-3"
                >
                    <article
                        v-for="item in topKegiatan"
                        :key="item.id"
                        data-testid="beranda-kegiatan-card"
                        class="flex flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white"
                    >
                        <div>
                            <div
                                class="flex h-40 w-full items-center justify-center bg-slate-100"
                            >
                                <img
                                    v-if="item.foto_sampul_url"
                                    :src="item.foto_sampul_url"
                                    :alt="item.judul"
                                    class="h-full w-full object-cover"
                                />
                                <Hammer
                                    v-else
                                    class="h-8 w-8 text-slate-300"
                                />
                            </div>

                            <div class="space-y-2 p-5">
                                <div
                                    class="flex items-center justify-between gap-2 text-xs text-slate-500"
                                >
                                    <span
                                        class="rounded bg-blue-50 px-2 py-0.5 font-medium text-blue-700"
                                    >
                                        {{ item.kategori_label }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 tabular-nums"
                                    >
                                        <Calendar class="h-3.5 w-3.5" />
                                        {{ formatDateId(item.tanggal_kegiatan) }}
                                    </span>
                                </div>

                                <h3
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    <Link
                                        :href="`/kegiatan/${item.slug}`"
                                        class="hover:text-blue-700"
                                    >
                                        {{ item.judul }}
                                    </Link>
                                </h3>

                                <p class="text-xs leading-relaxed text-slate-600">
                                    {{ item.ringkasan }}
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 px-5 py-3">
                            <Link
                                :href="`/kegiatan/${item.slug}`"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:underline"
                            >
                                <span>Baca Dokumentasi</span>
                                <ArrowRight class="h-3.5 w-3.5" />
                            </Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
