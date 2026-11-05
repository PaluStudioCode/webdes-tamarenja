<script setup>
import { computed, onMounted, onUnmounted, ref, useId } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: 'Dusun',
    },
    placeholder: {
        type: String,
        default: 'Contoh: Dusun I',
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
    options: {
        type: Array,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const page = usePage();
const autoId = useId();
const inputId = computed(() => `dusun-combobox-${autoId}`);
const rootRef = ref(null);
const isOpen = ref(false);

const dusunMasterList = computed(() => {
    if (Array.isArray(props.options)) {
        return props.options;
    }

    const fromProfil = page.props?.profil_desa?.daftar_dusun;
    return Array.isArray(fromProfil) ? fromProfil : [];
});

const filteredOptions = computed(() => {
    const q = String(props.modelValue || '')
        .trim()
        .toLowerCase();
    if (!q) {
        return dusunMasterList.value;
    }

    const matches = dusunMasterList.value.filter((d) =>
        String(d).toLowerCase().includes(q),
    );
    return matches.length > 0 ? matches : dusunMasterList.value;
});

function handleInput(event) {
    const val = event.target.value;
    isOpen.value = true;
    emit('update:modelValue', val);
    emit('change', val);
}

function selectDusun(dusun) {
    isOpen.value = false;
    emit('update:modelValue', dusun);
    emit('change', dusun);
}

function handleOutsideClick(event) {
    if (
        isOpen.value &&
        rootRef.value &&
        !rootRef.value.contains(event.target)
    ) {
        isOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
});
</script>

<template>
    <div ref="rootRef" class="space-y-1.5">
        <label
            v-if="label"
            :for="inputId"
            class="block text-sm font-medium text-slate-700"
        >
            <span>{{ label }}</span>
            <span v-if="required" class="ml-0.5 text-red-600">*</span>
        </label>

        <div class="relative">
            <input
                :id="inputId"
                type="text"
                autocomplete="off"
                data-testid="dusun-combobox-input"
                :value="modelValue"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="Boolean(error) || undefined"
                :class="[
                    'h-10 w-full rounded-md border bg-white pr-9 pl-3 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:ring-2',
                    error
                        ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                        : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                    disabled
                        ? 'cursor-not-allowed bg-slate-100 text-slate-500'
                        : '',
                ]"
                @focus="isOpen = true"
                @input="handleInput"
            />

            <button
                v-if="!disabled && dusunMasterList.length > 0"
                type="button"
                aria-label="Tampilkan daftar dusun resmi"
                data-testid="dusun-combobox-toggle"
                class="absolute top-1/2 right-2 inline-flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded text-slate-400 hover:text-slate-700"
                @click.stop="isOpen = !isOpen"
            >
                <ChevronDown class="h-4 w-4" :stroke-width="2" />
            </button>

            <ul
                v-if="isOpen && filteredOptions.length > 0 && !disabled"
                data-testid="dusun-combobox-options"
                role="listbox"
                class="absolute z-30 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-md"
            >
                <li v-for="dusun in filteredOptions" :key="dusun">
                    <button
                        type="button"
                        :data-testid="`dusun-option-${dusun}`"
                        :class="[
                            'flex w-full items-center px-3.5 py-2 text-left text-sm transition-colors',
                            modelValue === dusun
                                ? 'bg-blue-50 font-medium text-blue-700'
                                : 'text-slate-700 hover:bg-slate-50',
                        ]"
                        @click="selectDusun(dusun)"
                    >
                        {{ dusun }}
                    </button>
                </li>
            </ul>
        </div>

        <p
            v-if="error"
            data-testid="dusun-combobox-error"
            class="text-xs font-medium text-red-600"
        >
            {{ error }}
        </p>
        <p v-else-if="helperText" class="text-xs text-slate-500">
            {{ helperText }}
        </p>
    </div>
</template>
