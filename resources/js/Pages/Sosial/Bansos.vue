<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle2,
    Download,
    FileSpreadsheet,
    HeartHandshake,
    Pencil,
    Plus,
    Printer,
    Trash2,
    TrendingUp,
    Upload,
    UserCheck,
    Users,
    X,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import NikSearchAutocomplete from '@/Components/NikSearchAutocomplete.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    tahun_anggaran: {
        type: Number,
        default: 2026,
    },
    penerima_bansos: {
        type: Array,
        default: () => [],
    },
    pemerataan_kk: {
        type: Array,
        default: () => [],
    },
    pemerataan_individu: {
        type: Array,
        default: () => [],
    },
    mode_pemerataan: {
        type: String,
        default: 'per_kk',
    },
    ringkasan: {
        type: Object,
        default: () => ({
            total_penerima: 0,
            total_aktif: 0,
            total_graduasi: 0,
            jumlah_kk_belum_terima: 0,
            jumlah_individu_belum_terima: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    daftar_dusun: {
        type: Array,
        default: () => ['Dusun I', 'Dusun II', 'Dusun III'],
    },
    opsi_jenis_bantuan: {
        type: Array,
        default: () => [
            { value: 'pkh', label: 'Program Keluarga Harapan (PKH)' },
            { value: 'bpnt', label: 'Bantuan Pangan Non-Tunai (BPNT)' },
            { value: 'blt_dd', label: 'BLT Dana Desa (BLT-DD)' },
            { value: 'rtlh', label: 'Rumah Tidak Layak Huni (RTLH)' },
            { value: 'pbi_jkn', label: 'PBI Jaminan Kesehatan (PBI-JKN)' },
            { value: 'bantuan_daerah', label: 'Bantuan Pemerintah Daerah' },
        ],
    },
    opsi_status_kepesertaan: {
        type: Array,
        default: () => [
            { value: 'aktif', label: 'Aktif' },
            { value: 'graduasi', label: 'Graduasi (Mandiri)' },
            { value: 'pindah', label: 'Pindah Keluar' },
            { value: 'meninggal_dunia', label: 'Meninggal Dunia' },
        ],
    },
    opsi_tahap: {
        type: Array,
        default: () => ['Tahap I', 'Tahap II', 'Tahap III', 'Tahap IV'],
    },
    residents: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

// 2 Tab Internal Halaman Bansos
const activeTab = ref('penerima');
const modePemerataan = ref(
    props.mode_pemerataan === 'per_individu' ? 'per_individu' : 'per_kk',
);

const searchQuery = ref(props.filters?.search || '');
const selectedJenis = ref(props.filters?.jenis_bantuan || '');
const selectedTahap = ref(props.filters?.tahap_penyaluran || '');
const selectedStatus = ref(props.filters?.status_kepesertaan || '');
const selectedDusun = ref(props.filters?.dusun || '');
const selectedDesil = ref(props.filters?.status_kesejahteraan || '');

const hasActiveFilters = computed(() =>
    Boolean(
        searchQuery.value ||
            selectedJenis.value ||
            selectedTahap.value ||
            selectedStatus.value ||
            selectedDusun.value ||
            selectedDesil.value,
    ),
);

function resetFilters() {
    searchQuery.value = '';
    selectedJenis.value = '';
    selectedTahap.value = '';
    selectedStatus.value = '';
    selectedDusun.value = '';
    selectedDesil.value = '';
}

// Active Filter Chips (Pills)
const activeFilterChips = computed(() => {
    const chips = [];

    if (searchQuery.value.trim()) {
        chips.push({
            id: 'search',
            label: `Pencarian: "${searchQuery.value.trim()}"`,
            clear: () => {
                searchQuery.value = '';
            },
        });
    }

    if (selectedDusun.value) {
        chips.push({
            id: 'dusun',
            label: `Dusun: ${selectedDusun.value}`,
            clear: () => {
                selectedDusun.value = '';
            },
        });
    }

    if (activeTab.value === 'penerima') {
        if (selectedJenis.value) {
            const found = props.opsi_jenis_bantuan.find(
                (o) => o.value === selectedJenis.value,
            );
            chips.push({
                id: 'jenis',
                label: `Program: ${found?.label || selectedJenis.value}`,
                clear: () => {
                    selectedJenis.value = '';
                },
            });
        }
        if (selectedTahap.value) {
            chips.push({
                id: 'tahap',
                label: `Tahap: ${selectedTahap.value}`,
                clear: () => {
                    selectedTahap.value = '';
                },
            });
        }
        if (selectedStatus.value) {
            const found = props.opsi_status_kepesertaan.find(
                (o) => o.value === selectedStatus.value,
            );
            chips.push({
                id: 'status',
                label: `Status: ${found?.label || selectedStatus.value}`,
                clear: () => {
                    selectedStatus.value = '';
                },
            });
        }
    } else {
        if (selectedDesil.value) {
            chips.push({
                id: 'desil',
                label: `Desil: ${selectedDesil.value}`,
                clear: () => {
                    selectedDesil.value = '';
                },
            });
        }
    }

    return chips;
});

const filteredPenerima = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.penerima_bansos || []).filter((item) => {
        if (q) {
            const matchNama = String(item.nama_lengkap || '')
                .toLowerCase()
                .includes(q);
            const matchNik = String(item.nik || '').includes(q);
            const matchKk = String(item.nomor_kk || '').includes(q);
            if (!matchNama && !matchNik && !matchKk) {
                return false;
            }
        }
        if (selectedJenis.value && item.jenis_bantuan !== selectedJenis.value) {
            return false;
        }
        if (
            selectedTahap.value &&
            item.tahap_penyaluran !== selectedTahap.value
        ) {
            return false;
        }
        if (
            selectedStatus.value &&
            item.status_kepesertaan !== selectedStatus.value
        ) {
            return false;
        }
        if (selectedDusun.value && item.dusun !== selectedDusun.value) {
            return false;
        }
        return true;
    });
});

