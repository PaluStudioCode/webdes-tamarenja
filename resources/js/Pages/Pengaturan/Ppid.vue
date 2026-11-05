<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import {
    ExternalLink,
    Eye,
    EyeOff,
    FileText,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import DataTable from '@/Components/DataTable.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import { useConfirm } from '@/Composables/useConfirm';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    dokumenList: {
        type: [Array, Object],
        default: () => [],
    },
    kategoriOptions: {
        type: Array,
        default: () => [
            { value: 'perdes_apbdes', label: 'Perdes APBDes' },
            { value: 'lpj', label: 'Laporan Pertanggungjawaban (LPJ)' },
            {
                value: 'rkpdes',
                label: 'RKPDes (Rencana Kerja Pemerintah Desa)',
            },
            {
                value: 'rpjmdes',
                label: 'RPJMDes (Rencana Pembangunan Jangka Menengah)',
            },
            { value: 'sk_regulasi', label: 'SK & Regulasi Desa' },
            { value: 'panduan_layanan', label: 'Panduan & Standar Layanan' },
        ],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', kategori: '' }),
    },
});

const confirm = useConfirm();

const pengaturanTabs = [
    {
        key: 'profil-desa',
        label: 'Profil & Kop',
        href: '/panel/pengaturan/profil-desa',
    },
    { key: 'sotk', label: 'SOTK', href: '/panel/pengaturan/sotk' },
    { key: 'ppid', label: 'Dokumen PPID', href: '/panel/pengaturan/ppid' },
    {
        key: 'pengguna',
        label: 'Pengguna',
        href: '/panel/pengaturan/pengguna',
    },
    { key: 'backup', label: 'Backup Data', href: '/panel/pengaturan/backup' },
];

const columns = [
    { key: 'judul_dokumen', label: 'Judul Dokumen Resmi' },
    { key: 'kategori_label', label: 'Kategori' },
    { key: 'tahun_dokumen', label: 'Tahun' },
    { key: 'ukuran_file_kb', label: 'Ukuran & Unduhan' },
    { key: 'is_publik', label: 'Status Tayang' },
];

const searchQuery = ref(props.filters?.search || '');
const kategoriFilter = ref(props.filters?.kategori || '');

const filterDefinitions = computed(() => [
    {
        key: 'kategori',
        label: 'Kategori PPID',
        value: kategoriFilter.value,
        allLabel: 'Semua Kategori',
        options: props.kategoriOptions,
    },
]);

