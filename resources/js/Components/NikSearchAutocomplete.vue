<script setup>
import { computed, onMounted, onUnmounted, ref, useId, watch } from 'vue';
import axios from 'axios';
import { Loader2, Search, UserCheck, X } from 'lucide-vue-next';
import StatusBadge from '@/Components/StatusBadge.vue';

const MIN_QUERY_LENGTH = 3;
const DEBOUNCE_MS = 300;

const props = defineProps({
    modelValue: {
        type: [Number, String, null],
        default: null,
    },
    initialResident: {
        type: Object,
        default: null,
    },
    label: {
        type: String,
        default: 'Cari Warga (NIK / Nama)',
    },
    placeholder: {
        type: String,
        default: 'Contoh: 7203081205900001 atau Budi Santoso',
    },
    helperText: {
        type: String,
        default: 'Ketik minimal 3 karakter NIK atau nama lengkap warga.',
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
    endpoint: {
        type: String,
        default: '/panel/kependudukan/cari-nik',
    },
    searchParams: {
        type: Object,
        default: () => ({}),
    },
    searchFn: {
        type: Function,
        default: null,
    },
    items: {
        type: Array,
        default: null,
    },
});

const emit = defineEmits(['update:modelValue', 'select', 'clear']);

const autoId = useId();
const inputId = computed(() => `nik-search-${autoId}`);
const rootRef = ref(null);

const query = ref('');
const results = ref([]);
const isLoading = ref(false);
const isDropdownOpen = ref(false);
const selectedResident = ref(props.initialResident || null);

let debounceTimer = null;

watch(
    () => props.initialResident,
    (next) => {
        selectedResident.value = next || null;
    },
);

watch(
    () => props.modelValue,
    (nextVal) => {
        if (!nextVal) {
            selectedResident.value = null;
        }
    },
);

async function runSearch(trimmed) {
    isLoading.value = true;
    try {
        if (typeof props.searchFn === 'function') {
            const data = await props.searchFn(trimmed);
            results.value = Array.isArray(data) ? data : [];
        } else if (Array.isArray(props.items)) {
            const lower = trimmed.toLowerCase();
            results.value = props.items.filter(
                (w) =>
                    String(w.nik || '').includes(trimmed) ||
                    String(w.nama_lengkap || '')
                        .toLowerCase()
                        .includes(lower),
            );
        } else {
            const response = await axios.get(props.endpoint, {
                params: { q: trimmed, ...props.searchParams },
            });
            results.value = Array.isArray(response?.data?.data)
                ? response.data.data
                : Array.isArray(response?.data)
                  ? response.data
                  : [];
        }
        isDropdownOpen.value = true;
    } catch {
        results.value = [];
        isDropdownOpen.value = false;
    } finally {
        isLoading.value = false;
    }
}

function handleQueryInput(event) {
    const val = event.target.value;
    query.value = val;

    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    const trimmed = val.trim();
    if (trimmed.length < MIN_QUERY_LENGTH) {
        results.value = [];
        isDropdownOpen.value = false;
        isLoading.value = false;
        return;
    }

    debounceTimer = setTimeout(() => {
        runSearch(trimmed);
    }, DEBOUNCE_MS);
}

function selectResident(resident) {
    selectedResident.value = resident;
    query.value = '';
    results.value = [];
    isDropdownOpen.value = false;
    emit('update:modelValue', resident.id);
    emit('select', resident);
}

function clearSelection() {
    selectedResident.value = null;
    query.value = '';
    results.value = [];
    isDropdownOpen.value = false;
    emit('update:modelValue', null);
    emit('select', null);
    emit('clear');
}

function resolveDusun(resident) {
    return (
        resident?.dusun ||
        resident?.kartu_keluarga?.dusun ||
        resident?.alamat_dusun ||
        ''
    );
}

function handleOutsideClick(event) {
    if (
        isDropdownOpen.value &&
        rootRef.value &&
        !rootRef.value.contains(event.target)
    ) {
        isDropdownOpen.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
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

        <!-- Kartu Ringkasan Warga Terpilih (Tanpa 12 Input Disabled Berulang) -->
        <div
            v-if="selectedResident"
            data-testid="selected-resident-summary"
            class="flex items-start justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50/50 p-3.5"
        >
            <div class="flex min-w-0 items-start gap-3">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-100 text-blue-700"
                >
                    <UserCheck class="h-4 w-4" :stroke-width="2" />
                </div>

                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ selectedResident.nama_lengkap }}
                        </p>
                        <StatusBadge
                            v-if="selectedResident.status_kesejahteraan"
                            :status="selectedResident.status_kesejahteraan"
                        />
                        <StatusBadge
                            v-if="
                                selectedResident.status_kependudukan &&
                                selectedResident.status_kependudukan !== 'aktif'
                            "
                            :status="selectedResident.status_kependudukan"
                        />
                    </div>

                    <p class="text-xs tabular-nums text-slate-600">
                        NIK: {{ selectedResident.nik }}
                        <template v-if="resolveDusun(selectedResident)">
                            &bull; {{ resolveDusun(selectedResident) }}
                        </template>
                    </p>
                </div>
            </div>

            <button
                v-if="!disabled"
                type="button"
                data-testid="clear-resident-btn"
                class="inline-flex h-8 shrink-0 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
                @click="clearSelection"
            >
                <X class="h-3.5 w-3.5" />
                <span>Ganti</span>
            </button>
        </div>

        <!-- Input Autocomplete NIK / Nama -->
        <div v-else class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                :stroke-width="1.75"
            />
            <input
                :id="inputId"
                type="text"
                autocomplete="off"
                data-testid="nik-search-input"
                :value="query"
                :placeholder="placeholder"
                :disabled="disabled"
                :aria-invalid="Boolean(error) || undefined"
                :class="[
                    'h-10 w-full rounded-md border bg-white pr-9 pl-9 text-sm text-slate-900 placeholder:text-slate-400 transition-colors focus:outline-none focus:ring-2',
                    error
                        ? 'border-red-500 focus:border-red-600 focus:ring-red-600/20'
                        : 'border-slate-300 focus:border-blue-700 focus:ring-blue-700/20',
                    disabled
                        ? 'cursor-not-allowed bg-slate-100 text-slate-500'
                        : '',
                ]"
                @input="handleQueryInput"
            />

            <Loader2
                v-if="isLoading"
                class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 animate-spin text-blue-700"
            />

            <!-- Daftar Hasil Pencarian -->
            <div
                v-if="isDropdownOpen"
                data-testid="nik-search-dropdown"
                class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-md"
            >
                <template v-if="results.length > 0">
                    <button
                        v-for="item in results"
                        :key="item.id || item.nik"
                        type="button"
                        :data-testid="`nik-option-${item.id || item.nik}`"
                        class="flex w-full items-center justify-between gap-3 px-3.5 py-2.5 text-left text-sm transition-colors hover:bg-slate-50"
                        @click="selectResident(item)"
                    >
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-900">
                                {{ item.nama_lengkap }}
                            </p>
                            <p class="text-xs tabular-nums text-slate-500">
                                NIK: {{ item.nik }}
                                <template v-if="resolveDusun(item)">
                                    &bull; {{ resolveDusun(item) }}
                                </template>
                            </p>
                        </div>
                        <StatusBadge
                            v-if="item.status_kesejahteraan"
                            :status="item.status_kesejahteraan"
                        />
                    </button>
                </template>

                <p v-else class="px-3.5 py-3 text-xs text-slate-500">
                    Warga yang cocok tidak ditemukan.
                </p>
            </div>
        </div>

        <p
            v-if="error"
            data-testid="nik-search-error"
            class="text-xs font-medium text-red-600"
        >
            {{ error }}
        </p>
        <p
            v-else-if="helperText && !selectedResident"
            class="text-xs text-slate-500"
        >
            {{ helperText }}
        </p>
    </div>
</template>
