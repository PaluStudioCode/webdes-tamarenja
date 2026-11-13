<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ChevronDown,
    ChevronRight,
    Download,
    FileSpreadsheet,
    MoreHorizontal,
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
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
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

// --- State Filter & Expandable Baris KK ---
const searchQuery = ref(props.filters?.search || '');
const selectedDusun = ref(props.filters?.dusun || '');
const selectedStatusKependudukan = ref(props.filters?.status_kependudukan || '');
const selectedStatusKesejahteraan = ref(
    props.filters?.status_kesejahteraan || '',
);

const expandedKkIds = ref(new Set());
const collapsedByUserIds = ref(new Set());

const hasActiveMemberFilter = computed(
    () =>
        Boolean(searchQuery.value && searchQuery.value.trim() !== '') ||
        Boolean(selectedStatusKependudukan.value),
);

function isKkExpanded(row) {
    if (!row?.id) return false;
    if (expandedKkIds.value.has(row.id)) {
        return true;
    }
    if (
        hasActiveMemberFilter.value &&
        !collapsedByUserIds.value.has(row.id)
    ) {
        return true;
    }
    return false;
}

function toggleExpandKk(row) {
    if (!row?.id) return;
    const nextExpanded = new Set(expandedKkIds.value);
    const nextCollapsed = new Set(collapsedByUserIds.value);

    if (isKkExpanded(row)) {
        nextExpanded.delete(row.id);
        nextCollapsed.add(row.id);
    } else {
        nextExpanded.add(row.id);
        nextCollapsed.delete(row.id);
    }

    expandedKkIds.value = nextExpanded;
    collapsedByUserIds.value = nextCollapsed;
}

function isMemberMatchingFilter(member) {
    const q = (searchQuery.value || '').trim().toLowerCase();
    const statusFilter = selectedStatusKependudukan.value || '';

    const matchSearch =
        q !== '' &&
        ((member.nama_lengkap || '').toLowerCase().includes(q) ||
            (member.nik || '').toLowerCase().includes(q));
    const matchStatus =
        statusFilter !== '' && member.status_kependudukan === statusFilter;

    return matchSearch || matchStatus;
}

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
            { value: 'dtsen_desil_1', label: 'Desil 1' },
            { value: 'dtsen_desil_2', label: 'Desil 2' },
            { value: 'dtsen_desil_3_4', label: 'Desil 3–4' },
            { value: 'non_prioritas', label: 'Non-Prioritas' },
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

function handleSearch(val) {
    searchQuery.value = val;
    collapsedByUserIds.value = new Set();
    applyFilters({ search: val || undefined });
}

function handleFilterUpdate({ key, value }) {
    if (key === 'dusun') selectedDusun.value = value;
    if (key === 'status_kependudukan') selectedStatusKependudukan.value = value;
    if (key === 'status_kesejahteraan')
        selectedStatusKesejahteraan.value = value;
    collapsedByUserIds.value = new Set();
    applyFilters();
}

function handleResetFilters() {
    searchQuery.value = '';
    selectedDusun.value = '';
    selectedStatusKependudukan.value = '';
    selectedStatusKesejahteraan.value = '';
    collapsedByUserIds.value = new Set();
    applyFilters({
        search: undefined,
        dusun: undefined,
        status_kependudukan: undefined,
        status_kesejahteraan: undefined,
    });
}

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

const DTSEN_TABLE_LABELS = {
    dtsen_desil_1: 'Desil 1',
    dtsen_desil_2: 'Desil 2',
    dtsen_desil_3_4: 'Desil 3-4',
    non_prioritas: 'Non-Prioritas',
};

function formatDtsenTableLabel(val) {
    const key = val || 'non_prioritas';
    return DTSEN_TABLE_LABELS[key] || '';
}

function formatHubungan(val) {
    return HUBUNGAN_LABELS[val] || val || '-';
}

function formatPerkawinan(val) {
    return PERKAWINAN_LABELS[val] || val || '-';
}

// --- Kolom & Aksi Tabel Terpadu Kartu Keluarga (5 Kolom Ramping + Expandable Anggota) ---
const kkColumns = [
    { key: 'nomor_kk', label: 'Nomor KK' },
    { key: 'kepala_keluarga', label: 'Kepala Keluarga' },
    { key: 'dusun', label: 'Wilayah & Alamat' },
    { key: 'status_kesejahteraan', label: 'Kategori DTSEN' },
    { key: 'jumlah_anggota', label: 'Anggota Keluarga' },
];

