<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Download, FileText } from 'lucide-vue-next';
import EmptyState from '@/Components/EmptyState.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    dokumen: {
        type: Array,
        default: () => [],
    },
    kategori_options: {
        type: Array,
        default: () => [],
    },
    available_tahun: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ kategori: '', tahun: null }),
    },
});

const selectedKategori = ref(props.filters?.kategori || '');
const selectedTahun = ref(props.filters?.tahun || '');

function applyFilters() {
    router.get(
        '/unduhan',
        {
            kategori: selectedKategori.value || undefined,
            tahun: selectedTahun.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <PublicLayout>
        <Head title="Pusat Unduhan Dokumen Resmi PPID" />

        <div class="space-y-6">
            <div
                class="flex flex-col justify-between gap-4 rounded-lg border border-slate-200 bg-white p-6 lg:flex-row lg:items-center"
            >
                <div>
                    <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">
                        Pusat Unduhan Dokumen Resmi Desa (PPID)
                    </h1>
                    <p class="mt-1 text-sm text-slate-600">
                        Unduh Peraturan Desa (Perdes APBDes), LPJ, RKPDes,
                        RPJMDes, SK, dan panduan layanan administrasi desa.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <label
                            for="filter-kategori-ppid"
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Kategori Dokumen
                        </label>
                        <select
                            id="filter-kategori-ppid"
                            v-model="selectedKategori"
                            data-testid="publik-filter-kategori-ppid"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            @change="applyFilters"
                        >
                            <option value="">Semua Kategori</option>
                            <option
                                v-for="opt in kategori_options"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="filter-tahun-ppid"
                            class="mb-1 block text-xs font-medium text-slate-500"
                        >
                            Tahun Regulasi
                        </label>
                        <select
                            id="filter-tahun-ppid"
                            v-model="selectedTahun"
                            data-testid="publik-filter-tahun-ppid"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            @change="applyFilters"
                        >
                            <option value="">Semua Tahun</option>
                            <option
                                v-for="yr in available_tahun"
                                :key="yr"
                                :value="yr"
                            >
                                {{ yr }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <EmptyState
                v-if="dokumen.length === 0"
                title="Belum ada dokumen resmi PPID pada kriteria ini"
                description="Silakan pilih kategori atau tahun regulasi lainnya."
            />

            <div v-else class="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white">
                <div
                    v-for="doc in dokumen"
                    :key="doc.id"
                    :data-testid="`publik-ppid-row-${doc.id}`"
                    class="flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center"
                >
                    <div class="flex items-start gap-3">
                        <div
                            class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                        >
                            <FileText class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                {{ doc.judul_dokumen }}
                            </h2>
                            <div
                                class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500"
                            >
                                <span
                                    class="rounded bg-slate-100 px-2 py-0.5 font-medium text-slate-700"
                                >
                                    {{ doc.kategori_label }}
                                </span>
                                <span v-if="doc.tahun_dokumen" class="tabular-nums">
                                    • Tahun {{ doc.tahun_dokumen }}
                                </span>
                                <span class="tabular-nums">
                                    • {{ doc.ukuran_file_kb }} KB
                                </span>
                                <span class="tabular-nums">
                                    • {{ doc.jumlah_unduhan }}x diunduh
                                </span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <a
                            :href="doc.download_url || `/unduhan/${doc.id}/file`"
                            :data-testid="`publik-download-ppid-${doc.id}`"
                            class="inline-flex min-h-[40px] items-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-blue-800"
                        >
                            <Download class="h-4 w-4" />
                            <span>Unduh PDF</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
