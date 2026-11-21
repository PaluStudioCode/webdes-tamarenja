<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const showPassword = ref(false);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AuthLayout>
        <Head title="Masuk Panel Perangkat Desa" />

        <div class="mb-6 space-y-1.5">
            <h2 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Masuk ke Panel Desa
            </h2>
            <p class="text-sm text-slate-500">
                Gunakan akun resmi perangkat desa untuk melanjutkan.
            </p>
        </div>

        <form
            data-testid="login-form"
            class="space-y-5"
            novalidate
            @submit.prevent="submit"
        >
            <!-- Email Kedinasan -->
            <div class="space-y-1.5">
                <label
                    for="email"
                    class="block text-sm font-medium text-slate-700"
                >
                    <span>Email Kedinasan</span>
                    <span class="ml-0.5 text-red-600">*</span>
                </label>

                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    autofocus
                    required
                    data-testid="login-email-input"
                    placeholder="Contoh: operator@desa.id"
                    :aria-invalid="Boolean(form.errors.email) || undefined"
                    :class="[
                        'h-10 w-full rounded-md border bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:ring-2',
                        form.errors.email
                            ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                            : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                    ]"
                />

                <p
                    v-if="form.errors.email"
                    data-testid="login-email-error"
                    class="text-xs font-medium text-red-600"
                >
                    {{ form.errors.email }}
                </p>
            </div>

            <!-- Kata Sandi dengan Toggle Lihat/Sembunyikan -->
            <div class="space-y-1.5">
                <label
                    for="password"
                    class="block text-sm font-medium text-slate-700"
                >
                    <span>Kata Sandi</span>
                    <span class="ml-0.5 text-red-600">*</span>
                </label>

                <div class="relative">
                    <input
                        id="password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        required
                        data-testid="login-password-input"
                        placeholder="••••••••"
                        :aria-invalid="Boolean(form.errors.password) || undefined"
                        :class="[
                            'h-10 w-full rounded-md border bg-white pl-3 pr-10 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:ring-2',
                            form.errors.password
                                ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                                : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                        ]"
                    />

                    <button
                        type="button"
                        data-testid="toggle-password-visibility"
                        :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Lihat kata sandi'"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-slate-400 transition-colors hover:text-slate-600 focus:outline-none"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>

                <p
                    v-if="form.errors.password"
                    data-testid="login-password-error"
                    class="text-xs font-medium text-red-600"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <!-- Ingat Sesi -->
            <div class="flex items-center">
                <input
                    id="remember"
                    v-model="form.remember"
                    type="checkbox"
                    name="remember"
                    class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-700"
                />
                <label
                    for="remember"
                    class="ml-2 select-none text-sm text-slate-600"
                >
                    Ingat sesi di perangkat ini
                </label>
            </div>

            <!-- Tombol Masuk Tunggal -->
            <div class="pt-1">
                <BaseButton
                    type="submit"
                    variant="primary"
                    size="lg"
                    data-testid="login-submit-btn"
                    class="w-full"
                    :loading="form.processing"
                >
                    Masuk ke Panel Desa
                </BaseButton>
            </div>
        </form>
    </AuthLayout>
</template>