function getKkRowActions(row) {
    return [
        {
            key: 'tambah_anggota',
            label: '+ Anggota',
            icon: UserPlus,
            operatorOnly: true,
            onClick: () => openCreatePendudukModal(row),
        },
        {
            key: 'edit',
            label: 'Ubah KK',
            icon: Pencil,
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

// --- Modal Form Kartu Keluarga (Tambah KK Sekaligus Anggota / Edit KK) ---
const isKkModalOpen = ref(false);
const editingKk = ref(null);
const isKkAssetsOpen = ref(false);

function hasKkHousingOrAssets(row) {
    if (!row) return false;
    return Boolean(
        row.status_kepemilikan_rumah ||
            row.kondisi_rumah ||
            row.jenis_listrik ||
            row.kamar_mandi ||
            row.penghasilan_bulanan ||
            row.kepemilikan_usaha ||
            row.kepemilikan_hewan_ternak ||
            row.kepemilikan_kendaraan_elektronik ||
            row.kepemilikan_shml ||
            row.kepemilikan_tanah_bidang ||
            row.kepemilikan_tanah_sawah ||
            row.tanaman,
    );
}

function createEmptyKkMember(hubungan = 'kepala_keluarga') {
    return {
        nik: '',
        nama_lengkap: '',
        hubungan_keluarga: hubungan,
        jenis_kelamin: hubungan === 'istri' ? 'P' : 'L',
        tempat_lahir: '',
        tanggal_lahir: '',
        agama: 'Islam',
        suku: '',
        kewarganegaraan: 'WNI',
        status_perkawinan: hubungan === 'anak' ? 'belum_kawin' : 'kawin',
        pendidikan: '',
        peserta_kip_pip: '',
        kelas_sekolah: '',
        pekerjaan: '',
        jenis_bpjs: '',
    };
}

const kkForm = useForm({
    nomor_kk: '',
    dusun: '',
    rt: '001',
    rw: '001',
    alamat: '',
    status_kesejahteraan: 'non_prioritas',
    status_kepemilikan_rumah: '',
    kondisi_rumah: '',
    jenis_listrik: '',
    kamar_mandi: '',
    penghasilan_bulanan: '',
    kepemilikan_usaha: '',
    kepemilikan_hewan_ternak: '',
    kepemilikan_kendaraan_elektronik: '',
    kepemilikan_shml: '',
    kepemilikan_tanah_bidang: '',
    kepemilikan_tanah_sawah: '',
    tanaman: '',
    anggota: [createEmptyKkMember('kepala_keluarga')],
});

function openCreateKkModal() {
    editingKk.value = null;
    isKkAssetsOpen.value = false;
    kkForm.reset();
    kkForm.clearErrors();
    kkForm.nomor_kk = '';
    kkForm.dusun = props.daftar_dusun?.[0] || '';
    kkForm.rt = '001';
    kkForm.rw = '001';
    kkForm.alamat = '';
    kkForm.status_kesejahteraan = 'non_prioritas';
    kkForm.status_kepemilikan_rumah = '';
    kkForm.kondisi_rumah = '';
    kkForm.jenis_listrik = '';
    kkForm.kamar_mandi = '';
    kkForm.penghasilan_bulanan = '';
    kkForm.kepemilikan_usaha = '';
    kkForm.kepemilikan_hewan_ternak = '';
    kkForm.kepemilikan_kendaraan_elektronik = '';
    kkForm.kepemilikan_shml = '';
    kkForm.kepemilikan_tanah_bidang = '';
    kkForm.kepemilikan_tanah_sawah = '';
    kkForm.tanaman = '';
    kkForm.anggota = [createEmptyKkMember('kepala_keluarga')];
    isKkModalOpen.value = true;
}

function addKkMemberRow() {
    const count = kkForm.anggota.length;
    const nextHubungan =
        count === 0 ? 'kepala_keluarga' : count === 1 ? 'istri' : 'anak';
    kkForm.anggota.push(createEmptyKkMember(nextHubungan));
}

function removeKkMemberRow(index) {
    if (kkForm.anggota.length > 1) {
        kkForm.anggota.splice(index, 1);
    }
}

function openEditKkModal(kkRow) {
    editingKk.value = kkRow;
    isKkAssetsOpen.value = hasKkHousingOrAssets(kkRow);
    kkForm.clearErrors();
    kkForm.nomor_kk = kkRow.nomor_kk || '';
    kkForm.dusun = kkRow.dusun || '';
    kkForm.rt = kkRow.rt || '001';
    kkForm.rw = kkRow.rw || '001';
    kkForm.alamat = kkRow.alamat || '';
    kkForm.status_kesejahteraan = kkRow.status_kesejahteraan || 'non_prioritas';
    kkForm.status_kepemilikan_rumah = kkRow.status_kepemilikan_rumah || '';
    kkForm.kondisi_rumah = kkRow.kondisi_rumah || '';
    kkForm.jenis_listrik = kkRow.jenis_listrik || '';
    kkForm.kamar_mandi = kkRow.kamar_mandi || '';
    kkForm.penghasilan_bulanan = kkRow.penghasilan_bulanan || '';
    kkForm.kepemilikan_usaha = kkRow.kepemilikan_usaha || '';
    kkForm.kepemilikan_hewan_ternak = kkRow.kepemilikan_hewan_ternak || '';
    kkForm.kepemilikan_kendaraan_elektronik =
        kkRow.kepemilikan_kendaraan_elektronik || '';
    kkForm.kepemilikan_shml = kkRow.kepemilikan_shml || '';
    kkForm.kepemilikan_tanah_bidang = kkRow.kepemilikan_tanah_bidang || '';
    kkForm.kepemilikan_tanah_sawah = kkRow.kepemilikan_tanah_sawah || '';
    kkForm.tanaman = kkRow.tanaman || '';
    kkForm.anggota = [];
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

// --- Modal Form Penduduk (Tambah Anggota ke KK / Edit Biodata) ---
const isPendudukModalOpen = ref(false);
const editingPenduduk = ref(null);
const targetKkForNewMember = ref(null);

const pendudukForm = useForm({
    kartu_keluarga_id: '',
    nik: '',
    nama_lengkap: '',
    tempat_lahir: '',
    tanggal_lahir: '',
    jenis_kelamin: 'L',
    agama: 'Islam',
    suku: '',
    kewarganegaraan: 'WNI',
    pendidikan: '',
    peserta_kip_pip: '',
    kelas_sekolah: '',
    pekerjaan: '',
    status_perkawinan: 'kawin',
    hubungan_keluarga: 'anak',
    jenis_bpjs: '',
    status_kependudukan: 'aktif',
});

function openCreatePendudukModal(kkRowOrId = null) {
    editingPenduduk.value = null;
    pendudukForm.reset();
    pendudukForm.clearErrors();

    const kkObj =
        kkRowOrId && typeof kkRowOrId === 'object'
            ? kkRowOrId
            : (props.kartu_keluarga?.data || []).find(
                  (k) => k.id === kkRowOrId,
              ) ||
              (props.kartu_keluarga_options || []).find(
                  (k) => k.id === kkRowOrId,
              ) ||
              null;

    targetKkForNewMember.value = kkObj;

    const initialKkId =
        kkObj?.id ||
        (typeof kkRowOrId === 'number' ? kkRowOrId : '') ||
        props.kartu_keluarga_options?.[0]?.id ||
        '';

    pendudukForm.kartu_keluarga_id = initialKkId;
    pendudukForm.jenis_kelamin = 'L';
    pendudukForm.agama = 'Islam';
    pendudukForm.suku = '';
    pendudukForm.kewarganegaraan = 'WNI';
    pendudukForm.peserta_kip_pip = '';
    pendudukForm.kelas_sekolah = '';
    pendudukForm.jenis_bpjs = '';
    pendudukForm.status_perkawinan = 'belum_kawin';
    pendudukForm.hubungan_keluarga = kkObj?.kepala_keluarga
        ? 'anak'
        : 'kepala_keluarga';
    pendudukForm.status_kependudukan = 'aktif';
    isPendudukModalOpen.value = true;
}

function openEditPendudukModal(row) {
    editingPenduduk.value = row;
    targetKkForNewMember.value = null;
    pendudukForm.clearErrors();
    pendudukForm.kartu_keluarga_id = row.kartu_keluarga_id || '';
    pendudukForm.nik = row.nik || '';
    pendudukForm.nama_lengkap = row.nama_lengkap || '';
    pendudukForm.tempat_lahir = row.tempat_lahir || '';
    pendudukForm.tanggal_lahir = row.tanggal_lahir || '';
    pendudukForm.jenis_kelamin = row.jenis_kelamin || 'L';
    pendudukForm.agama = row.agama || 'Islam';
    pendudukForm.suku = row.suku || '';
    pendudukForm.kewarganegaraan = row.kewarganegaraan || 'WNI';
    pendudukForm.pendidikan = row.pendidikan || '';
    pendudukForm.peserta_kip_pip = row.peserta_kip_pip || '';
    pendudukForm.kelas_sekolah = row.kelas_sekolah || '';
    pendudukForm.pekerjaan = row.pekerjaan || '';
    pendudukForm.status_perkawinan = row.status_perkawinan || 'kawin';
    pendudukForm.hubungan_keluarga = row.hubungan_keluarga || 'lainnya';
    pendudukForm.jenis_bpjs = row.jenis_bpjs || '';
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

// --- Modal Import Excel Kependudukan (2 Langkah: Pratinjau & Konfirmasi Simpan) ---
const isImportModalOpen = ref(false);
const isImportPreviewDismissed = ref(false);
const importPreviewTab = ref('all');

const importForm = useForm({
    file: null,
    mode: 'preview',
});

const importSummary = computed(() =>
    isImportPreviewDismissed.value
        ? null
        : page.props?.flash?.import_summary || null,
);

const importPreviewRows = computed(() => {
    const rows = importSummary.value?.preview_rows;
    return Array.isArray(rows) ? rows : [];
});

const isPreviewReady = computed(
    () =>
        Boolean(importSummary.value?.is_preview) &&
        importPreviewRows.value.length > 0,
);

const filteredImportPreviewRows = computed(() => {
    if (importPreviewTab.value === 'valid') {
        return importPreviewRows.value.filter((r) => r.aksi !== 'gagal');
    }
    if (importPreviewTab.value === 'error') {
        return importPreviewRows.value.filter((r) => r.aksi === 'gagal');
    }
    return importPreviewRows.value;
});

const importErrors = computed(() => {
    if (isImportPreviewDismissed.value) {
        return [];
    }
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
    () => [importErrors.value, importSummary.value],
    ([rows, summary]) => {
        if (
            (Array.isArray(rows) && rows.length > 0) ||
            Boolean(summary?.is_preview)
        ) {
            isImportPreviewDismissed.value = false;
            isImportModalOpen.value = true;
        }
    },
    { immediate: true },
);

function openImportModal() {
    isImportPreviewDismissed.value = true;
    importPreviewTab.value = 'all';
    importForm.reset();
    importForm.clearErrors();
    isImportModalOpen.value = true;
}

function resetImportPreview() {
    isImportPreviewDismissed.value = true;
    importPreviewTab.value = 'all';
    importForm.reset();
    importForm.clearErrors();
}

function closeImportModal() {
    isImportModalOpen.value = false;
}

function submitPreviewImportExcel() {
    isImportPreviewDismissed.value = false;
    importPreviewTab.value = 'all';
    importForm.mode = 'preview';
    importForm.post(
        resolveRoute(
            'panel.kependudukan.import-excel',
            undefined,
            '/panel/kependudukan/import-excel',
        ),
        {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function submitCommitImportExcel() {
    isImportPreviewDismissed.value = false;
    importForm.mode = 'commit';
    importForm.post(
        resolveRoute(
            'panel.kependudukan.import-excel',
            undefined,
            '/panel/kependudukan/import-excel',
        ),
        {
            forceFormData: true,
            preserveState: true,
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

function submitImportExcel() {
    if (isPreviewReady.value) {
        submitCommitImportExcel();
    } else {
        submitPreviewImportExcel();
    }
}

// --- Responsive Action Dropdown untuk Sub-Tabel Anggota ---
const openMemberMenuId = ref(null);

function toggleMemberMenu(id) {
    openMemberMenuId.value = openMemberMenuId.value === id ? null : id;
}

function handleMemberMenuOutsideClick(event) {
    if (
        openMemberMenuId.value !== null &&
        !event.target.closest('[data-testid^="member-more-container-"]')
    ) {
        openMemberMenuId.value = null;
    }
}

onMounted(() => {
    document.addEventListener('click', handleMemberMenuOutsideClick);
});

onUnmounted(() => {
    document.removeEventListener('click', handleMemberMenuOutsideClick);
});
</script>

<template>
    <Head title="Master Data Kependudukan" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header Halaman & Aksi Utama (Khusus Operator Desa) -->
            <PageHeader
                title="Master Data Kependudukan"
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
                        variant="primary"
                        :icon="Plus"
                        data-testid="btn-open-create-kk"
                        @click="openCreateKkModal"
                    >
                        Tambah KK
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

            <!-- Filter Satu Baris (UnifiedFilterBar) -->
            <UnifiedFilterBar
                :model-value="searchQuery"
                search-placeholder="Cari NIK, Nama Lengkap, atau Nomor KK..."
                :filters="filterBarConfig"
                @search="handleSearch"
                @update:filter="handleFilterUpdate"
                @reset="handleResetFilters"
            />

            <!-- TABEL TERPADU KARTU KELUARGA & RINCIAN ANGGOTA KELUARGA (Master-Detail Expandable) -->
            <div data-testid="table-kk-terpadu">
                <DataTable
                    :columns="kkColumns"
                    :rows="kartu_keluarga"
                    :row-actions="getKkRowActions"
                    :responsive-actions="true"
                    :is-row-expanded="isKkExpanded"
                    empty-text="Belum ada data Kartu Keluarga atau Penduduk yang sesuai dengan pencarian/filter."
                    :empty-action-label="isOperator ? '+ Tambah KK' : ''"
                    @empty-action="openCreateKkModal"
                >
                    <template #cell-nomor_kk="{ row }">
                        <div class="flex items-start gap-2">
                            <button
                                type="button"
                                :data-testid="`toggle-expand-kk-${row.id}`"
                                :aria-expanded="isKkExpanded(row)"
                                aria-label="Buka atau tutup rincian anggota keluarga"
                                class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded border border-slate-200 bg-white text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                @click="toggleExpandKk(row)"
                            >
                                <ChevronDown
                                    v-if="isKkExpanded(row)"
                                    class="h-3.5 w-3.5 text-blue-700"
                                />
                                <ChevronRight
                                    v-else
                                    class="h-3.5 w-3.5"
                                />
                            </button>

                            <div class="space-y-1">
                                <span
                                    class="font-semibold tabular-nums text-slate-900"
                                >
                                    {{ row.nomor_kk }}
                                </span>
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
                                <span
                                    class="text-xs font-normal tabular-nums text-slate-500"
                                >
                                    (RT {{ row.rt }} / RW {{ row.rw }})
                                </span>
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ row.alamat || '-' }}
                            </p>
                        </div>
                    </template>

                    <template #cell-status_kesejahteraan="{ row }">
                        <StatusBadge
                            :status="
                                row.status_kesejahteraan || 'non_prioritas'
                            "
                            :label="
                                formatDtsenTableLabel(row.status_kesejahteraan)
                            "
                        />
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

                    <!-- SUB-TABEL EXPANDABLE ANGGOTA KELUARGA DI DALAM 1 KK -->
                    <template #expanded-row="{ row }">
                        <div
                            :data-testid="`subtable-anggota-kk-${row.id}`"
                            class="rounded-lg border border-slate-200 bg-white shadow-xs"
                        >
                            <!-- Ringkasan Profil Hunian, Sosial-Ekonomi & Aset KK -->
                            <div
                                v-if="hasKkHousingOrAssets(row)"
                                :data-testid="`kk-assets-summary-${row.id}`"
                                class="grid grid-cols-1 gap-3 border-b border-slate-200 bg-slate-50/50 px-3.5 py-3 text-xs sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div class="space-y-0.5">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        Hunian &amp; Sanitasi
                                    </p>
                                    <p class="font-medium text-slate-800">
                                        {{
                                            row.status_kepemilikan_rumah ||
                                            '-'
                                        }}
                                        <span
                                            v-if="row.kondisi_rumah"
                                            class="text-slate-500"
                                        >
                                            ({{ row.kondisi_rumah }})
                                        </span>
                                    </p>
                                    <p class="text-slate-600">
                                        Listrik:
                                        {{ row.jenis_listrik || '-' }} &bull;
                                        KM: {{ row.kamar_mandi || '-' }}
                                    </p>
                                </div>

                                <div class="space-y-0.5">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        Ekonomi &amp; Usaha
                                    </p>
                                    <p class="font-medium text-slate-800">
                                        Penghasilan:
                                        {{ row.penghasilan_bulanan || '-' }}
                                    </p>
                                    <p class="text-slate-600">
                                        Usaha:
                                        {{
                                            row.kepemilikan_usaha ||
                                            'Tidak ada'
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-0.5">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        Ternak &amp; Kendaraan
                                    </p>
                                    <p class="font-medium text-slate-800">
                                        {{
                                            row.kepemilikan_hewan_ternak || '-'
                                        }}
                                    </p>
                                    <p class="text-slate-600">
                                        {{
                                            row.kepemilikan_kendaraan_elektronik ||
                                            '-'
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-0.5">
                                    <p
                                        class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                                    >
                                        Lahan &amp; Tanaman
                                    </p>
                                    <p class="font-medium text-slate-800">
                                        SHML: {{ row.kepemilikan_shml || '0' }}
                                        &bull; Bidang:
                                        {{
                                            row.kepemilikan_tanah_bidang || '0'
                                        }}
                                        <span
                                            v-if="row.kepemilikan_tanah_sawah"
                                        >
                                            &bull; Sawah:
                                            {{ row.kepemilikan_tanah_sawah }}
                                        </span>
                                    </p>
                                    <p
                                        class="text-slate-600 line-clamp-2"
                                        :title="row.tanaman || ''"
                                    >
                                        {{ row.tanaman || '-' }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-3.5 py-2"
                            >
                                <div class="flex items-center gap-2 text-xs">
                                    <Users class="h-3.5 w-3.5 text-slate-500" />
                                    <span class="font-semibold text-slate-800">
                                        Daftar Anggota Keluarga
                                    </span>
                                </div>
                            </div>

                            <div
                                v-if="(row.anggota || []).length > 0"
                                class="overflow-x-auto"
                            >
                                <table
                                    class="min-w-full divide-y divide-slate-200 text-xs"
                                >
                                    <thead class="bg-slate-50/50">
                                        <tr>
                                            <th
                                                class="px-3.5 py-2 text-left font-medium uppercase tracking-wider text-slate-500"
                                            >
                                                Warga &amp; NIK
                                            </th>
                                            <th
                                                class="px-3.5 py-2 text-left font-medium uppercase tracking-wider text-slate-500"
                                            >
                                                Hubungan &amp; Status Kawin
                                            </th>
                                            <th
                                                class="px-3.5 py-2 text-left font-medium uppercase tracking-wider text-slate-500"
                                            >
                                                Tempat, Tanggal Lahir
                                            </th>
                                            <th
                                                class="px-3.5 py-2 text-left font-medium uppercase tracking-wider text-slate-500"
                                            >
                                                Pendidikan &amp; Pekerjaan
                                            </th>
                                            <th
                                                class="px-3.5 py-2 text-left font-medium uppercase tracking-wider text-slate-500"
                                            >
                                                Status Warga
                                            </th>
                                            <th
                                                v-if="isOperator"
                                                class="px-3.5 py-2 text-right font-medium uppercase tracking-wider text-slate-500"
                                            >
                                                Aksi Anggota
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="member in row.anggota"
                                            :key="member.id || member.nik"
                                            :data-testid="`member-row-${member.id}`"
                                            :class="[
                                                'transition-colors',
                                                isMemberMatchingFilter(member)
                                                    ? 'bg-amber-50/90 hover:bg-amber-100/80'
                                                    : 'hover:bg-slate-50/80',
                                            ]"
                                        >
                                            <td
                                                :class="[
                                                    'px-3.5 py-2.5',
                                                    isMemberMatchingFilter(
                                                        member,
                                                    )
                                                        ? 'border-l-4 border-l-amber-500'
                                                        : '',
                                                ]"
                                            >
                                                <div
                                                    class="flex flex-wrap items-center gap-1.5"
                                                >
                                                    <p
                                                        class="font-semibold text-slate-900"
                                                    >
                                                        {{
                                                            member.nama_lengkap
                                                        }}
                                                    </p>
                                                    <span
                                                        v-if="
                                                            isMemberMatchingFilter(
                                                                member,
                                                            )
                                                        "
                                                        class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800"
                                                    >
                                                        Cocok
                                                    </span>
                                                </div>
                                                <p
                                                    class="tabular-nums text-slate-500"
                                                >
                                                    NIK: {{ member.nik }} &bull;
                                                    {{
                                                        member.jenis_kelamin ===
                                                        'P'
                                                            ? 'Perempuan'
                                                            : 'Laki-laki'
                                                    }}
                                                </p>
                                            </td>

                                            <td class="px-3.5 py-2.5">
                                                <p
                                                    class="font-medium text-slate-800"
                                                >
                                                    {{
                                                        formatHubungan(
                                                            member.hubungan_keluarga,
                                                        )
                                                    }}
                                                </p>
                                                <p class="text-slate-500">
                                                    {{
                                                        formatPerkawinan(
                                                            member.status_perkawinan,
                                                        )
                                                    }}
                                                    <span v-if="member.agama">
                                                        &bull;
                                                        {{ member.agama }}
                                                    </span>
                                                    <span v-if="member.suku">
                                                        &bull;
                                                        {{ member.suku }}
                                                    </span>
                                                    <span
                                                        v-if="
                                                            member.kewarganegaraan &&
                                                            member.kewarganegaraan !==
                                                                'WNI'
                                                        "
                                                    >
                                                        ({{
                                                            member.kewarganegaraan
                                                        }})
                                                    </span>
                                                </p>
                                            </td>

                                            <td
                                                class="px-3.5 py-2.5 tabular-nums text-slate-700"
                                            >
                                                <p class="font-medium">
                                                    {{
                                                        member.tempat_lahir ||
                                                        '-'
                                                    }},
                                                    {{
                                                        formatDateId(
                                                            member.tanggal_lahir,
                                                        )
                                                    }}
                                                </p>
                                            </td>

                                            <td class="px-3.5 py-2.5">
                                                <p
                                                    class="font-medium text-slate-800"
                                                >
                                                    {{
                                                        member.pekerjaan ||
                                                        'Belum bekerja'
                                                    }}
                                                </p>
                                                <p class="text-slate-500">
                                                    {{
                                                        member.pendidikan || '-'
                                                    }}
                                                    <span
                                                        v-if="
                                                            member.kelas_sekolah
                                                        "
                                                    >
                                                        (Kelas
                                                        {{
                                                            member.kelas_sekolah
                                                        }})
                                                    </span>
                                                </p>
                                                <div
                                                    v-if="
                                                        (member.peserta_kip_pip &&
                                                            member.peserta_kip_pip !==
                                                                'Tidak') ||
                                                        member.jenis_bpjs
                                                    "
                                                    class="mt-1 flex flex-wrap items-center gap-1"
                                                >
                                                    <span
                                                        v-if="
                                                            member.peserta_kip_pip &&
                                                            member.peserta_kip_pip !==
                                                                'Tidak'
                                                        "
                                                        class="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700"
                                                    >
                                                        {{
                                                            member.peserta_kip_pip
                                                        }}
                                                    </span>
                                                    <span
                                                        v-if="member.jenis_bpjs"
                                                        class="inline-flex items-center rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600"
                                                    >
                                                        BPJS:
                                                        {{ member.jenis_bpjs }}
                                                    </span>
                                                </div>
                                            </td>

                                            <td class="px-3.5 py-2.5">
                                                <div class="space-y-0.5">
                                                    <StatusBadge
                                                        :status="
                                                            member.status_kependudukan
                                                        "
                                                    />
                                                    <p
                                                        v-if="
                                                            member.catatan_mutasi
                                                        "
                                                        class="text-[11px] text-slate-500"
                                                    >
                                                        {{
                                                            member.catatan_mutasi
                                                        }}
                                                    </p>
                                                </div>
                                            </td>

                                            <td
                                                v-if="isOperator"
                                                class="px-3.5 py-2.5 text-right whitespace-nowrap"
                                            >
                                                <!-- Desktop (>= md): Inline action buttons -->
                                                <div
                                                    class="hidden items-center justify-end gap-1 md:inline-flex"
                                                >
                                                    <button
                                                        type="button"
                                                        :data-testid="`member-action-edit-${member.id}`"
                                                        class="inline-flex h-7 items-center gap-1 rounded px-2 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                                        @click="
                                                            openEditPendudukModal(
                                                                member,
                                                            )
                                                        "
                                                    >
                                                        <Pencil
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        <span>Ubah</span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        :data-testid="`member-action-mutasi-${member.id}`"
                                                        class="inline-flex h-7 items-center gap-1 rounded px-2 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                                        @click="
                                                            openMutasiModal(
                                                                member,
                                                            )
                                                        "
                                                    >
                                                        <RefreshCw
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        <span>Mutasi</span>
                                                    </button>

                                                    <button
                                                        type="button"
                                                        :data-testid="`member-action-hapus-${member.id}`"
                                                        class="inline-flex h-7 items-center gap-1 rounded px-2 text-xs font-medium text-red-600 transition-colors hover:bg-red-50"
                                                        @click="
                                                            handleDeletePenduduk(
                                                                member,
                                                            )
                                                        "
                                                    >
                                                        <Trash2
                                                            class="h-3.5 w-3.5"
                                                        />
                                                        <span>Hapus</span>
                                                    </button>
                                                </div>

                                                <!-- Mobile (< md): Titik Tiga (MoreHorizontal) Dropdown -->
                                                <div
                                                    :data-testid="`member-more-container-${member.id}`"
                                                    class="relative inline-block text-left md:hidden"
                                                >
                                                    <button
                                                        type="button"
                                                        :data-testid="`member-more-btn-${member.id}`"
                                                        aria-label="Aksi anggota lainnya"
                                                        class="inline-flex h-7 w-7 items-center justify-center rounded text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800"
                                                        @click.stop="toggleMemberMenu(member.id)"
                                                    >
                                                        <MoreHorizontal class="h-4 w-4" />
                                                    </button>

                                                    <div
                                                        v-if="openMemberMenuId === member.id"
                                                        :data-testid="`member-more-menu-${member.id}`"
                                                        class="absolute right-0 z-20 mt-1 w-36 rounded-lg border border-slate-200 bg-white py-1 shadow-md"
                                                    >
                                                        <button
                                                            type="button"
                                                            :data-testid="`member-action-menu-edit-${member.id}`"
                                                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                            @click="
                                                                openMemberMenuId = null;
                                                                openEditPendudukModal(member);
                                                            "
                                                        >
                                                            <Pencil class="h-3.5 w-3.5 text-slate-500" />
                                                            <span>Ubah</span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            :data-testid="`member-action-menu-mutasi-${member.id}`"
                                                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                            @click="
                                                                openMemberMenuId = null;
                                                                openMutasiModal(member);
                                                            "
                                                        >
                                                            <RefreshCw class="h-3.5 w-3.5 text-slate-500" />
                                                            <span>Mutasi</span>
                                                        </button>
                                                        <button
                                                            type="button"
                                                            :data-testid="`member-action-menu-hapus-${member.id}`"
                                                            class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-red-600 hover:bg-red-50"
                                                            @click="
                                                                openMemberMenuId = null;
                                                                handleDeletePenduduk(member);
                                                            "
                                                        >
                                                            <Trash2 class="h-3.5 w-3.5 text-red-500" />
                                                            <span>Hapus</span>
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div
                                v-else
                                class="px-4 py-4 text-center text-xs text-slate-500"
                            >
                                Belum ada anggota keluarga terdaftar pada KK
                                ini.
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>

        <!-- MODAL FORM KARTU KELUARGA (Tambah KK Sekaligus Anggota / Ubah KK) -->
        <BaseModal
            :show="isKkModalOpen"
            :title="
                editingKk
                    ? 'Ubah Data Kartu Keluarga'
                    : 'Tambah Kartu Keluarga Baru'
            "
            :max-width="editingKk ? '2xl' : '4xl'"
            @close="closeKkModal"
        >
            <form
                id="form-kk"
                data-testid="form-kk"
                class="space-y-6"
                @submit.prevent="submitKkForm"
            >
                <!-- Bagian 1: Data Kartu Keluarga & Domisili -->
                <div
                    :class="
                        editingKk
                            ? 'space-y-4'
                            : 'grid grid-cols-1 gap-4 sm:grid-cols-2'
                    "
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
                            for="select-kk-dtsen"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Status Kesejahteraan Keluarga (DTSEN)
                            <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="select-kk-dtsen"
                            v-model="kkForm.status_kesejahteraan"
                            data-testid="select-kk-dtsen"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                        >
                            <option value="dtsen_desil_1">
                                Desil 1
                            </option>
                            <option value="dtsen_desil_2">
                                Desil 2
                            </option>
                            <option value="dtsen_desil_3_4">
                                Desil 3–4
                            </option>
                            <option value="non_prioritas">
                                Non-Prioritas
                            </option>
                        </select>
                        <p
                            v-if="kkForm.errors.status_kesejahteraan"
                            class="text-xs font-medium text-red-600"
                        >
                            {{ kkForm.errors.status_kesejahteraan }}
                        </p>
                    </div>

                    <div
                        :class="
                            editingKk
                                ? 'space-y-1.5'
                                : 'space-y-1.5 sm:col-span-2'
                        "
                    >
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
                </div>

                <!-- Bagian 1B: Kondisi Hunian, Sosial-Ekonomi & Aset Keluarga (Collapsible) -->
                <div class="rounded-lg border border-slate-200 bg-slate-50/50">
                    <button
                        type="button"
                        data-testid="toggle-kk-assets-section"
                        class="flex w-full items-center justify-between px-4 py-3 text-left text-sm font-semibold text-slate-800 transition-colors hover:bg-slate-100/70"
                        @click="isKkAssetsOpen = !isKkAssetsOpen"
                    >
                        <div>
                            <span>
                                Kondisi Hunian, Sosial-Ekonomi &amp; Aset
                                Keluarga
                            </span>
                            <span
                                class="ml-1.5 text-xs font-normal text-slate-500"
                            >
                                (Opsional)
                            </span>
                        </div>
                        <ChevronDown
                            v-if="isKkAssetsOpen"
                            class="h-4 w-4 text-slate-500"
                        />
                        <ChevronRight
                            v-else
                            class="h-4 w-4 text-slate-500"
                        />
                    </button>

                    <div
                        v-if="isKkAssetsOpen"
                        data-testid="kk-assets-fields"
                        class="grid grid-cols-1 gap-4 border-t border-slate-200 p-4 sm:grid-cols-2"
                    >
                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Status Kepemilikan Rumah
                            </label>
                            <select
                                v-model="kkForm.status_kepemilikan_rumah"
                                data-testid="select-kk-status-rumah"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            >
                                <option value="">-- Pilih Status Rumah --</option>
                                <option value="Milik Sendiri">
                                    Milik Sendiri
                                </option>
                                <option value="Menumpang">Menumpang</option>
                                <option value="Sewa/Kontrak">
                                    Sewa/Kontrak
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Kondisi Bangunan Rumah
                            </label>
                            <select
                                v-model="kkForm.kondisi_rumah"
                                data-testid="select-kk-kondisi-rumah"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            >
                                <option value="">
                                    -- Pilih Kondisi Bangunan --
                                </option>
                                <option value="Permanen">Permanen</option>
                                <option value="Semi Permanen">
                                    Semi Permanen
                                </option>
                                <option value="Rumah Panggung">
                                    Rumah Panggung
                                </option>
                                <option value="Rumah Kayu">Rumah Kayu</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Jenis Listrik yang Digunakan
                            </label>
                            <input
                                v-model="kkForm.jenis_listrik"
                                type="text"
                                data-testid="input-kk-jenis-listrik"
                                placeholder="Contoh: PLS 450 watt / Manual 900 Watt / Nyalur"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Ketersediaan Kamar Mandi
                            </label>
                            <select
                                v-model="kkForm.kamar_mandi"
                                data-testid="select-kk-kamar-mandi"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            >
                                <option value="">-- Pilih --</option>
                                <option value="Ada">Ada</option>
                                <option value="Tidak Ada">Tidak Ada</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Penghasilan Rata-rata / Bulan
                            </label>
                            <input
                                v-model="kkForm.penghasilan_bulanan"
                                type="text"
                                data-testid="input-kk-penghasilan"
                                placeholder="Contoh: Rp 1.500.000"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Kepemilikan Usaha
                            </label>
                            <input
                                v-model="kkForm.kepemilikan_usaha"
                                type="text"
                                data-testid="input-kk-usaha"
                                placeholder="Contoh: Warung Sembako, Jual Kue"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Kepemilikan Hewan Ternak
                            </label>
                            <input
                                v-model="kkForm.kepemilikan_hewan_ternak"
                                type="text"
                                data-testid="input-kk-ternak"
                                placeholder="Contoh: Sapi Milik Sendiri (2), Ayam Kampung 15 ekor"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Kepemilikan Kendaraan &amp; Elektronik
                            </label>
                            <input
                                v-model="kkForm.kepemilikan_kendaraan_elektronik"
                                type="text"
                                data-testid="input-kk-kendaraan"
                                placeholder="Contoh: Motor 1, TV 1, HP 2, Kulkas 1"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            />
                        </div>

                        <div class="grid grid-cols-3 gap-3 sm:col-span-2">
                            <div class="space-y-1.5">
                                <label
                                    class="block text-xs font-medium text-slate-700"
                                >
                                    Jumlah SHML (Sertifikat)
                                </label>
                                <input
                                    v-model="kkForm.kepemilikan_shml"
                                    type="text"
                                    data-testid="input-kk-shml"
                                    placeholder="Contoh: 1"
                                    class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    class="block text-xs font-medium text-slate-700"
                                >
                                    Jumlah Tanah Bidang
                                </label>
                                <input
                                    v-model="kkForm.kepemilikan_tanah_bidang"
                                    type="text"
                                    data-testid="input-kk-tanah-bidang"
                                    placeholder="Contoh: 2"
                                    class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    class="block text-xs font-medium text-slate-700"
                                >
                                    Kepemilikan Tanah Sawah
                                </label>
                                <input
                                    v-model="kkForm.kepemilikan_tanah_sawah"
                                    type="text"
                                    data-testid="input-kk-tanah-sawah"
                                    placeholder="Contoh: 1 Bidang"
                                    class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                />
                            </div>
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label
                                class="block text-xs font-medium text-slate-700"
                            >
                                Komoditas Tanaman Perkebunan
                            </label>
                            <input
                                v-model="kkForm.tanaman"
                                type="text"
                                data-testid="input-kk-tanaman"
                                placeholder="Contoh: Kelapa 50 Pohon, Salak 200 Pohon, Coklat 40 Pohon, Pisang 15 Pohon"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            />
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Input Daftar Anggota Keluarga Dinamis (Khusus Saat Tambah KK Baru) -->
                <div
                    v-if="!editingKk"
                    data-testid="kk-members-repeater"
                    class="space-y-3 border-t border-slate-200 pt-4"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <div class="flex items-center gap-2">
                            <Users class="h-4 w-4 text-slate-600" />
                            <h3 class="text-sm font-semibold text-slate-900">
                                Daftar Anggota Keluarga ({{
                                    kkForm.anggota.length
                                }}
                                Orang)
                            </h3>
                        </div>

                        <button
                            type="button"
                            data-testid="btn-add-kk-member-row"
                            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-3 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100"
                            @click="addKkMemberRow"
                        >
                            <UserPlus class="h-3.5 w-3.5" />
                            <span>+ Tambah Baris Anggota</span>
                        </button>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(member, idx) in kkForm.anggota"
                            :key="`kk-member-${idx}`"
                            :data-testid="`kk-member-card-${idx}`"
                            class="rounded-lg border border-slate-200 bg-slate-50/60 p-3.5 space-y-3"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200/80 pb-2"
                            >
                                <span
                                    class="text-xs font-semibold text-slate-800"
                                >
                                    Anggota #{{ idx + 1 }} —
                                    {{
                                        formatHubungan(
                                            member.hubungan_keluarga,
                                        )
                                    }}
                                </span>

                                <button
                                    v-if="kkForm.anggota.length > 1"
                                    type="button"
                                    :data-testid="`btn-remove-kk-member-${idx}`"
                                    class="inline-flex items-center gap-1 text-xs font-medium text-red-600 hover:underline"
                                    @click="removeKkMemberRow(idx)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    <span>Hapus Baris</span>
                                </button>
                            </div>

                            <div
                                class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        NIK (16 Digit)
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <input
                                        v-model="member.nik"
                                        type="text"
                                        maxlength="16"
                                        inputmode="numeric"
                                        :data-testid="`input-kk-member-nik-${idx}`"
                                        placeholder="16 digit NIK"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                    <p
                                        v-if="
                                            kkForm.errors[
                                                `anggota.${idx}.nik`
                                            ]
                                        "
                                        class="text-[11px] font-medium text-red-600"
                                    >
                                        {{
                                            kkForm.errors[
                                                `anggota.${idx}.nik`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Nama Lengkap
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <input
                                        v-model="member.nama_lengkap"
                                        type="text"
                                        :data-testid="`input-kk-member-nama-${idx}`"
                                        placeholder="Sesuai KTP/KK"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                    <p
                                        v-if="
                                            kkForm.errors[
                                                `anggota.${idx}.nama_lengkap`
                                            ]
                                        "
                                        class="text-[11px] font-medium text-red-600"
                                    >
                                        {{
                                            kkForm.errors[
                                                `anggota.${idx}.nama_lengkap`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Hubungan Keluarga
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <select
                                        v-model="member.hubungan_keluarga"
                                        :data-testid="`select-kk-member-hubungan-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option value="kepala_keluarga">
                                            Kepala Keluarga
                                        </option>
                                        <option value="istri">Istri</option>
                                        <option value="anak">Anak</option>
                                        <option value="lainnya">
                                            Lainnya
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Jenis Kelamin
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <select
                                        v-model="member.jenis_kelamin"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option value="L">Laki-laki (L)</option>
                                        <option value="P">Perempuan (P)</option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Tempat Lahir
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <input
                                        v-model="member.tempat_lahir"
                                        type="text"
                                        :data-testid="`input-kk-member-tempat-lahir-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                    <p
                                        v-if="
                                            kkForm.errors[
                                                `anggota.${idx}.tempat_lahir`
                                            ]
                                        "
                                        class="text-[11px] font-medium text-red-600"
                                    >
                                        {{
                                            kkForm.errors[
                                                `anggota.${idx}.tempat_lahir`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Tanggal Lahir
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <input
                                        v-model="member.tanggal_lahir"
                                        type="date"
                                        :data-testid="`input-kk-member-tanggal-lahir-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs tabular-nums text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                    <p
                                        v-if="
                                            kkForm.errors[
                                                `anggota.${idx}.tanggal_lahir`
                                            ]
                                        "
                                        class="text-[11px] font-medium text-red-600"
                                    >
                                        {{
                                            kkForm.errors[
                                                `anggota.${idx}.tanggal_lahir`
                                            ]
                                        }}
                                    </p>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Agama
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <input
                                        v-model="member.agama"
                                        type="text"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Status Perkawinan
                                        <span class="text-red-600">*</span>
                                    </label>
                                    <select
                                        v-model="member.status_perkawinan"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option value="belum_kawin">
                                            Belum Kawin
                                        </option>
                                        <option value="kawin">Kawin</option>
                                        <option value="cerai_hidup">
                                            Cerai Hidup
                                        </option>
                                        <option value="cerai_mati">
                                            Cerai Mati
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Pendidikan Terakhir
                                    </label>
                                    <input
                                        v-model="member.pendidikan"
                                        type="text"
                                        placeholder="Contoh: SLTA/Sederajat"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Pekerjaan
                                    </label>
                                    <input
                                        v-model="member.pekerjaan"
                                        type="text"
                                        placeholder="Contoh: Petani/Pekebun"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Suku Bangsa
                                    </label>
                                    <input
                                        v-model="member.suku"
                                        type="text"
                                        :data-testid="`input-kk-member-suku-${idx}`"
                                        placeholder="Contoh: Kaili / Bugis"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Kewarganegaraan
                                    </label>
                                    <select
                                        v-model="member.kewarganegaraan"
                                        :data-testid="`select-kk-member-wn-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option value="WNI">WNI</option>
                                        <option value="WNA">WNA</option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Kepesertaan BPJS
                                    </label>
                                    <select
                                        v-model="member.jenis_bpjs"
                                        :data-testid="`select-kk-member-bpjs-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option value="">-- Pilih BPJS --</option>
                                        <option value="Pemerintah">
                                            Pemerintah (PBI)
                                        </option>
                                        <option value="Mandiri">Mandiri</option>
                                        <option value="Tidak Ada">
                                            Tidak Ada
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-1">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Peserta KIP / PIP
                                    </label>
                                    <select
                                        v-model="member.peserta_kip_pip"
                                        :data-testid="`select-kk-member-kip-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    >
                                        <option value="">-- Tidak / Belum --</option>
                                        <option value="Tidak">Tidak</option>
                                        <option value="PIP">PIP</option>
                                        <option value="KIP">KIP</option>
                                    </select>
                                </div>

                                <div class="space-y-1 sm:col-span-2">
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Kelas Sekolah Saat Ini (Anak Sekolah)
                                    </label>
                                    <input
                                        v-model="member.kelas_sekolah"
                                        type="text"
                                        :data-testid="`input-kk-member-kelas-${idx}`"
                                        placeholder="Contoh: PAUD / TK / 4 SD / 2 SMP / 1 SMA"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
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
                    {{
                        editingKk
                            ? 'Simpan Perubahan KK'
                            : 'Simpan KK & Anggota'
                    }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- MODAL FORM PENDUDUK (Tambah Anggota ke KK / Ubah Biodata Warga) -->
        <BaseModal
            :show="isPendudukModalOpen"
            :title="
                editingPenduduk
                    ? 'Ubah Biodata Penduduk'
                    : 'Tambah Anggota Keluarga'
            "
            max-width="2xl"
            @close="closePendudukModal"
        >
            <form
                id="form-penduduk"
                data-testid="form-penduduk"
                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                @submit.prevent="submitPendudukForm"
            >
                <div
                    v-if="!editingPenduduk && targetKkForNewMember"
                    class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700 sm:col-span-2"
                >
                    Kartu Keluarga Tujuan:
                    <span class="font-semibold tabular-nums text-slate-900">
                        {{ targetKkForNewMember.nomor_kk }}
                    </span>
                    <span class="text-slate-500">
                        ({{ targetKkForNewMember.dusun }})
                    </span>
                </div>

                <div
                    v-else
                    class="space-y-1.5 sm:col-span-2"
                >
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

                <div class="space-y-1.5">
                    <label
                        for="input-suku"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Suku Bangsa
                    </label>
                    <input
                        id="input-suku"
                        v-model="pendudukForm.suku"
                        type="text"
                        data-testid="input-suku"
                        placeholder="Contoh: Kaili / Bugis"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
                </div>

                <div class="space-y-1.5">
                    <label
                        for="select-kewarganegaraan"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Kewarganegaraan
                    </label>
                    <select
                        id="select-kewarganegaraan"
                        v-model="pendudukForm.kewarganegaraan"
                        data-testid="select-kewarganegaraan"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="WNI">WNI</option>
                        <option value="WNA">WNA</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="select-jenis-bpjs"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Kepesertaan BPJS Kesehatan
                    </label>
                    <select
                        id="select-jenis-bpjs"
                        v-model="pendudukForm.jenis_bpjs"
                        data-testid="select-jenis-bpjs"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="">-- Pilih Jenis BPJS --</option>
                        <option value="Pemerintah">Pemerintah (PBI)</option>
                        <option value="Mandiri">Mandiri</option>
                        <option value="Tidak Ada">Tidak Ada</option>
                    </select>
                </div>

                <div class="space-y-1.5">
                    <label
                        for="select-peserta-kip-pip"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Peserta Bantuan Pendidikan (KIP/PIP)
                    </label>
                    <select
                        id="select-peserta-kip-pip"
                        v-model="pendudukForm.peserta_kip_pip"
                        data-testid="select-peserta-kip-pip"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    >
                        <option value="">-- Tidak / Belum --</option>
                        <option value="Tidak">Tidak</option>
                        <option value="PIP">PIP</option>
                        <option value="KIP">KIP</option>
                    </select>
                </div>

                <div class="space-y-1.5 sm:col-span-2">
                    <label
                        for="input-kelas-sekolah"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Kelas Sekolah Saat Ini (Khusus Anak Sekolah)
                    </label>
                    <input
                        id="input-kelas-sekolah"
                        v-model="pendudukForm.kelas_sekolah"
                        type="text"
                        data-testid="input-kelas-sekolah"
                        placeholder="Contoh: PAUD / TK / 4 SD / 2 SMP / 1 SMA"
                        class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                    />
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
            max-width="lg"
            @close="closeMutasiModal"
        >
            <form
                id="form-mutasi"
                data-testid="form-mutasi"
                class="space-y-4"
                @submit.prevent="submitMutasiForm"
            >
                <div
                    v-if="mutasiTarget"
                    class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700"
                >
                    Warga: <span class="font-semibold text-slate-900">{{ mutasiTarget.nama_lengkap }}</span> (NIK: {{ mutasiTarget.nik }})
                </div>
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

        <!-- MODAL IMPORT EXCEL KEPENDUDUKAN (2 LANGKAH: PRATINJAU & KONFIRMASI SIMPAN) -->
        <BaseModal
            :show="isImportModalOpen"
            title="Import Excel Kependudukan"
            max-width="3xl"
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
                        Gunakan format kolom standar Template Excel atau berkas
                        hasil Export Excel agar validasi berjalan lancar.
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
                    v-if="!isPreviewReady"
                    v-model="importForm.file"
                    label="Berkas Excel Kependudukan"
                    accept=".xlsx,.xls,.csv"
                    helper-text="Format .xlsx, .xls, atau .csv • Maks. 5 MB"
                    :error="importForm.errors.file"
                    required
                />

                <!-- 4 Kartu Ringkasan Hasil Validasi / Pratinjau Import -->
                <div
                    v-if="importSummary"
                    data-testid="import-summary-cards"
                    class="grid grid-cols-2 gap-2.5 sm:grid-cols-4"
                >
                    <div
                        class="rounded-lg border border-emerald-200 bg-emerald-50/60 px-3 py-2"
                    >
                        <p class="text-[11px] font-medium text-emerald-800">
                            Baru Ditambahkan
                        </p>
                        <p
                            class="mt-0.5 text-lg font-bold tabular-nums text-emerald-900"
                        >
                            {{ importSummary.ditambahkan ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-blue-200 bg-blue-50/60 px-3 py-2"
                    >
                        <p class="text-[11px] font-medium text-blue-800">
                            Data Diperbarui
                        </p>
                        <p
                            class="mt-0.5 text-lg font-bold tabular-nums text-blue-900"
                        >
                            {{ importSummary.diperbarui ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-amber-200 bg-amber-50/60 px-3 py-2"
                    >
                        <p class="text-[11px] font-medium text-amber-800">
                            Arsip Dipulihkan
                        </p>
                        <p
                            class="mt-0.5 text-lg font-bold tabular-nums text-amber-900"
                        >
                            {{ importSummary.dipulihkan ?? 0 }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg border border-red-200 bg-red-50/60 px-3 py-2"
                    >
                        <p class="text-[11px] font-medium text-red-800">
                            Baris Bermasalah
                        </p>
                        <p
                            class="mt-0.5 text-lg font-bold tabular-nums text-red-900"
                        >
                            {{ importSummary.gagal ?? importErrors.length }}
                        </p>
                    </div>
                </div>

                <!-- Tabel Pratinjau Baris Sebelum Disimpan (Tahap 2) -->
                <div
                    v-if="isPreviewReady"
                    data-testid="import-preview-section"
                    class="space-y-2.5 rounded-lg border border-slate-200 bg-slate-50/50 p-3.5"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <p class="text-xs font-semibold text-slate-800">
                            Pratinjau Data Sebelum Disimpan ({{
                                importPreviewRows.length
                            }}
                            Baris)
                        </p>
                        <div class="inline-flex rounded-md border border-slate-200 bg-white p-0.5 text-xs">
                            <button
                                type="button"
                                :class="[
                                    'rounded px-2.5 py-1 font-medium transition-colors',
                                    importPreviewTab === 'all'
                                        ? 'bg-slate-900 text-white'
                                        : 'text-slate-600 hover:text-slate-900',
                                ]"
                                @click="importPreviewTab = 'all'"
                            >
                                Semua ({{ importPreviewRows.length }})
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded px-2.5 py-1 font-medium transition-colors',
                                    importPreviewTab === 'valid'
                                        ? 'bg-emerald-700 text-white'
                                        : 'text-slate-600 hover:text-slate-900',
                                ]"
                                @click="importPreviewTab = 'valid'"
                            >
                                Valid ({{ importSummary?.berhasil ?? 0 }})
                            </button>
                            <button
                                type="button"
                                :class="[
                                    'rounded px-2.5 py-1 font-medium transition-colors',
                                    importPreviewTab === 'error'
                                        ? 'bg-red-600 text-white'
                                        : 'text-slate-600 hover:text-slate-900',
                                ]"
                                @click="importPreviewTab = 'error'"
                            >
                                Error ({{ importSummary?.gagal ?? 0 }})
                            </button>
                        </div>
                    </div>

                    <div
                        class="max-h-60 overflow-y-auto rounded border border-slate-200 bg-white"
                    >
                        <table
                            data-testid="import-preview-table"
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
                                        Warga &amp; NIK
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-slate-600"
                                    >
                                        Nomor KK &amp; Dusun
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-slate-600"
                                    >
                                        Hubungan &amp; DTSEN
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium text-slate-600"
                                    >
                                        Status Review
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr
                                    v-for="(pRow, idx) in filteredImportPreviewRows"
                                    :key="`prev-${pRow.baris}-${idx}`"
                                    :class="
                                        pRow.aksi === 'gagal'
                                            ? 'bg-red-50/40'
                                            : ''
                                    "
                                >
                                    <td
                                        class="px-3 py-2 font-semibold tabular-nums text-slate-900"
                                    >
                                        #{{ pRow.baris }}
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        <p class="font-medium text-slate-900">
                                            {{ pRow.nama_lengkap || '-' }}
                                        </p>
                                        <p class="tabular-nums text-slate-500">
                                            {{ pRow.nik || '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        <p class="tabular-nums font-medium">
                                            {{ pRow.nomor_kk || '-' }}
                                        </p>
                                        <p class="text-slate-500">
                                            {{ pRow.dusun || '-' }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-700">
                                        <p class="font-medium">
                                            {{
                                                formatHubungan(
                                                    pRow.hubungan_keluarga,
                                                )
                                            }}
                                        </p>
                                        <p class="text-slate-500">
                                            {{
                                                formatDtsenTableLabel(
                                                    pRow.status_kesejahteraan,
                                                )
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-2">
                                        <span
                                            v-if="pRow.aksi === 'ditambahkan'"
                                            class="inline-flex rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"
                                        >
                                            Baru Ditambahkan
                                        </span>
                                        <span
                                            v-else-if="
                                                pRow.aksi === 'diperbarui'
                                            "
                                            class="inline-flex rounded bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-800"
                                        >
                                            Akan Diperbarui
                                        </span>
                                        <span
                                            v-else-if="
                                                pRow.aksi === 'dipulihkan'
                                            "
                                            class="inline-flex rounded bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800"
                                        >
                                            Akan Dipulihkan
                                        </span>
                                        <span
                                            v-else
                                            class="text-red-700 font-medium"
                                        >
                                            {{ pRow.pesan }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

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
                <BaseButton
                    v-if="isPreviewReady"
                    variant="secondary"
                    data-testid="btn-reset-import-preview"
                    @click="resetImportPreview"
                >
                    Ganti File Excel
                </BaseButton>
                <BaseButton
                    v-else
                    variant="secondary"
                    @click="closeImportModal"
                >
                    Tutup
                </BaseButton>

                <BaseButton
                    type="submit"
                    form="form-import-excel"
                    variant="primary"
                    :disabled="
                        isPreviewReady && (importSummary?.berhasil ?? 0) === 0
                    "
                    :loading="importForm.processing"
                    data-testid="submit-import-btn"
                >
                    {{
                        isPreviewReady
                            ? `Simpan ${importSummary?.berhasil ?? 0} Data Valid`
                            : 'Cek & Review Data'
                    }}
                </BaseButton>
            </template>
        </BaseModal>
    </AppLayout>
</template>
