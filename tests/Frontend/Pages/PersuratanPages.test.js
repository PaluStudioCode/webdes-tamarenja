import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import PersuratanAntrean from '@/Pages/Persuratan/Antrean.vue';
import PersuratanSuratKeluar from '@/Pages/Persuratan/SuratKeluar.vue';
import PersuratanSuratMasuk from '@/Pages/Persuratan/SuratMasuk.vue';
import BuatSuratLoketModal from '@/Components/BuatSuratLoketModal.vue';
import { useConfirm } from '@/Composables/useConfirm';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
    },
}));

describe('Sub-Fase 7.3: Antarmuka Grup Persuratan (/panel/persuratan/*), Cek Bansos SKTM & Klik WA', () => {
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
        page.props.errors = {};
        page.props.filter_tahun = 2026;
    });

    it('BuatSuratLoketModal merender pergantian field dinamis untuk ke-10 jenis surat, pintasan kelahiran KK, dan panel otomatis Bansos 1 KK pada SKTM', async () => {
        axios.get.mockResolvedValue({
            data: {
                penduduk_id: 77,
                nomor_kk: '7203080101200001',
                status_kesejahteraan: 'dtsen_desil_1',
                status_kesejahteraan_label: 'DTSEN Desil 1 (Sangat Miskin)',
                total_bantuan_aktif: 2,
                riwayat_bansos: [
                    {
                        id: 1,
                        nama_penerima: 'Siti Aminah',
                        jenis_bantuan_label: 'BLT Dana Desa',
                        tahun_anggaran: 2026,
                        tahap_penyaluran: 1,
                        status_penerima_label: 'Aktif',
                    },
                ],
            },
        });

        const sampleResidents = [
            {
                id: 77,
                nik: '7203085205920002',
                nama_lengkap: 'Siti Aminah',
                jenis_kelamin: 'P',
                status_kesejahteraan: 'dtsen_desil_1',
                status_kependudukan: 'aktif',
                dusun: 'Dusun I',
            },
        ];

        const wrapper = mount(BuatSuratLoketModal, {
            props: {
                show: true,
                residents: sampleResidents,
                tanggal_hari_ini: '2026-03-15',
            },
        });

        const selectJenis = wrapper.find('[data-testid="select-jenis-surat"]');

        // 1. Default SKU (memastikan ada bidang_usaha)
        expect(wrapper.find('[data-testid="fields-template-sku"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-sku-bidang-usaha"]').exists()).toBe(true);

        // 2. Domisili
        await selectJenis.setValue('domisili');
        expect(wrapper.find('[data-testid="fields-template-domisili"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-domisili-sejak-tahun"]').exists()).toBe(true);

        // 3. SKTM + Kode Klasifikasi Otomatis Terkunci (Read-Only) + Panel Otomatis Cek Bansos 1 KK
        await selectJenis.setValue('sktm');
        expect(wrapper.find('[data-testid="fields-template-sktm"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-kode-klasifikasi-readonly"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-kode-klasifikasi-readonly"]').element.value).toBe('400');
        expect(wrapper.find('[data-testid="panel-bansos-kk-sktm"]').exists()).toBe(true);

        // Simulasikan memilih warga pada NikSearchAutocomplete untuk memicu cek Bansos 1 KK
        const nikComp = wrapper.findComponent({ name: 'NikSearchAutocomplete' });
        nikComp.vm.$emit('update:modelValue', 77);
        nikComp.vm.$emit('select', sampleResidents[0]);
        await flushPromises();

        expect(axios.get).toHaveBeenCalledWith('/panel/persuratan/cek-bansos-kk/77');
        expect(wrapper.find('[data-testid="panel-bansos-kk-sktm"]').text()).toContain('7203080101200001');
        expect(wrapper.find('[data-testid="panel-bansos-kk-sktm"]').text()).toContain('BLT Dana Desa');

        // 4. Pengantar SKCK
        await selectJenis.setValue('pengantar_skck');
        expect(wrapper.find('[data-testid="fields-template-pengantar_skck"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-skck-tujuan"]').exists()).toBe(true);

        // 5. Kematian (plus callout warning trigger otomatis)
        await selectJenis.setValue('kematian');
        expect(wrapper.find('[data-testid="fields-template-kematian"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="callout-warning-kematian"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-kematian-penyebab"]').exists()).toBe(true);

        // 6. Kelahiran (plus pintasan tambah anggota ke KK pasca punya NIK 16 digit)
        await selectJenis.setValue('kelahiran');
        expect(wrapper.find('[data-testid="fields-template-kelahiran"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="callout-pintasan-kelahiran-kk"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="link-pintasan-tambah-anggota-kk"]').attributes('href')).toBe(
            '/panel/kependudukan/penduduk/tambah',
        );

        // 7. Belum Menikah
        await selectJenis.setValue('belum_menikah');
        expect(wrapper.find('[data-testid="fields-template-belum_menikah"]').exists()).toBe(true);

        // 8. Janda / Duda
        await selectJenis.setValue('janda_duda');
        expect(wrapper.find('[data-testid="fields-template-janda_duda"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="select-janda-duda-status-cerai"]').exists()).toBe(true);

        // 9. Kehilangan
        await selectJenis.setValue('kehilangan');
        expect(wrapper.find('[data-testid="fields-template-kehilangan"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-kehilangan-barang"]').exists()).toBe(true);

        // 10. Keterangan Umum (pilihan kode 140/000 + judul_keterangan opsional)
        await selectJenis.setValue('keterangan_umum');
        expect(wrapper.find('[data-testid="fields-template-keterangan_umum"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-umum-judul-keterangan"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="textarea-umum-isi-keterangan"]').exists()).toBe(true);
    });

    it('Persuratan/SuratKeluar memicu pembukaan tab/unduhan URL PDF pasca-terbit dan konfirmasi pembatalan via useConfirm()', async () => {
        const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation(() => {});

        const wrapper = mount(PersuratanSuratKeluar, {
            props: {
                tahun_surat: 2026,
                jumlah_menunggu: 2,
                cetak_id: 88,
                cetak_pdf_url: '/panel/persuratan/surat-keluar/88/pdf',
                surat_keluar: [
                    {
                        id: 88,
                        nomor_surat: '500/001/PEMDES-TMR/III/2026',
                        tanggal_surat: '2026-03-15',
                        sumber_pengajuan: 'loket',
                        jenis_surat: 'sku',
                        jenis_surat_label: 'Surat Keterangan Usaha (SKU)',
                        keperluan: 'Pengajuan KUR BRI',
                        nama_pemohon: 'Budi Santoso',
                        nik_pemohon: '7203081205900001',
                        nama_penandatangan: 'Moh. Rizal',
                        jabatan_penandatangan: 'Kepala Desa',
                        status: 'terbit',
                        status_label: 'Terbit / Sah',
                        no_wa_pemohon: '6281234567890',
                        wa_pickup_url:
                            'https://wa.me/6281234567890?text=Halo%20Bpk%2FIbu%20Budi%20Santoso',
                    },
                    {
                        id: 89,
                        nomor_surat: '500/002/PEMDES-TMR/III/2026',
                        tanggal_surat: '2026-03-15',
                        sumber_pengajuan: 'loket',
                        jenis_surat: 'domisili',
                        jenis_surat_label: 'Surat Keterangan Domisili',
                        keperluan: 'Pendaftaran kuliah',
                        nama_pemohon: 'Joko',
                        nik_pemohon: '7203081205900002',
                        nama_penandatangan: 'Moh. Rizal',
                        jabatan_penandatangan: 'Kepala Desa',
                        status: 'dibatalkan',
                        status_label: 'Dibatalkan',
                        alasan_pembatalan: 'Salah ketik',
                    },
                ],
            },
        });

        // 1. Pemicu buka PDF otomatis saat halaman dimuat pasca-terbit
        expect(openSpy).toHaveBeenCalledWith(
            '/panel/persuratan/surat-keluar/88/pdf',
            '_blank',
        );

        // 2. Tombol Buat Surat di Loket membuka BuatSuratLoketModal
        expect(wrapper.findComponent(BuatSuratLoketModal).props('show')).toBe(false);
        await wrapper.find('[data-testid="btn-buat-surat-loket"]').trigger('click');
        expect(wrapper.findComponent(BuatSuratLoketModal).props('show')).toBe(true);

        // 3. Uji Batalkan Surat via useConfirm()
        await wrapper.find('[data-testid="btn-batalkan-surat-88"]').trigger('click');
        await wrapper
            .find('[data-testid="input-alasan-pembatalan"]')
            .setValue('Salah ketik alamat usaha');
        await wrapper
            .find('[data-testid="btn-confirm-batalkan-surat"]')
            .trigger('click');

        expect(useConfirm().state.isOpen).toBe(true);
        useConfirm().accept();
        await flushPromises();

        expect(patchSpy).toHaveBeenCalledWith(
            '/panel/persuratan/surat-keluar/88/batalkan',
            { alasan_pembatalan: 'Salah ketik alamat usaha' },
            expect.any(Object),
        );

        // 4. Pastikan tidak ada tombol aksi WA pada tabel Buku Agenda Surat Keluar
        expect(wrapper.find('[data-testid="btn-wa-agenda-88"]').exists()).toBe(false);

        // 5. Pastikan surat berstatus dibatalkan tidak memunculkan tombol Cetak F4 maupun Batalkan
        expect(wrapper.find('[data-testid="btn-cetak-ulang-89"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="btn-batalkan-surat-89"]').exists()).toBe(false);

        // 6. Pastikan tabel Buku Agenda rapi (5 kolom, thead tanpa kolom Pejabat Penandatangan terpisah & tanpa teks sumber LOKET/ONLINE)
        expect(wrapper.find('thead').text()).not.toContain('Pejabat Penandatangan');
        expect(wrapper.find('tbody').text()).not.toContain('LOKET');
        expect(wrapper.find('tbody').text()).toContain('TTD: Moh. Rizal');
    });

    it('Persuratan/Antrean membentuk tautan https://wa.me/628... beserta teks pesan pengambilan surat yang akurat dan konfirmasi tolak via useConfirm()', async () => {
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation(() => {});

        const wrapper = mount(PersuratanAntrean, {
            props: {
                jumlah_menunggu: 1,
                antrean: [
                    {
                        id: 101,
                        nama_pemohon: 'Andi Saputra',
                        nik_pemohon: '7203080102900003',
                        dusun_pemohon: 'Dusun II',
                        jenis_surat: 'domisili',
                        jenis_surat_label: 'Surat Keterangan Domisili',
                        keperluan: 'Pendaftaran kuliah',
                        no_wa_pemohon: '085299887766',
                        wa_pickup_url: null,
                        nomor_surat: '470/002/PEMDES-TMR/III/2026',
                        status: 'terbit',
                        status_label: 'Terbit / Sah',
                    },
                    {
                        id: 102,
                        nama_pemohon: 'Rina Wati',
                        nik_pemohon: '7203084506950004',
                        dusun_pemohon: 'Dusun I',
                        jenis_surat: 'sktm',
                        jenis_surat_label: 'Surat Keterangan Tidak Mampu (SKTM)',
                        keperluan: 'Beasiswa sekolah',
                        no_wa_pemohon: '081344556677',
                        data_khusus: {
                            penghasilan_rata_rata: 500000,
                            jumlah_tanggungan: 3,
                            nama_tanggungan: 'Ahmad & Siti',
                        },
                        status: 'menunggu_verifikasi',
                        status_label: 'Menunggu Verifikasi',
                    },
                ],
                pejabat_penandatangan: [
                    { value: 'kepala_desa', label: 'Kepala Desa' },
                ],
            },
        });

        // 1. Cek pembentukan tautan WA pickup otomatis dari 085299887766 -> https://wa.me/6285299887766
        const waBtn = wrapper.find('[data-testid="btn-wa-pickup-101"]');
        expect(waBtn.exists()).toBe(true);
        expect(waBtn.text()).toContain('Whatsapp');
        const href = waBtn.attributes('href');
        expect(href).toContain('https://wa.me/6285299887766?text=');
        const decodedText = decodeURIComponent(href.split('?text=')[1]);
        expect(decodedText).toBe(
            'Halo Bpk/Ibu Andi Saputra, Surat Surat Keterangan Domisili Anda sudah selesai dicetak dan ditandatangani. Silakan diambil di Kantor Desa dengan membawa KTP/KK asli.',
        );

        // 1b. Pastikan tombol Cetak, teks nomor surat resmi, dan teks nomor WA sel tidak ada di tabel antrean
        expect(wrapper.find('[data-testid="btn-cetak-antrean-101"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('470/002/PEMDES-TMR/III/2026');
        expect(wrapper.text()).not.toContain('WA: 085299887766');
        expect(wrapper.text()).not.toContain('Aksi Layanan');
        expect(wrapper.text()).toContain('Waktu Pengajuan');
        expect(wrapper.text()).toContain('Aksi');

        // 1c. Uji Modal Verifikasi: merender rincian data khusus yang diinput warga
        await wrapper.find('[data-testid="btn-verifikasi-antrean-102"]').trigger('click');
        expect(wrapper.find('[data-testid="section-review-data-khusus"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="section-review-data-khusus"]').text()).toContain('500.000');
        expect(wrapper.find('[data-testid="section-review-data-khusus"]').text()).toContain('3 Orang');
        expect(wrapper.find('[data-testid="section-review-data-khusus"]').text()).toContain('Ahmad & Siti');

        // Uji tombol pintas tolak dari dalam modal verifikasi
        const shortcutTolakBtn = wrapper.find('[data-testid="btn-verifikasi-tolak-shortcut"]');
        expect(shortcutTolakBtn.exists()).toBe(true);
        await shortcutTolakBtn.trigger('click');

        // 2. Uji Tolak Permohonan dengan useConfirm() (modal tolak sudah terbuka via shortcut)
        await wrapper
            .find('[data-testid="input-alasan-penolakan"]')
            .setValue('Mohon lampirkan pengantar RT setempat');
        await wrapper.find('[data-testid="btn-submit-tolak"]').trigger('click');

        expect(useConfirm().state.isOpen).toBe(true);
        useConfirm().accept();
        await flushPromises();

        expect(patchSpy).toHaveBeenCalledWith(
            '/panel/persuratan/antrean/102/tolak',
            { alasan_penolakan: 'Mohon lampirkan pengantar RT setempat' },
            expect.any(Object),
        );
    });

    it('Persuratan/SuratMasuk membuka berkas scan di tab baru langsung dan menyembunyikan seluruh aksi tulis bagi kepala_desa (Read-Only)', async () => {
        const sampleSuratMasuk = [
            {
                id: 301,
                tahun_agenda: 2026,
                nomor_urut_agenda: 1,
                nomor_urut_padded: '001',
                nomor_surat_masuk: '005/99/KEC-ST/2026',
                tanggal_surat: '2026-03-10',
                tanggal_terima: '2026-03-12',
                pengirim: 'Camat Sindue Tobata',
                perihal: 'Undangan Rapat Koordinasi Pemerintahan Desa',
                tujuan_disposisi: 'Sekretaris Desa',
                catatan_disposisi: 'Hadiri tepat waktu',
                has_scan: true,
                scan_type: 'pdf',
                scan_stream_url: '/panel/persuratan/surat-masuk/301/berkas',
            },
        ];

        // 1. Uji sebagai Operator: dapat membuka drawer pratinjau scan & melihat tombol tambah/edit/hapus
        const wrapperOperator = mount(PersuratanSuratMasuk, {
            props: {
                tahun_agenda: 2026,
                surat_masuk: sampleSuratMasuk,
                jumlah_menunggu: 0,
            },
        });

        expect(wrapperOperator.find('[data-testid="btn-tambah-surat-masuk"]').exists()).toBe(true);
        expect(wrapperOperator.find('[data-testid="btn-edit-surat-masuk-301"]').exists()).toBe(true);

        // Uji tombol Lihat berkas scan (buka langsung tab baru, tanpa drawer duplikat info)
        const previewBtn = wrapperOperator.find('[data-testid="btn-preview-scan-301"]');
        expect(previewBtn.text()).toContain('Lihat');
        expect(previewBtn.text()).not.toContain('Lihat Scan');
        expect(previewBtn.attributes('href')).toBe('/panel/persuratan/surat-masuk/301/berkas');
        expect(previewBtn.attributes('target')).toBe('_blank');
        expect(previewBtn.attributes('rel')).toBe('noopener noreferrer');
        expect(wrapperOperator.find('[data-testid="scan-preview-iframe"]').exists()).toBe(false);

        // Uji tabel 5 kolom (Berkas Scan digabung ke Aksi) dan kebersihan teks tanggal
        expect(wrapperOperator.find('thead').text()).not.toContain('Berkas Scan');
        expect(wrapperOperator.find('tbody').text()).not.toContain('Terima:');
        expect(wrapperOperator.find('tbody').text()).not.toContain('Tgl Surat:');
        expect(wrapperOperator.find('tbody').text()).toContain('Tgl: 2026-03-10');

        // Uji label submit modal: "Simpan" saat tambah baru, "Perbarui" saat edit
        await wrapperOperator.find('[data-testid="btn-tambah-surat-masuk"]').trigger('click');
        expect(wrapperOperator.find('[data-testid="btn-submit-surat-masuk"]').text()).toBe('Simpan');

        await wrapperOperator.find('[data-testid="btn-edit-surat-masuk-301"]').trigger('click');
        expect(wrapperOperator.find('[data-testid="btn-submit-surat-masuk"]').text()).toBe('Perbarui');

        // 2. Uji sebagai Kepala Desa (Read-Only): tombol tambah/edit/hapus/verifikasi disembunyikan, namun Cetak Agenda F4 & Lihat Scan tetap aktif
        usePage().props.auth = {
            user: {
                id: 2,
                name: 'Kepala Desa',
                role: 'kepala_desa',
            },
        };

        const wrapperKadesMasuk = mount(PersuratanSuratMasuk, {
            props: {
                tahun_agenda: 2026,
                surat_masuk: sampleSuratMasuk,
                jumlah_menunggu: 1,
            },
        });

        expect(wrapperKadesMasuk.find('[data-testid="btn-cetak-agenda-masuk"]').exists()).toBe(true);
        expect(wrapperKadesMasuk.find('[data-testid="btn-preview-scan-301"]').exists()).toBe(true);
        expect(wrapperKadesMasuk.find('[data-testid="btn-tambah-surat-masuk"]').exists()).toBe(false);
        expect(wrapperKadesMasuk.find('[data-testid="btn-edit-surat-masuk-301"]').exists()).toBe(false);
        expect(wrapperKadesMasuk.find('[data-testid="btn-delete-surat-masuk-301"]').exists()).toBe(false);

        const wrapperKadesAntrean = mount(PersuratanAntrean, {
            props: {
                jumlah_menunggu: 1,
                antrean: [
                    {
                        id: 102,
                        nama_pemohon: 'Rina Wati',
                        nik_pemohon: '7203084506950004',
                        jenis_surat: 'sktm',
                        jenis_surat_label: 'SKTM',
                        keperluan: 'Beasiswa',
                        status: 'menunggu_verifikasi',
                        status_label: 'Menunggu Verifikasi',
                    },
                ],
            },
        });

        expect(wrapperKadesAntrean.find('[data-testid="btn-buat-surat-loket"]').exists()).toBe(false);
        expect(wrapperKadesAntrean.find('[data-testid="btn-verifikasi-antrean-102"]').exists()).toBe(false);
        expect(wrapperKadesAntrean.find('[data-testid="btn-tolak-antrean-102"]').exists()).toBe(false);
    });
});
