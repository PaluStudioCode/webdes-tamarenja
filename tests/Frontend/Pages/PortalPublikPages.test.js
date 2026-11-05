import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import Beranda from '@/Pages/Publik/Beranda.vue';
import Profil from '@/Pages/Publik/Profil.vue';
import TransparansiApbdes from '@/Pages/Publik/TransparansiApbdes.vue';
import TransparansiPembangunan from '@/Pages/Publik/TransparansiPembangunan.vue';
import StatistikPenduduk from '@/Pages/Publik/StatistikPenduduk.vue';
import KegiatanIndex from '@/Pages/Publik/KegiatanIndex.vue';
import Unduhan from '@/Pages/Publik/Unduhan.vue';

vi.mock('vue-chartjs', () => ({
    Bar: {
        name: 'Bar',
        props: ['data', 'options'],
        template: '<div data-testid="chartjs-bar-stub"></div>',
    },
}));

describe('Portal Publik Pages (Sub-Fase 10.1)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        const page = usePage();
        page.url = '/';
        page.props.auth = { user: null };
        page.props.profil_desa = {
            nama_desa: 'Tamarenja',
            kecamatan: 'Sindue Tobata',
            kabupaten: 'Donggala',
            provinsi: 'Sulawesi Tengah',
        };
    });

    it('renders Beranda with maximum 3 project cards and 3 activity cards without full table/chart duplication', () => {
        const proyekList = [1, 2, 3, 4].map((idx) => ({
            id: idx,
            tahun_anggaran: 2026,
            nama_kegiatan: `Proyek Jalan ${idx}`,
            lokasi_dusun: 'Dusun I',
            pagu_anggaran: 120000000,
            realisasi_anggaran: 60000000,
            progres_fisik: 50,
            status_proyek: 'sedang_dikerjakan',
            status_label: 'Sedang Dikerjakan',
        }));

        const kegiatanList = [1, 2, 3, 4].map((idx) => ({
            id: idx,
            judul: `Kegiatan Desa ${idx}`,
            slug: `kegiatan-desa-${idx}`,
            kategori_label: 'Gotong Royong',
            tanggal_kegiatan: `2026-04-0${idx}`,
            lokasi_dusun: 'Dusun I',
            ringkasan: `Ringkasan kegiatan ${idx}`,
            foto_sampul_url: null,
        }));

        const wrapper = mount(Beranda, {
            props: {
                tahun_aktif: 2026,
                sambutan: {
                    nama_kepala_desa: 'Moh. Rizal, S.AP',
                    sambutan_kepala_desa: 'Selamat datang di website resmi Desa Tamarenja.',
                    foto_kepala_desa_url: null,
                },
                sorotan_apbdes: {
                    tahun_anggaran: 2026,
                    jenis_dokumen: 'murni',
                    total_pagu_pendapatan: 1200000000,
                    total_pagu_belanja: 1150000000,
                    total_realisasi_belanja: 750000000,
                    persentase_serapan_belanja: 65.2,
                },
                proyek_terbaru: proyekList,
                kegiatan_terbaru: kegiatanList,
            },
        });

        expect(wrapper.findAll('[data-testid="beranda-proyek-card"]')).toHaveLength(3);
        expect(wrapper.findAll('[data-testid="beranda-kegiatan-card"]')).toHaveLength(3);
        expect(wrapper.find('[data-testid="cta-layanan-mandiri"]').exists()).toBe(true);
    });

    it('switches between Identitas, Sejarah & Potensi, and SOTK tabs in Profil.vue', async () => {
        const wrapper = mount(Profil, {
            props: {
                profil: {
                    nama_desa: 'Tamarenja',
                    kecamatan: 'Sindue Tobata',
                    kabupaten: 'Donggala',
                    provinsi: 'Sulawesi Tengah',
                    kode_wilayah: '72.03.18.2004',
                    daftar_dusun: ['Dusun I', 'Dusun II'],
                    visi: 'Desa Maju dan Mandiri',
                    misi: ['Pelayanan Prima', 'Transparansi Anggaran'],
                    sejarah_singkat: 'Sejarah panjang Desa Tamarenja.',
                    potensi_unggulan: 'Perikanan dan Kelapa.',
                },
                sotk_pemerintah: [
                    {
                        id: 1,
                        nama_lengkap: 'Moh. Rizal, S.AP',
                        jabatan: 'Kepala Desa',
                        kelompok_sotk: 'pimpinan',
                        kelompok_label: 'Pimpinan Desa',
                    },
                ],
                sotk_bpd: [
                    {
                        id: 2,
                        nama_lengkap: 'Hasan Basri',
                        jabatan: 'Ketua BPD',
                        kelompok_sotk: 'bpd',
                        kelompok_label: 'Badan Permusyawaratan Desa (BPD)',
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="tab-panel-identitas"]').exists()).toBe(true);

        await wrapper.find('[data-testid="contextual-tab-sejarah"]').trigger('click');
        expect(wrapper.find('[data-testid="tab-panel-sejarah"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Sejarah panjang Desa Tamarenja.');

        await wrapper.find('[data-testid="contextual-tab-sotk"]').trigger('click');
        expect(wrapper.find('[data-testid="tab-panel-sotk"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Moh. Rizal, S.AP');
        expect(wrapper.text()).toContain('Hasan Basri');
    });

    it('supports year selector and Baliho PNG generator in TransparansiApbdes.vue', async () => {
        const wrapper = mount(TransparansiApbdes, {
            props: {
                tahun_anggaran: 2026,
                available_tahun: [2026, 2025],
                has_perubahan: true,
                dokumen_aktif: {
                    id: 1,
                    tahun_anggaran: 2026,
                    jenis_dokumen: 'perubahan',
                    jenis_label: 'APBDes Perubahan (P-APBDes)',
                    nomor_perdes: 'Perdes No. 4 Tahun 2026',
                    rincian: [],
                },
                dokumen_murni: {
                    id: 2,
                    tahun_anggaran: 2026,
                    jenis_dokumen: 'murni',
                    rincian: [],
                },
                dokumen_perubahan: {
                    id: 1,
                    tahun_anggaran: 2026,
                    jenis_dokumen: 'perubahan',
                    rincian: [],
                },
                ringkasan: {
                    total_pagu_pendapatan: 1200000000,
                    total_realisasi_pendapatan: 1100000000,
                    persentase_realisasi_pendapatan: 91.67,
                    total_pagu_belanja: 1150000000,
                    total_realisasi_belanja: 950000000,
                    sisa_anggaran_belanja: 200000000,
                    persentase_serapan_belanja: 82.61,
                    lima_bidang: [
                        { kode_pos: 'bidang_1', nama_pos: 'Bidang Penyelenggaraan Pemerintahan', pagu_anggaran: 300000000, total_realisasi: 250000000 },
                    ],
                },
            },
            global: {
                stubs: {
                    ApbdesBannerCanvas: {
                        template: '<button data-testid="btn-unduh-baliho">Unduh Baliho PNG</button>',
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="publik-select-tahun-apbdes"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="publik-select-jenis-apbdes"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="btn-unduh-baliho"]').exists()).toBe(true);

        await wrapper.find('[data-testid="publik-select-tahun-apbdes"]').setValue('2025');
        expect(router.get).toHaveBeenCalledWith(
            '/transparansi/apbdes',
            { tahun: 2025, jenis: 'perubahan' },
            expect.any(Object),
        );
    });

    it('opens photo drawer when clicking marker popup on LeafletMapView in TransparansiPembangunan.vue', async () => {
        const proyekSample = {
            id: 10,
            tahun_anggaran: 2026,
            nama_kegiatan: 'Rabat Beton Dusun II',
            lokasi_dusun: 'Dusun II',
            sumber_dana: 'dds',
            sumber_dana_label: 'Dana Desa (DDS)',
            pagu_anggaran: 180000000,
            realisasi_anggaran: 180000000,
            persentase_serapan: 100,
            progres_fisik: 100,
            status_proyek: 'selesai',
            status_label: 'Selesai',
            latitude: -0.461,
            longitude: 119.776,
            foto_0_url: '/storage/proyek/2026/0.webp',
            foto_50_url: '/storage/proyek/2026/50.webp',
            foto_100_url: '/storage/proyek/2026/100.webp',
        };

        const wrapper = mount(TransparansiPembangunan, {
            props: {
                tahun_anggaran: 2026,
                available_tahun: [2026],
                daftar_dusun: ['Dusun I', 'Dusun II'],
                filters: { dusun: '' },
                proyek: [proyekSample],
            },
            global: {
                stubs: {
                    LeafletMapView: {
                        props: ['projects'],
                        emits: ['select-project'],
                        template: `
                            <div data-testid="leaflet-map-stub">
                                <button data-testid="trigger-marker-select" @click="$emit('select-project', projects[0])">
                                    Pilih Marker
                                </button>
                            </div>
                        `,
                    },
                    SlideOverDrawer: {
                        props: ['show', 'title'],
                        template: '<div v-if="show" data-testid="drawer-proyek-open">{{ title }}<slot /></div>',
                    },
                },
            },
        });

        expect(wrapper.find('[data-testid="publik-download-rekap-pembangunan-pdf"]').attributes('href')).toContain(
            '/transparansi/pembangunan/cetak-pdf?tahun=2026',
        );

        await wrapper.find('[data-testid="trigger-marker-select"]').trigger('click');
        expect(wrapper.find('[data-testid="drawer-proyek-open"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="drawer-proyek-open"]').text()).toContain('Rabat Beton Dusun II');
    });

    it('renders 5 standard age groups and charts in StatistikPenduduk.vue', () => {
        const wrapper = mount(StatistikPenduduk, {
            props: {
                ringkasan: {
                    total_penduduk_aktif: 250,
                    total_kk_aktif: 75,
                    laki_laki_count: 128,
                    perempuan_count: 122,
                },
                kelompok_usia: [
                    { kode: '0_4', label: '0–4 thn (Balita)', jumlah: 20 },
                    { kode: '5_14', label: '5–14 thn (Anak)', jumlah: 45 },
                    { kode: '15_24', label: '15–24 thn (Pemuda)', jumlah: 50 },
                    { kode: '25_59', label: '25–59 thn (Produktif)', jumlah: 110 },
                    { kode: '60_plus', label: '60+ thn (Lansia)', jumlah: 25 },
                ],
                sebaran_dusun: [
                    { dusun: 'Dusun I', jumlah_jiwa: 130, jumlah_kk: 40 },
                    { dusun: 'Dusun II', jumlah_jiwa: 120, jumlah_kk: 35 },
                ],
                pekerjaan: [
                    { pekerjaan: 'Petani/Pekebun', jumlah: 80 },
                    { pekerjaan: 'Nelayan/Perikanan', jumlah: 40 },
                ],
            },
        });

        expect(wrapper.find('[data-testid="chartjs-bar-stub"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="age-group-0_4"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="age-group-5_14"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="age-group-15_24"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="age-group-25_59"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="age-group-60_plus"]').exists()).toBe(true);
    });

    it('filters categories on KegiatanIndex.vue and Unduhan.vue', async () => {
        const kegiatanWrapper = mount(KegiatanIndex, {
            props: {
                filters: { kategori: '' },
                kategori_options: [
                    { value: 'gotong_royong', label: 'Gotong Royong' },
                    { value: 'musdes', label: 'Musyawarah Desa (Musdes)' },
                ],
                kegiatan: {
                    data: [
                        {
                            id: 1,
                            judul: 'Kerja Bakti Dusun I',
                            slug: 'kerja-bakti-dusun-i',
                            kategori_label: 'Gotong Royong',
                            tanggal_kegiatan: '2026-04-10',
                            lokasi_dusun: 'Dusun I',
                            ringkasan: 'Gotong royong warga',
                            foto_sampul_url: null,
                        },
                    ],
                    links: [],
                },
            },
        });

        await kegiatanWrapper.find('[data-testid="publik-filter-kategori-kegiatan"]').setValue('gotong_royong');
        expect(router.get).toHaveBeenCalledWith(
            '/kegiatan',
            { kategori: 'gotong_royong' },
            expect.any(Object),
        );

        const unduhanWrapper = mount(Unduhan, {
            props: {
                filters: { kategori: '', tahun: null },
                kategori_options: [{ value: 'perdes', label: 'Peraturan Desa (Perdes)' }],
                available_tahun: [2026, 2025],
                dokumen: [
                    {
                        id: 5,
                        judul_dokumen: 'Perdes APBDes 2026',
                        kategori: 'perdes',
                        kategori_label: 'Peraturan Desa (Perdes)',
                        tahun_dokumen: 2026,
                        ukuran_file_kb: 512,
                        jumlah_unduhan: 12,
                        download_url: '/unduhan/5/file',
                    },
                ],
            },
        });

        expect(unduhanWrapper.find('[data-testid="publik-download-ppid-5"]').attributes('href')).toBe('/unduhan/5/file');
        await unduhanWrapper.find('[data-testid="publik-filter-kategori-ppid"]').setValue('perdes');
        expect(router.get).toHaveBeenCalledWith(
            '/unduhan',
            { kategori: 'perdes', tahun: undefined },
            expect.any(Object),
        );
    });
});