function applyFilters() {
    router.get(
        '/panel/pengaturan/ppid',
        {
            search: searchQuery.value || undefined,
            kategori: kategoriFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function handleFilterChange({ key, value }) {
    if (key === 'kategori') {
        kategoriFilter.value = value;
        applyFilters();
    }
}

function handleResetFilter() {
    searchQuery.value = '';
    kategoriFilter.value = '';
    applyFilters();
}

const isModalOpen = ref(false);
const editingRow = ref(null);
const isSubmitting = ref(false);
const formErrors = ref({});

const formState = ref({
    judul_dokumen: '',
    kategori: 'perdes_apbdes',
    tahun_dokumen: new Date().getFullYear(),
    is_publik: true,
    file_pdf: null,
});

function openCreateModal() {
    editingRow.value = null;
    formErrors.value = {};
    formState.value = {
        judul_dokumen: '',
        kategori: 'perdes_apbdes',
        tahun_dokumen: new Date().getFullYear(),
        is_publik: true,
        file_pdf: null,
    };
    isModalOpen.value = true;
}

function openEditModal(row) {
    editingRow.value = row;
    formErrors.value = {};
    formState.value = {
        judul_dokumen: row.judul_dokumen || '',
        kategori: row.kategori || 'perdes_apbdes',
        tahun_dokumen: row.tahun_dokumen || new Date().getFullYear(),
        is_publik: Boolean(row.is_publik),
        file_pdf: null,
    };
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editingRow.value = null;
}

function submitPpid() {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    formErrors.value = {};

    const payload = {
        judul_dokumen: formState.value.judul_dokumen,
        kategori: formState.value.kategori,
        tahun_dokumen: formState.value.tahun_dokumen || '',
        is_publik: formState.value.is_publik ? 1 : 0,
    };

    if (formState.value.file_pdf) {
        payload.file_pdf = formState.value.file_pdf;
    }

    const url = editingRow.value
        ? `/panel/pengaturan/ppid/${editingRow.value.id}`
        : '/panel/pengaturan/ppid';

    if (editingRow.value) {
        payload._method = 'PUT';
    }

    router.post(url, payload, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
        },
        onError: (errors) => {
            formErrors.value = errors || {};
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

function handleTogglePublikasi(row) {
    router.patch(
        `/panel/pengaturan/ppid/${row.id}/publikasi`,
        {},
        { preserveScroll: true },
    );
}

async function handleDelete(row) {
    const ok = await confirm.ask({
        title: 'Hapus Dokumen PPID?',
        message: `Dokumen "${row.judul_dokumen}" akan dihapus dari Pusat Unduhan Desa.`,
        confirmText: 'Ya, Hapus',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/pengaturan/ppid/${row.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Dokumen Resmi PPID" />

        <PageHeader
            title="Pengaturan Sistem"
            description="Kelola berkas regulasi dan dokumen resmi desa (Perdes, LPJ, RKPDes, RPJMDes) untuk Pusat Unduhan PPID publik."
        >
            <template #actions>
                <BaseButton
                    variant="primary"
                    data-testid="add-ppid-btn"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" :stroke-width="2" />
                    <span>Unggah Dokumen PPID</span>
                </BaseButton>
            </template>
        </PageHeader>

        <ContextualTabs :tabs="pengaturanTabs" />

        <UnifiedFilterBar
            v-model="searchQuery"
            search-placeholder="Cari judul dokumen resmi..."
            :filters="filterDefinitions"
            @search="applyFilters"
            @update:filter="handleFilterChange"
            @reset="handleResetFilter"
        />

        <DataTable
            :columns="columns"
            :rows="dokumenList"
            empty-text="Belum ada dokumen resmi PPID yang diunggah."
        >
            <template #cell-judul_dokumen="{ row }">
                <div class="flex items-start gap-2.5">
                    <FileText
                        class="mt-0.5 h-4 w-4 shrink-0 text-blue-700"
                    />
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ row.judul_dokumen }}
                        </p>
                        <a
                            v-if="row.file_pdf_url"
                            :href="row.file_pdf_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 hover:underline"
                        >
                            <span>Lihat Berkas PDF</span>
                            <ExternalLink class="h-3 w-3" />
                        </a>
                    </div>
                </div>
            </template>

            <template #cell-tahun_dokumen="{ row }">
                <span class="tabular-nums text-slate-700">
                    {{ row.tahun_dokumen || '-' }}
                </span>
            </template>

            <template #cell-ukuran_file_kb="{ row }">
                <div class="text-xs tabular-nums text-slate-600">
                    <p class="font-medium text-slate-800">
                        {{ row.ukuran_file_kb }} KB
                    </p>
                    <p>{{ row.jumlah_unduhan }}x diunduh</p>
                </div>
            </template>

            <template #cell-is_publik="{ row }">
                <StatusBadge :status="row.is_publik ? 'published' : 'draft'" />
            </template>

            <template #actions="{ row }">
                <div class="inline-flex items-center justify-end gap-1.5">
                    <button
                        type="button"
                        :data-testid="`toggle-ppid-btn-${row.id}`"
                        class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="handleTogglePublikasi(row)"
                    >
                        <EyeOff v-if="row.is_publik" class="h-3.5 w-3.5" />
                        <Eye v-else class="h-3.5 w-3.5" />
                        <span>{{
                            row.is_publik ? 'Sembunyikan' : 'Tayangkan'
                        }}</span>
                    </button>

                    <button
                        type="button"
                        :data-testid="`edit-ppid-btn-${row.id}`"
                        class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="openEditModal(row)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        <span>Edit</span>
                    </button>

                    <button
                        type="button"
                        :data-testid="`delete-ppid-btn-${row.id}`"
                        class="inline-flex h-8 items-center gap-1 rounded-md border border-red-200 bg-white px-2.5 text-xs font-medium text-red-600 hover:bg-red-50"
                        @click="handleDelete(row)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                        <span>Hapus</span>
                    </button>
                </div>
            </template>
        </DataTable>

        <BaseModal
            :show="isModalOpen"
            :title="
                editingRow
                    ? 'Edit Dokumen Resmi PPID'
                    : 'Unggah Dokumen Resmi PPID'
            "
            max-width="lg"
            @close="closeModal"
        >
            <form
                id="ppid-form"
                class="space-y-4"
                @submit.prevent="submitPpid"
            >
                <div>
                    <label
                        for="ppid-judul"
                        class="mb-1.5 block text-sm font-medium text-slate-700"
                    >
                        Judul Dokumen Resmi <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="ppid-judul"
                        v-model="formState.judul_dokumen"
                        type="text"
                        placeholder="Contoh: Peraturan Desa Nomor 01 Tahun 2026 tentang APBDes"
                        data-testid="ppid-input-judul"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                    />
                    <p
                        v-if="formErrors.judul_dokumen"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ formErrors.judul_dokumen }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            for="ppid-kategori"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Kategori Dokumen <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="ppid-kategori"
                            v-model="formState.kategori"
                            data-testid="ppid-select-kategori"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option
                                v-for="opt in kategoriOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="ppid-tahun"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Tahun Dokumen
                        </label>
                        <input
                            id="ppid-tahun"
                            v-model.number="formState.tahun_dokumen"
                            type="number"
                            min="2000"
                            max="2100"
                            data-testid="ppid-input-tahun"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>
                </div>

                <div>
                    <FileDropzone
                        v-model="formState.file_pdf"
                        label="Berkas PDF Dokumen Resmi (Maks. 10 MB)"
                        accept=".pdf,application/pdf"
                        :max-size-mb="10"
                        :required="!editingRow"
                        :error="formErrors.file_pdf"
                    />
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="formState.is_publik"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600"
                    />
                    <span>Tayangkan langsung di Pusat Unduhan Publik</span>
                </label>
            </form>

            <template #footer>
                <div class="flex items-center justify-end gap-2">
                    <BaseButton
                        type="button"
                        variant="secondary"
                        @click="closeModal"
                    >
                        Batal
                    </BaseButton>
                    <BaseButton
                        type="submit"
                        form="ppid-form"
                        variant="primary"
                        :loading="isSubmitting"
                        data-testid="submit-ppid-btn"
                    >
                        Simpan Dokumen
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
