<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    CheckCircle2,
    MessageCircle,
    Plus,
    Printer,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import BuatSuratLoketModal from '@/Components/BuatSuratLoketModal.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    antrean: {
        type: Array,
        default: () => [],
    },
    jumlah_menunggu: {
        type: Number,
        default: 0,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    opsi_jenis_surat: {
        type: Array,
        default: () => [],
    },
    pejabat_penandatangan: {
        type: Array,
        default: () => [],
    },
    tanggal_hari_ini: {
        type: String,
        default: () => new Date().toISOString().slice(0, 10),
    },
    residents: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const confirm = useConfirm();
const showBuatModal = ref(false);

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const persuratanTabs = computed(() => [
    {
        key: 'antrean',
        label: 'Antrean Online',
        href: '/panel/persuratan/antrean',
        badge: props.jumlah_menunggu,
        active: true,
    },
    {
        key: 'surat-keluar',
        label: 'Buku Agenda Surat Keluar',
        href: '/panel/persuratan/surat-keluar',
        active: false,
    },
    {
        key: 'surat-masuk',
        label: 'Buku Agenda Surat Masuk',
        href: '/panel/persuratan/surat-masuk',
        active: false,
    },
]);

const searchQuery = ref(props.filters?.search || '');
const selectedStatus = ref(props.filters?.status || '');

const hasActiveFilters = computed(() =>
    Boolean(searchQuery.value || selectedStatus.value),
);

const filteredAntrean = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    return (props.antrean || []).filter((item) => {
        if (q) {
            const matchName = String(item.nama_pemohon || '')
                .toLowerCase()
                .includes(q);
            const matchNik = String(item.nik_pemohon || '').includes(q);
            const matchNo = String(item.nomor_surat || '')
                .toLowerCase()
                .includes(q);
            if (!matchName && !matchNik && !matchNo) {
                return false;
            }
        }

        if (selectedStatus.value && item.status !== selectedStatus.value) {
            return false;
        }

        return true;
    });
});

function resetFilters() {
    searchQuery.value = '';
    selectedStatus.value = '';
}

const columns = [
    { key: 'pemohon', label: 'Pemohon & NIK' },
    { key: 'jenis_surat', label: 'Jenis Surat & Keperluan' },
    { key: 'kontak', label: 'Waktu Pengajuan' },
    { key: 'status', label: 'Status' },
    {
        key: 'actions',
        label: 'Aksi',
        align: 'right',
        class: 'whitespace-nowrap',
        cellClass: 'whitespace-nowrap',
    },
];

/**
 * Bentuk URL WhatsApp dengan pesan resmi pengambilan surat di Kantor Desa.
 */
function buildWaPickupUrl(row) {
    if (row.wa_pickup_url) {
        return row.wa_pickup_url;
    }
    if (!row.no_wa_pemohon) {
        return '';
    }
    const cleanPhone = String(row.no_wa_pemohon).replace(/\D/g, '');
    const normalizedPhone = cleanPhone.startsWith('08')
        ? `62${cleanPhone.slice(1)}`
        : cleanPhone;
    const message = `Halo Bpk/Ibu ${row.nama_pemohon}, Surat ${row.jenis_surat_label} Anda sudah selesai dicetak dan ditandatangani. Silakan diambil di Kantor Desa dengan membawa KTP/KK asli.`;
    return `https://wa.me/${normalizedPhone}?text=${encodeURIComponent(message)}`;
}

// Modal Verifikasi & Cetak F4
const showVerifikasiModal = ref(false);
const activeAntrean = ref(null);
const isSubmittingVerifikasi = ref(false);
const bansosKkInfo = ref(null);
const verifikasiForm = reactive({
    opsi_penandatangan: 'kepala_desa',
    kode_klasifikasi: '',
    keperluan: '',
});