const filteredPemerataanKk = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.pemerataan_kk || []).filter((item) => {
        if (q) {
            const matchKk = String(item.nomor_kk || '').includes(q);
            const matchNama = String(item.nama_kepala_keluarga || '')
                .toLowerCase()
                .includes(q);
            const matchNik = String(item.nik_kepala_keluarga || '').includes(q);
            if (!matchKk && !matchNama && !matchNik) {
                return false;
            }
        }
        if (selectedDusun.value && item.dusun !== selectedDusun.value) {
            return false;
        }
        if (
            selectedDesil.value &&
            item.status_kesejahteraan !== selectedDesil.value
        ) {
            return false;
        }
        return true;
    });
});

const filteredPemerataanIndividu = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.pemerataan_individu || []).filter((item) => {
        if (q) {
            const matchKk = String(item.nomor_kk || '').includes(q);
            const matchNama = String(item.nama_lengkap || '')
                .toLowerCase()
                .includes(q);
            const matchNik = String(item.nik || '').includes(q);
            if (!matchKk && !matchNama && !matchNik) {
                return false;
            }
        }
        if (selectedDusun.value && item.dusun !== selectedDusun.value) {
            return false;
        }
        if (
            selectedDesil.value &&
            item.status_kesejahteraan !== selectedDesil.value
        ) {
            return false;
        }
        return true;
    });
});

