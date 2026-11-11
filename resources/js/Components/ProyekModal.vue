<script setup>
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Save, X } from 'lucide-vue-next';
import BaseModal from '@/Components/BaseModal.vue';
import BaseButton from '@/Components/BaseButton.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DusunCombobox from '@/Components/DusunCombobox.vue';
import LeafletMapPicker from '@/Components/LeafletMapPicker.vue';
import ProjectPhotoCheckpoints from '@/Components/ProjectPhotoCheckpoints.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    isEdit: {
        type: Boolean,
        default: false,
    },
    proyek: {
        type: Object,
        default: null,
    },
    tahunAnggaran: {
        type: Number,
        required: true,
    },
    daftarDusun: {
        type: Array,
        default: () => [],
    },
    opsiSumberDana: {
        type: Array,
        default: () => [],
    },
    koordinatDefault: {
        type: Object,
        default: () => ({ latitude: -0.5835, longitude: 119.7932 }),
    },
});

const emit = defineEmits(['close']);

const page = usePage();
const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const form = useForm({
    tahun_anggaran: props.tahunAnggaran,
    nama_kegiatan: '',
    lokasi_dusun: props.daftarDusun?.[0] || 'Dusun I',
    sumber_dana: props.opsiSumberDana?.[0]?.value || 'dds',
    pagu_anggaran: 0,
    realisasi_anggaran: 0,
    volume_fisik: '',
    waktu_pelaksanaan: '',
    pelaksana_kegiatan: '',
    progres_fisik: 0,
    catatan_kendala: '',
    latitude: null,
    longitude: null,
    foto_0: null,
    foto_50: null,
    foto_100: null,
});

function initFormData() {
    form.clearErrors();

    if (props.isEdit && props.proyek) {
        form.tahun_anggaran = props.proyek.tahun_anggaran ?? props.tahunAnggaran;
        form.nama_kegiatan = props.proyek.nama_kegiatan ?? '';
        form.lokasi_dusun = props.proyek.lokasi_dusun ?? (props.daftarDusun?.[0] || 'Dusun I');
        form.sumber_dana = props.proyek.sumber_dana ?? (props.opsiSumberDana?.[0]?.value || 'dds');
        form.pagu_anggaran = Number(props.proyek.pagu_anggaran ?? 0);
        form.realisasi_anggaran = Number(props.proyek.realisasi_anggaran ?? 0);
        form.volume_fisik = props.proyek.volume_fisik ?? '';
        form.waktu_pelaksanaan = props.proyek.waktu_pelaksanaan ?? '';
        form.pelaksana_kegiatan = props.proyek.pelaksana_kegiatan ?? '';
        form.progres_fisik = Number(props.proyek.progres_fisik ?? 0);
        form.catatan_kendala = props.proyek.catatan_kendala ?? '';
        form.latitude = props.proyek.latitude !== null && props.proyek.latitude !== undefined
            ? Number(props.proyek.latitude)
            : null;
        form.longitude = props.proyek.longitude !== null && props.proyek.longitude !== undefined
            ? Number(props.proyek.longitude)
            : null;
        form.foto_0 = null;
        form.foto_50 = null;
        form.foto_100 = null;
        return;
    }

    form.reset();
    form.tahun_anggaran = props.tahunAnggaran;
    form.nama_kegiatan = '';
    form.lokasi_dusun = props.daftarDusun?.[0] || 'Dusun I';
    form.sumber_dana = props.opsiSumberDana?.[0]?.value || 'dds';
    form.pagu_anggaran = 0;
    form.realisasi_anggaran = 0;
    form.volume_fisik = '';
    form.waktu_pelaksanaan = '';
    form.pelaksana_kegiatan = '';
    form.progres_fisik = 0;
    form.catatan_kendala = '';
    form.latitude = null;
    form.longitude = null;
    form.foto_0 = null;
    form.foto_50 = null;
    form.foto_100 = null;
}

watch(
    () => props.show,
    (isOpen) => {
        if (isOpen) {
            initFormData();
        }
    },
    { immediate: true },
);

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

    if (props.isEdit && props.proyek?.id) {
        form.transform((data) => ({
            ...data,
            progres_fisik: computedProgresFisik.value,
            _method: 'put',
        })).post(`/panel/pembangunan/${props.proyek.id}`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                emit('close');
            },
        });
        return;
    }

    form.transform((data) => ({
        ...data,
        progres_fisik: computedProgresFisik.value,
    })).post('/panel/pembangunan', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('close');
        },
    });
}
</script>