async function openVerifikasiModal(item) {
    activeAntrean.value = item;
    verifikasiForm.opsi_penandatangan =
        item.opsi_penandatangan || 'kepala_desa';
    verifikasiForm.kode_klasifikasi = item.kode_klasifikasi || '';
    verifikasiForm.keperluan = item.keperluan || '';
    bansosKkInfo.value = null;
    showVerifikasiModal.value = true;

    if (item.jenis_surat === 'sktm' && item.penduduk_id) {
        try {
            const res = await axios.get(
                `/panel/persuratan/cek-bansos-kk/${item.penduduk_id}`,
            );
            bansosKkInfo.value = res.data;
        } catch {
            bansosKkInfo.value = null;
        }
    }
}

function switchFromVerifikasiToTolak() {
    const item = activeAntrean.value;
    showVerifikasiModal.value = false;
    if (item) {
        openTolakModal(item);
    }
}

function submitVerifikasi() {
    if (!activeAntrean.value || isSubmittingVerifikasi.value) {
        return;
    }

    isSubmittingVerifikasi.value = true;
    const targetId = activeAntrean.value.id;

    router.post(
        `/panel/persuratan/antrean/${targetId}/verifikasi`,
        {
            opsi_penandatangan: verifikasiForm.opsi_penandatangan,
            kode_klasifikasi: verifikasiForm.kode_klasifikasi,
            keperluan: verifikasiForm.keperluan,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showVerifikasiModal.value = false;
                if (typeof window !== 'undefined' && typeof window.open === 'function') {
                    window.open(`/panel/persuratan/surat-keluar/${targetId}/pdf`, '_blank');
                }
            },
            onFinish: () => {
                isSubmittingVerifikasi.value = false;
            },
        },
    );
}

// Modal Tolak Permohonan
const showTolakModal = ref(false);
const tolakTarget = ref(null);
const alasanPenolakan = ref('');

function openTolakModal(item) {
    tolakTarget.value = item;
    alasanPenolakan.value = 'Data permohonan belum lengkap, silakan hadir ke loket Kantor Desa.';
    showTolakModal.value = true;
}

