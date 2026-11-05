<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    Eye,
    FileText,
    Pencil,
    Plus,
    Printer,
    Trash2,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';
import DataTable from '@/Components/DataTable.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseModal from '@/Components/BaseModal.vue';
import SlideOverDrawer from '@/Components/SlideOverDrawer.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    tahun_agenda: {
        type: Number,
        required: true,
    },
    surat_masuk: {
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
        active: false,
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
        active: true,
    },
]);

const searchQuery = ref(props.filters?.search || '');
const hasActiveFilters = computed(() => Boolean(searchQuery.value));

const filteredSuratMasuk = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.surat_masuk || [];
    }

    return (props.surat_masuk || []).filter((item) => {
        const matchNomor = String(item.nomor_surat_masuk || '')
            .toLowerCase()
            .includes(q);
        const matchPengirim = String(item.pengirim || '')
            .toLowerCase()
            .includes(q);
        const matchPerihal = String(item.perihal || '')
            .toLowerCase()
            .includes(q);
        const matchDisposisi = String(item.tujuan_disposisi || '')
            .toLowerCase()
            .includes(q);

        return matchNomor || matchPengirim || matchPerihal || matchDisposisi;
    });
});

function resetFilters() {
    searchQuery.value = '';
}

const columns = [
    { key: 'agenda', label: 'No. Agenda & Tgl Terima' },
    { key: 'surat', label: 'Nomor & Tanggal Surat' },
    { key: 'pengirim_perihal', label: 'Pengirim & Perihal' },
    { key: 'disposisi', label: 'Disposisi' },
    { key: 'berkas', label: 'Berkas Scan' },
    { key: 'actions', label: 'Aksi', align: 'right' },
];

// Modal Tambah / Edit Surat Masuk
const showFormModal = ref(false);
const isEditing = ref(false);
const editingId = ref(null);
const isSubmitting = ref(false);

const form = reactive({
    nomor_surat_masuk: '',
    tanggal_surat: '',
    tanggal_terima: new Date().toISOString().slice(0, 10),
    pengirim: '',
    perihal: '',
    tujuan_disposisi: '',
    catatan_disposisi: '',
    berkas_scan: null,
});

function openCreateModal() {
    isEditing.value = false;
    editingId.value = null;
    form.nomor_surat_masuk = '';
    form.tanggal_surat = '';
    form.tanggal_terima = new Date().toISOString().slice(0, 10);
    form.pengirim = '';
    form.perihal = '';
    form.tujuan_disposisi = '';
    form.catatan_disposisi = '';
    form.berkas_scan = null;
    showFormModal.value = true;
}

function openEditModal(item) {
    isEditing.value = true;
    editingId.value = item.id;
    form.nomor_surat_masuk = item.nomor_surat_masuk || '';
    form.tanggal_surat = item.tanggal_surat || '';
    form.tanggal_terima =
        item.tanggal_terima || new Date().toISOString().slice(0, 10);
    form.pengirim = item.pengirim || '';
    form.perihal = item.perihal || '';
    form.tujuan_disposisi = item.tujuan_disposisi || '';
    form.catatan_disposisi = item.catatan_disposisi || '';
    form.berkas_scan = null;
    showFormModal.value = true;
}

function submitSuratMasuk() {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    const payload = {
        nomor_surat_masuk: form.nomor_surat_masuk,
        tanggal_surat: form.tanggal_surat || null,
        tanggal_terima: form.tanggal_terima,
        pengirim: form.pengirim,
        perihal: form.perihal,
        tujuan_disposisi: form.tujuan_disposisi || null,
        catatan_disposisi: form.catatan_disposisi || null,
    };

    if (form.berkas_scan) {
        payload.berkas_scan = form.berkas_scan;
    }

    if (isEditing.value && editingId.value) {
        router.post(
            `/panel/persuratan/surat-masuk/${editingId.value}`,
            {
                ...payload,
                _method: 'PUT',
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    showFormModal.value = false;
                },
                onFinish: () => {
                    isSubmitting.value = false;
                },
            },
        );
        return;
    }

    router.post('/panel/persuratan/surat-masuk', payload, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showFormModal.value = false;
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}

async function handleDelete(item) {
    const ok = await confirm.ask({
        title: 'Hapus Catatan Surat Masuk?',
        message: `Surat Masuk Nomor "${item.nomor_surat_masuk}" dari ${item.pengirim} akan dihapus dari daftar aktif (nomor urut agenda tetap terjaga).`,
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        variant: 'danger',
    });

    if (!ok) {
        return;
    }

    router.delete(`/panel/persuratan/surat-masuk/${item.id}`, {
        preserveScroll: true,
    });
}

// Slide-Over Drawer Pratinjau Scan Private Storage
const showPreviewDrawer = ref(false);
const previewItem = ref(null);

function openPreviewDrawer(item) {
    previewItem.value = item;
    showPreviewDrawer.value = true;
}

function resolveStreamUrl(item) {
    if (!item) {
        return '';
    }
    return (
        item.scan_stream_url ||
        `/panel/persuratan/surat-masuk/${item.id}/berkas`
    );
}
</script>

