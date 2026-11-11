<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronLeft,
    ChevronRight,
    MoreHorizontal,
} from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import { formatNumber, formatRupiah } from '@/Utils/formatters';

const MAX_PRIMARY_COLUMNS = 7;
const MUTATING_ACTION_KEYS = new Set([
    'edit',
    'ubah',
    'update',
    'delete',
    'hapus',
    'destroy',
    'batalkan',
    'tolak',
    'verifikasi',
    'mutasi',
    'reaktivasi',
    'tambah',
    'create',
    'status',
]);

const props = defineProps({
    columns: {
        type: Array,
        required: true,
    },
    rows: {
        type: [Array, Object],
        default: () => [],
    },
    pagination: {
        type: Object,
        default: null,
    },
    rowKey: {
        type: String,
        default: 'id',
    },
    rowActions: {
        type: [Array, Function],
        default: null,
    },
    sortBy: {
        type: String,
        default: '',
    },
    sortDirection: {
        type: String,
        default: 'asc',
    },
    emptyText: {
        type: String,
        default: 'Belum ada data untuk ditampilkan.',
    },
    emptyActionLabel: {
        type: String,
        default: '',
    },
    emptyActionHref: {
        type: String,
        default: '',
    },
    responsiveActions: {
        type: Boolean,
        default: false,
    },
    isRowExpanded: {
        type: Function,
        default: null,
    },
});

const emit = defineEmits(['sort', 'page-change', 'action', 'empty-action']);

const page = usePage();
const openMenuRowId = ref(null);
const tableRootRef = ref(null);

const userRole = computed(() => page.props?.auth?.user?.role || null);
const isOperator = computed(() => userRole.value === 'operator');

// Disiplin anti-penumpukan kolom: maksimal 7 kolom utama
const visibleColumns = computed(() =>
    (props.columns || []).slice(0, MAX_PRIMARY_COLUMNS),
);

const items = computed(() => {
    if (Array.isArray(props.rows)) {
        return props.rows;
    }
    if (props.rows && Array.isArray(props.rows.data)) {
        return props.rows.data;
    }
    return [];
});

const paginationMeta = computed(() => {
    if (props.pagination) {
        return props.pagination;
    }
    if (
        props.rows &&
        !Array.isArray(props.rows) &&
        typeof props.rows.total === 'number'
    ) {
        return props.rows;
    }
    return null;
});

function getRowId(row, index) {
    return row?.[props.rowKey] ?? index;
}

function isActionAllowedForRole(action) {
    const keyLower = String(action.key || '').toLowerCase();
    const requiresOperator =
        typeof action.operatorOnly === 'boolean'
            ? action.operatorOnly
            : MUTATING_ACTION_KEYS.has(keyLower) || action.variant === 'danger';

    if (requiresOperator && !isOperator.value) {
        return false;
    }

    return true;
}

function getResolvedActions(row) {
    if (!props.rowActions) {
        return { inline: [], menu: [] };
    }

    const rawList =
        typeof props.rowActions === 'function'
            ? props.rowActions(row) || []
            : props.rowActions;

    const allowed = rawList.filter(
        (act) => act && !act.hidden && isActionAllowedForRole(act),
    );

    if (props.responsiveActions) {
        return { inline: allowed, menu: allowed, all: allowed };
    }

    if (
        allowed.length <= 2 &&
        !allowed.some((a) => a.variant === 'danger' || a.menuOnly)
    ) {
        return { inline: allowed, menu: [], all: allowed };
    }

    const canBeInline = allowed.filter(
        (act) => !act.menuOnly && act.variant !== 'danger',
    );

    const explicitPrimary = canBeInline.filter((act) => act.primary);

    const inline =
        explicitPrimary.length > 0
            ? explicitPrimary.slice(0, 2)
            : canBeInline.slice(0, 1);

    const inlineSet = new Set(inline);
    const menu = allowed.filter((act) => !inlineSet.has(act));

    return { inline, menu, all: allowed };
}

const hasActionsColumn = computed(() => Boolean(props.rowActions));

