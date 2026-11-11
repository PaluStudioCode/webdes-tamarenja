<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConditionalBreadcrumb from '@/Components/ConditionalBreadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BaseButton from '@/Components/BaseButton.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DusunCombobox from '@/Components/DusunCombobox.vue';
import LeafletMapPicker from '@/Components/LeafletMapPicker.vue';
import ProjectPhotoCheckpoints from '@/Components/ProjectPhotoCheckpoints.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    mode: {
        type: String,
        default: 'create',
    },
    tahun_anggaran: {
        type: Number,
        required: true,
    },
    proyek: {
        type: Object,
        default: null,
    },
    daftar_dusun: {
        type: Array,
        default: () => [],
    },
    opsi_sumber_dana: {
        type: Array,
        default: () => [],
    },
    koordinat_default: {
        type: Object,
        default: () => ({ latitude: -0.5835, longitude: 119.7932 }),
    },
});

const isEditMode = computed(() => props.mode === 'edit' && Boolean(props.proyek?.id));

const breadcrumbItems = computed(() => [
    { label: 'Pembangunan Fisik', href: `/panel/pembangunan?tahun=${props.tahun_anggaran}` },
    { label: isEditMode.value ? 'Edit Proyek Fisik' : 'Tambah Proyek Fisik' },
]);

const form = useForm({
    tahun_anggaran: props.proyek?.tahun_anggaran ?? props.tahun_anggaran,
    nama_kegiatan: props.proyek?.nama_kegiatan ?? '',
    lokasi_dusun: props.proyek?.lokasi_dusun ?? '',
    sumber_dana: props.proyek?.sumber_dana ?? (props.opsi_sumber_dana?.[0]?.value || 'dds'),
    pagu_anggaran: props.proyek?.pagu_anggaran ?? 0,
    realisasi_anggaran: props.proyek?.realisasi_anggaran ?? 0,
    volume_fisik: props.proyek?.volume_fisik ?? '',
    waktu_pelaksanaan: props.proyek?.waktu_pelaksanaan ?? '',
    pelaksana_kegiatan: props.proyek?.pelaksana_kegiatan ?? '',
    progres_fisik: props.proyek?.progres_fisik ?? 0,
    catatan_kendala: props.proyek?.catatan_kendala ?? '',
    latitude: props.proyek?.latitude ?? null,
    longitude: props.proyek?.longitude ?? null,
    foto_0: null,
    foto_50: null,
    foto_100: null,
});

const photoFilesModel = computed({
    get: () => ({
        foto_0: form.foto_0,
        foto_50: form.foto_50,
        foto_100: form.foto_100,
    }),
    set: (val) => {
        form.foto_0 = val?.foto_0 ?? null;
        form.foto_50 = val?.foto_50 ?? null;
        form.foto_100 = val?.foto_100 ?? null;
    },
});

const existingPhotoUrls = computed(() => ({
    foto_0: props.proyek?.foto_0_url || '',
    foto_50: props.proyek?.foto_50_url || '',
    foto_100: props.proyek?.foto_100_url || '',
}));

const hasFoto100 = computed(() => Boolean(form.foto_100 || props.proyek?.foto_100_path));
const hasFoto50 = computed(() => Boolean(form.foto_50 || props.proyek?.foto_50_path));

const computedProgresFisik = computed(() => {
    if (hasFoto100.value) {
        return 100;
    }
    if (hasFoto50.value) {
        return 50;
    }
    return 0;
});

const autoStatusPreview = computed(() => {
    const p = computedProgresFisik.value;
    if (p >= 100) {
        return { status: 'selesai', label: 'Selesai (100%)' };
    }
    if (p >= 50) {
        return { status: 'sedang_dikerjakan', label: 'Sedang Dikerjakan (50%)' };
    }
    return { status: 'belum_dimulai', label: 'Belum Dimulai (0%)' };
});

function submitForm() {
    form.progres_fisik = computedProgresFisik.value;

    if (isEditMode.value) {
        form.transform((data) => ({
            ...data,
            progres_fisik: computedProgresFisik.value,
            _method: 'put',
        })).post(`/panel/pembangunan/${props.proyek.id}`, {
            forceFormData: true,
        });
        return;
    }

    form.transform((data) => ({
        ...data,
        progres_fisik: computedProgresFisik.value,
    })).post('/panel/pembangunan', {
        forceFormData: true,
    });
}
</script>

