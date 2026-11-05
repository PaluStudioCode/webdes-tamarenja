<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    tabs: {
        type: Array,
        required: true,
    },
    modelValue: {
        type: String,
        default: '',
    },
    ariaLabel: {
        type: String,
        default: 'Navigasi tab kontekstual',
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const page = usePage();

const currentPath = computed(() => {
    const raw = String(page.url || '/');
    const withoutQuery = raw.split('?')[0] || '/';
    return withoutQuery.length > 1 ? withoutQuery.replace(/\/+$/, '') : '/';
});

function getTabKey(tab, index) {
    return tab.key ?? tab.value ?? tab.href ?? `tab-${index}`;
}

function isTabActive(tab) {
    if (typeof tab.active === 'boolean') {
        return tab.active;
    }

    if (tab.href) {
        const cleanHref =
            tab.href.length > 1 ? tab.href.replace(/\/+$/, '') : tab.href;
        return (
            currentPath.value === cleanHref ||
            currentPath.value.startsWith(`${cleanHref}/`)
        );
    }

    const tabId = tab.key ?? tab.value;
    return Boolean(tabId && props.modelValue === tabId);
}

function selectTab(tab) {
    if (tab.disabled || tab.href) {
        return;
    }

    const tabId = tab.key ?? tab.value;
    emit('update:modelValue', tabId);
    emit('change', tabId);
}
</script>

<template>
    <div
        data-testid="contextual-tabs"
        class="border-b border-slate-200 overflow-x-auto"
    >
        <nav
            :aria-label="ariaLabel"
            class="-mb-px flex min-w-max items-center gap-6"
        >
            <template v-for="(tab, index) in tabs" :key="getTabKey(tab, index)">
                <Link
                    v-if="tab.href"
                    :href="tab.href"
                    :data-testid="`contextual-tab-${getTabKey(tab, index)}`"
                    :aria-current="isTabActive(tab) ? 'page' : undefined"
                    :class="[
                        'inline-flex items-center gap-2 border-b-2 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                        isTabActive(tab)
                            ? 'border-blue-700 font-semibold text-blue-700'
                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
                    ]"
                >
                    <component
                        :is="tab.icon"
                        v-if="tab.icon"
                        class="h-4 w-4 shrink-0"
                        :stroke-width="1.75"
                    />
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.badge !== undefined && tab.badge !== null && Number(tab.badge) > 0"
                        class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-amber-800"
                    >
                        {{ tab.badge }}
                    </span>
                </Link>

                <button
                    v-else
                    type="button"
                    role="tab"
                    :disabled="tab.disabled"
                    :aria-selected="isTabActive(tab)"
                    :data-testid="`contextual-tab-${getTabKey(tab, index)}`"
                    :class="[
                        'inline-flex items-center gap-2 border-b-2 py-3 text-sm font-medium whitespace-nowrap transition-colors',
                        isTabActive(tab)
                            ? 'border-blue-700 font-semibold text-blue-700'
                            : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900',
                        tab.disabled ? 'cursor-not-allowed opacity-50' : '',
                    ]"
                    @click="selectTab(tab)"
                >
                    <component
                        :is="tab.icon"
                        v-if="tab.icon"
                        class="h-4 w-4 shrink-0"
                        :stroke-width="1.75"
                    />
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.badge !== undefined && tab.badge !== null && Number(tab.badge) > 0"
                        class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-amber-800"
                    >
                        {{ tab.badge }}
                    </span>
                </button>
            </template>
        </nav>
    </div>
</template>
