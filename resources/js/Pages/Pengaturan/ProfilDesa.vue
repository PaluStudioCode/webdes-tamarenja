<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Building2, ImagePlus, Plus, Save, Trash2 } from 'lucide-vue-next';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCallout from '@/Components/BaseCallout.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    profil: {
        type: Object,
        default: () => ({}),
    },
});

const pengaturanTabs = [
    {
        key: 'profil-desa',
        label: 'Profil & Kop',
        href: '/panel/pengaturan/profil-desa',
    },
    { key: 'sotk', label: 'SOTK', href: '/panel/pengaturan/sotk' },
    { key: 'ppid', label: 'Dokumen PPID', href: '/panel/pengaturan/ppid' },
    {
        key: 'pengguna',
        label: 'Pengguna',
        href: '/panel/pengaturan/pengguna',
    },
    { key: 'backup', label: 'Backup Data', href: '/panel/pengaturan/backup' },
];

const formState = ref({
    nama_desa: props.profil?.nama_desa || 'Tamarenja',
    kode_desa_surat: props.profil?.kode_desa_surat || 'DS-TMR',
    kecamatan: props.profil?.kecamatan || 'Sindue Tobata',
    kabupaten: props.profil?.kabupaten || 'Donggala',
    provinsi: props.profil?.provinsi || 'Sulawesi Tengah',
    kode_wilayah: props.profil?.kode_wilayah || '',
    daftar_dusun:
        Array.isArray(props.profil?.daftar_dusun) &&
        props.profil.daftar_dusun.length > 0
            ? [...props.profil.daftar_dusun]
            : ['Dusun I'],
    alamat_kantor: props.profil?.alamat_kantor || '',
    jam_pelayanan: props.profil?.jam_pelayanan || '',
    email_desa: props.profil?.email_desa || '',
    no_wa_pelayanan: props.profil?.no_wa_pelayanan || '',
    nama_kepala_desa: props.profil?.nama_kepala_desa || '',
    nip_kepala_desa: props.profil?.nip_kepala_desa || '',
    sambutan_kepala_desa: props.profil?.sambutan_kepala_desa || '',
    nama_sekdes: props.profil?.nama_sekdes || '',
    nip_sekdes: props.profil?.nip_sekdes || '',
    visi: props.profil?.visi || '',
    misi:
        Array.isArray(props.profil?.misi) && props.profil.misi.length > 0
            ? [...props.profil.misi]
            : [''],
    sejarah_singkat: props.profil?.sejarah_singkat || '',
    potensi_unggulan: props.profil?.potensi_unggulan || '',
    tahun_anggaran_aktif: Number(
        props.profil?.tahun_anggaran_aktif || new Date().getFullYear(),
    ),
    logo_desa: null,
    foto_kepala_desa: null,
});

const logoPreviewUrl = ref(props.profil?.logo_url || null);
const fotoKadesPreviewUrl = ref(props.profil?.foto_kepala_desa_url || null);
const formErrors = ref({});
const isSubmitting = ref(false);

const canRemoveDusun = computed(() => formState.value.daftar_dusun.length > 1);

function addDusunRow() {
    const nextIndex = formState.value.daftar_dusun.length + 1;
    formState.value.daftar_dusun.push(`Dusun ${nextIndex}`);
}

function removeDusunRow(index) {
    if (!canRemoveDusun.value) {
        return;
    }
    formState.value.daftar_dusun.splice(index, 1);
}

function addMisiRow() {
    formState.value.misi.push('');
}

function removeMisiRow(index) {
    formState.value.misi.splice(index, 1);
}

function handleLogoChange(event) {
    const file = event.target?.files?.[0] || null;
    formState.value.logo_desa = file;
    if (file && typeof URL !== 'undefined' && URL.createObjectURL) {
        logoPreviewUrl.value = URL.createObjectURL(file);
    }
}

function handleFotoKadesChange(event) {
    const file = event.target?.files?.[0] || null;
    formState.value.foto_kepala_desa = file;
    if (file && typeof URL !== 'undefined' && URL.createObjectURL) {
        fotoKadesPreviewUrl.value = URL.createObjectURL(file);
    }
}

