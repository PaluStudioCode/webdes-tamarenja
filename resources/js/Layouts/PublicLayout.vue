<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ChevronDown,
    Clock,
    FileText,
    Landmark,
    Mail,
    MapPin,
    Menu,
    Phone,
    X,
} from 'lucide-vue-next';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';

defineProps({
    noPadding: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const isMobileMenuOpen = ref(false);
const isTransparansiOpen = ref(false);
const isMobileTransparansiOpen = ref(false);
const transparansiRef = ref(null);

const profilDesa = computed(() => page.props?.profil_desa || {});
const isAuthenticated = computed(() => Boolean(page.props?.auth?.user));

const namaDesa = computed(() => profilDesa.value.nama_desa || 'Tamarenja');
const wilayahDesa = computed(() => {
    const parts = [
        profilDesa.value.kecamatan ? `Kec. ${profilDesa.value.kecamatan}` : '',
        profilDesa.value.kabupaten ? `Kab. ${profilDesa.value.kabupaten}` : '',
    ].filter(Boolean);

    return parts.join(', ');
});

const logoSrc = computed(() => {
    if (profilDesa.value.logo_url) {
        return profilDesa.value.logo_url;
    }
    if (profilDesa.value.logo_desa_path) {
        return `/storage/${profilDesa.value.logo_desa_path}`;
    }
    return null;
});

const cleanWaDigits = computed(() =>
    String(profilDesa.value.no_wa_pelayanan || '').replace(/[^0-9]/g, ''),
);

const waHref = computed(() =>
    cleanWaDigits.value ? `https://wa.me/${cleanWaDigits.value}` : '#',
);

const currentPath = computed(() => {
    const raw = String(page.url || '/');
    const withoutQuery = raw.split('?')[0] || '/';
    return withoutQuery.length > 1 ? withoutQuery.replace(/\/+$/, '') : '/';
});

const transparansiItems = [
    { label: 'APBDes', href: '/transparansi/apbdes' },
    { label: 'Pembangunan', href: '/transparansi/pembangunan' },
    { label: 'Statistik Penduduk', href: '/transparansi/statistik-penduduk' },
];

function isPathActive(prefix, exact = false) {
    if (exact) {
        return currentPath.value === prefix;
    }
    return (
        currentPath.value === prefix ||
        currentPath.value.startsWith(`${prefix}/`)
    );
}

function closeMenus() {
    isMobileMenuOpen.value = false;
    isTransparansiOpen.value = false;
}

function handleOutsideClick(event) {
    if (
        isTransparansiOpen.value &&
        transparansiRef.value &&
        !transparansiRef.value.contains(event.target)
    ) {
        isTransparansiOpen.value = false;
    }
}

watch(
    () => page.url,
    () => {
        closeMenus();
    },
);

onMounted(() => {
    document.addEventListener('click', handleOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleOutsideClick);
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-slate-50 text-slate-900">
        <GlobalNotificationHost />

        <!-- Top Navbar (6 Item Datar, Tanpa Tombol Login Internal Mencolok) -->
        <header
            data-testid="public-navbar"
            class="sticky top-0 z-40 border-b border-slate-200 bg-white"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8"
            >
                <!-- Identitas Desa -->
                <Link
                    href="/"
                    class="flex items-center gap-3 focus:outline-none"
                >
                    <img
                        v-if="logoSrc"
                        :src="logoSrc"
                        :alt="`Logo Desa ${namaDesa}`"
                        class="h-9 w-9 rounded-md object-contain"
                    />
                    <div
                        v-else
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-700 text-white"
                    >
                        <Landmark class="h-5 w-5" :stroke-width="1.75" />
                    </div>

                    <div class="leading-tight">
                        <span class="block text-sm font-semibold text-slate-900 sm:text-base">
                            Desa {{ namaDesa }}
                        </span>
                        <span
                            v-if="wilayahDesa"
                            class="hidden text-xs text-slate-500 sm:block"
                        >
                            {{ wilayahDesa }}
                        </span>
                    </div>
                </Link>

                <!-- Navigasi Desktop (6 Item Datar) -->
                <nav
                    aria-label="Navigasi utama"
                    data-testid="public-desktop-nav"
                    class="hidden items-center gap-1 lg:flex"
                >
                    <!-- 1. Beranda -->
                    <Link
                        href="/"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isPathActive('/', true)
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                        ]"
                    >
                        Beranda
                    </Link>

                    <!-- 2. Profil Desa -->
                    <Link
                        href="/profil"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isPathActive('/profil')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                        ]"
                    >
                        Profil Desa
                    </Link>

                    <!-- 3. Transparansi (Dropdown Ringkas) -->
                    <div ref="transparansiRef" class="relative">
                        <button
                            type="button"
                            data-testid="public-transparansi-trigger"
                            :aria-expanded="isTransparansiOpen"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                                isPathActive('/transparansi')
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                            ]"
                            @click.stop="isTransparansiOpen = !isTransparansiOpen"
                        >
                            <span>Transparansi</span>
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 transition-transform',
                                    isTransparansiOpen ? 'rotate-180' : '',
                                ]"
                                :stroke-width="2"
                            />
                        </button>

                        <div
                            v-if="isTransparansiOpen"
                            data-testid="public-transparansi-dropdown"
                            class="absolute left-0 mt-1.5 w-52 rounded-lg border border-slate-200 bg-white py-1.5 shadow-md"
                        >
                            <Link
                                v-for="sub in transparansiItems"
                                :key="sub.href"
                                :href="sub.href"
                                :class="[
                                    'block px-4 py-2 text-sm transition-colors',
                                    isPathActive(sub.href)
                                        ? 'bg-blue-50 font-medium text-blue-700'
                                        : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900',
                                ]"
                                @click="closeMenus"
                            >
                                {{ sub.label }}
                            </Link>
                        </div>
                    </div>

                    <!-- 4. Kegiatan -->
                    <Link
                        href="/kegiatan"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isPathActive('/kegiatan')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                        ]"
                    >
                        Kegiatan
                    </Link>

                    <!-- 5. Unduhan PPID -->
                    <Link
                        href="/unduhan"
                        :class="[
                            'rounded-md px-3 py-2 text-sm font-medium transition-colors',
                            isPathActive('/unduhan')
                                ? 'bg-blue-50 text-blue-700'
                                : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900',
                        ]"
                    >
                        Unduhan PPID
                    </Link>

                    <!-- 6. Tombol Aksen Layanan Mandiri -->
                    <Link
                        href="/layanan-mandiri"
                        class="ml-2 inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-medium text-white transition-colors hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-1"
                    >
                        Layanan Mandiri
                    </Link>
                </nav>

                <!-- Tombol Toggle Mobile -->
                <button
                    type="button"
                    aria-label="Buka menu navigasi"
                    data-testid="public-mobile-toggle"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                >
                    <X v-if="isMobileMenuOpen" class="h-5 w-5" :stroke-width="2" />
                    <Menu v-else class="h-5 w-5" :stroke-width="2" />
                </button>
            </div>

            <!-- Menu Navigasi Mobile -->
            <div
                v-if="isMobileMenuOpen"
                data-testid="public-mobile-menu"
                class="border-t border-slate-200 bg-white px-4 py-3 lg:hidden"
            >
                <div class="flex flex-col gap-1">
                    <Link
                        href="/"
                        class="flex min-h-[44px] items-center rounded-md px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="closeMenus"
                    >
                        Beranda
                    </Link>

                    <Link
                        href="/profil"
                        class="flex min-h-[44px] items-center rounded-md px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="closeMenus"
                    >
                        Profil Desa
                    </Link>

                    <div>
                        <button
                            type="button"
                            class="flex min-h-[44px] w-full items-center justify-between rounded-md px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                            @click="isMobileTransparansiOpen = !isMobileTransparansiOpen"
                        >
                            <span>Transparansi</span>
                            <ChevronDown
                                :class="[
                                    'h-4 w-4 transition-transform',
                                    isMobileTransparansiOpen ? 'rotate-180' : '',
                                ]"
                            />
                        </button>
                        <div
                            v-if="isMobileTransparansiOpen"
                            class="ml-3 flex flex-col border-l border-slate-200 pl-3"
                        >
                            <Link
                                v-for="sub in transparansiItems"
                                :key="sub.href"
                                :href="sub.href"
                                class="flex min-h-[44px] items-center rounded-md px-3 text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900"
                                @click="closeMenus"
                            >
                                {{ sub.label }}
                            </Link>
                        </div>
                    </div>

                    <Link
                        href="/kegiatan"
                        class="flex min-h-[44px] items-center rounded-md px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="closeMenus"
                    >
                        Kegiatan
                    </Link>

                    <Link
                        href="/unduhan"
                        class="flex min-h-[44px] items-center rounded-md px-3 text-sm font-medium text-slate-700 hover:bg-slate-100"
                        @click="closeMenus"
                    >
                        Unduhan PPID
                    </Link>

                    <Link
                        href="/layanan-mandiri"
                        class="mt-2 inline-flex min-h-[44px] items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-medium text-white hover:bg-blue-800"
                        @click="closeMenus"
                    >
                        Layanan Mandiri
                    </Link>
                </div>
            </div>
        </header>

        <!-- Slot Opsional Hero / Sub-header Lebar Penuh -->
        <slot name="banner" />

        <!-- Kontainer Konten Utama max-w-7xl -->
        <main
            data-testid="public-main-container"
            :class="[
                'mx-auto w-full max-w-7xl flex-1 px-4 sm:px-6 lg:px-8',
                noPadding ? '' : 'py-8 sm:py-10',
            ]"
        >
            <slot />
        </main>

        <!-- Footer Resmi Pemerintah Desa -->
        <footer
            data-testid="public-footer"
            class="border-t border-slate-200 bg-white text-slate-600"
        >
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <!-- Kolom 1: Alamat Kantor & Jam Pelayanan -->
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            Pemerintah Desa {{ namaDesa }}
                        </p>
                        <p
                            v-if="wilayahDesa"
                            class="mt-0.5 text-xs text-slate-500"
                        >
                            {{ wilayahDesa }}
                            <template v-if="profilDesa.provinsi">
                                — {{ profilDesa.provinsi }}
                            </template>
                        </p>

                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li
                                v-if="profilDesa.alamat_kantor"
                                class="flex items-start gap-2.5"
                            >
                                <MapPin
                                    class="mt-0.5 h-4 w-4 shrink-0 text-slate-400"
                                    :stroke-width="1.75"
                                />
                                <span>{{ profilDesa.alamat_kantor }}</span>
                            </li>
                            <li
                                v-if="profilDesa.jam_pelayanan"
                                class="flex items-start gap-2.5"
                            >
                                <Clock
                                    class="mt-0.5 h-4 w-4 shrink-0 text-slate-400"
                                    :stroke-width="1.75"
                                />
                                <span>{{ profilDesa.jam_pelayanan }}</span>
                            </li>
                        </ul>
                    </div>

                    <!-- Kolom 2: Kontak Resmi Pelayanan -->
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            Kontak Pelayanan Desa
                        </p>
                        <ul class="mt-4 space-y-2.5 text-sm">
                            <li v-if="profilDesa.email_desa">
                                <a
                                    :href="`mailto:${profilDesa.email_desa}`"
                                    class="inline-flex items-center gap-2.5 hover:text-blue-700"
                                >
                                    <Mail
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                        :stroke-width="1.75"
                                    />
                                    <span>{{ profilDesa.email_desa }}</span>
                                </a>
                            </li>
                            <li v-if="profilDesa.no_wa_pelayanan">
                                <a
                                    :href="waHref"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2.5 tabular-nums hover:text-blue-700"
                                >
                                    <Phone
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                        :stroke-width="1.75"
                                    />
                                    <span>{{ profilDesa.no_wa_pelayanan }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Kolom 3: Tautan Cepat Informasi Publik & PPID -->
                    <div>
                        <p class="text-sm font-semibold text-slate-900">
                            Keterbukaan Informasi Publik
                        </p>
                        <ul class="mt-4 space-y-2 text-sm">
                            <li>
                                <Link
                                    href="/unduhan"
                                    data-testid="footer-ppid-link"
                                    class="inline-flex items-center gap-2 hover:text-blue-700"
                                >
                                    <FileText
                                        class="h-4 w-4 shrink-0 text-slate-400"
                                        :stroke-width="1.75"
                                    />
                                    <span>Pusat Unduhan Dokumen Resmi PPID</span>
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/transparansi/apbdes"
                                    class="hover:text-blue-700"
                                >
                                    Infografis &amp; Baliho APBDes
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/layanan-mandiri"
                                    class="hover:text-blue-700"
                                >
                                    Layanan Mandiri Warga
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Baris Bawah: Hak Cipta & Tautan Halus Panel Perangkat Desa -->
                <div
                    class="mt-8 flex flex-col items-center justify-between gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500 sm:flex-row"
                >
                    <p>
                        &copy; {{ new Date().getFullYear() }} Pemerintah Desa
                        {{ namaDesa }}. Hak cipta dilindungi.
                    </p>
                    <Link
                        :href="isAuthenticated ? '/panel/dashboard' : '/login'"
                        data-testid="footer-panel-link"
                        class="text-slate-500 transition-colors hover:text-slate-800"
                    >
                        Panel Perangkat Desa
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
