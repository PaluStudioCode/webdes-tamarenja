<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    subtext: {
        type: String,
        default: '',
    },
    tone: {
        type: String,
        default: 'neutral',
        validator: (val) =>
            ['neutral', 'emerald', 'amber', 'red', 'blue'].includes(val),
    },
    icon: {
        type: [Object, Function],
        default: null,
    },
});

const SUBTEXT_TONE_CLASSES = {
    neutral: 'text-slate-600',
    emerald: 'text-emerald-700 font-medium',
    amber: 'text-amber-700 font-medium',
    red: 'text-red-700 font-medium',
    blue: 'text-blue-700 font-medium',
};

const subtextClass = computed(
    () => SUBTEXT_TONE_CLASSES[props.tone] || SUBTEXT_TONE_CLASSES.neutral,
);
</script>

<template>
    <div
        data-testid="stat-card"
        class="rounded-lg border border-slate-200 bg-white p-5 shadow-xs"
    >
        <div class="flex items-start justify-between gap-3">
            <p
                data-testid="stat-card-label"
                class="text-xs font-medium uppercase tracking-wider text-slate-500"
            >
                {{ label }}
            </p>

            <component
                :is="icon"
                v-if="icon"
                class="h-5 w-5 shrink-0 text-slate-400"
                :stroke-width="1.75"
            />
        </div>

        <p
            data-testid="stat-card-value"
            class="mt-2 text-2xl font-semibold tabular-nums text-slate-900"
        >
            {{ value }}
        </p>

        <p
            v-if="subtext"
            data-testid="stat-card-subtext"
            :class="['mt-1.5 text-xs tabular-nums', subtextClass]"
        >
            {{ subtext }}
        </p>
    </div>
</template>
