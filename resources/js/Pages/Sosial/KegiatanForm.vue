<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    CheckCircle2,
    FileEdit,
    ImagePlus,
    Star,
    Trash2,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConditionalBreadcrumb from '@/Components/ConditionalBreadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCallout from '@/Components/BaseCallout.vue';

const MAX_PHOTOS = 5;

const props = defineProps({
    is_edit: {
        type: Boolean,
        default: false,
    },
    kegiatan: {
        type: Object,
        default: null,
    },
    daftar_dusun: {
        type: Array,
        default: () => ['Dusun I', 'Dusun II', 'Dusun III'],
    },
    opsi_kategori: {
        type: Array,
        default: () => [
            { value: 'penyaluran_bansos', label: 'Penyaluran Bantuan Sosial' },
            { value: 'posyandu_kesehatan', label: 'Posyandu & Kesehatan' },
            { value: 'gotong_royong', label: 'Gotong Royong' },
            { value: 'musdes', label: 'Musyawarah Desa (Musdes)' },
            { value: 'pelatihan_bumdes', label: 'Pelatihan & BUMDes' },
        ],
    },
    opsi_status_publikasi: {
        type: Array,
        default: () => [
            { value: 'draft', label: 'Draft' },
            { value: 'published', label: 'Published' },
        ],
    },
    tanggal_hari_ini: {
        type: String,
        default: () => new Date().toISOString().slice(0, 10),
    },
});

const page = usePage();
const errors = computed(() => page.props?.errors || {});

const isOperator = computed(
    () => page.props?.auth?.user?.role === 'operator',
);

const breadcrumbs = computed(() => [
    {
        label: 'Dokumentasi Kegiatan Desa',
        href: '/panel/sosial/kegiatan',
    },
    {
        label: props.is_edit
            ? 'Edit Dokumentasi Kegiatan'
            : 'Buat Dokumentasi Kegiatan',
    },
]);

const form = reactive({
    judul: props.kegiatan?.judul || '',
    kategori: props.kegiatan?.kategori || 'penyaluran_bansos',
    tanggal_kegiatan:
        props.kegiatan?.tanggal_kegiatan || props.tanggal_hari_ini,
    lokasi_dusun:
        props.kegiatan?.lokasi_dusun || props.daftar_dusun[0] || 'Dusun I',
    uraian: props.kegiatan?.uraian || '',
    status_publikasi: props.kegiatan?.status_publikasi || 'draft',
});

// Daftar terpadu foto (existing + new) untuk pengurutan 1 s/d 5 foto
const galleryItems = ref(
    Array.isArray(props.kegiatan?.foto)
        ? props.kegiatan.foto.map((f) => ({
              type: 'existing',
              id: f.id,
              foto_path: f.foto_path,
              previewUrl: f.foto_url || `/storage/${f.foto_path}`,
              name: f.foto_path?.split('/').pop() || `Foto #${f.id}`,
          }))
        : [],
);

const deletedExistingIds = ref([]);
const photoLimitWarning = ref('');
const publishedPhotoError = ref('');
const isSubmitting = ref(false);

const sampulUtamaItem = computed(() => galleryItems.value[0] || null);
const remainingQuota = computed(() =>
    Math.max(0, MAX_PHOTOS - galleryItems.value.length),
);

function createObjectPreview(file) {
    if (
        typeof URL !== 'undefined' &&
        typeof URL.createObjectURL === 'function'
    ) {
        try {
            return URL.createObjectURL(file);
        } catch {
            return '';
        }
    }
    return '';
}

function addFiles(rawFiles) {
    if (!isOperator.value) {
        return;
    }

    const incoming = Array.from(rawFiles || []);
    if (incoming.length === 0) {
        return;
    }

    photoLimitWarning.value = '';
    publishedPhotoError.value = '';

    const available = MAX_PHOTOS - galleryItems.value.length;
    if (available <= 0) {
        photoLimitWarning.value =
            'Maksimal 5 foto dokumentasi per kegiatan. Hapus foto yang ada bila ingin mengganti.';
        return;
    }

    if (incoming.length > available) {
        photoLimitWarning.value = `Batas maksimal adalah ${MAX_PHOTOS} foto. Hanya ${available} foto pertama yang ditambahkan.`;
    }

    const accepted = incoming.slice(0, available);
    for (const file of accepted) {
        galleryItems.value.push({
            type: 'new',
            file,
            previewUrl: createObjectPreview(file),
            name: file.name || 'foto-baru.jpg',
        });
    }
}

