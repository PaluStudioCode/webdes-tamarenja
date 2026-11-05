<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Power, Trash2 } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import BaseModal from '@/Components/BaseModal.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import DataTable from '@/Components/DataTable.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import { useConfirm } from '@/Composables/useConfirm';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDateTime } from '@/Utils/formatters';

const props = defineProps({
    users: {
        type: [Array, Object],
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', role: '' }),
    },
    activeOperatorCount: {
        type: Number,
        default: 1,
    },
});

const page = usePage();
const confirm = useConfirm();

const currentUserId = computed(() => Number(page.props?.auth?.user?.id || 0));

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
    { key: 'name', label: 'Nama & Email' },
    { key: 'role', label: 'Peran Akses' },
    { key: 'is_active', label: 'Status Akun' },
    { key: 'last_login_at', label: 'Login Terakhir' },
];

const searchQuery = ref(props.filters?.search || '');
const roleFilter = ref(props.filters?.role || '');

const filterDefinitions = computed(() => [
    {
        key: 'role',
        label: 'Peran',
        value: roleFilter.value,
        allLabel: 'Semua Peran',
        options: [
            { value: 'operator', label: 'Operator Desa' },
            { value: 'kepala_desa', label: 'Kepala Desa' },
        ],
    },
]);

function applyFilters() {
    router.get(
        '/panel/pengaturan/pengguna',
        {
            search: searchQuery.value || undefined,
            role: roleFilter.value || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function handleFilterChange({ key, value }) {
    if (key === 'role') {
        roleFilter.value = value;
        applyFilters();
    }
}

function handleResetFilter() {
    searchQuery.value = '';
    roleFilter.value = '';
    applyFilters();
}

// Modal Tambah / Edit Pengguna
const isModalOpen = ref(false);
const editingUser = ref(null);

const userForm = useForm({
    name: '',
    email: '',
    role: 'operator',
    is_active: true,
    password: '',
    password_confirmation: '',
});

function openCreateModal() {
    editingUser.value = null;
    userForm.reset();
    userForm.clearErrors();
    userForm.name = '';
    userForm.email = '';
    userForm.role = 'operator';
    userForm.is_active = true;
    userForm.password = '';
    userForm.password_confirmation = '';
    isModalOpen.value = true;
}

function openEditModal(row) {
    editingUser.value = row;
    userForm.clearErrors();
    userForm.name = row.name;
    userForm.email = row.email;
    userForm.role = row.role;
    userForm.is_active = Boolean(row.is_active);
    userForm.password = '';
    userForm.password_confirmation = '';
    isModalOpen.value = true;
}

function closeModal() {
    isModalOpen.value = false;
    editingUser.value = null;
}

function submitUserForm() {
    if (editingUser.value) {
        userForm.put(`/panel/pengaturan/pengguna/${editingUser.value.id}`, {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        userForm.post('/panel/pengaturan/pengguna', {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
}

function isSelfRow(row) {
    return Number(row.id) === currentUserId.value;
}

function isLastActiveOperatorRow(row) {
    return (
        row.role === 'operator' &&
        Boolean(row.is_active) &&
        props.activeOperatorCount <= 1
    );
}

function isLockoutProtected(row) {
    return isSelfRow(row) || isLastActiveOperatorRow(row);
}

async function handleToggleStatus(row) {
    if (isLockoutProtected(row)) {
        return;
    }

    const actionWord = row.is_active ? 'Nonaktifkan' : 'Aktifkan';
    const ok = await confirm.ask({
        title: `${actionWord} Akun Pengguna?`,
        message: row.is_active
            ? `Akun ${row.name} akan langsung dikeluarkan dari sesi aktif dan tidak dapat masuk ke panel internal.`
            : `Akun ${row.name} akan diizinkan kembali masuk ke panel internal desa.`,
        variant: row.is_active ? 'warning' : 'primary',
        confirmText: `${actionWord} Akun`,
    });

    if (!ok) {
        return;
    }

    router.patch(
        `/panel/pengaturan/pengguna/${row.id}/toggle-status`,
        {},
        { preserveScroll: true },
    );
}

async function handleDeleteUser(row) {
    if (isLockoutProtected(row)) {
        return;
    }

    const ok = await confirm.ask({
        title: 'Hapus Akun Pengguna?',
        message: `Akun ${row.name} (${row.email}) akan dihapus permanen apabila belum memiliki riwayat pencatatan dokumen.`,
        variant: 'danger',
        confirmText: 'Hapus Akun',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/pengaturan/pengguna/${row.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Manajemen Pengguna Internal" />

        <PageHeader
            title="Pengaturan Sistem"
            description="Kelola akun perangkat desa internal (Operator Desa & Kepala Desa) beserta pemulihan kata sandi."
        >
            <template #actions>
                <BaseButton
                    variant="primary"
                    data-testid="add-user-btn"
                    @click="openCreateModal"
                >
                    <Plus class="h-4 w-4" :stroke-width="2" />
                    <span>Tambah Pengguna</span>
                </BaseButton>
            </template>
        </PageHeader>

        <ContextualTabs :tabs="pengaturanTabs" />

        <BaseCallout
            variant="info"
            title="Proteksi Keamanan Akun & Anti Self-Lockout"
        >
            Sistem wajib menyisakan minimal 1 akun
            <strong>Operator Desa</strong> berstatus aktif dan mengunci tombol
            nonaktif/hapus pada akun yang sedang Anda gunakan sendiri. Jika
            seluruh operator lupa kata sandi, gunakan perintah server
            <code>php artisan desa:reset-password {email}</code>.
        </BaseCallout>

        <UnifiedFilterBar
            v-model="searchQuery"
            search-placeholder="Cari nama atau email perangkat desa..."
            :filters="filterDefinitions"
            @search="applyFilters"
            @update:filter="handleFilterChange"
            @reset="handleResetFilter"
        />

        <DataTable
            :columns="columns"
            :rows="users"
            empty-text="Belum ada akun pengguna yang sesuai kriteria pencarian."
        >
            <template #cell-name="{ row }">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-slate-900">
                            {{ row.name }}
                        </span>
                        <span
                            v-if="isSelfRow(row)"
                            data-testid="self-account-badge"
                            class="inline-flex items-center rounded border border-blue-200 bg-blue-50 px-1.5 py-0.5 text-[11px] font-medium text-blue-700"
                        >
                            Akun Anda
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ row.email }}
                    </p>
                </div>
            </template>

            <template #cell-role="{ row }">
                <span class="text-sm font-medium text-slate-700">
                    {{
                        row.role === 'kepala_desa'
                            ? 'Kepala Desa'
                            : 'Operator Desa'
                    }}
                </span>
            </template>

            <template #cell-is_active="{ row }">
                <StatusBadge :status="row.is_active ? 'aktif' : 'nonaktif'" />
            </template>

            <template #cell-last_login_at="{ row }">
                <span class="text-xs tabular-nums text-slate-600">
                    {{
                        row.last_login_at
                            ? formatDateTime(row.last_login_at)
                            : 'Belum pernah login'
                    }}
                </span>
            </template>

            <template #actions="{ row }">
                <div class="inline-flex items-center justify-end gap-1.5">
                    <button
                        type="button"
                        :data-testid="`edit-user-btn-${row.id}`"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                        @click="openEditModal(row)"
                    >
                        <Pencil class="h-3.5 w-3.5" :stroke-width="1.75" />
                        <span>Edit / Sandi</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isLockoutProtected(row)"
                        :data-testid="`toggle-status-btn-${row.id}`"
                        :title="
                            isSelfRow(row)
                                ? 'Akun yang sedang digunakan tidak dapat dinonaktifkan'
                                : undefined
                        "
                        :class="[
                            'inline-flex h-8 items-center gap-1 rounded-md border px-2.5 text-xs font-medium transition-colors',
                            isLockoutProtected(row)
                                ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-400'
                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                        ]"
                        @click="handleToggleStatus(row)"
                    >
                        <Power class="h-3.5 w-3.5" :stroke-width="1.75" />
                        <span>{{
                            row.is_active ? 'Nonaktifkan' : 'Aktifkan'
                        }}</span>
                    </button>

                    <button
                        type="button"
                        :disabled="isLockoutProtected(row)"
                        :data-testid="`delete-user-btn-${row.id}`"
                        :aria-label="`Hapus akun ${row.name}`"
                        :title="
                            isSelfRow(row)
                                ? 'Akun yang sedang digunakan tidak dapat dihapus'
                                : undefined
                        "
                        :class="[
                            'inline-flex h-8 w-8 items-center justify-center rounded-md border transition-colors',
                            isLockoutProtected(row)
                                ? 'cursor-not-allowed border-slate-200 bg-slate-50 text-slate-300'
                                : 'border-red-200 bg-white text-red-600 hover:bg-red-50',
                        ]"
                        @click="handleDeleteUser(row)"
                    >
                        <Trash2 class="h-3.5 w-3.5" :stroke-width="1.75" />
                    </button>
                </div>
            </template>
        </DataTable>

        <!-- Modal Tambah / Edit Pengguna & Reset Kata Sandi -->
        <BaseModal
            :show="isModalOpen"
            :title="
                editingUser
                    ? `Edit Akun: ${editingUser.name}`
                    : 'Tambah Akun Pengguna Internal'
            "
            subtitle="Tentukan peran akses dan kata sandi bagi perangkat desa"
            max-width="lg"
            @close="closeModal"
        >
            <form
                id="pengguna-modal-form"
                data-testid="pengguna-modal-form"
                class="space-y-4"
                @submit.prevent="submitUserForm"
            >
                <div class="space-y-1.5">
                    <label
                        for="user-name"
                        class="block text-sm font-medium text-slate-700"
                    >
                        <span>Nama Lengkap</span>
                        <span class="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="user-name"
                        v-model="userForm.name"
                        type="text"
                        required
                        data-testid="modal-user-name"
                        placeholder="Contoh: Siti Aminah, S.Sos"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p
                        v-if="userForm.errors.name"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ userForm.errors.name }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="user-email"
                        class="block text-sm font-medium text-slate-700"
                    >
                        <span>Email Kedinasan</span>
                        <span class="ml-0.5 text-red-600">*</span>
                    </label>
                    <input
                        id="user-email"
                        v-model="userForm.email"
                        type="email"
                        required
                        data-testid="modal-user-email"
                        placeholder="Contoh: sekdes@tamarenja.desa.id"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    />
                    <p
                        v-if="userForm.errors.email"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ userForm.errors.email }}
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label
                            for="user-role"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Peran Akses</span>
                            <span class="ml-0.5 text-red-600">*</span>
                        </label>
                        <select
                            id="user-role"
                            v-model="userForm.role"
                            data-testid="modal-user-role"
                            :disabled="
                                editingUser && isLockoutProtected(editingUser)
                            "
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 disabled:bg-slate-100"
                        >
                            <option value="operator">
                                Operator Desa (Operasional Penuh)
                            </option>
                            <option value="kepala_desa">
                                Kepala Desa / BPD (Read-Only &amp; Cetak PDF)
                            </option>
                        </select>
                        <p
                            v-if="userForm.errors.role"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ userForm.errors.role }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="user-status"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Status Akun</span>
                        </label>
                        <select
                            id="user-status"
                            v-model="userForm.is_active"
                            data-testid="modal-user-active"
                            :disabled="
                                editingUser && isLockoutProtected(editingUser)
                            "
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20 disabled:bg-slate-100"
                        >
                            <option :value="true">Aktif</option>
                            <option :value="false">Nonaktif</option>
                        </select>
                        <p
                            v-if="userForm.errors.is_active"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ userForm.errors.is_active }}
                        </p>
                    </div>
                </div>

                <div class="border-t border-slate-200 pt-3">
                    <p class="mb-3 text-xs font-medium text-slate-500">
                        {{
                            editingUser
                                ? 'Kosongkan kolom kata sandi di bawah apabila tidak ingin mengubah kata sandi akun ini.'
                                : 'Tetapkan kata sandi awal minimal 8 karakter.'
                        }}
                    </p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label
                                for="user-password"
                                class="block text-sm font-medium text-slate-700"
                            >
                                <span>{{
                                    editingUser
                                        ? 'Kata Sandi Baru'
                                        : 'Kata Sandi Awal'
                                }}</span>
                                <span
                                    v-if="!editingUser"
                                    class="ml-0.5 text-red-600"
                                    >*</span
                                >
                            </label>
                            <input
                                id="user-password"
                                v-model="userForm.password"
                                type="password"
                                autocomplete="new-password"
                                :required="!editingUser"
                                data-testid="modal-user-password"
                                placeholder="Minimal 8 karakter"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <p
                                v-if="userForm.errors.password"
                                class="text-xs font-medium text-red-600"
                            >
                                {{ userForm.errors.password }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                for="user-password-confirm"
                                class="block text-sm font-medium text-slate-700"
                            >
                                <span>Konfirmasi Kata Sandi</span>
                                <span
                                    v-if="!editingUser"
                                    class="ml-0.5 text-red-600"
                                    >*</span
                                >
                            </label>
                            <input
                                id="user-password-confirm"
                                v-model="userForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                :required="!editingUser"
                                data-testid="modal-user-password-confirmation"
                                placeholder="Ulangi kata sandi"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                        </div>
                    </div>
                </div>
            </form>

            <template #footer>
                <BaseButton variant="secondary" @click="closeModal">
                    Batal
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="pengguna-modal-form"
                    variant="primary"
                    data-testid="modal-user-submit"
                    :loading="userForm.processing"
                    @click="submitUserForm"
                >
                    {{ editingUser ? 'Simpan Perubahan' : 'Tambah Akun' }}
                </BaseButton>
            </template>
        </BaseModal>
    </AppLayout>
</template>
