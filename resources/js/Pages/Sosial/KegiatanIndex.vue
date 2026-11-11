<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Image as ImageIcon,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import KegiatanModal from '@/Components/KegiatanModal.vue';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    kegiatan: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    daftar_dusun: {
        type: Array,
        default: () => ['Dusun I', 'Dusun II', 'Dusun III'],
    },
    opsi_kategori: {
        type: Array,
        default: () => [
            { value: 'penyaluran_bansos', label: 'Penyaluran Bantuan Sosial' },
            { value: 'posyandu_kesehatan', label: 'Posyandu & Kesehatan' },
            { value: 'gotong_royong', label: 'Gotong Royong' },
            { value: 'musdes', label: 'Musyawarah Desa (Musdes)' },
            { value: 'pelatihan_bumdes', label: 'Pelatihan & BUMDes' },
        ],
    },
    opsi_status_publikasi: {
        type: Array,
        default: () => [
            { value: 'draft', label: 'Draft' },
            { value: 'published', label: 'Published' },
        ],
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const searchQuery = ref(props.filters?.search || '');
const selectedKategori = ref(props.filters?.kategori || '');
const selectedStatus = ref(props.filters?.status_publikasi || '');

const hasActiveFilters = computed(() =>
    Boolean(
        searchQuery.value || selectedKategori.value || selectedStatus.value,
    ),
);

function resetFilters() {
    searchQuery.value = '';
    selectedKategori.value = '';
    selectedStatus.value = '';
}

const filteredKegiatan = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.kegiatan || []).filter((item) => {
        if (q) {
            const matchJudul = String(item.judul || '')
                .toLowerCase()
                .includes(q);
            const matchLokasi = String(item.lokasi_dusun || '')
                .toLowerCase()
                .includes(q);
            if (!matchJudul && !matchLokasi) {
                return false;
            }
        }
        if (selectedKategori.value && item.kategori !== selectedKategori.value) {
            return false;
        }
        if (
            selectedStatus.value &&
            item.status_publikasi !== selectedStatus.value
        ) {
            return false;
        }
        return true;
    });
});

