<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toPng } from 'html-to-image';
import { Download, Landmark } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import { useNotification } from '@/Composables/useNotification';
import { formatPercentage, formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    dokumen: {
        type: Object,
        required: true,
    },
    ringkasan: {
        type: Object,
        default: () => ({}),
    },
    profilDesa: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['downloaded']);

const page = usePage();
const notify = useNotification();
const canvasRef = ref(null);
const isExporting = ref(false);

const desaInfo = computed(() => {
    const src = props.profilDesa || page.props?.profil_desa || {};
    return {
        nama_desa: src.nama_desa || 'Tamarenja',
        kecamatan: src.kecamatan || 'Sindue Tobata',
        kabupaten: src.kabupaten || 'Donggala',
        provinsi: src.provinsi || 'Sulawesi Tengah',
        nama_kepala_desa: src.nama_kepala_desa || 'Kepala Desa Tamarenja',
        logo_url: src.logo_url || null,
    };
});

const rincianList = computed(() =>
    Array.isArray(props.dokumen?.rincian) ? props.dokumen.rincian : [],
);

const pendapatanRows = computed(() =>
    rincianList.value.filter((r) => r.kelompok === 'pendapatan'),
);

const belanjaRows = computed(() =>
    rincianList.value.filter((r) => r.kelompok === 'belanja'),
);

const pembiayaanRows = computed(() =>
    rincianList.value.filter(
        (r) =>
            r.kelompok === 'pembiayaan_penerimaan' ||
            r.kelompok === 'pembiayaan_pengeluaran',
    ),
);

const jenisBadgeText = computed(() =>
    props.dokumen?.jenis_dokumen === 'perubahan'
        ? 'APBDes PERUBAHAN (P-APBDes)'
        : 'APBDes AWAL (MURNI)',
);

async function downloadBannerPng() {
    if (!canvasRef.value || isExporting.value) {
        return;
    }

    isExporting.value = true;
    try {
        const dataUrl = await toPng(canvasRef.value, {
            width: 1920,
            height: 1080,
            pixelRatio: 2,
            cacheBust: true,
            backgroundColor: '#f8fafc',
        });

        const tahun = props.dokumen?.tahun_anggaran || new Date().getFullYear();
        const jenis =
            props.dokumen?.jenis_dokumen === 'perubahan' ? 'Perubahan' : 'Murni';
        const filename = `Baliho_APBDes_${desaInfo.value.nama_desa}_TA_${tahun}_${jenis}.png`;

        const link = document.createElement('a');
        link.download = filename;
        link.href = dataUrl;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        notify.success(
            'Baliho APBDes berhasil diunduh dalam resolusi cetak (3840 × 2160 px).',
        );
        emit('downloaded', { filename, dataUrl });
    } catch {
        notify.error('Gagal mengekspor gambar baliho APBDes. Silakan coba lagi.');
    } finally {
        isExporting.value = false;
    }
}

defineExpose({
    downloadBannerPng,
});
</script>

