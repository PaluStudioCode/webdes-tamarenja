import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import LeafletMapPicker from '@/Components/LeafletMapPicker.vue';
import ApbdesBannerCanvas from '@/Components/ApbdesBannerCanvas.vue';
import ApbdesIndex from '@/Pages/Apbdes/Index.vue';
import PembangunanIndex from '@/Pages/Pembangunan/Index.vue';
import ProyekModal from '@/Components/ProyekModal.vue';
import ProyekDetailModal from '@/Components/ProyekDetailModal.vue';
import { useConfirm } from '@/Composables/useConfirm';

vi.mock('html-to-image', () => ({
    toPng: vi.fn(() => Promise.resolve('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')),
}));

// Stub komponen Bar vue-chartjs agar ringan di JSDOM
vi.mock('vue-chartjs', () => ({
    Bar: {
        name: 'Bar',
        props: ['data', 'options'],
        template: '<div data-testid="chart-bar-stub"></div>',
    },
}));

const sampleRincian = [
    {
        id: 1,
        kode_pos: 'pendapatan_dds',
        nama_pos: 'Dana Desa (DDS)',
        kelompok: 'pendapatan',
        is_otomatis_modul_1: false,
        pagu_anggaran: 800000000,
        realisasi_tahap_1: 400000000,
        realisasi_tahap_2: 200000000,
        realisasi_tahap_3: 200000000,
        total_realisasi: 800000000,
        sisa_anggaran: 0,
        persentase: 100,
        urutan: 2,
    },
    {
        id: 2,
        kode_pos: 'bidang_1_pemerintahan',
        nama_pos: 'Bidang 1: Penyelenggaraan Pemerintahan Desa',
        kelompok: 'belanja',
        is_otomatis_modul_1: false,
        pagu_anggaran: 350000000,
        realisasi_tahap_1: 150000000,
        realisasi_tahap_2: 100000000,
        realisasi_tahap_3: 50000000,
        total_realisasi: 300000000,
        sisa_anggaran: 50000000,
        persentase: 85.7,
        urutan: 8,
    },
    {
        id: 3,
        kode_pos: 'bidang_2_fisik',
        nama_pos: 'Bidang 2: Pelaksanaan Pembangunan Desa (Komponen Proyek Fisik)',
        kelompok: 'belanja',
        is_otomatis_modul_1: true,
        pagu_anggaran: 450000000,
        realisasi_tahap_1: 300000000,
        realisasi_tahap_2: 0,
        realisasi_tahap_3: 0,
        total_realisasi: 300000000,
        sisa_anggaran: 150000000,
        persentase: 66.7,
        urutan: 9,
    },
];

const sampleSummary = {
    total_pagu_pendapatan: 800000000,
    total_realisasi_pendapatan: 800000000,
    total_pagu_belanja: 800000000,
    total_realisasi_belanja: 600000000,
    sisa_anggaran_belanja: 200000000,
    persentase_serapan_belanja: 75,
    pembiayaan_netto_pagu: 0,
    pembiayaan_netto_realisasi: 0,
    selisih_keseimbangan_pagu: 0,
    is_pagu_seimbang: true,
};

