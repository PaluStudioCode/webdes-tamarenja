<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UserSquare2 } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import DataTable from '@/Components/DataTable.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import { useConfirm } from '@/Composables/useConfirm';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    perangkatList: {
        type: [Array, Object],
        default: () => [],
    },
    kelompokOptions: {
        type: Array,
        default: () => [
            { value: 'pimpinan', label: 'Pimpinan Pemerintah Desa' },
            { value: 'sekretariat', label: 'Sekretariat Desa (Sekdes & Kaur)' },
            { value: 'pelaksana_teknis', label: 'Pelaksana Teknis (Kasi)' },
            {
                value: 'kewilayahan_dusun',
                label: 'Pelaksana Kewilayahan (Kepala Dusun)',
            },
            { value: 'bpd', label: 'Badan Permusyawaratan Desa (BPD)' },
        ],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', kelompok_sotk: '' }),
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
    { key: 'urutan', label: 'Urutan' },
    { key: 'nama_lengkap', label: 'Pejabat / Perangkat' },
    { key: 'jabatan', label: 'Jabatan Resmi' },
    { key: 'kelompok_label', label: 'Kelompok SOTK' },
    { key: 'is_aktif', label: 'Status Tayang' },
];

const searchQuery = ref(props.filters?.search || '');
const kelompokFilter = ref(props.filters?.kelompok_sotk || '');

const filterDefinitions = computed(() => [
    {
        key: 'kelompok_sotk',
        label: 'Kelompok SOTK',
        value: kelompokFilter.value,
        allLabel: 'Semua Kelompok',
        options: props.kelompokOptions,
    },
]);

