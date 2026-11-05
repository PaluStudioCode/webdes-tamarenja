import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { usePage } from '@inertiajs/vue3';
import LayananMandiriIndex from '@/Pages/LayananMandiri/Index.vue';
import VerifikasiSurat from '@/Pages/LayananMandiri/VerifikasiSurat.vue';

describe('Layanan Mandiri & Verifikasi QR Pages (Sub-Fase 10.2)', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        const page = usePage();
        page.url = '/layanan-mandiri';
        page.props.auth = { user: null };
        page.props.profil_desa = {
            nama_desa: 'Tamarenja',
            kecamatan: 'Sindue Tobata',
            kabupaten: 'Donggala',
            provinsi: 'Sulawesi Tengah',
        };
    });

    it('dynamically updates NIK and Birthdate labels when switching between sku, kelahiran, and kematian and ensures >= 44px mobile touch height', async () => {
        const wrapper = mount(LayananMandiriIndex, {
            props: {
                active_tab: 'pengajuan_surat',
            },
        });

        // Default jenis_surat = sku
        expect(wrapper.find('[data-testid="label-nik-pemohon"]').text()).toContain('NIK Pemohon');
        expect(wrapper.find('[data-testid="label-tanggal-lahir-pemohon"]').text()).toContain('Tanggal Lahir Pemohon');

        // Pastikan input utama dan tombol submit memiliki tinggi sentuh minimal 44px (min-h-[44px] / h-11)
        const nikInput = wrapper.find('[data-testid="input-nik-surat"]');
        const dobInput = wrapper.find('[data-testid="input-tanggal-lahir-surat"]');
        const submitBtn = wrapper.find('[data-testid="btn-submit-pengajuan-surat"]');
        expect(nikInput.classes()).toContain('min-h-[44px]');
        expect(dobInput.classes()).toContain('min-h-[44px]');
        expect(submitBtn.classes()).toContain('min-h-[44px]');

        // Pilih Surat Kelahiran -> Label berubah ke NIK & Tanggal Lahir Ibu / Ayah Kandung
        const jenisSelect = wrapper.find('[data-testid="select-jenis-surat"]');
        await jenisSelect.setValue('kelahiran');
        expect(wrapper.find('[data-testid="label-nik-pemohon"]').text()).toContain(
            'NIK Ibu / Ayah Kandung (Pelapor)',
        );
        expect(wrapper.find('[data-testid="label-tanggal-lahir-pemohon"]').text()).toContain(
            'Tanggal Lahir Ibu / Ayah Kandung',
        );

        // Pilih Surat Kematian -> Label berubah ke NIK & Tanggal Lahir Warga yang Meninggal Dunia
        await jenisSelect.setValue('kematian');
        expect(wrapper.find('[data-testid="label-nik-pemohon"]').text()).toContain(
            'NIK Warga yang Meninggal Dunia',
        );
        expect(wrapper.find('[data-testid="label-tanggal-lahir-pemohon"]').text()).toContain(
            'Tanggal Lahir Warga yang Meninggal Dunia',
        );
    });

    it('displays confirmation card after online letter submission and renders masked Bansos history on Cek Bansos tab', async () => {
        const wrapper = mount(LayananMandiriIndex, {
            props: {
                active_tab: 'pengajuan_surat',
                konfirmasi_pengajuan: {
                    id: 12,
                    jenis_surat: 'domisili',
                    jenis_surat_label: 'Surat Keterangan Domisili',
                    nama_pemohon_tersamarkan: 'B*** S******',
                    keperluan: 'Persyaratan Administrasi Bank',
                    no_wa_pemohon: '6281234567890',
                    status: 'menunggu_verifikasi',
                    status_label: 'Menunggu Verifikasi',
                },
                hasil_bansos: {
                    nama_tersamarkan: 'B*** S******',
                    dusun: 'Dusun II',
                    total_riwayat: 1,
                    riwayat: [
                        {
                            id: 7,
                            jenis_bantuan: 'blt_dd',
                            jenis_bantuan_label: 'BLT Dana Desa (BLT-DD)',
                            bentuk_nominal: 'Rp 300.000 / Bulan',
                            tahun_anggaran: 2026,
                            tahap_penyaluran: 'Tahap I',
                            status_kepesertaan: 'aktif',
                            status_label: 'Aktif',
                        },
                    ],
                },
            },
        });

        // Kartu konfirmasi pengajuan tampil
        const confirmCard = wrapper.find('[data-testid="konfirmasi-pengajuan-card"]');
        expect(confirmCard.exists()).toBe(true);
        expect(confirmCard.text()).toContain('Surat Keterangan Domisili');
        expect(confirmCard.text()).toContain('B*** S******');
        expect(confirmCard.text()).toContain('6281234567890');

        // Pindah ke Tab Cek Status Bansos Mandiri
        await wrapper.find('[data-testid="contextual-tab-cek_bansos"]').trigger('click');
        const bansosCard = wrapper.find('[data-testid="hasil-cek-bansos-card"]');
        expect(bansosCard.exists()).toBe(true);
        expect(wrapper.find('[data-testid="hasil-bansos-nama-tersamarkan"]').text()).toBe('B*** S******');
        expect(wrapper.find('[data-testid="hasil-bansos-dusun"]').text()).toContain('Dusun II');
        expect(bansosCard.text()).toContain('BLT Dana Desa (BLT-DD)');
    });

    it('renders VerifikasiSurat.vue accurately for terbit, dibatalkan, and tidak_terdaftar states', () => {
        // 1. Status Terbit
        const terbitWrapper = mount(VerifikasiSurat, {
            props: {
                status_verifikasi: 'terbit',
                banner_judul: 'SURAT ASLI & TERDAFTAR DI BUKU AGENDA DESA',
                banner_deskripsi: 'Dokumen surat ini sah.',
                dokumen: {
                    nomor_surat: '470 / 001 / DS-TMR / IV / 2026',
                    jenis_surat: 'Surat Keterangan Domisili',
                    tanggal_terbit: '2026-04-15',
                    nama_warga_tersamarkan: 'B*** S******',
                    pejabat_penandatangan: 'Kepala Desa Tamarenja — Moh. Rizal, S.AP',
                },
            },
        });

        expect(terbitWrapper.find('[data-testid="banner-verifikasi-terbit"]').exists()).toBe(true);
        expect(terbitWrapper.text()).toContain('SURAT ASLI & TERDAFTAR DI BUKU AGENDA DESA');
        expect(terbitWrapper.find('[data-testid="verifikasi-nomor-surat"]').text()).toBe(
            '470 / 001 / DS-TMR / IV / 2026',
        );
        expect(terbitWrapper.find('[data-testid="verifikasi-jenis-surat"]').text()).toBe(
            'Surat Keterangan Domisili',
        );
        expect(terbitWrapper.find('[data-testid="verifikasi-nama-warga"]').text()).toBe('B*** S******');
        expect(terbitWrapper.find('[data-testid="verifikasi-pejabat-penandatangan"]').text()).toContain(
            'Moh. Rizal, S.AP',
        );

        // 2. Status Dibatalkan
        const dibatalkanWrapper = mount(VerifikasiSurat, {
            props: {
                status_verifikasi: 'dibatalkan',
                dokumen: {
                    nomor_surat: '470 / 002 / DS-TMR / IV / 2026',
                    jenis_surat: 'Surat Keterangan Usaha (SKU)',
                    alasan_pembatalan: 'Salah penulisan alamat usaha',
                },
            },
        });

        expect(dibatalkanWrapper.find('[data-testid="banner-verifikasi-dibatalkan"]').exists()).toBe(true);
        expect(dibatalkanWrapper.text()).toContain('Salah penulisan alamat usaha');

        // 3. Status Tidak Terdaftar
        const invalidWrapper = mount(VerifikasiSurat, {
            props: {
                status_verifikasi: 'tidak_terdaftar',
                dokumen: null,
            },
        });

        expect(invalidWrapper.find('[data-testid="banner-verifikasi-tidak-terdaftar"]').exists()).toBe(true);
        expect(invalidWrapper.text()).toContain('DOKUMEN TIDAK TERDAFTAR PADA BUKU AGENDA DESA');
    });
});
