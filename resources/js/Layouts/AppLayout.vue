<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronDown,
    FileSpreadsheet,
    Hammer,
    HeartHandshake,
    KeyRound,
    Landmark,
    LayoutDashboard,
    LogOut,
    Menu,
    PanelLeftClose,
    PanelLeftOpen,
    Settings,
    Users,
    Wallet,
    X,
} from 'lucide-vue-next';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';

const page = usePage();

const isSidebarCollapsed = ref(false);
const isMobileDrawerOpen = ref(false);
const isUserMenuOpen = ref(false);
const userMenuRef = ref(null);

const profilDesa = computed(() => page.props?.profil_desa || {});
const currentUser = computed(() => page.props?.auth?.user || null);
const userRole = computed(() => currentUser.value?.role || 'operator');
const isOperator = computed(() => userRole.value === 'operator');

const roleBadgeLabel = computed(() =>
    userRole.value === 'kepala_desa' ? 'Kepala Desa' : 'Operator Desa',
);

const namaDesa = computed(() => profilDesa.value.nama_desa || 'Tamarenja');

const logoSrc = computed(() => {
    if (profilDesa.value.logo_url) {
        return profilDesa.value.logo_url;
    }
    if (profilDesa.value.logo_desa_path) {
        return `/storage/${profilDesa.value.logo_desa_path}`;
    }
    return null;
});

const antreanCount = computed(() => {
    const raw =
        page.props?.antrean_surat_count ??
        page.props?.jumlah_antrean_surat ??
        0;
    return Number(raw) > 0 ? Number(raw) : 0;
});

const currentPath = computed(() => {
    const raw = String(page.url || '/panel/dashboard');
    const withoutQuery = raw.split('?')[0] || '/';
    return withoutQuery.length > 1 ? withoutQuery.replace(/\/+$/, '') : '/';
});

// Pemilih Tahun Anggaran Sesi disembunyikan otomatis pada rute lintas-waktu
const CROSS_TIME_PREFIXES = [
    '/panel/kependudukan',
    '/panel/pengaturan',
    '/panel/akun',
];

const showYearSelector = computed(() => {
    const routeCheck =
        typeof route === 'function' && typeof route().current === 'function'
            ? Boolean(
                  route().current('panel.kependudukan*') ||
                      route().current('panel.pengaturan*') ||
                      route().current('panel.akun*'),
              )
            : false;

    if (routeCheck) {
        return false;
    }

    return !CROSS_TIME_PREFIXES.some(
        (prefix) =>
            currentPath.value === prefix ||
            currentPath.value.startsWith(`${prefix}/`),
    );
});

const selectedTahun = computed(() =>
    Number(
        page.props?.filter_tahun ||
            profilDesa.value.tahun_anggaran_aktif ||
            new Date().getFullYear(),
    ),
);

const yearOptions = computed(() => {
    const baseActive = Number(
        profilDesa.value.tahun_anggaran_aktif || new Date().getFullYear(),
    );
    const fromProps = Array.isArray(page.props?.available_tahun)
        ? page.props.available_tahun.map(Number)
        : [baseActive + 1, baseActive, baseActive - 1, baseActive - 2];

    const merged = new Set([...fromProps, selectedTahun.value, baseActive]);
    return Array.from(merged)
        .filter((yr) => Number.isFinite(yr) && yr >= 2020)
        .sort((a, b) => b - a);
});

function handleYearChange(event) {
    const nextYear = Number(event.target.value);
    if (!nextYear || nextYear === selectedTahun.value) {
        return;
    }

    router.post(
        '/panel/filter-tahun',
        { tahun: nextYear },
        { preserveScroll: true },
    );
}

function isRouteOrPrefixActive(routePattern, urlPrefix) {
    if (
        routePattern &&
        typeof route === 'function' &&
        typeof route().current === 'function' &&
        route().current(routePattern)
    ) {
        return true;
    }

    return (
        currentPath.value === urlPrefix ||
        currentPath.value.startsWith(`${urlPrefix}/`)
    );
}

