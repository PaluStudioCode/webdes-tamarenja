<script setup>
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    ShieldAlert,
} from 'lucide-vue-next';
import StatusBadge from '@/Components/StatusBadge.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import { formatDateId } from '@/Utils/formatters';

defineProps({
    status_verifikasi: {
        type: String,
        default: 'tidak_terdaftar',
    },
    banner_judul: {
        type: String,
        default: 'DOKUMEN TIDAK TERDAFTAR PADA BUKU AGENDA DESA',
    },
    banner_deskripsi: {
        type: String,
        default: '',
    },
    dokumen: {
        type: Object,
        default: null,
    },
    desa: {
        type: Object,
        default: () => ({
            nama_desa: 'Tamarenja',
            kecamatan: 'Sindue Tobata',
            kabupaten: 'Donggala',
        }),
    },
});
</script>

<template>
    <PublicLayout>
        <Head :title="`Verifikasi Keaslian Dokumen Surat — Desa ${desa.nama_desa}`" />

        <div class="mx-auto max-w-2xl space-y-6">
            <div>
                <Link
                    href="/layanan-mandiri"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-700 hover:underline"
                >
                    <ArrowLeft class="h-3.5 w-3.5" />
                    <span>Kembali ke Layanan Mandiri</span>
                </Link>
                <h1 class="mt-2 text-xl font-semibold text-slate-900 sm:text-2xl">
                    Verifikasi Keaslian Surat Resmi Desa {{ desa.nama_desa }}
                </h1>
                <p class="mt-1 text-xs text-slate-500">
                    Kecamatan {{ desa.kecamatan }}, Kabupaten {{ desa.kabupaten }}
                </p>
            </div>

            <!-- SKENARIO 1: SURAT ASLI & TERBIT -->
            <div
                v-if="status_verifikasi === 'terbit' && dokumen"
                data-testid="banner-verifikasi-terbit"
                class="overflow-hidden rounded-lg border border-emerald-200 bg-white"
            >
                <div
                    class="flex items-start gap-3.5 border-b border-emerald-200 bg-emerald-50 p-5"
                >
                    <CheckCircle2
                        class="mt-0.5 h-6 w-6 shrink-0 text-emerald-700"
                    />
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="text-sm font-bold uppercase tracking-wide text-emerald-950 sm:text-base"
                            >
                                SURAT ASLI &amp; TERDAFTAR DI BUKU AGENDA DESA
                            </h2>
                            <StatusBadge
                                status="terbit"
                                label="Sah & Terbit"
                            />
                        </div>
                        <p class="text-xs leading-relaxed text-emerald-800">
                            {{ banner_deskripsi }}
                        </p>
                    </div>
                </div>

                <!-- 5 Atribut Resmi Verifikasi QR (Patuh UU PDP) -->
                <dl class="divide-y divide-slate-100 p-6 text-sm">
                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">1. Nomor Surat Resmi</dt>
                        <dd
                            data-testid="verifikasi-nomor-surat"
                            class="font-semibold tabular-nums text-slate-900"
                        >
                            {{ dokumen.nomor_surat }}
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">2. Jenis Surat</dt>
                        <dd
                            data-testid="verifikasi-jenis-surat"
                            class="font-semibold text-slate-900"
                        >
                            {{ dokumen.jenis_surat }}
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">3. Tanggal Terbit</dt>
                        <dd
                            data-testid="verifikasi-tanggal-terbit"
                            class="font-medium tabular-nums text-slate-800"
                        >
                            {{ formatDateId(dokumen.tanggal_terbit) }}
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">
                            4. Nama Warga (Tersamarkan)
                        </dt>
                        <dd
                            data-testid="verifikasi-nama-warga"
                            class="font-semibold text-slate-900"
                        >
                            {{ dokumen.nama_warga_tersamarkan }}
                        </dd>
                    </div>

                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">
                            5. Pejabat Penandatangan
                        </dt>
                        <dd
                            data-testid="verifikasi-pejabat-penandatangan"
                            class="font-medium text-slate-900 sm:text-right"
                        >
                            {{ dokumen.pejabat_penandatangan }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- SKENARIO 2: SURAT DIBATALKAN -->
            <div
                v-else-if="status_verifikasi === 'dibatalkan' && dokumen"
                data-testid="banner-verifikasi-dibatalkan"
                class="overflow-hidden rounded-lg border border-red-200 bg-white"
            >
                <div
                    class="flex items-start gap-3.5 border-b border-red-200 bg-red-50 p-5"
                >
                    <AlertTriangle
                        class="mt-0.5 h-6 w-6 shrink-0 text-red-700"
                    />
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="text-sm font-bold uppercase tracking-wide text-red-950 sm:text-base"
                            >
                                SURAT DIBATALKAN &amp; TIDAK BERLAKU
                            </h2>
                            <StatusBadge
                                status="dibatalkan"
                                label="Surat Dibatalkan"
                            />
                        </div>
                        <p class="text-xs leading-relaxed text-red-800">
                            {{ banner_deskripsi }}
                        </p>
                    </div>
                </div>

                <dl class="divide-y divide-slate-100 p-6 text-sm">
                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">Nomor Surat</dt>
                        <dd class="font-semibold tabular-nums text-slate-900">
                            {{ dokumen.nomor_surat }}
                        </dd>
                    </div>
                    <div
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">Jenis Surat</dt>
                        <dd class="font-medium text-slate-800">
                            {{ dokumen.jenis_surat }}
                        </dd>
                    </div>
                    <div
                        v-if="dokumen.alasan_pembatalan"
                        class="flex flex-col justify-between gap-1 py-3 sm:flex-row sm:items-center"
                    >
                        <dt class="text-slate-500">Keterangan Pembatalan</dt>
                        <dd class="font-medium text-red-700">
                            {{ dokumen.alasan_pembatalan }}
                        </dd>
                    </div>
                </dl>
            </div>

            <!-- SKENARIO 3: TOKEN TIDAK TERDAFTAR -->
            <div
                v-else
                data-testid="banner-verifikasi-tidak-terdaftar"
                class="rounded-lg border border-red-200 bg-red-50 p-6"
            >
                <div class="flex items-start gap-3.5">
                    <ShieldAlert
                        class="mt-0.5 h-6 w-6 shrink-0 text-red-700"
                    />
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="text-sm font-bold uppercase tracking-wide text-red-950 sm:text-base"
                            >
                                DOKUMEN TIDAK TERDAFTAR PADA BUKU AGENDA DESA
                            </h2>
                            <StatusBadge
                                status="ditolak"
                                label="Tidak Terdaftar"
                            />
                        </div>
                        <p class="text-xs leading-relaxed text-red-800">
                            {{
                                banner_deskripsi ||
                                'Token QR Code tidak ditemukan pada Buku Agenda Surat Keluar Pemerintah Desa.'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
