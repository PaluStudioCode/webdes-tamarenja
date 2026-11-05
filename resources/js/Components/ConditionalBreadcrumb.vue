<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();

// Daftar path indeks tingkat pertama yang dilarang menampilkan Breadcrumb (PRD 04 Seksi 1.1.4)
const FIRST_LEVEL_INDEX_PATHS = new Set([
    '/panel/dashboard',
    '/panel/kependudukan',
    '/panel/pembangunan',
    '/panel/apbdes',
    '/panel/akun',
    '/panel/persuratan/antrean',
    '/panel/persuratan/surat-keluar',
    '/panel/persuratan/surat-masuk',
    '/panel/sosial/bansos',
    '/panel/sosial/kegiatan',
    '/panel/pengaturan/profil-desa',
    '/panel/pengaturan/sotk',
    '/panel/pengaturan/ppid',
    '/panel/pengaturan/pengguna',
    '/panel/pengaturan/backup',
    '/profil',
    '/kegiatan',
    '/unduhan',
    '/layanan-mandiri',
]);

const normalizedPath = computed(() => {
    const raw = String(page.url || '');
    const withoutQuery = raw.split('?')[0] || '/';
    return withoutQuery.length > 1 ? withoutQuery.replace(/\/+$/, '') : '/';
});

const shouldRender = computed(() => {
    if (!Array.isArray(props.items) || props.items.length < 2) {
        return false;
    }

    if (FIRST_LEVEL_INDEX_PATHS.has(normalizedPath.value)) {
        return false;
    }

    return true;
});
</script>

<template>
    <nav
        v-if="shouldRender"
        aria-label="Breadcrumb"
        data-testid="conditional-breadcrumb"
        class="flex items-center text-xs font-medium text-slate-500"
    >
        <ol class="flex flex-wrap items-center gap-1.5">
            <li
                v-for="(item, index) in items"
                :key="`${item.label}-${index}`"
                class="inline-flex items-center gap-1.5"
            >
                <ChevronRight
                    v-if="index > 0"
                    class="h-3.5 w-3.5 shrink-0 text-slate-400"
                    :stroke-width="2"
                />

                <Link
                    v-if="item.href && index < items.length - 1"
                    :href="item.href"
                    class="transition-colors hover:text-blue-700"
                >
                    {{ item.label }}
                </Link>
                <span
                    v-else
                    :aria-current="
                        index === items.length - 1 ? 'page' : undefined
                    "
                    :class="[
                        index === items.length - 1
                            ? 'font-semibold text-slate-800'
                            : 'text-slate-500',
                    ]"
                >
                    {{ item.label }}
                </span>
            </li>
        </ol>
    </nav>
</template>