const menuGroups = computed(() => {
    const groups = [
        {
            key: 'ringkasan',
            heading: 'Ringkasan',
            items: [
                {
                    key: 'dashboard',
                    label: 'Dashboard',
                    href: '/panel/dashboard',
                    icon: LayoutDashboard,
                    active: isRouteOrPrefixActive(
                        'panel.dashboard*',
                        '/panel/dashboard',
                    ),
                },
            ],
        },
        {
            key: 'master',
            heading: 'Master Data',
            items: [
                {
                    key: 'kependudukan',
                    label: 'Kependudukan',
                    href: '/panel/kependudukan',
                    icon: Users,
                    active: isRouteOrPrefixActive(
                        'panel.kependudukan*',
                        '/panel/kependudukan',
                    ),
                },
            ],
        },
        {
            key: 'operasional',
            heading: 'Operasional Desa',
            items: [
                {
                    key: 'pembangunan',
                    label: 'Pembangunan Fisik',
                    href: '/panel/pembangunan',
                    icon: Hammer,
                    active: isRouteOrPrefixActive(
                        'panel.pembangunan*',
                        '/panel/pembangunan',
                    ),
                },
                {
                    key: 'apbdes',
                    label: 'Anggaran APBDes',
                    href: '/panel/apbdes',
                    icon: Wallet,
                    active: isRouteOrPrefixActive(
                        'panel.apbdes*',
                        '/panel/apbdes',
                    ),
                },
                {
                    key: 'persuratan',
                    label: 'Persuratan & Agenda',
                    href: '/panel/persuratan/antrean',
                    icon: FileSpreadsheet,
                    badge: antreanCount.value,
                    active: isRouteOrPrefixActive(
                        'panel.persuratan*',
                        '/panel/persuratan',
                    ),
                },
                {
                    key: 'bansos',
                    label: 'Bantuan Sosial',
                    href: '/panel/sosial/bansos',
                    icon: HeartHandshake,
                    active: isRouteOrPrefixActive(
                        'panel.sosial.bansos*',
                        '/panel/sosial/bansos',
                    ),
                },
                {
                    key: 'kegiatan',
                    label: 'Kegiatan Desa',
                    href: '/panel/sosial/kegiatan',
                    icon: Calendar,
                    active: isRouteOrPrefixActive(
                        'panel.sosial.kegiatan*',
                        '/panel/sosial/kegiatan',
                    ),
                },
            ],
        },
    ];

    if (isOperator.value) {
        groups.push({
            key: 'konfigurasi',
            heading: 'Konfigurasi',
            items: [
                {
                    key: 'pengaturan',
                    label: 'Pengaturan Sistem',
                    href: '/panel/pengaturan/profil-desa',
                    icon: Settings,
                    active: isRouteOrPrefixActive(
                        'panel.pengaturan*',
                        '/panel/pengaturan',
                    ),
                },
            ],
        });
    }

    return groups;
});

function closeMobileDrawer() {
    isMobileDrawerOpen.value = false;
}

function handleLogout() {
    isUserMenuOpen.value = false;
    router.post('/logout');
}

function handleOutsideClick(event) {
    if (
        isUserMenuOpen.value &&
        userMenuRef.value &&
        !userMenuRef.value.contains(event.target)
    ) {
        isUserMenuOpen.value = false;
    }
}

let removeRouterListener = null;

watch(
    () => page.url,
    () => {
        isMobileDrawerOpen.value = false;
        isUserMenuOpen.value = false;
    },
);

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
    if (typeof router.on === 'function') {
        removeRouterListener = router.on('navigate', () => {
            isMobileDrawerOpen.value = false;
            isUserMenuOpen.value = false;
        });
    }
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
    if (typeof removeRouterListener === 'function') {
        removeRouterListener();
    }
});
</script>

