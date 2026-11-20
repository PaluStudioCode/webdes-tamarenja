<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    AlertTriangle,
    ChevronDown,
    ChevronRight,
    Download,
    Eye,
    FileSpreadsheet,
    Loader2,
    MoreHorizontal,
    Pencil,
    Plus,
    RefreshCw,
    Search,
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

// --- State Filter & Modal Detail KK ---
const searchQuery = ref(props.filters?.search || '');
const selectedDusun = ref(props.filters?.dusun || '');
const selectedStatusKesejahteraan = ref(
    props.filters?.status_kesejahteraan || '',
);

const isDetailKkModalOpen = ref(false);
const detailKkSnapshot = ref(null);
const highlightedMemberId = ref(null);

const activeDetailKk = computed(() => {
    if (!detailKkSnapshot.value) return null;
    const rows = Array.isArray(props.kartu_keluarga?.data)
        ? props.kartu_keluarga.data
        : [];
    const fresh = rows.find((r) => r.id === detailKkSnapshot.value.id);
    return fresh || detailKkSnapshot.value;
});

function openDetailKkModal(row, focusMemberId = null) {
    if (!row) return;
    highlightedMemberId.value = focusMemberId;
    detailKkSnapshot.value = row;
    isDetailKkModalOpen.value = true;
}

function closeDetailKkModal() {
    isDetailKkModalOpen.value = false;
    highlightedMemberId.value = null;
}

// --- State & Logika Pencarian Per Individu (Realtime + Filter Options) ---
const isSearchIndividuModalOpen = ref(false);
const individuSearchQuery = ref('');
const individuFilterDusun = ref('');
const individuFilterStatus = ref('');
const individuFilterJk = ref('');
const individuFilterDtsen = ref('');
const individuFilterHubungan = ref('');
const individuSearchResults = ref([]);
const isSearchingIndividu = ref(false);
let individuSearchTimer = null;

const hasActiveIndividuFilter = computed(() =>
    Boolean(
        individuFilterDusun.value ||
            individuFilterStatus.value ||
            individuFilterJk.value ||
            individuFilterDtsen.value ||
            individuFilterHubungan.value,
    ),
);

const canRunIndividuSearch = computed(
    () =>
        individuSearchQuery.value.trim().length >= 2 ||
        hasActiveIndividuFilter.value,
);

function collectLocalMatchingMembers(rawQuery = individuSearchQuery.value) {
    const qLower = String(rawQuery || '')
        .trim()
        .toLowerCase();
    const filterDusun = individuFilterDusun.value;
    const filterStatus = individuFilterStatus.value;
    const filterJk = individuFilterJk.value;
    const filterDtsen = individuFilterDtsen.value;
    const filterHubungan = individuFilterHubungan.value;

    if (qLower.length < 2 && !hasActiveIndividuFilter.value) {
        return [];
    }

    const kkRows = Array.isArray(props.kartu_keluarga?.data)
        ? props.kartu_keluarga.data
        : [];
    const matched = [];
    for (const kk of kkRows) {
        for (const m of kk.anggota || []) {
            const memberDusun = m.dusun || kk.dusun || '';
            const memberStatus = m.status_kependudukan || 'aktif';
            const memberJk = m.jenis_kelamin || '';
            const memberDtsen =
                m.status_kesejahteraan ||
                kk.status_kesejahteraan ||
                'non_prioritas';
            const memberHubungan = m.hubungan_keluarga || '';

            if (filterDusun && memberDusun !== filterDusun) continue;
            if (filterStatus && memberStatus !== filterStatus) continue;
            if (filterJk && memberJk !== filterJk) continue;
            if (filterDtsen && memberDtsen !== filterDtsen) continue;
            if (filterHubungan && memberHubungan !== filterHubungan) continue;

            if (qLower !== '') {
                const matchName = (m.nama_lengkap || '')
                    .toLowerCase()
                    .includes(qLower);
                const matchNik = String(m.nik || '')
                    .toLowerCase()
                    .includes(qLower);
                const matchKk = String(kk.nomor_kk || '')
                    .toLowerCase()
                    .includes(qLower);
                if (!matchName && !matchNik && !matchKk) continue;
            }

            matched.push({
                ...m,
                nomor_kk: m.nomor_kk || kk.nomor_kk,
                dusun: memberDusun,
                rt: m.rt || kk.rt,
                rw: m.rw || kk.rw,
                alamat: m.alamat || kk.alamat,
                status_kesejahteraan: memberDtsen,
                kartu_keluarga: kk,
            });
        }
    }
    return matched;
}

async function runIndividuSearch(rawQuery = individuSearchQuery.value) {
    const trimmed = String(rawQuery || '').trim();
    if (trimmed.length < 2 && !hasActiveIndividuFilter.value) {
        individuSearchResults.value = [];
        isSearchingIndividu.value = false;
        return;
    }

    const localMatches = collectLocalMatchingMembers(trimmed);
    individuSearchResults.value = localMatches;
    isSearchingIndividu.value = true;

    try {
        const params = {
            all_status: 1,
        };
        if (trimmed !== '') params.q = trimmed;
        if (individuFilterDusun.value) params.dusun = individuFilterDusun.value;
        if (individuFilterStatus.value)
            params.status_kependudukan = individuFilterStatus.value;
        if (individuFilterJk.value)
            params.jenis_kelamin = individuFilterJk.value;
        if (individuFilterDtsen.value)
            params.status_kesejahteraan = individuFilterDtsen.value;
        if (individuFilterHubungan.value)
            params.hubungan_keluarga = individuFilterHubungan.value;

        const response = await axios.get(
            resolveRoute(
                'panel.kependudukan.cari-nik',
                undefined,
                '/panel/kependudukan/cari-nik',
            ),
            { params },
        );
        const remoteData = Array.isArray(response?.data?.data)
            ? response.data.data
            : [];
        if (remoteData.length > 0) {
            individuSearchResults.value = remoteData;
        }
    } catch {
        // Tetap gunakan hasil pencocokan lokal apabila request gagal
    } finally {
        isSearchingIndividu.value = false;
    }
}

