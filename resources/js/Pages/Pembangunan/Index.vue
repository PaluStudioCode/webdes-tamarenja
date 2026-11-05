<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Camera,
    Eye,
    FileSpreadsheet,
    Hammer,
    MapPin,
    Pencil,
    Plus,
    Printer,
    Trash2,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import SlideOverDrawer from '@/Components/SlideOverDrawer.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatPercentage, formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    tahun_anggaran: {
        type: Number,
        required: true,
    },
    proyek: {
        type: Array,
        default: () => [],
    },
    ringkasan: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    daftar_dusun: {
        type: Array,
        default: () => [],
    },
    opsi_sumber_dana: {
        type: Array,
        default: () => [],
    },
    opsi_status: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const searchQuery = ref(props.filters?.search || '');
const selectedDusun = ref(props.filters?.dusun || '');
const selectedStatus = ref(props.filters?.status || '');
const selectedSumberDana = ref(props.filters?.sumber_dana || '');

const hasActiveFilters = computed(() =>
    Boolean(
        searchQuery.value ||
            selectedDusun.value ||
            selectedStatus.value ||
            selectedSumberDana.value,
    ),
);

const filteredProjects = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.proyek || []).filter((item) => {
        if (q) {
            const matchName = String(item.nama_kegiatan || '')
                .toLowerCase()
                .includes(q);
            const matchPelaksana = String(item.pelaksana_kegiatan || '')
                .toLowerCase()
                .includes(q);
            const matchDusun = String(item.lokasi_dusun || '')
                .toLowerCase()
                .includes(q);
            if (!matchName && !matchPelaksana && !matchDusun) {
                return false;
            }
        }

        if (selectedDusun.value && item.lokasi_dusun !== selectedDusun.value) {
            return false;
        }

        if (selectedStatus.value && item.status_proyek !== selectedStatus.value) {
            return false;
        }

        if (
            selectedSumberDana.value &&
            item.sumber_dana !== selectedSumberDana.value
        ) {
            return false;
        }

        return true;
    });
});

function resetFilters() {
    searchQuery.value = '';
    selectedDusun.value = '';
    selectedStatus.value = '';
    selectedSumberDana.value = '';
}

