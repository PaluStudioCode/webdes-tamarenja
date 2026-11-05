<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: '',
    },
    color: {
        type: String,
        default: '',
        validator: (val) =>
            ['', 'slate', 'amber', 'emerald', 'red', 'blue'].includes(val),
    },
});

const COLOR_STYLES = {
    slate: 'border-slate-200 bg-slate-100 text-slate-700',
    amber: 'border-amber-200 bg-amber-50 text-amber-700',
    emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    red: 'border-red-200 bg-red-50 text-red-700',
    blue: 'border-blue-200 bg-blue-50 text-blue-700',
};

// Pemetaan tunggal seluruh status sistem sesuai PRD 04 Seksi 1.15
const STATUS_CATALOG = {
    // Abu-abu (slate)
    belum_dimulai: { color: 'slate', label: 'Belum Dimulai' },
    draft: { color: 'slate', label: 'Draft' },
    pindah_keluar: { color: 'slate', label: 'Pindah Keluar' },
    pindah: { color: 'slate', label: 'Pindah Keluar' },
    graduasi: { color: 'slate', label: 'Graduasi' },
    murni_terkunci: {
        color: 'slate',
        label: 'APBDes Murni - Arsip Terkunci',
    },
    apbdes_murni_terkunci: {
        color: 'slate',
        label: 'APBDes Murni - Arsip Terkunci',
    },
    'apbdes murni - arsip terkunci': {
        color: 'slate',
        label: 'APBDes Murni - Arsip Terkunci',
    },
    murni: { color: 'slate', label: 'APBDes Murni' },
    non_prioritas: { color: 'slate', label: 'Non-Prioritas' },
    nonaktif: { color: 'slate', label: 'Nonaktif' },

    // Kuning / Amber (amber)
    sedang_dikerjakan: { color: 'amber', label: 'Sedang Dikerjakan' },
    menunggu_verifikasi: { color: 'amber', label: 'Menunggu Verifikasi' },
    perubahan: { color: 'amber', label: 'APBDes Perubahan' },
    apbdes_perubahan: { color: 'amber', label: 'APBDes Perubahan' },
    'apbdes perubahan': { color: 'amber', label: 'APBDes Perubahan' },
    dtsen_desil_2: { color: 'amber', label: 'DTSEN Desil 2' },
    dtsen_desil_3_4: { color: 'amber', label: 'DTSEN Desil 3-4' },

    // Hijau (emerald)
    selesai: { color: 'emerald', label: 'Selesai' },
    terbit: { color: 'emerald', label: 'Terbit / Dicetak' },
    'terbit / dicetak': { color: 'emerald', label: 'Terbit / Dicetak' },
    surat_asli: { color: 'emerald', label: 'Surat Asli' },
    'surat asli': { color: 'emerald', label: 'Surat Asli' },
    aktif: { color: 'emerald', label: 'Aktif' },
    published: { color: 'emerald', label: 'Published' },

    // Merah (red)
    ditolak: { color: 'red', label: 'Ditolak' },
    dibatalkan: { color: 'red', label: 'Dibatalkan' },
    meninggal_dunia: { color: 'red', label: 'Meninggal Dunia' },
    tidak_terdaftar: { color: 'red', label: 'Tidak Terdaftar' },
    'tidak terdaftar': { color: 'red', label: 'Tidak Terdaftar' },
    dtsen_desil_1: { color: 'red', label: 'DTSEN Desil 1' },
};

const normalizedKey = computed(() =>
    String(props.status || '')
        .trim()
        .toLowerCase(),
);

const resolvedMeta = computed(() => {
    const entry =
        STATUS_CATALOG[normalizedKey.value] ||
        STATUS_CATALOG[normalizedKey.value.replace(/\s+/g, '_')];

    return {
        color: props.color || entry?.color || 'slate',
        label: props.label || entry?.label || props.status || '-',
    };
});

const badgeClasses = computed(
    () => COLOR_STYLES[resolvedMeta.value.color] || COLOR_STYLES.slate,
);
</script>

<template>
    <span
        data-testid="status-badge"
        :data-color="resolvedMeta.color"
        :class="[
            'inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-medium whitespace-nowrap',
            badgeClasses,
        ]"
    >
        <slot>{{ resolvedMeta.label }}</slot>
    </span>
</template>
