<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Ban,
    Plus,
    Printer,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BuatSuratLoketModal from '@/Components/BuatSuratLoketModal.vue';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    tahun_surat: {
        type: Number,
        required: true,
    },
    surat_keluar: {
        type: Array,
        default: () => [],
    },
    jumlah_menunggu: {
        type: Number,
        default: 0,
    },
    cetak_id: {
        type: Number,
        default: null,
    },
    cetak_pdf_url: {
        type: String,
        default: null,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    opsi_jenis_surat: {
        type: Array,
        default: () => [],
    },
    pejabat_penandatangan: {
        type: Array,
        default: () => [],
    },
    tanggal_hari_ini: {
        type: String,
        default: () => new Date().toISOString().slice(0, 10),
    },
    buka_modal_buat: {
        type: Boolean,
        default: false,
    },
    residents: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const persuratanTabs = computed(() => [
    {
        key: 'antrean',
        label: 'Antrean Online',
        href: '/panel/persuratan/antrean',
        badge: props.jumlah_menunggu,
        active: false,
    },
    {
        key: 'surat-keluar',
        label: 'Buku Agenda Surat Keluar',
        href: '/panel/persuratan/surat-keluar',
        active: true,
    },
    {
        key: 'surat-masuk',
        label: 'Buku Agenda Surat Masuk',
        href: '/panel/persuratan/surat-masuk',
        active: false,
    },
]);

const searchQuery = ref(props.filters?.search || '');
const selectedJenis = ref(props.filters?.jenis_surat || '');
const selectedStatus = ref(props.filters?.status || '');

const hasActiveFilters = computed(() =>
    Boolean(
        searchQuery.value || selectedJenis.value || selectedStatus.value,
    ),
);

const filteredSurat = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.surat_keluar || []).filter((item) => {
        if (q) {
            const matchNomor = String(item.nomor_surat || '')
                .toLowerCase()
                .includes(q);
            const matchNama = String(item.nama_pemohon || '')
                .toLowerCase()
                .includes(q);
            const matchNik = String(item.nik_pemohon || '').includes(q);
            if (!matchNomor && !matchNama && !matchNik) {
                return false;
            }
        }

        if (selectedJenis.value && item.jenis_surat !== selectedJenis.value) {
            return false;
        }

        if (selectedStatus.value && item.status !== selectedStatus.value) {
            return false;
        }

        return true;
    });
});

function resetFilters() {
    searchQuery.value = '';
    selectedJenis.value = '';
    selectedStatus.value = '';
}

const columns = [
    { key: 'nomor_surat', label: 'Nomor & Tanggal Surat' },
    { key: 'jenis_surat', label: 'Jenis Surat & Keperluan' },
    { key: 'pemohon', label: 'Pemohon / Subjek Warga' },
    { key: 'status', label: 'Status' },
    {
        key: 'actions',
        label: 'Aksi',
        align: 'right',
        class: 'whitespace-nowrap',
        cellClass: 'whitespace-nowrap',
    },
];

// Modal Buat Surat di Loket
const showBuatModal = ref(Boolean(props.buka_modal_buat));

watch(
    () => props.buka_modal_buat,
    (val) => {
        if (val) {
            showBuatModal.value = true;
        }
    },
    { immediate: true },
);

// Pemicu otomatis pembukaan tab/unduhan PDF pasca-terbit dari loket
watch(
    () => props.cetak_pdf_url,
    (url) => {
        if (
            url &&
            typeof window !== 'undefined' &&
            typeof window.open === 'function'
        ) {
            window.open(url, '_blank');
        }
    },
    { immediate: true },
);

// Modal & Konfirmasi Pembatalan Surat Terbit
const showBatalkanModal = ref(false);
const targetBatalkan = ref(null);
const alasanPembatalan = ref('');

function openBatalkanModal(item) {
    targetBatalkan.value = item;
    alasanPembatalan.value = '';
    showBatalkanModal.value = true;
}

