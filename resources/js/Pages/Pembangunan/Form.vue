<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConditionalBreadcrumb from '@/Components/ConditionalBreadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
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

const autoStatusPreview = computed(() => {
    const p = Number(form.progres_fisik || 0);
    if (p <= 0) {
        return { status: 'belum_dimulai', label: 'Belum Dimulai' };
    }
    if (p >= 100) {
        return { status: 'selesai', label: 'Selesai' };
    }
    return { status: 'sedang_dikerjakan', label: 'Sedang Dikerjakan' };
});

function submitForm() {
    if (isEditMode.value) {
        form.transform((data) => ({
            ...data,
            _method: 'put',
        })).post(`/panel/pembangunan/${props.proyek.id}`, {
            forceFormData: true,
        });
        return;
    }

    form.post('/panel/pembangunan', {
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

            <BaseCallout
                variant="info"
                title="Aturan Sinkronisasi APBDes & Validasi Bertahap 3 Titik Foto"
            >
                Setiap penyimpanan proyek akan otomatis menyinkronkan total Pagu &amp; Realisasi baris
                <strong>Bidang 2: Pelaksanaan Pembangunan Desa (Komponen Proyek Fisik)</strong> pada dokumen APBDes tahun terkait.
                Progres fisik <strong>1%–49%</strong> mewajibkan Foto 0%, <strong>50%–99%</strong> mewajibkan Foto 0% &amp; 50%, dan
                <strong>100%</strong> mewajibkan ketiga titik foto (0%, 50%, 100%).
            </BaseCallout>

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

                <!-- Bagian 2: Progres Fisik, Status Otomatis & Dokumentasi 3 Titik Foto -->
                <div class="rounded-lg border border-slate-200 bg-white p-6 space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                        <h2 class="text-base font-semibold text-slate-900">
                            2. Progres Fisik &amp; Dokumentasi 3 Titik Foto (0% &ndash; 50% &ndash; 100%)
                        </h2>
                        <div class="flex items-center gap-2 text-xs text-slate-600">
                            <span>Status Otomatis:</span>
                            <StatusBadge
                                :status="autoStatusPreview.status"
                                :label="autoStatusPreview.label"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label
                                    for="progres_fisik"
                                    class="text-sm font-medium text-slate-700"
                                >
                                    Persentase Progres Fisik Lapangan (%) <span class="text-red-600">*</span>
                                </label>
                                <span class="text-sm font-bold tabular-nums text-blue-700">
                                    {{ form.progres_fisik }}%
                                </span>
                            </div>

                            <div class="flex items-center gap-4">
                                <input
                                    id="progres_fisik_slider"
                                    v-model.number="form.progres_fisik"
                                    type="range"
                                    min="0"
                                    max="100"
                                    step="1"
                                    class="h-2 flex-1 cursor-pointer appearance-none rounded-lg bg-slate-200 accent-blue-700"
                                />
                                <input
                                    id="progres_fisik"
                                    v-model.number="form.progres_fisik"
                                    type="number"
                                    min="0"
                                    max="100"
                                    data-testid="input-progres-fisik"
                                    class="h-10 w-24 rounded-md border border-slate-300 bg-white px-3 text-right text-sm font-semibold tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                                />
                            </div>
                            <p v-if="form.errors.progres_fisik" class="text-xs font-medium text-red-600">
                                {{ form.errors.progres_fisik }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label
                                for="catatan_kendala"
                                class="block text-sm font-medium text-slate-700"
                            >
                                Catatan Kendala Lapangan (Opsional)
                            </label>
                            <textarea
                                id="catatan_kendala"
                                v-model="form.catatan_kendala"
                                rows="2"
                                placeholder="Contoh: Cuaca hujan intensif pada minggu ke-3 pengerjaan"
                                class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            />
                        </div>
                    </div>

                    <ProjectPhotoCheckpoints
                        v-model="photoFilesModel"
                        :existing-urls="existingPhotoUrls"
                        :progres-fisik="Number(form.progres_fisik || 0)"
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
