<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Loader2 } from 'lucide-vue-next';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (val) =>
            [
                'primary',
                'secondary',
                'outline',
                'success-wa',
                'ghost',
                'destructive',
                'danger',
            ].includes(val),
    },
    size: {
        type: String,
        default: 'md',
        validator: (val) => ['sm', 'md', 'lg'].includes(val),
    },
    type: {
        type: String,
        default: 'button',
    },
    href: {
        type: String,
        default: '',
    },
    external: {
        type: Boolean,
        default: false,
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const VARIANT_CLASSES = {
    primary:
        'border-transparent bg-blue-700 text-white hover:bg-blue-800 focus:ring-blue-700',
    secondary:
        'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-400',
    outline:
        'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 focus:ring-slate-400',
    'success-wa':
        'border-transparent bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-600',
    ghost:
        'border-transparent bg-transparent text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:ring-slate-400',
    destructive:
        'border-transparent bg-red-600 text-white hover:bg-red-700 focus:ring-red-600',
    danger:
        'border-transparent bg-red-600 text-white hover:bg-red-700 focus:ring-red-600',
};

const SIZE_CLASSES = {
    sm: 'h-8 px-3 text-xs gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-11 min-h-[44px] px-5 text-sm gap-2',
};

const isDisabled = computed(() => props.disabled || props.loading);

const buttonClasses = computed(() => [
    'inline-flex items-center justify-center rounded-md border font-medium whitespace-nowrap transition-colors focus:outline-none focus:ring-2 focus:ring-offset-1',
    VARIANT_CLASSES[props.variant] || VARIANT_CLASSES.primary,
    SIZE_CLASSES[props.size] || SIZE_CLASSES.md,
    isDisabled.value ? 'cursor-not-allowed opacity-60 pointer-events-none' : '',
]);
</script>

<template>
    <a
        v-if="href && external"
        :href="isDisabled ? undefined : href"
        :aria-disabled="isDisabled || undefined"
        :class="buttonClasses"
        target="_blank"
        rel="noopener noreferrer"
    >
        <Loader2
            v-if="loading"
            data-testid="button-spinner"
            class="h-4 w-4 shrink-0 animate-spin"
        />
        <slot />
    </a>

    <Link
        v-else-if="href"
        :href="href"
        :aria-disabled="isDisabled || undefined"
        :class="buttonClasses"
    >
        <Loader2
            v-if="loading"
            data-testid="button-spinner"
            class="h-4 w-4 shrink-0 animate-spin"
        />
        <slot />
    </Link>

    <button
        v-else
        :type="type"
        :disabled="isDisabled"
        :aria-busy="loading || undefined"
        :class="buttonClasses"
    >
        <Loader2
            v-if="loading"
            data-testid="button-spinner"
            class="h-4 w-4 shrink-0 animate-spin"
        />
        <slot />
    </button>
</template>