function scheduleIndividuSearch(rawQuery = individuSearchQuery.value) {
    if (individuSearchTimer) {
        clearTimeout(individuSearchTimer);
    }

    const trimmed = String(rawQuery || '').trim();
    if (trimmed.length < 2 && !hasActiveIndividuFilter.value) {
        individuSearchResults.value = [];
        isSearchingIndividu.value = false;
        return;
    }

    individuSearchResults.value = collectLocalMatchingMembers(trimmed);

    individuSearchTimer = setTimeout(() => {
        runIndividuSearch(trimmed);
    }, 250);
}

function handleIndividuSearchInput(event) {
    const val = event.target.value;
    individuSearchQuery.value = val;
    scheduleIndividuSearch(val);
}

function handleIndividuFilterChange() {
    scheduleIndividuSearch(individuSearchQuery.value);
}

function resetIndividuSearchFilters() {
    individuSearchQuery.value = '';
    individuFilterDusun.value = '';
    individuFilterStatus.value = '';
    individuFilterJk.value = '';
    individuFilterDtsen.value = '';
    individuFilterHubungan.value = '';
    individuSearchResults.value = [];
    isSearchingIndividu.value = false;
    if (individuSearchTimer) {
        clearTimeout(individuSearchTimer);
        individuSearchTimer = null;
    }
}

function openSearchIndividuModal() {
    resetIndividuSearchFilters();
    isSearchIndividuModalOpen.value = true;
}

function closeSearchIndividuModal() {
    if (individuSearchTimer) {
        clearTimeout(individuSearchTimer);
        individuSearchTimer = null;
    }
    isSearchIndividuModalOpen.value = false;
}

async function handleShowKkFromIndividu(person) {
    if (!person) return;
    const memberId = person.id || null;
    closeSearchIndividuModal();

    const kkRows = Array.isArray(props.kartu_keluarga?.data)
        ? props.kartu_keluarga.data
        : [];
    const targetKkId = person.kartu_keluarga_id || person.kartu_keluarga?.id;
    const existingKk = kkRows.find(
        (r) =>
            (targetKkId && r.id === targetKkId) ||
            (person.nomor_kk && r.nomor_kk === person.nomor_kk),
    );

    if (existingKk) {
        openDetailKkModal(existingKk, memberId);
        return;
    }

    if (
        person.kartu_keluarga &&
        Array.isArray(person.kartu_keluarga.anggota)
    ) {
        openDetailKkModal(person.kartu_keluarga, memberId);
        return;
    }

    if (targetKkId) {
        try {
            const res = await axios.get(`/panel/kependudukan/kk/${targetKkId}`);
            if (res?.data?.data) {
                openDetailKkModal(res.data.data, memberId);
            }
        } catch {
            // Abaikan apabila gagal memuat detail KK
        }
    }
}

