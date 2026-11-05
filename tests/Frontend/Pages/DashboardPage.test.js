import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Dashboard from '@/Pages/Panel/Dashboard.vue';

function setupAuthRole(role = 'operator') {
    const page = usePage();
    page.url = '/panel/dashboard';
    page.props.auth = {
        user: {
            id: role === 'operator' ? 1 : 2,
            name: role === 'operator' ? 'Operator Desa' : 'Kepala Desa',
            email: role === 'operator' ? 'operator@desa.id' : 'kades@desa.id',
            role,
        },
    };
    page.props.profil_desa = {
        nama_desa: 'Tamarenja',
        tahun_anggaran_aktif: 2026,
        daftar_dusun: ['Dusun I', 'Dusun II'],
    };
    page.props.filter_tahun = 2026;
    page.props.available_tahun = [2026, 2025];
    page.props.antrean_surat_count = 2;
    page.props.flash = {};
}

const sampleDashboardProps = {
    tahun_anggaran: 2026,
    apbdes: {
        id: 1,
        jenis_dokumen: 'perubahan',
        jenis_dokumen_label: 'APBDes Perubahan (P-APBDes)',
        has_perubahan: true,
        total_pagu_belanja: 1250000000,
        total_realisasi_belanja: 950000000,
        sisa_anggaran_belanja: 300000000,
        persentase_serapan_belanja: 76,
        lima_bidang: [
            {
                kode: 'bidang_1',
                nama: 'Bidang 1: Penyelenggaraan Pemerintahan Desa',
                pagu_anggaran: 400000000,
                total_realisasi: 350000000,
                sisa_anggaran: 50000000,
                persentase_serapan: 87.5,
            },
            {
                kode: 'bidang_2',
                nama: 'Bidang 2: Pelaksanaan Pembangunan Desa (Fisik & Non-Fisik)',
                pagu_anggaran: 550000000,
                total_realisasi: 400000000,
                sisa_anggaran: 150000000,
                persentase_serapan: 72.73,
            },
        ],
    },
    pembangunan: {
        total_proyek: 3,
        selesai_count: 1,
        sedang_dikerjakan_count: 2,
        belum_dimulai_count: 0,
        total_pagu: 450000000,
        total_realisasi: 320000000,
        rata_progres_fisik: 75,
        daftar_proyek: [
            {
                id: 101,
                nama_kegiatan: 'Pembangunan Talud Penahan Tanah Dusun II',
                lokasi_dusun: 'Dusun II',
                sumber_dana: 'dds',
                sumber_dana_label: 'Dana Desa (DDS)',
                pagu_anggaran: 180000000,
                realisasi_anggaran: 120000000,
                persentase_serapan: 66.67,
                progres_fisik: 70,
                status_proyek: 'sedang_dikerjakan',
                status_label: 'Sedang Dikerjakan',
                catatan_kendala: 'Menunggu pengiriman material batu kali.',
                foto_0_path: 'proyek/2026/talud-0.webp',
                foto_50_path: 'proyek/2026/talud-50.webp',
                foto_100_path: null,
                foto_0_url: '/storage/proyek/2026/talud-0.webp',
                foto_50_url: '/storage/proyek/2026/talud-50.webp',
                foto_100_url: null,
            },
        ],
    },
    persuratan: {
        antrean_menunggu_count: 2,
        surat_keluar_terbit_count: 34,
        surat_keluar_dibatalkan_count: 1,
        surat_masuk_count: 12,
    },
    sosial: {
        total_penerima_bansos: 48,
        penerima_aktif_count: 45,
        kk_belum_menerima_count: 5,
        per_jenis: [
            { jenis_bantuan: 'blt_dd', label: 'BLT Dana Desa', jumlah: 25 },
            { jenis_bantuan: 'pkh', label: 'Program Keluarga Harapan (PKH)', jumlah: 20 },
        ],
        penduduk_aktif_count: 104,
        kk_aktif_count: 28,
    },
};