async function submitBatalkan() {
    if (!targetBatalkan.value || !alasanPembatalan.value.trim()) {
        return;
    }

    const ok = await confirm.ask({
        title: 'Batalkan Surat Resmi pada Buku Agenda?',
        message: `Surat Nomor "${targetBatalkan.value.nomor_surat}" akan diubah statusnya menjadi Dibatalkan (tanpa menghapus nomor urut agenda) dan QR Code surat akan menampilkan peringatan pembatalan.`,
        confirmText: 'Ya, Batalkan Surat',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.patch(
        `/panel/persuratan/surat-keluar/${targetBatalkan.value.id}/batalkan`,
        {
            alasan_pembatalan: alasanPembatalan.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showBatalkanModal.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Buku Agenda Surat Keluar TA ${tahun_surat}`" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                title="Persuratan & Buku Agenda Desa"
                :subtitle="`Buku Agenda Surat Keluar Resmi Tahun Anggaran ${tahun_surat}. Nomor urut dijaga utuh dan anti-bolong.`"
            >
                <template #actions>
                    <a
                        :href="`/panel/persuratan/surat-keluar/cetak-agenda?tahun=${tahun_surat}`"
                        data-testid="btn-cetak-agenda-keluar"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Printer class="mr-1.5 h-4 w-4" />
                        <span>Cetak Agenda F4</span>
                    </a>

                    <BaseButton
                        v-if="isOperator"
                        variant="primary"
                        data-testid="btn-buat-surat-loket"
                        @click="showBuatModal = true"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Buat Surat</span>
                    </BaseButton>
                </template>
            </PageHeader>

            <!-- Navigasi 3 Tab Grup Persuratan -->
            <ContextualTabs :tabs="persuratanTabs" />

            <!-- Filter Bar -->
            <UnifiedFilterBar
                v-model="searchQuery"
                search-placeholder="Cari nomor surat, nama warga, atau NIK..."
                :has-active-filters="hasActiveFilters"
                @reset="resetFilters"
            >
                <template #filters>
                    <select
                        v-model="selectedJenis"
                        aria-label="Filter Jenis Surat"
                        data-testid="filter-jenis-surat-keluar"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option value="">Semua Jenis Surat</option>
                        <option
                            v-for="opt in opsi_jenis_surat"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>

                    <select
                        v-model="selectedStatus"
                        aria-label="Filter Status Surat Keluar"
                        data-testid="filter-status-surat-keluar"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option value="">Semua Status</option>
                        <option value="terbit">Terbit / Sah</option>
                        <option value="dibatalkan">Dibatalkan</option>
                    </select>
                </template>
            </UnifiedFilterBar>

            <!-- Tabel Buku Agenda Surat Keluar -->
            <DataTable
                :columns="columns"
                :rows="filteredSurat"
                :empty-title="`Belum ada catatan Buku Agenda Surat Keluar pada Tahun ${tahun_surat}`"
                empty-description="Surat yang diterbitkan dari Loket maupun hasil verifikasi permohonan online akan otomatis tercatat di sini."
            >
                <template #cell-nomor_surat="{ row }">
                    <div>
                        <p class="font-semibold tabular-nums text-slate-900">
                            {{ row.nomor_surat }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500">
                            Tgl: {{ row.tanggal_surat || '—' }}
                        </p>
                    </div>
                </template>

                <template #cell-jenis_surat="{ row }">
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ row.jenis_surat_label }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ row.keperluan }}
                        </p>
                    </div>
                </template>

                <template #cell-pemohon="{ row }">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ row.nama_pemohon }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500">
                            NIK: {{ row.nik_pemohon }}
                        </p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="space-y-1">
                        <StatusBadge
                            :status="row.status"
                            :label="row.status_label"
                        />
                        <p
                            v-if="row.nama_penandatangan && row.status === 'terbit'"
                            class="text-xs text-slate-500"
                        >
                            TTD: {{ row.nama_penandatangan }}
                        </p>
                        <p
                            v-if="row.alasan_pembatalan"
                            class="text-xs text-red-600"
                        >
                            {{ row.alasan_pembatalan }}
                        </p>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                        <a
                            v-if="row.status === 'terbit'"
                            :href="`/panel/persuratan/surat-keluar/${row.id}/pdf`"
                            :data-testid="`btn-cetak-ulang-${row.id}`"
                            class="inline-flex h-8 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <Printer class="mr-1 h-3.5 w-3.5" />
                            <span>Cetak</span>
                        </a>

                        <button
                            v-if="row.status === 'terbit' && isOperator"
                            type="button"
                            :data-testid="`btn-batalkan-surat-${row.id}`"
                            class="inline-flex h-8 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            @click="openBatalkanModal(row)"
                        >
                            <Ban class="mr-1 h-3.5 w-3.5 text-red-600" />
                            <span>Batalkan</span>
                        </button>

                        <span
                            v-if="row.status === 'dibatalkan'"
                            class="text-xs italic text-slate-400"
                        >
                            &mdash;
                        </span>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modal Pembatalan Surat Terbit -->
        <BaseModal
            :show="showBatalkanModal"
            title="Batalkan Surat Keluar pada Buku Agenda"
            max-width="md"
            @close="showBatalkanModal = false"
        >
            <div class="space-y-4">
                <p class="text-xs text-slate-600">
                    Surat yang sudah terbit tidak dapat dihapus agar nomor urut Buku Agenda tidak bolong. Masukkan alasan pembatalan di bawah ini:
                </p>
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">
                        Alasan Pembatalan Surat <span class="text-red-600">*</span>
                    </label>
                    <textarea
                        v-model="alasanPembatalan"
                        rows="3"
                        data-testid="input-alasan-pembatalan"
                        placeholder="Contoh: Kesalahan penulisan tempat lahir pada permohonan awal"
                        class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm text-slate-900"
                    />
                </div>
                <div class="flex items-center justify-end gap-2">
                    <BaseButton variant="outline" @click="showBatalkanModal = false">
                        Kembali
                    </BaseButton>
                    <BaseButton
                        variant="destructive"
                        data-testid="btn-confirm-batalkan-surat"
                        @click="submitBatalkan"
                    >
                        Batalkan Surat
                    </BaseButton>
                </div>
            </div>
        </BaseModal>

        <!-- Modal Buat Surat di Loket (10 Template Baku) -->
        <BuatSuratLoketModal
            :show="showBuatModal"
            :opsi_jenis_surat="opsi_jenis_surat"
            :pejabat_penandatangan="pejabat_penandatangan"
            :tanggal_hari_ini="tanggal_hari_ini"
            :residents="residents"
            @close="showBuatModal = false"
        />
    </AppLayout>
</template>
