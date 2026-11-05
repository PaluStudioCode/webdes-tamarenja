<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Database, Download, FileSpreadsheet, ShieldCheck } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    moduleStats: {
        type: Array,
        default: () => [
            { sheet: 'Kartu Keluarga', count: 0 },
            { sheet: 'Penduduk', count: 0 },
            { sheet: 'Proyek Pembangunan', count: 0 },
            { sheet: 'APBDes', count: 0 },
            { sheet: 'Surat Keluar', count: 0 },
            { sheet: 'Surat Masuk', count: 0 },
            { sheet: 'Penerima Bansos', count: 0 },
            { sheet: 'Kegiatan Desa', count: 0 },
        ],
    },
    sheetTitles: {
        type: Array,
        default: () => [
            'Kartu Keluarga',
            'Penduduk',
            'Proyek Pembangunan',
            'APBDes',
            'Surat Keluar',
            'Surat Masuk',
            'Penerima Bansos',
            'Kegiatan Desa',
        ],
    },
});

const pengaturanTabs = [
    {
        key: 'profil-desa',
        label: 'Profil & Kop',
        href: '/panel/pengaturan/profil-desa',
    },
    { key: 'sotk', label: 'SOTK', href: '/panel/pengaturan/sotk' },
    { key: 'ppid', label: 'Dokumen PPID', href: '/panel/pengaturan/ppid' },
    {
        key: 'pengguna',
        label: 'Pengguna',
        href: '/panel/pengaturan/pengguna',
    },
    { key: 'backup', label: 'Backup Data', href: '/panel/pengaturan/backup' },
];

const isDownloadingSql = ref(false);
const isDownloadingExcel = ref(false);

function handleDownloadSql() {
    if (isDownloadingSql.value) {
        return;
    }

    isDownloadingSql.value = true;
    if (typeof window !== 'undefined' && window.location) {
        window.location.assign('/panel/pengaturan/backup/sql');
    }
    setTimeout(() => {
        isDownloadingSql.value = false;
    }, 1500);
}

function handleDownloadExcel() {
    if (isDownloadingExcel.value) {
        return;
    }

    isDownloadingExcel.value = true;
    if (typeof window !== 'undefined' && window.location) {
        window.location.assign('/panel/pengaturan/backup/excel');
    }
    setTimeout(() => {
        isDownloadingExcel.value = false;
    }, 1500);
}
</script>

<template>
    <AppLayout>
        <Head title="Backup & Export Data Desa" />

        <PageHeader
            title="Pengaturan Sistem"
            description="Unduh cadangan penuh database (.sql) atau rekapitulasi arsip 8 modul desa (.xlsx) dengan 1x klik ke komputer lokal kantor desa."
        />

        <ContextualTabs :tabs="pengaturanTabs" />

        <BaseCallout
            variant="info"
            title="Penyimpanan Terproteksi & Fallback PDO SQL Dumper"
        >
            Seluruh berkas cadangan dibuat pada
            <strong>Private Storage</strong> (`storage/app/private/backups/`)
            yang tertutup dari akses publik dan langsung dialirkan melalui sesi
            terautentikasi Operator Desa. Pembuatan berkas `.sql` mendukung
            <code>mysqldump</code> dengan
            <strong>fallback otomatis ke Pure PHP PDO SQL Dumper</strong> bila
            binary server tidak tersedia.
        </BaseCallout>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Kartu 1: Backup Database Penuh (.sql) -->
            <div
                class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6"
            >
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-700"
                        >
                            <Database class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                Backup Database Sistem (`.sql`)
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Mencadangkan seluruh struktur tabel (`CREATE
                                TABLE`) dan seluruh baris data operasional desa
                                dalam format SQL standar yang siap dipulihkan
                                kapan saja.
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-md border border-slate-100 bg-slate-50 p-3.5 text-xs text-slate-600"
                    >
                        <div class="flex items-center gap-2 font-medium text-slate-800">
                            <ShieldCheck class="h-4 w-4 text-emerald-600" />
                            <span>Mesin Pencadangan Ganda</span>
                        </div>
                        <p class="mt-1">
                            Menggunakan <code>mysqldump</code> berkecepatan
                            tinggi bila tersedia di server, atau otomatis
                            beralih ke <strong>PHP PDO SQL Dumper</strong> murni
                            tanpa gagal di shared hosting.
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <BaseButton
                        variant="primary"
                        :loading="isDownloadingSql"
                        data-testid="download-sql-backup-btn"
                        @click="handleDownloadSql"
                    >
                        <Download class="h-4 w-4" />
                        <span>Unduh Backup Database (.sql)</span>
                    </BaseButton>
                </div>
            </div>

            <!-- Kartu 2: Export Data Penuh 8 Sheet (.xlsx) -->
            <div
                class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-6"
            >
                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"
                        >
                            <FileSpreadsheet class="h-5 w-5" />
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                Export Data Penuh 8 Lembar Kerja (`.xlsx`)
                            </h2>
                            <p class="mt-1 text-sm text-slate-600">
                                Mengunduh seluruh data operasional desa dalam 1
                                berkas Microsoft Excel multi-sheet (8 lembar
                                kerja terpisah) untuk arsip fisik dan pemeriksaan
                                lintas tahun.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div
                            v-for="item in moduleStats"
                            :key="item.sheet"
                            class="flex items-center justify-between rounded border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs"
                        >
                            <span class="font-medium text-slate-700">
                                {{ item.sheet }}
                            </span>
                            <span
                                class="font-semibold tabular-nums text-slate-900"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100">
                    <BaseButton
                        variant="secondary"
                        :loading="isDownloadingExcel"
                        data-testid="download-excel-backup-btn"
                        @click="handleDownloadExcel"
                    >
                        <FileSpreadsheet class="h-4 w-4" />
                        <span>Unduh Export Penuh 8 Sheet (.xlsx)</span>
                    </BaseButton>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