<template>
    <BaseModal
        :show="show"
        :title="isEdit ? 'Edit Proyek Pembangunan Fisik' : 'Tambah Proyek Pembangunan Fisik'"
        max-width="3xl"
        data-testid="modal-proyek-pembangunan"
        @close="emit('close')"
    >
        <form
            id="proyek-modal-form"
            data-testid="form-proyek-modal"
            class="space-y-6"
            @submit.prevent="submitForm"
        >
            <!-- Bagian 1: Identitas & Anggaran Kegiatan -->
            <div class="space-y-4">
                <h3 class="border-b border-slate-200 pb-2 text-sm font-semibold text-slate-900">
                    1. Identitas Kegiatan &amp; Anggaran
                </h3>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2 space-y-1.5">
                        <label
                            for="modal_nama_kegiatan"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Nama Kegiatan Proyek Fisik <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="modal_nama_kegiatan"
                            v-model="form.nama_kegiatan"
                            type="text"
                            required
                            data-testid="input-nama-kegiatan-modal"
                            placeholder="Contoh: Pembangunan Rabat Beton RT 02"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                        <p v-if="form.errors.nama_kegiatan" class="text-xs font-medium text-red-600">
                            {{ form.errors.nama_kegiatan }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="modal_tahun_anggaran"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Tahun Anggaran <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="modal_tahun_anggaran"
                            v-model.number="form.tahun_anggaran"
                            type="number"
                            min="2020"
                            max="2100"
                            required
                            data-testid="input-tahun-anggaran-modal"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                        <p v-if="form.errors.tahun_anggaran" class="text-xs font-medium text-red-600">
                            {{ form.errors.tahun_anggaran }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="modal_sumber_dana"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Sumber Dana <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="modal_sumber_dana"
                            v-model="form.sumber_dana"
                            data-testid="select-sumber-dana-modal"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        >
                            <option
                                v-for="opt in opsiSumberDana"
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

                    <DusunCombobox
                        v-model="form.lokasi_dusun"
                        label="Lokasi Dusun"
                        :options="daftarDusun"
                        :error="form.errors.lokasi_dusun"
                        required
                    />

                    <div class="space-y-1.5">
                        <label
                            for="modal_volume_fisik"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Volume / Spesifikasi Fisik
                        </label>
                        <input
                            id="modal_volume_fisik"
                            v-model="form.volume_fisik"
                            type="text"
                            placeholder="Contoh: 250 M × 3 M"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                    </div>

                    <CurrencyInput
                        id="modal_pagu_anggaran"
                        v-model="form.pagu_anggaran"
                        label="Pagu Anggaran (Rp)"
                        :error="form.errors.pagu_anggaran"
                        required
                    />

                    <CurrencyInput
                        id="modal_realisasi_anggaran"
                        v-model="form.realisasi_anggaran"
                        label="Realisasi Anggaran (Rp)"
                        helper-text="Tidak boleh melebihi Pagu Anggaran"
                        :error="form.errors.realisasi_anggaran"
                    />

                    <div class="space-y-1.5">
                        <label
                            for="modal_pelaksana_kegiatan"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Pelaksana Kegiatan
                        </label>
                        <input
                            id="modal_pelaksana_kegiatan"
                            v-model="form.pelaksana_kegiatan"
                            type="text"
                            placeholder="Contoh: TPK Desa Tamarenja"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <label
                            for="modal_waktu_pelaksanaan"
                            class="block text-sm font-medium text-slate-700"
                        >
                            Waktu Pelaksanaan
                        </label>
                        <input
                            id="modal_waktu_pelaksanaan"
                            v-model="form.waktu_pelaksanaan"
                            type="text"
                            placeholder="Contoh: 90 Hari Kalender"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                        />
                    </div>
                </div>
            </div>

            <!-- Bagian 2: Dokumentasi 3 Titik Foto (0% – 50% – 100%) -->
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-2">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">
                            2. Dokumentasi Foto Lapangan (0% &ndash; 50% &ndash; 100%)
                        </h3>
                        <p class="text-xs text-slate-500">
                            Unggah foto tahapan fisik. Status dan progres otomatis terhitung.
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

            <!-- Bagian 3: Titik Koordinat Geotagging Peta -->
            <div class="space-y-3">
                <LeafletMapPicker
                    v-model:latitude="form.latitude"
                    v-model:longitude="form.longitude"
                    :default-center="koordinatDefault"
                    :errors="form.errors"
                />
            </div>
        </form>

        <template #footer>
            <BaseButton
                variant="ghost"
                type="button"
                data-testid="btn-cancel-proyek-modal"
                @click="emit('close')"
            >
                Batal
            </BaseButton>

            <BaseButton
                variant="primary"
                type="submit"
                form="proyek-modal-form"
                :loading="form.processing"
                data-testid="btn-submit-proyek-modal"
                @click="submitForm"
            >
                <Save class="mr-1.5 h-4 w-4" />
                <span>{{ isEdit ? 'Simpan Perubahan' : 'Simpan Proyek' }}</span>
            </BaseButton>
        </template>
    </BaseModal>
</template>
