<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    FileCheck2,
    Printer,
    ShieldAlert,
    UserPlus,
} from 'lucide-vue-next';
import AppLayout from '@/Layouts/AppLayout.vue';
import ConditionalBreadcrumb from '@/Components/ConditionalBreadcrumb.vue';
import PageHeader from '@/Components/PageHeader.vue';
import NikSearchAutocomplete from '@/Components/NikSearchAutocomplete.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    opsi_jenis_surat: {
        type: Array,
        default: () => [
            {
                value: 'sku',
                label: 'Surat Keterangan Usaha (SKU)',
                default_kode: '500',
                allowed_kode: ['500'],
            },
            {
                value: 'domisili',
                label: 'Surat Keterangan Domisili',
                default_kode: '470',
                allowed_kode: ['470'],
            },
            {
                value: 'sktm',
                label: 'Surat Keterangan Tidak Mampu (SKTM)',
                default_kode: '400',
                allowed_kode: ['400', '460'],
            },
            {
                value: 'pengantar_skck',
                label: 'Surat Pengantar Catatan Kepolisian (SKCK)',
                default_kode: '300',
                allowed_kode: ['300'],
            },
            {
                value: 'kematian',
                label: 'Surat Keterangan Kematian',
                default_kode: '472.12',
                allowed_kode: ['472.12'],
            },
            {
                value: 'kelahiran',
                label: 'Surat Keterangan Kelahiran',
                default_kode: '472.11',
                allowed_kode: ['472.11'],
            },
            {
                value: 'belum_menikah',
                label: 'Surat Keterangan Belum Menikah',
                default_kode: '474.2',
                allowed_kode: ['474.2'],
            },
            {
                value: 'janda_duda',
                label: 'Surat Keterangan Janda / Duda',
                default_kode: '474.2',
                allowed_kode: ['474.2'],
            },
            {
                value: 'kehilangan',
                label: 'Surat Pengantar Kehilangan Barang',
                default_kode: '300',
                allowed_kode: ['300'],
            },
            {
                value: 'keterangan_umum',
                label: 'Surat Keterangan Umum / Lain-lain',
                default_kode: '140',
                allowed_kode: ['140', '000'],
            },
        ],
    },
    pejabat_penandatangan: {
        type: Array,
        default: () => [
            {
                value: 'kepala_desa',
                label: 'Kepala Desa',
                nama: 'Kepala Desa',
                jabatan: 'Kepala Desa',
            },
            {
                value: 'sekdes_an',
                label: 'a.n. Kepala Desa - Sekretaris Desa',
                nama: 'Sekretaris Desa',
                jabatan: 'a.n. Kepala Desa - Sekretaris Desa',
            },
        ],
    },
    tanggal_hari_ini: {
        type: String,
        default: () => new Date().toISOString().slice(0, 10),
    },
    residents: {
        type: Array,
        default: null,
    },
});

const page = usePage();
const errors = computed(() => page.props?.errors || {});

const breadcrumbs = [
    {
        label: 'Persuratan & Buku Agenda',
        href: '/panel/persuratan/surat-keluar',
    },
    {
        label: 'Buat Surat di Loket',
    },
];

function createDefaultDataKhusus() {
    return {
        // 1. SKU
        nama_usaha: '',
        jenis_usaha: '',
        bidang_usaha: '',
        lama_usaha: '',
        alamat_usaha: '',
        // 2. Domisili
        sejak_tahun: '',
        status_tempat_tinggal: 'Milik Sendiri',
        // 3. SKTM
        penghasilan_rata_rata: 0,
        jumlah_tanggungan: 1,
        nama_tanggungan: '',
        // 4. Pengantar SKCK
        catatan_kelakuan:
            'Berkelakuan baik dalam kehidupan bermasyarakat dan tidak pernah terlibat perkara pidana.',
        tujuan_skck: '',
        // 5. Kematian
        hari_meninggal: '',
        tanggal_meninggal: props.tanggal_hari_ini,
        jam_meninggal: '',
        tempat_meninggal: '',
        penyebab_meninggal: '',
        lokasi_makam: 'TPU Desa Tamarenja',
        nama_pelapor: '',
        nik_pelapor: '',
        hubungan_pelapor: '',
        // 6. Kelahiran
        nama_bayi: '',
        jenis_kelamin_bayi: 'L',
        hari_lahir_bayi: '',
        tanggal_lahir_bayi: props.tanggal_hari_ini,
        jam_lahir_bayi: '',
        tempat_lahir_bayi: '',
        anak_ke: 1,
        nama_ayah: '',
        nik_ayah: '',
        nama_ibu: '',
        nik_ibu: '',
        // 7. Belum Menikah
        pernyataan_belum_menikah:
            'Belum pernah menikah secara adat, agama, maupun negara hingga surat keterangan ini diterbitkan.',
        // 8. Janda / Duda
        status_cerai: 'cerai_mati',
        nama_mantan_pasangan: '',
        sejak_tanggal_tahun: '',
        // 9. Kehilangan
        barang_hilang: '',
        waktu_kehilangan: '',
        lokasi_kehilangan: '',
        // 10. Keterangan Umum
        judul_keterangan: '',
        isi_keterangan: '',
    };
}

