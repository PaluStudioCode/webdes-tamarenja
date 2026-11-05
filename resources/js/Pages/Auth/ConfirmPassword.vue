<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({
    password: '',
});

function submit() {
    form.post('/confirm-password', {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <AuthLayout>
        <Head title="Konfirmasi Kata Sandi" />

        <p class="mb-4 text-sm text-slate-600">
            Silakan konfirmasi kata sandi Anda sebelum melanjutkan ke tindakan
            sensitif ini.
        </p>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="space-y-1.5">
                <label
                    for="password"
                    class="block text-sm font-medium text-slate-700"
                >
                    Kata Sandi <span class="text-red-600">*</span>
                </label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autofocus
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                />
                <p
                    v-if="form.errors.password"
                    class="text-xs font-medium text-red-600"
                >
                    {{ form.errors.password }}
                </p>
            </div>

            <BaseButton
                type="submit"
                variant="primary"
                class="w-full"
                :loading="form.processing"
            >
                Konfirmasi
            </BaseButton>
        </form>
    </AuthLayout>
</template>