function applyFilters() {
    router.get(
        '/panel/pengaturan/sotk',
        {
            search: searchQuery.value || undefined,
            kelompok_sotk: kelompokFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function handleFilterChange({ key, value }) {
    if (key === 'kelompok_sotk') {
        kelompokFilter.value = value;
        applyFilters();
    }
}

function handleResetFilter() {
    searchQuery.value = '';
    kelompokFilter.value = '';
    applyFilters();
}

const isModalOpen = ref(false);
const editingRow = ref(null);
const isSubmitting = ref(false);
const formErrors = ref({});

const formState = ref({
    nama_lengkap: '',
    jabatan: '',
    kelompok_sotk: 'sekretariat',
    nip_niap: '',
    urutan: 1,
    is_aktif: true,
    foto: null,
});

function openCreateModal() {
    editingRow.value = null;
    formErrors.value = {};
    formState.value = {
        nama_lengkap: '',
        jabatan: '',
        kelompok_sotk: 'sekretariat',
        nip_niap: '',
        urutan: 1,
        is_aktif: true,
        foto: null,
    };
    isModalOpen.value = true;
}

function openEditModal(row) {
    editingRow.value = row;
    formErrors.value = {};
    formState.value = {
        nama_lengkap: row.nama_lengkap || '',
        jabatan: row.jabatan || '',
        kelompok_sotk: row.kelompok_sotk || 'sekretariat',
        nip_niap: row.nip_niap || '',
        urutan: Number(row.urutan ?? 1),
        is_aktif: Boolean(row.is_aktif),
        foto: null,
    };
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editingRow.value = null;
}

function handleFotoChange(event) {
    formState.value.foto = event.target?.files?.[0] || null;
}

function submitSotk() {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    formErrors.value = {};

    const payload = {
        nama_lengkap: formState.value.nama_lengkap,
        jabatan: formState.value.jabatan,
        kelompok_sotk: formState.value.kelompok_sotk,
        nip_niap: formState.value.nip_niap || '',
        urutan: Number(formState.value.urutan || 0),
        is_aktif: formState.value.is_aktif ? 1 : 0,
    };

    if (formState.value.foto) {
        payload.foto = formState.value.foto;
    }

    const url = editingRow.value
        ? `/panel/pengaturan/sotk/${editingRow.value.id}`
        : '/panel/pengaturan/sotk';

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

async function handleDelete(row) {
    const ok = await confirm.ask({
        title: 'Hapus Perangkat SOTK?',
        message: `Data perangkat "${row.nama_lengkap}" (${row.jabatan}) akan dihapus dari bagan Struktur Organisasi Desa.`,
        confirmText: 'Ya, Hapus',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/pengaturan/sotk/${row.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Struktur Organisasi (SOTK & BPD)" />

        <PageHeader
            title="Pengaturan Sistem"
            description="Kelola daftar pejabat perangkat desa, kepala dusun, dan anggota BPD untuk ditampilkan pada bagan SOTK halaman publik."
        >
            <template #actions>
                <BaseButton
                    variant="primary"
                    data-testid="add-sotk-btn"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" :stroke-width="2" />
                    <span>Tambah Perangkat SOTK</span>
                </BaseButton>
            </template>
        </PageHeader>

        <ContextualTabs :tabs="pengaturanTabs" />

        <UnifiedFilterBar
            v-model="searchQuery"
            search-placeholder="Cari nama perangkat, jabatan, atau NIP/NIAP..."
            :filters="filterDefinitions"
            @search="applyFilters"
            @update:filter="handleFilterChange"
            @reset="handleResetFilter"
        />

        <DataTable
            :columns="columns"
            :rows="perangkatList"
            empty-text="Belum ada data perangkat SOTK atau anggota BPD."
        >
            <template #cell-urutan="{ row }">
                <span class="font-semibold tabular-nums text-slate-700">
                    #{{ row.urutan }}
                </span>
            </template>

            <template #cell-nama_lengkap="{ row }">
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-100"
                    >
                        <img
                            v-if="row.foto_url"
                            :src="row.foto_url"
                            :alt="row.nama_lengkap"
                            class="h-full w-full object-cover"
                        />
                        <UserSquare2 v-else class="h-4 w-4 text-slate-400" />
                    </div>
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ row.nama_lengkap }}
                        </p>
                        <p
                            v-if="row.nip_niap"
                            class="text-xs tabular-nums text-slate-500"
                        >
                            NIP/NIAP: {{ row.nip_niap }}
                        </p>
                    </div>
                </div>
            </template>

            <template #cell-is_aktif="{ row }">
                <StatusBadge :status="row.is_aktif ? 'aktif' : 'nonaktif'" />
            </template>

            <template #actions="{ row }">
                <div class="inline-flex items-center justify-end gap-1.5">
                    <button
                        type="button"
                        :data-testid="`edit-sotk-btn-${row.id}`"
                        class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        @click="openEditModal(row)"
                    >
                        <Pencil class="h-3.5 w-3.5" />
                        <span>Edit</span>
                    </button>
                    <button
                        type="button"
                        :data-testid="`delete-sotk-btn-${row.id}`"
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
                    ? 'Edit Perangkat SOTK / BPD'
                    : 'Tambah Perangkat SOTK / BPD'
            "
            max-width="lg"
            @close="closeModal"
        >
            <form
                id="sotk-form"
                class="space-y-4"
                @submit.prevent="submitSotk"
            >
                <div>
                    <label
                        for="sotk-nama"
                        class="mb-1.5 block text-sm font-medium text-slate-700"
                    >
                        Nama Lengkap <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="sotk-nama"
                        v-model="formState.nama_lengkap"
                        type="text"
                        data-testid="sotk-input-nama"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                    />
                    <p
                        v-if="formErrors.nama_lengkap"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ formErrors.nama_lengkap }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            for="sotk-jabatan"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Jabatan Resmi <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="sotk-jabatan"
                            v-model="formState.jabatan"
                            type="text"
                            placeholder="Contoh: Kaur Keuangan"
                            data-testid="sotk-input-jabatan"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.jabatan"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.jabatan }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="sotk-kelompok"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Kelompok SOTK <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="sotk-kelompok"
                            v-model="formState.kelompok_sotk"
                            data-testid="sotk-select-kelompok"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        >
                            <option
                                v-for="opt in kelompokOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label
                            for="sotk-nip"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            NIP / NIAP
                        </label>
                        <input
                            id="sotk-nip"
                            v-model="formState.nip_niap"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label
                            for="sotk-urutan"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Nomor Urutan Tampil
                            <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="sotk-urutan"
                            v-model.number="formState.urutan"
                            type="number"
                            min="0"
                            max="999"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>
                </div>

                <div>
                    <label
                        for="sotk-foto"
                        class="mb-1.5 block text-sm font-medium text-slate-700"
                    >
                        Foto Perangkat (Maks. 3 MB JPG/PNG/WEBP)
                    </label>
                    <input
                        id="sotk-foto"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp"
                        class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-md file:border file:border-slate-300 file:bg-slate-50 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700"
                        @change="handleFotoChange"
                    />
                    <p
                        v-if="formErrors.foto"
                        class="mt-1 text-xs text-red-600"
                    >
                        {{ formErrors.foto }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input
                        v-model="formState.is_aktif"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-300 text-blue-600"
                    />
                    <span>Tampilkan pada halaman Profil & SOTK Publik</span>
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
                        form="sotk-form"
                        variant="primary"
                        :loading="isSubmitting"
                        data-testid="submit-sotk-btn"
                    >
                        Simpan Data
                    </BaseButton>
                </div>
            </template>
        </BaseModal>
    </AppLayout>
</template>