function submitProfil() {
    if (isSubmitting.value) {
        return;
    }

    isSubmitting.value = true;
    formErrors.value = {};

    const payload = {
        _method: 'PUT',
        nama_desa: formState.value.nama_desa,
        kode_desa_surat: formState.value.kode_desa_surat,
        kecamatan: formState.value.kecamatan,
        kabupaten: formState.value.kabupaten,
        provinsi: formState.value.provinsi,
        kode_wilayah: formState.value.kode_wilayah || '',
        daftar_dusun: formState.value.daftar_dusun
            .map((d) => String(d || '').trim())
            .filter(Boolean),
        alamat_kantor: formState.value.alamat_kantor,
        jam_pelayanan: formState.value.jam_pelayanan || '',
        email_desa: formState.value.email_desa || '',
        no_wa_pelayanan: formState.value.no_wa_pelayanan || '',
        nama_kepala_desa: formState.value.nama_kepala_desa,
        nip_kepala_desa: formState.value.nip_kepala_desa || '',
        sambutan_kepala_desa: formState.value.sambutan_kepala_desa || '',
        nama_sekdes: formState.value.nama_sekdes,
        nip_sekdes: formState.value.nip_sekdes || '',
        visi: formState.value.visi || '',
        misi: formState.value.misi
            .map((m) => String(m || '').trim())
            .filter(Boolean),
        sejarah_singkat: formState.value.sejarah_singkat || '',
        potensi_unggulan: formState.value.potensi_unggulan || '',
        tahun_anggaran_aktif: Number(formState.value.tahun_anggaran_aktif),
    };

    if (formState.value.logo_desa) {
        payload.logo_desa = formState.value.logo_desa;
    }

    if (formState.value.foto_kepala_desa) {
        payload.foto_kepala_desa = formState.value.foto_kepala_desa;
    }

    router.post('/panel/pengaturan/profil-desa', payload, {
        forceFormData: true,
        preserveScroll: true,
        onError: (errors) => {
            formErrors.value = errors || {};
        },
        onFinish: () => {
            isSubmitting.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Profil & Kop Surat Desa" />

        <PageHeader
            title="Pengaturan Sistem"
            description="Konfigurasi identitas resmi desa, daftar dusun, kop surat PDF F4, pejabat penandatangan, dan tahun anggaran aktif default."
        >
            <template #actions>
                <BaseButton
                    variant="primary"
                    :loading="isSubmitting"
                    data-testid="save-profil-btn"
                    @click="submitProfil"
                >
                    <Save class="h-4 w-4" :stroke-width="2" />
                    <span>Simpan Perubahan</span>
                </BaseButton>
            </template>
        </PageHeader>

        <ContextualTabs :tabs="pengaturanTabs" />

        <BaseCallout
            variant="info"
            title="Standar Kop Surat & Tahun Anggaran Publik"
        >
            Logo desa yang diunggah akan otomatis dikonversi ke format
            <strong>PNG</strong> untuk menjamin kompatibilitas 100% pada cetakan
            PDF kertas F4 (`DomPDF`). Perubahan
            <strong>Tahun Anggaran Aktif Default</strong> menentukan tahun yang
            ditampilkan pertama kali kepada pengunjung Halaman Publik.
        </BaseCallout>

        <form class="space-y-6" @submit.prevent="submitProfil">
            <!-- Seksi 1: Identitas Resmi, Kop Surat & Tahun Aktif -->
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-slate-900">
                    Identitas Wilayah & Kop Surat Resmi
                </h2>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            for="nama_desa"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Nama Desa <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="nama_desa"
                            v-model="formState.nama_desa"
                            type="text"
                            data-testid="input-nama-desa"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.nama_desa"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.nama_desa }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="kode_desa_surat"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Kode Desa pada Nomor Surat
                            <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="kode_desa_surat"
                            v-model="formState.kode_desa_surat"
                            type="text"
                            placeholder="DS-TMR"
                            data-testid="input-kode-desa-surat"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.kode_desa_surat"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.kode_desa_surat }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="kecamatan"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Kecamatan <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="kecamatan"
                            v-model="formState.kecamatan"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.kecamatan"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.kecamatan }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="kabupaten"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Kabupaten <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="kabupaten"
                            v-model="formState.kabupaten"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.kabupaten"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.kabupaten }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="provinsi"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Provinsi <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="provinsi"
                            v-model="formState.provinsi"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.provinsi"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.provinsi }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                for="kode_wilayah"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Kode Wilayah Kemendagri
                            </label>
                            <input
                                id="kode_wilayah"
                                v-model="formState.kode_wilayah"
                                type="text"
                                placeholder="72.03.19.2003"
                                class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label
                                for="tahun_anggaran_aktif"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Tahun Anggaran Aktif
                                <span class="text-red-600">*</span>
                            </label>
                            <input
                                id="tahun_anggaran_aktif"
                                v-model.number="formState.tahun_anggaran_aktif"
                                type="number"
                                min="2020"
                                max="2100"
                                data-testid="input-tahun-aktif"
                                class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                            <p
                                v-if="formErrors.tahun_anggaran_aktif"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formErrors.tahun_anggaran_aktif }}
                            </p>
                        </div>
                    </div>

                    <div class="sm:col-span-2">
                        <label
                            for="alamat_kantor"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Alamat Lengkap Kantor Desa
                            <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="alamat_kantor"
                            v-model="formState.alamat_kantor"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.alamat_kantor"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.alamat_kantor }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="jam_pelayanan"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Jam Pelayanan Loket
                        </label>
                        <input
                            id="jam_pelayanan"
                            v-model="formState.jam_pelayanan"
                            type="text"
                            placeholder="Senin - Jumat, 08.00 - 15.30 WITA"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="email_desa"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Email Resmi Desa
                            </label>
                            <input
                                id="email_desa"
                                v-model="formState.email_desa"
                                type="email"
                                class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                            <p
                                v-if="formErrors.email_desa"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formErrors.email_desa }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="no_wa_pelayanan"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                WhatsApp Pelayanan
                            </label>
                            <input
                                id="no_wa_pelayanan"
                                v-model="formState.no_wa_pelayanan"
                                type="text"
                                placeholder="081245001122"
                                class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                            <p
                                v-if="formErrors.no_wa_pelayanan"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ formErrors.no_wa_pelayanan }}
                            </p>
                        </div>
                    </div>

                    <!-- Unggah Logo Desa -->
                    <div class="sm:col-span-2">
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Logo Desa / Kabupaten (Kop Surat & Portal)
                        </label>
                        <div class="flex flex-wrap items-center gap-4">
                            <div
                                class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                            >
                                <img
                                    v-if="logoPreviewUrl"
                                    :src="logoPreviewUrl"
                                    alt="Logo Desa"
                                    class="h-14 w-14 object-contain"
                                />
                                <Building2
                                    v-else
                                    class="h-7 w-7 text-slate-400"
                                />
                            </div>
                            <div class="space-y-1">
                                <label
                                    class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    <ImagePlus class="h-4 w-4" />
                                    <span>Pilih Logo Baru</span>
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.webp"
                                        data-testid="input-logo-desa"
                                        class="hidden"
                                        @change="handleLogoChange"
                                    />
                                </label>
                                <p class="text-xs text-slate-500">
                                    Maks. 3 MB (JPG/PNG/WEBP — otomatis disimpan
                                    sebagai PNG).
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="formErrors.logo_desa"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.logo_desa }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Seksi 2: Daftar Dusun Resmi (Standarisasi Dropdown KK, Proyek & Statistik) -->
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Daftar Dusun Resmi Desa
                        </h2>
                        <p class="text-xs text-slate-500">
                            Digunakan sebagai pilihan standar pada Kartu
                            Keluarga, lokasi proyek, dan grafik demografi.
                        </p>
                    </div>
                    <BaseButton
                        type="button"
                        variant="secondary"
                        size="sm"
                        data-testid="add-dusun-btn"
                        @click="addDusunRow"
                    >
                        <Plus class="h-4 w-4" />
                        <span>Tambah Dusun</span>
                    </BaseButton>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div
                        v-for="(_, index) in formState.daftar_dusun"
                        :key="`dusun-${index}`"
                        class="flex items-center gap-2"
                    >
                        <input
                            v-model="formState.daftar_dusun[index]"
                            type="text"
                            :data-testid="`input-dusun-${index}`"
                            placeholder="Contoh: Dusun I"
                            class="h-10 flex-1 rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <button
                            type="button"
                            :disabled="!canRemoveDusun"
                            :data-testid="`remove-dusun-${index}`"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-40"
                            @click="removeDusunRow(index)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <p
                    v-if="formErrors.daftar_dusun"
                    class="mt-2 text-xs text-red-600"
                >
                    {{ formErrors.daftar_dusun }}
                </p>
            </div>

            <!-- Seksi 3: Pejabat Penandatangan Surat (Kepala Desa & Sekretaris Desa) -->
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-slate-900">
                    Pejabat Penandatangan Resmi (Kepala Desa & Sekretaris Desa)
                </h2>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            for="nama_kepala_desa"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Nama Lengkap Kepala Desa
                            <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="nama_kepala_desa"
                            v-model="formState.nama_kepala_desa"
                            type="text"
                            data-testid="input-nama-kades"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.nama_kepala_desa"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.nama_kepala_desa }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="nip_kepala_desa"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            NIP / NIAP Kepala Desa (Jika Ada)
                        </label>
                        <input
                            id="nip_kepala_desa"
                            v-model="formState.nip_kepala_desa"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <div>
                        <label
                            for="nama_sekdes"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Nama Lengkap Sekretaris Desa (a.n. Kades)
                            <span class="text-red-600">*</span>
                        </label>
                        <input
                            id="nama_sekdes"
                            v-model="formState.nama_sekdes"
                            type="text"
                            data-testid="input-nama-sekdes"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                        <p
                            v-if="formErrors.nama_sekdes"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.nama_sekdes }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="nip_sekdes"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            NIP / NIAP Sekretaris Desa
                        </label>
                        <input
                            id="nip_sekdes"
                            v-model="formState.nip_sekdes"
                            type="text"
                            class="h-10 w-full rounded-md border border-slate-300 px-3 text-sm tabular-nums text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <div class="sm:col-span-2">
                        <label
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Foto Resmi Kepala Desa (Halaman Profil Publik)
                        </label>
                        <div class="flex flex-wrap items-center gap-4">
                            <div
                                class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
                            >
                                <img
                                    v-if="fotoKadesPreviewUrl"
                                    :src="fotoKadesPreviewUrl"
                                    alt="Foto Kepala Desa"
                                    class="h-full w-full object-cover"
                                />
                                <span
                                    v-else
                                    class="text-xs font-semibold text-slate-400"
                                >
                                    KADES
                                </span>
                            </div>
                            <label
                                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-50"
                            >
                                <ImagePlus class="h-4 w-4" />
                                <span>Pilih Foto Kepala Desa</span>
                                <input
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    data-testid="input-foto-kades"
                                    class="hidden"
                                    @change="handleFotoKadesChange"
                                />
                            </label>
                        </div>
                        <p
                            v-if="formErrors.foto_kepala_desa"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ formErrors.foto_kepala_desa }}
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <label
                            for="sambutan_kepala_desa"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Sambutan Kepala Desa (Beranda Publik)
                        </label>
                        <textarea
                            id="sambutan_kepala_desa"
                            v-model="formState.sambutan_kepala_desa"
                            rows="3"
                            class="w-full rounded-md border border-slate-300 p-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- Seksi 4: Visi, Misi, Sejarah & Potensi Desa -->
            <div class="rounded-lg border border-slate-200 bg-white p-6">
                <h2 class="mb-4 text-base font-semibold text-slate-900">
                    Visi, Misi, Sejarah & Potensi Unggulan Desa
                </h2>

                <div class="space-y-5">
                    <div>
                        <label
                            for="visi"
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Visi Pemerintah Desa
                        </label>
                        <textarea
                            id="visi"
                            v-model="formState.visi"
                            rows="2"
                            class="w-full rounded-md border border-slate-300 p-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                        />
                    </div>

                    <div>
                        <div
                            class="mb-2 flex items-center justify-between gap-2"
                        >
                            <label
                                class="block text-sm font-medium text-slate-700"
                            >
                                Butir Misi Pemerintah Desa
                            </label>
                            <BaseButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                data-testid="add-misi-btn"
                                @click="addMisiRow"
                            >
                                <Plus class="h-4 w-4" />
                                <span>Tambah Butir Misi</span>
                            </BaseButton>
                        </div>

                        <div class="space-y-2.5">
                            <div
                                v-for="(_, index) in formState.misi"
                                :key="`misi-${index}`"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="w-6 text-right text-xs font-semibold tabular-nums text-slate-500"
                                >
                                    {{ index + 1 }}.
                                </span>
                                <input
                                    v-model="formState.misi[index]"
                                    type="text"
                                    :data-testid="`input-misi-${index}`"
                                    class="h-10 flex-1 rounded-md border border-slate-300 px-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                                />
                                <button
                                    type="button"
                                    :data-testid="`remove-misi-${index}`"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-slate-200 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    @click="removeMisiRow(index)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="sejarah_singkat"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Sejarah Singkat Desa
                            </label>
                            <textarea
                                id="sejarah_singkat"
                                v-model="formState.sejarah_singkat"
                                rows="4"
                                class="w-full rounded-md border border-slate-300 p-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>

                        <div>
                            <label
                                for="potensi_unggulan"
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Potensi Ekonomi & Unggulan Desa
                            </label>
                            <textarea
                                id="potensi_unggulan"
                                v-model="formState.potensi_unggulan"
                                rows="4"
                                class="w-full rounded-md border border-slate-300 p-3 text-sm text-slate-900 focus:border-blue-600 focus:outline-none"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