const form = reactive({
    jenis_surat: 'sku',
    kode_klasifikasi: '500',
    penduduk_id: null,
    tanggal_surat: props.tanggal_hari_ini,
    opsi_penandatangan: 'kepala_desa',
    keperluan: '',
    no_wa_pemohon: '',
    data_khusus: createDefaultDataKhusus(),
});

const selectedResident = ref(null);
const isSubmitting = ref(false);
const isLoadingBansos = ref(false);
const bansosKkHistory = ref(null);

const currentJenisMeta = computed(() => {
    return (
        props.opsi_jenis_surat.find((item) => item.value === form.jenis_surat) ||
        props.opsi_jenis_surat[0]
    );
});

const allowedKodeOptions = computed(() => {
    const allowed = currentJenisMeta.value?.allowed_kode || [
        currentJenisMeta.value?.default_kode || '140',
    ];
    return allowed;
});

const nikSearchParams = computed(() => {
    if (form.jenis_surat === 'kematian') {
        return { include_meninggal: 1 };
    }
    return {};
});

const nikSearchLabel = computed(() => {
    if (form.jenis_surat === 'kelahiran') {
        return 'Cari NIK / Nama Orang Tua Pemohon (Ibu atau Ayah)';
    }
    if (form.jenis_surat === 'kematian') {
        return 'Cari NIK / Nama Warga (Subjek Almarhum/Almarhumah)';
    }
    return 'Cari NIK / Nama Warga Pemohon';
});

async function fetchBansosKkHistory(pendudukId) {
    if (!pendudukId) {
        bansosKkHistory.value = null;
        return;
    }

    isLoadingBansos.value = true;
    try {
        const res = await axios.get(
            `/panel/persuratan/cek-bansos-kk/${pendudukId}`,
        );
        bansosKkHistory.value = res.data;
    } catch {
        bansosKkHistory.value = null;
    } finally {
        isLoadingBansos.value = false;
    }
}

watch(
    () => form.jenis_surat,
    (newJenis) => {
        const meta = props.opsi_jenis_surat.find((i) => i.value === newJenis);
        if (meta) {
            form.kode_klasifikasi = meta.default_kode;
        }

        if (newJenis === 'sktm' && form.penduduk_id) {
            fetchBansosKkHistory(form.penduduk_id);
        }
    },
);

function handleSelectResident(resident) {
    selectedResident.value = resident;
    form.penduduk_id = resident?.id || null;

    if (resident && form.jenis_surat === 'kelahiran') {
        if (resident.jenis_kelamin === 'P' && !form.data_khusus.nama_ibu) {
            form.data_khusus.nama_ibu = resident.nama_lengkap || '';
            form.data_khusus.nik_ibu = resident.nik || '';
        } else if (
            resident.jenis_kelamin === 'L' &&
            !form.data_khusus.nama_ayah
        ) {
            form.data_khusus.nama_ayah = resident.nama_lengkap || '';
            form.data_khusus.nik_ayah = resident.nik || '';
        }
    }

    if (resident?.id && form.jenis_surat === 'sktm') {
        fetchBansosKkHistory(resident.id);
    } else if (!resident) {
        bansosKkHistory.value = null;
    }
}

