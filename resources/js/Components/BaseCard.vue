<script setup>
import { computed, inject, provide } from 'vue';

const CARD_DEPTH_KEY = Symbol.for('webdes.card.depth');

const props = defineProps({
    title: {
        type: String,
        default: '',
    },
    subtitle: {
        type: String,
        default: '',
    },
    padding: {
        type: String,
        default: 'md',
        validator: (val) => ['none', 'sm', 'md', 'lg'].includes(val),
    },
    flat: {
        type: Boolean,
        default: false,
    },
});

const parentDepth = inject(CARD_DEPTH_KEY, 0);
provide(CARD_DEPTH_KEY, parentDepth + 1);

// Anti-nested-card: jika tanpa sengaja berada di dalam BaseCard lain, hilangkan border & shadow ganda
const isNested = computed(() => parentDepth > 0);

const paddingClass = computed(() => {
    const map = {
        none: 'p-0',
        sm: 'p-4',
        md: 'p-6',
        lg: 'p-6 sm:p-8',
    };
    return map[props.padding] || map.md;
});
</script>

<template>
    <section
        data-testid="base-card"
        :data-nested="isNested ? 'true' : 'false'"
        :class="[
            'bg-white',
            isNested
                ? 'rounded-md'
                : [
                      'rounded-lg border border-slate-200',
                      flat ? '' : 'shadow-xs',
                  ],
        ]"
    >
        <header
            v-if="title || $slots.header || $slots.headerActions"
            class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-6 py-4"
        >
            <slot name="header">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-slate-900">
                        {{ title }}
                    </h2>
                    <p v-if="subtitle" class="mt-0.5 text-xs text-slate-500">
                        {{ subtitle }}
                    </p>
                </div>
            </slot>

            <div
                v-if="$slots.headerActions"
                class="flex shrink-0 items-center gap-2"
            >
                <slot name="headerActions" />
            </div>
        </header>

        <div :class="paddingClass">
            <slot />
        </div>

        <footer
            v-if="$slots.footer"
            class="border-t border-slate-200 bg-slate-50/60 px-6 py-3.5 rounded-b-lg"
        >
            <slot name="footer" />
        </footer>
    </section>
</template>
