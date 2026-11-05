<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    FileCheck2,
    HandHeart,
    Info,
    ShieldCheck,
} from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    active_tab: {
        type: String,
        default: 'pengajuan_surat',
    },
    jenis_surat_options: {
        type: Array,
        default: () => [
            { value: 'sku', label: 'Surat Keterangan Usaha (SKU)' },
            { value: 'domisili', label: 'Surat Keterangan Domisili' },
            { value: 'sktm', label: 'Surat Keterangan Tidak Mampu (SKTM)' },
            { value: 'pengantar_skck', label: 'Surat Pengantar SKCK' },
            { value: 'kematian', label: 'Surat Keterangan Kematian' },
            { value: 'kelahiran', label: 'Surat Keterangan Kelahiran' },
            { value: 'belum_menikah', label: 'Surat Keterangan Belum Menikah' },
            { value: 'janda_duda', label: 'Surat Keterangan Janda / Duda' },
            { value: 'kehilangan', label: 'Surat Pengantar Kehilangan' },
            { value: 'keterangan_umum', label: 'Surat Keterangan Umum' },
        ],
    },
    profil: {
        type: Object,
        default: () => ({
            nama_desa: 'Tamarenja',
            jam_pelayanan: 'Senin – Jumat, 08.00 – 15.00 WITA',
            alamat_kantor: 'Kantor Desa Tamarenja',
            no_wa_pelayanan: null,
        }),
    },
    konfirmasi_pengajuan: {
        type: Object,
        default: null,
    },
    hasil_bansos: {
        type: Object,
        default: null,
    },
});

const currentTab = ref(props.active_tab || 'pengajuan_surat');
const showConfirmationCard = ref(Boolean(props.konfirmasi_pengajuan));

watch(
    () => props.active_tab,
    (newTab) => {
        if (newTab) {
            currentTab.value = newTab;
        }
    },
);

watch(
    () => props.konfirmasi_pengajuan,
    (val) => {
        showConfirmationCard.value = Boolean(val);
    },
);

const tabs = [
    {
        key: 'pengajuan_surat',
        label: 'Pengajuan Surat Online',
        icon: FileCheck2,
    },
    {
        key: 'cek_bansos',
        label: 'Cek Status Bansos Mandiri',
        icon: HandHeart,
    },
];

function createDefaultDataKhusus(jenis) {
    switch (jenis) {
        case 'sku':
            return {
                nama_usaha: '',
                jenis_usaha: '',
                bidang_usaha: '',
                lama_usaha: '',
                alamat_usaha: '',
            };
        case 'domisili':
            return {
                sejak_tahun: '',
                status_tempat_tinggal: 'Milik Sendiri',
            };
        case 'sktm':
            return {
                penghasilan_rata_rata: 0,
                jumlah_tanggungan: 1,
                nama_tanggungan: '',
            };
        case 'pengantar_skck':
            return {
                catatan_kelakuan: 'Baik dan tidak pernah terlibat perkara pidana',
                tujuan_skck: '',
            };
        case 'kematian':
            return {
                hari_meninggal: '',
                tanggal_meninggal: '',
                jam_meninggal: '',
                tempat_meninggal: '',
                penyebab_meninggal: '',
                lokasi_makam: '',
                nama_pelapor: '',
                nik_pelapor: '',
                hubungan_pelapor: '',
            };
        case 'kelahiran':
            return {
                nama_bayi: '',
                jenis_kelamin_bayi: 'L',
                hari_lahir_bayi: '',
                tanggal_lahir_bayi: '',
                jam_lahir_bayi: '',
                tempat_lahir_bayi: '',
                anak_ke: 1,
                nama_ayah: '',
                nik_ayah: '',
                nama_ibu: '',
                nik_ibu: '',
            };
        case 'belum_menikah':
            return {
                pernyataan_belum_menikah:
                    'Belum pernah menikah secara adat maupun negara',
            };
        case 'janda_duda':
            return {
                status_cerai: 'cerai_mati',
                nama_mantan_pasangan: '',
                sejak_tanggal_tahun: '',
            };
        case 'kehilangan':
            return {
                barang_hilang: '',
                waktu_kehilangan: '',
                lokasi_kehilangan: '',
            };
        case 'keterangan_umum':
        default:
            return {
                judul_keterangan: '',
                isi_keterangan: '',
            };
    }
}

