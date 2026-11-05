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
import { useConfirm } from '@/Composables/useConfirm';

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
});

const page = usePage();
const confirm = useConfirm();

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
    { key: 'kontak', label: 'Waktu & WhatsApp' },
    { key: 'status', label: 'Status & Nomor Surat' },
    { key: 'actions', label: 'Aksi Layanan', align: 'right' },
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
                    <Link
                        v-if="isOperator"
                        href="/panel/persuratan/surat-keluar/buat"
                        data-testid="btn-buat-surat-loket"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-medium text-white transition-colors hover:bg-blue-800"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Buat Surat di Loket</span>
                    </Link>
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
                    <div class="text-xs tabular-nums">
                        <p class="text-slate-700">{{ row.created_at || '—' }}</p>
                        <p v-if="row.no_wa_pemohon" class="mt-0.5 font-medium text-emerald-700">
                            WA: {{ row.no_wa_pemohon }}
                        </p>
                    </div>
                </template>

                <template #cell-status="{ row }">
                    <div class="space-y-1">
                        <StatusBadge
                            :status="row.status"
                            :label="row.status_label"
                        />
                        <p
                            v-if="row.nomor_surat"
                            class="text-xs font-semibold tabular-nums text-slate-700"
                        >
                            {{ row.nomor_surat }}
                        </p>
                        <p
                            v-if="row.alasan_penolakan"
                            class="text-xs text-red-600"
                        >
                            Alasan: {{ row.alasan_penolakan }}
                        </p>
                    </div>
                </template>

                <template #cell-actions="{ row }">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <!-- Aksi saat Menunggu Verifikasi (Khusus Operator) -->
                        <template v-if="row.status === 'menunggu_verifikasi' && isOperator">
                            <BaseButton
                                variant="primary"
                                size="sm"
                                :data-testid="`btn-verifikasi-antrean-${row.id}`"
                                @click="openVerifikasiModal(row)"
                            >
                                <CheckCircle2 class="mr-1 h-3.5 w-3.5" />
                                <span>Verifikasi &amp; Cetak</span>
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

                        <!-- Aksi saat sudah Terbit (Cetak PDF F4 & Kabari Warga via WA) -->
                        <template v-else-if="row.status === 'terbit'">
                            <a
                                :href="`/panel/persuratan/surat-keluar/${row.id}/pdf`"
                                :data-testid="`btn-cetak-antrean-${row.id}`"
                                class="inline-flex h-8 items-center justify-center rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <Printer class="mr-1 h-3.5 w-3.5" />
                                <span>Cetak F4</span>
                            </a>

                            <a
                                v-if="row.no_wa_pemohon"
                                :href="buildWaPickupUrl(row)"
                                target="_blank"
                                rel="noopener noreferrer"
                                :data-testid="`btn-wa-pickup-${row.id}`"
                                class="inline-flex h-8 items-center justify-center rounded-md bg-emerald-600 px-3 text-xs font-medium text-white transition-colors hover:bg-emerald-700"
                            >
                                <MessageCircle class="mr-1 h-3.5 w-3.5" />
                                <span>Kabari Warga via WA</span>
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
            max-width="lg"
            @close="showVerifikasiModal = false"
        >
            <div v-if="activeAntrean" class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3.5 text-sm">
                    <p class="font-semibold text-slate-900">
                        {{ activeAntrean.nama_pemohon }} (NIK: {{ activeAntrean.nik_pemohon }})
                    </p>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Jenis Surat: <strong>{{ activeAntrean.jenis_surat_label }}</strong>
                    </p>
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

                <div class="flex items-center justify-end gap-2 pt-2">
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
                        <span>Terbitkan &amp; Cetak PDF F4</span>
                    </BaseButton>
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
    </AppLayout>
</template>