function submitSuratLoket() {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;

    router.post(
        '/panel/persuratan/surat-keluar',
        {
            jenis_surat: form.jenis_surat,
            kode_klasifikasi: form.kode_klasifikasi,
            penduduk_id: form.penduduk_id,
            tanggal_surat: form.tanggal_surat,
            opsi_penandatangan: form.opsi_penandatangan,
            keperluan: form.keperluan,
            no_wa_pemohon: form.no_wa_pemohon || null,
            data_khusus: { ...form.data_khusus },
        },
        {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Buat Surat di Loket — Layanan Kilat 10 Template" />

    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <ConditionalBreadcrumb :items="breadcrumbs" />

            <PageHeader
                title="Layanan Kilat Loket Persuratan Desa"
                subtitle="Terbitkan surat resmi ber-Kop Desa dan QR Code verifikasi dalam hitungan detik hanya dengan mencari NIK warga."
            />

            <form
                data-testid="form-buat-surat-loket"
                class="space-y-6"
                @submit.prevent="submitSuratLoket"
            >
                <!-- Bagian 1: Jenis Surat, Klasifikasi & Subjek Warga -->
                <section
                    class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
                >
                    <div class="flex items-center gap-2.5 border-b border-slate-100 pb-3">
                        <FileCheck2 class="h-5 w-5 text-blue-700" />
                        <h2 class="text-base font-semibold text-slate-900">
                            1. Jenis Surat &amp; Identitas Warga Pemohon
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Pilih Jenis Surat (10 Template Baku) <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.jenis_surat"
                                data-testid="select-jenis-surat"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-medium text-slate-900 focus:border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-700/20"
                            >
                                <option
                                    v-for="opt in opsi_jenis_surat"
                                    :key="opt.value"
                                    :value="opt.value"
                                >
                                    {{ opt.label }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Kode Klasifikasi Surat <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-if="allowedKodeOptions.length > 1"
                                v-model="form.kode_klasifikasi"
                                data-testid="select-kode-klasifikasi"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold tabular-nums text-slate-900"
                            >
                                <option
                                    v-for="kode in allowedKodeOptions"
                                    :key="kode"
                                    :value="kode"
                                >
                                    Kode {{ kode }}
                                </option>
                            </select>
                            <input
                                v-else
                                :value="form.kode_klasifikasi"
                                type="text"
                                readonly
                                data-testid="input-kode-klasifikasi-readonly"
                                class="h-10 w-full cursor-not-allowed rounded-md border border-slate-200 bg-slate-100 px-3 text-sm font-semibold tabular-nums text-slate-700"
                            />
                        </div>
                    </div>

                    <!-- Autocomplete NIK Warga -->
                    <NikSearchAutocomplete
                        v-model="form.penduduk_id"
                        :label="nikSearchLabel"
                        :items="residents"
                        :search-params="nikSearchParams"
                        :error="errors.penduduk_id"
                        required
                        @select="handleSelectResident"
                    />

                    <!-- Panel Otomatis Cek Riwayat Bansos 1 KK saat memilih SKTM -->
                    <div
                        v-if="form.jenis_surat === 'sktm'"
                        data-testid="panel-bansos-kk-sktm"
                        class="rounded-lg border border-blue-200 bg-blue-50/70 p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-semibold text-blue-950">
                                Rujukan Verifikasi SKTM — Riwayat Bansos &amp; Ekonomi 1 KK
                            </h3>
                            <StatusBadge
                                v-if="
                                    bansosKkHistory?.status_kesejahteraan ||
                                    selectedResident?.status_kesejahteraan
                                "
                                :status="
                                    bansosKkHistory?.status_kesejahteraan ||
                                    selectedResident?.status_kesejahteraan
                                "
                                :label="
                                    bansosKkHistory?.status_kesejahteraan_label
                                "
                            />
                        </div>

                        <p
                            v-if="!form.penduduk_id"
                            class="mt-1.5 text-xs text-blue-800"
                        >
                            Pilih NIK warga terlebih dahulu untuk menampilkan ringkasan otomatis riwayat penerimaan Bansos seluruh anggota Kartu Keluarga (KK).
                        </p>

                        <p
                            v-else-if="isLoadingBansos"
                            class="mt-1.5 text-xs text-blue-800"
                        >
                            Memeriksa riwayat penerimaan Bansos anggota Kartu Keluarga...
                        </p>

                        <div
                            v-else-if="bansosKkHistory"
                            class="mt-2 space-y-2 text-xs text-slate-700"
                        >
                            <p>
                                Nomor KK:
                                <strong class="tabular-nums">{{
                                    bansosKkHistory.nomor_kk || '—'
                                }}</strong>
                                &bull; Status Ekonomi:
                                <strong>{{
                                    bansosKkHistory.status_kesejahteraan_label
                                }}</strong>
                                &bull; Total Bantuan Aktif di KK:
                                <strong>{{
                                    bansosKkHistory.total_bantuan_aktif
                                }} program</strong>
                            </p>

                            <div
                                v-if="
                                    Array.isArray(bansosKkHistory.riwayat_bansos) &&
                                    bansosKkHistory.riwayat_bansos.length > 0
                                "
                                class="rounded-md border border-blue-200 bg-white p-2.5"
                            >
                                <p class="font-semibold text-slate-800">
                                    Daftar Bantuan Terdaftar di KK Ini:
                                </p>
                                <ul class="mt-1 list-disc space-y-0.5 pl-4">
                                    <li
                                        v-for="b in bansosKkHistory.riwayat_bansos"
                                        :key="b.id"
                                    >
                                        <span class="font-medium">{{
                                            b.nama_penerima
                                        }}</span>
                                        — {{ b.jenis_bantuan_label }} (TA
                                        {{ b.tahun_anggaran }} Tahap
                                        {{ b.tahap_penyaluran }}:
                                        <strong>{{ b.status_penerima_label }}</strong>)
                                    </li>
                                </ul>
                            </div>
                            <p v-else class="text-emerald-800 font-medium">
                                Belum ada anggota KK ini yang tercatat sebagai penerima bantuan sosial pada basis data desa.
                            </p>
                        </div>
                    </div>

                    <!-- Callout Peringatan Otomatis Surat Kematian -->
                    <BaseCallout
                        v-if="form.jenis_surat === 'kematian'"
                        variant="warning"
                        data-testid="callout-warning-kematian"
                        title="Trigger Otomatis Kependudukan & Bansos"
                    >
                        Saat Surat Keterangan Kematian ini diterbitkan, sistem secara otomatis mengubah status kependudukan warga menjadi <strong>Meninggal Dunia</strong> serta menonaktifkan seluruh kepesertaan Bansos aktif warga terkait.
                    </BaseCallout>

                    <!-- Callout Pintasan Tambah Anggota KK pasca-Surat Kelahiran -->
                    <BaseCallout
                        v-if="form.jenis_surat === 'kelahiran'"
                        variant="info"
                        data-testid="callout-pintasan-kelahiran-kk"
                        title="Aturan Subjek Surat Kelahiran & Pintasan Update KK"
                    >
                        <div class="space-y-2">
                            <p>
                                Surat Keterangan Kelahiran diikat ke NIK Ibu/Ayah pemohon tanpa menginput bayi yang belum ber-NIK ke tabel penduduk. Setelah keluarga mengurus Akta Kelahiran &amp; memperoleh NIK 16 digit dari Disdukcapil, tambahkan bayi ke Kartu Keluarga melalui pintasan berikut:
                            </p>
                            <Link
                                href="/panel/kependudukan/penduduk/tambah"
                                data-testid="link-pintasan-tambah-anggota-kk"
                                class="inline-flex items-center gap-1.5 rounded-md border border-blue-300 bg-white px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50"
                            >
                                <UserPlus class="h-3.5 w-3.5" />
                                <span>+ Tambah Anggota Baru ke KK (Setelah Punya NIK 16 Digit)</span>
                            </Link>
                        </div>
                    </BaseCallout>
                </section>

                <!-- Bagian 2: Field Dinamis Spesifik 10 Jenis Surat -->
                <section
                    data-testid="section-data-khusus-dinamis"
                    class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
                >
                    <h2 class="border-b border-slate-100 pb-3 text-base font-semibold text-slate-900">
                        2. Data Khusus — {{ currentJenisMeta?.label }}
                    </h2>

                    <!-- 1. Template SKU -->
                    <div
                        v-if="form.jenis_surat === 'sku'"
                        data-testid="fields-template-sku"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Usaha <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.nama_usaha"
                                type="text"
                                data-testid="input-sku-nama-usaha"
                                placeholder="Contoh: Warung Sembako Berkah Tamarenja"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Jenis Usaha <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.jenis_usaha"
                                type="text"
                                data-testid="input-sku-jenis-usaha"
                                placeholder="Contoh: Perdagangan Eceran / Pertanian"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Bidang Usaha <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.bidang_usaha"
                                type="text"
                                data-testid="input-sku-bidang-usaha"
                                placeholder="Contoh: Kebutuhan Pokok & Hasil Bumi"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Lama Usaha Berjalan <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.lama_usaha"
                                type="text"
                                data-testid="input-sku-lama-usaha"
                                placeholder="Contoh: 4 Tahun (Sejak 2022)"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Alamat Lengkap Lokasi Usaha <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.alamat_usaha"
                                type="text"
                                data-testid="input-sku-alamat-usaha"
                                placeholder="Contoh: Dusun I RT 001/RW 001 Desa Tamarenja"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 2. Template Domisili -->
                    <div
                        v-else-if="form.jenis_surat === 'domisili'"
                        data-testid="fields-template-domisili"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Menetap / Berdomisili Sejak Tahun <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.sejak_tahun"
                                type="text"
                                data-testid="input-domisili-sejak-tahun"
                                placeholder="Contoh: 2015 / Sejak Lahir"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Status Tempat Tinggal <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.status_tempat_tinggal"
                                type="text"
                                data-testid="input-domisili-status-tempat-tinggal"
                                placeholder="Contoh: Milik Sendiri / Menumpang Keluarga"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 3. Template SKTM -->
                    <div
                        v-else-if="form.jenis_surat === 'sktm'"
                        data-testid="fields-template-sktm"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Penghasilan Rata-Rata per Bulan (Rp) <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model.number="form.data_khusus.penghasilan_rata_rata"
                                type="number"
                                min="0"
                                data-testid="input-sktm-penghasilan"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Jumlah Tanggungan Keluarga (Jiwa) <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model.number="form.data_khusus.jumlah_tanggungan"
                                type="number"
                                min="0"
                                data-testid="input-sktm-jumlah-tanggungan"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Anak / Anggota Keluarga yang Ditanggung (Opsional)
                            </label>
                            <input
                                v-model="form.data_khusus.nama_tanggungan"
                                type="text"
                                data-testid="input-sktm-nama-tanggungan"
                                placeholder="Contoh: Rina Santoso (Siswa SMAN 1 Sindue)"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 4. Template Pengantar SKCK -->
                    <div
                        v-else-if="form.jenis_surat === 'pengantar_skck'"
                        data-testid="fields-template-pengantar_skck"
                        class="grid grid-cols-1 gap-4"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Catatan Kelakuan di Lingkungan Desa <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.catatan_kelakuan"
                                type="text"
                                data-testid="input-skck-catatan-kelakuan"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Tujuan Pengurusan SKCK <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.tujuan_skck"
                                type="text"
                                data-testid="input-skck-tujuan"
                                placeholder="Contoh: Melamar Pekerjaan / Pendaftaran CPNS"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 5. Template Surat Kematian -->
                    <div
                        v-else-if="form.jenis_surat === 'kematian'"
                        data-testid="fields-template-kematian"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Hari Meninggal <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.hari_meninggal"
                                type="text"
                                data-testid="input-kematian-hari"
                                placeholder="Contoh: Jumat"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Tanggal Meninggal <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.tanggal_meninggal"
                                type="date"
                                data-testid="input-kematian-tanggal"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Pukul / Jam (Opsional)
                            </label>
                            <input
                                v-model="form.data_khusus.jam_meninggal"
                                type="text"
                                data-testid="input-kematian-jam"
                                placeholder="Contoh: 14:30 WITA"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Tempat Meninggal <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.tempat_meninggal"
                                type="text"
                                data-testid="input-kematian-tempat"
                                placeholder="Contoh: RSUD Kabelota / Rumah Duka"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Penyebab Meninggal <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.penyebab_meninggal"
                                type="text"
                                data-testid="input-kematian-penyebab"
                                placeholder="Contoh: Sakit / Usia Lanjut"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Lokasi Pemakaman <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.lokasi_makam"
                                type="text"
                                data-testid="input-kematian-lokasi-makam"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Lengkap Pelapor <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.nama_pelapor"
                                type="text"
                                data-testid="input-kematian-nama-pelapor"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                NIK Pelapor (16 Digit, Opsional)
                            </label>
                            <input
                                v-model="form.data_khusus.nik_pelapor"
                                type="text"
                                maxlength="16"
                                data-testid="input-kematian-nik-pelapor"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Hubungan Pelapor <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.hubungan_pelapor"
                                type="text"
                                data-testid="input-kematian-hubungan-pelapor"
                                placeholder="Contoh: Anak Kandung / Istri"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 6. Template Surat Kelahiran -->
                    <div
                        v-else-if="form.jenis_surat === 'kelahiran'"
                        data-testid="fields-template-kelahiran"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                    >
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Lengkap Bayi <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.nama_bayi"
                                type="text"
                                data-testid="input-kelahiran-nama-bayi"
                                placeholder="Contoh: Muhammad Alfatih"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Jenis Kelamin Bayi <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.data_khusus.jenis_kelamin_bayi"
                                data-testid="select-kelahiran-jk-bayi"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            >
                                <option value="L">Laki-laki (L)</option>
                                <option value="P">Perempuan (P)</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Hari Kelahiran <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.hari_lahir_bayi"
                                type="text"
                                data-testid="input-kelahiran-hari"
                                placeholder="Contoh: Senin"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Tanggal Lahir Bayi <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.tanggal_lahir_bayi"
                                type="date"
                                data-testid="input-kelahiran-tanggal"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Pukul / Jam Lahir (Opsional)
                            </label>
                            <input
                                v-model="form.data_khusus.jam_lahir_bayi"
                                type="text"
                                data-testid="input-kelahiran-jam"
                                placeholder="Contoh: 08:15 WITA"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Tempat Kelahiran <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.tempat_lahir_bayi"
                                type="text"
                                data-testid="input-kelahiran-tempat"
                                placeholder="Contoh: Puskesmas Pembantu Tamarenja"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Kelahiran Anak Ke- <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model.number="form.data_khusus.anak_ke"
                                type="number"
                                min="1"
                                data-testid="input-kelahiran-anak-ke"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Lengkap Ibu Kandung <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.nama_ibu"
                                type="text"
                                data-testid="input-kelahiran-nama-ibu"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                NIK Ibu (16 Digit, Opsional)
                            </label>
                            <input
                                v-model="form.data_khusus.nik_ibu"
                                type="text"
                                maxlength="16"
                                data-testid="input-kelahiran-nik-ibu"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Lengkap Ayah Kandung <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.nama_ayah"
                                type="text"
                                data-testid="input-kelahiran-nama-ayah"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                NIK Ayah (16 Digit, Opsional)
                            </label>
                            <input
                                v-model="form.data_khusus.nik_ayah"
                                type="text"
                                maxlength="16"
                                data-testid="input-kelahiran-nik-ayah"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 7. Template Belum Menikah -->
                    <div
                        v-else-if="form.jenis_surat === 'belum_menikah'"
                        data-testid="fields-template-belum_menikah"
                        class="space-y-1.5"
                    >
                        <label class="block text-sm font-medium text-slate-700">
                            Redaksi Pernyataan Belum Menikah <span class="text-red-600">*</span>
                        </label>
                        <input
                            v-model="form.data_khusus.pernyataan_belum_menikah"
                            type="text"
                            data-testid="input-belum-menikah-pernyataan"
                            class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                        />
                    </div>

                    <!-- 8. Template Janda / Duda -->
                    <div
                        v-else-if="form.jenis_surat === 'janda_duda'"
                        data-testid="fields-template-janda_duda"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Penyebab / Status Cerai <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.data_khusus.status_cerai"
                                data-testid="select-janda-duda-status-cerai"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            >
                                <option value="cerai_mati">Cerai Mati (Wafat)</option>
                                <option value="cerai_hidup">Cerai Hidup</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Nama Mantan / Almarhum Pasangan <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.nama_mantan_pasangan"
                                type="text"
                                data-testid="input-janda-duda-mantan-pasangan"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Sejak Tanggal / Tahun <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.sejak_tanggal_tahun"
                                type="text"
                                data-testid="input-janda-duda-sejak"
                                placeholder="Contoh: 12 Mei 2023"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 9. Template Kehilangan -->
                    <div
                        v-else-if="form.jenis_surat === 'kehilangan'"
                        data-testid="fields-template-kehilangan"
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                    >
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Rincian Barang / Dokumen yang Hilang <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.barang_hilang"
                                type="text"
                                data-testid="input-kehilangan-barang"
                                placeholder="Contoh: 1 (Satu) buah Buku Tabungan BRI dan KTP Asli"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Perkiraan Waktu Kehilangan <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.waktu_kehilangan"
                                type="text"
                                data-testid="input-kehilangan-waktu"
                                placeholder="Contoh: Selasa, 10 Februari 2026 sekitar pukul 10.00 WITA"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Perkiraan Lokasi Kehilangan <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.data_khusus.lokasi_kehilangan"
                                type="text"
                                data-testid="input-kehilangan-lokasi"
                                placeholder="Contoh: Perjalanan antara Pasar Sindue menuju Desa Tamarenja"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>

                    <!-- 10. Template Surat Keterangan Umum -->
                    <div
                        v-else-if="form.jenis_surat === 'keterangan_umum'"
                        data-testid="fields-template-keterangan_umum"
                        class="space-y-4"
                    >
                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Judul Keterangan Khusus (Opsional — Kosongkan untuk "SURAT KETERANGAN")
                            </label>
                            <input
                                v-model="form.data_khusus.judul_keterangan"
                                type="text"
                                data-testid="input-umum-judul-keterangan"
                                placeholder="Contoh: SURAT KETERANGAN BEDA NAMA / PENGHASILAN"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Uraian / Isi Keterangan Resmi <span class="text-red-600">*</span>
                            </label>
                            <textarea
                                v-model="form.data_khusus.isi_keterangan"
                                rows="4"
                                data-testid="textarea-umum-isi-keterangan"
                                placeholder="Tuliskan uraian keterangan resmi yang menyatakan kondisi atau fakta administratif warga..."
                                class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm text-slate-900"
                            />
                        </div>
                    </div>
                </section>

                <!-- Bagian 3: Keperluan, Pejabat Penandatangan & Kontak WA -->
                <section
                    class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-xs"
                >
                    <h2 class="border-b border-slate-100 pb-3 text-base font-semibold text-slate-900">
                        3. Keperluan &amp; Pejabat Penandatangan
                    </h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Keperluan Pembuatan Surat <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.keperluan"
                                type="text"
                                required
                                data-testid="input-keperluan-surat"
                                placeholder="Contoh: Persyaratan pengajuan KUR Bank BRI / Administrasi Beasiswa Sekolah"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                            <p
                                v-if="errors.keperluan"
                                class="text-xs font-medium text-red-600"
                            >
                                {{ errors.keperluan }}
                            </p>
                        </div>

                        <div class="space-y-1.5">
                            <label class="block text-sm font-medium text-slate-700">
                                Pejabat Penandatangan <span class="text-red-600">*</span>
                            </label>
                            <select
                                v-model="form.opsi_penandatangan"
                                data-testid="select-opsi-penandatangan"
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
                                Tanggal Surat <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="form.tanggal_surat"
                                type="date"
                                required
                                data-testid="input-tanggal-surat"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900"
                            />
                        </div>

                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700">
                                Nomor WhatsApp Pemohon (Opsional — untuk fitur 1-Klik Kabari Warga)
                            </label>
                            <input
                                v-model="form.no_wa_pemohon"
                                type="text"
                                data-testid="input-no-wa-pemohon"
                                placeholder="Contoh: 081234567890"
                                class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900"
                            />
                        </div>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4"
                    >
                        <Link
                            href="/panel/persuratan/surat-keluar"
                            class="inline-flex h-10 items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >
                            Batal
                        </Link>

                        <BaseButton
                            variant="primary"
                            type="submit"
                            :loading="isSubmitting"
                            data-testid="btn-submit-terbitkan-surat"
                        >
                            <Printer class="mr-1.5 h-4 w-4" />
                            <span>Terbitkan &amp; Cetak PDF F4</span>
                        </BaseButton>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>