function handleFileSelect(event) {
    addFiles(event.target.files);
    if (event.target) {
        event.target.value = '';
    }
}

function movePhotoUp(index) {
    if (!isOperator.value || index <= 0) {
        return;
    }
    const items = [...galleryItems.value];
    const temp = items[index - 1];
    items[index - 1] = items[index];
    items[index] = temp;
    galleryItems.value = items;
}

function movePhotoDown(index) {
    if (!isOperator.value || index >= galleryItems.value.length - 1) {
        return;
    }
    const items = [...galleryItems.value];
    const temp = items[index + 1];
    items[index + 1] = items[index];
    items[index] = temp;
    galleryItems.value = items;
}

function removePhoto(index) {
    if (!isOperator.value) {
        return;
    }
    const target = galleryItems.value[index];
    if (!target) {
        return;
    }
    if (target.type === 'existing' && target.id) {
        deletedExistingIds.value.push(target.id);
    }
    galleryItems.value.splice(index, 1);
    photoLimitWarning.value = '';
}

function submitWithStatus(targetStatus) {
    if (!isOperator.value || isSubmitting.value) {
        return;
    }

    form.status_publikasi = targetStatus;
    publishedPhotoError.value = '';

    if (targetStatus === 'published' && galleryItems.value.length < 1) {
        publishedPhotoError.value =
            'Minimal 1 foto dokumentasi wajib diunggah untuk menerbitkan kegiatan (Published).';
        return;
    }

    isSubmitting.value = true;

    const existingFotoIds = galleryItems.value
        .filter((item) => item.type === 'existing' && item.id)
        .map((item) => item.id);

    const newFiles = galleryItems.value
        .filter((item) => item.type === 'new' && item.file)
        .map((item) => item.file);

    const payload = {
        judul: form.judul,
        kategori: form.kategori,
        tanggal_kegiatan: form.tanggal_kegiatan,
        lokasi_dusun: form.lokasi_dusun,
        uraian: form.uraian,
        status_publikasi: form.status_publikasi,
        foto_baru: newFiles,
    };

    if (props.is_edit && props.kegiatan?.id) {
        router.post(
            `/panel/sosial/kegiatan/${props.kegiatan.id}`,
            {
                ...payload,
                _method: 'PUT',
                existing_foto_ids: existingFotoIds,
                hapus_foto_ids: deletedExistingIds.value,
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    isSubmitting.value = false;
                },
            },
        );
        return;
    }

    router.post('/panel/sosial/kegiatan', payload, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}
</script>

