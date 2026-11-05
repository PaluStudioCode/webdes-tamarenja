<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { AlertTriangle, Info } from 'lucide-vue-next';
import { useConfirm } from '@/Composables/useConfirm';

const { state, accept, cancel } = useConfirm();

const VARIANT_CONFIG = {
    danger: {
        icon: AlertTriangle,
        iconWrap: 'bg-red-50 text-red-700',
        confirmBtn:
            'bg-red-600 text-white hover:bg-red-700 focus:ring-red-600',
    },
    destructive: {
        icon: AlertTriangle,
        iconWrap: 'bg-red-50 text-red-700',
        confirmBtn:
            'bg-red-600 text-white hover:bg-red-700 focus:ring-red-600',
    },
    warning: {
        icon: AlertTriangle,
        iconWrap: 'bg-amber-50 text-amber-700',
        confirmBtn:
            'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-600',
    },
    primary: {
        icon: Info,
        iconWrap: 'bg-blue-50 text-blue-700',
        confirmBtn:
            'bg-blue-700 text-white hover:bg-blue-800 focus:ring-blue-700',
    },
};

const currentConfig = computed(
    () => VARIANT_CONFIG[state.variant] || VARIANT_CONFIG.danger,
);

function handleKeydown(event) {
    if (event.key === 'Escape' && state.isOpen) {
        cancel();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-100 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="state.isOpen"
            data-testid="global-confirm-dialog"
            class="fixed inset-0 z-[80] flex items-center justify-center p-4"
        >
            <div
                data-testid="confirm-backdrop"
                class="fixed inset-0 bg-slate-900/50"
                @click="cancel"
            />

            <div
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="global-confirm-title"
                aria-describedby="global-confirm-message"
                class="relative z-10 w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-lg"
            >
                <div class="flex items-start gap-4">
                    <div
                        :class="[
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                            currentConfig.iconWrap,
                        ]"
                    >
                        <component
                            :is="currentConfig.icon"
                            class="h-5 w-5"
                            :stroke-width="2"
                        />
                    </div>

                    <div class="min-w-0 flex-1">
                        <h2
                            id="global-confirm-title"
                            class="text-base font-semibold text-slate-900"
                        >
                            {{ state.title }}
                        </h2>
                        <p
                            id="global-confirm-message"
                            class="mt-1.5 text-sm leading-relaxed text-slate-600"
                        >
                            {{ state.message }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button
                        type="button"
                        data-testid="confirm-cancel-btn"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1"
                        @click="cancel"
                    >
                        {{ state.cancelText }}
                    </button>

                    <button
                        type="button"
                        data-testid="confirm-accept-btn"
                        :class="[
                            'inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1',
                            currentConfig.confirmBtn,
                        ]"
                        @click="accept"
                    >
                        {{ state.confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