const penerimaColumns = [
    { key: 'penerima', label: 'Nama Penerima & NIK/KK' },
    { key: 'dusun', label: 'Wilayah Dusun' },
    { key: 'program', label: 'Program & Tahap' },
    { key: 'nominal', label: 'Bentuk / Nominal' },
    { key: 'ekonomi', label: 'Prioritas DTSEN' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

const pemerataanKkColumns = [
    { key: 'kk', label: 'Nomor KK & Kepala Keluarga' },
    { key: 'dusun', label: 'Dusun / RT-RW' },
    { key: 'desil', label: 'Prioritas DTSEN' },
    { key: 'anggota', label: 'Anggota Prioritas Aktif' },
    { key: 'actions', label: 'Tindak Lanjut', align: 'right' },
];

const pemerataanIndividuColumns = [
    { key: 'warga', label: 'Nama Warga & NIK' },
    { key: 'kk_dusun', label: 'Nomor KK & Dusun' },
    { key: 'desil', label: 'Prioritas DTSEN' },
    { key: 'pekerjaan', label: 'Hubungan & Pekerjaan' },
    { key: 'actions', label: 'Tindak Lanjut', align: 'right' },
];

const cetakPdfUrl = computed(() => {
    const params = new URLSearchParams({
        tahun: String(props.tahun_anggaran),
    });
    if (selectedJenis.value) {
        params.set('jenis_bantuan', selectedJenis.value);
    }
    if (selectedTahap.value) {
        params.set('tahap_penyaluran', selectedTahap.value);
    }
    if (selectedStatus.value) {
        params.set('status_kepesertaan', selectedStatus.value);
    }
    if (selectedDusun.value) {
        params.set('dusun', selectedDusun.value);
    }
    return `/panel/sosial/bansos/cetak-pdf?${params.toString()}`;
});

const exportExcelUrl = computed(() => {
    const params = new URLSearchParams({
        tahun: String(props.tahun_anggaran),
    });
    if (selectedJenis.value) {
        params.set('jenis_bantuan', selectedJenis.value);
    }
    if (selectedTahap.value) {
        params.set('tahap_penyaluran', selectedTahap.value);
    }
    if (selectedStatus.value) {
        params.set('status_kepesertaan', selectedStatus.value);
    }
    if (selectedDusun.value) {
        params.set('dusun', selectedDusun.value);
    }
    return `/panel/sosial/bansos/export-excel?${params.toString()}`;
});

// Modal Tambah / Edit Penerima Bansos
const showFormModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isSubmitting = ref(false);
const initialResidentForForm = ref(null);
const kkHistoryInfo = ref(null);
const formErrors = ref({});

const STANDARD_TAHAP = ['Tahap I', 'Tahap II', 'Tahap III', 'Tahap IV'];
const selectedTahapOption = ref('Tahap I');
const customTahapInput = ref('');

const form = reactive({
    penduduk_id: null,
    jenis_bantuan: 'blt_dd',
    bentuk_nominal: 'Rp 300.000 / Bulan',
    tahun_anggaran: props.tahun_anggaran,
    tahap_penyaluran: 'Tahap I',
    status_kepesertaan: 'aktif',
    keterangan: '',
});

// Pengecekan Bantuan Ganda dalam 1 KK (Aturan: DILARANG)
const duplicateBansosInKk = computed(() => {
    if (!kkHistoryInfo.value || !Array.isArray(kkHistoryInfo.value.riwayat_bansos)) {
        return null;
    }

    const currentYear = Number(form.tahun_anggaran);
    const currentJenis = form.jenis_bantuan;
    const currentPendudukId = Number(form.penduduk_id);

    return (
        kkHistoryInfo.value.riwayat_bansos.find((item) => {
            if (Number(item.penduduk_id) === currentPendudukId) {
                return false;
            }
            if (Number(item.tahun_anggaran) !== currentYear) {
                return false;
            }
            if (item.jenis_bantuan !== currentJenis) {
                return false;
            }
            return item.status_kepesertaan === 'aktif';
        }) || null
    );
});

const isBlockedByDuplicate = computed(() => {
    return Boolean(duplicateBansosInKk.value);
});

const otherActiveBansosInKk = computed(() => {
    if (!kkHistoryInfo.value || !Array.isArray(kkHistoryInfo.value.riwayat_bansos)) {
        return [];
    }

    const currentYear = Number(form.tahun_anggaran);
    const currentJenis = form.jenis_bantuan;
    const currentPendudukId = Number(form.penduduk_id);

    return kkHistoryInfo.value.riwayat_bansos.filter((item) => {
        if (Number(item.penduduk_id) === currentPendudukId) {
            return false;
        }
        if (Number(item.tahun_anggaran) !== currentYear) {
            return false;
        }
        if (item.jenis_bantuan === currentJenis) {
            return false;
        }
        return item.status_kepesertaan === 'aktif';
    });
});

function openCreateModal(prefillResident = null) {
    isEditing.value = false;
    editingId.value = null;
    formErrors.value = {};

    form.penduduk_id = prefillResident?.id || null;
    form.jenis_bantuan = 'blt_dd';
    form.bentuk_nominal = 'Rp 300.000 / Bulan';
    form.tahun_anggaran = props.tahun_anggaran;
    form.tahap_penyaluran = 'Tahap I';
    form.status_kepesertaan = 'aktif';
    form.keterangan = '';

    selectedTahapOption.value = 'Tahap I';
    customTahapInput.value = '';

    initialResidentForForm.value = prefillResident || null;
    kkHistoryInfo.value = null;
    showFormModal.value = true;

    if (prefillResident?.id) {
        loadKkHistory(prefillResident.id);
    }
}

function openEditModal(item) {
    isEditing.value = true;
    editingId.value = item.id;
    formErrors.value = {};

    form.penduduk_id = item.penduduk_id;
    form.jenis_bantuan = item.jenis_bantuan || 'blt_dd';
    form.bentuk_nominal = item.bentuk_nominal || '';
    form.tahun_anggaran = item.tahun_anggaran || props.tahun_anggaran;
    form.tahap_penyaluran = item.tahap_penyaluran || 'Tahap I';
    form.status_kepesertaan = item.status_kepesertaan || 'aktif';
    form.keterangan = item.keterangan || '';

    if (STANDARD_TAHAP.includes(item.tahap_penyaluran)) {
        selectedTahapOption.value = item.tahap_penyaluran;
        customTahapInput.value = '';
    } else {
        selectedTahapOption.value = 'custom';
        customTahapInput.value = item.tahap_penyaluran || '';
    }

    initialResidentForForm.value = {
        id: item.penduduk_id,
        nik: item.nik,
        nama_lengkap: item.nama_lengkap,
        dusun: item.dusun,
        status_kesejahteraan: item.kategori_ekonomi,
    };
    kkHistoryInfo.value = null;
    showFormModal.value = true;
    loadKkHistory(item.penduduk_id);
}

async function loadKkHistory(pendudukId) {
    if (!pendudukId) {
        kkHistoryInfo.value = null;
        return;
    }
    try {
        const res = await axios.get(`/panel/sosial/bansos/cek-kk/${pendudukId}`);
        kkHistoryInfo.value = res.data;
    } catch {
        kkHistoryInfo.value = null;
    }
}

function handleSelectResident(resident) {
    form.penduduk_id = resident?.id || null;
    formErrors.value = {};
    if (resident?.id) {
        loadKkHistory(resident.id);
    } else {
        kkHistoryInfo.value = null;
    }
}

function submitBansosForm() {
    if (isSubmitting.value || isBlockedByDuplicate.value) {
        return;
    }

    const finalTahap =
        selectedTahapOption.value === 'custom'
            ? customTahapInput.value.trim() || 'Tahap I'
            : selectedTahapOption.value;

    form.tahap_penyaluran = finalTahap;
    formErrors.value = {};
    isSubmitting.value = true;

    const payload = {
        penduduk_id: form.penduduk_id,
        jenis_bantuan: form.jenis_bantuan,
        bentuk_nominal: form.bentuk_nominal,
        tahun_anggaran: form.tahun_anggaran,
        tahap_penyaluran: finalTahap,
        status_kepesertaan: form.status_kepesertaan,
        keterangan: form.keterangan || null,
    };

    if (isEditing.value && editingId.value) {
        router.put(`/panel/sosial/bansos/${editingId.value}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                showFormModal.value = false;
            },
            onError: (errs) => {
                formErrors.value = errs || {};
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        });
        return;
    }

    router.post('/panel/sosial/bansos', payload, {
        preserveScroll: true,
        onSuccess: () => {
            showFormModal.value = false;
        },
        onError: (errs) => {
            formErrors.value = errs || {};
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

async function handleDeleteBansos(item) {
    const ok = await confirm.ask({
        title: 'Hapus Catatan Penerima Bansos?',
        message: `Data bantuan "${item.jenis_bantuan_label}" untuk ${item.nama_lengkap} (${item.tahap_penyaluran}) akan dihapus dari daftar aktif.`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/sosial/bansos/${item.id}`, {
        preserveScroll: true,
    });
}

// Modal Import Excel Bansos
const showImportModal = ref(false);
const importFile = ref(null);
const isImporting = ref(false);
const importErrors = computed(() => page.props?.flash?.import_errors || []);

function submitImportExcel() {
    if (!importFile.value || isImporting.value) {
        return;
    }

    isImporting.value = true;
    router.post(
        '/panel/sosial/bansos/import-excel',
        {
            file: importFile.value,
            tahun_anggaran: props.tahun_anggaran,
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                importFile.value = null;
                if (!page.props?.flash?.import_errors?.length) {
                    showImportModal.value = false;
                }
            },
            onFinish: () => {
                isImporting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="`Data Penerima Bansos & Pemerataan TA ${tahun_anggaran}`" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header Halaman dengan PageHeader -->
            <PageHeader
                title="Data Sosial (Bansos) & Pemerataan Bantuan"
                :description="`Pengelolaan penyaluran bantuan sosial dan saringan keluarga prioritas DTSEN Desil 1–4 Tahun Anggaran ${tahun_anggaran}.`"
            >
                <template #actions>
                    <a
                        :href="cetakPdfUrl"
                        data-testid="btn-cetak-bansos-f4"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Printer class="mr-1.5 h-4 w-4" />
                        <span>Cetak Daftar Hadir F4</span>
                    </a>

                    <template v-if="isOperator">
                        <a
                            :href="exportExcelUrl"
                            data-testid="btn-export-bansos"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                        >
                            <Download class="mr-1.5 h-4 w-4" />
                            <span>Export Excel</span>
                        </a>

                        <BaseButton
                            variant="outline"
                            data-testid="btn-open-import-bansos"
                            @click="showImportModal = true"
                        >
                            <Upload class="mr-1.5 h-4 w-4" />
                            <span>Import Excel</span>
                        </BaseButton>

                        <BaseButton
                            variant="primary"
                            data-testid="btn-tambah-bansos"
                            @click="openCreateModal()"
                        >
                            <Plus class="mr-1.5 h-4 w-4" />
                            <span>Tambah Penerima</span>
                        </BaseButton>
                    </template>
                </template>
            </PageHeader>

            <!-- 4 StatCard Ringkasan -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Total Penerima Bansos"
                    :value="ringkasan?.total_penerima ?? penerima_bansos.length"
                    :subtext="`Tercatat di TA ${tahun_anggaran}`"
                    tone="blue"
                    :icon="Users"
                />
                <StatCard
                    label="KPM Bantuan Aktif"
                    :value="ringkasan?.total_aktif ?? 0"
                    subtext="Status penyaluran aktif"
                    tone="emerald"
                    :icon="CheckCircle2"
                />
                <StatCard
                    label="Graduasi Mandiri"
                    :value="ringkasan?.total_graduasi ?? 0"
                    subtext="KPM telah mandiri/lulus"
                    tone="amber"
                    :icon="TrendingUp"
                />
                <StatCard
                    label="Keluarga Belum Menerima"
                    :value="modePemerataan === 'per_kk' ? (ringkasan?.jumlah_kk_belum_terima ?? pemerataan_kk.length) : (ringkasan?.jumlah_individu_belum_terima ?? pemerataan_individu.length)"
                    subtext="Sasaran prioritas DTSEN"
                    tone="neutral"
                    :icon="HeartHandshake"
                />
            </div>

            <!-- 2 Tab Kontekstual: Daftar Penerima Bansos vs Pemerataan Bantuan -->
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-3">
                <div class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-1">
                    <button
                        type="button"
                        data-testid="tab-daftar-penerima"
                        :class="[
                            'inline-flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
                            activeTab === 'penerima'
                                ? 'bg-white text-blue-700 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="activeTab = 'penerima'"
                    >
                        <span>Daftar Penerima Bansos</span>
                        <span
                            class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold tabular-nums text-blue-700"
                        >
                            {{ ringkasan?.total_penerima ?? penerima_bansos.length }}
                        </span>
                    </button>

                    <button
                        type="button"
                        data-testid="tab-pemerataan-bantuan"
                        :class="[
                            'inline-flex items-center gap-2 rounded-md px-3.5 py-1.5 text-sm font-medium transition-colors',
                            activeTab === 'pemerataan'
                                ? 'bg-white text-blue-700 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900',
                        ]"
                        @click="activeTab = 'pemerataan'"
                    >
                        <span>Pemerataan Bantuan (Prioritas DTSEN)</span>
                        <span
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-amber-800"
                        >
                            {{
                                modePemerataan === 'per_kk'
                                    ? pemerataan_kk.length
                                    : pemerataan_individu.length
                            }}
                        </span>
                    </button>
                </div>

                <!-- Toggle Mode Pemerataan: Per KK [Default] vs Per Individu -->
                <div
                    v-if="activeTab === 'pemerataan'"
                    data-testid="pemerataan-mode-toggle"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1"
                >
                    <button
                        type="button"
                        data-testid="toggle-pemerataan-per-kk"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors',
                            modePemerataan === 'per_kk'
                                ? 'bg-blue-700 text-white'
                                : 'text-slate-600 hover:bg-slate-100',
                        ]"
                        @click="modePemerataan = 'per_kk'"
                    >
                        <Users class="h-3.5 w-3.5" />
                        <span>Per KK (Default)</span>
                    </button>

                    <button
                        type="button"
                        data-testid="toggle-pemerataan-per-individu"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors',
                            modePemerataan === 'per_individu'
                                ? 'bg-blue-700 text-white'
                                : 'text-slate-600 hover:bg-slate-100',
                        ]"
                        @click="modePemerataan = 'per_individu'"
                    >
                        <UserCheck class="h-3.5 w-3.5" />
                        <span>Per Individu</span>
                    </button>
                </div>
            </div>

            <!-- UnifiedFilterBar dengan Template #filters & Active Filter Chips -->
            <div class="space-y-2">
                <UnifiedFilterBar
                    v-model="searchQuery"
                    search-placeholder="Cari nama warga, NIK, atau Nomor KK..."
                    :has-custom-active-filter="hasActiveFilters"
                    @reset="resetFilters"
                >
                    <template #filters>
                        <select
                            v-model="selectedDusun"
                            aria-label="Filter Dusun"
                            data-testid="filter-dusun-bansos"
                            class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                        >
                            <option value="">Semua Dusun</option>
                            <option
                                v-for="d in daftar_dusun"
                                :key="d"
                                :value="d"
                            >
                                {{ d }}
                            </option>
                        </select>

                        <template v-if="activeTab === 'penerima'">
                            <select
                                v-model="selectedJenis"
                                aria-label="Filter Jenis Bantuan"
                                data-testid="filter-jenis-bansos"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                            >
                                <option value="">Semua Program Bantuan</option>
                                <option
                                    v-for="opt in opsi_jenis_bantuan"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>

                            <select
                                v-model="selectedTahap"
                                aria-label="Filter Tahap Penyaluran"
                                data-testid="filter-tahap-bansos"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                            >
                                <option value="">Semua Tahap</option>
                                <option
                                    v-for="thp in opsi_tahap"
                                    :key="thp"
                                    :value="thp"
                                >
                                    {{ thp }}
                                </option>
                            </select>

                            <select
                                v-model="selectedStatus"
                                aria-label="Filter Status Kepesertaan"
                                data-testid="filter-status-bansos"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                            >
                                <option value="">Semua Status</option>
                                <option
                                    v-for="st in opsi_status_kepesertaan"
                                    :key="st.value"
                                    :value="st.value"
                                >
                                    {{ st.label }}
                                </option>
                            </select>
                        </template>

                        <template v-else>
                            <select
                                v-model="selectedDesil"
                                aria-label="Filter Prioritas DTSEN"
                                data-testid="filter-desil-bansos"
                                class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                            >
                                <option value="">Semua Prioritas DTSEN</option>
                                <option value="dtsen_desil_1">Desil 1 (Sangat Miskin)</option>
                                <option value="dtsen_desil_2">Desil 2 (Miskin)</option>
                                <option value="dtsen_desil_3_4">Desil 3–4 (Hampir / Rentan Miskin)</option>
                            </select>
                        </template>
                    </template>
                </UnifiedFilterBar>

                <!-- Active Filter Chips (Pills) -->
                <div
                    v-if="activeFilterChips.length > 0"
                    class="flex flex-wrap items-center gap-2 pt-1"
                >
                    <span class="text-xs font-medium text-slate-500">Filter Aktif:</span>
                    <span
                        v-for="chip in activeFilterChips"
                        :key="chip.id"
                        class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 border border-blue-200"
                    >
                        <span>{{ chip.label }}</span>
                        <button
                            type="button"
                            class="rounded-full p-0.5 text-blue-600 hover:bg-blue-100 hover:text-blue-900"
                            @click="chip.clear()"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </span>
                    <button
                        type="button"
                        class="text-xs font-medium text-slate-500 hover:text-red-700 underline underline-offset-2 ml-1"
                        @click="resetFilters"
                    >
                        Reset Semua
                    </button>
                </div>
            </div>

            <!-- TAB 1: Daftar Penerima Bansos (DataTable) -->
            <div v-if="activeTab === 'penerima'" data-testid="section-daftar-penerima">
                <DataTable
                    :columns="penerimaColumns"
                    :rows="filteredPenerima"
                    :empty-title="`Belum ada data penerima bantuan sosial pada Tahun Anggaran ${tahun_anggaran}`"
                    empty-description="Tambahkan penerima secara manual atau impor daftar NIK penerima melalui berkas Excel."
                >
                    <template #cell-penerima="{ row }">
                        <div>
                            <p class="font-semibold text-slate-900">
                                {{ row.nama_lengkap }}
                            </p>
                            <p class="text-xs tabular-nums text-slate-500">
                                NIK: {{ row.nik }} &bull; KK: {{ row.nomor_kk }}
                            </p>
                        </div>
                    </template>

                    <template #cell-dusun="{ row }">
                        <div class="text-xs">
                            <p class="font-medium text-slate-800">{{ row.dusun }}</p>
                            <p class="text-slate-500">
                                RT {{ row.rt || '001' }} / RW {{ row.rw || '001' }}
                            </p>
                        </div>
                    </template>

                    <template #cell-program="{ row }">
                        <div>
                            <p class="font-medium text-slate-900">
                                {{ row.jenis_bantuan_label }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ row.tahap_penyaluran }} (TA {{ row.tahun_anggaran }})
                            </p>
                        </div>
                    </template>

                    <template #cell-nominal="{ row }">
                        <span class="font-semibold text-slate-900">
                            {{ row.bentuk_nominal }}
                        </span>
                    </template>

                    <template #cell-ekonomi="{ row }">
                        <StatusBadge
                            :status="row.kategori_ekonomi"
                            :label="row.kategori_ekonomi_label"
                        />
                    </template>

                    <template #cell-status="{ row }">
                        <StatusBadge
                            :status="row.status_kepesertaan"
                            :label="row.status_kepesertaan_label"
                        />
                    </template>

                    <template #cell-actions="{ row }">
                        <div
                            v-if="isOperator"
                            class="flex items-center justify-end gap-1.5"
                        >
                            <button
                                type="button"
                                :data-testid="`btn-edit-bansos-${row.id}`"
                                class="inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                @click="openEditModal(row)"
                            >
                                <Pencil class="mr-1 h-3.5 w-3.5" />
                                <span>Edit</span>
                            </button>

                            <button
                                type="button"
                                :data-testid="`btn-delete-bansos-${row.id}`"
                                class="inline-flex h-8 items-center justify-center rounded-md px-2 text-xs font-medium text-red-600 hover:bg-red-50"
                                @click="handleDeleteBansos(row)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <span v-else class="text-xs text-slate-400">Read-Only</span>
                    </template>
                </DataTable>
            </div>

            <!-- TAB 2: Pemerataan Bantuan (DataTable) -->
            <div v-else data-testid="section-pemerataan-bantuan" class="space-y-4">
                <BaseCallout
                    variant="info"
                    title="Saringan Pemerataan Bantuan Sosial (Prioritas DTSEN Desil 1–4)"
                >
                    <span v-if="modePemerataan === 'per_kk'">
                        <strong>Mode Default (Berbasis Kartu Keluarga)</strong>: Menampilkan KK aktif beranggota DTSEN Desil 1–4 di mana <strong>seluruh anggota dalam KK tersebut belum pernah menerima bantuan sosial apa pun</strong> (baik Aktif maupun Graduasi) pada Tahun Anggaran {{ tahun_anggaran }}.
                    </span>
                    <span v-else>
                        <strong>Mode Toggle (Per Individu)</strong>: Menampilkan individu warga aktif kategori DTSEN Desil 1–4 yang belum tercatat menerima bantuan sosial pada Tahun Anggaran {{ tahun_anggaran }}.
                    </span>
                </BaseCallout>

                <!-- Tabel Pemerataan Per KK -->
                <div
                    v-if="modePemerataan === 'per_kk'"
                    data-testid="table-pemerataan-kk"
                >
                    <DataTable
                        :columns="pemerataanKkColumns"
                        :rows="filteredPemerataanKk"
                        empty-title="Seluruh KK Prioritas DTSEN Desil 1–4 Sudah Terjangkau Bantuan"
                        empty-description="Tidak ditemukan Kartu Keluarga prioritas Desil 1–4 yang belum menerima bantuan pada tahun anggaran ini."
                    >
                        <template #cell-kk="{ row }">
                            <div>
                                <p class="font-semibold text-slate-900">
                                    {{ row.nama_kepala_keluarga }}
                                </p>
                                <p class="text-xs tabular-nums text-slate-500">
                                    No. KK: {{ row.nomor_kk }} &bull; NIK: {{ row.nik_kepala_keluarga }}
                                </p>
                            </div>
                        </template>

                        <template #cell-dusun="{ row }">
                            <div class="text-xs">
                                <p class="font-medium text-slate-800">{{ row.dusun }}</p>
                                <p class="text-slate-500">
                                    RT {{ row.rt || '001' }} / RW {{ row.rw || '001' }}
                                </p>
                            </div>
                        </template>

                        <template #cell-desil="{ row }">
                            <StatusBadge
                                :status="row.status_kesejahteraan"
                                :label="row.status_kesejahteraan_label"
                            />
                        </template>

                        <template #cell-anggota="{ row }">
                            <span class="text-xs font-medium text-slate-700">
                                {{ row.jumlah_anggota_prioritas }} prioritas dari {{ row.jumlah_anggota_aktif }} anggota aktif
                            </span>
                        </template>

                        <template #cell-actions="{ row }">
                            <BaseButton
                                v-if="isOperator"
                                variant="outline"
                                size="sm"
                                :data-testid="`btn-daftarkan-kk-${row.kartu_keluarga_id}`"
                                @click="
                                    openCreateModal({
                                        id: row.penduduk_id,
                                        nik: row.nik_kepala_keluarga,
                                        nomor_kk: row.nomor_kk,
                                        nama_lengkap: row.nama_kepala_keluarga,
                                        dusun: row.dusun,
                                        status_kesejahteraan: row.status_kesejahteraan,
                                    })
                                "
                            >
                                <Plus class="mr-1 h-3.5 w-3.5" />
                                <span>Daftarkan Bantuan</span>
                            </BaseButton>
                            <span v-else class="text-xs text-slate-400">Read-Only</span>
                        </template>
                    </DataTable>
                </div>

                <!-- Tabel Pemerataan Per Individu -->
                <div
                    v-else
                    data-testid="table-pemerataan-individu"
                >
                    <DataTable
                        :columns="pemerataanIndividuColumns"
                        :rows="filteredPemerataanIndividu"
                        empty-title="Seluruh Individu Prioritas DTSEN Desil 1–4 Sudah Terjangkau"
                        empty-description="Tidak ditemukan warga aktif kategori DTSEN Desil 1–4 yang belum menerima bantuan pada tahun anggaran ini."
                    >
                        <template #cell-warga="{ row }">
                            <div>
                                <p class="font-semibold text-slate-900">
                                    {{ row.nama_lengkap }}
                                </p>
                                <p class="text-xs tabular-nums text-slate-500">
                                    NIK: {{ row.nik }}
                                </p>
                            </div>
                        </template>

                        <template #cell-kk_dusun="{ row }">
                            <div class="text-xs tabular-nums">
                                <p class="font-medium text-slate-800">KK: {{ row.nomor_kk }}</p>
                                <p class="text-slate-500">{{ row.dusun }}</p>
                            </div>
                        </template>

                        <template #cell-desil="{ row }">
                            <StatusBadge
                                :status="row.status_kesejahteraan"
                                :label="row.status_kesejahteraan_label"
                            />
                        </template>

                        <template #cell-pekerjaan="{ row }">
                            <div class="text-xs">
                                <p class="font-medium capitalize text-slate-800">
                                    {{ String(row.hubungan_keluarga || '').replace('_', ' ') }}
                                </p>
                                <p class="text-slate-500">{{ row.pekerjaan || '—' }}</p>
                            </div>
                        </template>

                        <template #cell-actions="{ row }">
                            <BaseButton
                                v-if="isOperator"
                                variant="outline"
                                size="sm"
                                :data-testid="`btn-daftarkan-individu-${row.penduduk_id}`"
                                @click="
                                    openCreateModal({
                                        id: row.penduduk_id,
                                        nik: row.nik,
                                        nomor_kk: row.nomor_kk,
                                        nama_lengkap: row.nama_lengkap,
                                        dusun: row.dusun,
                                        status_kesejahteraan: row.status_kesejahteraan,
                                    })
                                "
                            >
                                <Plus class="mr-1 h-3.5 w-3.5" />
                                <span>Daftarkan Bantuan</span>
                            </BaseButton>
                            <span v-else class="text-xs text-slate-400">Read-Only</span>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Modal Tambah / Edit Penerima Bansos (BaseModal) -->
        <BaseModal
            :show="showFormModal"
            :title="
                isEditing
                    ? 'Perbarui Data Penerima Bansos'
                    : 'Tambah Penerima Bantuan Sosial'
            "
            max-width="lg"
            @close="showFormModal = false"
        >
            <form
                data-testid="form-penerima-bansos"
                class="space-y-4"
                @submit.prevent="submitBansosForm"
            >
                <NikSearchAutocomplete
                    v-model="form.penduduk_id"
                    :initial-resident="initialResidentForForm"
                    :items="residents"
                    label="Cari Warga Penerima (NIK / Nama Lengkap)"
                    required
                    :error="formErrors.penduduk_id"
                    @select="handleSelectResident"
                />

                <!-- Ringkasan Warga & KK menggunakan BaseCallout -->
                <BaseCallout
                    v-if="isBlockedByDuplicate"
                    variant="danger"
                    title="DILARANG: Bantuan Ganda dalam 1 Kartu Keluarga"
                >
                    Anggota keluarga dalam 1 KK atas nama
                    <strong>{{ duplicateBansosInKk?.nama_penerima }}</strong> ({{ duplicateBansosInKk?.hubungan_keluarga || 'Anggota KK' }})
                    sudah terdaftar sebagai penerima aktif <strong>{{ duplicateBansosInKk?.jenis_bantuan_label }}</strong> pada Tahun Anggaran {{ form.tahun_anggaran }}.
                    Sesuai kebijakan desa, bantuan ganda sejenis dalam 1 KK dilarang keras. Tombol simpan dinonaktifkan.
                </BaseCallout>

                <BaseCallout
                    v-else-if="otherActiveBansosInKk.length > 0"
                    variant="info"
                    title="Catatan Bantuan Keluarga Lain"
                >
                    Nomor KK: <strong>{{ kkHistoryInfo?.nomor_kk }}</strong> &bull;
                    Anggota KK lain telah menerima:
                    <strong>{{ otherActiveBansosInKk.map(o => `${o.nama_penerima} (${o.jenis_bantuan_label})`).join(', ') }}</strong>.
                </BaseCallout>

                <BaseCallout
                    v-else-if="kkHistoryInfo"
                    variant="info"
                    title="Ringkasan Kepesertaan Bansos 1 KK"
                >
                    Nomor KK: <strong>{{ kkHistoryInfo.nomor_kk }}</strong> &bull;
                    Total Bantuan Aktif di KK ini:
                    <strong>{{ kkHistoryInfo.total_bantuan_aktif }} program</strong>.
                </BaseCallout>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Jenis Program Bantuan <span class="text-red-600">*</span>
                        </label>
                        <select
                            v-model="form.jenis_bantuan"
                            data-testid="select-jenis-bantuan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        >
                            <option
                                v-for="opt in opsi_jenis_bantuan"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Bentuk / Nominal Bantuan <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.bentuk_nominal"
                            type="text"
                            required
                            data-testid="input-bentuk-nominal"
                            placeholder="Contoh: Rp 300.000 / Bulan atau Beras 10 Kg"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Tahun Anggaran <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model.number="form.tahun_anggaran"
                            type="number"
                            required
                            data-testid="input-tahun-bansos"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Tahap Penyaluran <span class="text-red-600">*</span>
                        </label>
                        <select
                            v-model="selectedTahapOption"
                            data-testid="select-tahap-penyaluran"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        >
                            <option
                                v-for="thp in STANDARD_TAHAP"
                                :key="thp"
                                :value="thp"
                            >
                                {{ thp }}
                            </option>
                            <option value="custom">Lainnya... (Kustom)</option>
                        </select>
                    </div>

                    <div
                        v-if="selectedTahapOption === 'custom'"
                        class="space-y-1.5 sm:col-span-2"
                    >
                        <label class="block text-sm font-medium text-slate-700">
                            Nama Tahap Penyaluran Kustom <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="customTahapInput"
                            type="text"
                            required
                            data-testid="input-tahap-bansos"
                            placeholder="Contoh: Bulan Ramadhan, Triwulan IV, dll."
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Status Kepesertaan
                        </label>
                        <select
                            v-model="form.status_kepesertaan"
                            data-testid="select-status-kepesertaan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        >
                            <option
                                v-for="st in opsi_status_kepesertaan"
                                :key="st.value"
                                :value="st.value"
                            >
                                {{ st.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Keterangan Tambahan (Opsional)
                        </label>
                        <input
                            v-model="form.keterangan"
                            type="text"
                            data-testid="input-keterangan-bansos"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <BaseButton
                        variant="outline"
                        type="button"
                        @click="showFormModal = false"
                    >
                        Batal
                    </BaseButton>
                    <BaseButton
                        variant="primary"
                        type="submit"
                        :loading="isSubmitting"
                        :disabled="isBlockedByDuplicate || isSubmitting"
                        data-testid="btn-submit-bansos"
                    >
                        {{ isEditing ? 'Simpan Perubahan' : 'Simpan Penerima' }}
                    </BaseButton>
                </div>
            </form>
        </BaseModal>

        <!-- Modal Import Excel Bansos (BaseModal & FileDropzone) -->
        <BaseModal
            :show="showImportModal"
            title="Import Data Penerima Bansos dari Excel"
            max-width="lg"
            @close="showImportModal = false"
        >
            <form
                data-testid="form-import-bansos"
                class="space-y-4"
                @submit.prevent="submitImportExcel"
            >
                <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs">
                    <span class="text-slate-600">
                        Gunakan format kolom standar (`nik`, `jenis_bantuan`, `bentuk_nominal`, `tahun_anggaran`, `tahap_penyaluran`).
                    </span>
                    <a
                        href="/panel/sosial/bansos/template-excel"
                        data-testid="link-template-bansos"
                        class="inline-flex shrink-0 items-center gap-1 font-semibold text-blue-700 hover:underline"
                    >
                        <FileSpreadsheet class="h-3.5 w-3.5" />
                        <span>Unduh Template</span>
                    </a>
                </div>

                <FileDropzone
                    v-model="importFile"
                    label="Pilih Berkas Excel / CSV Penerima Bansos"
                    accept=".xlsx,.xls,.csv"
                    helper-text="Format .xlsx, .xls, atau .csv • Maks. 5 MB"
                    required
                />

                <!-- Tabel Ringkasan Error Baris Import -->
                <div
                    v-if="importErrors.length > 0"
                    data-testid="import-bansos-errors"
                    class="max-h-48 overflow-y-auto rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800"
                >
                    <p class="font-semibold">
                        Daftar Baris Gagal Divalidasi ({{ importErrors.length }} baris):
                    </p>
                    <ul class="mt-1.5 list-disc space-y-1 pl-4">
                        <li
                            v-for="(err, idx) in importErrors"
                            :key="idx"
                        >
                            <strong>Baris #{{ err.baris }}</strong> (NIK: {{ err.nik }}): {{ err.pesan }}
                        </li>
                    </ul>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <BaseButton
                        variant="outline"
                        type="button"
                        @click="showImportModal = false"
                    >
                        Tutup
                    </BaseButton>
                    <BaseButton
                        variant="primary"
                        type="submit"
                        :loading="isImporting"
                        data-testid="btn-submit-import-bansos"
                    >
                        Proses Import Excel
                    </BaseButton>
                </div>
            </form>
        </BaseModal>
    </AppLayout>
</template>