<template>
    <Head :title="isEditMode ? 'Edit Proyek Pembangunan Fisik' : 'Tambah Proyek Pembangunan Fisik'" />

    <AppLayout>
        <div class="space-y-6">
            <div>
                <ConditionalBreadcrumb :items="breadcrumbItems" />
                <PageHeader
                    :title="isEditMode ? 'Edit Proyek Pembangunan Fisik' : 'Tambah Proyek Pembangunan Fisik'"
                    subtitle="Rincian kegiatan infrastruktur desa, titik koordinat geotag, serta bukti foto tahapan 0%–50%–100%."
                >
                    <template #actions>
                        <Link
                            :href="`/panel/pembangunan?tahun=${form.tahun_anggaran}`"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            <ArrowLeft class="mr-1.5 h-4 w-4" />
                            <span>Kembali ke Daftar</span>
                        </Link>
                    </template>
                </PageHeader>
            </div>

            <form
                data-testid="form-proyek-pembangunan"
                class="space-y-6"
                @submit.prevent="submitForm"
            >
                <!-- Bagian 1: Identitas & Anggaran Kegiatan -->
                <div class="rounded-lg border border-slate-200 bg-white p-6 space-y-6">
                    <h2 class="text-base font-semibold text-slate-900 border-b border-slate-200 pb-3">
                        1. Identitas Kegiatan &amp; Anggaran
                    </h2>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-1.5">
                            <label
                                for="nama_kegiatan"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Nama Kegiatan Proyek Fisik <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="nama_kegiatan"
                                v-model="form.nama_kegiatan"
                                type="text"
                                required
                                data-testid="input-nama-kegiatan"
                                placeholder="Contoh: Pembangunan Rabat Beton RT 02"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                            <p v-if="form.errors.nama_kegiatan" class="text-xs font-medium text-red-600">
                                {{ form.errors.nama_kegiatan }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label
                                    for="tahun_anggaran"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Tahun Anggaran <span class="text-red-600">*</span>
                                </label>
                                <input
                                    id="tahun_anggaran"
                                    v-model.number="form.tahun_anggaran"
                                    type="number"
                                    min="2020"
                                    max="2100"
                                    required
                                    data-testid="input-tahun-anggaran"
                                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                                />
                                <p v-if="form.errors.tahun_anggaran" class="text-xs font-medium text-red-600">
                                    {{ form.errors.tahun_anggaran }}
                                </p>
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    for="sumber_dana"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Sumber Dana <span class="text-red-600">*</span>
                                </label>
                                <select
                                    id="sumber_dana"
                                    v-model="form.sumber_dana"
                                    data-testid="select-sumber-dana"
                                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                                >
                                    <option
                                        v-for="opt in opsi_sumber_dana"
                                        :key="opt.value"
                                        :value="opt.value"
                                    >
                                        {{ opt.label }}
                                    </option>
                                </select>
                                <p v-if="form.errors.sumber_dana" class="text-xs font-medium text-red-600">
                                    {{ form.errors.sumber_dana }}
                                </p>
                            </div>
                        </div>

                        <DusunCombobox
                            v-model="form.lokasi_dusun"
                            label="Lokasi Dusun"
                            :options="daftar_dusun"
                            :error="form.errors.lokasi_dusun"
                            required
                        />

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <CurrencyInput
                                id="pagu_anggaran"
                                v-model="form.pagu_anggaran"
                                label="Pagu Anggaran (Rp)"
                                :error="form.errors.pagu_anggaran"
                                required
                            />

                            <CurrencyInput
                                id="realisasi_anggaran"
                                v-model="form.realisasi_anggaran"
                                label="Realisasi Anggaran (Rp)"
                                helper-text="Tidak boleh melebihi Pagu Anggaran"
                                :error="form.errors.realisasi_anggaran"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label
                                for="volume_fisik"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Volume / Spesifikasi Fisik
                            </label>
                            <input
                                id="volume_fisik"
                                v-model="form.volume_fisik"
                                type="text"
                                placeholder="Contoh: Panjang 250 Meter × Lebar 3 Meter"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div class="space-y-1.5">
                                <label
                                    for="pelaksana_kegiatan"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Pelaksana Kegiatan
                                </label>
                                <input
                                    id="pelaksana_kegiatan"
                                    v-model="form.pelaksana_kegiatan"
                                    type="text"
                                    placeholder="Contoh: TPK Desa Tamarenja"
                                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                                />
                            </div>

                            <div class="space-y-1.5">
                                <label
                                    for="waktu_pelaksanaan"
                                    class="block text-sm font-medium text-slate-700"
                                >
                                    Waktu Pelaksanaan
                                </label>
                                <input
                                    id="waktu_pelaksanaan"
                                    v-model="form.waktu_pelaksanaan"
                                    type="text"
                                    placeholder="Contoh: 90 Hari Kalender"
                                    class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bagian 2: Dokumentasi 3 Titik Foto (0% – 50% – 100%) -->
                <div class="rounded-lg border border-slate-200 bg-white p-6 space-y-5">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                2. Dokumentasi Foto Lapangan (0% &ndash; 50% &ndash; 100%)
                            </h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Unggah foto sesuai tahapan pengerjaan. Status dan persentase proyek akan otomatis terhitung.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span>Status Otomatis:</span>
                            <StatusBadge
                                :status="autoStatusPreview.status"
                                :label="autoStatusPreview.label"
                            />
                        </div>
                    </div>

                    <ProjectPhotoCheckpoints
                        v-model="photoFilesModel"
                        :existing-urls="existingPhotoUrls"
                        :progres-fisik="computedProgresFisik"
                        :errors="form.errors"
                    />
                </div>

                <!-- Bagian 3: Peta Geotag Lokasi Proyek -->
                <div class="rounded-lg border border-slate-200 bg-white p-6">
                    <LeafletMapPicker
                        v-model:latitude="form.latitude"
                        v-model:longitude="form.longitude"
                        :default-center="koordinat_default"
                        :errors="form.errors"
                    />
                </div>

                <!-- Footer Tombol Simpan -->
                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="`/panel/pembangunan?tahun=${form.tahun_anggaran}`"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Batal
                    </Link>

                    <BaseButton
                        type="submit"
                        variant="primary"
                        :loading="form.processing"
                        data-testid="btn-submit-proyek"
                    >
                        <Save class="mr-1.5 h-4 w-4" />
                        <span>{{ isEditMode ? 'Simpan Perubahan Proyek' : 'Simpan Proyek Baru' }}</span>
                    </BaseButton>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