const columns = [
    { key: 'nama_kegiatan', label: 'Kegiatan & Pelaksana' },
    { key: 'lokasi_dusun', label: 'Lokasi & Sumber Dana' },
    { key: 'anggaran', label: 'Pagu & Realisasi', align: 'right' },
    { key: 'progres', label: 'Fisik vs Serapan Dana' },
    { key: 'status', label: 'Status & Foto' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

// State Slide-Over Drawer Pratinjau Foto 0% - 50% - 100%
const isDrawerOpen = ref(false);
const activeProyek = ref(null);

function openPhotoDrawer(proyekItem) {
    activeProyek.value = proyekItem;
    isDrawerOpen.value = true;
}

async function confirmDeleteProyek(proyekItem) {
    const ok = await confirm.ask({
        title: 'Hapus Proyek Pembangunan?',
        message: `Apakah Anda yakin ingin menghapus proyek "${proyekItem.nama_kegiatan}"? Total Pagu dan Realisasi Bidang 2 pada APBDes TA ${props.tahun_anggaran} akan otomatis dihitung ulang.`,
        confirmText: 'Ya, Hapus Proyek',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/pembangunan/${proyekItem.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Monitoring Pembangunan Fisik TA ${tahun_anggaran}`" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                :title="`Monitoring Pembangunan Fisik TA ${tahun_anggaran}`"
                subtitle="Pemantauan progres fisik lapangan, dokumentasi bertahap 3 titik foto (0%–50%–100%), dan sinkronisasi otomatis ke APBDes Bidang 2."
            >
                <template #actions>
                    <a
                        :href="`/panel/pembangunan/cetak-pdf?tahun=${tahun_anggaran}`"
                        data-testid="btn-cetak-rekap-proyek"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Printer class="mr-1.5 h-4 w-4" />
                        <span>Cetak Rekap F4</span>
                    </a>

                    <Link
                        v-if="isOperator"
                        :href="`/panel/pembangunan/tambah?tahun=${tahun_anggaran}`"
                        data-testid="btn-tambah-proyek"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-medium text-white transition-colors hover:bg-blue-800"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Tambah Proyek</span>
                    </Link>
                </template>
            </PageHeader>

            <!-- 4 Kartu Ringkasan Metrik Tahun Aktif -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total Kegiatan Fisik"
                    :value="ringkasan?.total_proyek ?? proyek.length"
                    :subtext="`${ringkasan?.jumlah_selesai ?? 0} selesai · ${ringkasan?.jumlah_sedang_dikerjakan ?? 0} dikerjakan`"
                    :icon="Hammer"
                />
                <StatCard
                    label="Total Pagu Bidang 2"
                    :value="formatRupiah(ringkasan?.total_pagu || 0)"
                    subtext="Tersinkronisasi otomatis ke APBDes"
                    :icon="FileSpreadsheet"
                />
                <StatCard
                    label="Realisasi Dana Proyek"
                    :value="formatRupiah(ringkasan?.total_realisasi || 0)"
                    :subtext="`Serapan anggaran ${formatPercentage(ringkasan?.persentase_serapan || 0)}`"
                />
                <StatCard
                    label="Rata-Rata Capaian Fisik"
                    :value="`${ringkasan?.rata_progres_fisik ?? 0}%`"
                    :subtext="`Progres fisik lapangan TA ${tahun_anggaran}`"
                />
            </div>

            <!-- Baris Filter Terpadu -->
            <UnifiedFilterBar
                v-model="searchQuery"
                search-placeholder="Cari nama kegiatan, pelaksana, atau dusun..."
                :has-active-filters="hasActiveFilters"
                @reset="resetFilters"
            >
                <template #filters>
                    <select
                        v-model="selectedDusun"
                        aria-label="Filter Dusun"
                        data-testid="filter-dusun-proyek"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option value="">Semua Dusun</option>
                        <option
                            v-for="dusun in daftar_dusun"
                            :key="dusun"
                            :value="dusun"
                        >
                            {{ dusun }}
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        aria-label="Filter Status Proyek"
                        data-testid="filter-status-proyek"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option value="">Semua Status</option>
                        <option
                            v-for="opt in opsi_status"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>

                    <select
                        v-model="selectedSumberDana"
                        aria-label="Filter Sumber Dana"
                        data-testid="filter-sumber-dana-proyek"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option value="">Semua Sumber Dana</option>
                        <option
                            v-for="opt in opsi_sumber_dana"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </template>
            </UnifiedFilterBar>

            <!-- Tabel Monitoring Proyek Fisik -->
            <DataTable
                :columns="columns"
                :rows="filteredProjects"
                :empty-title="`Belum ada data proyek pembangunan pada Tahun Anggaran ${tahun_anggaran}`"
                empty-description="Tambahkan kegiatan pembangunan fisik desa untuk mulai memantau progres dan menyinkronkan APBDes Bidang 2."
                :empty-action-label="isOperator ? '+ Tambah Proyek' : ''"
                @empty-action="router.visit(`/panel/pembangunan/tambah?tahun=${tahun_anggaran}`)"
            >
                <template #cell-nama_kegiatan="{ row }">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ row.nama_kegiatan }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            <span v-if="row.volume_fisik">Volume: {{ row.volume_fisik }}</span>
                            <span v-if="row.volume_fisik && row.pelaksana_kegiatan"> &bull; </span>
                            <span v-if="row.pelaksana_kegiatan">{{ row.pelaksana_kegiatan }}</span>
                        </p>
                    </div>
                </template>

                <template #cell-lokasi_dusun="{ row }">
                    <div>
                        <div class="flex items-center gap-1 text-sm font-medium text-slate-800">
                            <MapPin class="h-3.5 w-3.5 text-slate-400" />
                            <span>{{ row.lokasi_dusun }}</span>
                        </div>
                        <span class="mt-1 inline-block rounded border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                            {{ row.sumber_dana_label || row.sumber_dana }}
                        </span>
                    </div>
                </template>

                <template #cell-anggaran="{ row }">
                    <div class="text-right tabular-nums">
                        <p class="font-semibold text-slate-900">
                            {{ formatRupiah(row.pagu_anggaran) }}
                        </p>
                        <p class="mt-0.5 text-xs font-medium text-emerald-700">
                            Realisasi: {{ formatRupiah(row.realisasi_anggaran) }}
                        </p>
                    </div>
                </template>

                <template #cell-progres="{ row }">
                    <div class="min-w-[165px] space-y-2 tabular-nums">
                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Fisik</span>
                                <span class="font-semibold text-slate-900">{{ row.progres_fisik }}%</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-blue-700"
                                    :style="{ width: `${Math.min(100, Number(row.progres_fisik || 0))}%` }"
                                />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-slate-600">Dana</span>
                                <span class="font-semibold text-emerald-700">{{ formatPercentage(row.persentase_dana || 0) }}</span>
                            </div>
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full bg-emerald-600"
                                    :style="{ width: `${Math.min(100, Number(row.persentase_dana || 0))}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="space-y-1.5">
                        <StatusBadge
                            :status="row.status_proyek"
                            :label="row.status_proyek_label"
                        />
                        <div class="flex items-center gap-1 text-[11px] font-medium tabular-nums">
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 border',
                                    row.foto_0_path
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-slate-50 text-slate-400',
                                ]"
                            >
                                0%
                            </span>
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 border',
                                    row.foto_50_path
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-slate-50 text-slate-400',
                                ]"
                            >
                                50%
                            </span>
                            <span
                                :class="[
                                    'rounded px-1.5 py-0.5 border',
                                    row.foto_100_path
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                        : 'border-slate-200 bg-slate-50 text-slate-400',
                                ]"
                            >
                                100%
                            </span>
                        </div>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-1.5">
                        <BaseButton
                            variant="ghost"
                            size="sm"
                            :data-testid="`btn-detail-proyek-${row.id}`"
                            @click="openPhotoDrawer(row)"
                        >
                            <Eye class="mr-1 h-4 w-4" />
                            <span>Foto &amp; Detail</span>
                        </BaseButton>

                        <Link
                            v-if="isOperator"
                            :href="`/panel/pembangunan/${row.id}/edit`"
                            :data-testid="`btn-edit-proyek-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md px-2.5 text-xs font-medium text-blue-700 hover:bg-blue-50"
                        >
                            <Pencil class="mr-1 h-3.5 w-3.5" />
                            <span>Edit</span>
                        </Link>

                        <button
                            v-if="isOperator"
                            type="button"
                            :data-testid="`btn-delete-proyek-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md px-2 text-xs font-medium text-red-600 hover:bg-red-50"
                            @click="confirmDeleteProyek(row)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Slide-Over Drawer Pratinjau Foto 0% - 50% - 100% & Detail Proyek -->
        <SlideOverDrawer
            :show="isDrawerOpen"
            :title="activeProyek?.nama_kegiatan || 'Detail Proyek Pembangunan'"
            subtitle="Dokumentasi fisik 3 titik tahapan (0%–50%–100%) dan rincian anggaran kegiatan."
            @close="isDrawerOpen = false"
        >
            <div v-if="activeProyek" class="space-y-6">
                <div class="grid grid-cols-2 gap-4 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <div>
                        <p class="text-xs text-slate-500">Lokasi Dusun</p>
                        <p class="font-semibold text-slate-900">{{ activeProyek.lokasi_dusun }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Sumber Dana</p>
                        <p class="font-semibold text-slate-900">{{ activeProyek.sumber_dana_label || activeProyek.sumber_dana }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pagu Anggaran</p>
                        <p class="font-semibold tabular-nums text-slate-900">{{ formatRupiah(activeProyek.pagu_anggaran) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Realisasi Dana</p>
                        <p class="font-semibold tabular-nums text-emerald-700">
                            {{ formatRupiah(activeProyek.realisasi_anggaran) }}
                            ({{ formatPercentage(activeProyek.persentase_dana || 0) }})
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Volume Fisik</p>
                        <p class="font-medium text-slate-800">{{ activeProyek.volume_fisik || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500">Pelaksana &amp; Waktu</p>
                        <p class="font-medium text-slate-800">
                            {{ activeProyek.pelaksana_kegiatan || 'TPK Desa' }}
                            <span v-if="activeProyek.waktu_pelaksanaan"> ({{ activeProyek.waktu_pelaksanaan }})</span>
                        </p>
                    </div>
                </div>

                <!-- Catatan Kendala Lapangan (Jika Ada) -->
                <div
                    v-if="activeProyek.catatan_kendala"
                    class="rounded-md border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"
                >
                    <p class="font-semibold">Catatan Kendala Lapangan:</p>
                    <p class="mt-1">{{ activeProyek.catatan_kendala }}</p>
                </div>

                <!-- Dokumentasi 3 Titik Foto -->
                <div class="space-y-4">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <Camera class="h-4 w-4 text-blue-700" />
                        <span>Dokumentasi Tahapan Fisik (0% &ndash; 50% &ndash; 100%)</span>
                    </h3>

                    <div
                        v-for="cp in [
                            { key: 'foto_0_url', label: 'Titik Awal (0%)', desc: 'Kondisi sebelum pengerjaan dimulai' },
                            { key: 'foto_50_url', label: 'Titik Tengah (50%)', desc: 'Kondisi pengerjaan berjalan' },
                            { key: 'foto_100_url', label: 'Titik Akhir (100%)', desc: 'Kondisi proyek selesai 100%' },
                        ]"
                        :key="cp.key"
                        class="rounded-lg border border-slate-200 bg-white p-3.5"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold text-slate-800">{{ cp.label }}</span>
                            <span class="text-[11px] text-slate-500">{{ cp.desc }}</span>
                        </div>
                        <div
                            v-if="activeProyek[cp.key]"
                            class="overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                        >
                            <img
                                :src="activeProyek[cp.key]"
                                :alt="cp.label"
                                class="h-48 w-full object-cover"
                            />
                        </div>
                        <div
                            v-else
                            class="flex h-28 items-center justify-center rounded-md border border-dashed border-slate-200 bg-slate-50 text-xs text-slate-400"
                        >
                            Belum ada unggahan foto untuk {{ cp.label }}
                        </div>
                    </div>
                </div>
            </div>
        </SlideOverDrawer>
    </AppLayout>
</template>
