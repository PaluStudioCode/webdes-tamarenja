<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { RotateCcw, Search } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    searchPlaceholder: {
        type: String,
        default: 'Cari kata kunci...',
    },
    debounceMs: {
        type: Number,
        default: 300,
    },
    filters: {
        type: Array,
        default: () => [],
    },
    hasCustomActiveFilter: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits([
    'update:modelValue',
    'search',
    'update:filter',
    'reset',
]);

const localSearch = ref(props.modelValue || '');
let debounceTimer = null;

watch(
    () => props.modelValue,
    (newVal) => {
        if ((newVal || '') !== localSearch.value) {
            localSearch.value = newVal || '';
        }
    },
);

function handleSearchInput(event) {
    const val = event.target.value;
    localSearch.value = val;

    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }

    debounceTimer = setTimeout(() => {
        emit('update:modelValue', val);
        emit('search', val);
    }, props.debounceMs);
}

function handleFilterSelect(key, event) {
    emit('update:filter', { key, value: event.target.value });
}

const hasActiveFilter = computed(() => {
    if (localSearch.value.trim() !== '') {
        return true;
    }

    if (props.hasCustomActiveFilter) {
        return true;
    }

    return (props.filters || []).some(
        (f) =>
            f.value !== '' &&
            f.value !== null &&
            f.value !== undefined &&
            f.value !== 'all',
    );
});

function handleReset() {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    localSearch.value = '';
    emit('update:modelValue', '');
    emit('reset');
}

onUnmounted(() => {
    if (debounceTimer) {
        clearTimeout(debounceTimer);
    }
});
</script>

<template>
    <div
        data-testid="unified-filter-bar"
        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
    >
        <!-- Pencarian Debounced -->
        <div class="relative min-w-[220px] flex-1">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                :stroke-width="1.75"
            />
            <input
                type="search"
                data-testid="filter-search-input"
                :value="localSearch"
                :placeholder="searchPlaceholder"
                aria-label="Pencarian data"
                class="h-10 w-full rounded-md border border-slate-300 bg-white pr-3 pl-9 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:ring-blue-700"
                @input="handleSearchInput"
            />
        </div>

        <!-- Dropdown Filter Satu Baris -->
        <div
            v-if="filters.length > 0 || $slots.default"
            class="flex flex-wrap items-center gap-2.5"
        >
            <select
                v-for="filter in filters"
                :key="filter.key"
                :value="filter.value ?? ''"
                :aria-label="filter.label || filter.key"
                :data-testid="`filter-select-${filter.key}`"
                class="h-10 rounded-md border border-slate-300 bg-white py-1.5 pr-8 pl-3 text-sm text-slate-700 focus:border-blue-700 focus:ring-blue-700"
                @change="handleFilterSelect(filter.key, $event)"
            >
                <option value="">
                    {{ filter.allLabel || `Semua ${filter.label || ''}`.trim() }}
                </option>
                <option
                    v-for="opt in filter.options"
                    :key="opt.value"
                    :value="opt.value"
                >
                    {{ opt.label }}
                </option>
            </select>

            <slot />
        </div>

        <!-- Tombol Reset Filter Kondisional -->
        <button
            v-if="hasActiveFilter"
            type="button"
            data-testid="filter-reset-btn"
            class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50"
            @click="handleReset"
        >
            <RotateCcw class="h-3.5 w-3.5 text-slate-500" :stroke-width="2" />
            <span>Reset Filter</span>
        </button>
    </div>
</template>
