<script setup>
import { computed, onMounted, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    maxWidth: {
        type: String,
        default: 'lg',
        validator: (val) =>
            ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl'].includes(val),
    },
    closeable: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close']);

const MAX_WIDTH_MAP = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
};

const widthClass = computed(
    () => MAX_WIDTH_MAP[props.maxWidth] || MAX_WIDTH_MAP.lg,
);

function close() {
    if (props.closeable) {
        emit('close');
    }
}

function handleKeydown(event) {
    if (event.key === 'Escape' && props.show) {
        close();
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
            v-if="show"
            data-testid="base-modal"
            class="fixed inset-0 z-[75] flex items-center justify-center overflow-y-auto p-4"
        >
            <div
                data-testid="base-modal-backdrop"
                class="fixed inset-0 bg-slate-900/50"
                @click="close"
            />

            <div
                role="dialog"
                aria-modal="true"
                :class="[
                    'relative z-10 w-full rounded-lg border border-slate-200 bg-white shadow-lg',
                    widthClass,
                ]"
            >
                <header
                    v-if="title || closeable"
                    class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4"
                >
                    <div class="min-w-0">
                        <h2
                            v-if="title"
                            class="text-base font-semibold text-slate-900"
                        >
                            {{ title }}
                        </h2>
                    </div>

                    <button
                        v-if="closeable"
                        type="button"
                        aria-label="Tutup dialog"
                        data-testid="base-modal-close"
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-700"
                        @click="close"
                    >
                        <X class="h-4 w-4" :stroke-width="2" />
                    </button>
                </header>

                <div class="max-h-[75vh] overflow-y-auto p-6">
                    <slot />
                </div>

                <footer
                    v-if="$slots.footer"
                    class="flex items-center justify-end gap-3 rounded-b-lg border-t border-slate-200 bg-slate-50/70 px-6 py-4"
                >
                    <slot name="footer" />
                </footer>
            </div>
        </div>
    </Transition>
</template>