function getColumnAlignClass(col) {
    if (col.isCurrency || col.type === 'currency' || col.align === 'right') {
        return 'text-right tabular-nums';
    }
    if (col.align === 'center') {
        return 'text-center';
    }
    return col.isNumeric ? 'text-left tabular-nums' : 'text-left';
}

function formatCellValue(row, col) {
    const val = row?.[col.key];
    if (col.isCurrency || col.type === 'currency') {
        return formatRupiah(val ?? 0);
    }
    if (typeof col.formatter === 'function') {
        return col.formatter(val, row);
    }
    return val ?? '-';
}

function handleSort(col) {
    if (!col.sortable) {
        return;
    }
    const nextDir =
        props.sortBy === col.key && props.sortDirection === 'asc'
            ? 'desc'
            : 'asc';
    emit('sort', { key: col.key, direction: nextDir });
}

function triggerRowAction(action, row) {
    openMenuRowId.value = null;
    if (typeof action.onClick === 'function') {
        action.onClick(row);
    }
    emit('action', { key: action.key, row });
}

function toggleRowMenu(id) {
    openMenuRowId.value = openMenuRowId.value === id ? null : id;
}

function handleOutsideClick(event) {
    if (
        openMenuRowId.value !== null &&
        tableRootRef.value &&
        !tableRootRef.value.contains(event.target)
    ) {
        openMenuRowId.value = null;
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
    <div
        ref="tableRootRef"
        data-testid="data-table"
        class="rounded-lg border border-slate-200 bg-white"
    >
        <div v-if="items.length > 0" class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50/80">
                    <tr>
                        <th
                            v-for="col in visibleColumns"
                            :key="col.key"
                            scope="col"
                            :data-testid="`th-${col.key}`"
                            :class="[
                                'px-4 py-3 text-xs font-medium uppercase tracking-wider text-slate-500',
                                getColumnAlignClass(col),
                                col.class || '',
                            ]"
                        >
                            <button
                                v-if="col.sortable"
                                type="button"
                                :data-testid="`sort-btn-${col.key}`"
                                class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-slate-800"
                                @click="handleSort(col)"
                            >
                                <span>{{ col.label }}</span>
                                <ArrowUp
                                    v-if="
                                        sortBy === col.key &&
                                        sortDirection === 'asc'
                                    "
                                    class="h-3.5 w-3.5 text-blue-700"
                                />
                                <ArrowDown
                                    v-else-if="
                                        sortBy === col.key &&
                                        sortDirection === 'desc'
                                    "
                                    class="h-3.5 w-3.5 text-blue-700"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="h-3.5 w-3.5 text-slate-400"
                                />
                            </button>
                            <span v-else>{{ col.label }}</span>
                        </th>

                        <th
                            v-if="hasActionsColumn || $slots.actions"
                            scope="col"
                            class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-slate-500"
                        >
                            Aksi
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 bg-white">
                    <template
                        v-for="(row, rowIndex) in items"
                        :key="getRowId(row, rowIndex)"
                    >
                    <tr
                        class="transition-colors hover:bg-slate-50/60"
                    >
                        <td
                            v-for="col in visibleColumns"
                            :key="col.key"
                            :data-testid="`td-${col.key}-${rowIndex}`"
                            :class="[
                                'px-4 py-3.5 align-middle text-slate-700',
                                getColumnAlignClass(col),
                                col.cellClass || '',
                            ]"
                        >
                            <slot
                                :name="`cell-${col.key}`"
                                :row="row"
                                :value="row[col.key]"
                                :index="rowIndex"
                            >
                                {{ formatCellValue(row, col) }}
                            </slot>
                        </td>

                        <td
                            v-if="hasActionsColumn || $slots.actions"
                            class="px-4 py-3.5 text-right align-middle whitespace-nowrap"
                        >
                            <slot
                                name="actions"
                                :row="row"
                                :is-operator="isOperator"
                            >
                                <div
                                    class="inline-flex items-center justify-end gap-1"
                                >
                                    <!-- Mode Responsif: Desktop Seluruh Aksi Berjejer, Mobile Masuk Titik 3 -->
                                    <template v-if="responsiveActions">
                                        <!-- Desktop (>= md) -->
                                        <div
                                            v-if="getResolvedActions(row).all.length > 0"
                                            class="hidden md:inline-flex items-center gap-1"
                                        >
                                            <template
                                                v-for="act in getResolvedActions(row).all"
                                                :key="`dt-desk-${act.key}`"
                                            >
                                                <a
                                                    v-if="act.href && act.external"
                                                    :href="act.href"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    :data-testid="`row-action-${act.key}-${rowIndex}`"
                                                    :class="[
                                                        'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors',
                                                        act.variant === 'danger'
                                                            ? 'text-red-600 hover:bg-red-50 hover:text-red-700'
                                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                                    ]"
                                                >
                                                    <component
                                                        :is="act.icon"
                                                        v-if="act.icon"
                                                        class="h-4 w-4 shrink-0"
                                                        :stroke-width="1.75"
                                                    />
                                                    <span>{{ act.label }}</span>
                                                </a>

                                                <Link
                                                    v-else-if="act.href"
                                                    :href="act.href"
                                                    :data-testid="`row-action-${act.key}-${rowIndex}`"
                                                    :class="[
                                                        'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors',
                                                        act.variant === 'danger'
                                                            ? 'text-red-600 hover:bg-red-50 hover:text-red-700'
                                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                                    ]"
                                                >
                                                    <component
                                                        :is="act.icon"
                                                        v-if="act.icon"
                                                        class="h-4 w-4 shrink-0"
                                                        :stroke-width="1.75"
                                                    />
                                                    <span>{{ act.label }}</span>
                                                </Link>

                                                <button
                                                    v-else
                                                    type="button"
                                                    :data-testid="`row-action-${act.key}-${rowIndex}`"
                                                    :class="[
                                                        'inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition-colors',
                                                        act.variant === 'danger'
                                                            ? 'text-red-600 hover:bg-red-50 hover:text-red-700'
                                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                                    ]"
                                                    @click="triggerRowAction(act, row)"
                                                >
                                                    <component
                                                        :is="act.icon"
                                                        v-if="act.icon"
                                                        class="h-4 w-4 shrink-0"
                                                        :stroke-width="1.75"
                                                    />
                                                    <span>{{ act.label }}</span>
                                                </button>
                                            </template>
                                        </div>

                                        <!-- Mobile (< md) Titik Tiga -->
                                        <div
                                            v-if="getResolvedActions(row).all.length > 0"
                                            class="relative inline-block text-left md:hidden"
                                        >
                                            <button
                                                type="button"
                                                aria-label="Aksi lainnya"
                                                :data-testid="`row-more-btn-${rowIndex}`"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                                                @click.stop="toggleRowMenu(getRowId(row, rowIndex))"
                                            >
                                                <MoreHorizontal
                                                    class="h-4 w-4"
                                                    :stroke-width="2"
                                                />
                                            </button>

                                            <div
                                                v-if="openMenuRowId === getRowId(row, rowIndex)"
                                                :data-testid="`row-more-menu-${rowIndex}`"
                                                class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-md"
                                            >
                                                <template
                                                    v-for="sec in getResolvedActions(row).all"
                                                    :key="`dt-mob-${sec.key}`"
                                                >
                                                    <Link
                                                        v-if="sec.href"
                                                        :href="sec.href"
                                                        :data-testid="`row-action-menu-${sec.key}-${rowIndex}`"
                                                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                        @click="openMenuRowId = null"
                                                    >
                                                        <component
                                                            :is="sec.icon"
                                                            v-if="sec.icon"
                                                            class="h-4 w-4 shrink-0"
                                                            :stroke-width="1.75"
                                                        />
                                                        <span>{{ sec.label }}</span>
                                                    </Link>

                                                    <button
                                                        v-else
                                                        type="button"
                                                        :data-testid="`row-action-menu-${sec.key}-${rowIndex}`"
                                                        :class="[
                                                            'flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium',
                                                            sec.variant === 'danger'
                                                                ? 'text-red-600 hover:bg-red-50'
                                                                : 'text-slate-700 hover:bg-slate-50',
                                                        ]"
                                                        @click="triggerRowAction(sec, row)"
                                                    >
                                                        <component
                                                            :is="sec.icon"
                                                            v-if="sec.icon"
                                                            class="h-4 w-4 shrink-0"
                                                            :stroke-width="1.75"
                                                        />
                                                        <span>{{ sec.label }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Mode Standar Non-Responsive (inline + menu) -->
                                    <template v-else>
                                        <!-- Aksi Langsung (Maks 1-2 Tombol) -->
                                        <template
                                            v-for="act in getResolvedActions(row)
                                                .inline"
                                            :key="act.key"
                                        >
                                            <a
                                                v-if="act.href && act.external"
                                                :href="act.href"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                :data-testid="`row-action-${act.key}-${rowIndex}`"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                            >
                                                <component
                                                    :is="act.icon"
                                                    v-if="act.icon"
                                                    class="h-4 w-4 shrink-0"
                                                    :stroke-width="1.75"
                                                />
                                                <span>{{ act.label }}</span>
                                            </a>

                                            <Link
                                                v-else-if="act.href"
                                                :href="act.href"
                                                :data-testid="`row-action-${act.key}-${rowIndex}`"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                            >
                                                <component
                                                    :is="act.icon"
                                                    v-if="act.icon"
                                                    class="h-4 w-4 shrink-0"
                                                    :stroke-width="1.75"
                                                />
                                                <span>{{ act.label }}</span>
                                            </Link>

                                            <button
                                                v-else
                                                type="button"
                                                :data-testid="`row-action-${act.key}-${rowIndex}`"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                                @click="triggerRowAction(act, row)"
                                            >
                                                <component
                                                    :is="act.icon"
                                                    v-if="act.icon"
                                                    class="h-4 w-4 shrink-0"
                                                    :stroke-width="1.75"
                                                />
                                                <span>{{ act.label }}</span>
                                            </button>
                                        </template>

                                        <!-- Menu Tiga Titik (MoreHorizontal) untuk Aksi Sekunder -->
                                        <div
                                            v-if="
                                                getResolvedActions(row).menu
                                                    .length > 0
                                            "
                                            class="relative inline-block text-left"
                                        >
                                            <button
                                                type="button"
                                                aria-label="Aksi lainnya"
                                                :data-testid="`row-more-btn-${rowIndex}`"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                                                @click.stop="
                                                    toggleRowMenu(
                                                        getRowId(row, rowIndex),
                                                    )
                                                "
                                            >
                                                <MoreHorizontal
                                                    class="h-4 w-4"
                                                    :stroke-width="2"
                                                />
                                            </button>

                                            <div
                                                v-if="
                                                    openMenuRowId ===
                                                    getRowId(row, rowIndex)
                                                "
                                                :data-testid="`row-more-menu-${rowIndex}`"
                                                class="absolute right-0 z-20 mt-1 w-44 rounded-lg border border-slate-200 bg-white py-1 shadow-md"
                                            >
                                                <template
                                                    v-for="sec in getResolvedActions(
                                                        row,
                                                    ).menu"
                                                    :key="sec.key"
                                                >
                                                    <Link
                                                        v-if="sec.href"
                                                        :href="sec.href"
                                                        :data-testid="`row-action-${sec.key}-${rowIndex}`"
                                                        class="flex items-center gap-2 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                        @click="
                                                            openMenuRowId = null
                                                        "
                                                    >
                                                        <component
                                                            :is="sec.icon"
                                                            v-if="sec.icon"
                                                            class="h-4 w-4 shrink-0"
                                                            :stroke-width="1.75"
                                                        />
                                                        <span>{{ sec.label }}</span>
                                                    </Link>

                                                    <button
                                                        v-else
                                                        type="button"
                                                        :data-testid="`row-action-${sec.key}-${rowIndex}`"
                                                        :class="[
                                                            'flex w-full items-center gap-2 px-3 py-2 text-left text-xs font-medium',
                                                            sec.variant === 'danger'
                                                                ? 'text-red-600 hover:bg-red-50'
                                                                : 'text-slate-700 hover:bg-slate-50',
                                                        ]"
                                                        @click="
                                                            triggerRowAction(
                                                                sec,
                                                                row,
                                                            )
                                                        "
                                                    >
                                                        <component
                                                            :is="sec.icon"
                                                            v-if="sec.icon"
                                                            class="h-4 w-4 shrink-0"
                                                            :stroke-width="1.75"
                                                        />
                                                        <span>{{ sec.label }}</span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </slot>
                        </td>
                    </tr>

                    <tr
                        v-if="
                            $slots['expanded-row'] &&
                            isRowExpanded &&
                            isRowExpanded(row, rowIndex)
                        "
                        :data-testid="`expanded-row-${getRowId(row, rowIndex)}`"
                        class="bg-slate-50/70"
                    >
                        <td
                            :colspan="
                                visibleColumns.length +
                                (hasActionsColumn || $slots.actions ? 1 : 0)
                            "
                            class="px-4 py-3"
                        >
                            <slot
                                name="expanded-row"
                                :row="row"
                                :index="rowIndex"
                                :is-operator="isOperator"
                            />
                        </td>
                    </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <!-- Empty State saat tidak ada baris -->
        <div v-else class="p-4">
            <slot name="empty">
                <EmptyState
                    :title="emptyText"
                    :action-label="emptyActionLabel"
                    :action-href="emptyActionHref"
                    @action="emit('empty-action')"
                />
            </slot>
        </div>

        <!-- Server-Side Pagination Footer -->
        <div
            v-if="paginationMeta && paginationMeta.total > 0"
            data-testid="data-table-pagination"
            class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-600 sm:flex-row"
        >
            <p class="tabular-nums">
                Menampilkan
                <span class="font-semibold text-slate-900">{{
                    formatNumber(paginationMeta.from || 1)
                }}</span>
                &ndash;
                <span class="font-semibold text-slate-900">{{
                    formatNumber(paginationMeta.to || items.length)
                }}</span>
                dari
                <span class="font-semibold text-slate-900">{{
                    formatNumber(paginationMeta.total)
                }}</span>
                data
            </p>

            <div
                v-if="paginationMeta.last_page > 1"
                class="flex items-center gap-1.5"
            >
                <Link
                    v-if="paginationMeta.prev_page_url"
                    :href="paginationMeta.prev_page_url"
                    preserve-scroll
                    preserve-state
                    class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 font-medium text-slate-700 hover:bg-slate-50"
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                    <span>Sebelumnya</span>
                </Link>
                <button
                    v-else
                    type="button"
                    :disabled="paginationMeta.current_page <= 1"
                    class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-2.5 font-medium text-slate-400 cursor-not-allowed"
                    @click="
                        paginationMeta.current_page > 1 &&
                        emit('page-change', paginationMeta.current_page - 1)
                    "
                >
                    <ChevronLeft class="h-3.5 w-3.5" />
                    <span>Sebelumnya</span>
                </button>

                <span class="px-2 font-medium tabular-nums text-slate-700">
                    Hal. {{ paginationMeta.current_page }} /
                    {{ paginationMeta.last_page }}
                </span>

                <Link
                    v-if="paginationMeta.next_page_url"
                    :href="paginationMeta.next_page_url"
                    preserve-scroll
                    preserve-state
                    class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 font-medium text-slate-700 hover:bg-slate-50"
                >
                    <span>Berikutnya</span>
                    <ChevronRight class="h-3.5 w-3.5" />
                </Link>
                <button
                    v-else
                    type="button"
                    :disabled="
                        paginationMeta.current_page >= paginationMeta.last_page
                    "
                    class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 font-medium text-slate-700 hover:bg-slate-50 disabled:border-slate-200 disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed"
                    @click="
                        paginationMeta.current_page <
                            paginationMeta.last_page &&
                        emit('page-change', paginationMeta.current_page + 1)
                    "
                >
                    <span>Berikutnya</span>
                    <ChevronRight class="h-3.5 w-3.5" />
                </button>
            </div>
        </div>
    </div>
</template>
