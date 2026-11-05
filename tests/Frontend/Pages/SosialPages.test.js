import { describe, expect, it, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import SosialBansos from '@/Pages/Sosial/Bansos.vue';
import SosialKegiatanIndex from '@/Pages/Sosial/KegiatanIndex.vue';
import SosialKegiatanForm from '@/Pages/Sosial/KegiatanForm.vue';

describe('Sub-Fase 8.3: Antarmuka Modul 4 Bansos & Dokumentasi Kegiatan (/panel/sosial/*)', () => {
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
        page.props.flash = {};
        page.props.filter_tahun = 2026;
    });

    it('Sosial/Bansos merender perpindahan 2 tab, toggle filter Per KK [Default] vs Per Individu, serta tombol Import, Export, dan Cetak Daftar Hadir F4', async () => {
        const samplePenerima = [
            {
                id: 11,
                penduduk_id: 201,
                nik: '7203081205900001',
                nama_lengkap: 'Budi Santoso',
                nomor_kk: '7203080101200001',
                dusun: 'Dusun I',
                rt: '001',
                rw: '001',
                jenis_bantuan: 'blt_dd',
                jenis_bantuan_label: 'BLT Dana Desa (BLT-DD)',
                bentuk_nominal: 'Rp 300.000 / Bulan',
                tahun_anggaran: 2026,
                tahap_penyaluran: 'Tahap I',
                kategori_ekonomi: 'dtsen_desil_1',
                kategori_ekonomi_label: 'DTSEN Desil 1 (Miskin Ekstrem)',
                status_kepesertaan: 'aktif',
                status_kepesertaan_label: 'Aktif',
            },
        ];

        const samplePemerataanKk = [
            {
                kartu_keluarga_id: 301,
                nomor_kk: '7203080101200099',
                dusun: 'Dusun II',
                rt: '002',
                rw: '001',
                penduduk_id: 401,
                nik_kepala_keluarga: '7203081506850009',
                nama_kepala_keluarga: 'Hasan Basri',
                status_kesejahteraan: 'dtsen_desil_1',
                status_kesejahteraan_label: 'DTSEN Desil 1 (Miskin Ekstrem)',
                jumlah_anggota_aktif: 4,
                jumlah_anggota_prioritas: 4,
            },
        ];

        const samplePemerataanIndividu = [
            {
                penduduk_id: 401,
                kartu_keluarga_id: 301,
                nik: '7203081506850009',
                nama_lengkap: 'Hasan Basri',
                hubungan_keluarga: 'kepala_keluarga',
                pekerjaan: 'Petani',
                nomor_kk: '7203080101200099',
                dusun: 'Dusun II',
                status_kesejahteraan: 'dtsen_desil_1',
                status_kesejahteraan_label: 'DTSEN Desil 1 (Miskin Ekstrem)',
            },
            {
                penduduk_id: 402,
                kartu_keluarga_id: 301,
                nik: '7203085506880010',
                nama_lengkap: 'Aminah Basri',
                hubungan_keluarga: 'istri',
                pekerjaan: 'IRT',
                nomor_kk: '7203080101200099',
                dusun: 'Dusun II',
                status_kesejahteraan: 'dtsen_desil_1',
                status_kesejahteraan_label: 'DTSEN Desil 1 (Miskin Ekstrem)',
            },
        ];

        const wrapper = mount(SosialBansos, {
            props: {
                tahun_anggaran: 2026,
                penerima_bansos: samplePenerima,
                pemerataan_kk: samplePemerataanKk,
                pemerataan_individu: samplePemerataanIndividu,
                mode_pemerataan: 'per_kk',
                ringkasan: {
                    total_penerima: 1,
                    total_aktif: 1,
                    total_graduasi: 0,
                    jumlah_kk_belum_terima: 1,
                    jumlah_individu_belum_terima: 2,
                },
            },
        });

        // 1. Cek tombol Cetak Daftar Hadir F4, Export Excel, Import Excel, & Tambah Penerima
        expect(wrapper.find('[data-testid="btn-cetak-bansos-f4"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="btn-cetak-bansos-f4"]').attributes('href')).toContain(
            '/panel/sosial/bansos/cetak-pdf?tahun=2026',
        );
        expect(wrapper.find('[data-testid="btn-export-bansos"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="btn-open-import-bansos"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="btn-tambah-bansos"]').exists()).toBe(true);

        // 2. Default berada di Tab 1 (Daftar Penerima Bansos)
        expect(wrapper.find('[data-testid="section-daftar-penerima"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Budi Santoso');

        // 3. Pindah ke Tab 2 (Pemerataan Bantuan) -> Default menampilkan Mode Per KK
        await wrapper.find('[data-testid="tab-pemerataan-bantuan"]').trigger('click');
        expect(wrapper.find('[data-testid="section-pemerataan-bantuan"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="table-pemerataan-kk"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="table-pemerataan-individu"]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Hasan Basri');
        expect(wrapper.text()).toContain('4 prioritas dari 4 anggota aktif');

        // 4. Klik Toggle Per Individu -> beralih ke tabel Per Individu
        await wrapper.find('[data-testid="toggle-pemerataan-per-individu"]').trigger('click');
        expect(wrapper.find('[data-testid="table-pemerataan-kk"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="table-pemerataan-individu"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Aminah Basri');

        // Klik kembali Toggle Per KK (Default)
        await wrapper.find('[data-testid="toggle-pemerataan-per-kk"]').trigger('click');
        expect(wrapper.find('[data-testid="table-pemerataan-kk"]').exists()).toBe(true);
    });

    it('Sosial/KegiatanForm memvalidasi batas 1-5 foto, penyusunan sampul utama, pemilihan status Draft vs Published, serta tampilan Read-Only bagi kepala_desa', async () => {
        const postSpy = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onFinish?.();
            });

        const wrapper = mount(SosialKegiatanForm, {
            props: {
                is_edit: false,
                tanggal_hari_ini: '2026-03-20',
            },
        });

        await wrapper
            .find('[data-testid="input-judul-kegiatan"]')
            .setValue('Gotong Royong Bersih Desa');
        await wrapper
            .find('[data-testid="textarea-uraian-kegiatan"]')
            .setValue('Kegiatan kerja bakti warga Dusun I.');

        // 1. Klik "Terbitkan Langsung (Published)" saat foto masih 0 -> ditolak oleh validasi client & menampilkan pesan error
        await wrapper.find('[data-testid="btn-terbitkan-langsung"]').trigger('click');
        expect(wrapper.find('[data-testid="published-photo-error"]').exists()).toBe(true);
        expect(postSpy).not.toHaveBeenCalled();

        // 2. Klik "Simpan sebagai Draft" saat foto masih 0 -> diizinkan
        await wrapper.find('[data-testid="btn-simpan-draft"]').trigger('click');
        expect(postSpy).toHaveBeenCalledTimes(1);
        expect(postSpy.mock.calls[0][1]).toMatchObject({
            judul: 'Gotong Royong Bersih Desa',
            status_publikasi: 'draft',
        });

        // 3. Uji batas maksimal 5 foto (coba tambahkan 6 foto sekaligus)
        const fakeFiles = [
            new File(['a'], 'foto-1.jpg', { type: 'image/jpeg' }),
            new File(['b'], 'foto-2.jpg', { type: 'image/jpeg' }),
            new File(['c'], 'foto-3.jpg', { type: 'image/jpeg' }),
            new File(['d'], 'foto-4.jpg', { type: 'image/jpeg' }),
            new File(['e'], 'foto-5.jpg', { type: 'image/jpeg' }),
            new File(['f'], 'foto-6.jpg', { type: 'image/jpeg' }),
        ];

        wrapper.vm.addFiles(fakeFiles);
        await flushPromises();

        // Hanya 5 foto yang diterima dan peringatan batas 5 foto muncul
        expect(wrapper.find('[data-testid="photo-counter"]').text()).toBe('5 / 5');
        expect(wrapper.find('[data-testid="photo-limit-warning"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="badge-sampul-utama"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="preview-sampul-utama"]').text()).toContain('foto-1.jpg');

        // Geser foto ke-2 naik menjadi Sampul Utama (Urutan #1)
        await wrapper.find('[data-testid="btn-move-up-1"]').trigger('click');
        expect(wrapper.find('[data-testid="preview-sampul-utama"]').text()).toContain('foto-2.jpg');

        // Sekarang klik "Terbitkan Langsung (Published)" dengan 5 foto -> berhasil memicu router.post dengan status_publikasi = 'published'
        postSpy.mockClear();
        await wrapper.find('[data-testid="btn-terbitkan-langsung"]').trigger('click');
        expect(postSpy).toHaveBeenCalledTimes(1);
        expect(postSpy.mock.calls[0][1]).toMatchObject({
            judul: 'Gotong Royong Bersih Desa',
            status_publikasi: 'published',
        });

        // 4. Uji Mode Read-Only bagi Kepala Desa pada KegiatanForm, KegiatanIndex, dan Bansos
        usePage().props.auth = {
            user: {
                id: 2,
                name: 'Kepala Desa',
                role: 'kepala_desa',
            },
        };

        const wrapperKadesForm = mount(SosialKegiatanForm, {
            props: {
                is_edit: true,
                kegiatan: {
                    id: 10,
                    judul: 'Musdes RKPDes',
                    kategori: 'musdes',
                    tanggal_kegiatan: '2026-03-10',
                    lokasi_dusun: 'Balai Desa',
                    uraian: 'Musyawarah desa',
                    status_publikasi: 'published',
                    foto: [
                        { id: 1, foto_path: 'kegiatan/2026/a.webp', foto_url: '/storage/kegiatan/2026/a.webp', urutan: 1 },
                    ],
                },
            },
        });

        expect(wrapperKadesForm.find('[data-testid="readonly-kades-badge"]').exists()).toBe(true);
        expect(wrapperKadesForm.find('[data-testid="btn-simpan-draft"]').exists()).toBe(false);
        expect(wrapperKadesForm.find('[data-testid="btn-terbitkan-langsung"]').exists()).toBe(false);
        expect(wrapperKadesForm.find('[data-testid="input-judul-kegiatan"]').attributes('disabled')).toBeDefined();

        const wrapperKadesKegiatanIndex = mount(SosialKegiatanIndex, {
            props: {
                kegiatan: [
                    {
                        id: 10,
                        judul: 'Musdes RKPDes',
                        slug: 'musdes-rkpdes',
                        kategori: 'musdes',
                        kategori_label: 'Musyawarah Desa (Musdes)',
                        tanggal_kegiatan: '2026-03-10',
                        lokasi_dusun: 'Balai Desa',
                        status_publikasi: 'published',
                        status_publikasi_label: 'Published',
                        jumlah_foto: 1,
                    },
                ],
            },
        });

        expect(wrapperKadesKegiatanIndex.find('[data-testid="btn-buat-kegiatan"]').exists()).toBe(false);
        expect(wrapperKadesKegiatanIndex.find('[data-testid="btn-edit-kegiatan-10"]').exists()).toBe(false);
        expect(wrapperKadesKegiatanIndex.find('[data-testid="btn-delete-kegiatan-10"]').exists()).toBe(false);
    });
});
