import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import Beranda from '@/Pages/Publik/Beranda.vue';
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

    it('renders Beranda without APBDes section, renders project cards without progress bar/recap download, and caps kegiatan cards to 3', () => {
        const proyekList = [1, 2, 3, 4].map((idx) => ({
            id: idx,
            tahun_anggaran: 2026,
            nama_kegiatan: `Proyek Jalan ${idx}`,
            lokasi_dusun: idx % 2 === 0 ? 'Dusun II' : 'Dusun I',
            sumber_dana: 'dds',
            sumber_dana_label: 'Dana Desa (DDS)',
            pagu_anggaran: 120000000,
            realisasi_anggaran: 60000000,
            progres_fisik: 50,
            status_proyek: 'sedang_dikerjakan',
            status_label: 'Sedang Dikerjakan',
            foto_0_url: `/storage/proyek/${idx}-0.webp`,
            foto_50_url: `/storage/proyek/${idx}-50.webp`,
            foto_100_url: null,
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
                profil: {
                    nama_desa: 'Tamarenja',
                    daftar_dusun: ['Dusun I', 'Dusun II'],
                },
                proyek_terbaru: proyekList,
                kegiatan_terbaru: kegiatanList,
            },
        });

        expect(wrapper.find('[data-testid="beranda-sorotan-apbdes"]').exists()).toBe(false);
        expect(wrapper.find('#pembangunan').exists()).toBe(true);
        expect(wrapper.find('#kegiatan').exists()).toBe(true);
        expect(wrapper.findAll('[data-testid="beranda-proyek-card"]')).toHaveLength(4);
        expect(wrapper.find('[data-testid="publik-download-rekap-pembangunan-pdf"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Unduh PDF Rekap');
        expect(wrapper.find('[role="progressbar"]').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="beranda-kegiatan-card"]')).toHaveLength(3);
        expect(wrapper.find('[data-testid="beranda-statistik-section"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="cta-layanan-mandiri"]').exists()).toBe(true);
        expect(wrapper.find('#layanan-mandiri').exists()).toBe(true);
        expect(wrapper.find('[data-testid="card-layanan-surat"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="card-layanan-bansos"]').exists()).toBe(true);
    });

    it('renders pembangunan without dusun filter tabs and navigates 0%, 50%, 100% slider modal photos in Beranda.vue', async () => {
        const proyekList = [
            {
                id: 10,
                tahun_anggaran: 2026,
                nama_kegiatan: 'Drainase Saluran Lingkungan',
                lokasi_dusun: 'Dusun I',
                sumber_dana: 'dds',
                sumber_dana_label: 'Dana Desa (DDS)',
                pagu_anggaran: 90000000,
                status_proyek: 'sedang_dikerjakan',
                volume_fisik: '200 M',
                pelaksana_kegiatan: 'TPK Desa Tamarenja',
                foto_0_url: '/storage/proyek/10-0.webp',
                foto_50_url: '/storage/proyek/10-50.webp',
                foto_100_url: null,
            },
            {
                id: 11,
                tahun_anggaran: 2026,
                nama_kegiatan: 'Rabat Beton Jalan Usaha Tani',
                lokasi_dusun: 'Dusun II',
                sumber_dana: 'add',
                sumber_dana_label: 'Alokasi Dana Desa (ADD)',
                pagu_anggaran: 180000000,
                status_proyek: 'selesai',
                foto_0_url: '/storage/proyek/11-0.webp',
                foto_50_url: '/storage/proyek/11-50.webp',
                foto_100_url: '/storage/proyek/11-100.webp',
            },
        ];

        const wrapper = mount(Beranda, {
            props: {
                tahun_aktif: 2026,
                profil: {
                    nama_desa: 'Tamarenja',
                    daftar_dusun: ['Dusun I', 'Dusun II'],
                },
                proyek_terbaru: proyekList,
                kegiatan_terbaru: [],
            },
        });

        // Pastikan tidak ada tab filter dusun dan kartu tidak menduplikasi badge dusun & status yang ada di modal
        expect(wrapper.find('[data-testid="pembangunan-filter-dusun"]').exists()).toBe(false);
        const cards = wrapper.findAll('[data-testid="beranda-proyek-card"]');
        expect(cards).toHaveLength(2);
        expect(cards[0].findComponent({ name: 'StatusBadge' }).exists()).toBe(false);
        expect(cards[0].text()).not.toContain('Dusun I');
        expect(cards[0].text()).not.toContain('Sedang Dikerjakan');

        // Buka modal slider foto pada proyek pertama
        await wrapper.findAll('[data-testid="btn-lihat-foto-progres"]')[0].trigger('click');
        expect(wrapper.find('[data-testid="beranda-proyek-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="beranda-proyek-modal"]').findComponent({ name: 'StatusBadge' }).exists()).toBe(true);
        expect(wrapper.find('[data-testid="beranda-proyek-modal"]').text()).toContain('Dusun I');
        expect(wrapper.find('[data-testid="beranda-proyek-modal"]').text()).toContain('Sedang Dikerjakan');
        expect(wrapper.find('[data-testid="slider-stage-label"]').text()).toContain('Titik 0%');
        expect(wrapper.find('[data-testid="slider-active-image"]').attributes('src')).toBe('/storage/proyek/10-0.webp');

        // Geser ke slide berikutnya (50%)
        await wrapper.find('[data-testid="slider-next-btn"]').trigger('click');
        expect(wrapper.find('[data-testid="slider-stage-label"]').text()).toContain('Titik 50%');
        expect(wrapper.find('[data-testid="slider-active-image"]').attributes('src')).toBe('/storage/proyek/10-50.webp');

        // Geser ke slide 100% yang belum memiliki foto -> tampilkan fallback message
        await wrapper.find('[data-testid="slider-next-btn"]').trigger('click');
        expect(wrapper.find('[data-testid="slider-stage-label"]').text()).toContain('Titik 100%');
        expect(wrapper.find('[data-testid="slider-empty-photo"]').text()).toContain('Foto belum didokumentasikan untuk tahap ini');

        // Tutup modal
        await wrapper.find('[data-testid="base-modal-close"]').trigger('click');
        expect(wrapper.find('[data-testid="beranda-proyek-modal"]').exists()).toBe(false);
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