<template>
    <Head :title="`Buku Agenda Surat Masuk TA ${tahun_agenda}`" />

    <AppLayout>
        <div class="space-y-6">
            <PageHeader
                title="Persuratan & Buku Agenda Desa"
                :subtitle="`Buku Agenda Surat Masuk Tahun Anggaran ${tahun_agenda}. Berkas scan tersimpan aman di Private Storage.`"
            >
                <template #actions>
                    <a
                        :href="`/panel/persuratan/surat-masuk/cetak-agenda?tahun=${tahun_agenda}`"
                        data-testid="btn-cetak-agenda-masuk"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <Printer class="mr-1.5 h-4 w-4" />
                        <span>Cetak Agenda F4</span>
                    </a>

                    <BaseButton
                        v-if="isOperator"
                        variant="primary"
                        data-testid="btn-tambah-surat-masuk"
                        @click="openCreateModal"
                    >
                        <Plus class="mr-1.5 h-4 w-4" />
                        <span>Catat Surat Masuk</span>
                    </BaseButton>
                </template>
            </PageHeader>

            <!-- Navigasi 3 Tab Grup Persuratan -->
            <ContextualTabs :tabs="persuratanTabs" />

            <!-- Filter Bar -->
            <UnifiedFilterBar
                v-model="searchQuery"
                search-placeholder="Cari nomor surat masuk, pengirim, perihal, atau disposisi..."
                :has-active-filters="hasActiveFilters"
                @reset="resetFilters"
            />

            <!-- Tabel Buku Agenda Surat Masuk -->
            <DataTable
                :columns="columns"
                :rows="filteredSuratMasuk"
                :empty-title="`Belum ada catatan Buku Agenda Surat Masuk pada Tahun ${tahun_agenda}`"
                empty-description="Catat setiap surat masuk dari instansi kecamatan, kabupaten, maupun lembaga mitra beserta unggahan berkas scan."
            >
                <template #cell-agenda="{ row }">
                    <div>
                        <span
                            class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-bold tabular-nums text-slate-800"
                        >
                            #{{
                                row.nomor_urut_padded ||
                                String(row.nomor_urut_agenda).padStart(3, '0')
                            }}
                        </span>
                        <p class="mt-1 text-xs tabular-nums text-slate-500">
                            Terima: {{ row.tanggal_terima || '—' }}
                        </p>
                    </div>
                </template>

                <template #cell-surat="{ row }">
                    <div>
                        <p class="font-semibold tabular-nums text-slate-900">
                            {{ row.nomor_surat_masuk }}
                        </p>
                        <p class="text-xs tabular-nums text-slate-500">
                            Tgl Surat: {{ row.tanggal_surat || '—' }}
                        </p>
                    </div>
                </template>

                <template #cell-pengirim_perihal="{ row }">
                    <div>
                        <p class="font-semibold text-slate-900">
                            {{ row.pengirim }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-600">
                            {{ row.perihal }}
                        </p>
                    </div>
                </template>

                <template #cell-disposisi="{ row }">
                    <div class="text-xs">
                        <p class="font-medium text-slate-800">
                            {{ row.tujuan_disposisi || '—' }}
                        </p>
                        <p
                            v-if="row.catatan_disposisi"
                            class="mt-0.5 text-slate-500"
                        >
                            {{ row.catatan_disposisi }}
                        </p>
                    </div>
                </template>

                <template #cell-berkas="{ row }">
                    <button
                        v-if="row.has_scan || row.berkas_scan_path"
                        type="button"
                        :data-testid="`btn-preview-scan-${row.id}`"
                        class="inline-flex h-8 items-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-2.5 text-xs font-medium text-blue-700 transition-colors hover:bg-blue-100"
                        @click="openPreviewDrawer(row)"
                    >
                        <Eye class="h-3.5 w-3.5" />
                        <span>Lihat Scan</span>
                    </button>
                    <span v-else class="text-xs text-slate-400">
                        Belum diunggah
                    </span>
                </template>

                <template #cell-actions="{ row }">
                    <div
                        v-if="isOperator"
                        class="flex items-center justify-end gap-1.5"
                    >
                        <button
                            type="button"
                            :data-testid="`btn-edit-surat-masuk-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            @click="openEditModal(row)"
                        >
                            <Pencil class="mr-1 h-3.5 w-3.5" />
                            <span>Edit</span>
                        </button>

                        <button
                            type="button"
                            :data-testid="`btn-delete-surat-masuk-${row.id}`"
                            class="inline-flex h-8 items-center justify-center rounded-md px-2 text-xs font-medium text-red-600 hover:bg-red-50"
                            @click="handleDelete(row)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <span v-else class="text-xs text-slate-400">Read-Only</span>
                </template>
            </DataTable>
        </div>

        <!-- Modal Tambah / Edit Surat Masuk -->
        <BaseModal
            :show="showFormModal"
            :title="
                isEditing
                    ? 'Perbarui Catatan Surat Masuk'
                    : 'Catat Surat Masuk Baru'
            "
            max-width="xl"
            @close="showFormModal = false"
        >
            <form
                data-testid="form-surat-masuk"
                class="space-y-4"
                @submit.prevent="submitSuratMasuk"
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Nomor Surat Masuk <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.nomor_surat_masuk"
                            type="text"
                            required
                            data-testid="input-nomor-surat-masuk"
                            placeholder="Contoh: 005/124/KEC-DGL/II/2026"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Tanggal Terima di Desa <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.tanggal_terima"
                            type="date"
                            required
                            data-testid="input-tanggal-terima"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Tanggal Tertera di Surat
                        </label>
                        <input
                            v-model="form.tanggal_surat"
                            type="date"
                            data-testid="input-tanggal-surat-masuk"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Instansi / Pengirim Surat <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.pengirim"
                            type="text"
                            required
                            data-testid="input-pengirim-surat"
                            placeholder="Contoh: Camat Sindue Tobata / Dinas Sosial Kab. Donggala"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700">
                            Perihal / Ringkasan Isi <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.perihal"
                            type="text"
                            required
                            data-testid="input-perihal-surat"
                            placeholder="Contoh: Undangan Musrenbang tingkat Kecamatan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Tujuan Disposisi
                        </label>
                        <input
                            v-model="form.tujuan_disposisi"
                            type="text"
                            data-testid="input-tujuan-disposisi"
                            placeholder="Contoh: Sekretaris Desa / Kaur Keuangan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-sm font-medium text-slate-700">
                            Catatan Disposisi Kepala Desa
                        </label>
                        <input
                            v-model="form.catatan_disposisi"
                            type="text"
                            data-testid="input-catatan-disposisi"
                            placeholder="Contoh: Tindak lanjuti & hadiri"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>
                </div>

                <FileDropzone
                    v-model="form.berkas_scan"
                    label="Unggah Scan Surat Masuk (Opsional — Private Storage)"
                    accept=".pdf,.jpg,.jpeg,.png"
                    helper-text="Format PDF, JPG, atau PNG • Maks. 5 MB"
                />

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
                        data-testid="btn-submit-surat-masuk"
                    >
                        {{
                            isEditing
                                ? 'Simpan Perubahan'
                                : 'Simpan ke Buku Agenda'
                        }}
                    </BaseButton>
                </div>
            </form>
        </BaseModal>

        <!-- Slide-Over Drawer Pratinjau Scan Surat Masuk -->
        <SlideOverDrawer
            :show="showPreviewDrawer"
            :title="
                previewItem
                    ? `Pratinjau Scan — Agenda #${previewItem.nomor_urut_padded || previewItem.nomor_urut_agenda}`
                    : 'Pratinjau Berkas Scan Surat Masuk'
            "
            :subtitle="
                previewItem
                    ? `${previewItem.nomor_surat_masuk} • ${previewItem.pengirim}`
                    : ''
            "
            max-width="2xl"
            @close="showPreviewDrawer = false"
        >
            <div v-if="previewItem" class="space-y-4">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-semibold text-slate-900">
                                {{ previewItem.perihal }}
                            </p>
                            <p class="text-xs text-slate-600">
                                Pengirim: <strong>{{ previewItem.pengirim }}</strong>
                                &bull; Tgl Terima: {{ previewItem.tanggal_terima }}
                            </p>
                            <p
                                v-if="previewItem.tujuan_disposisi"
                                class="text-xs text-slate-600"
                            >
                                Disposisi: <strong>{{ previewItem.tujuan_disposisi }}</strong>
                                <span v-if="previewItem.catatan_disposisi">
                                    — {{ previewItem.catatan_disposisi }}
                                </span>
                            </p>
                        </div>

                        <a
                            :href="resolveStreamUrl(previewItem)"
                            target="_blank"
                            rel="noopener noreferrer"
                            data-testid="link-open-scan-tab"
                            class="inline-flex h-8 shrink-0 items-center gap-1 rounded-md border border-slate-300 bg-white px-2.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <ExternalLink class="h-3.5 w-3.5" />
                            <span>Buka Tab Baru</span>
                        </a>
                    </div>
                </div>

                <!-- Pratinjau PDF atau Gambar dari Streaming Terproteksi -->
                <div
                    data-testid="scan-preview-container"
                    class="overflow-hidden rounded-lg border border-slate-200 bg-slate-100"
                >
                    <iframe
                        v-if="previewItem.scan_type === 'pdf'"
                        :src="resolveStreamUrl(previewItem)"
                        title="Pratinjau PDF Surat Masuk"
                        data-testid="scan-preview-iframe"
                        class="h-[520px] w-full border-0"
                    />
                    <img
                        v-else
                        :src="resolveStreamUrl(previewItem)"
                        :alt="`Scan ${previewItem.nomor_surat_masuk}`"
                        data-testid="scan-preview-image"
                        class="mx-auto max-h-[560px] w-auto object-contain p-2"
                    />
                </div>
            </div>
        </SlideOverDrawer>
    </AppLayout>
</template>