<template>
    <div class="space-y-4">
        <!-- Toolbar Unduh Baliho -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4">
            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    Pratinjau Kanvas Baliho Infografis APBDes (16:9)
                </h2>
                <p class="text-xs text-slate-600">
                    Dimensi kanvas baku <span class="font-semibold tabular-nums">1920 × 1080 px</span> diekspor dengan <span class="font-semibold tabular-nums">pixelRatio: 2</span> menghasilkan gambar PNG tajam <span class="font-semibold tabular-nums">3840 × 2160 px</span> siap cetak spanduk.
                </p>
            </div>

            <BaseButton
                variant="primary"
                :loading="isExporting"
                data-testid="download-apbdes-banner-btn"
                @click="downloadBannerPng"
            >
                <Download class="mr-1.5 h-4 w-4" />
                <span>Unduh Baliho PNG (3840 × 2160 px)</span>
            </BaseButton>
        </div>

        <!-- Wrapper Pratinjau Responsif -->
        <div class="overflow-x-auto rounded-lg border border-slate-300 bg-slate-200/70 p-4">
            <!-- Kanvas Tetap 1920 x 1080 px -->
            <div
                ref="canvasRef"
                data-testid="apbdes-banner-canvas"
                style="width: 1920px; height: 1080px;"
                class="flex flex-col justify-between overflow-hidden rounded-lg border-4 border-blue-900 bg-slate-50 p-10 text-slate-900 shadow-sm"
            >
                <!-- HEADER BALIHO -->
                <div class="flex items-center justify-between border-b-4 border-blue-800 bg-blue-900 px-8 py-6 text-white rounded-md">
                    <div class="flex items-center gap-5">
                        <div class="flex h-20 w-20 items-center justify-center rounded-lg bg-white/10 border border-white/20">
                            <img
                                v-if="desaInfo.logo_url"
                                :src="desaInfo.logo_url"
                                alt="Logo Desa"
                                class="h-16 w-16 object-contain"
                            />
                            <Landmark v-else class="h-12 w-12 text-white" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-widest text-blue-200">
                                Pemerintah Kabupaten {{ desaInfo.kabupaten }} &bull; Kecamatan {{ desaInfo.kecamatan }}
                            </p>
                            <h1 class="text-3xl font-bold uppercase tracking-tight">
                                INFOGRAFIS APBDes DESA {{ desaInfo.nama_desa }} TAHUN ANGGARAN {{ dokumen?.tahun_anggaran }}
                            </h1>
                            <p class="mt-1 text-sm text-blue-100">
                                Transparansi Pengelolaan Keuangan Desa &mdash;
                                <span v-if="dokumen?.nomor_perdes">Perdes Nomor: {{ dokumen.nomor_perdes }}</span>
                                <span v-else>Dokumen Resmi Pemerintah Desa</span>
                            </p>
                        </div>
                    </div>

                    <div class="text-right">
                        <span class="inline-block rounded-md bg-amber-400 px-4 py-1.5 text-sm font-bold uppercase tracking-wider text-slate-950">
                            {{ jenisBadgeText }}
                        </span>
                        <p class="mt-2 text-xs text-blue-200">
                            Kepala Desa: <strong class="text-white">{{ desaInfo.nama_kepala_desa }}</strong>
                        </p>
                    </div>
                </div>

                <!-- 3 RINGKASAN METRIK UTAMA -->
                <div class="grid grid-cols-3 gap-6 my-4">
                    <div class="rounded-lg border-2 border-blue-200 bg-white px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Total Pendapatan Desa
                        </p>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold tabular-nums text-blue-800">
                                {{ formatRupiah(ringkasan?.total_pagu_pendapatan || 0) }}
                            </span>
                            <span class="text-sm font-semibold tabular-nums text-emerald-700">
                                Realisasi: {{ formatRupiah(ringkasan?.total_realisasi_pendapatan || 0) }}
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg border-2 border-emerald-200 bg-white px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Total Belanja Desa (5 Bidang)
                        </p>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold tabular-nums text-slate-900">
                                {{ formatRupiah(ringkasan?.total_pagu_belanja || 0) }}
                            </span>
                            <span class="text-sm font-semibold tabular-nums text-emerald-700">
                                Realisasi: {{ formatRupiah(ringkasan?.total_realisasi_belanja || 0) }}
                                ({{ formatPercentage(ringkasan?.persentase_serapan_belanja || 0) }})
                            </span>
                        </div>
                    </div>

                    <div class="rounded-lg border-2 border-slate-200 bg-white px-6 py-4">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                            Pembiayaan Netto &amp; Sisa Anggaran
                        </p>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold tabular-nums text-slate-800">
                                {{ formatRupiah(ringkasan?.pembiayaan_netto_pagu || 0) }}
                            </span>
                            <span class="text-sm font-semibold tabular-nums text-blue-700">
                                Sisa Belanja: {{ formatRupiah(ringkasan?.sisa_anggaran_belanja || 0) }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- ISI 2 KOLOM: PENDAPATAN (KIRI) & 5 BIDANG BELANJA + PEMBIAYAAN (KANAN) -->
                <div class="grid flex-1 grid-cols-2 gap-8 overflow-hidden">
                    <!-- Kolom Kiri: 7 Pos Pendapatan Desa -->
                    <div class="flex flex-col justify-between rounded-lg border border-slate-300 bg-white p-6">
                        <div>
                            <h2 class="border-b-2 border-blue-700 pb-2 text-lg font-bold uppercase text-blue-900">
                                I. Rincian Pendapatan Desa (7 Pos Baku)
                            </h2>
                            <table class="mt-3 w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 text-xs font-bold uppercase text-slate-500">
                                        <th class="py-2 text-left">Sumber Pendapatan</th>
                                        <th class="py-2 text-right">Pagu Anggaran</th>
                                        <th class="py-2 text-right">Realisasi</th>
                                        <th class="py-2 text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="item in pendapatanRows" :key="item.kode_pos">
                                        <td class="py-2.5 font-medium text-slate-800">
                                            {{ item.nama_pos }}
                                        </td>
                                        <td class="py-2.5 text-right tabular-nums text-slate-700">
                                            {{ formatRupiah(item.pagu_anggaran) }}
                                        </td>
                                        <td class="py-2.5 text-right font-semibold tabular-nums text-emerald-700">
                                            {{ formatRupiah(item.total_realisasi) }}
                                        </td>
                                        <td class="py-2.5 text-right tabular-nums text-slate-600">
                                            {{ formatPercentage(item.persentase || 0) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pembiayaan Desa -->
                        <div class="mt-4 border-t border-slate-200 pt-4">
                            <h3 class="text-sm font-bold uppercase text-slate-700">
                                III. Rincian Pembiayaan Desa
                            </h3>
                            <div class="mt-2 grid grid-cols-2 gap-4">
                                <div
                                    v-for="item in pembiayaanRows"
                                    :key="item.kode_pos"
                                    class="rounded border border-slate-200 bg-slate-50 px-4 py-2.5"
                                >
                                    <p class="text-xs font-semibold text-slate-600">
                                        {{ item.nama_pos }}
                                    </p>
                                    <p class="mt-1 text-sm font-bold tabular-nums text-slate-900">
                                        Pagu: {{ formatRupiah(item.pagu_anggaran) }}
                                    </p>
                                    <p class="text-xs font-medium tabular-nums text-emerald-700">
                                        Realisasi: {{ formatRupiah(item.total_realisasi) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Kolom Kanan: 5 Bidang Belanja Desa -->
                    <div class="flex flex-col justify-between rounded-lg border border-slate-300 bg-white p-6">
                        <div>
                            <h2 class="border-b-2 border-emerald-700 pb-2 text-lg font-bold uppercase text-emerald-900">
                                II. Rincian Belanja Desa (5 Bidang Utama)
                            </h2>

                            <div class="mt-4 space-y-4">
                                <div
                                    v-for="item in belanjaRows"
                                    :key="item.kode_pos"
                                    class="rounded-md border border-slate-200 bg-slate-50/70 p-3.5"
                                >
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-bold text-slate-900">
                                            {{ item.nama_pos }}
                                        </span>
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-xs font-bold tabular-nums text-emerald-800">
                                            Serapan: {{ formatPercentage(item.persentase || 0) }}
                                        </span>
                                    </div>
                                    <div class="mt-1.5 flex items-center justify-between text-xs tabular-nums">
                                        <span class="font-medium text-slate-600">
                                            Pagu: <strong class="text-slate-900">{{ formatRupiah(item.pagu_anggaran) }}</strong>
                                        </span>
                                        <span class="font-medium text-emerald-700">
                                            Realisasi: <strong>{{ formatRupiah(item.total_realisasi) }}</strong>
                                        </span>
                                    </div>
                                    <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
                                        <div
                                            class="h-full rounded-full bg-emerald-600"
                                            :style="{ width: `${Math.min(100, Number(item.persentase || 0))}%` }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 rounded-md bg-blue-50 px-4 py-3 text-xs text-blue-900 border border-blue-200">
                            <strong>Keterangan Akuntabilitas:</strong> Publikasi Baliho APBDes ini diterbitkan secara resmi melalui Sistem Informasi &amp; Website Operasional Pemerintah Desa {{ desaInfo.nama_desa }} sebagai wujud keterbukaan informasi publik.
                        </div>
                    </div>
                </div>

                <!-- FOOTER BALIHO -->
                <div class="mt-4 flex items-center justify-between border-t border-slate-300 pt-3 text-xs text-slate-600">
                    <span>Pemerintah Desa {{ desaInfo.nama_desa }} &mdash; Kecamatan {{ desaInfo.kecamatan }}, Kabupaten {{ desaInfo.kabupaten }}</span>
                    <span class="font-semibold">Mari Bersama Mengawasi &amp; Membangun Desa {{ desaInfo.nama_desa }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
