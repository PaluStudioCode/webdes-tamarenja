<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Building2, Landmark, MapPin, UserSquare2 } from 'lucide-vue-next';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

const props = defineProps({
    profil: {
        type: Object,
        default: () => ({
            nama_desa: 'Tamarenja',
            kecamatan: 'Sindue Tobata',
            kabupaten: 'Donggala',
            provinsi: 'Sulawesi Tengah',
            kode_wilayah: '72.03.19.2003',
            daftar_dusun: [],
            alamat_kantor: '',
            jam_pelayanan: '',
            nama_kepala_desa: '',
            visi: '',
            misi: [],
            sejarah_singkat: '',
            potensi_unggulan: '',
        }),
    },
    sotk_pemerintah: {
        type: Array,
        default: () => [],
    },
    sotk_bpd: {
        type: Array,
        default: () => [],
    },
});

const activeTab = ref('identitas');

const tabs = [
    { key: 'identitas', label: 'Identitas & Visi-Misi' },
    { key: 'sejarah', label: 'Sejarah & Potensi Desa' },
    { key: 'sotk', label: 'Struktur Organisasi (SOTK & BPD)' },
];

const sotkGroups = computed(() => {
    const groups = [
        {
            key: 'pimpinan',
            title: 'Pimpinan Pemerintah Desa',
            subtitle: 'Kepala Desa',
            items: [],
        },
        {
            key: 'sekretariat',
            title: 'Sekretariat Desa',
            subtitle: 'Sekretaris Desa & Kepala Urusan (Kaur)',
            items: [],
        },
        {
            key: 'pelaksana_teknis',
            title: 'Pelaksana Teknis',
            subtitle: 'Kepala Seksi (Kasi)',
            items: [],
        },
        {
            key: 'kewilayahan_dusun',
            title: 'Pelaksana Kewilayahan',
            subtitle: 'Kepala Dusun (Kadus)',
            items: [],
        },
    ];

    const mapByKey = Object.fromEntries(groups.map((g) => [g.key, g]));
    const lainnya = {
        key: 'lainnya',
        title: 'Perangkat Pemerintah Desa Lainnya',
        subtitle: '',
        items: [],
    };

    for (const item of props.sotk_pemerintah || []) {
        const target = mapByKey[item.kelompok_sotk];
        if (target) {
            target.items.push(item);
        } else {
            lainnya.items.push(item);
        }
    }

    if (lainnya.items.length > 0) {
        groups.push(lainnya);
    }

    return groups.filter((g) => g.items.length > 0);
});
</script>