const columns = [
    { key: 'kegiatan', label: 'Sampul & Judul Kegiatan' },
    { key: 'kategori_lokasi', label: 'Kategori & Lokasi' },
    { key: 'tanggal_foto', label: 'Tanggal & Galeri' },
    { key: 'status', label: 'Status Publikasi' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

function handleTogglePublikasi(item) {
    const nextStatus =
        item.status_publikasi === 'published' ? 'draft' : 'published';

    router.patch(
        `/panel/sosial/kegiatan/${item.id}/publikasi`,
        {
            status_publikasi: nextStatus,
        },
        {
            preserveScroll: true,
        },
    );
}

async function handleDeleteKegiatan(item) {
    const ok = await confirm.ask({
        title: 'Hapus Dokumentasi Kegiatan Desa?',
        message: `Kegiatan "${item.judul}" beserta foto galerinya akan dihapus dari daftar aktif.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/sosial/kegiatan/${item.id}`, {
        preserveScroll: true,
    });
}

const showModal = ref(false);
const isEditing = ref(false);
const selectedKegiatan = ref(null);

function openCreateModal() {
    isEditing.value = false;
    selectedKegiatan.value = null;
    showModal.value = true;
}

function openEditModal(item) {
    isEditing.value = true;
    selectedKegiatan.value = item;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    selectedKegiatan.value = null;
}
</script>

<template>
    <Head title="Dokumentasi Kegiatan Desa" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                title="Dokumentasi & Publikasi Kegiatan Desa"
                subtitle="Kelola berita dan galeri 1–5 foto bukti pelaksanaan kegiatan kemasyarakatan Desa Tamarenja."
            >
                <template #actions>
                    <BaseButton
                        v-if="isOperator"
                        variant="primary"
                        data-testid="btn-buat-kegiatan"
                        @click="openCreateModal"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Buat Dokumentasi Kegiatan</span>
                    </BaseButton>
                </template>
            </PageHeader>

            <!-- Filter Bar -->
            <UnifiedFilterBar
                v-model="searchQuery"
                search-placeholder="Cari judul kegiatan atau lokasi dusun..."
                :has-active-filters="hasActiveFilters"
                @reset="resetFilters"
            >
                <template #filters>
                    <select
                        v-model="selectedKategori"
                        aria-label="Filter Kategori Kegiatan"
                        data-testid="filter-kategori-kegiatan"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                    >
                        <option value="">Semua Kategori</option>
                        <option
                            v-for="opt in opsi_kategori"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        aria-label="Filter Status Publikasi"
                        data-testid="filter-status-publikasi"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                    >
                        <option value="">Semua Status</option>
                        <option
                            v-for="st in opsi_status_publikasi"
                            :key="st.value"
                            :value="st.value"
                        >
                            {{ st.label }}
                        </option>
                    </select>
                </template>
            </UnifiedFilterBar>

            <!-- Tabel Dokumentasi Kegiatan -->
            <DataTable
                :columns="columns"
                :rows="filteredKegiatan"
                empty-title="Belum ada dokumentasi kegiatan desa"
                empty-description="Publikasikan bukti pelaksanaan penyaluran Bansos, Posyandu, Musdes, atau Gotong Royong lengkap dengan galeri foto."
            >
                <template #cell-kegiatan="{ row }">
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-12 w-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                        >
                            <img
                                v-if="row.sampul_url"
                                :src="row.sampul_url"
                                :alt="row.judul"
                                class="h-full w-full object-cover"
                            />
                            <ImageIcon
                                v-else
                                class="h-5 w-5 text-slate-400"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">
                                {{ row.judul }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                /kegiatan/{{ row.slug }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #cell-kategori_lokasi="{ row }">
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ row.kategori_label }}
                        </p>
                        <p class="text-xs text-slate-500">
                            Lokasi: {{ row.lokasi_dusun }}
                        </p>
                    </div>
                </template>

                <template #cell-tanggal_foto="{ row }">
                    <div class="text-xs tabular-nums">
                        <p class="font-medium text-slate-800">
                            {{ row.tanggal_kegiatan }}
                        </p>
                        <p class="text-slate-500">
                            {{ row.jumlah_foto || 0 }} / 5 Foto Galeri
                        </p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <StatusBadge
                        :status="row.status_publikasi"
                        :label="row.status_publikasi_label"
                    />
                </template>

                <template #cell-actions="{ row }">
                    <div
                        v-if="isOperator"
                        class="flex flex-wrap items-center justify-end gap-1.5"
                    >
                        <button
                            type="button"
                            :data-testid="`btn-toggle-publikasi-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            @click="handleTogglePublikasi(row)"
                        >
                            <RotateCcw
                                v-if="row.status_publikasi === 'published'"
                                class="mr-1 h-3.5 w-3.5 text-amber-600"
                            />
                            <CheckCircle2
                                v-else
                                class="mr-1 h-3.5 w-3.5 text-emerald-600"
                            />
                            <span>
                                {{
                                    row.status_publikasi === 'published'
                                        ? 'Tarik ke Draft'
                                        : 'Terbitkan'
                                }}
                            </span>
                        </button>

                        <button
                            type="button"
                            :data-testid="`btn-edit-kegiatan-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            @click="openEditModal(row)"
                        >
                            <Pencil class="mr-1 h-3.5 w-3.5" />
                            <span>Edit</span>
                        </button>

                        <button
                            type="button"
                            :data-testid="`btn-delete-kegiatan-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md px-2 text-xs font-medium text-red-600 hover:bg-red-50"
                            @click="handleDeleteKegiatan(row)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <span v-else class="text-xs text-slate-400">Read-Only</span>
                </template>
            </DataTable>

            <!-- Modal Buat & Edit Dokumentasi Kegiatan Desa -->
            <KegiatanModal
                :show="showModal"
                :is-edit="isEditing"
                :kegiatan="selectedKegiatan"
                :daftar-dusun="daftar_dusun"
                :opsi-kategori="opsi_kategori"
                :opsi-status-publikasi="opsi_status_publikasi"
                @close="closeModal"
            />
        </div>
    </AppLayout>
</template>
