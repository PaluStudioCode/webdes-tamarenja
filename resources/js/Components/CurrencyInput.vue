<script setup>
import { computed, useId } from 'vue';
import { formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    modelValue: {
        type: [Number, String],
        default: 0,
    },
    id: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Rp 0',
    },
    helperText: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const autoId = useId();
const inputId = computed(() => props.id || `currency-${autoId}`);

const displayValue = computed(() => {
    if (
        props.modelValue === null ||
        props.modelValue === undefined ||
        props.modelValue === ''
    ) {
        return '';
    }

    const digits = String(props.modelValue).replace(/\D/g, '');
    if (digits === '') {
        return '';
    }

    const numeric = parseInt(digits, 10);
    return formatRupiah(Number.isNaN(numeric) ? 0 : numeric, true);
});

function handleInput(event) {
    const raw = String(event.target.value || '');
    const digits = raw.replace(/\D/g, '');
    const integerVal = digits === '' ? 0 : parseInt(digits, 10);

    // Sinkronkan tampilan langsung pada elemen input agar pemisah ribuan titik konsisten saat mengetik
    event.target.value = digits === '' ? '' : formatRupiah(integerVal, true);

    emit('update:modelValue', integerVal);
    emit('change', integerVal);
}
</script>

<template>
    <div class="space-y-1.5">
        <label
            v-if="label"
            :for="inputId"
            class="block text-sm font-medium text-slate-700"
        >
            <span>{{ label }}</span>
            <span v-if="required" class="ml-0.5 text-red-600">*</span>
        </label>

        <input
            :id="inputId"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            data-testid="currency-input"
            :value="displayValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :aria-invalid="Boolean(error) || undefined"
            :class="[
                'h-10 w-full rounded-md border bg-white px-3 text-sm font-medium tabular-nums text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:ring-2',
                error
                    ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                    : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                disabled ? 'cursor-not-allowed bg-slate-100 text-slate-500' : '',
            ]"
            @input="handleInput"
        />

        <p
            v-if="error"
            data-testid="currency-input-error"
            class="text-xs font-medium text-red-600"
        >
            {{ error }}
        </p>
        <p
            v-else-if="helperText"
            data-testid="currency-input-helper"
            class="text-xs text-slate-500"
        >
            {{ helperText }}
        </p>
    </div>
</template>
