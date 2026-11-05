<script setup>
import { onUnmounted, reactive } from 'vue';
import { Camera, Trash2 } from 'lucide-vue-next';

const CHECKPOINTS = [
    {
        key: 'foto_0',
        title: 'Titik Awal (0%)',
        threshold: 1,
        hint: 'Kondisi lokasi sebelum pekerjaan dimulai',
    },
    {
        key: 'foto_50',
        title: 'Titik Tengah (50%)',
        threshold: 50,
        hint: 'Dokumentasi pengerjaan berjalan (>= 50%)',
    },
    {
        key: 'foto_100',
        title: 'Titik Akhir (100%)',
        threshold: 100,
        hint: 'Hasil akhir proyek selesai (100%)',
    },
];

const props = defineProps({
    modelValue: {
        type: Object,
        default: () => ({
            foto_0: null,
            foto_50: null,
            foto_100: null,
        }),
    },
    existingUrls: {
        type: Object,
        default: () => ({}),
    },
    progresFisik: {
        type: Number,
        default: 0,
    },
    errors: {
        type: Object,
        default: () => ({}),
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const previewUrls = reactive({
    foto_0: '',
    foto_50: '',
    foto_100: '',
});

function createPreviewUrl(file) {
    if (!file) {
        return '';
    }
    if (typeof URL !== 'undefined' && typeof URL.createObjectURL === 'function') {
        return URL.createObjectURL(file);
    }
    return `preview:${file.name}`;
}

function revokePreview(key) {
    if (
        previewUrls[key] &&
        previewUrls[key].startsWith('blob:') &&
        typeof URL !== 'undefined' &&
        typeof URL.revokeObjectURL === 'function'
    ) {
        URL.revokeObjectURL(previewUrls[key]);
    }
    previewUrls[key] = '';
}

function handleFileChange(key, event) {
    const file = event.target.files?.[0] || null;
    revokePreview(key);

    if (file) {
        previewUrls[key] = createPreviewUrl(file);
    }

    const nextValue = {
        ...props.modelValue,
        [key]: file,
    };

    emit('update:modelValue', nextValue);
    emit('change', { checkpoint: key, file });
}

function removeNewFile(key) {
    revokePreview(key);
    const nextValue = {
        ...props.modelValue,
        [key]: null,
    };
    emit('update:modelValue', nextValue);
    emit('change', { checkpoint: key, file: null });
}

function getPreviewSource(key) {
    return previewUrls[key] || props.existingUrls?.[key] || '';
}

function isRequiredForProgress(threshold) {
    return Number(props.progresFisik || 0) >= threshold;
}

onUnmounted(() => {
    CHECKPOINTS.forEach((cp) => revokePreview(cp.key));
});
</script>

<template>
    <div data-testid="project-photo-checkpoints" class="space-y-2">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div
                v-for="cp in CHECKPOINTS"
                :key="cp.key"
                :data-testid="`checkpoint-box-${cp.key}`"
                :class="[
                    'flex flex-col rounded-lg border bg-white p-4',
                    errors[cp.key] ? 'border-red-400' : 'border-slate-200',
                ]"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-semibold text-slate-900">
                        {{ cp.title }}
                    </span>
                    <span
                        v-if="isRequiredForProgress(cp.threshold)"
                        :data-testid="`checkpoint-required-${cp.key}`"
                        class="inline-flex items-center rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700"
                    >
                        Wajib
                    </span>
                </div>

                <p class="mt-0.5 text-xs text-slate-500">
                    {{ cp.hint }}
                </p>

                <!-- Area Pratinjau atau Pilih Foto -->
                <div class="mt-3 flex-1">
                    <div
                        v-if="getPreviewSource(cp.key)"
                        class="relative overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                    >
                        <img
                            :src="getPreviewSource(cp.key)"
                            :alt="`Pratinjau ${cp.title}`"
                            class="h-36 w-full object-cover"
                        />

                        <button
                            v-if="modelValue?.[cp.key] && !disabled"
                            type="button"
                            :aria-label="`Hapus pilihan ${cp.title}`"
                            :data-testid="`checkpoint-clear-${cp.key}`"
                            class="absolute top-2 right-2 inline-flex h-7 w-7 items-center justify-center rounded-md bg-white/90 text-red-600 shadow-xs hover:bg-white"
                            @click="removeNewFile(cp.key)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <label
                        v-else
                        :class="[
                            'flex h-36 flex-col items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50/70 px-3 text-center transition-colors',
                            disabled
                                ? 'cursor-not-allowed opacity-60'
                                : 'cursor-pointer hover:border-blue-600 hover:bg-blue-50/30',
                        ]"
                    >
                        <Camera
                            class="h-6 w-6 text-slate-400"
                            :stroke-width="1.75"
                        />
                        <span class="mt-1.5 text-xs font-medium text-blue-700">
                            Pilih Foto {{ cp.title }}
                        </span>
                        <span class="mt-0.5 text-[11px] text-slate-400">
                            JPG, PNG, WebP (Maks. 3 MB)
                        </span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :disabled="disabled"
                            :data-testid="`checkpoint-input-${cp.key}`"
                            @change="handleFileChange(cp.key, $event)"
                        />
                    </label>

                    <!-- Ganti foto lama jika sudah ada pratinjau -->
                    <label
                        v-if="getPreviewSource(cp.key) && !disabled"
                        class="mt-2 inline-flex cursor-pointer items-center text-xs font-medium text-blue-700 hover:text-blue-800"
                    >
                        <span>Ganti Foto</span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :data-testid="`checkpoint-replace-${cp.key}`"
                            @change="handleFileChange(cp.key, $event)"
                        />
                    </label>
                </div>

                <p
                    v-if="errors[cp.key]"
                    :data-testid="`checkpoint-error-${cp.key}`"
                    class="mt-2 text-xs font-medium text-red-600"
                >
                    {{ errors[cp.key] }}
                </p>
            </div>
        </div>
    </div>
</template>
