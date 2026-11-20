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

    it('Sosial/Bansos merender tabel penerima bansos yang bersih tanpa tab pemerataan, filter 3 kategori bantuan, dropdown kategori pada modal, serta tombol Import, Export, dan Cetak Daftar Hadir F4', async () => {
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
                kategori_bantuan: 'per_kk',
                kategori_bantuan_label: 'Bantuan Sosial Per KK',
                jenis_bantuan: 'blt_dd',
                jenis_bantuan_label: 'BLT Dana Desa (BLT-DD)',
                bentuk_nominal: 'Rp 300.000 / Bulan',
                tahun_anggaran: 2026,
                tahap_penyaluran: 'Tahap I',
                status_kepesertaan: 'aktif',
                status_kepesertaan_label: 'Aktif',
            },
            {
                id: 12,
                penduduk_id: 202,
                nik: '7203081506850009',
                nama_lengkap: 'Hasan Basri',
                nomor_kk: '7203080101200099',
                dusun: 'Dusun II',
                rt: '002',
                rw: '001',
                kategori_bantuan: 'kelompok',
                kategori_bantuan_label: 'Bantuan Kelompok Masyarakat',
                nama_kelompok: 'Kelompok Tani Harapan Makmur',
                jenis_bantuan: 'bantuan_kelompok_tani',
                jenis_bantuan_label: 'Bantuan Kelompok Tani (Poktan)',
                bentuk_nominal: 'Bibit / Pupuk / Alsintan',
                tahun_anggaran: 2026,
                tahap_penyaluran: 'Tahap I',
                status_kepesertaan: 'aktif',
                status_kepesertaan_label: 'Aktif',
            },
        ];

        const wrapper = mount(SosialBansos, {
            props: {
                tahun_anggaran: 2026,
                penerima_bansos: samplePenerima,
                ringkasan: {
                    total_penerima: 2,
                    total_aktif: 2,
                    total_per_kk: 1,
                    total_perorangan: 0,
                    total_kelompok: 1,
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

        // 2. Pastikan tab Pemerataan Bantuan sudah dihapus dan langsung menampilkan Daftar Penerima Bansos
        expect(wrapper.find('[data-testid="tab-pemerataan-bantuan"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="section-daftar-penerima"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Budi Santoso');
        expect(wrapper.text()).toContain('Hasan Basri');
        expect(wrapper.text()).toContain('Kelompok Tani Harapan Makmur');

        // 3. Cek kelengkapan filter tabel (Kategori, Program, Tahap, Tahun Anggaran)
        expect(wrapper.find('[data-testid="filter-kategori-bansos"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-jenis-bansos"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-tahap-bansos"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-tahun-bansos"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="filter-dusun-bansos"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="filter-status-bansos"]').exists()).toBe(false);

        // Filter berdasarkan Kategori Kelompok -> hanya menampilkan Hasan Basri
        await wrapper.find('[data-testid="filter-kategori-bansos"]').setValue('kelompok');
        expect(wrapper.text()).not.toContain('Budi Santoso');
        expect(wrapper.text()).toContain('Hasan Basri');
        expect(wrapper.find('[data-testid="btn-cetak-bansos-f4"]').attributes('href')).toContain(
            'kategori_bantuan=kelompok',
        );

        // 4. Buka Modal Tambah Penerima & uji dropdown Kategori Bantuan Sosial
        await wrapper.find('[data-testid="btn-tambah-bansos"]').trigger('click');
        expect(wrapper.find('[data-testid="form-penerima-bansos"]').exists()).toBe(true);
        const selectKategori = wrapper.find('[data-testid="select-kategori-bantuan"]');
        expect(selectKategori.exists()).toBe(true);
        expect(selectKategori.element.tagName).toBe('SELECT');
        expect(wrapper.find('[data-testid="input-nama-kelompok"]').exists()).toBe(false);

        // Pilih kategori Kelompok pada dropdown -> menampilkan input Nama Kelompok / Usaha dan menyaring program kelompok
        await selectKategori.setValue('kelompok');
        await selectKategori.trigger('change');
        expect(wrapper.find('[data-testid="input-nama-kelompok"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="select-jenis-bantuan"]').element.value).toBe(
            'bantuan_kelompok_tani',
        );

        // 5. Uji input banyak penerima sekaligus + validasi wajib Desil 1-5 & tolak NIK yang sudah menerima
        await selectKategori.setValue('per_kk');
        await selectKategori.trigger('change');
        await wrapper.find('[data-testid="select-jenis-bantuan"]').setValue('blt_dd');
        await wrapper.find('[data-testid="select-jenis-bantuan"]').trigger('change');

        // Coba pilih warga Non-Prioritas (bukan Desil 1-5) -> ditolak
        wrapper.vm.handleSelectResident({
            id: 301,
            nik: '7203081205900301',
            nama_lengkap: 'Warga Mampu Non Desil',
            nomor_kk: '7203080101200301',
            status_kesejahteraan: 'non_prioritas',
        });
        await flushPromises();
        expect(wrapper.find('[data-testid="resident-selection-error"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="resident-selection-error"]').text()).toContain(
            'bukan kategori Desil 1–5',
        );
        expect(wrapper.find('[data-testid="selected-residents-list"]').exists()).toBe(false);

        // Coba pilih Budi Santoso yang sudah menerima BLT-DD Tahap I 2026 -> ditolak
        wrapper.vm.handleSelectResident({
            id: 201,
            nik: '7203081205900001',
            nama_lengkap: 'Budi Santoso',
            nomor_kk: '7203080101200001',
            status_kesejahteraan: 'dtsen_desil_1',
        });
        await flushPromises();
        expect(wrapper.find('[data-testid="resident-selection-error"]').text()).toContain(
            'sudah terdaftar menerima bantuan',
        );

        // Pilih 2 warga Desil 1-5 yang memenuhi syarat -> keduanya masuk ke daftar penerima
        wrapper.vm.handleSelectResident({
            id: 302,
            nik: '7203081205900302',
            nama_lengkap: 'Aminah Desil Dua',
            nomor_kk: '7203080101200302',
            dusun: 'Dusun I',
            status_kesejahteraan: 'dtsen_desil_2',
        });
        wrapper.vm.handleSelectResident({
            id: 303,
            nik: '7203081205900303',
            nama_lengkap: 'Rahmat Desil Lima',
            nomor_kk: '7203080101200303',
            dusun: 'Dusun II',
            status_kesejahteraan: 'dtsen_desil_5',
        });
        await flushPromises();

        expect(wrapper.find('[data-testid="selected-residents-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="selected-resident-item-302"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="selected-resident-item-303"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="btn-submit-bansos"]').text()).toContain(
            'Simpan 2 Penerima',
        );
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

    it('KegiatanIndex membuka modal terpadu untuk Buat dan Edit Dokumentasi Kegiatan tanpa berpindah halaman', async () => {
        const postSpy = vi.spyOn(router, 'post').mockImplementation(() => {});
        const sampleKegiatan = [
            {
                id: 15,
                judul: 'Penyaluran BLT-DD Tahap I 2026',
                slug: 'penyaluran-blt-dd-tahap-i-2026',
                kategori: 'penyaluran_bansos',
                kategori_label: 'Penyaluran Bantuan Sosial',
                tanggal_kegiatan: '2026-03-20',
                lokasi_dusun: 'Dusun I',
                uraian: 'Penyaluran langsung kepada 30 KPM di kantor desa.',
                status_publikasi: 'published',
                status_publikasi_label: 'Published',
                sampul_url: '/storage/kegiatan/2026/sampul.webp',
                jumlah_foto: 1,
                foto: [
                    { id: 101, foto_path: 'kegiatan/2026/sampul.webp', foto_url: '/storage/kegiatan/2026/sampul.webp', urutan: 1 },
                ],
            },
        ];

        const wrapper = mount(SosialKegiatanIndex, {
            props: {
                kegiatan: sampleKegiatan,
                daftar_dusun: ['Dusun I', 'Dusun II', 'Dusun III'],
            },
        });

        // 1. Awalnya modal tertutup
        expect(wrapper.find('[data-testid="form-kegiatan-desa"]').exists()).toBe(false);

        // 2. Klik tombol Buat Dokumentasi Kegiatan -> modal terbuka dalam mode Tambah
        await wrapper.find('[data-testid="btn-buat-kegiatan"]').trigger('click');
        expect(wrapper.find('[data-testid="form-kegiatan-desa"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-judul-kegiatan"]').element.value).toBe('');

        // Isi form dan simpan draft
        await wrapper.find('[data-testid="input-judul-kegiatan"]').setValue('Posyandu Melati Dusun II');
        await wrapper.find('[data-testid="textarea-uraian-kegiatan"]').setValue('Pemeriksaan balita dan lansia.');
        await wrapper.find('[data-testid="btn-simpan-draft"]').trigger('click');

        expect(postSpy).toHaveBeenCalledTimes(1);
        expect(postSpy.mock.calls[0][0]).toBe('/panel/sosial/kegiatan');
        expect(postSpy.mock.calls[0][1]).toMatchObject({
            judul: 'Posyandu Melati Dusun II',
            status_publikasi: 'draft',
        });

        // 3. Klik tombol Edit pada baris kegiatan -> modal terbuka dalam mode Edit terisi data
        await wrapper.find('[data-testid="btn-edit-kegiatan-15"]').trigger('click');
        expect(wrapper.find('[data-testid="form-kegiatan-desa"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="input-judul-kegiatan"]').element.value).toBe('Penyaluran BLT-DD Tahap I 2026');
        expect(wrapper.find('[data-testid="input-lokasi-dusun"]').element.value).toBe('Dusun I');
        expect(wrapper.find('[data-testid="photo-counter"]').text()).toBe('1 / 5');
        expect(wrapper.find('[data-testid="badge-sampul-utama"]').exists()).toBe(true);
    });
});