describe('Sub-Fase 9.2: Dashboard Eksekutif & Operasional (/panel/dashboard)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        setupAuthRole('operator');
    });

    it('merender 4 StatCard tanpa menduplikasi nilai utama di sub-header grafik APBDes', () => {
        const wrapper = mount(Dashboard, {
            props: sampleDashboardProps,
        });

        const statCards = wrapper.findAll('[data-testid="stat-card"]');
        expect(statCards).toHaveLength(4);

        const totalBelanjaText = statCards[0].get('[data-testid="stat-card-value"]').text();
        expect(totalBelanjaText).toContain('1.250.000.000');

        // Sub-header grafik tidak boleh mengulang angka total belanja utama dari StatCard
        const chartSubheading = wrapper.get('[data-testid="apbdes-chart-subheading"]').text();
        expect(chartSubheading).not.toContain('1.250.000.000');
    });

    it('merender grafik serapan 5 bidang atau skeleton state saat loadingChart bernilai true', async () => {
        const wrapperChart = mount(Dashboard, {
            props: {
                ...sampleDashboardProps,
                loadingChart: false,
            },
        });
        expect(wrapperChart.find('[data-testid="apbdes-chart-container"]').exists()).toBe(true);
        expect(wrapperChart.find('[data-testid="apbdes-chart-skeleton"]').exists()).toBe(false);

        const wrapperSkeleton = mount(Dashboard, {
            props: {
                ...sampleDashboardProps,
                loadingChart: true,
            },
        });
        expect(wrapperSkeleton.find('[data-testid="apbdes-chart-skeleton"]').exists()).toBe(true);
        expect(wrapperSkeleton.find('[data-testid="apbdes-chart-container"]').exists()).toBe(false);
    });

    it('menampilkan kendala lapangan proyek dan membuka drawer pratinjau foto 0%/50%/100%', async () => {
        const wrapper = mount(Dashboard, {
            props: sampleDashboardProps,
        });

        expect(wrapper.get('[data-testid="project-kendala-101"]').text()).toContain(
            'Menunggu pengiriman material batu kali.',
        );

        expect(wrapper.find('[data-testid="slide-over-drawer"]').exists()).toBe(false);

        await wrapper.get('[data-testid="open-photo-drawer-101"]').trigger('click');

        const drawer = wrapper.get('[data-testid="slide-over-drawer"]');
        expect(drawer.text()).toContain('Pembangunan Talud Penahan Tanah Dusun II');
        expect(drawer.text()).toContain('Titik Awal (0%)');
        expect(drawer.text()).toContain('Titik Tengah (50%)');
        expect(drawer.text()).toContain('Titik Akhir / Selesai (100%)');
    });

    it('menampilkan menu pintasan cetak laporan PDF F4 bagi kedua peran tetapi menyembunyikan pintasan aksi kelola bagi kepala_desa', () => {
        // 1. Operator melihat pintasan kelola & pintasan cetak PDF
        setupAuthRole('operator');
        const operatorWrapper = mount(Dashboard, {
            props: sampleDashboardProps,
        });
        expect(operatorWrapper.find('[data-testid="operator-quick-actions"]').exists()).toBe(true);
        expect(operatorWrapper.find('[data-testid="pdf-shortcut-apbdes"]').exists()).toBe(true);
        expect(operatorWrapper.find('[data-testid="pdf-shortcut-pembangunan"]').exists()).toBe(true);
        expect(operatorWrapper.find('[data-testid="pdf-shortcut-surat-keluar"]').exists()).toBe(true);
        expect(operatorWrapper.find('[data-testid="pdf-shortcut-surat-masuk"]').exists()).toBe(true);
        expect(operatorWrapper.find('[data-testid="pdf-shortcut-bansos"]').exists()).toBe(true);

        // 2. Kepala Desa melihat pintasan cetak PDF F4, tetapi pintasan aksi kelola disembunyikan
        setupAuthRole('kepala_desa');
        const kadesWrapper = mount(Dashboard, {
            props: sampleDashboardProps,
        });
        expect(kadesWrapper.find('[data-testid="operator-quick-actions"]').exists()).toBe(false);
        expect(kadesWrapper.find('[data-testid="pdf-shortcut-apbdes"]').exists()).toBe(true);
        expect(kadesWrapper.find('[data-testid="pdf-shortcut-pembangunan"]').exists()).toBe(true);
        expect(kadesWrapper.find('[data-testid="pdf-shortcut-bansos"]').exists()).toBe(true);
    });
});
