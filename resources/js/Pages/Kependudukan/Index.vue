<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Download,
    Eye,
    FileSpreadsheet,
    Pencil,
    Plus,
    RefreshCw,
    Trash2,
    Upload,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import SlideOverDrawer from '@/Components/SlideOverDrawer.vue';
import DusunCombobox from '@/Components/DusunCombobox.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatDateId, formatNumber } from '@/Utils/formatters';

const props = defineProps({
    tab: {
        type: String,
        default: 'penduduk',
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    penduduk: {
        type: Object,
        default: () => ({ data: [], total: 0 }),
    },
    kartu_keluarga: {
        type: Object,
        default: () => ({ data: [], total: 0 }),
    },
    kartu_keluarga_options: {
        type: Array,
        default: () => [],
    },
    daftar_dusun: {
        type: Array,
        default: () => [],
    },
    ringkasan: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const confirm = useConfirm();

const isOperator = computed(() => page.props?.auth?.user?.role === 'operator');

// --- State Tab & Filter ---
const activeTab = ref(props.tab || props.filters?.tab || 'penduduk');
const searchQuery = ref(props.filters?.search || '');
const selectedDusun = ref(props.filters?.dusun || '');
const selectedStatusKependudukan = ref(props.filters?.status_kependudukan || '');
const selectedStatusKesejahteraan = ref(
    props.filters?.status_kesejahteraan || '',
);

watch(
    () => props.tab,
    (nextTab) => {
        if (nextTab && nextTab !== activeTab.value) {
            activeTab.value = nextTab;
        }
    },
);

const tabsConfig = computed(() => [
    {
        key: 'penduduk',
        label: 'Data Penduduk',
        badge: props.ringkasan?.total_penduduk_aktif ?? 0,
    },
    {
        key: 'kk',
        label: 'Kartu Keluarga',
        badge: props.ringkasan?.total_kk_aktif ?? 0,
    },
]);

const filterBarConfig = computed(() => [
    {
        key: 'dusun',
        label: 'Dusun',
        allLabel: 'Semua Dusun',
        value: selectedDusun.value,
        options: (props.daftar_dusun || []).map((d) => ({
            value: d,
            label: d,
        })),
    },
    {
        key: 'status_kependudukan',
        label: 'Status Warga',
        allLabel: 'Semua Status Warga',
        value: selectedStatusKependudukan.value,
        options: [
            { value: 'aktif', label: 'Aktif' },
            { value: 'pindah_keluar', label: 'Pindah Keluar' },
            { value: 'meninggal_dunia', label: 'Meninggal Dunia' },
        ],
    },
    {
        key: 'status_kesejahteraan',
        label: 'Kategori DTSEN',
        allLabel: 'Semua Desil DTSEN',
        value: selectedStatusKesejahteraan.value,
        options: [
            { value: 'dtsen_desil_1', label: 'DTSEN Desil 1 (Miskin Ekstrem)' },
            { value: 'dtsen_desil_2', label: 'DTSEN Desil 2 (Miskin)' },
            {
                value: 'dtsen_desil_3_4',
                label: 'DTSEN Desil 3–4 (Rentan Miskin)',
            },
            { value: 'non_prioritas', label: 'Non-Prioritas (Desil 5–10)' },
        ],
    },
]);

function resolveRoute(_name, _param, fallback) {
    return fallback;
}

const exportExcelUrl = computed(() => {
    const base = resolveRoute(
        'panel.kependudukan.export-excel',
        undefined,
        '/panel/kependudukan/export-excel',
    );
    const params = new URLSearchParams();
    if (searchQuery.value) params.set('search', searchQuery.value);
    if (selectedDusun.value) params.set('dusun', selectedDusun.value);
    if (selectedStatusKependudukan.value) {
        params.set('status_kependudukan', selectedStatusKependudukan.value);
    }
    if (selectedStatusKesejahteraan.value) {
        params.set('status_kesejahteraan', selectedStatusKesejahteraan.value);
    }
    const qs = params.toString();
    return qs ? `${base}?${qs}` : base;
});

const templateExcelUrl = computed(() =>
    resolveRoute(
        'panel.kependudukan.template-excel',
        undefined,
        '/panel/kependudukan/template-excel',
    ),
);

function applyFilters(overrides = {}) {
    const payload = {
        tab: activeTab.value,
        search: searchQuery.value || undefined,
        dusun: selectedDusun.value || undefined,
        status_kependudukan: selectedStatusKependudukan.value || undefined,
        status_kesejahteraan: selectedStatusKesejahteraan.value || undefined,
        ...overrides,
    };

    Object.keys(payload).forEach((k) => {
        if (!payload[k]) {
            delete payload[k];
        }
    });

    router.get(
        resolveRoute('panel.kependudukan.index', undefined, '/panel/kependudukan'),
        payload,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function handleTabChange(nextTab) {
    activeTab.value = nextTab;
    applyFilters({ tab: nextTab });
}

function handleSearch(val) {
    searchQuery.value = val;
    applyFilters({ search: val || undefined });
}

function handleFilterUpdate({ key, value }) {
    if (key === 'dusun') selectedDusun.value = value;
    if (key === 'status_kependudukan') selectedStatusKependudukan.value = value;
    if (key === 'status_kesejahteraan')
        selectedStatusKesejahteraan.value = value;
    applyFilters();
}

function handleResetFilters() {
    searchQuery.value = '';
    selectedDusun.value = '';
    selectedStatusKependudukan.value = '';
    selectedStatusKesejahteraan.value = '';
    applyFilters({
        search: undefined,
        dusun: undefined,
        status_kependudukan: undefined,
        status_kesejahteraan: undefined,
    });
}

// --- Kolom & Aksi Tabel Data Penduduk (6 Kolom Ramping) ---
const pendudukColumns = [
    { key: 'nama_lengkap', label: 'Warga & NIK' },
    { key: 'nomor_kk', label: 'No. KK & Hubungan' },
    { key: 'dusun', label: 'Wilayah Domisili' },
    { key: 'tanggal_lahir', label: 'TTL & Pekerjaan' },
    { key: 'status_kesejahteraan', label: 'Kategori DTSEN' },
    { key: 'status_kependudukan', label: 'Status' },
];

const HUBUNGAN_LABELS = {
    kepala_keluarga: 'Kepala Keluarga',
    istri: 'Istri',
    anak: 'Anak',
    lainnya: 'Famili Lain',
};

const PERKAWINAN_LABELS = {
    belum_kawin: 'Belum Kawin',
    kawin: 'Kawin',
    cerai_hidup: 'Cerai Hidup',
    cerai_mati: 'Cerai Mati',
};

function formatHubungan(val) {
    return HUBUNGAN_LABELS[val] || val || '-';
}

function formatPerkawinan(val) {
    return PERKAWINAN_LABELS[val] || val || '-';
}

function getPendudukRowActions(row) {
    return [
        {
            key: 'detail',
            label: 'Detail & KK',
            icon: Eye,
            primary: true,
            operatorOnly: false,
            onClick: () => openPendudukDrawer(row),
        },
        {
            key: 'edit',
            label: 'Ubah Biodata',
            icon: Pencil,
            primary: true,
            operatorOnly: true,
            onClick: () => openEditPendudukModal(row),
        },
        {
            key: 'mutasi',
            label: 'Mutasi / Reaktivasi',
            icon: RefreshCw,
            operatorOnly: true,
            onClick: () => openMutasiModal(row),
        },
        {
            key: 'hapus',
            label: 'Hapus Penduduk',
            icon: Trash2,
            variant: 'danger',
            operatorOnly: true,
            onClick: () => handleDeletePenduduk(row),
        },
    ];
}

// --- Kolom & Aksi Tabel Kartu Keluarga (5 Kolom Ramping) ---
const kkColumns = [
    { key: 'nomor_kk', label: 'Nomor KK' },
    { key: 'kepala_keluarga', label: 'Kepala Keluarga' },
    { key: 'dusun', label: 'Dusun & RT/RW' },
    { key: 'alamat', label: 'Alamat Rumah' },
    { key: 'jumlah_anggota', label: 'Anggota Keluarga' },
];

function getKkRowActions(row) {
    return [
        {
            key: 'detail',
            label: 'Lihat Anggota KK',
            icon: Users,
            primary: true,
            operatorOnly: false,
            onClick: () => openKkDrawer(row),
        },
        {
            key: 'edit',
            label: 'Ubah KK',
            icon: Pencil,
            primary: true,
            operatorOnly: true,
            onClick: () => openEditKkModal(row),
        },
        {
            key: 'hapus',
            label: 'Hapus KK',
            icon: Trash2,
            variant: 'danger',
            operatorOnly: true,
            onClick: () => handleDeleteKk(row),
        },
    ];
}

// --- SlideOverDrawer Detail Warga & Anggota 1 KK ---
const isDrawerOpen = ref(false);
const drawerMode = ref('penduduk'); // 'penduduk' | 'kk'
const selectedPendudukDetail = ref(null);
const selectedKkDetail = ref(null);

function openPendudukDrawer(row) {
    drawerMode.value = 'penduduk';
    selectedPendudukDetail.value = row;
    selectedKkDetail.value = row.kartu_keluarga || {
        id: row.kartu_keluarga_id,
        nomor_kk: row.nomor_kk,
        dusun: row.dusun,
        rt: row.rt,
        rw: row.rw,
        alamat: row.alamat,
        perlu_update_kepala_keluarga: row.perlu_update_kepala_keluarga,
        peringatan_kepala_keluarga: row.peringatan_kepala_keluarga,
        anggota: row.kartu_keluarga?.anggota || [row],
    };
    isDrawerOpen.value = true;
}

function openKkDrawer(kkRow) {
    drawerMode.value = 'kk';
    selectedPendudukDetail.value = null;
    selectedKkDetail.value = kkRow;
    isDrawerOpen.value = true;
}

function closeDrawer() {
    isDrawerOpen.value = false;
}

// --- Modal Form Kartu Keluarga (Tambah / Edit) ---
const isKkModalOpen = ref(false);
const editingKk = ref(null);

const kkForm = useForm({
    nomor_kk: '',
    dusun: '',
    rt: '001',
    rw: '001',
    alamat: '',
});

function openCreateKkModal() {
    editingKk.value = null;
    kkForm.reset();
    kkForm.clearErrors();
    kkForm.dusun = props.daftar_dusun?.[0] || '';
    kkForm.rt = '001';
    kkForm.rw = '001';
    isKkModalOpen.value = true;
}

function openEditKkModal(kkRow) {
    editingKk.value = kkRow;
    kkForm.clearErrors();
    kkForm.nomor_kk = kkRow.nomor_kk || '';
    kkForm.dusun = kkRow.dusun || '';
    kkForm.rt = kkRow.rt || '001';
    kkForm.rw = kkRow.rw || '001';
    kkForm.alamat = kkRow.alamat || '';
    isKkModalOpen.value = true;
}

function closeKkModal() {
    isKkModalOpen.value = false;
    editingKk.value = null;
}

function submitKkForm() {
    if (editingKk.value) {
        kkForm.put(
            resolveRoute(
                'panel.kependudukan.kk.update',
                editingKk.value.id,
                `/panel/kependudukan/kartu-keluarga/${editingKk.value.id}`,
            ),
            {
                preserveScroll: true,
                onSuccess: () => closeKkModal(),
            },
        );
    } else {
        kkForm.post(
            resolveRoute(
                'panel.kependudukan.kk.store',
                undefined,
                '/panel/kependudukan/kartu-keluarga',
            ),
            {
                preserveScroll: true,
                onSuccess: () => closeKkModal(),
            },
        );
    }
}

async function handleDeleteKk(kkRow) {
    const ok = await confirm.ask({
        title: 'Hapus Kartu Keluarga?',
        message: `Apakah Anda yakin ingin menghapus Kartu Keluarga No. ${kkRow.nomor_kk}? KK yang masih memiliki anggota keluarga tidak dapat dihapus.`,
        confirmText: 'Ya, Hapus KK',
        variant: 'danger',
    });

    if (!ok) return;

    router.delete(
        resolveRoute(
            'panel.kependudukan.kk.destroy',
            kkRow.id,
            `/panel/kependudukan/kartu-keluarga/${kkRow.id}`,
        ),
        {
            preserveScroll: true,
        },
    );
}

// --- Modal Form Penduduk (Tambah / Edit) ---
const isPendudukModalOpen = ref(false);
const editingPenduduk = ref(null);

const pendudukForm = useForm({
    kartu_keluarga_id: '',
    nik: '',
    nama_lengkap: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    jenis_kelamin: 'L',
    agama: 'Islam',
    pendidikan: '',
    pekerjaan: '',
    status_perkawinan: 'kawin',
    hubungan_keluarga: 'kepala_keluarga',
    status_kesejahteraan: 'non_prioritas',
    status_kependudukan: 'aktif',
});

function openCreatePendudukModal(preselectedKkId = null) {
    editingPenduduk.value = null;
    pendudukForm.reset();
    pendudukForm.clearErrors();
    pendudukForm.kartu_keluarga_id =
        preselectedKkId || props.kartu_keluarga_options?.[0]?.id || '';
    pendudukForm.jenis_kelamin = 'L';
    pendudukForm.agama = 'Islam';
    pendudukForm.status_perkawinan = 'kawin';
    pendudukForm.hubungan_keluarga = preselectedKkId
        ? 'anak'
        : 'kepala_keluarga';
    pendudukForm.status_kesejahteraan = 'non_prioritas';
    pendudukForm.status_kependudukan = 'aktif';
    isPendudukModalOpen.value = true;
}

function openEditPendudukModal(row) {
    editingPenduduk.value = row;
    pendudukForm.clearErrors();
    pendudukForm.kartu_keluarga_id = row.kartu_keluarga_id || '';
    pendudukForm.nik = row.nik || '';
    pendudukForm.nama_lengkap = row.nama_lengkap || '';
    pendudukForm.tempat_lahir = row.tempat_lahir || '';
    pendudukForm.tanggal_lahir = row.tanggal_lahir || '';
    pendudukForm.jenis_kelamin = row.jenis_kelamin || 'L';
    pendudukForm.agama = row.agama || 'Islam';
    pendudukForm.pendidikan = row.pendidikan || '';
    pendudukForm.pekerjaan = row.pekerjaan || '';
    pendudukForm.status_perkawinan = row.status_perkawinan || 'kawin';
    pendudukForm.hubungan_keluarga = row.hubungan_keluarga || 'lainnya';
    pendudukForm.status_kesejahteraan =
        row.status_kesejahteraan || 'non_prioritas';
    pendudukForm.status_kependudukan = row.status_kependudukan || 'aktif';
    isPendudukModalOpen.value = true;
}

function closePendudukModal() {
    isPendudukModalOpen.value = false;
    editingPenduduk.value = null;
}

function submitPendudukForm() {
    if (editingPenduduk.value) {
        pendudukForm.put(
            resolveRoute(
                'panel.kependudukan.penduduk.update',
                editingPenduduk.value.id,
                `/panel/kependudukan/penduduk/${editingPenduduk.value.id}`,
            ),
            {
                preserveScroll: true,
                onSuccess: () => closePendudukModal(),
            },
        );
    } else {
        pendudukForm.post(
            resolveRoute(
                'panel.kependudukan.penduduk.store',
                undefined,
                '/panel/kependudukan/penduduk',
            ),
            {
                preserveScroll: true,
                onSuccess: () => closePendudukModal(),
            },
        );
    }
}

async function handleDeletePenduduk(row) {
    const ok = await confirm.ask({
        title: 'Hapus Data Penduduk?',
        message: `Apakah Anda yakin ingin menghapus data penduduk "${row.nama_lengkap}" (NIK: ${row.nik})? Data akan diarsipkan (soft delete) agar histori surat dan Bansos tetap terjaga.`,
        confirmText: 'Ya, Hapus Penduduk',
        variant: 'danger',
    });

    if (!ok) return;

    router.delete(
        resolveRoute(
            'panel.kependudukan.penduduk.destroy',
            row.id,
            `/panel/kependudukan/penduduk/${row.id}`,
        ),
        {
            preserveScroll: true,
        },
    );
}

// --- Modal Mutasi / Reaktivasi Status Kependudukan ---
const isMutasiModalOpen = ref(false);
const mutasiTarget = ref(null);

const mutasiForm = useForm({
    status_kependudukan: 'pindah_keluar',
    tanggal_mutasi: new Date().toISOString().slice(0, 10),
    catatan_mutasi: '',
});

function openMutasiModal(row) {
    mutasiTarget.value = row;
    mutasiForm.clearErrors();
    mutasiForm.status_kependudukan =
        row.status_kependudukan === 'aktif' ? 'pindah_keluar' : 'aktif';
    mutasiForm.tanggal_mutasi =
        row.tanggal_mutasi || new Date().toISOString().slice(0, 10);
    mutasiForm.catatan_mutasi = row.catatan_mutasi || '';
    isMutasiModalOpen.value = true;
}

function closeMutasiModal() {
    isMutasiModalOpen.value = false;
    mutasiTarget.value = null;
}

const isReaktivasiPindahKeluar = computed(
    () =>
        mutasiTarget.value?.status_kependudukan === 'pindah_keluar' &&
        mutasiForm.status_kependudukan === 'aktif',
);

const isKoreksiMeninggalKeAktif = computed(
    () =>
        mutasiTarget.value?.status_kependudukan === 'meninggal_dunia' &&
        mutasiForm.status_kependudukan === 'aktif',
);

const isMutasiBlockedBySuratKematian = computed(
    () =>
        Boolean(mutasiTarget.value?.has_terbit_surat_kematian) &&
        mutasiTarget.value?.status_kependudukan === 'meninggal_dunia' &&
        mutasiForm.status_kependudukan !== 'meninggal_dunia',
);

async function submitMutasiForm() {
    if (!mutasiTarget.value || isMutasiBlockedBySuratKematian.value) {
        return;
    }

    const targetName = mutasiTarget.value.nama_lengkap;
    const nextStatus = mutasiForm.status_kependudukan;

    let confirmTitle = 'Konfirmasi Perubahan Status Warga';
    let confirmMessage = `Ubah status kependudukan "${targetName}"?`;
    let confirmVariant = 'warning';

    if (nextStatus === 'pindah_keluar') {
        confirmTitle = 'Mutasi Warga Pindah Keluar?';
        confirmMessage = `Status "${targetName}" akan diubah menjadi Pindah Keluar dan seluruh kepesertaan Bansos Aktif milik warga ini otomatis dinonaktifkan menjadi Pindah.`;
        confirmVariant = 'warning';
    } else if (nextStatus === 'meninggal_dunia') {
        confirmTitle = 'Mutasi Warga Meninggal Dunia?';
        confirmMessage = `Status "${targetName}" akan diubah menjadi Meninggal Dunia dan seluruh kepesertaan Bansos Aktif milik warga ini otomatis dinonaktifkan menjadi Meninggal Dunia.`;
        confirmVariant = 'danger';
    } else if (nextStatus === 'aktif') {
        confirmTitle = 'Reaktivasi / Koreksi Status Warga ke Aktif?';
        confirmMessage = `Status "${targetName}" akan dikembalikan menjadi Aktif. Kepesertaan Bansos lama yang telah nonaktif tidak otomatis aktif kembali.`;
        confirmVariant = 'warning';
    }

    const ok = await confirm.ask({
        title: confirmTitle,
        message: confirmMessage,
        confirmText: 'Ya, Simpan Perubahan',
        variant: confirmVariant,
    });

    if (!ok) return;

    mutasiForm.patch(
        resolveRoute(
            'panel.kependudukan.penduduk.mutasi',
            mutasiTarget.value.id,
            `/panel/kependudukan/penduduk/${mutasiTarget.value.id}/mutasi`,
        ),
        {
            preserveScroll: true,
            onSuccess: () => closeMutasiModal(),
        },
    );
}

// --- Modal Import Excel SIAK/Prodeskel & Tabel Ringkasan Baris Gagal ---
const isImportModalOpen = ref(false);
const importForm = useForm({
    file: null,
});

const importSummary = computed(
    () => page.props?.flash?.import_summary || null,
);

const importErrors = computed(() => {
    const fromFlashErrors = page.props?.flash?.import_errors;
    if (Array.isArray(fromFlashErrors) && fromFlashErrors.length > 0) {
        return fromFlashErrors;
    }
    const fromSummary = page.props?.flash?.import_summary?.failed_rows;
    if (Array.isArray(fromSummary) && fromSummary.length > 0) {
        return fromSummary;
    }
    return [];
});

watch(
    () => importErrors.value,
    (rows) => {
        if (Array.isArray(rows) && rows.length > 0) {
            isImportModalOpen.value = true;
        }
    },
    { immediate: true },
);

function openImportModal() {
    importForm.reset();
    importForm.clearErrors();
    isImportModalOpen.value = true;
}

function closeImportModal() {
    isImportModalOpen.value = false;
}

function submitImportExcel() {
    importForm.post(
        resolveRoute(
            'panel.kependudukan.import-excel',
            undefined,
            '/panel/kependudukan/import-excel',
        ),
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                importForm.reset();
                if (importErrors.value.length === 0) {
                    closeImportModal();
                }
            },
        },
    );
}
</script>

<template>
    <Head title="Master Data Kependudukan" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header Halaman & Aksi Utama (Khusus Operator Desa) -->
            <PageHeader
                title="Master Data Kependudukan"
                subtitle="Kelola data induk Kartu Keluarga, biodata warga, mutasi kependudukan, serta kategori kesejahteraan DTSEN."
            >
                <template v-if="isOperator" #actions>
                    <a
                        :href="exportExcelUrl"
                        data-testid="btn-export-excel"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Download class="h-4 w-4 text-slate-500" />
                        <span>Export Excel</span>
                    </a>

                    <BaseButton
                        variant="secondary"
                        :icon="Upload"
                        data-testid="btn-open-import-modal"
                        @click="openImportModal"
                    >
                        Import Excel
                    </BaseButton>

                    <BaseButton
                        variant="secondary"
                        :icon="Plus"
                        data-testid="btn-open-create-kk"
                        @click="openCreateKkModal"
                    >
                        Tambah KK
                    </BaseButton>

                    <BaseButton
                        variant="primary"
                        :icon="UserPlus"
                        data-testid="btn-open-create-penduduk"
                        @click="openCreatePendudukModal()"
                    >
                        Tambah Penduduk
                    </BaseButton>
                </template>
            </PageHeader>

            <!-- Ringkasan Kartu Statistik -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Penduduk Aktif"
                    :value="ringkasan?.total_penduduk_aktif ?? 0"
                    :subtext="`${formatNumber(ringkasan?.total_mutasi ?? 0)} warga berstatus mutasi non-aktif`"
                />
                <StatCard
                    label="Kartu Keluarga Aktif"
                    :value="ringkasan?.total_kk_aktif ?? 0"
                    :subtext="`Dari total ${formatNumber(ringkasan?.total_kk ?? 0)} KK terdaftar`"
                />
                <StatCard
                    label="Prioritas DTSEN (Desil 1–4)"
                    :value="ringkasan?.total_prioritas_dtsen_aktif ?? 0"
                    subtext="Warga aktif sasaran prioritas bantuan"
                />
                <StatCard
                    label="Pengingat Struktur KK"
                    :value="ringkasan?.jumlah_kk_perlu_update_kepala ?? 0"
                    subtext="KK dengan Kepala Keluarga non-aktif"
                />
            </div>

            <!-- Callout Pengingat Kepala Keluarga Non-Aktif -->
            <BaseCallout
                v-if="
                    (ringkasan?.jumlah_kk_perlu_update_kepala ?? 0) > 0 ||
                    page.props?.flash?.kepala_keluarga_reminder
                "
                variant="warning"
                data-testid="callout-kepala-keluarga-reminder"
                title="Pengingat Pembaruan Kepala Keluarga"
            >
                <p>
                    {{
                        page.props?.flash?.kepala_keluarga_reminder ||
                        `Terdapat ${ringkasan.jumlah_kk_perlu_update_kepala} Kartu Keluarga yang Kepala Keluarganya berstatus Meninggal Dunia atau Pindah Keluar sementara masih memiliki anggota keluarga aktif. Silakan perbarui struktur Kepala Keluarga pada KK terkait.`
                    }}
                </p>
            </BaseCallout>

            <!-- 2 Tab Kontekstual: Data Penduduk & Kartu Keluarga -->
            <ContextualTabs
                :model-value="activeTab"
                :tabs="tabsConfig"
                aria-label="Tab Master Data Kependudukan"
                @change="handleTabChange"
            />

            <!-- Filter Satu Baris (UnifiedFilterBar) -->
            <UnifiedFilterBar
                :model-value="searchQuery"
                search-placeholder="Cari NIK, Nama Lengkap, atau Nomor KK..."
                :filters="filterBarConfig"
                @search="handleSearch"
                @update:filter="handleFilterUpdate"
                @reset="handleResetFilters"
            />

            <!-- TAB 1: Data Penduduk (Maksimal 6 Kolom Ramping) -->
            <div v-if="activeTab === 'penduduk'" data-testid="tab-panel-penduduk">
                <DataTable
                    :columns="pendudukColumns"
                    :rows="penduduk"
                    :row-actions="getPendudukRowActions"
                    empty-text="Belum ada data penduduk yang sesuai dengan pencarian/filter."
                    :empty-action-label="isOperator ? '+ Tambah Penduduk' : ''"
                    @empty-action="openCreatePendudukModal()"
                >
                    <template #cell-nama_lengkap="{ row }">
                        <div class="space-y-0.5">
                            <button
                                type="button"
                                :data-testid="`open-penduduk-drawer-${row.id}`"
                                class="text-left font-semibold text-slate-900 hover:text-blue-700 hover:underline"
                                @click="openPendudukDrawer(row)"
                            >
                                {{ row.nama_lengkap }}
                            </button>
                            <p class="text-xs tabular-nums text-slate-500">
                                NIK: {{ row.nik }} &bull;
                                {{
                                    row.jenis_kelamin === 'P'
                                        ? 'Perempuan'
                                        : 'Laki-laki'
                                }}
                            </p>
                        </div>
                    </template>

                    <template #cell-nomor_kk="{ row }">
                        <div class="space-y-0.5">
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="font-medium tabular-nums text-slate-800"
                                >
                                    {{ row.nomor_kk || '-' }}
                                </span>
                                <AlertTriangle
                                    v-if="row.perlu_update_kepala_keluarga"
                                    class="h-3.5 w-3.5 shrink-0 text-amber-600"
                                    title="Kepala Keluarga pada KK ini berstatus non-aktif"
                                />
                            </div>
                            <p class="text-xs text-slate-500">
                                {{ formatHubungan(row.hubungan_keluarga) }}
                            </p>
                        </div>
                    </template>

                    <template #cell-dusun="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800">
                                {{ row.dusun || '-' }}
                            </p>
                            <p class="text-xs tabular-nums text-slate-500">
                                RT {{ row.rt || '001' }} / RW
                                {{ row.rw || '001' }}
                            </p>
                        </div>
                    </template>

                    <template #cell-tanggal_lahir="{ row }">
                        <div class="space-y-0.5">
                            <p class="text-xs font-medium text-slate-800">
                                {{ row.tempat_lahir }},
                                {{ formatDateId(row.tanggal_lahir) }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ row.pekerjaan || 'Belum bekerja' }}
                            </p>
                        </div>
                    </template>

                    <template #cell-status_kesejahteraan="{ row }">
                        <StatusBadge :status="row.status_kesejahteraan" />
                    </template>

                    <template #cell-status_kependudukan="{ row }">
                        <StatusBadge :status="row.status_kependudukan" />
                    </template>
                </DataTable>
            </div>

            <!-- TAB 2: Kartu Keluarga (Maksimal 5 Kolom Ramping) -->
            <div v-else data-testid="tab-panel-kk">
                <DataTable
                    :columns="kkColumns"
                    :rows="kartu_keluarga"
                    :row-actions="getKkRowActions"
                    empty-text="Belum ada data Kartu Keluarga yang sesuai dengan pencarian/filter."
                    :empty-action-label="isOperator ? '+ Tambah KK' : ''"
                    @empty-action="openCreateKkModal"
                >
                    <template #cell-nomor_kk="{ row }">
                        <div class="space-y-1">
                            <button
                                type="button"
                                :data-testid="`open-kk-drawer-${row.id}`"
                                class="text-left font-semibold tabular-nums text-slate-900 hover:text-blue-700 hover:underline"
                                @click="openKkDrawer(row)"
                            >
                                {{ row.nomor_kk }}
                            </button>
                            <div v-if="row.perlu_update_kepala_keluarga">
                                <span
                                    data-testid="badge-kk-warning"
                                    class="inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-800"
                                >
                                    <AlertTriangle class="h-3 w-3" />
                                    <span>Perlu Update Kepala KK</span>
                                </span>
                            </div>
                        </div>
                    </template>

                    <template #cell-kepala_keluarga="{ row }">
                        <div v-if="row.kepala_keluarga" class="space-y-0.5">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="font-medium text-slate-900">
                                    {{ row.kepala_keluarga.nama_lengkap }}
                                </span>
                                <StatusBadge
                                    v-if="
                                        row.kepala_keluarga
                                            .status_kependudukan !== 'aktif'
                                    "
                                    :status="
                                        row.kepala_keluarga.status_kependudukan
                                    "
                                />
                            </div>
                            <p class="text-xs tabular-nums text-slate-500">
                                NIK: {{ row.kepala_keluarga.nik }}
                            </p>
                        </div>
                        <span v-else class="text-xs italic text-slate-400">
                            Belum ada Kepala Keluarga
                        </span>
                    </template>

                    <template #cell-dusun="{ row }">
                        <div class="space-y-0.5">
                            <p class="font-medium text-slate-800">
                                {{ row.dusun }}
                            </p>
                            <p class="text-xs tabular-nums text-slate-500">
                                RT {{ row.rt }} / RW {{ row.rw }}
                            </p>
                        </div>
                    </template>

                    <template #cell-alamat="{ row }">
                        <span class="text-xs text-slate-600">
                            {{ row.alamat || '-' }}
                        </span>
                    </template>

                    <template #cell-jumlah_anggota="{ row }">
                        <div class="space-y-0.5 tabular-nums">
                            <span class="font-semibold text-slate-900">
                                {{ row.jumlah_anggota_aktif ?? 0 }} Aktif
                            </span>
                            <span class="text-xs text-slate-500">
                                / {{ row.jumlah_anggota ?? 0 }} Total Anggota
                            </span>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- SLIDE-OVER DRAWER: Detail Lengkap Warga & Daftar Anggota 1 KK -->
        <SlideOverDrawer
            :show="isDrawerOpen"
            :title="
                drawerMode === 'penduduk' && selectedPendudukDetail
                    ? `Detail Warga: ${selectedPendudukDetail.nama_lengkap}`
                    : `Kartu Keluarga No. ${selectedKkDetail?.nomor_kk || ''}`
            "
            :subtitle="
                selectedKkDetail
                    ? `${selectedKkDetail.dusun || ''} • RT ${selectedKkDetail.rt || '001'} / RW ${selectedKkDetail.rw || '001'}`
                    : ''
            "
            max-width="2xl"
            @close="closeDrawer"
        >
            <div class="space-y-6">
                <!-- Peringatan Kepala Keluarga Non-Aktif di Dalam Drawer -->
                <BaseCallout
                    v-if="selectedKkDetail?.perlu_update_kepala_keluarga"
                    variant="warning"
                    data-testid="drawer-kk-warning"
                    title="Struktur Kepala Keluarga Perlu Diperbarui"
                >
                    <p>
                        {{ selectedKkDetail.peringatan_kepala_keluarga }}
                    </p>
                </BaseCallout>

                <!-- Seksi Biodata Lengkap Individu (Jika dibuka dari Baris Penduduk) -->
                <div
                    v-if="selectedPendudukDetail"
                    data-testid="drawer-resident-biodata"
                    class="rounded-lg border border-slate-200 bg-slate-50/60 p-4 space-y-3"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                {{ selectedPendudukDetail.nama_lengkap }}
                            </h3>
                            <p class="text-xs tabular-nums text-slate-600">
                                NIK: {{ selectedPendudukDetail.nik }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <StatusBadge
                                :status="
                                    selectedPendudukDetail.status_kesejahteraan
                                "
                            />
                            <StatusBadge
                                :status="
                                    selectedPendudukDetail.status_kependudukan
                                "
                            />
                        </div>
                    </div>

                    <dl
                        class="grid grid-cols-1 gap-x-4 gap-y-2.5 border-t border-slate-200 pt-3 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="text-slate-500">Tempat, Tanggal Lahir</dt>
                            <dd class="font-medium text-slate-800">
                                {{ selectedPendudukDetail.tempat_lahir }},
                                {{
                                    formatDateId(
                                        selectedPendudukDetail.tanggal_lahir,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">
                                Jenis Kelamin & Agama
                            </dt>
                            <dd class="font-medium text-slate-800">
                                {{
                                    selectedPendudukDetail.jenis_kelamin === 'P'
                                        ? 'Perempuan'
                                        : 'Laki-laki'
                                }}
                                &bull; {{ selectedPendudukDetail.agama }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">
                                Hubungan & Status Kawin
                            </dt>
                            <dd class="font-medium text-slate-800">
                                {{
                                    formatHubungan(
                                        selectedPendudukDetail.hubungan_keluarga,
                                    )
                                }}
                                &bull;
                                {{
                                    formatPerkawinan(
                                        selectedPendudukDetail.status_perkawinan,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">
                                Pendidikan & Pekerjaan
                            </dt>
                            <dd class="font-medium text-slate-800">
                                {{
                                    selectedPendudukDetail.pendidikan || '-'
                                }}
                                &bull;
                                {{ selectedPendudukDetail.pekerjaan || '-' }}
                            </dd>
                        </div>
                        <div
                            v-if="selectedPendudukDetail.catatan_mutasi"
                            class="sm:col-span-2"
                        >
                            <dt class="text-slate-500">Catatan Mutasi</dt>
                            <dd class="font-medium text-slate-800">
                                {{ selectedPendudukDetail.catatan_mutasi }}
                                <span
                                    v-if="selectedPendudukDetail.tanggal_mutasi"
                                    class="text-slate-500"
                                >
                                    ({{
                                        formatDateId(
                                            selectedPendudukDetail.tanggal_mutasi,
                                        )
                                    }})
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Seksi Daftar Anggota Keluarga dalam 1 Kartu Keluarga -->
                <div
                    v-if="selectedKkDetail"
                    data-testid="drawer-kk-members"
                    class="space-y-3"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">
                                Daftar Anggota Kartu Keluarga ({{
                                    selectedKkDetail.nomor_kk
                                }})
                            </h3>
                            <p class="text-xs text-slate-500">
                                {{
                                    selectedKkDetail.alamat ||
                                    `${selectedKkDetail.dusun}, RT ${selectedKkDetail.rt}/RW ${selectedKkDetail.rw}`
                                }}
                            </p>
                        </div>

                        <BaseButton
                            v-if="isOperator"
                            size="sm"
                            variant="secondary"
                            :icon="UserPlus"
                            data-testid="drawer-add-member-btn"
                            @click="
                                closeDrawer();
                                openCreatePendudukModal(selectedKkDetail.id);
                            "
                        >
                            Tambah Anggota
                        </BaseButton>
                    </div>

                    <div
                        class="overflow-x-auto rounded-lg border border-slate-200"
                    >
                        <table
                            class="min-w-full divide-y divide-slate-200 text-xs"
                        >
                            <thead class="bg-slate-50">
                                <tr>
                                    <th
                                        class="px-3 py-2.5 text-left font-medium uppercase text-slate-500"
                                    >
                                        Nama & NIK
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-left font-medium uppercase text-slate-500"
                                    >
                                        Hubungan
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-left font-medium uppercase text-slate-500"
                                    >
                                        Tgl Lahir
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-left font-medium uppercase text-slate-500"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                <tr
                                    v-for="member in selectedKkDetail.anggota ||
                                    []"
                                    :key="member.id || member.nik"
                                >
                                    <td class="px-3 py-2.5">
                                        <p class="font-semibold text-slate-900">
                                            {{ member.nama_lengkap }}
                                        </p>
                                        <p class="tabular-nums text-slate-500">
                                            {{ member.nik }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2.5 text-slate-700">
                                        {{
                                            formatHubungan(
                                                member.hubungan_keluarga,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-2.5 tabular-nums text-slate-600"
                                    >
                                        {{ formatDateId(member.tanggal_lahir) }}
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <StatusBadge
                                            :status="member.status_kependudukan"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <template #footer>
                <BaseButton variant="secondary" @click="closeDrawer">
                    Tutup
                </BaseButton>
            </template>
        </SlideOverDrawer>

        <!-- MODAL FORM KARTU KELUARGA (Tambah / Ubah KK dengan DusunCombobox) -->
        <BaseModal
            :show="isKkModalOpen"
            :title="
                editingKk
                    ? 'Ubah Data Kartu Keluarga'
                    : 'Tambah Kartu Keluarga Baru'
            "
            subtitle="Pastikan 16 digit Nomor KK dan nama Dusun sesuai dokumen resmi kependudukan."
            max-width="lg"
            @close="closeKkModal"
        >
            <form
                id="form-kk"
                data-testid="form-kk"
                class="space-y-4"
                @submit.prevent="submitKkForm"
            >
                <div class="space-y-1.5">
                    <label
                        for="input-nomor-kk"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Nomor Kartu Keluarga (16 Digit)
                        <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="input-nomor-kk"
                        v-model="kkForm.nomor_kk"
                        type="text"
                        maxlength="16"
                        inputmode="numeric"
                        data-testid="input-nomor-kk"
                        placeholder="Contoh: 7203080101260001"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="kkForm.errors.nomor_kk"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ kkForm.errors.nomor_kk }}
                    </p>
                </div>

                <DusunCombobox
                    v-model="kkForm.dusun"
                    label="Dusun / Lingkungan"
                    :options="daftar_dusun"
                    :error="kkForm.errors.dusun"
                    required
                />

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label
                            for="input-rt"
                            class="block text-sm font-medium text-slate-700"
                        >
                            RT <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="input-rt"
                            v-model="kkForm.rt"
                            type="text"
                            maxlength="5"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                        />
                        <p
                            v-if="kkForm.errors.rt"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ kkForm.errors.rt }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="input-rw"
                            class="block text-sm font-medium text-slate-700"
                        >
                            RW <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="input-rw"
                            v-model="kkForm.rw"
                            type="text"
                            maxlength="5"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                        />
                        <p
                            v-if="kkForm.errors.rw"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ kkForm.errors.rw }}
                        </p>
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-alamat-kk"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Detail Alamat Jalan / Rumah
                    </label>
                    <textarea
                        id="input-alamat-kk"
                        v-model="kkForm.alamat"
                        rows="2"
                        placeholder="Contoh: Jl. Trans Palu-Donggala No. 12"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="kkForm.errors.alamat"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ kkForm.errors.alamat }}
                    </p>
                </div>
            </form>

            <template #footer>
                <BaseButton variant="secondary" @click="closeKkModal">
                    Batal
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="form-kk"
                    variant="primary"
                    :loading="kkForm.processing"
                    data-testid="submit-kk-btn"
                >
                    Simpan KK
                </BaseButton>
            </template>
        </BaseModal>

        <!-- MODAL FORM PENDUDUK (Tambah / Ubah Biodata Warga) -->
        <BaseModal
            :show="isPendudukModalOpen"
            :title="
                editingPenduduk
                    ? 'Ubah Biodata Penduduk'
                    : 'Tambah Data Penduduk Baru'
            "
            subtitle="Lengkapi biodata penduduk sesuai KTP/KK dan kategori kesejahteraan DTSEN."
            max-width="2xl"
            @close="closePendudukModal"
        >
            <form
                id="form-penduduk"
                data-testid="form-penduduk"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                @submit.prevent="submitPendudukForm"
            >
                <div class="space-y-1.5 sm:col-span-2">
                    <label
                        for="select-kk"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Kartu Keluarga (KK)
                        <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="select-kk"
                        v-model="pendudukForm.kartu_keluarga_id"
                        data-testid="select-kk"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="">-- Pilih Kartu Keluarga --</option>
                        <option
                            v-for="kkOpt in kartu_keluarga_options"
                            :key="kkOpt.id"
                            :value="kkOpt.id"
                        >
                            {{
                                kkOpt.label ||
                                `${kkOpt.nomor_kk} (${kkOpt.dusun})`
                            }}
                        </option>
                    </select>
                    <p
                        v-if="pendudukForm.errors.kartu_keluarga_id"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ pendudukForm.errors.kartu_keluarga_id }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-nik"
                        class="block text-sm font-medium text-slate-700"
                    >
                        NIK (16 Digit) <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="input-nik"
                        v-model="pendudukForm.nik"
                        type="text"
                        maxlength="16"
                        inputmode="numeric"
                        data-testid="input-nik"
                        placeholder="16 digit NIK"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="pendudukForm.errors.nik"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ pendudukForm.errors.nik }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-nama-lengkap"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Nama Lengkap <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="input-nama-lengkap"
                        v-model="pendudukForm.nama_lengkap"
                        type="text"
                        data-testid="input-nama-lengkap"
                        placeholder="Sesuai KTP/KK"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="pendudukForm.errors.nama_lengkap"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ pendudukForm.errors.nama_lengkap }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-tempat-lahir"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Tempat Lahir <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="input-tempat-lahir"
                        v-model="pendudukForm.tempat_lahir"
                        type="text"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="pendudukForm.errors.tempat_lahir"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ pendudukForm.errors.tempat_lahir }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-tanggal-lahir"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Tanggal Lahir <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="input-tanggal-lahir"
                        v-model="pendudukForm.tanggal_lahir"
                        type="date"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="pendudukForm.errors.tanggal_lahir"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ pendudukForm.errors.tanggal_lahir }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="select-jk"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Jenis Kelamin <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="select-jk"
                        v-model="pendudukForm.jenis_kelamin"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="L">Laki-laki (L)</option>
                        <option value="P">Perempuan (P)</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-agama"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Agama <span class="text-red-600">*</span>
                    </label>
                    <input
                        id="input-agama"
                        v-model="pendudukForm.agama"
                        type="text"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                </div>

                <div class="space-y-1.5">
                    <label
                        for="select-perkawinan"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Status Perkawinan <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="select-perkawinan"
                        v-model="pendudukForm.status_perkawinan"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="belum_kawin">Belum Kawin</option>
                        <option value="kawin">Kawin</option>
                        <option value="cerai_hidup">Cerai Hidup</option>
                        <option value="cerai_mati">Cerai Mati</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="select-hubungan"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Hubungan dalam Keluarga
                        <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="select-hubungan"
                        v-model="pendudukForm.hubungan_keluarga"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="kepala_keluarga">Kepala Keluarga</option>
                        <option value="istri">Istri</option>
                        <option value="anak">Anak</option>
                        <option value="lainnya">Lainnya</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-pendidikan"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Pendidikan Terakhir
                    </label>
                    <input
                        id="input-pendidikan"
                        v-model="pendudukForm.pendidikan"
                        type="text"
                        placeholder="Contoh: SLTA/Sederajat"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-pekerjaan"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Pekerjaan
                    </label>
                    <input
                        id="input-pekerjaan"
                        v-model="pendudukForm.pekerjaan"
                        type="text"
                        placeholder="Contoh: Petani/Pekebun"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                </div>

                <div class="space-y-1.5 sm:col-span-2">
                    <label
                        for="select-dtsen"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Status Kesejahteraan (DTSEN)
                        <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="select-dtsen"
                        v-model="pendudukForm.status_kesejahteraan"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="dtsen_desil_1">
                            DTSEN Desil 1 (Miskin Ekstrem)
                        </option>
                        <option value="dtsen_desil_2">
                            DTSEN Desil 2 (Miskin)
                        </option>
                        <option value="dtsen_desil_3_4">
                            DTSEN Desil 3–4 (Rentan Miskin)
                        </option>
                        <option value="non_prioritas">
                            Non-Prioritas (Desil 5–10 / Mampu)
                        </option>
                    </select>
                </div>
            </form>

            <template #footer>
                <BaseButton variant="secondary" @click="closePendudukModal">
                    Batal
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="form-penduduk"
                    variant="primary"
                    :loading="pendudukForm.processing"
                    data-testid="submit-penduduk-btn"
                >
                    Simpan Penduduk
                </BaseButton>
            </template>
        </BaseModal>

        <!-- MODAL MUTASI / REAKTIVASI STATUS KEPENDUDUKAN -->
        <BaseModal
            :show="isMutasiModalOpen"
            title="Mutasi / Reaktivasi Status Kependudukan"
            :subtitle="
                mutasiTarget
                    ? `${mutasiTarget.nama_lengkap} (NIK: ${mutasiTarget.nik})`
                    : ''
            "
            max-width="lg"
            @close="closeMutasiModal"
        >
            <form
                id="form-mutasi"
                data-testid="form-mutasi"
                class="space-y-4"
                @submit.prevent="submitMutasiForm"
            >
                <BaseCallout
                    v-if="isMutasiBlockedBySuratKematian"
                    variant="danger"
                    data-testid="mutasi-locked-surat-kematian"
                    title="Terkunci oleh Surat Keterangan Kematian Terbit"
                >
                    <p>
                        Warga ini telah memiliki Surat Keterangan Kematian
                        berstatus Terbit di Buku Agenda Surat Keluar. Batalkan
                        surat kematian terlebih dahulu apabila perubahan status
                        ini merupakan kekeliruan input.
                    </p>
                </BaseCallout>

                <div class="space-y-1.5">
                    <label
                        for="select-status-mutasi"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Status Kependudukan Baru
                        <span class="text-red-600">*</span>
                    </label>
                    <select
                        id="select-status-mutasi"
                        v-model="mutasiForm.status_kependudukan"
                        data-testid="select-status-mutasi"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="aktif">Aktif (Menetap di Desa)</option>
                        <option value="pindah_keluar">
                            Pindah Keluar Domisili
                        </option>
                        <option value="meninggal_dunia">Meninggal Dunia</option>
                    </select>
                    <p
                        v-if="mutasiForm.errors.status_kependudukan"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ mutasiForm.errors.status_kependudukan }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-tanggal-mutasi"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Tanggal Mutasi / Reaktivasi
                    </label>
                    <input
                        id="input-tanggal-mutasi"
                        v-model="mutasiForm.tanggal_mutasi"
                        type="date"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="mutasiForm.errors.tanggal_mutasi"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ mutasiForm.errors.tanggal_mutasi }}
                    </p>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="input-catatan-mutasi"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Catatan Mutasi / Keterangan
                        <span
                            v-if="isReaktivasiPindahKeluar"
                            class="text-red-600"
                        >
                            * (Wajib diisi untuk Reaktivasi)
                        </span>
                    </label>
                    <textarea
                        id="input-catatan-mutasi"
                        v-model="mutasiForm.catatan_mutasi"
                        rows="3"
                        data-testid="input-catatan-mutasi"
                        :placeholder="
                            isReaktivasiPindahKeluar
                                ? 'Contoh: Kembali menetap di Desa Tamarenja berdasarkan SKPWNI'
                                : isKoreksiMeninggalKeAktif
                                  ? 'Contoh: Koreksi salah pilih baris penduduk'
                                  : 'Contoh: Pindah domisili ke Kota Palu'
                        "
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                    <p
                        v-if="mutasiForm.errors.catatan_mutasi"
                        class="text-xs font-medium text-red-600"
                    >
                        {{ mutasiForm.errors.catatan_mutasi }}
                    </p>
                </div>
            </form>

            <template #footer>
                <BaseButton variant="secondary" @click="closeMutasiModal">
                    Batal
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="form-mutasi"
                    variant="primary"
                    :disabled="isMutasiBlockedBySuratKematian"
                    :loading="mutasiForm.processing"
                    data-testid="submit-mutasi-btn"
                >
                    Simpan Status
                </BaseButton>
            </template>
        </BaseModal>

        <!-- MODAL IMPORT EXCEL SIAK/PRODESKEL & TABEL RINGKASAN ERROR PER BARIS -->
        <BaseModal
            :show="isImportModalOpen"
            title="Import Massal Excel Kependudukan (SIAK / Prodeskel)"
            subtitle="Unggah berkas Excel (.xlsx/.xls/.csv) untuk menambah, memperbarui, atau memulihkan data KK & Penduduk."
            max-width="xl"
            @close="closeImportModal"
        >
            <form
                id="form-import-excel"
                data-testid="form-import-excel"
                class="space-y-4"
                @submit.prevent="submitImportExcel"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-blue-200 bg-blue-50/50 px-3.5 py-2.5 text-xs text-slate-700"
                >
                    <span>
                        Gunakan format kolom standar SIAK/Prodeskel agar proses
                        validasi berjalan lancar.
                    </span>
                    <a
                        :href="templateExcelUrl"
                        data-testid="link-download-template"
                        class="inline-flex items-center gap-1.5 font-semibold text-blue-700 hover:underline"
                    >
                        <FileSpreadsheet class="h-4 w-4" />
                        <span>Unduh Template Excel</span>
                    </a>
                </div>

                <FileDropzone
                    v-model="importForm.file"
                    label="Berkas Excel Kependudukan"
                    accept=".xlsx,.xls,.csv"
                    helper-text="Format .xlsx, .xls, atau .csv • Maks. 5 MB"
                    :error="importForm.errors.file"
                    required
                />

                <!-- Ringkasan & Tabel Baris Gagal Hasil Import -->
                <div
                    v-if="importErrors.length > 0"
                    data-testid="import-error-summary"
                    class="space-y-2.5 rounded-lg border border-red-200 bg-red-50/40 p-3.5"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs font-semibold text-red-800">
                            Daftar Baris Gagal Divalidasi ({{
                                importErrors.length
                            }}
                            Baris)
                        </p>
                        <span
                            v-if="importSummary"
                            class="text-xs tabular-nums text-slate-600"
                        >
                            Berhasil: {{ importSummary.berhasil ?? 0 }} &bull;
                            Gagal: {{ importSummary.gagal ?? importErrors.length }}
                        </span>
                    </div>

                    <div
                        class="max-h-52 overflow-y-auto rounded border border-red-200 bg-white"
                    >
                        <table
                            data-testid="import-error-summary-table"
                            class="min-w-full divide-y divide-slate-200 text-xs"
                        >
                            <thead class="bg-slate-50">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-slate-600"
                                    >
                                        Baris
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-slate-600"
                                    >
                                        NIK & Nama
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-slate-600"
                                    >
                                        Penyebab Kesalahan
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr
                                    v-for="(errRow, idx) in importErrors"
                                    :key="`err-${errRow.baris}-${idx}`"
                                >
                                    <td
                                        class="px-3 py-2 font-semibold tabular-nums text-slate-900"
                                    >
                                        #{{ errRow.baris }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        <p class="font-medium">
                                            {{ errRow.nama_lengkap || '-' }}
                                        </p>
                                        <p class="tabular-nums text-slate-500">
                                            {{ errRow.nik || '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-red-700">
                                        {{
                                            errRow.pesan ||
                                            (errRow.errors || []).join(' ')
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </form>

            <template #footer>
                <BaseButton variant="secondary" @click="closeImportModal">
                    Tutup
                </BaseButton>
                <BaseButton
                    type="submit"
                    form="form-import-excel"
                    variant="primary"
                    :loading="importForm.processing"
                    data-testid="submit-import-btn"
                >
                    Proses Import
                </BaseButton>
            </template>
        </BaseModal>
    </AppLayout>
</template>
