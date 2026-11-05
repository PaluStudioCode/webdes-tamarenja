<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Camera, MapPin, Printer } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import EmptyState from '@/Components/EmptyState.vue';
import LeafletMapView from '@/Components/LeafletMapView.vue';
import SlideOverDrawer from '@/Components/SlideOverDrawer.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatPercentage, formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    tahun_anggaran: {
        type: Number,
        default: () => new Date().getFullYear(),
    },
    available_tahun: {
        type: Array,
        default: () => [new Date().getFullYear()],
    },
    daftar_dusun: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ dusun: '' }),
    },
    proyek: {
        type: Array,
        default: () => [],
    },
});

const selectedTahun = ref(Number(props.tahun_anggaran));
const selectedDusun = ref(props.filters?.dusun || '');

function applyFilters() {
    router.get(
        '/transparansi/pembangunan',
        {
            tahun: selectedTahun.value,
            dusun: selectedDusun.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}

const activeProject = ref(null);
const isDrawerOpen = ref(false);

function openProjectDetail(project) {
    activeProject.value = project;
    isDrawerOpen.value = true;
}

function closeProjectDetail() {
    isDrawerOpen.value = false;
    activeProject.value = null;
}
</script>

<template>
    <PublicLayout>
        <Head :title="`Katalog Pembangunan Fisik TA ${tahun_anggaran}`" />

        <div class="space-y-6">
            <!-- Header, Filter Tahun/Dusun & Tombol Unduh PDF Rekap F4 -->
            <div
                class="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-6 lg:flex-row lg:items-center"
            >
                <div>
                    <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">
                        Katalog Pembangunan Infrastruktur Desa TA
                        {{ selectedTahun }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Peta titik lokasi, persentase fisik vs realisasi dana,
                        serta dokumentasi foto lapangan 0%, 50%, dan 100%.
                    </p>
                </div>

                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label
                            for="filter-tahun-proyek"
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Tahun Anggaran
                        </label>
                        <select
                            id="filter-tahun-proyek"
                            v-model.number="selectedTahun"
                            data-testid="publik-select-tahun-pembangunan"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm font-medium tabular-nums text-slate-900"
                            @change="applyFilters"
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

                    <div>
                        <label
                            for="filter-dusun-proyek"
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Wilayah Dusun
                        </label>
                        <select
                            id="filter-dusun-proyek"
                            v-model="selectedDusun"
                            data-testid="publik-select-dusun-pembangunan"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            @change="applyFilters"
                        >
                            <option value="">Semua Dusun</option>
                            <option
                                v-for="d in daftar_dusun"
                                :key="d"
                                :value="d"
                            >
                                {{ d }}
                            </option>
                        </select>
                    </div>

                    <a
                        :href="`/transparansi/pembangunan/cetak-pdf?tahun=${selectedTahun}`"
                        target="_blank"
                        rel="noopener noreferrer"
                        data-testid="publik-download-rekap-pembangunan-pdf"
                        class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Printer class="h-4 w-4 text-blue-700" />
                        <span>Unduh PDF Rekap F4</span>
                    </a>
                </div>
            </div>

            <!-- Peta Sebaran Lokasi Proyek (LeafletMapView) -->
            <section class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="mb-3 text-base font-semibold text-slate-900">
                    Peta Sebaran Titik Lokasi Proyek Pembangunan
                </h2>
                <LeafletMapView
                    :projects="proyek"
                    @select-project="openProjectDetail"
                />
            </section>

            <!-- Daftar Kartu Proyek Fisik -->
            <EmptyState
                v-if="proyek.length === 0"
                :title="`Belum ada proyek fisik pada Tahun Anggaran ${selectedTahun}`"
                description="Silakan pilih Tahun Anggaran atau filter wilayah dusun lainnya."
            />

            <div
                v-else
                class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="item in proyek"
                    :key="item.id"
                    :data-testid="`publik-proyek-card-${item.id}`"
                    class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-5"
                >
                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <StatusBadge :status="item.status_proyek" />
                            <span
                                class="text-xs font-medium text-slate-500"
                            >
                                {{ item.sumber_dana_label }}
                            </span>
                        </div>

                        <h3 class="text-base font-semibold text-slate-900">
                            {{ item.nama_kegiatan }}
                        </h3>

                        <p
                            class="inline-flex items-center gap-1 text-xs text-slate-500"
                        >
                            <MapPin class="h-3.5 w-3.5" />
                            <span>{{ item.lokasi_dusun }}</span>
                            <span v-if="item.volume_fisik">
                                • {{ item.volume_fisik }}
                            </span>
                        </p>

                        <!-- Progres Fisik vs Dana -->
                        <div class="space-y-2 pt-2 text-xs">
                            <div>
                                <div
                                    class="mb-1 flex justify-between tabular-nums"
                                >
                                    <span class="text-slate-600"
                                        >Progres Fisik</span
                                    >
                                    <span class="font-semibold text-emerald-700"
                                        >{{ item.progres_fisik }}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 w-full overflow-hidden rounded-full bg-slate-100"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-600"
                                        :style="{
                                            width: `${Math.min(100, item.progres_fisik)}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <div class="flex justify-between tabular-nums">
                                <span class="text-slate-500">Pagu Anggaran</span>
                                <span class="font-semibold text-slate-900">
                                    {{ formatRupiah(item.pagu_anggaran) }}
                                </span>
                            </div>
                            <div class="flex justify-between tabular-nums">
                                <span class="text-slate-500"
                                    >Realisasi Dana</span
                                >
                                <span class="font-medium text-blue-700">
                                    {{ formatRupiah(item.realisasi_anggaran) }}
                                    ({{
                                        formatPercentage(
                                            item.persentase_serapan,
                                        )
                                    }})
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <BaseButton
                            variant="secondary"
                            size="sm"
                            :data-testid="`publik-open-foto-proyek-${item.id}`"
                            @click="openProjectDetail(item)"
                        >
                            <Camera class="h-3.5 w-3.5" />
                            <span>Lihat Foto 0% / 50% / 100%</span>
                        </BaseButton>
                    </div>
                </article>
            </div>
        </div>

        <!-- Drawer Detail & Foto 0%–50%–100% -->
        <SlideOverDrawer
            :show="isDrawerOpen"
            :title="activeProject?.nama_kegiatan || 'Detail Proyek'"
            :subtitle="
                activeProject
                    ? `${activeProject.lokasi_dusun} • Fisik ${activeProject.progres_fisik}%`
                    : ''
            "
            max-width="xl"
            @close="closeProjectDetail"
        >
            <div v-if="activeProject" class="space-y-4">
                <div
                    class="grid grid-cols-2 gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-xs"
                >
                    <div>
                        <p class="text-slate-500">Pagu Anggaran</p>
                        <p
                            class="mt-0.5 font-semibold tabular-nums text-slate-900"
                        >
                            {{ formatRupiah(activeProject.pagu_anggaran) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-slate-500">Realisasi Anggaran</p>
                        <p
                            class="mt-0.5 font-semibold tabular-nums text-emerald-700"
                        >
                            {{ formatRupiah(activeProject.realisasi_anggaran) }}
                        </p>
                    </div>
                </div>

                <div
                    v-for="cp in [
                        {
                            label: 'Dokumentasi Titik 0%',
                            url: activeProject.foto_0_url,
                            path: activeProject.foto_0_path,
                        },
                        {
                            label: 'Dokumentasi Titik 50%',
                            url: activeProject.foto_50_url,
                            path: activeProject.foto_50_path,
                        },
                        {
                            label: 'Dokumentasi Titik 100%',
                            url: activeProject.foto_100_url,
                            path: activeProject.foto_100_path,
                        },
                    ]"
                    :key="cp.label"
                    class="rounded-lg border border-slate-200 p-3"
                >
                    <p class="mb-2 text-xs font-semibold text-slate-800">
                        {{ cp.label }}
                    </p>
                    <img
                        v-if="cp.url || cp.path"
                        :src="cp.url || `/storage/${cp.path}`"
                        :alt="cp.label"
                        class="max-h-56 w-full rounded object-cover"
                    />
                    <p v-else class="text-xs italic text-slate-400">
                        Belum ada foto pada titik progres ini.
                    </p>
                </div>
            </div>
        </SlideOverDrawer>
    </PublicLayout>
</template>
