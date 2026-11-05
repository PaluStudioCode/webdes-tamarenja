<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock,
    FileQuestion,
    RefreshCw,
    ServerCrash,
    ShieldAlert,
} from 'lucide-vue-next';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';

const props = defineProps({
    status: {
        type: Number,
        required: true,
    },
    message: {
        type: String,
        default: '',
    },
});

const page = usePage();

const isAuthenticated = computed(() => Boolean(page.props?.auth?.user));
const isInternalUrl = computed(() =>
    String(page.url || '').startsWith('/panel'),
);

const namaDesa = computed(() => {
    const raw = page.props?.profil_desa?.nama_desa;
    return raw ? `Pemerintah Desa ${raw}` : 'Sistem Informasi Pemerintah Desa';
});

const ERROR_CONFIGS = {
    403: {
        title: 'Akses Dibatasi Sesuai Peran',
        description:
            'Anda tidak memiliki kewenangan untuk mengakses halaman atau menjalankan tindakan ini sesuai pembagian peran perangkat desa.',
        icon: ShieldAlert,
        badgeClass: 'border-amber-200 bg-amber-50 text-amber-700',
        iconWrapClass: 'bg-amber-50 text-amber-700',
    },
    404: {
        title: 'Halaman atau Dokumen Tidak Ditemukan',
        description:
            'Alamat halaman atau berkas dokumen yang Anda tuju tidak tersedia, telah dipindahkan, atau tidak terdaftar di sistem desa.',
        icon: FileQuestion,
        badgeClass: 'border-slate-200 bg-slate-100 text-slate-700',
        iconWrapClass: 'bg-slate-100 text-slate-700',
    },
    419: {
        title: 'Sesi Telah Berakhir',
        description:
            'Sesi keamanan halaman telah kedaluwarsa karena tidak ada aktivitas. Halaman akan diarahkan otomatis atau klik tombol di bawah untuk melanjutkan.',
        icon: Clock,
        badgeClass: 'border-blue-200 bg-blue-50 text-blue-700',
        iconWrapClass: 'bg-blue-50 text-blue-700',
    },
    500: {
        title: 'Gangguan Layanan Internal',
        description:
            'Terjadi kendala teknis pada layanan internal desa saat memproses permintaan Anda. Silakan coba beberapa saat lagi.',
        icon: ServerCrash,
        badgeClass: 'border-red-200 bg-red-50 text-red-700',
        iconWrapClass: 'bg-red-50 text-red-700',
    },
};

const errorMeta = computed(
    () => ERROR_CONFIGS[props.status] || ERROR_CONFIGS[500],
);

const descriptionText = computed(
    () => props.message?.trim() || errorMeta.value.description,
);

const safeTarget = computed(() => {
    if (props.status === 419) {
        if (isInternalUrl.value || String(page.url || '').startsWith('/login')) {
            return {
                href: '/login',
                label: 'Masuk Kembali',
                isReload: true,
            };
        }

        return {
            href: page.url || '/',
            label: 'Muat Ulang Halaman',
            isReload: true,
        };
    }

    if (isAuthenticated.value && (props.status === 403 || isInternalUrl.value)) {
        return {
            href: '/panel/dashboard',
            label: 'Kembali ke Dashboard',
            isReload: false,
        };
    }

    return {
        href: '/',
        label: 'Kembali ke Beranda Desa',
        isReload: false,
    };
});

let sessionRedirectTimer = null;

function handleSessionRecovery() {
    router.visit(safeTarget.value.href);
}

onMounted(() => {
    if (props.status === 419) {
        sessionRedirectTimer = setTimeout(() => {
            handleSessionRecovery();
        }, 3500);
    }
});

onUnmounted(() => {
    if (sessionRedirectTimer) {
        clearTimeout(sessionRedirectTimer);
        sessionRedirectTimer = null;
    }
});
</script>

<template>
    <Head :title="`${status} - ${errorMeta.title}`" />
    <GlobalNotificationHost />

    <main
        data-testid="error-page"
        class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12 text-slate-900"
    >
        <div
            class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 text-center shadow-xs sm:p-8"
        >
            <p
                class="text-xs font-medium uppercase tracking-wider text-slate-500"
            >
                {{ namaDesa }}
            </p>

            <div class="mt-5 flex justify-center">
                <div
                    :class="[
                        'flex h-12 w-12 items-center justify-center rounded-lg',
                        errorMeta.iconWrapClass,
                    ]"
                >
                    <component
                        :is="errorMeta.icon"
                        class="h-6 w-6"
                        :stroke-width="1.75"
                    />
                </div>
            </div>

            <div class="mt-4">
                <span
                    data-testid="error-status-code"
                    :class="[
                        'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold tabular-nums',
                        errorMeta.badgeClass,
                    ]"
                >
                    Kode Status {{ status }}
                </span>
            </div>

            <h1
                data-testid="error-title"
                class="mt-3 text-xl font-semibold text-slate-900 sm:text-2xl"
            >
                {{ errorMeta.title }}
            </h1>

            <p
                data-testid="error-description"
                class="mt-2 text-sm leading-relaxed text-slate-600"
            >
                {{ descriptionText }}
            </p>

            <div class="mt-6">
                <button
                    v-if="safeTarget.isReload"
                    type="button"
                    data-testid="error-action-btn"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-700 px-5 text-sm font-medium text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-1"
                    @click="handleSessionRecovery"
                >
                    <RefreshCw class="h-4 w-4" :stroke-width="2" />
                    <span>{{ safeTarget.label }}</span>
                </button>

                <Link
                    v-else
                    :href="safeTarget.href"
                    data-testid="error-action-btn"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-blue-700 px-5 text-sm font-medium text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-1"
                >
                    <ArrowLeft class="h-4 w-4" :stroke-width="2" />
                    <span>{{ safeTarget.label }}</span>
                </Link>
            </div>
        </div>
    </main>
</template>
