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
            kategori_label: 'Musyawarah Desa (Musdes)',
            tanggal_kegiatan: `2026-04-0${idx}`,
            lokasi_dusun: 'Aula Kantor Desa Tamarenja',
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
                    jam_pelayanan: 'Senin - Jumat, 08.00 - 15.30 WITA',
                    alamat_kantor: 'Jl. Hi. Paggaru No. 80 Tamarenja Kode Pos 94353',
                    no_wa_pelayanan: '6281245001122',
                },
                sotk_pemerintah: [
                    {
                        id: 1,
                        nama_lengkap: 'Baswan Laramang',
                        jabatan: 'Kepala Desa',
                        kelompok_sotk: 'pimpinan',
                    },
                ],
                proyek_terbaru: proyekList,
                kegiatan_terbaru: kegiatanList,
                dokumen_ppid_terbaru: [
                    {
                        id: 1,
                        judul_dokumen: 'Perdes APBDes Tahun Anggaran 2026',
                        kategori: 'perdes_apbdes',
                        kategori_label: 'Perdes APBDes',
                        tahun_dokumen: 2026,
                        ukuran_file_kb: 420,
                        jumlah_unduhan: 3,
                        download_url: '/unduhan/1/file',
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="beranda-sorotan-apbdes"]').exists()).toBe(false);
        expect(wrapper.find('#profil').exists()).toBe(true);
        expect(wrapper.find('#potensi').exists()).toBe(true);
        expect(wrapper.find('#sotk').exists()).toBe(true);
        expect(wrapper.find('#pembangunan').exists()).toBe(true);
        expect(wrapper.find('#kegiatan').exists()).toBe(true);
        expect(wrapper.find('#ppid').exists()).toBe(true);
        expect(wrapper.find('#layanan-mandiri').exists()).toBe(true);

        // Section Potensi & Komoditas Desa dan Section Dokumen Publik PPID tampil rapi
        const potensiSection = wrapper.find('[data-testid="beranda-potensi-section"]');
        expect(potensiSection.exists()).toBe(true);
        expect(potensiSection.findAll('[data-testid="beranda-potensi-card"]')).toHaveLength(3);
        expect(potensiSection.text()).toContain('Sentra Salak Tamarenja');

        const ppidSection = wrapper.find('[data-testid="beranda-ppid-section"]');
        expect(ppidSection.exists()).toBe(true);
        expect(ppidSection.findAll('[data-testid="beranda-ppid-card"]')).toHaveLength(1);
        expect(ppidSection.text()).toContain('Perdes APBDes Tahun Anggaran 2026');
        expect(ppidSection.get('[data-testid="link-selengkapnya-ppid"]').attributes('href')).toBe('/unduhan');

        // Papan Struktur Organisasi memiliki section sendiri terpisah dari #profil
        expect(wrapper.find('#profil').find('#sotk').exists()).toBe(false);
        expect(wrapper.find('[data-testid="beranda-sotk-section"]').exists()).toBe(true);

        // Setiap section heading tidak memiliki deskripsi heading
        expect(wrapper.text()).not.toContain('Gambaran umum wilayah, visi-misi pembangunan berkelanjutan');
        expect(wrapper.text()).not.toContain('Informasi proyek pembangunan fisik serta dokumentasi foto progres');
        expect(wrapper.text()).not.toContain('Dokumentasi musyawarah desa, gotong royong, pembangunan sosial');
        expect(wrapper.text()).not.toContain('Akses pengurusan surat administrasi resmi dan pengecekan status bantuan sosial');

        // Proyek card: tidak ada badge jumlah foto (X/3 Foto) dan sumber dana tampil sebagai teks biasa tanpa badge
        const proyekCards = wrapper.findAll('[data-testid="beranda-proyek-card"]');
        expect(proyekCards).toHaveLength(4);
        expect(proyekCards[0].text()).not.toContain('/3 Foto');
        expect(proyekCards[0].text()).toContain('Dana Desa (DDS)');
        expect(proyekCards[0].find('span.bg-blue-50').exists()).toBe(false);

        expect(wrapper.find('[data-testid="publik-download-rekap-pembangunan-pdf"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Unduh PDF Rekap');
        expect(wrapper.find('[role="progressbar"]').exists()).toBe(false);

        // Kegiatan card: tidak ada badge kategori maupun informasi lokasi
        const kegiatanCards = wrapper.findAll('[data-testid="beranda-kegiatan-card"]');
        expect(kegiatanCards).toHaveLength(3);
        expect(kegiatanCards[0].text()).not.toContain('Musyawarah Desa');
        expect(kegiatanCards[0].text()).not.toContain('Aula Kantor Desa Tamarenja');

        expect(wrapper.find('[data-testid="beranda-statistik-section"]').exists()).toBe(true);
        const ctaLayanan = wrapper.find('[data-testid="cta-layanan-mandiri"]');
        expect(ctaLayanan.exists()).toBe(true);
        expect(ctaLayanan.findAll('svg')).toHaveLength(0);
        expect(wrapper.find('a[href="#profil"]').exists()).toBe(false);

        // Seksi Profil Desa tidak menampilkan heading Sejarah & Karakteristik Wilayah, Mata Pencaharian, & Jam Pelayanan
        const profilSection = wrapper.find('#profil');
        expect(profilSection.text()).not.toContain('Sejarah & Karakteristik Wilayah');
        expect(profilSection.text()).not.toContain('Membangun Kemandirian & Kesejahteraan');
        expect(profilSection.text()).not.toContain('Mata Pencaharian Utama');
        expect(profilSection.text()).not.toContain('Jam Pelayanan Kantor');

        // Layanan Mandiri card: tidak ada badge & tidak ada icon selain checklist
        const suratCard = wrapper.get('[data-testid="card-layanan-surat"]');
        const bansosCard = wrapper.get('[data-testid="card-layanan-bansos"]');
        expect(suratCard.text()).not.toContain('10 Jenis Surat');
        expect(bansosCard.text()).not.toContain('Transparan & Terlindungi');
        expect(suratCard.findAll('svg')).toHaveLength(3);
        expect(bansosCard.findAll('svg')).toHaveLength(3);

        // Component Bantuan Operasional Pelayanan dihapus
        expect(wrapper.find('#layanan-mandiri').text()).not.toContain('Bantuan Operasional Pelayanan');
        expect(wrapper.find('#layanan-mandiri').text()).not.toContain('Jl. Hi. Paggaru No. 80');

        // Indikator IDM & PODESKEL di seksi Profil Desa
        const indikatorDesa = wrapper.find('[data-testid="beranda-indikator-desa"]');
        expect(indikatorDesa.exists()).toBe(true);
        expect(indikatorDesa.text()).toContain('Desa Maju');
        expect(indikatorDesa.text()).toContain('0,7143');
        expect(indikatorDesa.text()).toContain('Desa Swasembada');
        expect(indikatorDesa.text()).toContain('Ketahanan Sosial (IKS)');
        expect(indikatorDesa.text()).toContain('Ketahanan Ekonomi (IKE)');
        expect(indikatorDesa.text()).toContain('Lingkungan & Sarpras (IKL)');
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
        expect(wrapper.find('[data-testid="beranda-proyek-modal"]').findComponent({ name: 'StatusBadge' }).exists()).toBe(false);
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

        const cardWrapper = kegiatanWrapper.find('[data-testid="publik-kegiatan-card-1"]');
        const cardHtml = cardWrapper.html();
        expect(cardHtml).toContain('Baca Dokumentasi Lengkap');
        expect(cardHtml).toContain('Dokumentasi Foto');
        expect(cardHtml).not.toContain('lucide-map-pin');
        expect(cardWrapper.find('.bg-blue-50').exists()).toBe(false);

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
