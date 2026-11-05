<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Landmark } from 'lucide-vue-next';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';

const page = usePage();

const profilDesa = computed(() => page.props?.profil_desa || {});
const namaDesa = computed(() => profilDesa.value.nama_desa || 'Tamarenja');

const logoSrc = computed(() => {
    if (profilDesa.value.logo_url) {
        return profilDesa.value.logo_url;
    }
    if (profilDesa.value.logo_desa_path) {
        return `/storage/${profilDesa.value.logo_desa_path}`;
    }
    return null;
});
</script>

<template>
    <div
        data-testid="auth-layout"
        class="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 py-12 text-slate-900"
    >
        <GlobalNotificationHost />

        <div class="w-full max-w-md">
            <!-- Header Identitas Sistem (Tanpa Teks Promosi / Ornamen) -->
            <div class="mb-6 flex flex-col items-center text-center">
                <img
                    v-if="logoSrc"
                    :src="logoSrc"
                    :alt="`Logo Desa ${namaDesa}`"
                    class="h-14 w-14 rounded-lg object-contain"
                />
                <div
                    v-else
                    class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-700 text-white"
                >
                    <Landmark class="h-6 w-6" :stroke-width="1.75" />
                </div>

                <h1 class="mt-3 text-lg font-semibold text-slate-900 sm:text-xl">
                    Pemerintah Desa {{ namaDesa }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Panel Operasional &amp; Administrasi Desa
                </p>
            </div>

            <!-- Kartu Form Login -->
            <div
                class="rounded-lg border border-slate-200 bg-white p-6 shadow-xs sm:p-8"
            >
                <slot />
            </div>

            <!-- Tautan Halus Kembali ke Portal Publik -->
            <div class="mt-6 text-center">
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition-colors hover:text-slate-800"
                >
                    <ArrowLeft class="h-3.5 w-3.5" :stroke-width="2" />
                    <span>Kembali ke Portal Publik Desa</span>
                </Link>
            </div>
        </div>
    </div>
</template>