<template>
    <div class="flex min-h-screen bg-slate-50 text-slate-900">
        <GlobalNotificationHost />

        <!-- Sidebar Desktop (>= 1024px, Collapsible) -->
        <aside
            data-testid="desktop-sidebar"
            :class="[
                'fixed inset-y-0 left-0 z-40 hidden shrink-0 flex-col border-r border-slate-200 bg-white transition-all duration-200 lg:flex',
                isSidebarCollapsed ? 'w-20' : 'w-64',
            ]"
        >
            <!-- Identitas Desa Satu Kali di Puncak Sidebar -->
            <div
                :class="[
                    'flex h-16 shrink-0 items-center border-b border-slate-200 px-4',
                    isSidebarCollapsed ? 'justify-center' : 'gap-3',
                ]"
            >
                <img
                    v-if="logoSrc"
                    :src="logoSrc"
                    :alt="`Logo Desa ${namaDesa}`"
                    class="h-9 w-9 shrink-0 rounded-md object-contain"
                />
                <div
                    v-else
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-700 text-white"
                >
                    <Landmark class="h-5 w-5" :stroke-width="1.75" />
                </div>

                <div v-if="!isSidebarCollapsed" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-900">
                        Desa {{ namaDesa }}
                    </p>
                    <p class="truncate text-xs text-slate-500">
                        Panel Pemerintahan
                    </p>
                </div>
            </div>

            <!-- Daftar Grup Menu -->
            <nav
                aria-label="Menu panel internal"
                class="flex-1 space-y-5 overflow-y-auto px-3 py-4"
            >
                <div v-for="group in menuGroups" :key="group.key">
                    <p
                        v-if="!isSidebarCollapsed"
                        class="mb-1.5 px-3 text-xs font-medium uppercase tracking-wider text-slate-400"
                    >
                        {{ group.heading }}
                    </p>

                    <ul class="space-y-1">
                        <li v-for="item in group.items" :key="item.key">
                            <Link
                                :href="item.href"
                                :data-testid="`sidebar-link-${item.key}`"
                                :title="isSidebarCollapsed ? item.label : undefined"
                                :class="[
                                    'flex items-center rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                    isSidebarCollapsed
                                        ? 'justify-center'
                                        : 'justify-between gap-3',
                                    item.active
                                        ? 'bg-blue-50 text-blue-700'
                                        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                                ]"
                            >
                                <span class="flex min-w-0 items-center gap-3">
                                    <component
                                        :is="item.icon"
                                        class="h-5 w-5 shrink-0"
                                        :stroke-width="1.75"
                                    />
                                    <span
                                        v-if="!isSidebarCollapsed"
                                        class="truncate"
                                    >
                                        {{ item.label }}
                                    </span>
                                </span>

                                <span
                                    v-if="item.badge > 0 && !isSidebarCollapsed"
                                    data-testid="persuratan-antrean-badge"
                                    class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-amber-800"
                                >
                                    {{ item.badge }}
                                </span>
                            </Link>

                            <!-- Submenu (opsional jika memiliki children) -->
                            <ul
                                v-if="item.children && !isSidebarCollapsed"
                                class="mt-1 ml-6 space-y-0.5 border-l border-slate-200 pl-3"
                            >
                                <li
                                    v-for="child in item.children"
                                    :key="child.key"
                                >
                                    <Link
                                        :href="child.href"
                                        :data-testid="`sidebar-sublink-${child.key}`"
                                        :class="[
                                            'block rounded-md px-2.5 py-1.5 text-xs font-medium transition-colors',
                                            child.active
                                                ? 'bg-blue-50 text-blue-700'
                                                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                        ]"
                                    >
                                        {{ child.label }}
                                    </Link>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </aside>

        <!-- Slide-Over Drawer Mobile (< 1024px) -->
        <div
            v-if="isMobileDrawerOpen"
            data-testid="mobile-sidebar-drawer"
            class="fixed inset-0 z-50 flex lg:hidden"
        >
            <div
                data-testid="mobile-drawer-backdrop"
                class="fixed inset-0 bg-slate-900/50"
                @click="closeMobileDrawer"
            />

            <aside
                class="relative z-10 flex w-72 max-w-[85vw] flex-col bg-white shadow-xl"
            >
                <div
                    class="flex h-16 items-center justify-between border-b border-slate-200 px-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-700 text-white"
                        >
                            <Landmark class="h-5 w-5" :stroke-width="1.75" />
                        </div>
                        <span class="text-sm font-semibold text-slate-900">
                            Desa {{ namaDesa }}
                        </span>
                    </div>

                    <button
                        type="button"
                        aria-label="Tutup menu"
                        data-testid="mobile-drawer-close"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                        @click="closeMobileDrawer"
                    >
                        <X class="h-5 w-5" :stroke-width="2" />
                    </button>
                </div>

                <nav
                    aria-label="Menu mobile panel internal"
                    class="flex-1 space-y-5 overflow-y-auto px-3 py-4"
                >
                    <div v-for="group in menuGroups" :key="group.key">
                        <p
                            class="mb-1.5 px-3 text-xs font-medium uppercase tracking-wider text-slate-400"
                        >
                            {{ group.heading }}
                        </p>

                        <ul class="space-y-1">
                            <li v-for="item in group.items" :key="item.key">
                                <Link
                                    :href="item.href"
                                    :data-testid="`mobile-sidebar-link-${item.key}`"
                                    :class="[
                                        'flex min-h-[44px] items-center justify-between gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                        item.active
                                            ? 'bg-blue-50 text-blue-700'
                                            : 'text-slate-700 hover:bg-slate-100',
                                    ]"
                                    @click="closeMobileDrawer"
                                >
                                    <span class="flex items-center gap-3">
                                        <component
                                            :is="item.icon"
                                            class="h-5 w-5 shrink-0"
                                            :stroke-width="1.75"
                                        />
                                        <span>{{ item.label }}</span>
                                    </span>

                                    <span
                                        v-if="item.badge > 0"
                                        class="inline-flex min-w-[20px] items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-semibold tabular-nums text-amber-800"
                                    >
                                        {{ item.badge }}
                                    </span>
                                </Link>

                                <ul
                                    v-if="item.children"
                                    class="mt-1 ml-6 space-y-0.5 border-l border-slate-200 pl-3"
                                >
                                    <li
                                        v-for="child in item.children"
                                        :key="child.key"
                                    >
                                        <Link
                                            :href="child.href"
                                            :class="[
                                                'flex min-h-[40px] items-center rounded-md px-2.5 text-xs font-medium transition-colors',
                                                child.active
                                                    ? 'bg-blue-50 text-blue-700'
                                                    : 'text-slate-600 hover:bg-slate-100',
                                            ]"
                                            @click="closeMobileDrawer"
                                        >
                                            {{ child.label }}
                                        </Link>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                </nav>
            </aside>
        </div>

        <!-- Area Utama (Topbar + Konten Kerja) -->
        <div
            data-testid="app-main-wrapper"
            :class="[
                'flex min-w-0 flex-1 flex-col transition-all duration-200',
                isSidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64',
            ]"
        >
            <!-- Topbar Ringkas (Tanpa Spanduk Selamat Datang Berulang) -->
            <header
                data-testid="app-topbar"
                class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-2">
                    <!-- Toggle Drawer Mobile -->
                    <button
                        type="button"
                        aria-label="Buka menu navigasi"
                        data-testid="mobile-sidebar-toggle"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                        @click="isMobileDrawerOpen = true"
                    >
                        <Menu class="h-5 w-5" :stroke-width="2" />
                    </button>

                    <!-- Toggle Collapse Sidebar Desktop -->
                    <button
                        type="button"
                        :aria-label="
                            isSidebarCollapsed
                                ? 'Perluas sidebar'
                                : 'Lipat sidebar'
                        "
                        data-testid="desktop-sidebar-toggle"
                        class="hidden h-9 w-9 items-center justify-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 lg:inline-flex"
                        @click="isSidebarCollapsed = !isSidebarCollapsed"
                    >
                        <PanelLeftOpen
                            v-if="isSidebarCollapsed"
                            class="h-5 w-5"
                            :stroke-width="1.75"
                        />
                        <PanelLeftClose
                            v-else
                            class="h-5 w-5"
                            :stroke-width="1.75"
                        />
                    </button>
                </div>

                <!-- Kanan Topbar: Pemilih Tahun Sesi + Badge Peran + Menu Akun -->
                <div class="flex items-center gap-3">
                    <!-- Pemilih Tahun Anggaran Sesi (Tersembunyi di Rute Lintas-Waktu) -->
                    <div
                        v-if="showYearSelector"
                        data-testid="topbar-year-selector-wrap"
                        class="flex items-center gap-2"
                    >
                        <Calendar
                            class="hidden h-4 w-4 text-slate-400 sm:block"
                            :stroke-width="1.75"
                        />
                        <label
                            for="topbar-filter-tahun"
                            class="hidden text-xs font-medium text-slate-600 sm:inline"
                        >
                            Tahun Anggaran:
                        </label>
                        <select
                            id="topbar-filter-tahun"
                            data-testid="topbar-year-selector"
                            :value="selectedTahun"
                            aria-label="Pilih Tahun Anggaran Sesi"
                            class="h-9 rounded-md border-slate-300 bg-slate-50 py-1 pr-8 pl-3 text-sm font-semibold tabular-nums text-slate-800 focus:border-blue-700 focus:ring-blue-700"
                            @change="handleYearChange"
                        >
                            <option
                                v-for="yr in yearOptions"
                                :key="yr"
                                :value="yr"
                            >
                                TA {{ yr }}
                            </option>
                        </select>
                    </div>

                    <!-- Menu Akun Pengguna -->
                    <div ref="userMenuRef" class="relative">
                        <button
                            type="button"
                            data-testid="topbar-user-trigger"
                            :aria-expanded="isUserMenuOpen"
                            class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                            @click.stop="isUserMenuOpen = !isUserMenuOpen"
                        >
                            <span class="max-w-[140px] truncate">
                                {{ currentUser?.name || 'Pengguna' }}
                            </span>
                            <ChevronDown class="h-4 w-4 text-slate-400" />
                        </button>

                        <div
                            v-if="isUserMenuOpen"
                            data-testid="topbar-user-dropdown"
                            class="absolute right-0 mt-1.5 w-52 rounded-lg border border-slate-200 bg-white py-1.5 shadow-md"
                        >
                            <div
                                class="border-b border-slate-100 px-4 py-2 sm:hidden"
                            >
                                <p class="text-xs font-medium text-slate-500">
                                    Peran: {{ roleBadgeLabel }}
                                </p>
                            </div>

                            <Link
                                href="/panel/akun"
                                class="flex items-center gap-2.5 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 hover:text-slate-900"
                                @click="isUserMenuOpen = false"
                            >
                                <KeyRound
                                    class="h-4 w-4 text-slate-400"
                                    :stroke-width="1.75"
                                />
                                <span>Ubah Kata Sandi</span>
                            </Link>

                            <button
                                type="button"
                                data-testid="topbar-logout-btn"
                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                                @click="handleLogout"
                            >
                                <LogOut
                                    class="h-4 w-4 text-red-500"
                                    :stroke-width="1.75"
                                />
                                <span>Keluar</span>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Area Kerja Konten -->
            <main class="flex-1 p-4 sm:p-6 lg:p-8">
                <div class="mx-auto max-w-7xl space-y-6">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