<template>
    <Head
        :title="
            is_edit
                ? 'Edit Dokumentasi Kegiatan Desa'
                : 'Buat Dokumentasi Kegiatan Desa'
        "
    />

    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <ConditionalBreadcrumb :items="breadcrumbs" />

            <PageHeader
                :title="
                    is_edit
                        ? 'Perbarui Dokumentasi Kegiatan Desa'
                        : 'Buat Dokumentasi Kegiatan Desa'
                "
                subtitle="Unggah dan susun urutan 1–5 foto dokumentasi lapangan. Urutan #1 otomatis menjadi foto sampul utama kartu berita."
            >
                <template #actions>
                    <span
                        v-if="!isOperator"
                        data-testid="readonly-kades-badge"
                        class="inline-flex items-center rounded-md border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                    >
                        Mode Baca (Read-Only Kepala Desa)
                    </span>
                </template>
            </PageHeader>

            <form
                data-testid="form-kegiatan-desa"
                class="space-y-6"
                @submit.prevent="submitWithStatus(form.status_publikasi)"
            >
                <!-- Bagian 1: Informasi Kegiatan -->
                <section
                    class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
                >
                    <h2 class="border-b border-slate-100 pb-3 text-base font-semibold text-slate-900">
                        1. Identitas &amp; Uraian Kegiatan
                    </h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Judul Kegiatan Desa <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.judul"
                                type="text"
                                required
                                :disabled="!isOperator"
                                data-testid="input-judul-kegiatan"
                                placeholder="Contoh: Penyaluran BLT Dana Desa Tahap I Tahun 2026 di Balai Desa"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:bg-slate-100"
                            />
                            <p
                                v-if="errors.judul"
                                class="text-xs font-medium text-red-600"
                            >
                                {{ errors.judul }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Kategori Kegiatan <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.kategori"
                                :disabled="!isOperator"
                                data-testid="select-kategori-kegiatan"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:bg-slate-100"
                            >
                                <option
                                    v-for="opt in opsi_kategori"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Tanggal Pelaksanaan <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.tanggal_kegiatan"
                                type="date"
                                required
                                :disabled="!isOperator"
                                data-testid="input-tanggal-kegiatan"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:bg-slate-100"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Lokasi Pelaksanaan / Dusun <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.lokasi_dusun"
                                type="text"
                                required
                                :disabled="!isOperator"
                                data-testid="input-lokasi-dusun"
                                placeholder="Contoh: Balai Desa Tamarenja / Dusun I"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:bg-slate-100"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Status Publikasi Target
                            </label>
                            <select
                                v-model="form.status_publikasi"
                                :disabled="!isOperator"
                                data-testid="select-status-publikasi"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 disabled:bg-slate-100"
                            >
                                <option
                                    v-for="st in opsi_status_publikasi"
                                    :key="st.value"
                                    :value="st.value"
                                >
                                    {{ st.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Uraian / Deskripsi Jalannya Kegiatan <span class="text-red-600">*</span>
                            </label>
                            <textarea
                                v-model="form.uraian"
                                rows="5"
                                required
                                :disabled="!isOperator"
                                data-testid="textarea-uraian-kegiatan"
                                placeholder="Ceritakan waktu, peserta yang hadir, dan hasil pelaksanaan kegiatan secara jelas..."
                                class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm text-slate-900 disabled:bg-slate-100"
                            />
                        </div>
                    </div>
                </section>

                <!-- Bagian 2: Galeri 1–5 Foto & Susun Urutan Sampul -->
                <section
                    class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                2. Galeri Dokumentasi Lapangan (1 s/d 5 Foto)
                            </h2>
                            <p class="text-xs text-slate-500">
                                Terunggah:
                                <strong data-testid="photo-counter" class="tabular-nums">
                                    {{ galleryItems.length }} / {{ MAX_PHOTOS }}
                                </strong>
                                foto (Sisa kuota: {{ remainingQuota }} foto). Wajib minimal 1 foto jika diterbitkan (Published).
                            </p>
                        </div>

                        <label
                            v-if="isOperator && remainingQuota > 0"
                            class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md bg-blue-50 px-3 text-xs font-semibold text-blue-700 transition-colors hover:bg-blue-100"
                        >
                            <ImagePlus class="h-4 w-4" />
                            <span>+ Tambah Foto ({{ remainingQuota }} tersisa)</span>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                multiple
                                data-testid="input-upload-foto-kegiatan"
                                class="sr-only"
                                @change="handleFileSelect"
                            />
                        </label>
                    </div>

                    <p
                        v-if="photoLimitWarning"
                        data-testid="photo-limit-warning"
                        class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800"
                    >
                        {{ photoLimitWarning }}
                    </p>

                    <p
                        v-if="publishedPhotoError || errors.foto_baru"
                        data-testid="published-photo-error"
                        class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700"
                    >
                        {{ publishedPhotoError || errors.foto_baru }}
                    </p>

                    <!-- Pratinjau Sampul Utama (Urutan #1) -->
                    <div
                        v-if="sampulUtamaItem"
                        data-testid="preview-sampul-utama"
                        class="flex items-center gap-4 rounded-lg border border-blue-200 bg-blue-50/50 p-3.5"
                    >
                        <img
                            v-if="sampulUtamaItem.previewUrl"
                            :src="sampulUtamaItem.previewUrl"
                            alt="Sampul Utama"
                            class="h-16 w-24 shrink-0 rounded-md border border-blue-200 object-cover"
                        />
                        <div class="min-w-0">
                            <span
                                data-testid="badge-sampul-utama"
                                class="inline-flex items-center gap-1 rounded-full bg-blue-700 px-2.5 py-0.5 text-[11px] font-semibold text-white"
                            >
                                <Star class="h-3 w-3" />
                                <span>Sampul Utama Kartu Berita (Urutan #1)</span>
                            </span>
                            <p class="mt-1 truncate text-xs font-medium text-slate-800">
                                {{ sampulUtamaItem.name }}
                            </p>
                        </div>
                    </div>

                    <!-- Daftar & Penyusunan Urutan 1-5 Foto -->
                    <ul
                        v-if="galleryItems.length > 0"
                        data-testid="gallery-photo-list"
                        class="space-y-2"
                    >
                        <li
                            v-for="(item, idx) in galleryItems"
                            :key="`${item.type}-${item.id || item.name}-${idx}`"
                            :data-testid="`gallery-item-${idx}`"
                            class="flex items-center justify-between gap-3 rounded-lg border border-slate-200 bg-slate-50/60 p-2.5"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span
                                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-slate-200 text-xs font-bold tabular-nums text-slate-800"
                                >
                                    {{ idx + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-900">
                                        {{ item.name }}
                                    </p>
                                    <p class="text-[11px] text-slate-500">
                                        {{
                                            idx === 0
                                                ? 'Foto Sampul Utama'
                                                : `Foto Galeri Urutan #${idx + 1}`
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="isOperator"
                                class="flex shrink-0 items-center gap-1"
                            >
                                <button
                                    type="button"
                                    :disabled="idx === 0"
                                    :data-testid="`btn-move-up-${idx}`"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 disabled:opacity-40"
                                    @click="movePhotoUp(idx)"
                                >
                                    <ArrowUp class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    :disabled="idx === galleryItems.length - 1"
                                    :data-testid="`btn-move-down-${idx}`"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded border border-slate-200 bg-white text-slate-600 hover:bg-slate-100 disabled:opacity-40"
                                    @click="movePhotoDown(idx)"
                                >
                                    <ArrowDown class="h-3.5 w-3.5" />
                                </button>
                                <button
                                    type="button"
                                    :data-testid="`btn-remove-photo-${idx}`"
                                    class="inline-flex h-7 w-7 items-center justify-center rounded border border-red-200 bg-white text-red-600 hover:bg-red-50"
                                    @click="removePhoto(idx)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </li>
                    </ul>

                    <BaseCallout
                        v-else
                        variant="info"
                        title="Belum Ada Foto Dokumentasi"
                    >
                        Anda tetap dapat menyimpan kegiatan ini sebagai <strong>Draft</strong> tanpa foto, namun wajib mengunggah minimal <strong>1 foto</strong> apabila ingin langsung menerbitkannya ke Halaman Publik.
                    </BaseCallout>

                    <!-- Footer Tombol Simpan Draft & Terbitkan Langsung (Khusus Operator) -->
                    <div
                        class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4"
                    >
                        <Link
                            href="/panel/sosial/kegiatan"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Kembali
                        </Link>

                        <template v-if="isOperator">
                            <BaseButton
                                variant="outline"
                                type="button"
                                :loading="isSubmitting && form.status_publikasi === 'draft'"
                                data-testid="btn-simpan-draft"
                                @click="submitWithStatus('draft')"
                            >
                                <FileEdit class="mr-1.5 h-4 w-4" />
                                <span>Simpan sebagai Draft</span>
                            </BaseButton>

                            <BaseButton
                                variant="primary"
                                type="button"
                                :loading="isSubmitting && form.status_publikasi === 'published'"
                                data-testid="btn-terbitkan-langsung"
                                @click="submitWithStatus('published')"
                            >
                                <CheckCircle2 class="mr-1.5 h-4 w-4" />
                                <span>Terbitkan Langsung (Published)</span>
                            </BaseButton>
                        </template>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>
