<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import {
    AlertTriangle,
    Camera,
    FileText,
    Hammer,
    HeartHandshake,
    MapPin,
    Plus,
    Printer,
    Wallet,
} from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PageHeader from '@/Components/PageHeader.vue';
import SlideOverDrawer from '@/Components/SlideOverDrawer.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatPercentage, formatRupiah } from '@/Utils/formatters';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
);

const props = defineProps({
    tahun_anggaran: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
    apbdes: {
        type: Object,
        default: () => ({
            jenis_dokumen: 'murni',
            jenis_dokumen_label: 'APBDes Awal (Murni)',
            has_perubahan: false,
            total_pagu_pendapatan: 0,
            total_realisasi_pendapatan: 0,
            persentase_realisasi_pendapatan: 0,
            total_pagu_belanja: 0,
            total_realisasi_belanja: 0,
            sisa_anggaran_belanja: 0,
            persentase_serapan_belanja: 0,
            lima_bidang: [],
        }),
    },
    pembangunan: {
        type: Object,
        default: () => ({
            total_proyek: 0,
            selesai_count: 0,
            sedang_dikerjakan_count: 0,
            belum_dimulai_count: 0,
            total_pagu: 0,
            total_realisasi: 0,
            rata_progres_fisik: 0,
            daftar_proyek: [],
        }),
    },
    persuratan: {
        type: Object,
        default: () => ({
            antrean_menunggu_count: 0,
            surat_keluar_terbit_count: 0,
            surat_keluar_dibatalkan_count: 0,
            surat_masuk_count: 0,
        }),
    },
    sosial: {
        type: Object,
        default: () => ({
            total_penerima_bansos: 0,
            penerima_aktif_count: 0,
            kk_belum_menerima_count: 0,
            per_jenis: [],
            penduduk_aktif_count: 0,
            kk_aktif_count: 0,
        }),
    },
    loadingChart: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const tahunAnggaran = computed(
    () =>
        Number(
            props.tahun_anggaran ||
                page.props?.filter_tahun ||
                new Date().getFullYear(),
        ),
);

const limaBidangList = computed(() =>
    Array.isArray(props.apbdes?.lima_bidang) ? props.apbdes.lima_bidang : [],
);

const chartData = computed(() => ({
    labels: limaBidangList.value.map((_, idx) => `Bidang ${idx + 1}`),
    datasets: [
        {
            label: 'Pagu Anggaran',
            backgroundColor: '#1D4ED8',
            borderRadius: 4,
            data: limaBidangList.value.map((b) => Number(b.pagu_anggaran || 0)),
        },
        {
            label: 'Realisasi',
            backgroundColor: '#059669',
            borderRadius: 4,
            data: limaBidangList.value.map((b) =>
                Number(b.total_realisasi || 0),
            ),
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
    },
    scales: {
        y: {
            beginAtZero: true,
        },
    },
};

const pdfShortcuts = computed(() => [
    {
        key: 'apbdes',
        label: 'Laporan Realisasi APBDes (F4 Portrait)',
        href: `/panel/apbdes/cetak-pdf?tahun=${tahunAnggaran.value}&jenis=${props.apbdes?.jenis_dokumen || 'murni'}`,
    },
    {
        key: 'pembangunan',
        label: 'Rekapitulasi Proyek Pembangunan Fisik (F4 Landscape)',
        href: `/panel/pembangunan/cetak-pdf?tahun=${tahunAnggaran.value}`,
    },
    {
        key: 'surat-keluar',
        label: 'Buku Agenda Surat Keluar (F4 Landscape)',
        href: `/panel/persuratan/surat-keluar/cetak-agenda?tahun=${tahunAnggaran.value}`,
    },
    {
        key: 'surat-masuk',
        label: 'Buku Agenda Surat Masuk (F4 Landscape)',
        href: `/panel/persuratan/surat-masuk/cetak-agenda?tahun=${tahunAnggaran.value}`,
    },
    {
        key: 'bansos',
        label: 'Daftar Hadir & Tanda Terima Bansos (F4 Landscape)',
        href: `/panel/sosial/bansos/cetak-pdf?tahun_anggaran=${tahunAnggaran.value}`,
    },
]);

// Drawer Pratinjau Foto Proyek 0% / 50% / 100%
const selectedProject = ref(null);
const isPhotoDrawerOpen = ref(false);

function openPhotoDrawer(project) {
    selectedProject.value = project;
    isPhotoDrawerOpen.value = true;
}

function closePhotoDrawer() {
    isPhotoDrawerOpen.value = false;
    selectedProject.value = null;
}
</script>

<template>
    <AppLayout>
        <Head title="Dashboard Eksekutif & Operasional" />

        <PageHeader
            title="Dashboard Eksekutif & Operasional"
            :description="`Ringkasan capaian anggaran, pembangunan fisik, pelayanan surat, dan penyaluran bantuan sosial Tahun Anggaran ${tahunAnggaran}.`"
        >
            <template #actions>
                <div
                    v-if="isOperator"
                    data-testid="operator-quick-actions"
                    class="flex flex-wrap items-center gap-2"
                >
                    <Link
                        href="/panel/persuratan/surat-keluar/buat"
                        data-testid="quick-action-buat-surat"
                        class="inline-flex h-10 items-center gap-1.5 rounded-md bg-blue-700 px-3.5 text-sm font-medium text-white transition-colors hover:bg-blue-800"
                    >
                        <Plus class="h-4 w-4" :stroke-width="2" />
                        <span>Layanan Cetak Surat</span>
                    </Link>
                    <Link
                        href="/panel/pembangunan/tambah"
                        data-testid="quick-action-tambah-proyek"
                        class="inline-flex h-10 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Hammer class="h-4 w-4" :stroke-width="1.75" />
                        <span>Tambah Proyek</span>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- 4 Kartu Metrik Utama (Anti-Duplikasi: 1 Label, 1 Angka Utama, 1 Sub-indikator) -->
        <div
            data-testid="dashboard-stat-grid"
            class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <StatCard
                label="Total Belanja APBDes"
                :value="formatRupiah(apbdes.total_pagu_belanja)"
                :subtext="`Serapan ${formatPercentage(apbdes.persentase_serapan_belanja)} (${apbdes.jenis_dokumen_label})`"
                tone="blue"
                :icon="Wallet"
            />

            <StatCard
                label="Proyek Pembangunan Fisik"
                :value="`${pembangunan.selesai_count} / ${pembangunan.total_proyek} Selesai`"
                :subtext="`Rata-rata fisik ${pembangunan.rata_progres_fisik}% • ${pembangunan.sedang_dikerjakan_count} berjalan`"
                tone="emerald"
                :icon="Hammer"
            />

            <StatCard
                label="Pelayanan & Agenda Surat"
                :value="`${persuratan.surat_keluar_terbit_count} Surat Terbit`"
                :subtext="`${persuratan.antrean_menunggu_count} antrean online • ${persuratan.surat_masuk_count} surat masuk`"
                :tone="persuratan.antrean_menunggu_count > 0 ? 'amber' : 'neutral'"
                :icon="FileText"
            />

            <StatCard
                label="Penyaluran Bansos & Warga"
                :value="`${sosial.penerima_aktif_count} Penerima Aktif`"
                :subtext="`${sosial.kk_belum_menerima_count} KK prioritas DTSEN belum terima`"
                tone="emerald"
                :icon="HeartHandshake"
            />
        </div>

        <!-- Baris Utama: Grafik 5 Bidang APBDes & Menu Pintasan Cetak Laporan PDF F4 -->
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Grafik Serapan 5 Bidang APBDes (8 Kolom di Desktop, 1 Kolom di HP) -->
            <section
                data-testid="dashboard-apbdes-section"
                class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6 lg:col-span-8"
            >
                <div
                    class="mb-4 flex flex-wrap items-start justify-between gap-3"
                >
                    <div>
                        <h2
                            data-testid="apbdes-chart-heading"
                            class="text-base font-semibold text-slate-900"
                        >
                            Serapan Anggaran 5 Bidang APBDes
                        </h2>
                        <p
                            data-testid="apbdes-chart-subheading"
                            class="mt-0.5 text-xs text-slate-500"
                        >
                            Perbandingan Pagu vs Realisasi per bidang
                            penyelenggaraan pemerintahan desa
                        </p>
                    </div>

                    <StatusBadge
                        :status="
                            apbdes.jenis_dokumen === 'perubahan'
                                ? 'perubahan'
                                : 'aktif'
                        "
                        :label="apbdes.jenis_dokumen_label"
                    />
                </div>

                <!-- Skeleton / Chart -->
                <div
                    v-if="loadingChart"
                    data-testid="apbdes-chart-skeleton"
                    class="h-64 w-full animate-pulse rounded-md bg-slate-100"
                />
                <div
                    v-else
                    data-testid="apbdes-chart-container"
                    class="h-64 w-full"
                >
                    <Bar :data="chartData" :options="chartOptions" />
                </div>

                <!-- Ringkasan Baris 5 Bidang (Nyaman dibaca satu tangan di HP) -->
                <div class="mt-5 divide-y divide-slate-100 border-t border-slate-100 pt-3">
                    <div
                        v-for="bidang in limaBidangList"
                        :key="bidang.kode"
                        class="flex flex-col justify-between gap-1 py-2.5 text-xs sm:flex-row sm:items-center"
                    >
                        <span class="font-medium text-slate-700">
                            {{ bidang.nama }}
                        </span>
                        <div
                            class="flex items-center gap-3 tabular-nums text-slate-600"
                        >
                            <span>
                                Realisasi:
                                <strong class="text-slate-900">
                                    {{ formatRupiah(bidang.total_realisasi) }}
                                </strong>
                                / {{ formatRupiah(bidang.pagu_anggaran) }}
                            </span>
                            <span
                                class="inline-flex rounded bg-emerald-50 px-1.5 py-0.5 font-semibold text-emerald-700"
                            >
                                {{ formatPercentage(bidang.persentase_serapan) }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Menu Pintasan Unduh / Cetak Laporan PDF F4 & Ringkasan Lintas Modul (4 Kolom di Desktop) -->
            <aside class="space-y-6 lg:col-span-4">
                <section
                    data-testid="pdf-shortcuts-card"
                    class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"
                >
                    <div class="mb-3 flex items-center gap-2">
                        <Printer class="h-4 w-4 text-blue-700" />
                        <h2 class="text-base font-semibold text-slate-900">
                            Pintasan Cetak Laporan PDF F4
                        </h2>
                    </div>
                    <p class="mb-4 text-xs text-slate-500">
                        Unduh atau cetak langsung laporan resmi kertas F4
                        (Folio) ber-Kop Surat Desa untuk evaluasi dan arsip
                        fisik.
                    </p>

                    <div class="space-y-2">
                        <a
                            v-for="item in pdfShortcuts"
                            :key="item.key"
                            :href="item.href"
                            :data-testid="`pdf-shortcut-${item.key}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex min-h-[44px] items-center justify-between gap-2 rounded-md border border-slate-200 bg-slate-50/70 px-3.5 py-2.5 text-xs font-medium text-slate-800 transition-colors hover:border-blue-300 hover:bg-blue-50/60 hover:text-blue-800"
                        >
                            <span>{{ item.label }}</span>
                            <Printer class="h-3.5 w-3.5 shrink-0 text-slate-500" />
                        </a>
                    </div>
                </section>

                <!-- Ringkasan Sebaran Bansos & Demografi Aktif -->
                <section
                    class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"
                >
                    <h2 class="mb-3 text-base font-semibold text-slate-900">
                        Rekap Bantuan Sosial & Demografi
                    </h2>

                    <div class="mb-4 grid grid-cols-2 gap-2.5 text-xs">
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <p class="text-slate-500">Penduduk Aktif</p>
                            <p
                                class="mt-1 text-base font-semibold tabular-nums text-slate-900"
                            >
                                {{ sosial.penduduk_aktif_count }} Jiwa
                            </p>
                        </div>
                        <div
                            class="rounded-md border border-slate-200 bg-slate-50 p-3"
                        >
                            <p class="text-slate-500">Kartu Keluarga Aktif</p>
                            <p
                                class="mt-1 text-base font-semibold tabular-nums text-slate-900"
                            >
                                {{ sosial.kk_aktif_count }} KK
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div
                            v-for="item in sosial.per_jenis"
                            :key="item.jenis_bantuan"
                            class="flex items-center justify-between border-b border-slate-100 pb-1.5 last:border-b-0"
                        >
                            <span class="text-slate-600">{{ item.label }}</span>
                            <span
                                class="font-semibold tabular-nums text-slate-900"
                            >
                                {{ item.jumlah }} KPM
                            </span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        <!-- Monitoring Progres Fisik vs Keuangan Proyek & Pratinjau Foto 0%/50%/100% -->
        <section
            data-testid="dashboard-projects-section"
            class="rounded-lg border border-slate-200 bg-white p-5 sm:p-6"
        >
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Monitoring Progres Fisik vs Realisasi Keuangan Proyek
                    </h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Daftar pelaksanaan proyek konstruksi fisik desa beserta
                        catatan kendala lapangan dan dokumentasi 3 titik foto.
                    </p>
                </div>

                <Link
                    href="/panel/pembangunan"
                    class="text-xs font-semibold text-blue-700 hover:underline"
                >
                    Lihat Modul Pembangunan Lengkap
                </Link>
            </div>

            <EmptyState
                v-if="!pembangunan.daftar_proyek || pembangunan.daftar_proyek.length === 0"
                :title="`Belum ada data proyek pembangunan fisik pada Tahun Anggaran ${tahunAnggaran}`"
                description="Proyek konstruksi fisik yang diinput pada Modul 1 akan otomatis tampil di sini."
            />

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <article
                    v-for="proyek in pembangunan.daftar_proyek"
                    :key="proyek.id"
                    :data-testid="`dashboard-project-card-${proyek.id}`"
                    class="flex flex-col justify-between rounded-lg border border-slate-200 bg-slate-50/40 p-4"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900">
                                    {{ proyek.nama_kegiatan }}
                                </h3>
                                <p
                                    class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500"
                                >
                                    <MapPin class="h-3.5 w-3.5" />
                                    <span>{{ proyek.lokasi_dusun }}</span>
                                    <span>•</span>
                                    <span>{{ proyek.sumber_dana_label }}</span>
                                </p>
                            </div>
                            <StatusBadge :status="proyek.status_proyek" />
                        </div>

                        <!-- Bar Progres Fisik vs Serapan Dana -->
                        <div class="space-y-2 text-xs">
                            <div>
                                <div
                                    class="mb-1 flex justify-between tabular-nums"
                                >
                                    <span class="text-slate-600"
                                        >Progres Fisik Lapangan</span
                                    >
                                    <span class="font-semibold text-slate-900"
                                        >{{ proyek.progres_fisik }}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-slate-200"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-600"
                                        :style="{
                                            width: `${Math.min(100, Math.max(0, proyek.progres_fisik))}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <div>
                                <div
                                    class="mb-1 flex justify-between tabular-nums"
                                >
                                    <span class="text-slate-600">
                                        Realisasi Dana ({{
                                            formatRupiah(
                                                proyek.realisasi_anggaran,
                                            )
                                        }}
                                        /
                                        {{ formatRupiah(proyek.pagu_anggaran) }})
                                    </span>
                                    <span class="font-semibold text-blue-700">
                                        {{
                                            formatPercentage(
                                                proyek.persentase_serapan,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-slate-200"
                                >
                                    <div
                                        class="h-full rounded-full bg-blue-700"
                                        :style="{
                                            width: `${Math.min(100, Math.max(0, proyek.persentase_serapan))}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Catatan / Kendala Lapangan -->
                        <div
                            v-if="proyek.catatan_kendala"
                            :data-testid="`project-kendala-${proyek.id}`"
                            class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 p-2.5 text-xs text-amber-800"
                        >
                            <AlertTriangle
                                class="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-600"
                            />
                            <div>
                                <span class="font-semibold"
                                    >Kendala Lapangan:</span
                                >
                                {{ proyek.catatan_kendala }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between border-t border-slate-200/80 pt-3"
                    >
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 font-medium',
                                    proyek.foto_0_path
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-400',
                                ]"
                            >
                                0%
                            </span>
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 font-medium',
                                    proyek.foto_50_path
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-400',
                                ]"
                            >
                                50%
                            </span>
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 font-medium',
                                    proyek.foto_100_path
                                        ? 'bg-emerald-50 text-emerald-700'
                                        : 'bg-slate-100 text-slate-400',
                                ]"
                            >
                                100%
                            </span>
                        </div>

                        <BaseButton
                            size="sm"
                            variant="secondary"
                            :data-testid="`open-photo-drawer-${proyek.id}`"
                            @click="openPhotoDrawer(proyek)"
                        >
                            <Camera class="h-3.5 w-3.5" />
                            <span>Pratinjau Foto</span>
                        </BaseButton>
                    </div>
                </article>
            </div>
        </section>

        <!-- Slide-over Drawer Pratinjau Foto Proyek 0% / 50% / 100% -->
        <SlideOverDrawer
            :show="isPhotoDrawerOpen"
            :title="selectedProject?.nama_kegiatan || 'Detail Foto Proyek'"
            :subtitle="
                selectedProject
                    ? `${selectedProject.lokasi_dusun} • Progres Fisik ${selectedProject.progres_fisik}%`
                    : ''
            "
            max-width="xl"
            @close="closePhotoDrawer"
        >
            <div v-if="selectedProject" class="space-y-5">
                <div
                    class="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-xs"
                >
                    <div>
                        <p class="text-slate-500">Pagu Anggaran</p>
                        <p
                            class="mt-0.5 font-semibold tabular-nums text-slate-900"
                        >
                            {{ formatRupiah(selectedProject.pagu_anggaran) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">Realisasi Anggaran</p>
                        <p
                            class="mt-0.5 font-semibold tabular-nums text-emerald-700"
                        >
                            {{
                                formatRupiah(selectedProject.realisasi_anggaran)
                            }}
                            ({{
                                formatPercentage(
                                    selectedProject.persentase_serapan,
                                )
                            }})
                        </p>
                    </div>
                </div>

                <div
                    v-if="selectedProject.catatan_kendala"
                    class="rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800"
                >
                    <p class="font-semibold">Catatan / Kendala Lapangan:</p>
                    <p class="mt-0.5">{{ selectedProject.catatan_kendala }}</p>
                </div>

                <!-- 3 Titik Foto Dokumentasi (0%, 50%, 100%) -->
                <div class="space-y-4">
                    <div
                        v-for="checkpoint in [
                            {
                                label: 'Titik Awal (0%)',
                                url: selectedProject.foto_0_url,
                                path: selectedProject.foto_0_path,
                            },
                            {
                                label: 'Titik Tengah (50%)',
                                url: selectedProject.foto_50_url,
                                path: selectedProject.foto_50_path,
                            },
                            {
                                label: 'Titik Akhir / Selesai (100%)',
                                url: selectedProject.foto_100_url,
                                path: selectedProject.foto_100_path,
                            },
                        ]"
                        :key="checkpoint.label"
                        class="rounded-lg border border-slate-200 p-3"
                    >
                        <p class="mb-2 text-xs font-semibold text-slate-800">
                            {{ checkpoint.label }}
                        </p>
                        <div
                            v-if="checkpoint.url || checkpoint.path"
                            class="overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                        >
                            <img
                                :src="
                                    checkpoint.url ||
                                    `/storage/${checkpoint.path}`
                                "
                                :alt="checkpoint.label"
                                class="max-h-56 w-full object-cover"
                            />
                        </div>
                        <p v-else class="text-xs italic text-slate-400">
                            Belum ada foto dokumentasi pada titik ini.
                        </p>
                    </div>
                </div>
            </div>
        </SlideOverDrawer>
    </AppLayout>
</template>