async function submitTolak() {
    if (!tolakTarget.value || !alasanPenolakan.value.trim()) {
        return;
    }

    const ok = await confirm.ask({
        title: 'Tolak Permohonan Surat Online?',
        message: `Permohonan "${tolakTarget.value.jenis_surat_label}" dari ${tolakTarget.value.nama_pemohon} akan ditandai sebagai Ditolak.`,
        confirmText: 'Ya, Tolak Permohonan',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.patch(
        `/panel/persuratan/antrean/${tolakTarget.value.id}/tolak`,
        {
            alasan_penolakan: alasanPenolakan.value.trim(),
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showTolakModal.value = false;
            },
        },
    );
}

async function handleDeleteAntrean(item) {
    const ok = await confirm.ask({
        title: 'Hapus Antrean Permohonan?',
        message: `Apakah Anda yakin ingin menghapus data permohonan dari ${item.nama_pemohon}?`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/persuratan/surat-keluar/${item.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Antrean Permohonan Surat Online" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                title="Persuratan & Buku Agenda Desa"
                subtitle="Pelayanan permohonan surat online warga, layanan kilat loket 10 template, dan pengarsipan Buku Agenda Surat."
            >
                <template #actions>
                    <BaseButton
                        v-if="isOperator"
                        variant="primary"
                        data-testid="btn-buat-surat-loket"
                        @click="showBuatModal = true"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Buat Surat</span>
                    </BaseButton>
                </template>
            </PageHeader>

            <!-- Navigasi 3 Tab Grup Persuratan -->
            <ContextualTabs :tabs="persuratanTabs" />

            <!-- Filter Bar -->
            <UnifiedFilterBar
                v-model="searchQuery"
                search-placeholder="Cari nama pemohon, NIK, atau nomor surat..."
                :has-active-filters="hasActiveFilters"
                @reset="resetFilters"
            >
                <template #filters>
                    <select
                        v-model="selectedStatus"
                        aria-label="Filter Status Antrean"
                        data-testid="filter-status-antrean"
                        class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                    >
                        <option value="">Semua Status</option>
                        <option value="menunggu_verifikasi">Menunggu Verifikasi</option>
                        <option value="terbit">Terbit / Dicetak</option>
                        <option value="ditolak">Ditolak</option>
                    </select>
                </template>
            </UnifiedFilterBar>

            <!-- Tabel Antrean Permohonan Surat Online -->
            <DataTable
                :columns="columns"
                :rows="filteredAntrean"
                empty-title="Belum ada permohonan surat online dari warga"
                empty-description="Permohonan yang diajukan melalui halaman Layanan Mandiri Warga akan otomatis tampil di daftar antrean ini."
            >
                <template #cell-pemohon="{ row }">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ row.nama_pemohon }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500">
                            NIK: {{ row.nik_pemohon }}
                            <span v-if="row.dusun_pemohon"> &bull; {{ row.dusun_pemohon }}</span>
                        </p>
                    </div>
                </template>

                <template #cell-jenis_surat="{ row }">
                    <div>
                        <p class="font-medium text-slate-900">
                            {{ row.jenis_surat_label }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Keperluan: {{ row.keperluan }}
                        </p>
                    </div>
                </template>

                <template #cell-kontak="{ row }">
                    <div class="text-xs tabular-nums text-slate-700">
                        <p>{{ row.created_at || '—' }}</p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="space-y-1">
                        <StatusBadge
                            :status="row.status"
                            :label="row.status_label"
                        />
                        <p
                            v-if="row.alasan_penolakan"
                            class="text-xs text-red-600"
                        >
                            Alasan: {{ row.alasan_penolakan }}
                        </p>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end gap-2 whitespace-nowrap">
                        <!-- Aksi saat Menunggu Verifikasi (Khusus Operator) -->
                        <template v-if="row.status === 'menunggu_verifikasi' && isOperator">
                            <BaseButton
                                variant="primary"
                                size="sm"
                                :data-testid="`btn-verifikasi-antrean-${row.id}`"
                                @click="openVerifikasiModal(row)"
                            >
                                <CheckCircle2 class="mr-1 h-3.5 w-3.5" />
                                <span>Verifikasi</span>
                            </BaseButton>

                            <BaseButton
                                variant="outline"
                                size="sm"
                                :data-testid="`btn-tolak-antrean-${row.id}`"
                                @click="openTolakModal(row)"
                            >
                                <XCircle class="mr-1 h-3.5 w-3.5 text-red-600" />
                                <span>Tolak</span>
                            </BaseButton>
                        </template>

                        <!-- Aksi saat sudah Terbit (Kabari Warga via WA) -->
                        <template v-else-if="row.status === 'terbit'">
                            <a
                                v-if="row.no_wa_pemohon"
                                :href="buildWaPickupUrl(row)"
                                target="_blank"
                                rel="noopener noreferrer"
                                :data-testid="`btn-wa-pickup-${row.id}`"
                                class="inline-flex h-8 items-center justify-center rounded-md bg-emerald-600 px-3 text-xs font-medium text-white transition-colors hover:bg-emerald-700"
                            >
                                <MessageCircle class="mr-1 h-3.5 w-3.5" />
                                <span>Whatsapp</span>
                            </a>
                        </template>

                        <!-- Hapus Antrean jika Ditolak -->
                        <button
                            v-if="row.status === 'ditolak' && isOperator"
                            type="button"
                            :data-testid="`btn-delete-antrean-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md px-2 text-xs font-medium text-red-600 hover:bg-red-50"
                            @click="handleDeleteAntrean(row)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Modal Verifikasi & Cetak Surat F4 -->
        <BaseModal
            :show="showVerifikasiModal"
            title="Verifikasi Permohonan & Terbitkan Surat Resmi"
            max-width="2xl"
            @close="showVerifikasiModal = false"
        >
            <div v-if="activeAntrean" class="space-y-4">
                <!-- 1. Identitas & Kontak Pemohon -->
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">
                                {{ activeAntrean.nama_pemohon }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                NIK: <span class="font-mono font-medium">{{ activeAntrean.nik_pemohon }}</span>
                                <span v-if="activeAntrean.dusun_pemohon"> &bull; {{ activeAntrean.dusun_pemohon }}</span>
                            </p>
                            <p v-if="activeAntrean.created_at" class="mt-0.5 text-xs text-slate-500">
                                Waktu Pengajuan: {{ activeAntrean.created_at }}
                            </p>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                                {{ activeAntrean.jenis_surat_label }}
                            </span>
                            <p v-if="activeAntrean.no_wa_pemohon" class="mt-1 text-xs font-medium text-emerald-700">
                                WA: {{ activeAntrean.no_wa_pemohon }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- 2. Rincian Data Khusus yang Diisi Warga Saat Pengajuan Online -->
                <div
                    v-if="activeAntrean.data_khusus && Object.keys(activeAntrean.data_khusus).length > 0"
                    class="rounded-lg border border-slate-200 bg-white p-4"
                    data-testid="section-review-data-khusus"
                >
                    <div class="mb-3 flex items-center justify-between border-b border-slate-100 pb-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-600">
                            Rincian Data Pengajuan Warga
                        </h4>
                        <span class="text-[11px] text-slate-400">Data Mandiri Online</span>
                    </div>

                    <!-- 1. SKU -->
                    <dl
                        v-if="activeAntrean.jenis_surat === 'sku'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Nama Usaha</dt>
                            <dd class="font-semibold text-slate-900">{{ activeAntrean.data_khusus.nama_usaha || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Jenis Usaha</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.jenis_usaha || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Bidang Usaha</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.bidang_usaha || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Lama Usaha Berjalan</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.lama_usaha || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Alamat Lokasi Usaha</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.alamat_usaha || '—' }}</dd>
                        </div>
                    </dl>

                    <!-- 2. Domisili -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'domisili'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Tinggal Sejak Tahun</dt>
                            <dd class="font-semibold text-slate-900">{{ activeAntrean.data_khusus.sejak_tahun || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Status Tempat Tinggal</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.status_tempat_tinggal || '—' }}</dd>
                        </div>
                    </dl>

                    <!-- 3. SKTM -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'sktm'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Penghasilan Rata-rata/Bulan</dt>
                            <dd class="font-semibold text-slate-900">
                                {{ formatRupiah(activeAntrean.data_khusus.penghasilan_rata_rata || 0) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Jumlah Tanggungan Keluarga</dt>
                            <dd class="font-semibold text-slate-900">
                                {{ activeAntrean.data_khusus.jumlah_tanggungan || 0 }} Orang
                            </dd>
                        </div>
                        <div v-if="activeAntrean.data_khusus.nama_tanggungan" class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Nama Tanggungan yang Dituju</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.nama_tanggungan }}</dd>
                        </div>
                    </dl>

                    <!-- 4. Pengantar SKCK -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'pengantar_skck'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Tujuan Pengurusan SKCK</dt>
                            <dd class="font-semibold text-slate-900">{{ activeAntrean.data_khusus.tujuan_skck || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Catatan Kelakuan Baik</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.catatan_kelakuan || '—' }}</dd>
                        </div>
                    </dl>

                    <!-- 5. Kematian -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'kematian'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Hari, Tanggal & Jam Wafat</dt>
                            <dd class="font-semibold text-slate-900">
                                {{ activeAntrean.data_khusus.hari_meninggal }}, {{ activeAntrean.data_khusus.tanggal_meninggal }}
                                <span v-if="activeAntrean.data_khusus.jam_meninggal"> pukul {{ activeAntrean.data_khusus.jam_meninggal }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Tempat Meninggal</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.tempat_meninggal || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Penyebab Kematian</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.penyebab_meninggal || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Lokasi Pemakaman</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.lokasi_makam || '—' }}</dd>
                        </div>
                        <div class="rounded border border-slate-100 bg-slate-50 p-2 sm:col-span-2">
                            <dt class="font-medium text-slate-500">Identitas Pelapor Kematian</dt>
                            <dd class="font-medium text-slate-800">
                                {{ activeAntrean.data_khusus.nama_pelapor }}
                                <span v-if="activeAntrean.data_khusus.nik_pelapor"> (NIK: {{ activeAntrean.data_khusus.nik_pelapor }})</span>
                                &bull; Hubungan: {{ activeAntrean.data_khusus.hubungan_pelapor }}
                            </dd>
                        </div>
                    </dl>

                    <!-- 6. Kelahiran -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'kelahiran'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Nama Bayi</dt>
                            <dd class="font-semibold text-slate-900">{{ activeAntrean.data_khusus.nama_bayi || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Jenis Kelamin</dt>
                            <dd class="font-semibold text-slate-900">
                                {{ activeAntrean.data_khusus.jenis_kelamin_bayi === 'L' ? 'Laki-laki (L)' : 'Perempuan (P)' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Hari, Tanggal & Jam Lahir</dt>
                            <dd class="text-slate-800">
                                {{ activeAntrean.data_khusus.hari_lahir_bayi }}, {{ activeAntrean.data_khusus.tanggal_lahir_bayi }}
                                <span v-if="activeAntrean.data_khusus.jam_lahir_bayi"> pukul {{ activeAntrean.data_khusus.jam_lahir_bayi }}</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Tempat Lahir & Anak Ke-</dt>
                            <dd class="text-slate-800">
                                {{ activeAntrean.data_khusus.tempat_lahir_bayi || '—' }} (Anak ke-{{ activeAntrean.data_khusus.anak_ke || 1 }})
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Nama Ayah Kandung</dt>
                            <dd class="text-slate-800">
                                {{ activeAntrean.data_khusus.nama_ayah || '—' }}
                                <span v-if="activeAntrean.data_khusus.nik_ayah" class="text-slate-500"> (NIK: {{ activeAntrean.data_khusus.nik_ayah }})</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Nama Ibu Kandung</dt>
                            <dd class="text-slate-800">
                                {{ activeAntrean.data_khusus.nama_ibu || '—' }}
                                <span v-if="activeAntrean.data_khusus.nik_ibu" class="text-slate-500"> (NIK: {{ activeAntrean.data_khusus.nik_ibu }})</span>
                            </dd>
                        </div>
                    </dl>

                    <!-- 7. Belum Menikah -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'belum_menikah'"
                        class="text-xs"
                    >
                        <dt class="font-medium text-slate-500">Pernyataan Status Pernikahan</dt>
                        <dd class="mt-0.5 font-semibold text-slate-900">{{ activeAntrean.data_khusus.pernyataan_belum_menikah || '—' }}</dd>
                    </dl>

                    <!-- 8. Janda / Duda -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'janda_duda'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div>
                            <dt class="font-medium text-slate-500">Status Perceraian</dt>
                            <dd class="font-semibold text-slate-900">
                                {{ activeAntrean.data_khusus.status_cerai === 'cerai_mati' ? 'Cerai Mati' : 'Cerai Hidup' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Nama Mantan Pasangan</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.nama_mantan_pasangan || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Bercerai Sejak</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.sejak_tanggal_tahun || '—' }}</dd>
                        </div>
                    </dl>

                    <!-- 9. Kehilangan -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'kehilangan'"
                        class="grid grid-cols-1 gap-x-4 gap-y-2 text-xs sm:grid-cols-2"
                    >
                        <div class="sm:col-span-2">
                            <dt class="font-medium text-slate-500">Rincian Barang yang Hilang</dt>
                            <dd class="font-semibold text-slate-900">{{ activeAntrean.data_khusus.barang_hilang || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Perkiraan Waktu Kehilangan</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.waktu_kehilangan || '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Perkiraan Lokasi Kehilangan</dt>
                            <dd class="text-slate-800">{{ activeAntrean.data_khusus.lokasi_kehilangan || '—' }}</dd>
                        </div>
                    </dl>

                    <!-- 10. Keterangan Umum -->
                    <dl
                        v-else-if="activeAntrean.jenis_surat === 'keterangan_umum'"
                        class="space-y-2 text-xs"
                    >
                        <div v-if="activeAntrean.data_khusus.judul_keterangan">
                            <dt class="font-medium text-slate-500">Judul Keterangan Kustom</dt>
                            <dd class="font-semibold text-slate-900">{{ activeAntrean.data_khusus.judul_keterangan }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-slate-500">Uraian / Isi Fakta Administratif</dt>
                            <dd class="mt-0.5 whitespace-pre-line text-slate-800">{{ activeAntrean.data_khusus.isi_keterangan || '—' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- Panel Riwayat Bansos 1 KK otomatis bila SKTM -->
                <BaseCallout
                    v-if="activeAntrean.jenis_surat === 'sktm' && bansosKkInfo"
                    variant="info"
                    title="Rujukan Verifikasi SKTM — Riwayat Bansos 1 KK"
                >
                    Status Ekonomi: <strong>{{ bansosKkInfo.status_kesejahteraan_label }}</strong> &bull;
                    Total Bantuan Aktif di KK ({{ bansosKkInfo.nomor_kk }}):
                    <strong>{{ bansosKkInfo.total_bantuan_aktif }} bantuan</strong>.
                </BaseCallout>

                <!-- 3. Form Pengesahan & Penerbitan -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Pejabat Penandatangan Surat <span class="text-red-600">*</span>
                        </label>
                        <select
                            v-model="verifikasiForm.opsi_penandatangan"
                            data-testid="select-verifikasi-penandatangan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        >
                            <option
                                v-for="pejabat in pejabat_penandatangan"
                                :key="pejabat.value"
                                :value="pejabat.value"
                            >
                                {{ pejabat.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Keperluan Surat
                        </label>
                        <input
                            v-model="verifikasiForm.keperluan"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>
                </div>

                <!-- 4. Tombol Aksi Verifikasi -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 pt-3">
                    <button
                        v-if="isOperator"
                        type="button"
                        data-testid="btn-verifikasi-tolak-shortcut"
                        class="inline-flex h-9 items-center justify-center rounded-md border border-red-200 bg-white px-3 text-xs font-medium text-red-600 hover:border-red-300 hover:bg-red-50"
                        @click="switchFromVerifikasiToTolak"
                    >
                        <XCircle class="mr-1 h-3.5 w-3.5" />
                        <span>Tolak Permohonan</span>
                    </button>

                    <div class="ml-auto flex items-center gap-2">
                        <BaseButton
                            variant="outline"
                            @click="showVerifikasiModal = false"
                        >
                            Batal
                        </BaseButton>
                        <BaseButton
                            variant="primary"
                            :loading="isSubmittingVerifikasi"
                            data-testid="btn-confirm-verifikasi"
                            @click="submitVerifikasi"
                        >
                            <Printer class="mr-1.5 h-4 w-4" />
                            <span>Terbitkan dan cetak</span>
                        </BaseButton>
                    </div>
                </div>
            </div>
        </BaseModal>

        <!-- Modal Tolak Permohonan -->
        <BaseModal
            :show="showTolakModal"
            title="Tolak Permohonan Surat Online"
            max-width="md"
            @close="showTolakModal = false"
        >
            <div class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-sm font-medium text-slate-700">
                        Alasan Penolakan <span class="text-red-600">*</span>
                    </label>
                    <textarea
                        v-model="alasanPenolakan"
                        rows="3"
                        data-testid="input-alasan-penolakan"
                        class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm text-slate-900"
                    />
                </div>
                <div class="flex items-center justify-end gap-2">
                    <BaseButton variant="outline" @click="showTolakModal = false">
                        Batal
                    </BaseButton>
                    <BaseButton
                        variant="destructive"
                        data-testid="btn-submit-tolak"
                        @click="submitTolak"
                    >
                        Tolak Permohonan
                    </BaseButton>
                </div>
            </div>
        </BaseModal>

        <!-- Modal Buat Surat di Loket (10 Template Baku) -->
        <BuatSuratLoketModal
            :show="showBuatModal"
            :opsi_jenis_surat="opsi_jenis_surat"
            :pejabat_penandatangan="pejabat_penandatangan"
            :tanggal_hari_ini="tanggal_hari_ini"
            :residents="residents"
            @close="showBuatModal = false"
        />
    </AppLayout>
</template>
