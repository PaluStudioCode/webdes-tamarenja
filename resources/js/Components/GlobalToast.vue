<script setup>
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
    XCircle,
} from 'lucide-vue-next';
import { useNotification } from '@/Composables/useNotification';

const { toasts, dismiss } = useNotification();

const TYPE_STYLES = {
    success: {
        container: 'border-emerald-200 bg-white',
        iconWrap: 'bg-emerald-50 text-emerald-700',
        icon: CheckCircle2,
    },
    error: {
        container: 'border-red-200 bg-white',
        iconWrap: 'bg-red-50 text-red-700',
        icon: XCircle,
    },
    warning: {
        container: 'border-amber-200 bg-white',
        iconWrap: 'bg-amber-50 text-amber-700',
        icon: AlertTriangle,
    },
    info: {
        container: 'border-blue-200 bg-white',
        iconWrap: 'bg-blue-50 text-blue-700',
        icon: Info,
    },
};

function getStyle(type) {
    return TYPE_STYLES[type] || TYPE_STYLES.info;
}
</script>

<template>
    <div
        aria-live="polite"
        aria-atomic="false"
        data-testid="global-toast-container"
        class="pointer-events-none fixed right-4 top-4 z-[70] flex w-full max-w-sm flex-col gap-2.5 px-4 sm:px-0"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-y-2 opacity-0 sm:translate-x-2 sm:translate-y-0"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :role="toast.type === 'error' ? 'alert' : 'status'"
                :data-testid="`toast-item-${toast.type}`"
                :class="[
                    'pointer-events-auto flex items-start gap-3 rounded-lg border p-4 shadow-md',
                    getStyle(toast.type).container,
                ]"
            >
                <div
                    :class="[
                        'flex h-8 w-8 shrink-0 items-center justify-center rounded-md',
                        getStyle(toast.type).iconWrap,
                    ]"
                >
                    <component
                        :is="getStyle(toast.type).icon"
                        class="h-4 w-4"
                        :stroke-width="2"
                    />
                </div>

                <div class="min-w-0 flex-1 pt-0.5">
                    <p
                        v-if="toast.title"
                        class="text-sm font-semibold text-slate-900"
                    >
                        {{ toast.title }}
                    </p>
                    <p
                        :class="[
                            'text-sm leading-relaxed',
                            toast.title
                                ? 'mt-0.5 text-slate-600'
                                : 'font-medium text-slate-800',
                        ]"
                    >
                        {{ toast.message }}
                    </p>
                </div>

                <button
                    type="button"
                    aria-label="Tutup notifikasi"
                    data-testid="toast-dismiss-btn"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600 focus:outline-none focus:ring-2 focus:ring-blue-600"
                    @click="dismiss(toast.id)"
                >
                    <X class="h-4 w-4" :stroke-width="2" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