function isMemberMatchingFilter(member) {
    return Boolean(
        highlightedMemberId.value && member?.id === highlightedMemberId.value,
    );
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
        key: 'status_kesejahteraan',
        label: 'Kategori DTSEN',
        allLabel: 'Semua Desil DTSEN',
        value: selectedStatusKesejahteraan.value,
        options: [
            { value: 'dtsen_desil_1', label: 'Desil 1' },
            { value: 'dtsen_desil_2', label: 'Desil 2' },
            { value: 'dtsen_desil_3', label: 'Desil 3' },
            { value: 'dtsen_desil_4', label: 'Desil 4' },
            { value: 'dtsen_desil_5', label: 'Desil 5' },
            { value: 'non_prioritas', label: 'Desil 6-10' },
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
    applyFilters({ search: val || undefined });
}

function handleFilterUpdate({ key, value }) {
    if (key === 'dusun') selectedDusun.value = value;
    if (key === 'status_kesejahteraan')
        selectedStatusKesejahteraan.value = value;
    applyFilters();
}

function handleResetFilters() {
    searchQuery.value = '';
    selectedDusun.value = '';
    selectedStatusKesejahteraan.value = '';
    applyFilters({
        search: undefined,
        dusun: undefined,
        status_kesejahteraan: undefined,
    });
}

const HUBUNGAN_OPTIONS = [
    { value: 'kepala_keluarga', label: 'Kepala Keluarga' },
    { value: 'istri', label: 'Istri' },
    { value: 'anak', label: 'Anak' },
    { value: 'cucu', label: 'Cucu' },
    { value: 'orang_tua', label: 'Orang Tua' },
    { value: 'kakek', label: 'Kakek' },
    { value: 'nenek', label: 'Nenek' },
    { value: 'famili_lain', label: 'Famili Lain' },
    { value: 'lainnya', label: 'Lainnya' },
];

const HUBUNGAN_LABELS = {
    kepala_keluarga: 'Kepala Keluarga',
    istri: 'Istri',
    anak: 'Anak',
    cucu: 'Cucu',
    orang_tua: 'Orang Tua',
    kakek: 'Kakek',
    nenek: 'Nenek',
    famili_lain: 'Famili Lain',
    lainnya: 'Lainnya',
};

const PERKAWINAN_LABELS = {
    belum_kawin: 'Belum Kawin',
    kawin: 'Kawin',
    cerai_hidup: 'Cerai Hidup',
    cerai_mati: 'Cerai Mati',
};

const DTSEN_OPTIONS = [
    { value: 'dtsen_desil_1', label: 'Desil 1' },
    { value: 'dtsen_desil_2', label: 'Desil 2' },
    { value: 'dtsen_desil_3', label: 'Desil 3' },
    { value: 'dtsen_desil_4', label: 'Desil 4' },
    { value: 'dtsen_desil_5', label: 'Desil 5' },
    { value: 'non_prioritas', label: 'Desil 6-10' },
];

const DTSEN_TABLE_LABELS = {
    dtsen_desil_1: 'Desil 1',
    dtsen_desil_2: 'Desil 2',
    dtsen_desil_3: 'Desil 3',
    dtsen_desil_3_4: 'Desil 3',
    dtsen_desil_4: 'Desil 4',
    dtsen_desil_5: 'Desil 5',
    non_prioritas: 'Desil 6-10',
    dtsen_desil_6_10: 'Desil 6-10',
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

function calculateAge(tanggalLahir) {
    if (!tanggalLahir) return null;
    const raw = String(tanggalLahir).trim();
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(raw);

    let birthYear;
    let birthMonth;
    let birthDay;
    if (match) {
        birthYear = parseInt(match[1], 10);
        birthMonth = parseInt(match[2], 10);
        birthDay = parseInt(match[3], 10);
    } else {
        const parsed = new Date(raw);
        if (isNaN(parsed.getTime())) return null;
        birthYear = parsed.getFullYear();
        birthMonth = parsed.getMonth() + 1;
        birthDay = parsed.getDate();
    }

    const today = new Date();
    const curYear = today.getFullYear();
    const curMonth = today.getMonth() + 1;
    const curDay = today.getDate();

    let years = curYear - birthYear;
    const mDiff = curMonth - birthMonth;
    if (mDiff < 0 || (mDiff === 0 && curDay < birthDay)) {
        years--;
    }

    if (years < 0) return null;
    if (years === 0) {
        let months = (curYear - birthYear) * 12 + (curMonth - birthMonth);
        if (curDay < birthDay) {
            months--;
        }
        return months > 0 ? `${months} Bulan` : '< 1 Bulan';
    }

    return `${years} Tahun`;
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
            key: 'detail',
            label: 'Detail',
            icon: Eye,
            operatorOnly: false,
            onClick: () => openDetailKkModal(row),
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

const SCHOOL_ELIGIBLE_HUBUNGAN = ['anak', 'cucu', 'famili_lain', 'lainnya'];

function isAnakBelumKawin(target) {
    return (
        SCHOOL_ELIGIBLE_HUBUNGAN.includes(target?.hubungan_keluarga) &&
        target?.status_perkawinan === 'belum_kawin'
    );
}

function hasActiveKelasSekolah(target) {
    return (
        isAnakBelumKawin(target) &&
        Boolean(
            target?.kelas_sekolah &&
                String(target.kelas_sekolah).trim() !== '',
        )
    );
}

function derivePendidikanFromKelas(kelasRaw) {
    const val = String(kelasRaw || '').trim().toUpperCase();
    if (!val) return '';
    if (val.includes('PAUD') || val.includes('TK') || val.includes('BELUM')) {
        return 'Tidak/Belum Sekolah';
    }
    if (val.includes('SD') || val.includes('MI')) {
        return 'Belum Tamat SD/Sederajat';
    }
    if (val.includes('SMP') || val.includes('MTS') || val.includes('SLTP')) {
        return 'Tamat SD/Sederajat';
    }
    if (
        val.includes('SMA') ||
        val.includes('SMK') ||
        val.includes('MA') ||
        val.includes('SLTA')
    ) {
        return 'SLTP/Sederajat';
    }
    return '';
}

function normalizeMemberEducationFields(target) {
    if (!target) return;
    if (!isAnakBelumKawin(target)) {
        target.peserta_kip_pip = '';
        target.kelas_sekolah = '';
        return;
    }

    const derived = derivePendidikanFromKelas(target.kelas_sekolah);
    if (derived) {
        target.pendidikan = derived;
    }
}

function handleHubunganKeluargaChange(target) {
    if (!target) return;
    if (SCHOOL_ELIGIBLE_HUBUNGAN.includes(target.hubungan_keluarga)) {
        target.status_perkawinan = 'belum_kawin';
    } else if (target.hubungan_keluarga === 'istri') {
        target.jenis_kelamin = 'P';
        target.status_perkawinan = 'kawin';
    } else if (target.hubungan_keluarga === 'nenek') {
        target.jenis_kelamin = 'P';
    } else if (target.hubungan_keluarga === 'kakek') {
        target.jenis_kelamin = 'L';
    }
    normalizeMemberEducationFields(target);
}

function createEmptyKkMember(hubungan = 'kepala_keluarga') {
    return {
        nik: '',
        nama_lengkap: '',
        hubungan_keluarga: hubungan,
        jenis_kelamin:
            hubungan === 'istri' || hubungan === 'nenek' ? 'P' : 'L',
        tempat_lahir: '',
        tanggal_lahir: '',
        agama: 'Islam',
        suku: '',
        kewarganegaraan: 'WNI',
        status_perkawinan: SCHOOL_ELIGIBLE_HUBUNGAN.includes(hubungan)
            ? 'belum_kawin'
            : 'kawin',
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
    if (!editingKk.value && Array.isArray(kkForm.anggota)) {
        kkForm.anggota.forEach((m) => normalizeMemberEducationFields(m));
    }
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
    normalizeMemberEducationFields(pendudukForm);
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
                    label="Total Kartu Keluarga"
                    :value="ringkasan?.total_kk ?? ringkasan?.total_kk_aktif ?? 0"
                    :subtext="
                        (ringkasan?.total_kk_nonaktif ?? ((ringkasan?.total_kk ?? 0) - (ringkasan?.total_kk_aktif ?? 0))) > 0
                            ? `${formatNumber(ringkasan?.total_kk_aktif ?? 0)} KK aktif • ${formatNumber(ringkasan?.total_kk_nonaktif ?? ((ringkasan?.total_kk ?? 0) - (ringkasan?.total_kk_aktif ?? 0)))} KK keluarga mutasi`
                            : 'Seluruh KK berdomisili aktif di desa'
                    "
                />
                <StatCard
                    label="Prioritas DTSEN (Desil 1–5)"
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

            <!-- Filter Satu Baris (UnifiedFilterBar) + Aksi Cari Individu -->
            <UnifiedFilterBar
                :model-value="searchQuery"
                search-placeholder="Cari Nomor KK, Kepala Keluarga, atau Wilayah..."
                :filters="filterBarConfig"
                @search="handleSearch"
                @update:filter="handleFilterUpdate"
                @reset="handleResetFilters"
            >
                <BaseButton
                    type="button"
                    variant="secondary"
                    data-testid="btn-open-search-individu"
                    @click="openSearchIndividuModal"
                >
                    <Search class="h-4 w-4 text-slate-500" />
                    <span>Cari Individu</span>
                </BaseButton>
            </UnifiedFilterBar>

            <!-- TABEL KARTU KELUARGA (5 Kolom Bersih + Modal Detail KK) -->
            <div data-testid="table-kk-terpadu">
                <DataTable
                    :columns="kkColumns"
                    :rows="kartu_keluarga"
                    :row-actions="getKkRowActions"
                    :responsive-actions="true"
                    empty-text="Belum ada data Kartu Keluarga atau Penduduk yang sesuai dengan pencarian/filter."
                    :empty-action-label="isOperator ? '+ Tambah KK' : ''"
                    @empty-action="openCreateKkModal"
                >
                    <template #cell-nomor_kk="{ row }">
                        <div class="space-y-1">
                            <button
                                type="button"
                                :data-testid="`btn-detail-kk-${row.id}`"
                                class="text-left font-semibold tabular-nums text-slate-900 transition-colors hover:text-blue-700 hover:underline"
                                @click="openDetailKkModal(row)"
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
                        <div class="tabular-nums">
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

        <!-- MODAL PENCARIAN PER INDIVIDU (Realtime Search + Filter Options & Pintasan Lihat KK) -->
        <BaseModal
            :show="isSearchIndividuModalOpen"
            title="Pencarian Cepat Data Individu"
            subtitle="Cari warga berdasarkan nama, NIK, atau gunakan opsi filter untuk menampilkan data secara realtime dan melihat Kartu Keluarganya."
            max-width="3xl"
            @close="closeSearchIndividuModal"
        >
            <div data-testid="modal-search-individu" class="space-y-4">
                <!-- Input Pencarian Realtime -->
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-slate-400"
                    />
                    <input
                        :value="individuSearchQuery"
                        type="search"
                        data-testid="input-search-individu"
                        placeholder="Ketik Nama Lengkap atau NIK warga (minimal 2 karakter)..."
                        class="h-11 w-full rounded-lg border border-slate-300 bg-white pr-10 pl-10 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:ring-blue-700"
                        @input="handleIndividuSearchInput"
                    />
                    <Loader2
                        v-if="isSearchingIndividu"
                        class="pointer-events-none absolute top-1/2 right-3.5 h-4 w-4 -translate-y-1/2 animate-spin text-blue-600"
                    />
                </div>

                <!-- Opsi Filter Pencarian Individu -->
                <div
                    data-testid="search-individu-filters"
                    class="rounded-lg border border-slate-200 bg-slate-50/70 p-3"
                >
                    <div
                        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <select
                            v-model="individuFilterDusun"
                            data-testid="search-individu-filter-dusun"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs text-slate-700 focus:border-blue-700 focus:ring-blue-700"
                            @change="handleIndividuFilterChange"
                        >
                            <option value="">Semua Dusun</option>
                            <option
                                v-for="d in daftar_dusun || []"
                                :key="d"
                                :value="d"
                            >
                                {{ d }}
                            </option>
                        </select>

                        <select
                            v-model="individuFilterStatus"
                            data-testid="search-individu-filter-status"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs text-slate-700 focus:border-blue-700 focus:ring-blue-700"
                            @change="handleIndividuFilterChange"
                        >
                            <option value="">Semua Status Warga</option>
                            <option value="aktif">Aktif</option>
                            <option value="pindah_keluar">Pindah Keluar</option>
                            <option value="meninggal_dunia">
                                Meninggal Dunia
                            </option>
                        </select>

                        <select
                            v-model="individuFilterJk"
                            data-testid="search-individu-filter-jk"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs text-slate-700 focus:border-blue-700 focus:ring-blue-700"
                            @change="handleIndividuFilterChange"
                        >
                            <option value="">Semua Jenis Kelamin</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>

                        <select
                            v-model="individuFilterDtsen"
                            data-testid="search-individu-filter-dtsen"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs text-slate-700 focus:border-blue-700 focus:ring-blue-700"
                            @change="handleIndividuFilterChange"
                        >
                            <option value="">Semua Kategori DTSEN</option>
                            <option
                                v-for="opt in DTSEN_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>

                        <select
                            v-model="individuFilterHubungan"
                            data-testid="search-individu-filter-hubungan"
                            class="h-9 rounded-lg border border-slate-300 bg-white px-2.5 text-xs text-slate-700 focus:border-blue-700 focus:ring-blue-700"
                            @change="handleIndividuFilterChange"
                        >
                            <option value="">Semua Hubungan Keluarga</option>
                            <option
                                v-for="opt in HUBUNGAN_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>

                        <div class="flex items-center justify-end">
                            <button
                                v-if="
                                    hasActiveIndividuFilter ||
                                    individuSearchQuery.trim() !== ''
                                "
                                type="button"
                                data-testid="search-individu-reset-btn"
                                class="inline-flex h-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-3 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900"
                                @click="resetIndividuSearchFilters"
                            >
                                Reset Filter
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Hasil Pencarian Realtime -->
                <div
                    v-if="!canRunIndividuSearch"
                    class="rounded-lg border border-dashed border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-xs text-slate-500"
                >
                    Ketik minimal 2 karakter nama/NIK warga atau pilih salah satu opsi filter di atas untuk menampilkan data individu secara realtime.
                </div>

                <div
                    v-else-if="
                        !isSearchingIndividu &&
                        individuSearchResults.length === 0
                    "
                    data-testid="search-individu-empty"
                    class="rounded-lg border border-slate-200 bg-slate-50/60 px-4 py-8 text-center text-xs text-slate-500"
                >
                    Tidak ditemukan data individu yang sesuai dengan pencarian
                    atau filter yang dipilih.
                </div>

                <div
                    v-else
                    data-testid="search-individu-results"
                    class="max-h-[420px] divide-y divide-slate-200 overflow-y-auto rounded-lg border border-slate-200 bg-white"
                >
                    <div
                        v-for="person in individuSearchResults"
                        :key="person.id || person.nik"
                        :data-testid="`search-individu-item-${person.id}`"
                        class="flex flex-col justify-between gap-3 p-3.5 transition-colors hover:bg-slate-50/80 sm:flex-row sm:items-center"
                    >
                        <div class="space-y-1 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="text-sm font-semibold text-slate-900"
                                >
                                    {{ person.nama_lengkap }}
                                </span>
                                <StatusBadge
                                    :status="
                                        person.status_kependudukan || 'aktif'
                                    "
                                />
                                <StatusBadge
                                    :status="
                                        person.status_kesejahteraan ||
                                        'non_prioritas'
                                    "
                                    :label="
                                        formatDtsenTableLabel(
                                            person.status_kesejahteraan,
                                        )
                                    "
                                />
                            </div>

                            <p class="tabular-nums text-slate-600">
                                NIK:
                                <span class="font-medium text-slate-800">
                                    {{ person.nik }}
                                </span>
                                &bull;
                                {{
                                    person.jenis_kelamin === 'P'
                                        ? 'Perempuan'
                                        : 'Laki-laki'
                                }}
                                &bull;
                                {{ formatHubungan(person.hubungan_keluarga) }}
                            </p>

                            <p class="text-slate-500">
                                No. KK:
                                <span
                                    class="font-medium tabular-nums text-slate-700"
                                >
                                    {{
                                        person.nomor_kk ||
                                        person.kartu_keluarga?.nomor_kk ||
                                        '-'
                                    }}
                                </span>
                                &bull;
                                {{
                                    person.dusun ||
                                    person.kartu_keluarga?.dusun ||
                                    '-'
                                }}
                                (RT
                                {{
                                    person.rt ||
                                    person.kartu_keluarga?.rt ||
                                    '-'
                                }}
                                / RW
                                {{
                                    person.rw ||
                                    person.kartu_keluarga?.rw ||
                                    '-'
                                }})
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <BaseButton
                                type="button"
                                variant="primary"
                                size="sm"
                                :data-testid="`btn-lihat-kk-individu-${person.id}`"
                                @click="handleShowKkFromIndividu(person)"
                            >
                                <Eye class="h-3.5 w-3.5" />
                                <span>Lihat KK</span>
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <BaseButton
                    type="button"
                    variant="secondary"
                    @click="closeSearchIndividuModal"
                >
                    Tutup
                </BaseButton>
            </template>
        </BaseModal>

        <!-- MODAL DETAIL KARTU KELUARGA (Profil Hunian/Aset 12 Field & Daftar Anggota Keluarga) -->
        <BaseModal
            :show="isDetailKkModalOpen"
            :title="
                activeDetailKk
                    ? `Detail Kartu Keluarga — ${activeDetailKk.nomor_kk}`
                    : 'Detail Kartu Keluarga'
            "
            :subtitle="
                activeDetailKk
                    ? `${activeDetailKk.dusun || '-'} (RT ${activeDetailKk.rt || '-'} / RW ${activeDetailKk.rw || '-'}) • ${activeDetailKk.alamat || 'Alamat belum diisi'}`
                    : ''
            "
            max-width="6xl"
            @close="closeDetailKkModal"
        >
            <div
                v-if="activeDetailKk"
                data-testid="modal-detail-kk"
                class="space-y-4"
            >
                <!-- Peringatan Kepala Keluarga Non-Aktif -->
                <div
                    v-if="activeDetailKk.perlu_update_kepala_keluarga"
                    class="flex items-start gap-2.5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"
                >
                    <AlertTriangle
                        class="mt-0.5 h-4 w-4 shrink-0 text-amber-600"
                    />
                    <div>
                        <p class="font-semibold">
                            Perlu Pembaruan Kepala Keluarga
                        </p>
                        <p class="mt-0.5 text-amber-800">
                            {{
                                activeDetailKk.peringatan_kepala_keluarga ||
                                'Kepala Keluarga pada KK ini berstatus non-aktif sementara masih terdapat anggota keluarga aktif.'
                            }}
                        </p>
                    </div>
                </div>

                <!-- Baris Ringkasan KK & Pintasan Aksi Operator -->
                <div
                    class="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-slate-200 bg-slate-50/70 px-4 py-3"
                >
                    <div
                        class="grid flex-1 grid-cols-1 gap-4 text-xs sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <p class="text-slate-500">Kepala Keluarga</p>
                            <div
                                v-if="activeDetailKk.kepala_keluarga"
                                class="mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5"
                            >
                                <span class="font-semibold text-slate-900">
                                    {{
                                        activeDetailKk.kepala_keluarga
                                            .nama_lengkap
                                    }}
                                </span>
                                <span class="tabular-nums text-slate-500">
                                    ({{ activeDetailKk.kepala_keluarga.nik }})
                                </span>
                            </div>
                            <p
                                v-else
                                class="mt-0.5 italic text-slate-400"
                            >
                                Belum ditentukan
                            </p>
                        </div>

                        <div data-testid="kk-summary-domisili">
                            <p class="text-slate-500">Domisili &amp; Alamat</p>
                            <p class="mt-0.5 font-semibold text-slate-900">
                                {{ activeDetailKk.dusun || '-' }}
                                <span class="font-normal text-slate-600">
                                    (RT {{ activeDetailKk.rt || '-' }} / RW
                                    {{ activeDetailKk.rw || '-' }})
                                </span>
                            </p>
                            <p class="text-slate-600">
                                {{
                                    activeDetailKk.alamat ||
                                    'Alamat belum diisi'
                                }}
                            </p>
                        </div>

                        <div>
                            <p class="text-slate-500">Kategori DTSEN</p>
                            <div class="mt-0.5">
                                <StatusBadge
                                    :status="
                                        activeDetailKk.status_kesejahteraan ||
                                        'non_prioritas'
                                    "
                                    :label="
                                        formatDtsenTableLabel(
                                            activeDetailKk.status_kesejahteraan,
                                        )
                                    "
                                />
                            </div>
                        </div>

                        <div>
                            <p class="text-slate-500">Jumlah Anggota</p>
                            <p
                                class="mt-0.5 font-semibold tabular-nums text-slate-900"
                            >
                                {{ activeDetailKk.jumlah_anggota_aktif ?? 0 }}
                                Aktif
                                <span class="font-normal text-slate-500">
                                    /
                                    {{ activeDetailKk.jumlah_anggota ?? 0 }}
                                    Total
                                </span>
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="isOperator"
                        class="flex shrink-0 flex-wrap items-center gap-2"
                    >
                        <BaseButton
                            type="button"
                            variant="secondary"
                            size="sm"
                            data-testid="modal-detail-kk-btn-edit"
                            :leading-icon="Pencil"
                            @click="openEditKkModal(activeDetailKk)"
                        >
                            Ubah KK
                        </BaseButton>
                        <BaseButton
                            type="button"
                            variant="primary"
                            size="sm"
                            data-testid="modal-detail-kk-btn-add-member"
                            :leading-icon="UserPlus"
                            @click="openCreatePendudukModal(activeDetailKk)"
                        >
                            + Anggota
                        </BaseButton>
                    </div>
                </div>

                <!-- Ringkasan Profil Hunian, Sosial-Ekonomi & Aset KK (12 Field) -->
                <div
                    :data-testid="`kk-assets-summary-${activeDetailKk.id}`"
                    class="rounded-lg border border-slate-200 bg-white"
                >
                    <div
                        class="border-b border-slate-200 bg-slate-50/80 px-3.5 py-2 text-xs font-semibold text-slate-800"
                    >
                        Profil Hunian, Sosial-Ekonomi &amp; Aset Rumah Tangga
                    </div>
                    <div
                        class="grid grid-cols-1 gap-4 p-3.5 text-xs sm:grid-cols-2 lg:grid-cols-4"
                    >
                        <div class="space-y-1">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Hunian &amp; Sanitasi
                            </p>
                            <p class="text-slate-600">
                                Kepemilikan Rumah:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.status_kepemilikan_rumah ||
                                        '-'
                                    }}
                                </span>
                            </p>
                            <p class="text-slate-600">
                                Kondisi Bangunan:
                                <span class="font-medium text-slate-800">
                                    {{ activeDetailKk.kondisi_rumah || '-' }}
                                </span>
                            </p>
                            <p class="text-slate-600">
                                Listrik:
                                <span class="font-medium text-slate-800">
                                    {{ activeDetailKk.jenis_listrik || '-' }}
                                </span>
                            </p>
                            <p class="text-slate-600">
                                Kamar Mandi:
                                <span class="font-medium text-slate-800">
                                    {{ activeDetailKk.kamar_mandi || '-' }}
                                </span>
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Ekonomi &amp; Usaha
                            </p>
                            <p class="text-slate-600">
                                Penghasilan / Bulan:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.penghasilan_bulanan ||
                                        '-'
                                    }}
                                </span>
                            </p>
                            <p class="text-slate-600">
                                Kepemilikan Usaha:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.kepemilikan_usaha ||
                                        'Tidak ada'
                                    }}
                                </span>
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Ternak &amp; Kendaraan / Elektronik
                            </p>
                            <p class="text-slate-600">
                                Hewan Ternak:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.kepemilikan_hewan_ternak ||
                                        '-'
                                    }}
                                </span>
                            </p>
                            <p class="text-slate-600">
                                Kendaraan / Elektronik:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.kepemilikan_kendaraan_elektronik ||
                                        '-'
                                    }}
                                </span>
                            </p>
                        </div>

                        <div class="space-y-1">
                            <p
                                class="text-[11px] font-semibold uppercase tracking-wider text-slate-500"
                            >
                                Lahan &amp; Tanaman
                            </p>
                            <p class="text-slate-600">
                                Sertifikat (SHML):
                                <span class="font-medium text-slate-800">
                                    {{ activeDetailKk.kepemilikan_shml || '0' }}
                                </span>
                                &bull; Tanah Bidang:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.kepemilikan_tanah_bidang ||
                                        '0'
                                    }}
                                </span>
                            </p>
                            <p class="text-slate-600">
                                Tanah Sawah:
                                <span class="font-medium text-slate-800">
                                    {{
                                        activeDetailKk.kepemilikan_tanah_sawah ||
                                        '0'
                                    }}
                                </span>
                            </p>
                            <p
                                class="line-clamp-2 text-slate-600"
                                :title="activeDetailKk.tanaman || ''"
                            >
                                Tanaman:
                                <span class="font-medium text-slate-800">
                                    {{ activeDetailKk.tanaman || '-' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Tabel Daftar Anggota Keluarga -->
                <div
                    :data-testid="`subtable-anggota-kk-${activeDetailKk.id}`"
                    class="rounded-lg border border-slate-200 bg-white shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-3.5 py-2"
                    >
                        <div class="flex items-center gap-2 text-xs">
                            <Users class="h-3.5 w-3.5 text-slate-500" />
                            <span class="font-semibold text-slate-800">
                                Daftar Anggota Keluarga
                            </span>
                        </div>
                        <span class="text-[11px] text-slate-500">
                            Geser tabel ke samping bila layar kecil untuk
                            melihat seluruh kolom
                        </span>
                    </div>

                    <div
                        v-if="(activeDetailKk.anggota || []).length > 0"
                        class="overflow-x-auto"
                    >
                        <table
                            class="min-w-full divide-y divide-slate-200 text-xs"
                        >
                            <thead class="bg-slate-50/50">
                                <tr>
                                    <th
                                        class="px-3 py-2 text-center font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        No
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Nama &amp; NIK
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        L/P
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Hubungan
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        TTL &amp; Usia
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Status Kawin &amp; Identitas
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Pendidikan
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Pekerjaan
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        BPJS &amp; Bantuan
                                    </th>
                                    <th
                                        class="px-3 py-2 text-left font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Status Warga
                                    </th>
                                    <th
                                        v-if="isOperator"
                                        class="px-3 py-2 text-right font-medium uppercase tracking-wider text-slate-500 whitespace-nowrap"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="(
                                        member, memberIdx
                                    ) in activeDetailKk.anggota"
                                    :key="member.id || member.nik"
                                    :data-testid="`member-row-${member.id}`"
                                    :class="[
                                        'align-top transition-colors',
                                        isMemberMatchingFilter(member)
                                            ? 'bg-amber-50/90 hover:bg-amber-100/80'
                                            : 'hover:bg-slate-50/80',
                                    ]"
                                >
                                    <td
                                        :class="[
                                            'px-3 py-2.5 text-center tabular-nums text-slate-500',
                                            isMemberMatchingFilter(member)
                                                ? 'border-l-4 border-l-amber-500'
                                                : '',
                                        ]"
                                    >
                                        {{ memberIdx + 1 }}
                                    </td>

                                    <td class="px-3 py-2.5 min-w-[175px]">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <p
                                                class="font-semibold text-slate-900"
                                            >
                                                {{ member.nama_lengkap }}
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
                                            class="mt-0.5 tabular-nums text-slate-500"
                                        >
                                            NIK: {{ member.nik }}
                                        </p>
                                    </td>

                                    <td
                                        class="px-3 py-2.5 text-slate-700 whitespace-nowrap"
                                    >
                                        {{
                                            member.jenis_kelamin === 'P'
                                                ? 'Perempuan'
                                                : 'Laki-laki'
                                        }}
                                    </td>

                                    <td class="px-3 py-2.5 whitespace-nowrap">
                                        <span
                                            :class="[
                                                'inline-flex items-center rounded px-2 py-0.5 text-[11px] font-medium',
                                                member.hubungan_keluarga ===
                                                'kepala_keluarga'
                                                    ? 'bg-slate-800 text-white'
                                                    : 'bg-slate-100 text-slate-700',
                                            ]"
                                        >
                                            {{
                                                formatHubungan(
                                                    member.hubungan_keluarga,
                                                )
                                            }}
                                        </span>
                                    </td>

                                    <td
                                        class="px-3 py-2.5 tabular-nums text-slate-700 min-w-[155px]"
                                    >
                                        <p class="font-medium text-slate-800">
                                            {{ member.tempat_lahir || '-' }},
                                            {{
                                                formatDateId(
                                                    member.tanggal_lahir,
                                                    'medium',
                                                )
                                            }}
                                        </p>
                                        <p
                                            v-if="
                                                calculateAge(
                                                    member.tanggal_lahir,
                                                )
                                            "
                                            class="mt-0.5 text-[11px] text-slate-500"
                                        >
                                            Usia:
                                            {{
                                                calculateAge(
                                                    member.tanggal_lahir,
                                                )
                                            }}
                                        </p>
                                    </td>

                                    <td class="px-3 py-2.5 min-w-[150px]">
                                        <p class="font-medium text-slate-800">
                                            {{
                                                formatPerkawinan(
                                                    member.status_perkawinan,
                                                )
                                            }}
                                        </p>
                                        <p class="mt-0.5 text-[11px] text-slate-500">
                                            {{
                                                [
                                                    member.agama,
                                                    member.suku,
                                                    member.kewarganegaraan ||
                                                        'WNI',
                                                ]
                                                    .filter(Boolean)
                                                    .join(' • ')
                                            }}
                                        </p>
                                    </td>

                                    <td class="px-3 py-2.5 min-w-[135px]">
                                        <p class="font-medium text-slate-800">
                                            {{ member.pendidikan || '-' }}
                                        </p>
                                        <span
                                            v-if="member.kelas_sekolah"
                                            class="mt-1 inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-medium text-indigo-700"
                                        >
                                            Kelas {{ member.kelas_sekolah }}
                                        </span>
                                    </td>

                                    <td class="px-3 py-2.5 min-w-[120px]">
                                        <p class="font-medium text-slate-800">
                                            {{
                                                member.pekerjaan ||
                                                'Belum bekerja'
                                            }}
                                        </p>
                                    </td>

                                    <td class="px-3 py-2.5 min-w-[140px]">
                                        <p class="text-slate-600">
                                            BPJS:
                                            <span
                                                class="font-medium text-slate-800"
                                            >
                                                {{ member.jenis_bpjs || '-' }}
                                            </span>
                                        </p>
                                        <p
                                            v-if="member.peserta_kip_pip"
                                            class="mt-1 text-[11px] text-slate-600"
                                        >
                                            Pendidikan:
                                            <span
                                                v-if="
                                                    member.peserta_kip_pip !==
                                                    'Tidak'
                                                "
                                                class="ml-0.5 inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-medium text-blue-700"
                                            >
                                                {{ member.peserta_kip_pip }}
                                            </span>
                                            <span
                                                v-else
                                                class="ml-0.5 text-slate-500"
                                            >
                                                Tidak
                                            </span>
                                        </p>
                                    </td>

                                    <td class="px-3 py-2.5 min-w-[125px]">
                                        <div class="space-y-0.5">
                                            <StatusBadge
                                                :status="
                                                    member.status_kependudukan
                                                "
                                            />
                                            <p
                                                v-if="
                                                    member.tanggal_mutasi &&
                                                    member.status_kependudukan !==
                                                        'aktif'
                                                "
                                                class="text-[11px] tabular-nums text-slate-500"
                                            >
                                                Tgl:
                                                {{
                                                    formatDateId(
                                                        member.tanggal_mutasi,
                                                        'medium',
                                                    )
                                                }}
                                            </p>
                                            <p
                                                v-if="member.catatan_mutasi"
                                                class="text-[11px] text-slate-500"
                                            >
                                                {{ member.catatan_mutasi }}
                                            </p>
                                        </div>
                                    </td>

                                    <td
                                        v-if="isOperator"
                                        class="px-3.5 py-2.5 text-right whitespace-nowrap"
                                    >
                                        <!-- Desktop (>= md): Inline action buttons -->
                                        <div
                                            class="hidden items-center justify-end gap-1.5 md:inline-flex"
                                        >
                                            <button
                                                type="button"
                                                :data-testid="`member-action-edit-${member.id}`"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900"
                                                @click="
                                                    openEditPendudukModal(
                                                        member,
                                                    )
                                                "
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                <span>Ubah</span>
                                            </button>

                                            <button
                                                type="button"
                                                :data-testid="`member-action-mutasi-${member.id}`"
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900"
                                                @click="
                                                    openMutasiModal(member)
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
                                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-red-200 bg-white px-2.5 text-xs font-medium text-red-600 shadow-xs transition-colors hover:bg-red-50 hover:text-red-700"
                                                @click="
                                                    handleDeletePenduduk(
                                                        member,
                                                    )
                                                "
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
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
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-500 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-800"
                                                @click.stop="
                                                    toggleMemberMenu(member.id)
                                                "
                                            >
                                                <MoreHorizontal
                                                    class="h-4 w-4"
                                                />
                                            </button>

                                            <div
                                                v-if="
                                                    openMemberMenuId ===
                                                    member.id
                                                "
                                                :data-testid="`member-more-menu-${member.id}`"
                                                class="absolute right-0 z-20 mt-1 w-36 rounded-lg border border-slate-200 bg-white py-1 shadow-md"
                                            >
                                                <button
                                                    type="button"
                                                    :data-testid="`member-action-menu-edit-${member.id}`"
                                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-slate-700 hover:bg-slate-50"
                                                    @click="
                                                        openMemberMenuId = null;
                                                        openEditPendudukModal(
                                                            member,
                                                        );
                                                    "
                                                >
                                                    <Pencil
                                                        class="h-3.5 w-3.5 text-slate-500"
                                                    />
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
                                                    <RefreshCw
                                                        class="h-3.5 w-3.5 text-slate-500"
                                                    />
                                                    <span>Mutasi</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    :data-testid="`member-action-menu-hapus-${member.id}`"
                                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs font-medium text-red-600 hover:bg-red-50"
                                                    @click="
                                                        openMemberMenuId = null;
                                                        handleDeletePenduduk(
                                                            member,
                                                        );
                                                    "
                                                >
                                                    <Trash2
                                                        class="h-3.5 w-3.5 text-red-500"
                                                    />
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
                        Belum ada anggota keluarga terdaftar pada KK ini.
                    </div>
                </div>
            </div>

            <template #footer>
                <BaseButton
                    type="button"
                    variant="secondary"
                    @click="closeDetailKkModal"
                >
                    Tutup
                </BaseButton>
            </template>
        </BaseModal>

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
                            <option
                                v-for="opt in DTSEN_OPTIONS"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
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
                                        @change="
                                            handleHubunganKeluargaChange(member)
                                        "
                                    >
                                        <option
                                            v-for="opt in HUBUNGAN_OPTIONS"
                                            :key="opt.value"
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
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
                                        :data-testid="`select-kk-member-perkawinan-${idx}`"
                                        class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                        @change="
                                            normalizeMemberEducationFields(
                                                member,
                                            )
                                        "
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

                                <div
                                    v-if="!hasActiveKelasSekolah(member)"
                                    class="space-y-1"
                                >
                                    <label
                                        class="block text-xs font-medium text-slate-700"
                                    >
                                        Pendidikan Terakhir
                                    </label>
                                    <input
                                        v-model="member.pendidikan"
                                        type="text"
                                        :data-testid="`input-kk-member-pendidikan-${idx}`"
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

                                <template v-if="isAnakBelumKawin(member)">
                                    <div class="space-y-1">
                                        <label
                                            class="block text-xs font-medium text-slate-700"
                                        >
                                            Peserta Bantuan Pendidikan
                                        </label>
                                        <select
                                            v-model="member.peserta_kip_pip"
                                            :data-testid="`select-kk-member-kip-${idx}`"
                                            class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                        >
                                            <option value="">
                                                -- Tidak / Belum --
                                            </option>
                                            <option value="Tidak">Tidak</option>
                                            <option value="PIP">PIP</option>
                                            <option value="KIP">KIP</option>
                                        </select>
                                    </div>

                                    <div class="space-y-1 sm:col-span-2">
                                        <label
                                            class="block text-xs font-medium text-slate-700"
                                        >
                                            Kelas Sekolah Saat Ini (Khusus Anak
                                            Sekolah)
                                        </label>
                                        <input
                                            v-model="member.kelas_sekolah"
                                            type="text"
                                            :data-testid="`input-kk-member-kelas-${idx}`"
                                            placeholder="Contoh: PAUD / TK / 4 SD / 2 SMP / 1 SMA (Kosongkan jika sudah lulus)"
                                            class="h-9 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                                            @input="
                                                normalizeMemberEducationFields(
                                                    member,
                                                )
                                            "
                                        />
                                        <p
                                            v-if="hasActiveKelasSekolah(member)"
                                            :data-testid="`note-auto-pendidikan-kk-${idx}`"
                                            class="text-[11px] text-slate-500"
                                        >
                                            Pendidikan terakhir otomatis
                                            tercatat:
                                            <span
                                                class="font-medium text-slate-700"
                                            >
                                                {{
                                                    member.pendidikan ||
                                                    'Sesuai jenjang kelas'
                                                }}
                                            </span>
                                        </p>
                                    </div>
                                </template>
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
                        data-testid="select-perkawinan"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                        @change="normalizeMemberEducationFields(pendudukForm)"
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
                        data-testid="select-hubungan"
                        class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                        @change="handleHubunganKeluargaChange(pendudukForm)"
                    >
                        <option
                            v-for="opt in HUBUNGAN_OPTIONS"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>

                <div
                    v-if="!hasActiveKelasSekolah(pendudukForm)"
                    class="space-y-1.5"
                >
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
                        data-testid="input-pendidikan"
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

                <template v-if="isAnakBelumKawin(pendudukForm)">
                    <div class="space-y-1.5">
                        <label
                            for="select-peserta-kip-pip"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Peserta Bantuan Pendidikan
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
                            placeholder="Contoh: PAUD / TK / 4 SD / 2 SMP / 1 SMA (Kosongkan jika sudah lulus)"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-700 focus:ring-blue-700"
                            @input="normalizeMemberEducationFields(pendudukForm)"
                        />
                        <p
                            v-if="hasActiveKelasSekolah(pendudukForm)"
                            data-testid="note-auto-pendidikan-penduduk"
                            class="text-xs text-slate-500"
                        >
                            Pendidikan terakhir otomatis tercatat:
                            <span class="font-medium text-slate-700">
                                {{
                                    pendudukForm.pendidikan ||
                                    'Sesuai jenjang kelas'
                                }}
                            </span>
                        </p>
                    </div>
                </template>
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
