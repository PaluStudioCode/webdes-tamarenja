<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const currentUser = computed(
    () => props.user || page.props?.auth?.user || {},
);

const roleLabel = computed(() =>
    currentUser.value.role === 'kepala_desa' ? 'Kepala Desa' : 'Operator Desa',
);

const profileForm = useForm({
    name: currentUser.value.name || '',
    email: currentUser.value.email || '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitProfile() {
    profileForm.patch('/panel/akun', {
        preserveScroll: true,
    });
}

function submitPassword() {
    passwordForm.put('/panel/akun/password', {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => {
            if (passwordForm.errors.password) {
                passwordForm.reset('password', 'password_confirmation');
            }
            if (passwordForm.errors.current_password) {
                passwordForm.reset('current_password');
            }
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Pengaturan Akun Pribadi" />

        <PageHeader
            title="Akun & Kata Sandi"
            description="Perbarui identitas nama lengkap serta kata sandi akun perangkat desa yang sedang Anda gunakan."
        />

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Kartu 1: Informasi Profil -->
            <BaseCard
                title="Informasi Akun"
                subtitle="Identitas pengguna pada buku agenda dan dokumen internal"
            >
                <form
                    data-testid="akun-profile-form"
                    class="space-y-4"
                    @submit.prevent="submitProfile"
                >
                    <div class="space-y-1.5">
                        <label
                            for="akun-name"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Nama Lengkap</span>
                            <span class="ml-0.5 text-red-600">*</span>
                        </label>
                        <input
                            id="akun-name"
                            v-model="profileForm.name"
                            type="text"
                            required
                            data-testid="akun-name-input"
                            placeholder="Contoh: Muh. Ridwan, S.AP"
                            :class="[
                                'h-10 w-full rounded-md border bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2',
                                profileForm.errors.name
                                    ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                                    : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                            ]"
                        />
                        <p
                            v-if="profileForm.errors.name"
                            data-testid="akun-name-error"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ profileForm.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="akun-email"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Email Kedinasan</span>
                            <span class="ml-0.5 text-red-600">*</span>
                        </label>
                        <input
                            id="akun-email"
                            v-model="profileForm.email"
                            type="email"
                            required
                            data-testid="akun-email-input"
                            placeholder="Contoh: operator@desa.id"
                            :class="[
                                'h-10 w-full rounded-md border bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2',
                                profileForm.errors.email
                                    ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                                    : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                            ]"
                        />
                        <p
                            v-if="profileForm.errors.email"
                            data-testid="akun-email-error"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ profileForm.errors.email }}
                        </p>
                    </div>

                    <div class="space-y-1">
                        <span class="block text-xs font-medium text-slate-500">
                            Peran Akses Sistem
                        </span>
                        <p class="text-sm font-semibold text-slate-800">
                            {{ roleLabel }}
                        </p>
                    </div>

                    <div class="pt-2">
                        <BaseButton
                            type="submit"
                            variant="primary"
                            data-testid="akun-profile-submit"
                            :loading="profileForm.processing"
                        >
                            Simpan Perubahan Profil
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <!-- Kartu 2: Ubah Kata Sandi -->
            <BaseCard
                title="Ubah Kata Sandi"
                subtitle="Gunakan kombinasi minimal 8 karakter untuk keamanan akses panel"
            >
                <form
                    data-testid="akun-password-form"
                    class="space-y-4"
                    @submit.prevent="submitPassword"
                >
                    <div class="space-y-1.5">
                        <label
                            for="current_password"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Kata Sandi Saat Ini</span>
                            <span class="ml-0.5 text-red-600">*</span>
                        </label>
                        <input
                            id="current_password"
                            v-model="passwordForm.current_password"
                            type="password"
                            autocomplete="current-password"
                            required
                            data-testid="akun-current-password"
                            placeholder="••••••••"
                            :class="[
                                'h-10 w-full rounded-md border bg-white px-3 text-sm text-slate-900 focus:outline-none focus:ring-2',
                                passwordForm.errors.current_password
                                    ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                                    : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                            ]"
                        />
                        <p
                            v-if="passwordForm.errors.current_password"
                            data-testid="akun-current-password-error"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ passwordForm.errors.current_password }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="new_password"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Kata Sandi Baru</span>
                            <span class="ml-0.5 text-red-600">*</span>
                        </label>
                        <input
                            id="new_password"
                            v-model="passwordForm.password"
                            type="password"
                            autocomplete="new-password"
                            required
                            data-testid="akun-new-password"
                            placeholder="Minimal 8 karakter"
                            :class="[
                                'h-10 w-full rounded-md border bg-white px-3 text-sm text-slate-900 focus:outline-none focus:ring-2',
                                passwordForm.errors.password
                                    ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                                    : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                            ]"
                        />
                        <p
                            v-if="passwordForm.errors.password"
                            data-testid="akun-new-password-error"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ passwordForm.errors.password }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="password_confirmation"
                            class="block text-sm font-medium text-slate-700"
                        >
                            <span>Ulangi Kata Sandi Baru</span>
                            <span class="ml-0.5 text-red-600">*</span>
                        </label>
                        <input
                            id="password_confirmation"
                            v-model="passwordForm.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            data-testid="akun-confirm-password"
                            placeholder="Ketik ulang kata sandi baru"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                    </div>

                    <div class="pt-2">
                        <BaseButton
                            type="submit"
                            variant="secondary"
                            data-testid="akun-password-submit"
                            :loading="passwordForm.processing"
                        >
                            Perbarui Kata Sandi
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>
        </div>
    </AppLayout>
</template>