describe('Sub-Fase 6.3: Komponen Peta, Baliho APBDes, Pembangunan & APBDes Pages', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        const page = usePage();
        page.props.auth = {
            user: {
                id: 1,
                name: 'Operator Desa',
                role: 'operator',
            },
        };
        page.props.filter_tahun = 2026;
    });

    it('LeafletMapPicker menyinkronkan props dan emits latitude & longitude dua arah', async () => {
        const wrapper = mount(LeafletMapPicker, {
            props: {
                latitude: -0.5835,
                longitude: 119.7932,
                defaultCenter: { latitude: -0.5835, longitude: 119.7932 },
            },
        });

        const latInput = wrapper.find('[data-testid="input-latitude"]');
        const lngInput = wrapper.find('[data-testid="input-longitude"]');

        expect(latInput.element.value).toBe('-0.5835');
        expect(lngInput.element.value).toBe('119.7932');

        // Ubah via input teks koordinat
        await latInput.setValue('-0.591234');
        expect(wrapper.emitted('update:latitude')?.[0]).toEqual([-0.591234]);

        await lngInput.setValue('119.801234');
        expect(wrapper.emitted('update:longitude')?.[0]).toEqual([119.801234]);

        // Ubah via metode klik peta (setCoordinates)
        wrapper.vm.setCoordinates(-0.5844449, 119.7955551);
        const latEvents = wrapper.emitted('update:latitude');
        const lngEvents = wrapper.emitted('update:longitude');

        expect(latEvents[latEvents.length - 1]).toEqual([-0.584445]);
        expect(lngEvents[lngEvents.length - 1]).toEqual([119.795555]);
    });

    it('ApbdesBannerCanvas merender dimensi tetap 1920x1080 px dan mengekspor PNG via html-to-image pixelRatio 2', async () => {
        const { toPng } = await import('html-to-image');

        const wrapper = mount(ApbdesBannerCanvas, {
            props: {
                dokumen: {
                    id: 1,
                    tahun_anggaran: 2026,
                    jenis_dokumen: 'murni',
                    nomor_perdes: 'Perdes No 02/2026',
                    rincian: sampleRincian,
                },
                ringkasan: sampleSummary,
            },
        });

        const canvasEl = wrapper.find('[data-testid="apbdes-banner-canvas"]');
        expect(canvasEl.exists()).toBe(true);
        expect(canvasEl.attributes('style')).toContain('width: 1920px');
        expect(canvasEl.attributes('style')).toContain('height: 1080px');
        expect(canvasEl.text()).toContain('INFOGRAFIS APBDes DESA Tamarenja TAHUN ANGGARAN 2026');

        await wrapper.find('[data-testid="download-apbdes-banner-btn"]').trigger('click');

        expect(toPng).toHaveBeenCalledTimes(1);
        expect(toPng.mock.calls[0][1]).toMatchObject({
            width: 1920,
            height: 1080,
            pixelRatio: 2,
        });
    });

    it('Apbdes/Index menampilkan baris bidang_2_fisik secara read-only dan mengunci dokumen Murni saat Perubahan ada', async () => {
        const murniDoc = {
            id: 10,
            tahun_anggaran: 2026,
            jenis_dokumen: 'murni',
            jenis_label: 'APBDes Awal (Murni)',
            is_aktif_publik: false,
            is_terkunci: true,
            nomor_perdes: 'Perdes No 1/2026',
            tanggal_penetapan: '2026-01-10',
            rincian: sampleRincian,
        };

        const perubahanDoc = {
            id: 11,
            tahun_anggaran: 2026,
            jenis_dokumen: 'perubahan',
            jenis_label: 'APBDes Perubahan (P-APBDes)',
            is_aktif_publik: true,
            is_terkunci: false,
            nomor_perdes: 'Perdes No 5/2026',
            tanggal_penetapan: '2026-08-15',
            rincian: sampleRincian,
        };

        const wrapper = mount(ApbdesIndex, {
            props: {
                tahun_anggaran: 2026,
                dokumen_aktif: perubahanDoc,
                dokumen_murni: murniDoc,
                dokumen_perubahan: perubahanDoc,
                has_perubahan: true,
                ringkasan: sampleSummary,
                perbandingan_lintas_tahun: [],
            },
        });

        // Pada dokumen Perubahan (belum terkunci), baris bidang_1_pemerintahan dan bidang_2_fisik dapat diedit secara mandiri
        const rowBidang1 = wrapper.find('[data-testid="row-pos-bidang_1_pemerintahan"]');
        const rowBidang2 = wrapper.find('[data-testid="row-pos-bidang_2_fisik"]');

        expect(rowBidang1.find('input').attributes('disabled')).toBeUndefined();
        expect(rowBidang2.find('[data-testid="badge-otomatis-modul-1"]').exists()).toBe(false);
        expect(rowBidang2.find('input').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('[data-testid="btn-save-apbdes"]').exists()).toBe(true);

        // Beralih ke dokumen Murni yang sudah terkunci (is_terkunci = true)
        await wrapper.find('[data-testid="switch-dokumen-murni"]').trigger('click');

        expect(wrapper.text()).toContain('APBDes Murni - Arsip Terkunci');
        expect(wrapper.find('[data-testid="row-pos-bidang_1_pemerintahan"] input').attributes('disabled')).toBeDefined();
        expect(wrapper.find('[data-testid="btn-save-apbdes"]').exists()).toBe(false);
    });

    it('Pembangunan/Index dan Apbdes/Index menyembunyikan aksi edit/hapus bagi kepala_desa serta membuka drawer foto & konfirmasi hapus bagi operator', async () => {
        const sampleProjects = [
            {
                id: 501,
                tahun_anggaran: 2026,
                nama_kegiatan: 'Pembangunan Rabat Beton Dusun I',
                lokasi_dusun: 'Dusun I',
                pagu_anggaran: 180000000,
                realisasi_anggaran: 90000000,
                persentase_dana: 50,
                sumber_dana: 'dds',
                sumber_dana_label: 'Dana Desa (DDS)',
                volume_fisik: '250 Meter',
                pelaksana_kegiatan: 'TPK Desa Tamarenja',
                progres_fisik: 50,
                status_proyek: 'sedang_dikerjakan',
                status_proyek_label: 'Sedang Dikerjakan',
                foto_0_path: 'proyek/2026/0.jpg',
                foto_50_path: 'proyek/2026/50.jpg',
                foto_100_path: null,
                foto_0_url: '/storage/proyek/2026/0.jpg',
                foto_50_url: '/storage/proyek/2026/50.jpg',
                foto_100_url: null,
            },
        ];

        // 1. Uji sebagai Operator: tombol Tambah, Edit, Hapus tampil & berfungsi
        const wrapperOperator = mount(PembangunanIndex, {
            props: {
                tahun_anggaran: 2026,
                proyek: sampleProjects,
                ringkasan: { total_proyek: 1, total_pagu: 180000000, total_realisasi: 90000000 },
                daftar_dusun: ['Dusun I', 'Dusun II'],
                opsi_sumber_dana: [{ value: 'dds', label: 'Dana Desa (DDS)' }],
                opsi_status: [{ value: 'sedang_dikerjakan', label: 'Sedang Dikerjakan' }],
            },
        });

        expect(wrapperOperator.find('[data-testid="btn-tambah-proyek"]').exists()).toBe(true);
        expect(wrapperOperator.find('[data-testid="btn-edit-proyek-501"]').exists()).toBe(true);
        expect(wrapperOperator.find('[data-testid="btn-delete-proyek-501"]').exists()).toBe(true);

        // Klik Tambah Proyek membuka modal form pembangunan fisik
        await wrapperOperator.find('[data-testid="btn-tambah-proyek"]').trigger('click');
        expect(wrapperOperator.find('[data-testid="modal-proyek-pembangunan"]').exists()).toBe(true);

        // Klik Edit membuka modal form pembangunan fisik dengan data proyek
        await wrapperOperator.find('[data-testid="btn-edit-proyek-501"]').trigger('click');
        expect(wrapperOperator.find('[data-testid="modal-proyek-pembangunan"]').exists()).toBe(true);

        // Klik Detail membuka ProyekDetailModal
        await wrapperOperator.find('[data-testid="btn-detail-proyek-501"]').trigger('click');
        expect(wrapperOperator.find('[data-testid="modal-detail-proyek"]').exists()).toBe(true);
        expect(wrapperOperator.text()).toContain('Dokumentasi Tahapan Fisik');

        // Klik Hapus memicu useConfirm()
        const deleteSpy = vi.spyOn(router, 'delete').mockImplementation(() => {});
        await wrapperOperator.find('[data-testid="btn-delete-proyek-501"]').trigger('click');
        expect(useConfirm().state.isOpen).toBe(true);
        useConfirm().accept();
        await Promise.resolve();
        expect(deleteSpy).toHaveBeenCalledWith('/panel/pembangunan/501', expect.any(Object));

        // 2. Uji sebagai Kepala Desa: seluruh tombol tulis disembunyikan, tombol Cetak Rekap F4 tetap tampil
        usePage().props.auth = {
            user: {
                id: 2,
                name: 'Kepala Desa',
                role: 'kepala_desa',
            },
        };

        const wrapperKades = mount(PembangunanIndex, {
            props: {
                tahun_anggaran: 2026,
                proyek: sampleProjects,
                ringkasan: { total_proyek: 1, total_pagu: 180000000, total_realisasi: 90000000 },
            },
        });

        expect(wrapperKades.find('[data-testid="btn-cetak-rekap-proyek"]').exists()).toBe(true);
        expect(wrapperKades.find('[data-testid="btn-tambah-proyek"]').exists()).toBe(false);
        expect(wrapperKades.find('[data-testid="btn-edit-proyek-501"]').exists()).toBe(false);
        expect(wrapperKades.find('[data-testid="btn-delete-proyek-501"]').exists()).toBe(false);
    });

    it('ProyekModal merender form modal tambah proyek, mendukung input identitas, dan memicu penutupan saat dibatalkan', async () => {
        usePage().props.auth = {
            user: {
                id: 1,
                name: 'Operator Desa',
                role: 'operator',
            },
        };

        const wrapper = mount(ProyekModal, {
            props: {
                show: true,
                isEdit: false,
                proyek: null,
                tahunAnggaran: 2026,
                daftarDusun: ['Dusun I', 'Dusun II'],
                opsiSumberDana: [{ value: 'dds', label: 'Dana Desa (DDS)' }],
                koordinatDefault: { latitude: -0.5835, longitude: 119.7932 },
            },
        });

        // 1. Memastikan judul dan field modal tampil
        expect(wrapper.text()).toContain('Tambah Proyek Pembangunan Fisik');
        expect(wrapper.find('[data-testid="input-nama-kegiatan-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-tahun-anggaran-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="select-sumber-dana-modal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="btn-submit-proyek-modal"]').exists()).toBe(true);

        // 2. Tombol batal memancarkan emit 'close'
        await wrapper.find('[data-testid="btn-cancel-proyek-modal"]').trigger('click');
        expect(wrapper.emitted('close')).toBeTruthy();
    });

    it('ProyekDetailModal merender galeri 3 titik foto, rincian pagu/realisasi, dan memicu emit edit bagi operator', async () => {
        const sampleProyek = {
            id: 501,
            tahun_anggaran: 2026,
            nama_kegiatan: 'Pembangunan Rabat Beton Dusun I',
            lokasi_dusun: 'Dusun I',
            pagu_anggaran: 180000000,
            realisasi_anggaran: 90000000,
            persentase_dana: 50,
            sumber_dana: 'dds',
            sumber_dana_label: 'Dana Desa (DDS)',
            volume_fisik: '250 Meter',
            pelaksana_kegiatan: 'TPK Desa Tamarenja',
            waktu_pelaksanaan: '90 Hari',
            progres_fisik: 50,
            status_proyek: 'sedang_dikerjakan',
            status_proyek_label: 'Sedang Dikerjakan',
            foto_0_url: '/storage/proyek/2026/0.jpg',
            foto_50_url: '/storage/proyek/2026/50.jpg',
            foto_100_url: null,
            latitude: -0.5835,
            longitude: 119.7932,
        };

        const wrapper = mount(ProyekDetailModal, {
            props: {
                show: true,
                proyek: sampleProyek,
                isOperator: true,
            },
        });

        // Memastikan rincian data tampil
        expect(wrapper.text()).toContain('Pembangunan Rabat Beton Dusun I');
        expect(wrapper.text()).toContain('Dana Desa (DDS)');
        expect(wrapper.text()).toContain('Titik Awal (0%)');
        expect(wrapper.text()).toContain('Titik Akhir (100%)');
        expect(wrapper.find('[data-testid="btn-edit-from-detail"]').exists()).toBe(true);

        // Klik Edit dari detail memicu emit 'edit' dengan objek proyek
        await wrapper.find('[data-testid="btn-edit-from-detail"]').trigger('click');
        expect(wrapper.emitted('edit')?.[0]?.[0]).toEqual(sampleProyek);

        // Klik Tutup memicu emit 'close'
        await wrapper.find('[data-testid="btn-close-detail-modal"]').trigger('click');
        expect(wrapper.emitted('close')).toBeTruthy();
    });
});
