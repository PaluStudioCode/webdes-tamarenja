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
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import NikSearchAutocomplete from '@/Components/NikSearchAutocomplete.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
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
            total_per_kk: 0,
            total_perorangan: 0,
            total_kelompok: 0,
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
            { value: 'pkh', label: 'Program Keluarga Harapan (PKH)', kategori: 'per_kk', kategori_label: 'Bantuan Sosial Per KK', default_nominal: 'Rp 225.000 - Rp 750.000 / Tahap' },
            { value: 'bpnt', label: 'Bantuan Pangan Non-Tunai (BPNT) / Kartu Sembako', kategori: 'per_kk', kategori_label: 'Bantuan Sosial Per KK', default_nominal: 'Rp 200.000 / Bulan' },
            { value: 'blt_dd', label: 'BLT Dana Desa (BLT-DD)', kategori: 'per_kk', kategori_label: 'Bantuan Sosial Per KK', default_nominal: 'Rp 300.000 / Bulan' },
            { value: 'bantuan_pangan_beras', label: 'Bantuan Pangan Beras (CBP)', kategori: 'per_kk', kategori_label: 'Bantuan Sosial Per KK', default_nominal: 'Beras 10 Kg / Bulan' },
            { value: 'bantuan_daerah', label: 'Bantuan Pangan Daerah', kategori: 'per_kk', kategori_label: 'Bantuan Sosial Per KK', default_nominal: 'Paket Sembako Daerah' },
            { value: 'rtlh', label: 'Bantuan Rumah Tidak Layak Huni (RTLH)', kategori: 'per_kk', kategori_label: 'Bantuan Sosial Per KK', default_nominal: 'Material Bangunan / Rp 20.000.000' },
            { value: 'pip', label: 'Program Indonesia Pintar (PIP)', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Rp 450.000 - Rp 1.800.000 / Tahun' },
            { value: 'pbi_jkn', label: 'PBI-JK (Jaminan Kesehatan / KIS)', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Iuran BPJS Kesehatan Gratis' },
            { value: 'atensi', label: 'ATENSI (Asistensi Rehabilitasi Sosial)', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Alat Bantu / Sembako / Kewirausahaan' },
            { value: 'bantuan_lansia', label: 'Bantuan Lansia', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Uang Tunai / Paket Sembako Lansia' },
            { value: 'bantuan_disabilitas', label: 'Bantuan Penyandang Disabilitas', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Uang Tunai / Alat Bantu Disabilitas' },
            { value: 'bantuan_korban_bencana', label: 'Bantuan Korban Bencana', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Santunan / Logistik Darurat' },
            { value: 'beasiswa_daerah', label: 'Beasiswa Daerah', kategori: 'perorangan', kategori_label: 'Bantuan Sosial Perorangan', default_nominal: 'Beasiswa Pendidikan Daerah' },
            { value: 'bantuan_kelompok_tani', label: 'Bantuan Kelompok Tani (Poktan)', kategori: 'kelompok', kategori_label: 'Bantuan Kelompok Masyarakat', default_nominal: 'Bibit / Pupuk / Alsintan' },
            { value: 'bantuan_umkm', label: 'Bantuan UMKM / Usaha Ekonomi Produktif', kategori: 'kelompok', kategori_label: 'Bantuan Kelompok Masyarakat', default_nominal: 'Modal Usaha / Peralatan Produksi' },
            { value: 'bantuan_sarana_pertanian', label: 'Bantuan Sarana Pertanian / Perikanan', kategori: 'kelompok', kategori_label: 'Bantuan Kelompok Masyarakat', default_nominal: 'Pompa Air / Jaring / Sarana Produksi' },
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
    opsi_tahun: {
        type: Array,
        default: () => [],
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

const OPSI_KATEGORI_BANTUAN = [
    {
        value: 'per_kk',
        label: 'Bantuan Sosial Per KK',
        shortLabel: 'Per KK',
        desc: '1 KK = 1 penerima aktif per program (PKH, BPNT, BLT-DD, CBP, RTLH)',
    },
    {
        value: 'perorangan',
        label: 'Bantuan Sosial Perorangan',
        shortLabel: 'Perorangan',
        desc: 'Berbasis individu warga (PIP, PBI-JK/KIS, ATENSI, Lansia, Disabilitas)',
    },
    {
        value: 'kelompok',
        label: 'Bantuan Kelompok Masyarakat',
        shortLabel: 'Kelompok',
        desc: 'Berbasis kelompok/usaha warga (Kelompok Tani, UMKM, Sarana Pertanian)',
    },
];

function resolveKategoriByJenis(jenisValue) {
    const found = (props.opsi_jenis_bantuan || []).find((o) => o.value === jenisValue);
    if (found?.kategori) {
        return found.kategori;
    }
    if (['pip', 'pbi_jkn', 'atensi', 'bantuan_lansia', 'bantuan_disabilitas', 'bantuan_korban_bencana', 'beasiswa_daerah'].includes(jenisValue)) {
        return 'perorangan';
    }
    if (['bantuan_kelompok_tani', 'bantuan_umkm', 'bantuan_sarana_pertanian'].includes(jenisValue)) {
        return 'kelompok';
    }
    return 'per_kk';
}

function resolveKategoriLabelByJenis(jenisValue) {
    const kat = resolveKategoriByJenis(jenisValue);
    const found = OPSI_KATEGORI_BANTUAN.find((k) => k.value === kat);
    return found?.label || 'Bantuan Sosial Per KK';
}

const searchQuery = ref(props.filters?.search || '');
const selectedKategori = ref(props.filters?.kategori_bantuan || '');
const selectedJenis = ref(props.filters?.jenis_bantuan || '');
const selectedTahap = ref(props.filters?.tahap_penyaluran || '');
const selectedTahun = ref(
    props.filters?.tahun_anggaran ? String(props.filters.tahun_anggaran) : '',
);

const opsiTahunList = computed(() => {
    const yearsFromRows = (props.penerima_bansos || [])
        .map((r) => Number(r.tahun_anggaran))
        .filter((y) => !Number.isNaN(y) && y > 0);
    const baseYears =
        Array.isArray(props.opsi_tahun) && props.opsi_tahun.length > 0
            ? props.opsi_tahun.map((y) => Number(y))
            : [
                  props.tahun_anggaran - 1,
                  props.tahun_anggaran,
                  props.tahun_anggaran + 1,
              ];
    return Array.from(new Set([...baseYears, ...yearsFromRows])).sort(
        (a, b) => b - a,
    );
});

const filteredOpsiJenisFilter = computed(() => {
    if (!selectedKategori.value) {
        return props.opsi_jenis_bantuan || [];
    }
    return (props.opsi_jenis_bantuan || []).filter(
        (opt) => (opt.kategori || resolveKategoriByJenis(opt.value)) === selectedKategori.value,
    );
});

watch(selectedKategori, (newKat) => {
    if (newKat && selectedJenis.value) {
        const currentKat = resolveKategoriByJenis(selectedJenis.value);
        if (currentKat !== newKat) {
            selectedJenis.value = '';
        }
    }
});

const hasActiveFilters = computed(() =>
    Boolean(
        searchQuery.value ||
            selectedKategori.value ||
            selectedJenis.value ||
            selectedTahap.value ||
            selectedTahun.value,
    ),
);

function resetFilters() {
    searchQuery.value = '';
    selectedKategori.value = '';
    selectedJenis.value = '';
    selectedTahap.value = '';
    selectedTahun.value = '';
}

const filteredPenerima = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.penerima_bansos || []).filter((item) => {
        if (q) {
            const matchNama = String(item.nama_lengkap || '')
                .toLowerCase()
                .includes(q);
            const matchNik = String(item.nik || '').includes(q);
            const matchKk = String(item.nomor_kk || '').includes(q);
            const matchKelompok = String(item.nama_kelompok || '')
                .toLowerCase()
                .includes(q);
            if (!matchNama && !matchNik && !matchKk && !matchKelompok) {
                return false;
            }
        }
        if (selectedKategori.value) {
            const itemKat =
                item.kategori_bantuan || resolveKategoriByJenis(item.jenis_bantuan);
            if (itemKat !== selectedKategori.value) {
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
            selectedTahun.value &&
            String(item.tahun_anggaran) !== String(selectedTahun.value)
        ) {
            return false;
        }
        return true;
    });
});

const penerimaColumns = [
    { key: 'penerima', label: 'Penerima Bantuan' },
    { key: 'dusun', label: 'Wilayah' },
    { key: 'program', label: 'Program & Kategori' },
    { key: 'nominal', label: 'Bentuk Bantuan' },
    { key: 'tahap', label: 'Tahap & Tahun Anggaran' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

const cetakPdfUrl = computed(() => {
    const params = new URLSearchParams({
        tahun: String(selectedTahun.value || props.tahun_anggaran),
    });
    if (selectedKategori.value) {
        params.set('kategori_bantuan', selectedKategori.value);
    }
    if (selectedJenis.value) {
        params.set('jenis_bantuan', selectedJenis.value);
    }
    if (selectedTahap.value) {
        params.set('tahap_penyaluran', selectedTahap.value);
    }
    return `/panel/sosial/bansos/cetak-pdf?${params.toString()}`;
});

const exportExcelUrl = computed(() => {
    const params = new URLSearchParams({
        tahun: String(selectedTahun.value || props.tahun_anggaran),
    });
    if (selectedKategori.value) {
        params.set('kategori_bantuan', selectedKategori.value);
    }
    if (selectedJenis.value) {
        params.set('jenis_bantuan', selectedJenis.value);
    }
    if (selectedTahap.value) {
        params.set('tahap_penyaluran', selectedTahap.value);
    }
    return `/panel/sosial/bansos/export-excel?${params.toString()}`;
});

// Modal Tambah / Edit Penerima Bansos
const PRIORITAS_DESIL_VALUES = [
    'dtsen_desil_1',
    'dtsen_desil_2',
    'dtsen_desil_3',
    'dtsen_desil_4',
    'dtsen_desil_5',
];
const PRIORITAS_DESIL_PARAM = PRIORITAS_DESIL_VALUES.join(',');

const showFormModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isSubmitting = ref(false);
const initialResidentForForm = ref(null);
const kkHistoryInfo = ref(null);
const formErrors = ref({});
const selectedResidents = ref([]);
const pickerResidentId = ref(null);
const autocompleteResetKey = ref(0);
const residentSelectionError = ref('');

const STANDARD_TAHAP = ['Tahap I', 'Tahap II', 'Tahap III', 'Tahap IV'];
const selectedTahapOption = ref('Tahap I');
const customTahapInput = ref('');

const activeFinalTahap = computed(() =>
    selectedTahapOption.value === 'custom'
        ? customTahapInput.value.trim() || 'Tahap I'
        : selectedTahapOption.value,
);

const form = reactive({
    penduduk_id: null,
    kategori_bantuan: 'per_kk',
    jenis_bantuan: 'blt_dd',
    nama_kelompok: '',
    bentuk_nominal: 'Rp 300.000 / Bulan',
    tahun_anggaran: props.tahun_anggaran,
    tahap_penyaluran: 'Tahap I',
    status_kepesertaan: 'aktif',
    keterangan: '',
});

const modalOpsiJenisByKategori = computed(() => {
    return (props.opsi_jenis_bantuan || []).filter(
        (opt) => (opt.kategori || resolveKategoriByJenis(opt.value)) === form.kategori_bantuan,
    );
});

function selectModalKategori(kategoriVal) {
    form.kategori_bantuan = kategoriVal;
    const programsInKategori = (props.opsi_jenis_bantuan || []).filter(
        (opt) => (opt.kategori || resolveKategoriByJenis(opt.value)) === kategoriVal,
    );
    if (programsInKategori.length > 0) {
        const firstProg = programsInKategori[0];
        form.jenis_bantuan = firstProg.value;
        if (firstProg.default_nominal) {
            form.bentuk_nominal = firstProg.default_nominal;
        }
    }
    if (kategoriVal !== 'kelompok') {
        form.nama_kelompok = '';
    }
}

function handleJenisBantuanChange() {
    const selectedOpt = (props.opsi_jenis_bantuan || []).find(
        (opt) => opt.value === form.jenis_bantuan,
    );
    if (selectedOpt) {
        form.kategori_bantuan = selectedOpt.kategori || resolveKategoriByJenis(selectedOpt.value);
        if (selectedOpt.default_nominal) {
            form.bentuk_nominal = selectedOpt.default_nominal;
        }
    }
}

// Pengecekan Bantuan Ganda dalam 1 KK (Aturan: DILARANG hanya untuk kategori per_kk)
const duplicateBansosInKk = computed(() => {
    if (form.kategori_bantuan !== 'per_kk') {
        return null;
    }
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

const batchConflictMessage = computed(() => {
    if (isEditing.value || selectedResidents.value.length === 0) {
        return '';
    }

    const seenKk = new Map();
    for (const res of selectedResidents.value) {
        if (
            res.status_kesejahteraan &&
            !PRIORITAS_DESIL_VALUES.includes(res.status_kesejahteraan)
        ) {
            return `Warga atas nama ${res.nama_lengkap} (NIK: ${res.nik}) bukan kategori Desil 1–5 sehingga tidak memenuhi syarat.`;
        }

        const existingSameNik = (props.penerima_bansos || []).find(
            (item) =>
                (Number(item.penduduk_id) === Number(res.id) ||
                    (item.nik && res.nik && String(item.nik) === String(res.nik))) &&
                item.jenis_bantuan === form.jenis_bantuan &&
                Number(item.tahun_anggaran) === Number(form.tahun_anggaran) &&
                item.tahap_penyaluran === activeFinalTahap.value,
        );
        if (existingSameNik) {
            return `Warga atas nama ${res.nama_lengkap} (NIK: ${res.nik}) sudah terdaftar menerima program ini pada ${activeFinalTahap.value} TA ${form.tahun_anggaran}. Hapus warga tersebut dari daftar atau ubah tahap penyaluran.`;
        }

        const resNoKk = res.nomor_kk || res.kartu_keluarga?.nomor_kk || '';
        if (form.kategori_bantuan === 'per_kk' && resNoKk) {
            if (seenKk.has(resNoKk)) {
                return `Terdapat lebih dari 1 anggota dalam No. KK ${resNoKk} (${seenKk.get(resNoKk)} dan ${res.nama_lengkap}) untuk kategori Bantuan Sosial Per KK.`;
            }
            seenKk.set(resNoKk, res.nama_lengkap);

            const sameKkExisting = (props.penerima_bansos || []).find(
                (item) =>
                    item.nomor_kk &&
                    String(item.nomor_kk) === String(resNoKk) &&
                    item.jenis_bantuan === form.jenis_bantuan &&
                    Number(item.tahun_anggaran) === Number(form.tahun_anggaran),
            );
            if (sameKkExisting) {
                return `Bantuan ganda 1 KK dilarang: anggota keluarga dari ${res.nama_lengkap} (${sameKkExisting.nama_lengkap} - No. KK ${resNoKk}) sudah menerima bantuan ini pada TA ${form.tahun_anggaran}.`;
            }
        }
    }

    return '';
});

const isBlockedByDuplicate = computed(() => {
    return Boolean(duplicateBansosInKk.value) || Boolean(batchConflictMessage.value);
});

function openCreateModal(prefillResident = null) {
    isEditing.value = false;
    editingId.value = null;
    formErrors.value = {};
    residentSelectionError.value = '';
    selectedResidents.value = [];
    pickerResidentId.value = null;
    autocompleteResetKey.value += 1;

    form.penduduk_id = null;
    form.kategori_bantuan = 'per_kk';
    form.jenis_bantuan = 'blt_dd';
    form.nama_kelompok = '';
    form.bentuk_nominal = 'Rp 300.000 / Bulan';
    form.tahun_anggaran = props.tahun_anggaran;
    form.tahap_penyaluran = 'Tahap I';
    form.status_kepesertaan = 'aktif';
    form.keterangan = '';

    selectedTahapOption.value = 'Tahap I';
    customTahapInput.value = '';

    initialResidentForForm.value = null;
    kkHistoryInfo.value = null;
    showFormModal.value = true;

    if (prefillResident?.id) {
        handleSelectResident(prefillResident);
    }
}

function openEditModal(item) {
    isEditing.value = true;
    editingId.value = item.id;
    formErrors.value = {};
    residentSelectionError.value = '';
    selectedResidents.value = [];
    pickerResidentId.value = item.penduduk_id;

    const jenisVal = item.jenis_bantuan || 'blt_dd';
    form.penduduk_id = item.penduduk_id;
    form.kategori_bantuan = item.kategori_bantuan || resolveKategoriByJenis(jenisVal);
    form.jenis_bantuan = jenisVal;
    form.nama_kelompok = item.nama_kelompok || '';
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
    if (isEditing.value) {
        form.penduduk_id = resident?.id || null;
        formErrors.value = {};
        if (resident?.id) {
            loadKkHistory(resident.id);
        } else {
            kkHistoryInfo.value = null;
        }
        return;
    }

    pickerResidentId.value = null;
    autocompleteResetKey.value += 1;

    if (!resident || !resident.id) {
        return;
    }

    formErrors.value = {};
    residentSelectionError.value = '';

    // 1. Validasi wajib Desil 1 - 5
    if (
        resident.status_kesejahteraan &&
        !PRIORITAS_DESIL_VALUES.includes(resident.status_kesejahteraan)
    ) {
        residentSelectionError.value = `Warga atas nama ${resident.nama_lengkap} (NIK: ${resident.nik}) bukan kategori Desil 1–5 sehingga tidak dapat ditambahkan sebagai penerima bantuan sosial.`;
        return;
    }

    // 2. Validasi tidak boleh duplikat di daftar pilihan saat ini
    if (
        selectedResidents.value.some(
            (r) =>
                Number(r.id) === Number(resident.id) ||
                (r.nik && resident.nik && String(r.nik) === String(resident.nik)),
        )
    ) {
        residentSelectionError.value = `Warga atas nama ${resident.nama_lengkap} (NIK: ${resident.nik}) sudah ada di dalam daftar penerima yang dipilih.`;
        return;
    }

    // 3. Validasi tidak boleh NIK yang sudah menerima bantuan sejenis pada tahap & tahun anggaran yang sama
    const existingSameNik = (props.penerima_bansos || []).find(
        (item) =>
            (Number(item.penduduk_id) === Number(resident.id) ||
                (item.nik && resident.nik && String(item.nik) === String(resident.nik))) &&
            item.jenis_bantuan === form.jenis_bantuan &&
            Number(item.tahun_anggaran) === Number(form.tahun_anggaran) &&
            item.tahap_penyaluran === activeFinalTahap.value,
    );
    if (existingSameNik) {
        residentSelectionError.value = `Warga atas nama ${resident.nama_lengkap} (NIK: ${resident.nik}) sudah terdaftar menerima bantuan ${existingSameNik.jenis_bantuan_label || form.jenis_bantuan} pada ${activeFinalTahap.value} TA ${form.tahun_anggaran}.`;
        return;
    }

    // 4. Validasi 1 KK maksimal 1 penerima untuk kategori Bantuan Sosial Per KK
    const residentNoKk = resident.nomor_kk || resident.kartu_keluarga?.nomor_kk || '';
    if (form.kategori_bantuan === 'per_kk' && residentNoKk) {
        const sameKkInBatch = selectedResidents.value.find((r) => {
            const rKk = r.nomor_kk || r.kartu_keluarga?.nomor_kk || '';
            return rKk && String(rKk) === String(residentNoKk);
        });
        if (sameKkInBatch) {
            residentSelectionError.value = `Tidak dapat menambahkan ${resident.nama_lengkap} karena anggota keluarga satu KK (${sameKkInBatch.nama_lengkap} - No. KK ${residentNoKk}) sudah dipilih dalam daftar ini.`;
            return;
        }

        const sameKkExisting = (props.penerima_bansos || []).find(
            (item) =>
                item.nomor_kk &&
                String(item.nomor_kk) === String(residentNoKk) &&
                item.jenis_bantuan === form.jenis_bantuan &&
                Number(item.tahun_anggaran) === Number(form.tahun_anggaran),
        );
        if (sameKkExisting) {
            residentSelectionError.value = `Bantuan ganda dalam 1 KK dilarang: anggota keluarga satu KK (${sameKkExisting.nama_lengkap} - No. KK ${residentNoKk}) sudah menerima bantuan ini pada TA ${form.tahun_anggaran}.`;
            return;
        }
    }

    selectedResidents.value.push(resident);
    form.penduduk_id = selectedResidents.value[0]?.id || null;
    loadKkHistory(resident.id);
}

function removeSelectedResident(residentId) {
    selectedResidents.value = selectedResidents.value.filter(
        (r) => Number(r.id) !== Number(residentId),
    );
    form.penduduk_id = selectedResidents.value[0]?.id || null;
    residentSelectionError.value = '';
    if (selectedResidents.value.length === 0) {
        kkHistoryInfo.value = null;
    }
}

function submitBansosForm() {
    if (isSubmitting.value || isBlockedByDuplicate.value) {
        return;
    }

    if (!isEditing.value && selectedResidents.value.length === 0 && !form.penduduk_id) {
        residentSelectionError.value =
            'Silakan pilih minimal 1 warga penerima (Desil 1–5) terlebih dahulu.';
        return;
    }

    const finalTahap = activeFinalTahap.value;

    form.tahap_penyaluran = finalTahap;
    formErrors.value = {};
    residentSelectionError.value = '';
    isSubmitting.value = true;

    const batchIds =
        selectedResidents.value.length > 0
            ? selectedResidents.value.map((r) => r.id)
            : form.penduduk_id
              ? [form.penduduk_id]
              : [];

    const payload = {
        penduduk_id: isEditing.value ? form.penduduk_id : batchIds[0] || null,
        penduduk_ids: isEditing.value ? [form.penduduk_id] : batchIds,
        kategori_bantuan: form.kategori_bantuan,
        jenis_bantuan: form.jenis_bantuan,
        nama_kelompok:
            form.kategori_bantuan === 'kelompok'
                ? form.nama_kelompok.trim() || null
                : null,
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
            selectedResidents.value = [];
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
    <Head :title="`Data Penerima Bantuan Sosial TA ${tahun_anggaran}`" />

    <AppLayout>
        <div class="space-y-6">
            <!-- Header Halaman dengan PageHeader -->
            <PageHeader
                title="Data Penerima Bantuan Sosial (Bansos)"
                :description="`Pencatatan penerima bantuan sosial keluarga (Per KK), perorangan, dan kelompok masyarakat Tahun Anggaran ${tahun_anggaran}.`"
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
                    label="Bansos Per KK (KPM)"
                    :value="ringkasan?.total_per_kk ?? 0"
                    subtext="PKH, BPNT, BLT-DD, CBP, RTLH"
                    tone="emerald"
                    :icon="CheckCircle2"
                />
                <StatCard
                    label="Bansos Perorangan"
                    :value="ringkasan?.total_perorangan ?? 0"
                    subtext="PIP, PBI-JK, Lansia, Disabilitas"
                    tone="amber"
                    :icon="UserCheck"
                />
                <StatCard
                    label="Bantuan Kelompok Masyarakat"
                    :value="ringkasan?.total_kelompok ?? 0"
                    subtext="Kelompok Tani, UMKM, Sarana Tani"
                    tone="neutral"
                    :icon="HeartHandshake"
                />
            </div>

            <!-- UnifiedFilterBar -->
            <UnifiedFilterBar
                v-model="searchQuery"
                search-placeholder="Cari nama penerima, NIK, Nomor KK, atau nama kelompok..."
                :has-custom-active-filter="hasActiveFilters"
                @reset="resetFilters"
            >
                <template #filters>
                    <select
                        v-model="selectedKategori"
                        aria-label="Filter Kategori Bantuan"
                        data-testid="filter-kategori-bansos"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                    >
                        <option value="">Semua Kategori Bantuan</option>
                        <option
                            v-for="kat in OPSI_KATEGORI_BANTUAN"
                            :key="kat.value"
                            :value="kat.value"
                        >
                            {{ kat.label }}
                        </option>
                    </select>

                    <select
                        v-model="selectedJenis"
                        aria-label="Filter Jenis Bantuan"
                        data-testid="filter-jenis-bansos"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                    >
                        <option value="">Semua Program Bantuan</option>
                        <option
                            v-for="opt in filteredOpsiJenisFilter"
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
                        v-model="selectedTahun"
                        aria-label="Filter Tahun Anggaran"
                        data-testid="filter-tahun-bansos"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700"
                    >
                        <option value="">Semua Tahun Anggaran</option>
                        <option
                            v-for="thn in opsiTahunList"
                            :key="thn"
                            :value="String(thn)"
                        >
                            TA {{ thn }}
                        </option>
                    </select>
                </template>
            </UnifiedFilterBar>

            <!-- Daftar Penerima Bansos (DataTable) -->
            <div data-testid="section-daftar-penerima">
                <DataTable
                    :columns="penerimaColumns"
                    :rows="filteredPenerima"
                    :empty-title="`Belum ada data penerima bantuan sosial pada Tahun Anggaran ${tahun_anggaran}`"
                    empty-description="Tambahkan penerima secara manual atau impor daftar NIK penerima melalui berkas Excel."
                >
                    <template #cell-penerima="{ row }">
                        <div class="py-0.5">
                            <p class="font-semibold text-slate-900">
                                {{ row.nama_lengkap }}
                            </p>
                            <p class="mt-0.5 text-xs tabular-nums text-slate-500">
                                NIK: {{ row.nik }}
                            </p>
                            <p
                                v-if="row.nama_kelompok"
                                class="mt-0.5 text-xs font-medium text-amber-700"
                            >
                                {{ row.nama_kelompok }}
                            </p>
                        </div>
                    </template>

                    <template #cell-dusun="{ row }">
                        <div class="py-0.5">
                            <p class="font-medium text-slate-800">{{ row.dusun }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                RT {{ row.rt || '001' }} / RW {{ row.rw || '001' }}
                            </p>
                        </div>
                    </template>

                    <template #cell-program="{ row }">
                        <div class="py-0.5">
                            <p class="font-medium text-slate-900">
                                {{ row.jenis_bantuan_label }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{
                                    row.kategori_bantuan_label ||
                                    resolveKategoriLabelByJenis(row.jenis_bantuan)
                                }}
                            </p>
                        </div>
                    </template>

                    <template #cell-nominal="{ row }">
                        <div class="py-0.5">
                            <p class="font-semibold text-slate-900">
                                {{ row.bentuk_nominal }}
                            </p>
                            <p
                                v-if="row.keterangan"
                                class="mt-0.5 text-xs text-slate-500"
                            >
                                {{ row.keterangan }}
                            </p>
                        </div>
                    </template>

                    <template #cell-tahap="{ row }">
                        <div class="py-0.5">
                            <p class="font-medium text-slate-800">
                                {{ row.tahap_penyaluran }}
                            </p>
                            <p class="mt-0.5 text-xs tabular-nums text-slate-500">
                                TA {{ row.tahun_anggaran }}
                            </p>
                        </div>
                    </template>

                    <template #cell-actions="{ row }">
                        <div
                            v-if="isOperator"
                            class="flex items-center justify-end gap-1.5"
                        >
                            <button
                                type="button"
                                :data-testid="`btn-edit-bansos-${row.id}`"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900"
                                @click="openEditModal(row)"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                <span>Edit</span>
                            </button>

                            <button
                                type="button"
                                :data-testid="`btn-delete-bansos-${row.id}`"
                                class="inline-flex h-8 items-center gap-1.5 rounded-md border border-red-200 bg-white px-2.5 text-xs font-medium text-red-600 shadow-xs transition-colors hover:bg-red-50 hover:text-red-700"
                                @click="handleDeleteBansos(row)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                                <span>Hapus</span>
                            </button>
                        </div>
                        <span v-else class="text-xs text-slate-400">Read-Only</span>
                    </template>
                </DataTable>
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
                    v-if="isEditing"
                    v-model="form.penduduk_id"
                    :initial-resident="initialResidentForForm"
                    :items="residents"
                    label="Cari Warga Penerima (NIK / Nama Lengkap)"
                    required
                    :error="formErrors.penduduk_id"
                    @select="handleSelectResident"
                />

                <div v-else class="space-y-3">
                    <NikSearchAutocomplete
                        :key="autocompleteResetKey"
                        v-model="pickerResidentId"
                        :items="residents"
                        :search-params="{ status_kesejahteraan: PRIORITAS_DESIL_PARAM }"
                        label="Cari & Tambah Warga Penerima (Khusus Desil 1–5)"
                        placeholder="Ketik NIK atau nama warga Desil 1–5 untuk ditambahkan ke daftar..."
                        helper-text="Anda dapat memilih banyak warga sekaligus. Hanya warga Desil 1–5 yang belum menerima bantuan ini yang dapat ditambahkan."
                        :error="formErrors.penduduk_id || formErrors.penduduk_ids"
                        @select="handleSelectResident"
                    />

                    <!-- Pesan Error Validasi Pilihan Warga (Bukan Desil 1-5 / Sudah Menerima / Duplikat) -->
                    <div
                        v-if="residentSelectionError || formErrors.penduduk_id || formErrors.penduduk_ids"
                        data-testid="resident-selection-error"
                        class="rounded-lg border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs font-medium text-red-700"
                    >
                        {{ residentSelectionError || formErrors.penduduk_id || formErrors.penduduk_ids }}
                    </div>

                    <!-- Daftar Warga Penerima yang Dipilih (Multi-Input) -->
                    <div
                        v-if="selectedResidents.length > 0"
                        data-testid="selected-residents-list"
                        class="rounded-lg border border-blue-200 bg-blue-50/40 p-3"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-xs font-semibold text-slate-800">
                                Daftar Penerima Dipilih ({{ selectedResidents.length }} Warga)
                            </p>
                            <button
                                v-if="selectedResidents.length > 1"
                                type="button"
                                data-testid="btn-clear-selected-residents"
                                class="text-xs font-medium text-red-600 hover:underline"
                                @click="
                                    selectedResidents = [];
                                    form.penduduk_id = null;
                                    kkHistoryInfo = null;
                                "
                            >
                                Kosongkan Semua
                            </button>
                        </div>

                        <div class="max-h-48 space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="(res, idx) in selectedResidents"
                                :key="res.id"
                                :data-testid="`selected-resident-item-${res.id}`"
                                class="flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 shadow-2xs"
                            >
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-100 text-[11px] font-bold text-blue-700">
                                            {{ idx + 1 }}
                                        </span>
                                        <p class="truncate text-sm font-semibold text-slate-900">
                                            {{ res.nama_lengkap }}
                                        </p>
                                        <StatusBadge
                                            v-if="res.status_kesejahteraan"
                                            :status="res.status_kesejahteraan"
                                        />
                                    </div>
                                    <p class="mt-0.5 text-xs tabular-nums text-slate-500">
                                        NIK: {{ res.nik }}
                                        <template v-if="res.dusun || res.kartu_keluarga?.dusun">
                                            &bull; {{ res.dusun || res.kartu_keluarga?.dusun }}
                                        </template>
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    :data-testid="`btn-remove-selected-resident-${res.id}`"
                                    class="inline-flex h-7 items-center gap-1 rounded-md border border-red-200 bg-white px-2 text-xs font-medium text-red-600 transition-colors hover:bg-red-50"
                                    @click="removeSelectedResident(res.id)"
                                >
                                    <X class="h-3.5 w-3.5" />
                                    <span>Hapus</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Peringatan Konflik / Bantuan Ganda dalam 1 KK -->
                <BaseCallout
                    v-if="batchConflictMessage"
                    data-testid="batch-conflict-callout"
                    variant="danger"
                    title="Terdapat Konflik Data Penerima"
                >
                    {{ batchConflictMessage }}
                </BaseCallout>

                <BaseCallout
                    v-else-if="duplicateBansosInKk"
                    variant="danger"
                    title="DILARANG: Bantuan Ganda dalam 1 Kartu Keluarga"
                >
                    Anggota keluarga dalam 1 KK atas nama
                    <strong>{{ duplicateBansosInKk?.nama_penerima }}</strong> ({{ duplicateBansosInKk?.hubungan_keluarga || 'Anggota KK' }})
                    sudah terdaftar sebagai penerima aktif <strong>{{ duplicateBansosInKk?.jenis_bantuan_label }}</strong> pada Tahun Anggaran {{ form.tahun_anggaran }}.
                    Sesuai kebijakan desa, bantuan ganda sejenis dalam 1 KK dilarang keras. Tombol simpan dinonaktifkan.
                </BaseCallout>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Kategori Bantuan Sosial <span class="text-red-600">*</span>
                        </label>
                        <select
                            v-model="form.kategori_bantuan"
                            data-testid="select-kategori-bantuan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            @change="selectModalKategori(form.kategori_bantuan)"
                        >
                            <option
                                v-for="kat in OPSI_KATEGORI_BANTUAN"
                                :key="kat.value"
                                :value="kat.value"
                            >
                                {{ kat.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Jenis Program Bantuan <span class="text-red-600">*</span>
                        </label>
                        <select
                            v-model="form.jenis_bantuan"
                            data-testid="select-jenis-bantuan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            @change="handleJenisBantuanChange"
                        >
                            <option
                                v-for="opt in modalOpsiJenisByKategori"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>

                    <div
                        v-if="form.kategori_bantuan === 'kelompok'"
                        class="space-y-1.5 sm:col-span-2"
                    >
                        <label class="block text-sm font-medium text-slate-700">
                            Nama Kelompok / Usaha (Poktan / UMKM) <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.nama_kelompok"
                            type="text"
                            required
                            data-testid="input-nama-kelompok"
                            placeholder="Contoh: Kelompok Tani Harapan Jaya / UMKM Keripik Pisang Ibu Sari"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                        <p
                            v-if="formErrors.nama_kelompok"
                            class="text-xs text-red-600"
                        >
                            {{ formErrors.nama_kelompok }}
                        </p>
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
                        {{
                            isEditing
                                ? 'Simpan Perubahan'
                                : selectedResidents.length > 1
                                  ? `Simpan ${selectedResidents.length} Penerima`
                                  : 'Simpan Penerima'
                        }}
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
