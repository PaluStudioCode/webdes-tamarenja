<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
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
import { Wallet } from 'lucide-vue-next';
import ApbdesBannerCanvas from '@/Components/ApbdesBannerCanvas.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
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
    available_tahun: {
        type: Array,
        default: () => [new Date().getFullYear()],
    },
    has_perubahan: {
        type: Boolean,
        default: false,
    },
    dokumen_aktif: {
        type: Object,
        default: () => ({
            tahun_anggaran: new Date().getFullYear(),
            jenis_dokumen: 'murni',
            jenis_label: 'APBDes Awal (Murni)',
            rincian: [],
        }),
    },
    dokumen_murni: {
        type: Object,
        default: () => ({}),
    },
    dokumen_perubahan: {
        type: Object,
        default: null,
    },
    ringkasan: {
        type: Object,
        default: () => ({
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
});

const selectedTahun = ref(Number(props.tahun_anggaran));
const selectedJenis = ref(props.dokumen_aktif?.jenis_dokumen || 'murni');

function handleYearOrJenisChange() {
    router.get(
        '/transparansi/apbdes',
        {
            tahun: selectedTahun.value,
            jenis: selectedJenis.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}

const limaBidang = computed(() =>
    Array.isArray(props.ringkasan?.lima_bidang)
        ? props.ringkasan.lima_bidang
        : [],
);

const chartData = computed(() => ({
    labels: limaBidang.value.map((_, i) => `Bidang ${i + 1}`),
    datasets: [
        {
            label: 'Pagu Anggaran',
            backgroundColor: '#1D4ED8',
            borderRadius: 4,
            data: limaBidang.value.map((b) => Number(b.pagu_anggaran || 0)),
        },
        {
            label: 'Realisasi',
            backgroundColor: '#059669',
            borderRadius: 4,
            data: limaBidang.value.map((b) => Number(b.total_realisasi || 0)),
        },
    ],
}));

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
};
</script>

<template>
    <PublicLayout>
        <Head :title="`Transparansi APBDes TA ${tahun_anggaran}`" />

        <div class="space-y-8">
            <!-- Header & Pemilih Tahun Anggaran + Jenis Dokumen -->
            <div
                class="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:flex-row sm:items-center"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">
                            Transparansi Anggaran APBDes TA {{ selectedTahun }}
                        </h1>
                        <StatusBadge
                            :status="
                                dokumen_aktif.jenis_dokumen === 'perubahan'
                                    ? 'perubahan'
                                    : 'aktif'
                            "
                            :label="dokumen_aktif.jenis_label"
                        />
                    </div>
                    <p class="mt-1 text-sm text-slate-600">
                        Rincian terbuka pendapatan, belanja 5 bidang, pembiayaan
                        desa, serta unduhan spanduk baliho infografis APBDes.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label
                            for="select-tahun-apbdes"
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Tahun Anggaran
                        </label>
                        <select
                            id="select-tahun-apbdes"
                            v-model.number="selectedTahun"
                            data-testid="publik-select-tahun-apbdes"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium tabular-nums text-slate-900"
                            @change="handleYearOrJenisChange"
                        >
                            <option
                                v-for="yr in available_tahun"
                                :key="yr"
                                :value="yr"
                            >
                                TA {{ yr }}
                            </option>
                        </select>
                    </div>

                    <div v-if="has_perubahan">
                        <label
                            for="select-jenis-apbdes"
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Versi Dokumen
                        </label>
                        <select
                            id="select-jenis-apbdes"
                            v-model="selectedJenis"
                            data-testid="publik-select-jenis-apbdes"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-900"
                            @change="handleYearOrJenisChange"
                        >
                            <option value="perubahan">
                                APBDes Perubahan (Aktif)
                            </option>
                            <option value="murni">
                                APBDes Awal (Murni - Arsip)
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3 StatCard Ringkasan -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard
                    label="Total Pendapatan Desa"
                    :value="formatRupiah(ringkasan.total_pagu_pendapatan)"
                    :subtext="`Realisasi ${formatRupiah(ringkasan.total_realisasi_pendapatan)} (${formatPercentage(ringkasan.persentase_realisasi_pendapatan)})`"
                    tone="blue"
                    :icon="Wallet"
                />

                <StatCard
                    label="Total Pagu Belanja (5 Bidang)"
                    :value="formatRupiah(ringkasan.total_pagu_belanja)"
                    :subtext="`Realisasi ${formatRupiah(ringkasan.total_realisasi_belanja)}`"
                    tone="neutral"
                    :icon="Wallet"
                />

                <StatCard
                    label="Sisa Anggaran Belanja"
                    :value="formatRupiah(ringkasan.sisa_anggaran_belanja)"
                    :subtext="`Serapan Belanja ${formatPercentage(ringkasan.persentase_serapan_belanja)}`"
                    tone="emerald"
                    :icon="Wallet"
                />
            </div>

            <!-- Grafik Serapan 5 Bidang -->
            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold text-slate-900">
                    Grafik Pagu vs Realisasi 5 Bidang Belanja Desa
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Perbandingan alokasi dan serapan tiap bidang sesuai
                    Permendagri No. 20/2018
                </p>

                <div class="mt-4 h-72 w-full">
                    <Bar :data="chartData" :options="chartOptions" />
                </div>
            </section>

            <!-- Generator & Unduh Gambar Baliho APBDes (3840 x 2160 px) -->
            <section data-testid="publik-baliho-section">
                <ApbdesBannerCanvas
                    :dokumen="dokumen_aktif"
                    :ringkasan="ringkasan"
                />
            </section>
        </div>
    </PublicLayout>
</template>
