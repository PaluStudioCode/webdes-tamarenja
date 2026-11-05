<script setup>
import { computed, ref, useId } from 'vue';
import { FileText, UploadCloud, X } from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: [Object, Array, null],
        default: null,
    },
    label: {
        type: String,
        default: '',
    },
    accept: {
        type: String,
        default: '.pdf',
    },
    helperText: {
        type: String,
        default: 'Format PDF • Maks. 10 MB',
    },
    error: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
    multiple: {
        type: Boolean,
        default: false,
    },
    maxFiles: {
        type: Number,
        default: 5,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const autoId = useId();
const inputId = computed(() => `file-dropzone-${autoId}`);
const isDragging = ref(false);
const fileInputRef = ref(null);

const selectedFiles = computed(() => {
    if (!props.modelValue) {
        return [];
    }
    if (Array.isArray(props.modelValue)) {
        return props.modelValue;
    }
    return [props.modelValue];
});

function formatFileSize(bytes) {
    const num = Number(bytes || 0);
    if (num < 1024) {
        return `${num} B`;
    }
    if (num < 1024 * 1024) {
        return `${(num / 1024).toFixed(1)} KB`;
    }
    return `${(num / (1024 * 1024)).toFixed(2)} MB`;
}

function applyFiles(rawFileList) {
    const incoming = Array.from(rawFileList || []);
    if (incoming.length === 0) {
        return;
    }

    if (props.multiple) {
        const merged = [...selectedFiles.value, ...incoming].slice(
            0,
            props.maxFiles,
        );
        emit('update:modelValue', merged);
        emit('change', merged);
    } else {
        const single = incoming[0];
        emit('update:modelValue', single);
        emit('change', single);
    }
}

function handleFileInput(event) {
    applyFiles(event.target.files);
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function handleDrop(event) {
    isDragging.value = false;
    if (props.disabled) {
        return;
    }
    applyFiles(event.dataTransfer?.files);
}

function removeFile(index) {
    if (props.multiple) {
        const next = selectedFiles.value.filter((_, idx) => idx !== index);
        emit('update:modelValue', next);
        emit('change', next);
    } else {
        emit('update:modelValue', null);
        emit('change', null);
    }
}
</script>

<template>
    <div data-testid="file-dropzone" class="space-y-1.5">
        <label
            v-if="label"
            :for="inputId"
            class="block text-sm font-medium text-slate-700"
        >
            <span>{{ label }}</span>
            <span v-if="required" class="ml-0.5 text-red-600">*</span>
        </label>

        <label
            :for="inputId"
            :class="[
                'flex flex-col items-center justify-center rounded-lg border border-dashed px-4 py-6 text-center transition-colors',
                error
                    ? 'border-red-400 bg-red-50/30'
                    : isDragging
                      ? 'border-blue-600 bg-blue-50/50'
                      : 'border-slate-300 bg-slate-50/60 hover:border-blue-600 hover:bg-blue-50/20',
                disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer',
            ]"
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleDrop"
        >
            <UploadCloud
                class="h-6 w-6 text-slate-400"
                :stroke-width="1.75"
            />
            <p class="mt-2 text-sm font-medium text-slate-700">
                <span class="text-blue-700">Pilih berkas</span> atau seret ke
                area ini
            </p>
            <p v-if="helperText" class="mt-1 text-xs text-slate-500">
                {{ helperText }}
            </p>

            <input
                :id="inputId"
                ref="fileInputRef"
                type="file"
                :accept="accept"
                :multiple="multiple"
                :disabled="disabled"
                data-testid="file-dropzone-input"
                class="sr-only"
                @change="handleFileInput"
            />
        </label>

        <!-- Daftar Berkas Terpilih (Nama & Ukuran sebelum Dikirim) -->
        <ul
            v-if="selectedFiles.length > 0"
            data-testid="file-dropzone-list"
            class="space-y-1.5 pt-1"
        >
            <li
                v-for="(file, idx) in selectedFiles"
                :key="`${file.name}-${idx}`"
                class="flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 py-2 text-xs"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <FileText
                        class="h-4 w-4 shrink-0 text-blue-700"
                        :stroke-width="1.75"
                    />
                    <span class="truncate font-medium text-slate-800">
                        {{ file.name }}
                    </span>
                    <span class="shrink-0 tabular-nums text-slate-500">
                        ({{ formatFileSize(file.size) }})
                    </span>
                </div>

                <button
                    v-if="!disabled"
                    type="button"
                    aria-label="Hapus berkas terpilih"
                    :data-testid="`file-dropzone-remove-${idx}`"
                    class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-red-600"
                    @click="removeFile(idx)"
                >
                    <X class="h-3.5 w-3.5" :stroke-width="2" />
                </button>
            </li>
        </ul>

        <p
            v-if="error"
            data-testid="file-dropzone-error"
            class="text-xs font-medium text-red-600"
        >
            {{ error }}
        </p>
    </div>
</template>
