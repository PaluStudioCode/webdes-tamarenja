<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    BarElement,
    CategoryScale,
    Chart as ChartJS,
    Legend,
    LinearScale,
    Title,
    Tooltip,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import {
    CopyPlus,
    FileSpreadsheet,
    Image as ImageIcon,
    Lock,
    Printer,
    Save,
    TrendingUp,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import ApbdesBannerCanvas from '@/Components/ApbdesBannerCanvas.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatPercentage, formatRupiah } from '@/Utils/formatters';

ChartJS.register(
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
);

const props = defineProps({
    tahun_anggaran: {
        type: Number,
        required: true,
    },
    dokumen_aktif: {
        type: Object,
        required: true,
    },
    dokumen_murni: {
        type: Object,
        required: true,
    },
    dokumen_perubahan: {
        type: Object,
        default: null,
    },
    has_perubahan: {
        type: Boolean,
        default: false,
    },
    ringkasan: {
        type: Object,
        required: true,
    },
    perbandingan_lintas_tahun: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const activeTab = ref('rincian');

const tabs = [
    { key: 'rincian', label: 'Rincian Pos Anggaran' },
    { key: 'grafik', label: 'Grafik & Perbandingan Lintas Tahun' },
    { key: 'baliho', label: 'Generator Baliho APBDes' },
];

// Pilihan jenis dokumen yang sedang ditampilkan di layar (murni vs perubahan)
const selectedJenisDokumen = ref(
    props.dokumen_aktif?.jenis_dokumen || 'murni',
);

watch(
    () => props.dokumen_aktif?.jenis_dokumen,
    (newVal) => {
        if (newVal) {
            selectedJenisDokumen.value = newVal;
        }
    },
);

const currentDocument = computed(() => {
    if (
        selectedJenisDokumen.value === 'perubahan' &&
        props.dokumen_perubahan
    ) {
        return props.dokumen_perubahan;
    }
    if (selectedJenisDokumen.value === 'murni' && props.dokumen_murni) {
        return props.dokumen_murni;
    }
    return props.dokumen_aktif;
});

const isDocumentLocked = computed(() =>
    Boolean(currentDocument.value?.is_terkunci),
);

const canEditCurrentDocument = computed(
    () => isOperator.value && !isDocumentLocked.value,
);

// State lokal form rincian pos anggaran agar dapat diedit secara responsif
const formMetadata = reactive({
    nomor_perdes: currentDocument.value?.nomor_perdes || '',
    tanggal_penetapan: currentDocument.value?.tanggal_penetapan || '',
});

const editableRows = ref([]);
const isSaving = ref(false);

function syncLocalRowsFromDocument(doc) {
    formMetadata.nomor_perdes = doc?.nomor_perdes || '';
    formMetadata.tanggal_penetapan = doc?.tanggal_penetapan || '';
    editableRows.value = (doc?.rincian || []).map((row) => ({
        id: row.id,
        kode_pos: row.kode_pos,
        nama_pos: row.nama_pos,
        kelompok: row.kelompok,
        is_otomatis_modul_1: Boolean(row.is_otomatis_modul_1),
        pagu_anggaran: Number(row.pagu_anggaran || 0),
        realisasi_tahap_1: Number(row.realisasi_tahap_1 || 0),
        realisasi_tahap_2: Number(row.realisasi_tahap_2 || 0),
        realisasi_tahap_3: Number(row.realisasi_tahap_3 || 0),
    }));
}

syncLocalRowsFromDocument(currentDocument.value);

watch(
    currentDocument,
    (newDoc) => {
        syncLocalRowsFromDocument(newDoc);
    },
    { deep: true },
);

function switchJenisDokumen(jenis) {
    if (jenis === 'perubahan' && !props.has_perubahan) {
        return;
    }
    selectedJenisDokumen.value = jenis;
}

function getRowTotalRealisasi(row) {
    return (
        Number(row.realisasi_tahap_1 || 0) +
        Number(row.realisasi_tahap_2 || 0) +
        Number(row.realisasi_tahap_3 || 0)
    );
}

function getRowPercentage(row) {
    const pagu = Number(row.pagu_anggaran || 0);
    if (pagu <= 0) {
        return 0;
    }
    return Number(((getRowTotalRealisasi(row) / pagu) * 100).toFixed(1));
}

function isRowReadOnly(row) {
    return !canEditCurrentDocument.value || Boolean(row.is_otomatis_modul_1);
}

const groupedRows = computed(() => {
    const groups = [
        {
            key: 'pendapatan',
            roman: 'I',
            title: 'Pendapatan Desa (7 Pos Baku)',
            note: 'Realisasi pendapatan diizinkan melampaui target pagu (> 100%).',
        },
        {
            key: 'belanja',
            roman: 'II',
            title: 'Belanja Desa (5 Bidang Utama)',
            note: 'Akumulasi Realisasi Tahap I + II + III tidak boleh melebihi Pagu Anggaran.',
        },
        {
            key: 'pembiayaan_penerimaan',
            roman: 'III.A',
            title: 'Penerimaan Pembiayaan Desa',
            note: 'SiLPA Tahun Sebelumnya dan penerimaan pembiayaan lainnya.',
        },
        {
            key: 'pembiayaan_pengeluaran',
            roman: 'III.B',
            title: 'Pengeluaran Pembiayaan Desa',
            note: 'Penyertaan Modal BUMDes tidak boleh melebihi Pagu Anggaran.',
        },
    ];

    return groups.map((g) => ({
        ...g,
        rows: editableRows.value.filter((r) => r.kelompok === g.key),
    }));
});

async function handleCreatePerubahan() {
    const ok = await confirm.ask({
        title: `Buat APBDes Perubahan TA ${props.tahun_anggaran}?`,
        message: `Seluruh rincian pagu dan realisasi dari APBDes Murni TA ${props.tahun_anggaran} akan disalin ke dokumen P-APBDes (Perubahan), dan dokumen APBDes Murni akan dikunci permanen sebagai arsip (Clone & Freeze).`,
        confirmText: 'Ya, Buat P-APBDes & Kunci Murni',
        cancelText: 'Batal',
        variant: 'warning',
    });

    if (!ok) {
        return;
    }

    router.post(
        '/panel/apbdes/buat-perubahan',
        {
            tahun_anggaran: props.tahun_anggaran,
        },
        {
            preserveScroll: true,
        },
    );
}

function saveApbdesDocument() {
    if (!canEditCurrentDocument.value || !currentDocument.value?.id) {
        return;
    }

    isSaving.value = true;
    const payloadRincian = editableRows.value
        .filter((r) => !r.is_otomatis_modul_1)
        .map((r) => ({
            id: r.id,
            kode_pos: r.kode_pos,
            pagu_anggaran: Number(r.pagu_anggaran || 0),
            realisasi_tahap_1: Number(r.realisasi_tahap_1 || 0),
            realisasi_tahap_2: Number(r.realisasi_tahap_2 || 0),
            realisasi_tahap_3: Number(r.realisasi_tahap_3 || 0),
        }));

    router.put(
        `/panel/apbdes/${currentDocument.value.id}`,
        {
            nomor_perdes: formMetadata.nomor_perdes,
            tanggal_penetapan: formMetadata.tanggal_penetapan || null,
            rincian: payloadRincian,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSaving.value = false;
            },
        },
    );
}

// Konfigurasi Grafik 5 Bidang Belanja (Tab 2)
const bidangBelanjaChartData = computed(() => {
    const belanja = (currentDocument.value?.rincian || []).filter(
        (r) => r.kelompok === 'belanja',
    );
    return {
        labels: belanja.map((b) =>
            String(b.nama_pos || '').replace(/^Bidang \d+:\s*/i, ''),
        ),
        datasets: [
            {
                label: 'Pagu Anggaran (Rp)',
                backgroundColor: '#1d4ed8',
                borderRadius: 4,
                data: belanja.map((b) => Number(b.pagu_anggaran || 0)),
            },
            {
                label: 'Total Realisasi (Rp)',
                backgroundColor: '#059669',
                borderRadius: 4,
                data: belanja.map((b) => Number(b.total_realisasi || 0)),
            },
        ],
    };
});

// Konfigurasi Grafik Perbandingan Lintas Tahun (Tab 2)
const multiYearChartData = computed(() => {
    const items = props.perbandingan_lintas_tahun || [];
    return {
        labels: items.map(
            (i) =>
                `TA ${i.tahun_anggaran} (${i.jenis_dokumen === 'perubahan' ? 'Perubahan' : 'Murni'})`,
        ),
        datasets: [
            {
                label: 'Total Pagu Belanja (Rp)',
                backgroundColor: '#1d4ed8',
                borderRadius: 4,
                data: items.map((i) => Number(i.total_pagu_belanja || 0)),
            },
            {
                label: 'Total Realisasi Belanja (Rp)',
                backgroundColor: '#059669',
                borderRadius: 4,
                data: items.map((i) => Number(i.total_realisasi_belanja || 0)),
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'top',
        },
    },
};
</script>

<template>
    <Head :title="`Transparansi & Realisasi APBDes TA ${tahun_anggaran}`" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                :title="`Anggaran & Realisasi APBDes TA ${tahun_anggaran}`"
                subtitle="Pengelolaan 14 pos anggaran baku, mekanisme Clone & Freeze P-APBDes, grafik serapan multi-tahun, dan generator baliho infografis."
            >
                <template #actions>
                    <a
                        :href="`/panel/apbdes/${currentDocument?.id}/cetak-pdf`"
                        data-testid="btn-cetak-pdf-apbdes"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Printer class="mr-1.5 h-4 w-4" />
                        <span>Cetak Realisasi F4</span>
                    </a>

                    <BaseButton
                        v-if="isOperator && !has_perubahan"
                        variant="primary"
                        data-testid="btn-buat-perubahan"
                        @click="handleCreatePerubahan"
                    >
                        <CopyPlus class="mr-1.5 h-4 w-4" />
                        <span>Buat APBDes Perubahan</span>
                    </BaseButton>
                </template>
            </PageHeader>

            <!-- 4 Kartu Ringkasan APBDes -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total Pendapatan Desa"
                    :value="formatRupiah(ringkasan?.total_pagu_pendapatan || 0)"
                    :subtext="`Realisasi ${formatRupiah(ringkasan?.total_realisasi_pendapatan || 0)}`"
                    :icon="FileSpreadsheet"
                />
                <StatCard
                    label="Total Belanja (5 Bidang)"
                    :value="formatRupiah(ringkasan?.total_pagu_belanja || 0)"
                    :subtext="`Realisasi ${formatRupiah(ringkasan?.total_realisasi_belanja || 0)} (${formatPercentage(ringkasan?.persentase_serapan_belanja || 0)})`"
                    :icon="TrendingUp"
                />
                <StatCard
                    label="Pembiayaan Netto"
                    :value="formatRupiah(ringkasan?.pembiayaan_netto_pagu || 0)"
                    :subtext="`Realisasi Netto ${formatRupiah(ringkasan?.pembiayaan_netto_realisasi || 0)}`"
                />
                <StatCard
                    label="Sisa Anggaran Belanja"
                    :value="formatRupiah(ringkasan?.sisa_anggaran_belanja || 0)"
                    :subtext="
                        ringkasan?.is_pagu_seimbang
                            ? 'Struktur Pagu APBDes Seimbang (Selisih Rp 0)'
                            : `Selisih Keseimbangan Pagu: ${formatRupiah(ringkasan?.selisih_keseimbangan_pagu || 0)}`
                    "
                />
            </div>

            <!-- Navigasi 3 Tab Kontekstual -->
            <ContextualTabs v-model="activeTab" :tabs="tabs" />

            <!-- TAB 1: RINCIAN POS ANGGARAN (14 POS BAKU) -->
            <div v-if="activeTab === 'rincian'" class="space-y-6">
                <!-- Bar Pemilih Jenis Dokumen (Murni vs Perubahan) & Status Kunci -->
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            data-testid="switch-dokumen-murni"
                            :class="[
                                'inline-flex h-9 items-center gap-2 rounded-md px-3.5 text-sm font-medium transition-colors',
                                selectedJenisDokumen === 'murni'
                                    ? 'bg-blue-700 text-white'
                                    : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                            ]"
                            @click="switchJenisDokumen('murni')"
                        >
                            <Lock
                                v-if="dokumen_murni?.is_terkunci"
                                class="h-3.5 w-3.5"
                            />
                            <span>APBDes Awal (Murni)</span>
                        </button>

                        <button
                            v-if="has_perubahan"
                            type="button"
                            data-testid="switch-dokumen-perubahan"
                            :class="[
                                'inline-flex h-9 items-center gap-2 rounded-md px-3.5 text-sm font-medium transition-colors',
                                selectedJenisDokumen === 'perubahan'
                                    ? 'bg-blue-700 text-white'
                                    : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50',
                            ]"
                            @click="switchJenisDokumen('perubahan')"
                        >
                            <span>APBDes Perubahan (P-APBDes)</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <StatusBadge
                            v-if="isDocumentLocked"
                            status="apbdes_murni_terkunci"
                            label="APBDes Murni - Arsip Terkunci"
                        />
                        <StatusBadge
                            v-else-if="currentDocument?.jenis_dokumen === 'perubahan'"
                            status="apbdes_perubahan"
                            label="APBDes Perubahan Aktif"
                        />
                        <StatusBadge
                            v-else
                            status="aktif"
                            label="APBDes Murni Aktif"
                        />
                    </div>
                </div>

                <!-- Callout Kontekstual: Dokumen Murni Terkunci atau Aturan Sinkronisasi Bidang 2 -->
                <BaseCallout
                    v-if="isDocumentLocked"
                    variant="warning"
                    title="Dokumen APBDes Murni Telah Dikunci Permanen (Clone & Freeze)"
                >
                    Tahun Anggaran {{ tahun_anggaran }} telah memiliki dokumen
                    <strong>APBDes Perubahan (P-APBDes)</strong>. Dokumen APBDes Murni ini disimpan sebagai arsip historis yang tidak dapat diubah kembali.
                </BaseCallout>

                <BaseCallout
                    variant="info"
                    title="Sinkronisasi Otomatis Bidang 2 & Keseimbangan Anggaran"
                >
                    Baris <strong>Bidang 2: Pelaksanaan Pembangunan Desa (Komponen Proyek Fisik)</strong> dihitung dan dikunci otomatis dari akumulasi proyek fisik pada Modul 1.
                    <span v-if="!ringkasan?.is_pagu_seimbang" class="font-semibold text-amber-800">
                        Perhatian: Terdapat selisih keseimbangan pagu sebesar {{ formatRupiah(ringkasan?.selisih_keseimbangan_pagu || 0) }} (Pendapatan &minus; Belanja + Pembiayaan Netto).
                    </span>
                </BaseCallout>

                <!-- Form Metadata Perdes & Tabel 14 Pos Baku -->
                <form
                    data-testid="form-rincian-apbdes"
                    class="space-y-6"
                    @submit.prevent="saveApbdesDocument"
                >
                    <div class="rounded-lg border border-slate-200 bg-white p-5">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label
                                    for="nomor_perdes"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Nomor Peraturan Desa (Perdes)
                                </label>
                                <input
                                    id="nomor_perdes"
                                    v-model="formMetadata.nomor_perdes"
                                    type="text"
                                    data-testid="input-nomor-perdes"
                                    placeholder="Contoh: Perdes Nomor 03 Tahun 2026"
                                    :disabled="!canEditCurrentDocument"
                                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    for="tanggal_penetapan"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Tanggal Penetapan Perdes
                                </label>
                                <input
                                    id="tanggal_penetapan"
                                    v-model="formMetadata.tanggal_penetapan"
                                    type="date"
                                    data-testid="input-tanggal-penetapan"
                                    :disabled="!canEditCurrentDocument"
                                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Tabel 14 Pos Baku per Kelompok -->
                    <div
                        v-for="group in groupedRows"
                        :key="group.key"
                        class="overflow-hidden rounded-lg border border-slate-200 bg-white"
                    >
                        <div class="border-b border-slate-200 bg-slate-50 px-5 py-3.5">
                            <h3 class="text-sm font-bold uppercase tracking-wide text-slate-900">
                                {{ group.roman }}. {{ group.title }}
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{ group.note }}
                            </p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full border-collapse text-left text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-50/60 text-xs font-medium uppercase tracking-wider text-slate-500">
                                        <th class="px-4 py-3">Uraian Pos Anggaran</th>
                                        <th class="px-3 py-3 text-right">Pagu Anggaran (Rp)</th>
                                        <th class="px-3 py-3 text-right">Tahap I (Rp)</th>
                                        <th class="px-3 py-3 text-right">Tahap II (Rp)</th>
                                        <th class="px-3 py-3 text-right">Tahap III (Rp)</th>
                                        <th class="px-4 py-3 text-right">Total Realisasi</th>
                                        <th class="px-3 py-3 text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <tr
                                        v-for="row in group.rows"
                                        :key="row.kode_pos"
                                        :data-testid="`row-pos-${row.kode_pos}`"
                                        :class="
                                            row.is_otomatis_modul_1
                                                ? 'bg-blue-50/40'
                                                : 'hover:bg-slate-50/60'
                                        "
                                    >
                                        <td class="px-4 py-3 align-middle">
                                            <div class="font-medium text-slate-900">
                                                {{ row.nama_pos }}
                                            </div>
                                            <span
                                                v-if="row.is_otomatis_modul_1"
                                                data-testid="badge-otomatis-modul-1"
                                                class="mt-1 inline-flex items-center gap-1 rounded border border-blue-200 bg-blue-50 px-2 py-0.5 text-[11px] font-medium text-blue-700"
                                            >
                                                <Lock class="h-3 w-3" />
                                                <span>Otomatis Modul 1 (Read-Only)</span>
                                            </span>
                                        </td>

                                        <td class="w-44 px-3 py-2.5 align-middle">
                                            <CurrencyInput
                                                v-model="row.pagu_anggaran"
                                                :disabled="isRowReadOnly(row)"
                                            />
                                        </td>

                                        <td class="w-40 px-3 py-2.5 align-middle">
                                            <CurrencyInput
                                                v-model="row.realisasi_tahap_1"
                                                :disabled="isRowReadOnly(row)"
                                            />
                                        </td>

                                        <td class="w-40 px-3 py-2.5 align-middle">
                                            <CurrencyInput
                                                v-model="row.realisasi_tahap_2"
                                                :disabled="isRowReadOnly(row)"
                                            />
                                        </td>

                                        <td class="w-40 px-3 py-2.5 align-middle">
                                            <CurrencyInput
                                                v-model="row.realisasi_tahap_3"
                                                :disabled="isRowReadOnly(row)"
                                            />
                                        </td>

                                        <td class="px-4 py-3 text-right font-semibold tabular-nums text-emerald-700 align-middle">
                                            {{ formatRupiah(getRowTotalRealisasi(row)) }}
                                        </td>

                                        <td class="px-3 py-3 text-right font-medium tabular-nums text-slate-700 align-middle">
                                            {{ formatPercentage(getRowPercentage(row)) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tombol Simpan Rincian APBDes (Hanya Tampil bagi Operator & Dokumen Tidak Terkunci) -->
                    <div
                        v-if="canEditCurrentDocument"
                        class="flex items-center justify-end"
                    >
                        <BaseButton
                            type="submit"
                            variant="primary"
                            :loading="isSaving"
                            data-testid="btn-save-apbdes"
                        >
                            <Save class="mr-1.5 h-4 w-4" />
                            <span>Simpan Rincian APBDes</span>
                        </BaseButton>
                    </div>
                </form>
            </div>

            <!-- TAB 2: GRAFIK & PERBANDINGAN LINTAS TAHUN -->
            <div v-else-if="activeTab === 'grafik'" class="space-y-6">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div class="rounded-lg border border-slate-200 bg-white p-6">
                        <h2 class="text-base font-semibold text-slate-900">
                            Serapan 5 Bidang Belanja Desa TA {{ tahun_anggaran }}
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Perbandingan Pagu Anggaran dan Realisasi pada dokumen {{ currentDocument?.jenis_label }}.
                        </p>
                        <div class="mt-4 h-72">
                            <Bar
                                :data="bidangBelanjaChartData"
                                :options="chartOptions"
                            />
                        </div>
                    </div>

                    <div class="rounded-lg border border-slate-200 bg-white p-6">
                        <h2 class="text-base font-semibold text-slate-900">
                            Perbandingan Belanja APBDes Lintas Tahun
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Tren Pagu dan Realisasi Belanja Desa antar-tahun anggaran aktif publik.
                        </p>
                        <div class="mt-4 h-72">
                            <Bar
                                :data="multiYearChartData"
                                :options="chartOptions"
                            />
                        </div>
                    </div>
                </div>

                <!-- Tabel Ringkasan Perbandingan Lintas Tahun -->
                <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-3.5">
                        <h3 class="text-sm font-semibold text-slate-900">
                            Tabel Rekapitulasi APBDes Lintas Tahun Anggaran
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse text-left text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 text-xs font-medium uppercase tracking-wider text-slate-500">
                                    <th class="px-4 py-3">Tahun Anggaran</th>
                                    <th class="px-4 py-3">Dokumen Aktif</th>
                                    <th class="px-4 py-3 text-right">Pagu Pendapatan</th>
                                    <th class="px-4 py-3 text-right">Realisasi Pendapatan</th>
                                    <th class="px-4 py-3 text-right">Pagu Belanja</th>
                                    <th class="px-4 py-3 text-right">Realisasi Belanja</th>
                                    <th class="px-4 py-3 text-right">Serapan Belanja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 tabular-nums">
                                <tr
                                    v-for="item in perbandingan_lintas_tahun"
                                    :key="item.id"
                                    class="hover:bg-slate-50"
                                >
                                    <td class="px-4 py-3 font-semibold text-slate-900">
                                        TA {{ item.tahun_anggaran }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <StatusBadge
                                            :status="item.jenis_dokumen === 'perubahan' ? 'apbdes_perubahan' : 'aktif'"
                                            :label="item.jenis_dokumen === 'perubahan' ? 'APBDes Perubahan' : 'APBDes Murni'"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">
                                        {{ formatRupiah(item.total_pagu_pendapatan) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-700">
                                        {{ formatRupiah(item.total_realisasi_pendapatan) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-700">
                                        {{ formatRupiah(item.total_pagu_belanja) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-700">
                                        {{ formatRupiah(item.total_realisasi_belanja) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-700">
                                        {{ formatPercentage(item.persentase_serapan_belanja) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- TAB 3: GENERATOR BALIHO APBDES (1920 x 1080 -> 3840 x 2160 PNG) -->
            <div v-else-if="activeTab === 'baliho'">
                <ApbdesBannerCanvas
                    :dokumen="currentDocument"
                    :ringkasan="ringkasan"
                />
            </div>
        </div>
    </AppLayout>
</template>
