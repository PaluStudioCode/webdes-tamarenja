<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
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
import { Home, ShieldCheck, Users } from 'lucide-vue-next';
import BaseCallout from '@/Components/BaseCallout.vue';
import StatCard from '@/Components/StatCard.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
);

const props = defineProps({
    ringkasan: {
        type: Object,
        default: () => ({
            total_penduduk_aktif: 0,
            total_kk_aktif: 0,
            laki_laki_count: 0,
            perempuan_count: 0,
        }),
    },
    kelompok_usia: {
        type: Array,
        default: () => [
            { kode: '0_4', label: '0–4 thn (Balita)', jumlah: 0 },
            { kode: '5_14', label: '5–14 thn (Anak)', jumlah: 0 },
            { kode: '15_24', label: '15–24 thn (Pemuda)', jumlah: 0 },
            { kode: '25_59', label: '25–59 thn (Produktif)', jumlah: 0 },
            { kode: '60_plus', label: '60+ thn (Lansia)', jumlah: 0 },
        ],
    },
    pekerjaan: {
        type: Array,
        default: () => [],
    },
    sebaran_dusun: {
        type: Array,
        default: () => [],
    },
});

const ageChartData = computed(() => ({
    labels: (props.kelompok_usia || []).map((u) => u.label),
    datasets: [
        {
            label: 'Jumlah Jiwa Aktif',
            backgroundColor: '#1D4ED8',
            borderRadius: 4,
            data: (props.kelompok_usia || []).map((u) => Number(u.jumlah || 0)),
        },
    ],
}));

const ageChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
};
</script>

<template>
    <PublicLayout>
        <Head title="Statistik Demografi Penduduk Desa" />

        <div class="space-y-8">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">
                    Statistik Demografi Penduduk Aktif
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Data agregat kependudukan berdasarkan jenis kelamin, 5
                    kelompok usia baku, pekerjaan, dan sebaran wilayah dusun.
                </p>
            </div>

            <BaseCallout
                variant="info"
                title="Kepatuhan Pelindungan Data Pribadi (UU No. 27 Tahun 2022)"
            >
                Halaman ini hanya menyajikan angka agregasi statistik penduduk
                dan Kartu Keluarga berstatus <strong>Aktif</strong> tanpa
                menampilkan NIK, Nomor KK, maupun daftar nama warga individu.
            </BaseCallout>

            <!-- 4 StatCard Demografi Utama -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total Penduduk Aktif"
                    :value="`${ringkasan.total_penduduk_aktif} Jiwa`"
                    subtext="Warga menetap aktif"
                    tone="blue"
                    :icon="Users"
                />

                <StatCard
                    label="Total Kartu Keluarga Aktif"
                    :value="`${ringkasan.total_kk_aktif} KK`"
                    subtext="Memiliki >= 1 anggota aktif"
                    tone="emerald"
                    :icon="Home"
                />

                <StatCard
                    label="Penduduk Laki-Laki"
                    :value="`${ringkasan.laki_laki_count} Jiwa`"
                    subtext="Jenis kelamin Laki-laki"
                    tone="neutral"
                    :icon="Users"
                />

                <StatCard
                    label="Penduduk Perempuan"
                    :value="`${ringkasan.perempuan_count} Jiwa`"
                    subtext="Jenis kelamin Perempuan"
                    tone="neutral"
                    :icon="Users"
                />
            </div>

            <!-- Grafik & Rincian 5 Kelompok Usia Baku -->
            <section
                data-testid="statistik-usia-section"
                class="rounded-lg border border-slate-200 bg-white p-6"
            >
                <h2 class="text-base font-semibold text-slate-900">
                    Distribusi 5 Kelompok Usia Baku
                </h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    Pengelompokan demografi Balita (0–4), Anak (5–14), Pemuda
                    (15–24), Usia Produktif (25–59), dan Lansia (60+ tahun)
                </p>

                <div class="mt-4 h-64 w-full">
                    <Bar :data="ageChartData" :options="ageChartOptions" />
                </div>

                <div
                    class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div
                        v-for="item in kelompok_usia"
                        :key="item.kode"
                        :data-testid="`age-group-${item.kode}`"
                        class="rounded-md border border-slate-200 bg-slate-50 p-3.5"
                    >
                        <p class="text-xs font-medium text-slate-600">
                            {{ item.label }}
                        </p>
                        <p
                            class="mt-1 text-lg font-semibold tabular-nums text-slate-900"
                        >
                            {{ item.jumlah }} Jiwa
                        </p>
                    </div>
                </div>
            </section>

            <!-- Sebaran Per Dusun & Pekerjaan -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Sebaran Per Dusun -->
                <section
                    data-testid="statistik-dusun-section"
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="mb-4 text-base font-semibold text-slate-900">
                        Sebaran Penduduk &amp; KK Aktif per Dusun
                    </h2>

                    <div class="divide-y divide-slate-100 text-sm">
                        <div
                            v-for="row in sebaran_dusun"
                            :key="row.dusun"
                            class="flex items-center justify-between py-2.5"
                        >
                            <span class="font-medium text-slate-800">
                                {{ row.dusun }}
                            </span>
                            <div
                                class="flex items-center gap-3 tabular-nums text-xs text-slate-600"
                            >
                                <span>
                                    <strong class="text-slate-900">{{
                                        row.jumlah_jiwa
                                    }}</strong>
                                    Jiwa
                                </span>
                                <span>•</span>
                                <span>
                                    <strong class="text-blue-700">{{
                                        row.jumlah_kk
                                    }}</strong>
                                    KK
                                </span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Jenis Pekerjaan -->
                <section
                    data-testid="statistik-pekerjaan-section"
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="mb-4 text-base font-semibold text-slate-900">
                        Komposisi Mata Pencaharian / Pekerjaan
                    </h2>

                    <div class="divide-y divide-slate-100 text-sm">
                        <div
                            v-for="job in pekerjaan"
                            :key="job.pekerjaan"
                            class="flex items-center justify-between py-2.5"
                        >
                            <span class="text-slate-700">
                                {{ job.pekerjaan }}
                            </span>
                            <span
                                class="font-semibold tabular-nums text-slate-900"
                            >
                                {{ job.jumlah }} Jiwa
                            </span>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </PublicLayout>
</template>
