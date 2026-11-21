<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Landmark, MapPin } from 'lucide-vue-next';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';

const page = usePage();

const profilDesa = computed(() => page.props?.profil_desa || {});
const namaDesa = computed(() => profilDesa.value.nama_desa || 'Tamarenja');
const kecamatan = computed(() => profilDesa.value.kecamatan || 'Sindue Tobata');
const kabupaten = computed(() => profilDesa.value.kabupaten || 'Donggala');
const provinsi = computed(() => profilDesa.value.provinsi || 'Sulawesi Tengah');
const alamatKantor = computed(
    () =>
        profilDesa.value.alamat_kantor ||
        'Jl. Hi. Paggaru No. 80 Tamarenja Kode Pos 94343',
);

const logoSrc = computed(() => {
    if (profilDesa.value.logo_url) {
        return profilDesa.value.logo_url;
    }
    if (profilDesa.value.logo_desa_path) {
        return `/storage/${profilDesa.value.logo_desa_path}`;
    }
    return null;
});
</script>

<template>
    <div
        data-testid="auth-layout"
        class="min-h-screen bg-white text-slate-900 lg:grid lg:grid-cols-12"
    >
        <GlobalNotificationHost />

        <!-- Panel Kiri: Visual Panorama & Identitas Resmi Desa (Desktop Split-Screen) -->
        <div
            class="relative hidden overflow-hidden bg-slate-900 p-10 text-white lg:col-span-5 lg:flex lg:flex-col lg:justify-between xl:col-span-6 xl:p-12"
        >
            <!-- Gambar Latar Panorama Desa -->
            <img
                src="/images/hero-desa.jpg"
                :alt="`Panorama Desa ${namaDesa}`"
                class="pointer-events-none absolute inset-0 h-full w-full object-cover object-center"
            />

            <!-- Gradasi Gelap agar Teks Kontras & Tenang -->
            <div
                class="pointer-events-none absolute inset-0 bg-gradient-to-br from-slate-950/90 via-slate-900/85 to-blue-950/80"
            ></div>

            <!-- Atas: Logo & Nama Pemerintah Desa -->
            <div class="relative z-10 flex items-center gap-3.5">
                <img
                    v-if="logoSrc"
                    :src="logoSrc"
                    :alt="`Logo Desa ${namaDesa}`"
                    class="h-11 w-11 rounded-lg bg-white/10 p-1 object-contain backdrop-blur-xs"
                />
                <div
                    v-else
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-700 text-white"
                >
                    <Landmark class="h-5 w-5" :stroke-width="1.75" />
                </div>

                <div class="leading-tight">
                    <p class="text-base font-semibold tracking-tight text-white">
                        Pemerintah Desa {{ namaDesa }}
                    </p>
                    <p class="mt-0.5 text-xs text-slate-300">
                        Kec. {{ kecamatan }}, Kab. {{ kabupaten }}
                    </p>
                </div>
            </div>

            <!-- Tengah: Informasi Sistem -->
            <div class="relative z-10 my-auto max-w-md space-y-4 py-12">
                <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">
                    Sistem Informasi Pemerintahan Desa
                </p>
                <h1 class="text-2xl font-bold leading-snug tracking-tight text-white xl:text-3xl">
                    Panel Operasional &amp; Administrasi Desa
                </h1>
                <p class="text-sm leading-relaxed text-slate-300">
                    Portal kerja terpadu perangkat desa untuk pengelolaan data kependudukan, layanan persuratan, anggaran APBDes, pembangunan fisik, dan penyaluran bantuan sosial.
                </p>
            </div>

            <!-- Bawah: Alamat Kantor & Wilayah -->
            <div class="relative z-10 border-t border-white/15 pt-5 text-xs text-slate-300">
                <div class="flex items-start gap-2">
                    <MapPin class="mt-0.5 h-3.5 w-3.5 shrink-0 text-blue-300" />
                    <span>{{ alamatKantor }} &bull; {{ provinsi }}</span>
                </div>
            </div>
        </div>

        <!-- Panel Kanan: Area Form Login Bersih -->
        <div
            class="flex min-h-screen flex-col justify-between bg-white px-6 py-8 sm:px-12 lg:col-span-7 xl:col-span-6"
        >
            <!-- Navigasi Kembali ke Portal Publik -->
            <div>
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ArrowLeft class="h-3.5 w-3.5" :stroke-width="2" />
                    <span>Kembali ke Portal Publik Desa</span>
                </Link>
            </div>

            <!-- Kontainer Form Login Terpusat -->
            <div class="mx-auto my-auto w-full max-w-sm py-8">
                <!-- Identitas Ringkas Khusus Layar Mobile / Tablet -->
                <div class="mb-6 flex items-center gap-3 border-b border-slate-200 pb-5 lg:hidden">
                    <img
                        v-if="logoSrc"
                        :src="logoSrc"
                        :alt="`Logo Desa ${namaDesa}`"
                        class="h-11 w-11 rounded-lg object-contain"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-700 text-white"
                    >
                        <Landmark class="h-5 w-5" :stroke-width="1.75" />
                    </div>
                    <div class="leading-tight">
                        <p class="text-sm font-semibold text-slate-900">
                            Pemerintah Desa {{ namaDesa }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Kec. {{ kecamatan }}, Kab. {{ kabupaten }}
                        </p>
                    </div>
                </div>

                <slot />
            </div>

            <!-- Footer Hak Cipta Ringkas -->
            <div class="text-center text-xs text-slate-400 sm:text-left">
                &copy; {{ new Date().getFullYear() }} Pemerintah Desa {{ namaDesa }}. Hak cipta dilindungi.
            </div>
        </div>
    </div>
</template>
