<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Calendar, MapPin } from 'lucide-vue-next';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatDateId } from '@/Utils/formatters';

defineProps({
    kegiatan: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <PublicLayout>
        <Head :title="kegiatan.judul" />

        <article class="mx-auto max-w-4xl space-y-6">
            <div>
                <Link
                    href="/kegiatan"
                    class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-700 hover:underline"
                >
                    <ArrowLeft class="h-4 w-4" />
                    <span>Kembali ke Daftar Kegiatan Desa</span>
                </Link>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-6 sm:p-8">
                <div class="flex flex-wrap items-center gap-3 text-xs text-slate-500">
                    <span
                        class="rounded bg-blue-50 px-2.5 py-1 font-semibold text-blue-700"
                    >
                        {{ kegiatan.kategori_label }}
                    </span>
                    <span class="inline-flex items-center gap-1 tabular-nums">
                        <Calendar class="h-3.5 w-3.5" />
                        {{ formatDateId(kegiatan.tanggal_kegiatan) }}
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <MapPin class="h-3.5 w-3.5" />
                        {{ kegiatan.lokasi_dusun }}
                    </span>
                </div>

                <h1
                    class="mt-3 text-2xl font-semibold text-slate-900 sm:text-3xl"
                >
                    {{ kegiatan.judul }}
                </h1>

                <div
                    class="mt-6 whitespace-pre-line text-sm leading-relaxed text-slate-700"
                >
                    {{ kegiatan.uraian }}
                </div>

                <!-- Galeri 1 s/d 5 Foto Dokumentasi -->
                <div
                    v-if="
                        kegiatan.foto_galeri &&
                        kegiatan.foto_galeri.length > 0
                    "
                    data-testid="kegiatan-foto-galeri"
                    class="mt-8 border-t border-slate-200 pt-6"
                >
                    <h2 class="mb-4 text-base font-semibold text-slate-900">
                        Galeri Dokumentasi Lapangan ({{
                            kegiatan.foto_galeri.length
                        }}
                        Foto)
                    </h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div
                            v-for="foto in kegiatan.foto_galeri"
                            :key="foto.id"
                            class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                        >
                            <img
                                :src="foto.foto_url"
                                :alt="`${kegiatan.judul} - Foto #${foto.urutan}`"
                                class="h-60 w-full object-cover"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </article>
    </PublicLayout>
</template>