<template>
    <PublicLayout>
        <Head :title="`Profil & SOTK Desa ${profil.nama_desa}`" />

        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    Profil &amp; Struktur Organisasi Desa
                    {{ profil.nama_desa }}
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Informasi resmi wilayah, arah kebijakan pembangunan, sejarah
                    desa, serta susunan perangkat pemerintah desa dan BPD.
                </p>
            </div>

            <ContextualTabs v-model="activeTab" :tabs="tabs" />

            <!-- Tab 1: Identitas & Visi-Misi -->
            <div
                v-if="activeTab === 'identitas'"
                data-testid="tab-panel-identitas"
                class="grid grid-cols-1 gap-6 lg:grid-cols-12"
            >
                <section
                    class="rounded-lg border border-slate-200 bg-white p-6 lg:col-span-5"
                >
                    <div class="mb-4 flex items-center gap-2">
                        <Landmark class="h-5 w-5 text-blue-700" />
                        <h2 class="text-base font-semibold text-slate-900">
                            Identitas Wilayah Desa
                        </h2>
                    </div>

                    <dl class="divide-y divide-slate-100 text-sm">
                        <div class="flex justify-between py-2.5">
                            <dt class="text-slate-500">Nama Resmi Desa</dt>
                            <dd class="font-semibold text-slate-900">
                                Desa {{ profil.nama_desa }}
                            </dd>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <dt class="text-slate-500">Kecamatan</dt>
                            <dd class="font-medium text-slate-800">
                                {{ profil.kecamatan }}
                            </dd>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <dt class="text-slate-500">Kabupaten</dt>
                            <dd class="font-medium text-slate-800">
                                {{ profil.kabupaten }}
                            </dd>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <dt class="text-slate-500">Provinsi</dt>
                            <dd class="font-medium text-slate-800">
                                {{ profil.provinsi }}
                            </dd>
                        </div>
                        <div class="flex justify-between py-2.5">
                            <dt class="text-slate-500">
                                Kode Wilayah Kemendagri
                            </dt>
                            <dd
                                class="font-medium tabular-nums text-slate-800"
                            >
                                {{ profil.kode_wilayah || '-' }}
                            </dd>
                        </div>
                        <div class="py-2.5">
                            <dt class="mb-1.5 text-slate-500">
                                Daftar Wilayah Dusun Resmi
                            </dt>
                            <dd class="flex flex-wrap gap-1.5">
                                <span
                                    v-for="dusun in profil.daftar_dusun"
                                    :key="dusun"
                                    class="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-medium text-slate-700"
                                >
                                    <MapPin class="h-3 w-3 text-blue-700" />
                                    {{ dusun }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section
                    class="space-y-6 rounded-lg border border-slate-200 bg-white p-6 lg:col-span-7"
                >
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Visi Pemerintah Desa
                        </h2>
                        <p
                            class="mt-2 rounded-md border border-blue-100 bg-blue-50/60 p-4 text-sm font-medium leading-relaxed text-slate-900"
                        >
                            "{{ profil.visi || 'Belum ditetapkan.' }}"
                        </p>
                    </div>

                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            Misi Pemerintah Desa
                        </h2>
                        <ol
                            v-if="profil.misi && profil.misi.length > 0"
                            class="mt-3 list-decimal space-y-2.5 pl-5 text-sm leading-relaxed text-slate-700"
                        >
                            <li
                                v-for="(butir, index) in profil.misi"
                                :key="index"
                            >
                                {{ butir }}
                            </li>
                        </ol>
                        <p v-else class="mt-2 text-sm text-slate-500">
                            Belum ada daftar misi desa.
                        </p>
                    </div>
                </section>
            </div>

            <!-- Tab 2: Sejarah & Potensi Desa -->
            <div
                v-else-if="activeTab === 'sejarah'"
                data-testid="tab-panel-sejarah"
                class="grid grid-cols-1 gap-6 md:grid-cols-2"
            >
                <section
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="mb-3 text-base font-semibold text-slate-900">
                        Sejarah Singkat Desa {{ profil.nama_desa }}
                    </h2>
                    <p
                        class="whitespace-pre-line text-sm leading-relaxed text-slate-700"
                    >
                        {{
                            profil.sejarah_singkat ||
                            'Narasi sejarah desa belum tersedia.'
                        }}
                    </p>
                </section>

                <section
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <h2 class="mb-3 text-base font-semibold text-slate-900">
                        Potensi Ekonomi &amp; Komoditas Unggulan
                    </h2>
                    <p
                        class="whitespace-pre-line text-sm leading-relaxed text-slate-700"
                    >
                        {{
                            profil.potensi_unggulan ||
                            'Deskripsi potensi unggulan desa belum tersedia.'
                        }}
                    </p>
                </section>
            </div>

            <!-- Tab 3: Bagan Struktur Organisasi (SOTK & BPD) -->
            <div
                v-else-if="activeTab === 'sotk'"
                data-testid="tab-panel-sotk"
                class="space-y-8"
            >
                <!-- Pemerintah Desa -->
                <section
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <div class="mb-5 flex items-center gap-2">
                        <Building2 class="h-5 w-5 text-blue-700" />
                        <div>
                            <h2 class="text-base font-semibold text-slate-900">
                                Struktur Organisasi Pemerintah Desa (SOTK)
                            </h2>
                            <p class="text-xs text-slate-500">
                                Pejabat pimpinan, sekretariat, pelaksana teknis,
                                dan kepala kewilayahan dusun aktif
                            </p>
                        </div>
                    </div>

                    <div
                        v-if="sotk_pemerintah.length === 0"
                        class="py-6 text-center text-sm text-slate-500"
                    >
                        Data perangkat pemerintah desa belum ditambahkan.
                    </div>

                    <div
                        v-else
                        class="space-y-8"
                    >
                        <div
                            v-for="group in sotkGroups"
                            :key="group.key"
                            :data-testid="`sotk-group-${group.key}`"
                            class="space-y-3"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-100 pb-2"
                            >
                                <div>
                                    <h3
                                        class="text-xs font-bold uppercase tracking-wider text-blue-700"
                                    >
                                        {{ group.title }}
                                    </h3>
                                    <p
                                        v-if="group.subtitle"
                                        class="text-xs text-slate-500"
                                    >
                                        {{ group.subtitle }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600"
                                >
                                    {{ group.items.length }} Pejabat
                                </span>
                            </div>

                            <div
                                :class="[
                                    'grid gap-4',
                                    group.key === 'pimpinan'
                                        ? 'mx-auto max-w-xs grid-cols-1'
                                        : group.items.length === 3
                                          ? 'grid-cols-1 sm:grid-cols-3'
                                          : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
                                ]"
                            >
                                <div
                                    v-for="pejabat in group.items"
                                    :key="pejabat.id"
                                    data-testid="sotk-member-card"
                                    :class="[
                                        'flex flex-col items-center rounded-lg border p-4 text-center',
                                        group.key === 'pimpinan'
                                            ? 'border-blue-200 bg-blue-50/40'
                                            : 'border-slate-200 bg-slate-50/60',
                                    ]"
                                >
                                    <div
                                        class="mb-3 flex h-16 w-16 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white"
                                    >
                                        <img
                                            v-if="pejabat.foto_url"
                                            :src="pejabat.foto_url"
                                            :alt="pejabat.nama_lengkap"
                                            class="h-full w-full object-cover"
                                        />
                                        <UserSquare2
                                            v-else
                                            class="h-7 w-7 text-slate-400"
                                        />
                                    </div>

                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ pejabat.nama_lengkap }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-xs font-medium text-blue-700"
                                    >
                                        {{ pejabat.jabatan }}
                                    </p>
                                    <p
                                        v-if="pejabat.nip_niap"
                                        class="mt-1 text-[11px] tabular-nums text-slate-500"
                                    >
                                        NIP/NIAP: {{ pejabat.nip_niap }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Badan Permusyawaratan Desa (BPD) -->
                <section
                    class="rounded-lg border border-slate-200 bg-white p-6"
                >
                    <div class="mb-5">
                        <h2 class="text-base font-semibold text-slate-900">
                            Badan Permusyawaratan Desa (BPD)
                        </h2>
                        <p class="text-xs text-slate-500">
                            Mitra permusyawaratan dan pengawasan penyelenggaraan
                            pemerintahan desa
                        </p>
                    </div>

                    <div
                        v-if="sotk_bpd.length === 0"
                        class="py-6 text-center text-sm text-slate-500"
                    >
                        Data anggota BPD belum ditambahkan.
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4"
                    >
                        <div
                            v-for="anggota in sotk_bpd"
                            :key="anggota.id"
                            data-testid="bpd-member-card"
                            class="flex flex-col items-center rounded-lg border border-slate-200 bg-slate-50/60 p-4 text-center"
                        >
                            <div
                                class="mb-3 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white"
                            >
                                <img
                                    v-if="anggota.foto_url"
                                    :src="anggota.foto_url"
                                    :alt="anggota.nama_lengkap"
                                    class="h-full w-full object-cover"
                                />
                                <UserSquare2
                                    v-else
                                    class="h-6 w-6 text-slate-400"
                                />
                            </div>
                            <p class="text-sm font-semibold text-slate-900">
                                {{ anggota.nama_lengkap }}
                            </p>
                            <p class="mt-0.5 text-xs text-slate-600">
                                {{ anggota.jabatan }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </PublicLayout>
</template>
