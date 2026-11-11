<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
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
import BaseButton from '@/Components/BaseButton.vue';
import ProyekModal from '@/Components/ProyekModal.vue';
import ProyekDetailModal from '@/Components/ProyekDetailModal.vue';
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
    koordinat_default: {
        type: Object,
        default: () => ({ latitude: -0.5835, longitude: 119.7932 }),
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

// State Modal Tambah & Edit Proyek
const isProyekModalOpen = ref(false);
const isEditModalMode = ref(false);
const modalProyekData = ref(null);

function openCreateModal() {
    modalProyekData.value = null;
    isEditModalMode.value = false;
    isProyekModalOpen.value = true;
}

function openEditModal(proyekItem) {
    modalProyekData.value = proyekItem;
    isEditModalMode.value = true;
    isProyekModalOpen.value = true;
}

function closeProyekModal() {
    isProyekModalOpen.value = false;
    modalProyekData.value = null;
    isEditModalMode.value = false;
}

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
    { key: 'nama_kegiatan', label: 'Kegiatan & Lokasi' },
    { key: 'anggaran', label: 'Pagu Anggaran', align: 'right' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

// State Modal Detail Proyek
const isDetailModalOpen = ref(false);
const selectedDetailProyek = ref(null);

function openDetailModal(proyekItem) {
    selectedDetailProyek.value = proyekItem;
    isDetailModalOpen.value = true;
}

function closeDetailModal() {
    isDetailModalOpen.value = false;
    selectedDetailProyek.value = null;
}

function handleEditFromDetail(proyekItem) {
    closeDetailModal();
    openEditModal(proyekItem);
}

async function confirmDeleteProyek(proyekItem) {
    const ok = await confirm.ask({
        title: 'Hapus Proyek Pembangunan?',
        message: `Apakah Anda yakin ingin menghapus proyek "${proyekItem.nama_kegiatan}"? Data proyek dan foto dokumentasi akan dihapus dari sistem.`,
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
    <Head :title="`Dokumentasi Pembangunan Infrastruktur Desa TA ${tahun_anggaran}`" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                :title="`Dokumentasi Pembangunan Infrastruktur Desa TA ${tahun_anggaran}`"
                subtitle="Katalog transparansi proyek fisik desa, pemantauan progres berkala, titik lokasi GPS, dan dokumentasi foto lapangan (0%–50%–100%) untuk publik."
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

                    <button
                        v-if="isOperator"
                        type="button"
                        data-testid="btn-tambah-proyek"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-medium text-white transition-colors hover:bg-blue-800"
                        @click="openCreateModal"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Tambah Proyek</span>
                    </button>
                </template>
            </PageHeader>

            <!-- 4 Kartu Ringkasan Metrik Tahun Aktif -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total Kegiatan Fisik"
                    :value="ringkasan?.total_proyek ?? proyek.length"
                    subtext="Kegiatan infrastruktur desa TA aktif"
                    :icon="Hammer"
                />
                <StatCard
                    label="Proyek Selesai"
                    :value="ringkasan?.jumlah_selesai ?? 0"
                    subtext="Tuntas 100% dan terdokumentasi"
                    :icon="CheckCircle2"
                />
                <StatCard
                    label="Sedang Dikerjakan"
                    :value="ringkasan?.jumlah_sedang_dikerjakan ?? 0"
                    subtext="Pengerjaan fisik aktif di lapangan"
                    :icon="Clock"
                />
                <StatCard
                    label="Total Alokasi Anggaran"
                    :value="formatRupiah(ringkasan?.total_pagu || 0)"
                    subtext="Akumulasi pagu proyek infrastruktur"
                    :icon="FileSpreadsheet"
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
                empty-description="Tambahkan kegiatan pembangunan fisik desa untuk mulai mendokumentasikan progres dan transparansi publik."
                :empty-action-label="isOperator ? '+ Tambah Proyek' : ''"
                @empty-action="openCreateModal"
            >
                <template #cell-nama_kegiatan="{ row }">
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ row.nama_kegiatan }}
                        </p>
                        <p class="mt-0.5 flex items-center gap-1 text-xs text-slate-500">
                            <MapPin class="h-3.5 w-3.5 text-slate-400" />
                            <span>{{ row.lokasi_dusun }}</span>
                        </p>
                    </div>
                </template>

                <template #cell-anggaran="{ row }">
                    <div class="text-right tabular-nums">
                        <p class="font-semibold text-slate-900">
                            {{ formatRupiah(row.pagu_anggaran) }}
                        </p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge
                        :status="row.status_proyek"
                        :label="row.status_proyek_label"
                    />
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-1.5 whitespace-nowrap">
                        <button
                            type="button"
                            :data-testid="`btn-detail-proyek-${row.id}`"
                            class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-2.5 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100"
                            @click="openDetailModal(row)"
                        >
                            <Eye class="h-3.5 w-3.5" />
                            <span>Detail</span>
                        </button>

                        <template v-if="isOperator">
                            <button
                                type="button"
                                :data-testid="`btn-edit-proyek-${row.id}`"
                                class="inline-flex h-8 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                @click="openEditModal(row)"
                            >
                                <Pencil class="mr-1 h-3.5 w-3.5" />
                                <span>Edit</span>
                            </button>

                            <button
                                type="button"
                                :data-testid="`btn-delete-proyek-${row.id}`"
                                class="inline-flex h-8 shrink-0 items-center justify-center rounded-md border border-slate-200 bg-white px-2 text-xs font-medium text-red-600 hover:border-red-200 hover:bg-red-50"
                                @click="confirmDeleteProyek(row)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </template>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modal Detail Proyek Pembangunan Fisik & Galeri 3 Titik Foto -->
        <ProyekDetailModal
            :show="isDetailModalOpen"
            :proyek="selectedDetailProyek"
            :is-operator="isOperator"
            @close="closeDetailModal"
            @edit="handleEditFromDetail"
        />

        <!-- Modal Tambah & Edit Proyek Pembangunan Fisik -->
        <ProyekModal
            :show="isProyekModalOpen"
            :is-edit="isEditModalMode"
            :proyek="modalProyekData"
            :tahun-anggaran="tahun_anggaran"
            :daftar-dusun="daftar_dusun"
            :opsi-sumber-dana="opsi_sumber_dana"
            :koordinat-default="koordinat_default"
            @close="closeProyekModal"
        />
    </AppLayout>
</template>