const suratForm = useForm({
    jenis_surat: 'sku',
    nik: '',
    tanggal_lahir: '',
    no_wa_pemohon: '',
    keperluan: '',
    data_khusus: createDefaultDataKhusus('sku'),
});

function handleJenisSuratChange() {
    suratForm.data_khusus = createDefaultDataKhusus(suratForm.jenis_surat);
}

// Label kontekstual dinamis sesuai jenis surat (Kelahiran vs Kematian vs Surat Umum)
const nikLabel = computed(() => {
    if (suratForm.jenis_surat === 'kelahiran') {
        return 'NIK Ibu / Ayah Kandung (Pelapor)';
    }
    if (suratForm.jenis_surat === 'kematian') {
        return 'NIK Warga yang Meninggal Dunia';
    }
    return 'NIK Pemohon';
});

const tanggalLahirLabel = computed(() => {
    if (suratForm.jenis_surat === 'kelahiran') {
        return 'Tanggal Lahir Ibu / Ayah Kandung';
    }
    if (suratForm.jenis_surat === 'kematian') {
        return 'Tanggal Lahir Warga yang Meninggal Dunia';
    }
    return 'Tanggal Lahir Pemohon';
});

const identitasHint = computed(() => {
    if (suratForm.jenis_surat === 'kelahiran') {
        return 'Pada Surat Kelahiran, gunakan 16 digit NIK dan Tanggal Lahir Ibu atau Ayah kandung yang tercatat Aktif pada Kartu Keluarga Desa.';
    }
    if (suratForm.jenis_surat === 'kematian') {
        return 'Pada Surat Kematian, masukkan NIK dan Tanggal Lahir warga yang meninggal dunia, sedangkan identitas pelapor diisi pada bagian Rincian Kematian di bawah.';
    }
    return 'Masukkan 16 digit NIK dan Tanggal Lahir pemohon sesuai KTP/KK warga aktif Desa.';
});

function submitPengajuanSurat() {
    suratForm.post('/layanan-mandiri/ajukan-surat', {
        preserveScroll: true,
        onSuccess: () => {
            showConfirmationCard.value = true;
            suratForm.reset('nik', 'tanggal_lahir', 'no_wa_pemohon', 'keperluan');
            suratForm.data_khusus = createDefaultDataKhusus(
                suratForm.jenis_surat,
            );
        },
    });
}

function resetConfirmationAndNewRequest() {
    showConfirmationCard.value = false;
}

const bansosForm = useForm({
    nik: '',
    tanggal_lahir: '',
});

