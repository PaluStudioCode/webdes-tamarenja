<script setup>
import { computed } from 'vue';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Info,
    Lock,
} from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'info',
        validator: (value) =>
            ['info', 'warning', 'neutral', 'locked', 'danger', 'success'].includes(
                value,
            ),
    },
    title: {
        type: String,
        default: '',
    },
    message: {
        type: String,
        default: '',
    },
});

const VARIANT_MAP = {
    info: {
        wrapper: 'border-blue-200 bg-blue-50/70 text-slate-800',
        iconClass: 'text-blue-700',
        titleClass: 'text-blue-950',
        icon: Info,
    },
    warning: {
        wrapper: 'border-amber-200 bg-amber-50/70 text-slate-800',
        iconClass: 'text-amber-700',
        titleClass: 'text-amber-950',
        icon: AlertTriangle,
    },
    neutral: {
        wrapper: 'border-slate-200 bg-slate-100/80 text-slate-700',
        iconClass: 'text-slate-600',
        titleClass: 'text-slate-900',
        icon: Info,
    },
    locked: {
        wrapper: 'border-slate-200 bg-slate-100/80 text-slate-700',
        iconClass: 'text-slate-600',
        titleClass: 'text-slate-900',
        icon: Lock,
    },
    danger: {
        wrapper: 'border-red-200 bg-red-50/70 text-slate-800',
        iconClass: 'text-red-700',
        titleClass: 'text-red-950',
        icon: AlertCircle,
    },
    success: {
        wrapper: 'border-emerald-200 bg-emerald-50/70 text-slate-800',
        iconClass: 'text-emerald-700',
        titleClass: 'text-emerald-950',
        icon: CheckCircle2,
    },
};

const currentStyle = computed(
    () => VARIANT_MAP[props.variant] || VARIANT_MAP.info,
);
</script>

<template>
    <div
        role="note"
        :data-testid="`base-callout-${variant}`"
        :class="[
            'flex items-start gap-3 rounded-lg border p-4 text-sm',
            currentStyle.wrapper,
        ]"
    >
        <component
            :is="currentStyle.icon"
            :class="['mt-0.5 h-5 w-5 shrink-0', currentStyle.iconClass]"
            :stroke-width="1.75"
        />

        <div class="min-w-0 flex-1">
            <p
                v-if="title"
                :class="['font-semibold', currentStyle.titleClass]"
            >
                {{ title }}
            </p>
            <div :class="[title ? 'mt-1' : '', 'leading-relaxed']">
                <slot>{{ message }}</slot>
            </div>
        </div>
    </div>
</template>
