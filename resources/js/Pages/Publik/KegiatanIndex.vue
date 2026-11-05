<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Calendar, Hammer, MapPin } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatDateId } from '@/Utils/formatters';

const props = defineProps({
    kegiatan: {
        type: [Array, Object],
        default: () => [],
    },
    kategori_options: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ kategori: '' }),
    },
});

const selectedKategori = ref(props.filters?.kategori || '');

const rows = computed(() => {
    if (Array.isArray(props.kegiatan)) {
        return props.kegiatan;
    }
    return Array.isArray(props.kegiatan?.data) ? props.kegiatan.data : [];
});

function selectKategori(val) {
    selectedKategori.value = val;
    router.get(
        '/kegiatan',
        { kategori: val || undefined },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <PublicLayout>
        <Head title="Kabar & Dokumentasi Kegiatan Desa" />

        <div class="space-y-6">
            <div
                class="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-6 sm:flex-row sm:items-center"
            >
                <div>
                    <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">
                        Dokumentasi Kegiatan &amp; Kemasyarakatan Desa
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Publikasi terbuka pelaksanaan penyaluran bantuan sosial,
                        Posyandu, Musdes, dan gotong royong warga.
                    </p>
                </div>

                <div>
                    <label
                        for="filter-kategori-kegiatan"
                        class="mb-1 block text-xs font-medium text-slate-500"
                    >
                        Filter Kategori
                    </label>
                    <select
                        id="filter-kategori-kegiatan"
                        v-model="selectedKategori"
                        data-testid="publik-filter-kategori-kegiatan"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        @change="selectKategori(selectedKategori)"
                    >
                        <option value="">Semua Kategori Kegiatan</option>
                        <option
                            v-for="opt in kategori_options"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
            </div>

            <EmptyState
                v-if="rows.length === 0"
                title="Belum ada dokumentasi kegiatan yang dipublikasikan"
                description="Silakan pilih kategori kegiatan lainnya."
            />

            <div
                v-else
                class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3"
            >
                <article
                    v-for="item in rows"
                    :key="item.id"
                    :data-testid="`publik-kegiatan-card-${item.id}`"
                    class="flex flex-col justify-between overflow-hidden rounded-lg border border-slate-200 bg-white"
                >
                    <div>
                        <div
                            class="flex h-44 w-full items-center justify-center bg-slate-100"
                        >
                            <img
                                v-if="item.foto_sampul_url"
                                :src="item.foto_sampul_url"
                                :alt="item.judul"
                                class="h-full w-full object-cover"
                            />
                            <Hammer v-else class="h-8 w-8 text-slate-300" />
                        </div>

                        <div class="space-y-2.5 p-5">
                            <div
                                class="flex items-center justify-between text-xs text-slate-500"
                            >
                                <span
                                    class="rounded bg-blue-50 px-2 py-0.5 font-medium text-blue-700"
                                >
                                    {{ item.kategori_label }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 tabular-nums"
                                >
                                    <Calendar class="h-3.5 w-3.5" />
                                    {{ formatDateId(item.tanggal_kegiatan) }}
                                </span>
                            </div>

                            <h2 class="text-base font-semibold text-slate-900">
                                <Link
                                    :href="`/kegiatan/${item.slug}`"
                                    class="hover:text-blue-700"
                                >
                                    {{ item.judul }}
                                </Link>
                            </h2>

                            <p
                                class="inline-flex items-center gap-1 text-xs text-slate-500"
                            >
                                <MapPin class="h-3.5 w-3.5" />
                                <span>{{ item.lokasi_dusun }}</span>
                            </p>

                            <p class="text-xs leading-relaxed text-slate-600">
                                {{ item.ringkasan }}
                            </p>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 px-5 py-3">
                        <Link
                            :href="`/kegiatan/${item.slug}`"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-blue-700 hover:underline"
                        >
                            <span>Baca Artikel &amp; Galeri Foto</span>
                            <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </article>
            </div>
        </div>
    </PublicLayout>
</template>
