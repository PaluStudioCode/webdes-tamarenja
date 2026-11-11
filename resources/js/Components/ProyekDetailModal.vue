<script setup>
import { computed } from 'vue';
import {
    Camera,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    Hammer,
    Layers,
    MapPin,
    Pencil,
    User,
    Wallet,
} from 'lucide-vue-next';
import BaseModal from '@/Components/BaseModal.vue';
import BaseButton from '@/Components/BaseButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import LeafletMapPicker from '@/Components/LeafletMapPicker.vue';
import { formatPercentage, formatRupiah } from '@/Utils/formatters';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    proyek: {
        type: Object,
        default: null,
    },
    isOperator: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'edit']);

const hasCoordinates = computed(() => {
    return (
        props.proyek?.latitude !== null &&
        props.proyek?.latitude !== undefined &&
        props.proyek?.longitude !== null &&
        props.proyek?.longitude !== undefined
    );
});

const fotoTahapan = computed(() => [
    {
        key: 'foto_0_url',
        label: 'Titik Awal (0%)',
        desc: 'Kondisi lokasi sebelum pengerjaan',
        hasFile: Boolean(props.proyek?.foto_0_url),
        url: props.proyek?.foto_0_url,
    },
    {
        key: 'foto_50_url',
        label: 'Titik Tengah (50%)',
        desc: 'Pengerjaan lapangan berjalan',
        hasFile: Boolean(props.proyek?.foto_50_url),
        url: props.proyek?.foto_50_url,
    },
    {
        key: 'foto_100_url',
        label: 'Titik Akhir (100%)',
        desc: 'Hasil akhir proyek tuntas',
        hasFile: Boolean(props.proyek?.foto_100_url),
        url: props.proyek?.foto_100_url,
    },
]);
</script>

<template>
    <BaseModal
        :show="show"
        :title="proyek?.nama_kegiatan || 'Detail Proyek Pembangunan Fisik'"
        max-width="4xl"
        data-testid="modal-detail-proyek"
        @close="emit('close')"
    >
        <div v-if="proyek" class="space-y-6">
            <!-- Header Ringkas Status & Lokasi -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-4">
                <div class="flex items-center gap-2">
                    <StatusBadge
                        :status="proyek.status_proyek"
                        :label="proyek.status_proyek_label"
                    />
                    <span class="inline-flex items-center gap-1 text-xs font-medium text-slate-600">
                        <MapPin class="h-3.5 w-3.5 text-slate-400" />
                        {{ proyek.lokasi_dusun }}
                    </span>
                    <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                        TA {{ proyek.tahun_anggaran }}
                    </span>
                </div>

                <div class="text-right">
                    <p class="text-xs text-slate-500">Pagu Anggaran</p>
                    <p class="text-base font-bold text-slate-900 tabular-nums">
                        {{ formatRupiah(proyek.pagu_anggaran) }}
                    </p>
                </div>
            </div>

            <!-- Grid Ringkasan Teknis & Anggaran -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4 text-xs">
                <div>
                    <span class="text-slate-500">Sumber Dana</span>
                    <p class="mt-0.5 font-semibold text-slate-800">
                        {{ proyek.sumber_dana_label || proyek.sumber_dana }}
                    </p>
                </div>

                <div>
                    <span class="text-slate-500">Realisasi Dana</span>
                    <p class="mt-0.5 font-semibold text-emerald-700 tabular-nums">
                        {{ formatRupiah(proyek.realisasi_anggaran) }}
                        <span class="text-[11px] font-normal text-slate-500">
                            ({{ formatPercentage(proyek.persentase_dana || 0) }})
                        </span>
                    </p>
                </div>

                <div>
                    <span class="text-slate-500">Volume / Spesifikasi</span>
                    <p class="mt-0.5 font-semibold text-slate-800">
                        {{ proyek.volume_fisik || '—' }}
                    </p>
                </div>

                <div>
                    <span class="text-slate-500">Pelaksana &amp; Waktu</span>
                    <p class="mt-0.5 font-semibold text-slate-800">
                        {{ proyek.pelaksana_kegiatan || 'TPK Desa' }}
                        <span v-if="proyek.waktu_pelaksanaan" class="font-normal text-slate-500">
                            ({{ proyek.waktu_pelaksanaan }})
                        </span>
                    </p>
                </div>
            </div>

            <!-- Catatan Kendala Lapangan (Jika Ada) -->
            <div
                v-if="proyek.catatan_kendala"
                class="rounded-md border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"
            >
                <p class="font-semibold">Catatan Kendala Lapangan:</p>
                <p class="mt-1">{{ proyek.catatan_kendala }}</p>
            </div>

            <!-- Galeri 3 Titik Foto Lapangan -->
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        <Camera class="h-4 w-4 text-blue-700" />
                        <span>Dokumentasi Tahapan Fisik (0% &ndash; 50% &ndash; 100%)</span>
                    </h3>
                    <span class="text-xs font-semibold tabular-nums text-slate-600">
                        Capaian Fisik: {{ proyek.progres_fisik }}%
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div
                        v-for="cp in fotoTahapan"
                        :key="cp.key"
                        class="flex flex-col justify-between rounded-lg border border-slate-200 bg-white p-3 shadow-xs"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800">{{ cp.label }}</span>
                                <span
                                    :class="[
                                        'rounded px-1.5 py-0.5 text-[10px] font-semibold',
                                        cp.hasFile
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-slate-100 text-slate-400',
                                    ]"
                                >
                                    {{ cp.hasFile ? 'Tersedia' : 'Kosong' }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500">{{ cp.desc }}</p>
                        </div>

                        <div class="mt-3">
                            <div
                                v-if="cp.url"
                                class="overflow-hidden rounded-md border border-slate-200 bg-slate-100"
                            >
                                <img
                                    :src="cp.url"
                                    :alt="cp.label"
                                    class="h-44 w-full object-cover transition-transform duration-200 hover:scale-105"
                                />
                            </div>
                            <div
                                v-else
                                class="flex h-44 flex-col items-center justify-center rounded-md border border-dashed border-slate-200 bg-slate-50 p-4 text-center text-xs text-slate-400"
                            >
                                <Camera class="mb-1.5 h-6 w-6 text-slate-300" />
                                <span>Belum ada foto {{ cp.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Peta Geotag Lokasi Fisik -->
            <div class="space-y-3">
                <div v-if="hasCoordinates">
                    <LeafletMapPicker
                        :latitude="proyek.latitude"
                        :longitude="proyek.longitude"
                        :disabled="true"
                    />
                </div>
                <div
                    v-else
                    class="space-y-2"
                >
                    <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                        <MapPin class="h-4 w-4 text-blue-700" :stroke-width="2" />
                        <span>Titik Koordinat Lokasi Proyek (Geotagging)</span>
                    </div>
                    <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-xs text-slate-500">
                        <MapPin class="mx-auto mb-1.5 h-5 w-5 text-slate-300" />
                        Titik koordinat GPS belum ditandai untuk kegiatan pembangunan ini.
                    </div>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex w-full items-center justify-between">
                <div>
                    <BaseButton
                        v-if="isOperator && proyek"
                        variant="ghost"
                        size="sm"
                        data-testid="btn-edit-from-detail"
                        @click="emit('edit', proyek)"
                    >
                        <Pencil class="mr-1.5 h-3.5 w-3.5" />
                        <span>Edit Proyek Ini</span>
                    </BaseButton>
                </div>

                <BaseButton
                    variant="primary"
                    data-testid="btn-close-detail-modal"
                    @click="emit('close')"
                >
                    Tutup
                </BaseButton>
            </div>
        </template>
    </BaseModal>
</template>