function submitCekBansos() {
    bansosForm.post('/layanan-mandiri/cek-bansos', {
        preserveState: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <PublicLayout>
        <Head :title="`Layanan Mandiri Warga — Desa ${profil.nama_desa}`" />

        <div class="mx-auto max-w-4xl space-y-6">
            <div>
                <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">
                    Layanan Mandiri Warga Desa {{ profil.nama_desa }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Ajukan permohonan surat administrasi dari rumah atau periksa
                    status kepesertaan bantuan sosial menggunakan verifikasi NIK
                    dan Tanggal Lahir.
                </p>
            </div>

            <BaseCallout
                variant="info"
                title="Perlindungan Data Pribadi & Keamanan Verifikasi"
            >
                Identitas Anda diverifikasi langsung di server menggunakan
                kombinasi <strong>16 digit NIK + Tanggal Lahir</strong> tanpa
                menampilkan data pribadi secara terbuka.
            </BaseCallout>

            <ContextualTabs v-model="currentTab" :tabs="tabs" />

            <!-- TAB 1: PENGAJUAN SURAT ONLINE -->
            <div
                v-if="currentTab === 'pengajuan_surat'"
                data-testid="tab-panel-pengajuan-surat"
                class="space-y-6"
            >
                <!-- Kartu Konfirmasi Berhasil Pasca-Submit -->
                <div
                    v-if="showConfirmationCard && konfirmasi_pengajuan"
                    data-testid="konfirmasi-pengajuan-card"
                    class="rounded-lg border border-emerald-200 bg-emerald-50/70 p-6"
                >
                    <div class="flex items-start gap-3.5">
                        <CheckCircle2
                            class="mt-0.5 h-6 w-6 shrink-0 text-emerald-700"
                        />
                        <div class="flex-1 space-y-4">
                            <div>
                                <h2
                                    class="text-base font-semibold text-emerald-950"
                                >
                                    Permohonan Surat Berhasil Dikirim ke Antrean
                                    Loket Desa
                                </h2>
                                <p class="mt-1 text-sm text-emerald-800">
                                    Operator Desa akan memverifikasi permohonan
                                    Anda. Setelah surat dicetak dan
                                    ditandatangani, Anda akan menerima pesan
                                    pemberitahuan melalui WhatsApp resmi kantor
                                    desa untuk mengambil surat asli.
                                </p>
                            </div>

                            <dl
                                class="grid grid-cols-1 gap-3 rounded-lg border border-emerald-200 bg-white p-4 text-sm sm:grid-cols-2"
                            >
                                <div>
                                    <dt class="text-xs text-slate-500">
                                        Jenis Surat yang Diajukan
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-semibold text-slate-900"
                                    >
                                        {{
                                            konfirmasi_pengajuan.jenis_surat_label
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">
                                        Status Permohonan
                                    </dt>
                                    <dd class="mt-0.5">
                                        <StatusBadge
                                            status="menunggu_verifikasi"
                                        />
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">
                                        Subjek / Pemohon (Tersamarkan)
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-medium text-slate-800"
                                    >
                                        {{
                                            konfirmasi_pengajuan.nama_pemohon_tersamarkan
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">
                                        Nomor WhatsApp Konfirmasi
                                    </dt>
                                    <dd
                                        class="mt-0.5 font-medium tabular-nums text-slate-800"
                                    >
                                        {{ konfirmasi_pengajuan.no_wa_pemohon }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-slate-500">
                                        Keperluan Pengajuan
                                    </dt>
                                    <dd class="mt-0.5 text-slate-800">
                                        {{ konfirmasi_pengajuan.keperluan }}
                                    </dd>
                                </div>
                            </dl>

                            <div>
                                <BaseButton
                                    type="button"
                                    variant="secondary"
                                    size="lg"
                                    class="min-h-[44px]"
                                    data-testid="btn-ajukan-surat-baru"
                                    @click="resetConfirmationAndNewRequest"
                                >
                                    Ajukan Permohonan Surat Lainnya
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulir Pengajuan Surat Online -->
                <form
                    v-else
                    data-testid="form-pengajuan-surat"
                    class="space-y-6 rounded-lg border border-slate-200 bg-white p-6"
                    @submit.prevent="submitPengajuanSurat"
                >
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-semibold text-slate-900">
                            Formulir Pengajuan Surat Administrasi Desa
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Jam pelayanan loket pengambilan surat asli:
                            {{ profil.jam_pelayanan }}
                        </p>
                    </div>

                    <!-- Pilih Jenis Surat -->
                    <div>
                        <label
                            for="jenis_surat"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Pilih Jenis Surat Layanan
                            <span class="text-red-600">*</span>
                        </label>
                        <select
                            id="jenis_surat"
                            v-model="suratForm.jenis_surat"
                            data-testid="select-jenis-surat"
                            class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3.5 text-sm font-medium text-slate-900 focus:border-blue-700 focus:outline-none"
                            @change="handleJenisSuratChange"
                        >
                            <option
                                v-for="opt in jenis_surat_options"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p
                            v-if="suratForm.errors.jenis_surat"
                            data-testid="error-jenis-surat"
                            class="mt-1.5 text-xs font-medium text-red-600"
                        >
                            {{ suratForm.errors.jenis_surat }}
                        </p>
                    </div>

                    <!-- Panduan Kontekstual NIK & Tanggal Lahir -->
                    <div
                        class="flex items-start gap-2.5 rounded-md border border-blue-100 bg-blue-50/70 p-3.5 text-xs leading-relaxed text-blue-900"
                    >
                        <Info class="mt-0.5 h-4 w-4 shrink-0 text-blue-700" />
                        <span data-testid="contextual-identity-hint">{{
                            identitasHint
                        }}</span>
                    </div>

                    <!-- Baris NIK & Tanggal Lahir Kontekstual -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="nik_surat"
                                data-testid="label-nik-pemohon"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                {{ nikLabel }}
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="nik_surat"
                                v-model="suratForm.nik"
                                type="text"
                                inputmode="numeric"
                                maxlength="16"
                                placeholder="720318xxxxxxxxxx"
                                data-testid="input-nik-surat"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 px-3.5 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                16 digit angka sesuai KTP/KK
                            </p>
                            <p
                                v-if="suratForm.errors.nik"
                                data-testid="error-nik-surat"
                                class="mt-1 text-xs font-medium text-red-600"
                            >
                                {{ suratForm.errors.nik }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="tanggal_lahir_surat"
                                data-testid="label-tanggal-lahir-pemohon"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                {{ tanggalLahirLabel }}
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="tanggal_lahir_surat"
                                v-model="suratForm.tanggal_lahir"
                                type="date"
                                data-testid="input-tanggal-lahir-surat"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 px-3.5 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none"
                            />
                            <p
                                v-if="suratForm.errors.tanggal_lahir"
                                data-testid="error-tanggal-lahir-surat"
                                class="mt-1 text-xs font-medium text-red-600"
                            >
                                {{ suratForm.errors.tanggal_lahir }}
                            </p>
                        </div>
                    </div>

                    <!-- Nomor WhatsApp & Keperluan -->
                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="no_wa_pemohon"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Nomor WhatsApp Aktif
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="no_wa_pemohon"
                                v-model="suratForm.no_wa_pemohon"
                                type="tel"
                                inputmode="tel"
                                placeholder="081234567890"
                                data-testid="input-no-wa-surat"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 px-3.5 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none"
                            />
                            <p class="mt-1 text-xs text-slate-500">
                                Digunakan untuk mengabari saat surat selesai
                                dicetak
                            </p>
                            <p
                                v-if="suratForm.errors.no_wa_pemohon"
                                data-testid="error-no-wa-surat"
                                class="mt-1 text-xs font-medium text-red-600"
                            >
                                {{ suratForm.errors.no_wa_pemohon }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="keperluan_surat"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Keperluan Pengajuan Surat
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="keperluan_surat"
                                v-model="suratForm.keperluan"
                                type="text"
                                placeholder="Contoh: Persyaratan kredit usaha mikro BRI"
                                data-testid="input-keperluan-surat"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 px-3.5 text-sm text-slate-900 focus:border-blue-700 focus:outline-none"
                            />
                            <p
                                v-if="suratForm.errors.keperluan"
                                data-testid="error-keperluan-surat"
                                class="mt-1 text-xs font-medium text-red-600"
                            >
                                {{ suratForm.errors.keperluan }}
                            </p>
                        </div>
                    </div>

                    <!-- Rincian Keterangan Khusus Sesuai Jenis Surat (10 Template) -->
                    <div
                        class="space-y-4 rounded-lg border border-slate-200 bg-slate-50/70 p-4 sm:p-5"
                    >
                        <h3 class="text-sm font-semibold text-slate-900">
                            Rincian Keterangan Khusus Surat
                        </h3>

                        <!-- 1. SKU -->
                        <div
                            v-if="suratForm.jenis_surat === 'sku'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Usaha <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.nama_usaha"
                                    type="text"
                                    data-testid="input-sku-nama-usaha"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Jenis Usaha <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.jenis_usaha"
                                    type="text"
                                    data-testid="input-sku-jenis-usaha"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Bidang Usaha <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.bidang_usaha"
                                    type="text"
                                    data-testid="input-sku-bidang-usaha"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Lama Usaha <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.lama_usaha"
                                    type="text"
                                    placeholder="3 Tahun"
                                    data-testid="input-sku-lama-usaha"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Alamat Lokasi Usaha
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.alamat_usaha"
                                    type="text"
                                    data-testid="input-sku-alamat-usaha"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 2. Domisili -->
                        <div
                            v-else-if="suratForm.jenis_surat === 'domisili'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Menetap Sejak Tahun
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.sejak_tahun"
                                    type="text"
                                    placeholder="2015"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Status Tempat Tinggal
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus
                                            .status_tempat_tinggal
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 3. SKTM -->
                        <div
                            v-else-if="suratForm.jenis_surat === 'sktm'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Penghasilan Rata-rata (Rp)
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model.number="
                                        suratForm.data_khusus
                                            .penghasilan_rata_rata
                                    "
                                    type="number"
                                    min="0"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Jumlah Tanggungan
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model.number="
                                        suratForm.data_khusus.jumlah_tanggungan
                                    "
                                    type="number"
                                    min="0"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Anak / Tanggungan
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.nama_tanggungan
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 4. Pengantar SKCK -->
                        <div
                            v-else-if="
                                suratForm.jenis_surat === 'pengantar_skck'
                            "
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Catatan Kelakuan
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.catatan_kelakuan
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Tujuan Pengurusan SKCK
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.tujuan_skck"
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 5. Kematian -->
                        <div
                            v-else-if="suratForm.jenis_surat === 'kematian'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Hari Meninggal
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.hari_meninggal
                                    "
                                    type="text"
                                    placeholder="Senin"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Tanggal Meninggal
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.tanggal_meninggal
                                    "
                                    type="date"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Tempat Meninggal
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.tempat_meninggal
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Penyebab Meninggal
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.penyebab_meninggal
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Lokasi Pemakaman
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.lokasi_makam"
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Pelapor
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.nama_pelapor"
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Hubungan Pelapor dengan Almarhum/ah
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.hubungan_pelapor
                                    "
                                    type="text"
                                    placeholder="Anak Kandung"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 6. Kelahiran -->
                        <div
                            v-else-if="suratForm.jenis_surat === 'kelahiran'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Bayi <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.nama_bayi"
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Jenis Kelamin Bayi
                                    <span class="text-red-600">*</span>
                                </label>
                                <select
                                    v-model="
                                        suratForm.data_khusus.jenis_kelamin_bayi
                                    "
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                >
                                    <option value="L">Laki-laki (L)</option>
                                    <option value="P">Perempuan (P)</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Hari Lahir Bayi
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.hari_lahir_bayi
                                    "
                                    type="text"
                                    placeholder="Jumat"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Tanggal Lahir Bayi
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.tanggal_lahir_bayi
                                    "
                                    type="date"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Tempat Lahir Bayi
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.tempat_lahir_bayi
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Anak Ke- <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model.number="
                                        suratForm.data_khusus.anak_ke
                                    "
                                    type="number"
                                    min="1"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Ayah Kandung
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.nama_ayah"
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Ibu Kandung
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="suratForm.data_khusus.nama_ibu"
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 7. Belum Menikah -->
                        <div
                            v-else-if="
                                suratForm.jenis_surat === 'belum_menikah'
                            "
                        >
                            <label
                                class="mb-1 block text-xs font-medium text-slate-700"
                            >
                                Pernyataan Status Belum Menikah
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                v-model="
                                    suratForm.data_khusus
                                        .pernyataan_belum_menikah
                                "
                                type="text"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                            />
                        </div>

                        <!-- 8. Janda / Duda -->
                        <div
                            v-else-if="suratForm.jenis_surat === 'janda_duda'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Status Perceraian
                                    <span class="text-red-600">*</span>
                                </label>
                                <select
                                    v-model="suratForm.data_khusus.status_cerai"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                >
                                    <option value="cerai_mati">
                                        Cerai Mati
                                    </option>
                                    <option value="cerai_hidup">
                                        Cerai Hidup
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Nama Mantan Pasangan
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus
                                            .nama_mantan_pasangan
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Sejak Tanggal / Tahun
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus
                                            .sejak_tanggal_tahun
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 9. Kehilangan -->
                        <div
                            v-else-if="suratForm.jenis_surat === 'kehilangan'"
                            class="grid grid-cols-1 gap-4 sm:grid-cols-3"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Barang / Dokumen Hilang
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.barang_hilang
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Perkiraan Waktu Kehilangan
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.waktu_kehilangan
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Perkiraan Lokasi Kehilangan
                                    <span class="text-red-600">*</span>
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.lokasi_kehilangan
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                        </div>

                        <!-- 10. Keterangan Umum -->
                        <div
                            v-else
                            class="space-y-4"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Judul Keterangan (Opsional)
                                </label>
                                <input
                                    v-model="
                                        suratForm.data_khusus.judul_keterangan
                                    "
                                    type="text"
                                    class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 bg-white px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-xs font-medium text-slate-700"
                                >
                                    Uraian Isi Keterangan
                                    <span class="text-red-600">*</span>
                                </label>
                                <textarea
                                    v-model="
                                        suratForm.data_khusus.isi_keterangan
                                    "
                                    rows="3"
                                    class="w-full rounded-md border border-slate-300 bg-white p-3 text-sm"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <BaseButton
                            type="submit"
                            variant="primary"
                            size="lg"
                            class="min-h-[44px]"
                            :loading="suratForm.processing"
                            data-testid="btn-submit-pengajuan-surat"
                        >
                            <FileCheck2 class="h-4 w-4" />
                            <span>Kirim Permohonan Surat</span>
                        </BaseButton>
                    </div>
                </form>
            </div>

            <!-- TAB 2: CEK STATUS BANSOS MANDIRI -->
            <div
                v-else
                data-testid="tab-panel-cek-bansos"
                class="space-y-6"
            >
                <form
                    data-testid="form-cek-bansos"
                    class="space-y-5 rounded-lg border border-slate-200 bg-white p-6"
                    @submit.prevent="submitCekBansos"
                >
                    <div class="border-b border-slate-100 pb-4">
                        <h2 class="text-base font-semibold text-slate-900">
                            Cek Status Kepesertaan Bantuan Sosial Mandiri
                        </h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Masukkan 16 digit NIK dan Tanggal Lahir Anda untuk
                            melihat riwayat penerimaan bantuan sosial desa.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="nik_bansos"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                NIK Warga <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="nik_bansos"
                                v-model="bansosForm.nik"
                                type="text"
                                inputmode="numeric"
                                maxlength="16"
                                placeholder="720318xxxxxxxxxx"
                                data-testid="input-nik-bansos"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 px-3.5 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none"
                            />
                            <p
                                v-if="bansosForm.errors.nik"
                                data-testid="error-nik-bansos"
                                class="mt-1 text-xs font-medium text-red-600"
                            >
                                {{ bansosForm.errors.nik }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="tanggal_lahir_bansos"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Tanggal Lahir Warga
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="tanggal_lahir_bansos"
                                v-model="bansosForm.tanggal_lahir"
                                type="date"
                                data-testid="input-tanggal-lahir-bansos"
                                class="h-11 min-h-[44px] w-full rounded-md border border-slate-300 px-3.5 text-sm tabular-nums text-slate-900 focus:border-blue-700 focus:outline-none"
                            />
                            <p
                                v-if="bansosForm.errors.tanggal_lahir"
                                data-testid="error-tanggal-lahir-bansos"
                                class="mt-1 text-xs font-medium text-red-600"
                            >
                                {{ bansosForm.errors.tanggal_lahir }}
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <BaseButton
                            type="submit"
                            variant="primary"
                            size="lg"
                            class="min-h-[44px]"
                            :loading="bansosForm.processing"
                            data-testid="btn-submit-cek-bansos"
                        >
                            <ShieldCheck class="h-4 w-4" />
                            <span>Periksa Status Bansos</span>
                        </BaseButton>
                    </div>
                </form>

                <!-- Hasil Pencarian Cek Bansos Mandiri (Tersamarkan Patuh UU PDP) -->
                <div
                    v-if="hasil_bansos"
                    data-testid="hasil-cek-bansos-card"
                    class="space-y-4 rounded-lg border border-slate-200 bg-white p-6"
                >
                    <div
                        class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">
                                Hasil Pencarian Kepesertaan Warga (Tersamarkan)
                            </p>
                            <h3
                                data-testid="hasil-bansos-nama-tersamarkan"
                                class="mt-0.5 text-lg font-semibold text-slate-900"
                            >
                                {{ hasil_bansos.nama_tersamarkan }}
                            </h3>
                        </div>
                        <span
                            data-testid="hasil-bansos-dusun"
                            class="inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700"
                        >
                            Wilayah: {{ hasil_bansos.dusun }}
                        </span>
                    </div>

                    <EmptyState
                        v-if="
                            !hasil_bansos.riwayat ||
                            hasil_bansos.riwayat.length === 0
                        "
                        title="Belum tercatat sebagai penerima bantuan sosial pada sistem desa"
                        description="Apabila membutuhkan informasi lebih lanjut, silakan hubungi perangkat dusun atau loket pelayanan Kantor Desa."
                    />

                    <div v-else class="divide-y divide-slate-100">
                        <div
                            v-for="item in hasil_bansos.riwayat"
                            :key="item.id"
                            :data-testid="`bansos-history-row-${item.id}`"
                            class="flex flex-col justify-between gap-3 py-3.5 sm:flex-row sm:items-center"
                        >
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    {{ item.jenis_bantuan_label }}
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Tahun Anggaran {{ item.tahun_anggaran }} •
                                    {{ item.tahap_penyaluran }}
                                    <span v-if="item.bentuk_nominal">
                                        • {{ item.bentuk_nominal }}
                                    </span>
                                </p>
                            </div>

                            <div>
                                <StatusBadge
                                    :status="item.status_kepesertaan"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
