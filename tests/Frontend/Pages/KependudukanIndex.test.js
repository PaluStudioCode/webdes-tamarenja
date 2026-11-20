import { beforeEach, describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import KependudukanIndex from '@/Pages/Kependudukan/Index.vue';
import { useConfirm } from '@/Composables/useConfirm';

function createSampleProps() {
    return {
        tab: 'penduduk',
        filters: {
            search: '',
            dusun: '',
            status_kependudukan: '',
            status_kesejahteraan: '',
            tab: 'penduduk',
        },
        ringkasan: {
            total_penduduk_aktif: 2,
            total_kk_aktif: 1,
            total_kk: 1,
            total_prioritas_dtsen_aktif: 1,
            total_mutasi: 1,
            jumlah_kk_perlu_update_kepala: 1,
        },
        daftar_dusun: ['Dusun I', 'Dusun II', 'Dusun III'],
        kartu_keluarga_options: [
            {
                id: 10,
                nomor_kk: '7203080101260001',
                dusun: 'Dusun I',
                rt: '001',
                rw: '001',
                label: '7203080101260001 — Hasanuddin (Dusun I)',
            },
        ],
        penduduk: {
            data: [
                {
                    id: 101,
                    kartu_keluarga_id: 10,
                    nik: '7203081205850001',
                    nama_lengkap: 'Hasanuddin',
                    tempat_lahir: 'Donggala',
                    tanggal_lahir: '1985-05-12',
                    jenis_kelamin: 'L',
                    agama: 'Islam',
                    pendidikan: 'SLTA/Sederajat',
                    pekerjaan: 'Petani',
                    status_perkawinan: 'kawin',
                    hubungan_keluarga: 'kepala_keluarga',
                    status_kesejahteraan: 'dtsen_desil_1',
                    status_kependudukan: 'pindah_keluar',
                    tanggal_mutasi: '2026-03-10',
                    catatan_mutasi: 'Pindah ke Palu',
                    has_terbit_surat_kematian: false,
                    nomor_kk: '7203080101260001',
                    dusun: 'Dusun I',
                    rt: '001',
                    rw: '001',
                    alamat: 'Jl. Trans Sulawesi No. 1',
                    perlu_update_kepala_keluarga: true,
                    peringatan_kepala_keluarga:
                        'Pengingat: Kepala Keluarga (Hasanuddin) pada KK 7203080101260001 kini berstatus Pindah Keluar, sementara masih ada anggota keluarga aktif.',
                    kartu_keluarga: {
                        id: 10,
                        nomor_kk: '7203080101260001',
                        dusun: 'Dusun I',
                        rt: '001',
                        rw: '001',
                        alamat: 'Jl. Trans Sulawesi No. 1',
                        perlu_update_kepala_keluarga: true,
                        peringatan_kepala_keluarga:
                            'Pengingat: Kepala Keluarga (Hasanuddin) pada KK 7203080101260001 kini berstatus Pindah Keluar, sementara masih ada anggota keluarga aktif.',
                        anggota: [
                            {
                                id: 101,
                                nik: '7203081205850001',
                                nama_lengkap: 'Hasanuddin',
                                hubungan_keluarga: 'kepala_keluarga',
                                tanggal_lahir: '1985-05-12',
                                status_kependudukan: 'pindah_keluar',
                            },
                            {
                                id: 102,
                                nik: '7203085508880002',
                                nama_lengkap: 'Siti Aminah',
                                hubungan_keluarga: 'istri',
                                tanggal_lahir: '1988-08-15',
                                status_kependudukan: 'aktif',
                            },
                        ],
                    },
                },
                {
                    id: 102,
                    kartu_keluarga_id: 10,
                    nik: '7203085508880002',
                    nama_lengkap: 'Siti Aminah',
                    tempat_lahir: 'Palu',
                    tanggal_lahir: '1988-08-15',
                    jenis_kelamin: 'P',
                    agama: 'Islam',
                    pendidikan: 'SLTA/Sederajat',
                    pekerjaan: 'IRT',
                    status_perkawinan: 'kawin',
                    hubungan_keluarga: 'istri',
                    status_kesejahteraan: 'dtsen_desil_1',
                    status_kependudukan: 'aktif',
                    has_terbit_surat_kematian: false,
                    nomor_kk: '7203080101260001',
                    dusun: 'Dusun I',
                    rt: '001',
                    rw: '001',
                    alamat: 'Jl. Trans Sulawesi No. 1',
                    perlu_update_kepala_keluarga: true,
                    peringatan_kepala_keluarga:
                        'Pengingat: Kepala Keluarga (Hasanuddin) pada KK 7203080101260001 kini berstatus Pindah Keluar, sementara masih ada anggota keluarga aktif.',
                },
            ],
            total: 2,
            from: 1,
            to: 2,
            current_page: 1,
            last_page: 1,
        },
        kartu_keluarga: {
            data: [
                {
                    id: 10,
                    nomor_kk: '7203080101260001',
                    dusun: 'Dusun I',
                    rt: '001',
                    rw: '001',
                    alamat: 'Jl. Trans Sulawesi No. 1',
                    kepala_keluarga: {
                        id: 101,
                        nik: '7203081205850001',
                        nama_lengkap: 'Hasanuddin',
                        status_kependudukan: 'pindah_keluar',
                    },
                    jumlah_anggota: 2,
                    jumlah_anggota_aktif: 1,
                    perlu_update_kepala_keluarga: true,
                    peringatan_kepala_keluarga:
                        'Pengingat: Kepala Keluarga (Hasanuddin) pada KK 7203080101260001 kini berstatus Pindah Keluar, sementara masih ada anggota keluarga aktif.',
                    anggota: [
                        {
                            id: 101,
                            kartu_keluarga_id: 10,
                            nik: '7203081205850001',
                            nama_lengkap: 'Hasanuddin',
                            tempat_lahir: 'Donggala',
                            tanggal_lahir: '1985-05-12',
                            jenis_kelamin: 'L',
                            agama: 'Islam',
                            pendidikan: 'SLTA/Sederajat',
                            pekerjaan: 'Petani',
                            status_perkawinan: 'kawin',
                            hubungan_keluarga: 'kepala_keluarga',
                            status_kesejahteraan: 'dtsen_desil_1',
                            status_kependudukan: 'pindah_keluar',
                            tanggal_mutasi: '2026-03-10',
                            catatan_mutasi: 'Pindah ke Palu',
                            has_terbit_surat_kematian: false,
                            nomor_kk: '7203080101260001',
                            dusun: 'Dusun I',
                            rt: '001',
                            rw: '001',
                            alamat: 'Jl. Trans Sulawesi No. 1',
                            perlu_update_kepala_keluarga: true,
                            peringatan_kepala_keluarga:
                                'Pengingat: Kepala Keluarga (Hasanuddin) pada KK 7203080101260001 kini berstatus Pindah Keluar, sementara masih ada anggota keluarga aktif.',
                        },
                        {
                            id: 102,
                            kartu_keluarga_id: 10,
                            nik: '7203085508880002',
                            nama_lengkap: 'Siti Aminah',
                            tempat_lahir: 'Palu',
                            tanggal_lahir: '1988-08-15',
                            jenis_kelamin: 'P',
                            agama: 'Islam',
                            pendidikan: 'SLTA/Sederajat',
                            pekerjaan: 'IRT',
                            status_perkawinan: 'kawin',
                            hubungan_keluarga: 'istri',
                            status_kesejahteraan: 'dtsen_desil_1',
                            status_kependudukan: 'aktif',
                            has_terbit_surat_kematian: false,
                            nomor_kk: '7203080101260001',
                            dusun: 'Dusun I',
                            rt: '001',
                            rw: '001',
                            alamat: 'Jl. Trans Sulawesi No. 1',
                            perlu_update_kepala_keluarga: true,
                            peringatan_kepala_keluarga:
                                'Pengingat: Kepala Keluarga (Hasanuddin) pada KK 7203080101260001 kini berstatus Pindah Keluar, sementara masih ada anggota keluarga aktif.',
                        },
                    ],
                },
            ],
            total: 1,
            from: 1,
            to: 1,
            current_page: 1,
            last_page: 1,
        },
    };
}

describe('Kependudukan/Index.vue Unified Interface', () => {
    beforeEach(() => {
        useConfirm().reset();
    });

    it('renders unified KK Master-Detail table with expandable family members, opens SlideOverDrawer with KK members & Kepala Keluarga reminder, and opens KK/Penduduk modals with DusunCombobox', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        // 1. Merender 1 Tabel Terpadu berbasis Kartu Keluarga (tanpa duplikasi tab)
        expect(wrapper.find('[data-testid="table-kk-terpadu"]').exists()).toBe(
            true,
        );
        expect(
            wrapper
                .find('[data-testid="callout-kepala-keluarga-reminder"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.find('[data-testid="badge-kk-warning"]').exists()).toBe(
            true,
        );

        // Memverifikasi StatCard Total Kartu Keluarga dengan subtext dinamis
        expect(wrapper.text()).toContain('Total Kartu Keluarga');
        expect(wrapper.text()).toContain('Seluruh KK berdomisili aktif di desa');

        // 2. Klik tombol Detail pada baris KK untuk membuka Modal Detail KK (berisi profil aset & daftar anggota keluarga)
        expect(
            wrapper.find('[data-testid="modal-detail-kk"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="subtable-anggota-kk-10"]').exists(),
        ).toBe(false);

        await wrapper
            .find('[data-testid="row-action-detail-0"]')
            .trigger('click');

        expect(
            wrapper.find('[data-testid="modal-detail-kk"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="kk-assets-summary-10"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="subtable-anggota-kk-10"]').exists(),
        ).toBe(true);
        const subtableText = wrapper
            .find('[data-testid="subtable-anggota-kk-10"]')
            .text();
        expect(subtableText).toContain('Daftar Anggota Keluarga');
        expect(subtableText).not.toContain('KK No.');
        expect(subtableText).toContain('Hasanuddin');
        expect(subtableText).toContain('Siti Aminah');

        // 3. Sub-tabel anggota di dalam Modal Detail KK menampilkan seluruh data individu secara langsung tanpa SlideOverDrawer
        expect(
            wrapper.find('[data-testid="member-action-detail-101"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-testid="slide-over-drawer"]').exists()).toBe(
            false,
        );

        // 4. Tidak ada duplikasi tombol Tambah Penduduk di Header atas
        // Desktop (>= md): aksi ditampilkan langsung inline tanpa harus klik titik tiga
        // Mobile (< md): aksi dibungkus rapi dalam tombol titik tiga (MoreHorizontal)
        expect(
            wrapper.find('[data-testid="btn-open-create-penduduk"]').exists(),
        ).toBe(false);

        // Aksi baris KK di Desktop (hanya Detail & Hapus KK, karena + Anggota dan Ubah KK berada di dalam Modal Detail KK)
        expect(
            wrapper.find('[data-testid="row-action-detail-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="row-action-tambah_anggota-0"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="row-action-edit-0"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="row-action-hapus-0"]').exists(),
        ).toBe(true);

        // Tombol Ubah KK dan + Anggota tersedia di dalam Modal Detail KK
        expect(
            wrapper.find('[data-testid="modal-detail-kk-btn-edit"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-testid="modal-detail-kk-btn-add-member"]')
                .exists(),
        ).toBe(true);

        // Aksi baris KK di Mobile (tombol titik tiga membuka dropdown menu)
        expect(wrapper.find('[data-testid="row-more-btn-0"]').exists()).toBe(
            true,
        );
        await wrapper.find('[data-testid="row-more-btn-0"]').trigger('click');
        expect(
            wrapper.find('[data-testid="row-action-menu-detail-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="row-action-menu-hapus-0"]').exists(),
        ).toBe(true);

        // Sub-tabel anggota: tombol aksi Desktop inline dan Mobile titik tiga
        expect(
            wrapper.find('[data-testid="member-action-edit-101"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="member-action-mutasi-101"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="member-action-hapus-101"]').exists(),
        ).toBe(true);

        expect(
            wrapper.find('[data-testid="member-more-btn-101"]').exists(),
        ).toBe(true);
        await wrapper.find('[data-testid="member-more-btn-101"]').trigger('click');
        expect(
            wrapper.find('[data-testid="member-action-menu-edit-101"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="member-action-menu-mutasi-101"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="member-action-menu-hapus-101"]').exists(),
        ).toBe(true);

        // 5. Buka Modal Tambah KK (memuat DusunCombobox & input Daftar Anggota Keluarga dinamis)
        await wrapper
            .find('[data-testid="btn-open-create-kk"]')
            .trigger('click');
        expect(wrapper.find('[data-testid="form-kk"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-testid="dusun-combobox-input"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="kk-members-repeater"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-testid="kk-member-card-0"]').exists()).toBe(
            true,
        );

        // Tambah baris anggota ke-2 di dalam Modal Tambah KK
        await wrapper
            .find('[data-testid="btn-add-kk-member-row"]')
            .trigger('click');
        expect(wrapper.find('[data-testid="kk-member-card-1"]').exists()).toBe(
            true,
        );
    });

    it('renders import Excel row error summary table when flash.import_errors contains failed rows', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };
        page.props.flash = {
            notification: null,
            import_summary: {
                is_preview: true,
                berhasil: 3,
                ditambahkan: 2,
                diperbarui: 1,
                dipulihkan: 0,
                gagal: 2,
                preview_rows: [
                    {
                        baris: 2,
                        nomor_kk: '7203080101260001',
                        dusun: 'Dusun I',
                        nik: '7203081205850001',
                        nama_lengkap: 'Warga Baru Valid',
                        hubungan_keluarga: 'kepala_keluarga',
                        status_kesejahteraan: 'dtsen_desil_1',
                        status_kependudukan: 'aktif',
                        aksi: 'ditambahkan',
                        pesan: null,
                    },
                    {
                        baris: 5,
                        nomor_kk: '7203080101260001',
                        dusun: 'Dusun I',
                        nik: '7203089999',
                        nama_lengkap: 'Warga NIK Pendek',
                        hubungan_keluarga: 'anak',
                        status_kesejahteraan: 'non_prioritas',
                        status_kependudukan: 'aktif',
                        aksi: 'gagal',
                        pesan: 'NIK harus tepat 16 digit angka.',
                    },
                ],
            },
            import_errors: [
                {
                    baris: 5,
                    nik: '7203089999',
                    nama_lengkap: 'Warga NIK Pendek',
                    pesan: 'NIK harus tepat 16 digit angka.',
                },
                {
                    baris: 8,
                    nik: '7203081205900088',
                    nama_lengkap: '-',
                    pesan: 'Nama lengkap wajib diisi.',
                },
            ],
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        // 4 Kartu ringkasan hasil pratinjau dan tabel pratinjau dirender
        expect(
            wrapper.find('[data-testid="import-summary-cards"]').exists(),
        ).toBe(true);
        const previewTable = wrapper.find(
            '[data-testid="import-preview-table"]',
        );
        expect(previewTable.exists()).toBe(true);
        expect(previewTable.text()).toContain('Warga Baru Valid');
        expect(previewTable.text()).toContain('Baru Ditambahkan');

        // Modal Import Excel terbuka otomatis dan merender tabel error per baris
        const errorTable = wrapper.find(
            '[data-testid="import-error-summary-table"]',
        );
        expect(errorTable.exists()).toBe(true);
        expect(errorTable.text()).toContain('#5');
        expect(errorTable.text()).toContain('NIK harus tepat 16 digit angka.');
        expect(errorTable.text()).toContain('#8');
        expect(errorTable.text()).toContain('Nama lengkap wajib diisi.');
    });

    it('triggers useConfirm() when mutating status or deleting resident/KK', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        // Buka Modal Detail KK terlebih dahulu -> klik Mutasi pada anggota pertama (id: 101)
        await wrapper
            .find('[data-testid="row-action-detail-0"]')
            .trigger('click');
        await wrapper
            .find('[data-testid="member-action-mutasi-101"]')
            .trigger('click');

        expect(wrapper.find('[data-testid="form-mutasi"]').exists()).toBe(true);

        // Submit form mutasi -> wajib memicu dialog konfirmasi global useConfirm()
        await wrapper
            .find('[data-testid="form-mutasi"]')
            .trigger('submit.prevent');
        await nextTick();

        expect(useConfirm().state.isOpen).toBe(true);
        useConfirm().cancel();
        await nextTick();

        // Uji klik Hapus Penduduk pada sub-tabel anggota -> wajib memicu dialog konfirmasi global useConfirm()
        await wrapper
            .find('[data-testid="member-action-hapus-101"]')
            .trigger('click');
        await nextTick();

        expect(useConfirm().state.isOpen).toBe(true);
        expect(useConfirm().state.title).toContain('Hapus Data Penduduk?');

        useConfirm().accept();
        await nextTick();

        expect(router.delete).toHaveBeenCalledWith(
            '/panel/kependudukan/penduduk/101',
            { preserveScroll: true },
        );
    });

    it('hides all CRUD, Import, Export, and row mutation/delete buttons for kepala_desa (Read-Only)', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 2,
            name: 'Kepala Desa',
            role: 'kepala_desa',
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        // Tombol header CRUD & Import/Export disembunyikan
        expect(wrapper.find('[data-testid="btn-export-excel"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.find('[data-testid="btn-open-import-modal"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="btn-open-create-kk"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="btn-open-create-penduduk"]').exists(),
        ).toBe(false);

        // Pada baris tabel KK, tombol Detail tetap tampil untuk kepala_desa, tetapi Tambah Anggota/Edit/Hapus disembunyikan
        expect(
            wrapper.find('[data-testid="row-action-detail-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="row-action-tambah_anggota-0"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-testid="row-action-edit-0"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-testid="row-action-hapus-0"]').exists()).toBe(
            false,
        );

        // Saat Modal Detail KK dibuka, seluruh tombol aksi anggota disembunyikan untuk kepala_desa (Read-Only)
        await wrapper
            .find('[data-testid="row-action-detail-0"]')
            .trigger('click');
        expect(
            wrapper.find('[data-testid="member-action-detail-101"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="member-action-edit-101"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="member-action-mutasi-101"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="member-action-hapus-101"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="member-more-btn-101"]').exists(),
        ).toBe(false);
    });

    it('keeps main KK table filter focused on KK-level filters only (Dusun & DTSEN) without individual member search badges', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const props = createSampleProps();
        const wrapper = mount(KependudukanIndex, { props });

        // Tabel utama KK hanya memiliki filter Dusun dan Kategori DTSEN (tanpa filter Status Warga individu)
        expect(
            wrapper.find('[data-testid="filter-select-dusun"]').exists(),
        ).toBe(true);
        const dtsenFilterSelect = wrapper.get(
            '[data-testid="filter-select-status_kesejahteraan"]',
        );
        expect(dtsenFilterSelect.text()).toContain('Desil 1');
        expect(dtsenFilterSelect.text()).toContain('Desil 2');
        expect(dtsenFilterSelect.text()).toContain('Desil 3');
        expect(dtsenFilterSelect.text()).toContain('Desil 4');
        expect(dtsenFilterSelect.text()).toContain('Desil 5');
        expect(dtsenFilterSelect.text()).toContain('Desil 6-10');
        expect(
            wrapper
                .find('[data-testid="filter-select-status_kependudukan"]')
                .exists(),
        ).toBe(false);

        // Tidak ada badge pencocokan individu di tabel utama KK
        expect(
            wrapper
                .find('[data-testid="badge-kk-matched-members-10"]')
                .exists(),
        ).toBe(false);
    });

    it('opens Cari Individu modal, supports filter options & realtime search, and highlights the individual row in Modal Detail KK when Lihat KK is clicked', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        expect(
            wrapper.find('[data-testid="btn-open-search-individu"]').exists(),
        ).toBe(true);

        await wrapper
            .find('[data-testid="btn-open-search-individu"]')
            .trigger('click');

        expect(
            wrapper.find('[data-testid="modal-search-individu"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="search-individu-filters"]').exists(),
        ).toBe(true);

        // 1. Uji filter option tanpa mengetik kata kunci (misal: Status Warga = Pindah Keluar -> hanya Hasanuddin id 101)
        await wrapper
            .get('[data-testid="search-individu-filter-status"]')
            .setValue('pindah_keluar');

        expect(
            wrapper.find('[data-testid="search-individu-results"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="search-individu-item-101"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="search-individu-item-102"]').exists(),
        ).toBe(false);

        // Reset filter pada modal pencarian individu
        await wrapper
            .get('[data-testid="search-individu-reset-btn"]')
            .trigger('click');
        expect(
            wrapper.find('[data-testid="search-individu-results"]').exists(),
        ).toBe(false);

        // 2. Uji kombinasi filter Jenis Kelamin = P + pencarian nama 'Siti'
        await wrapper
            .get('[data-testid="search-individu-filter-jk"]')
            .setValue('P');
        const searchInput = wrapper.get('[data-testid="input-search-individu"]');
        await searchInput.setValue('Siti Aminah');

        expect(
            wrapper.find('[data-testid="search-individu-results"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="search-individu-item-102"]').exists(),
        ).toBe(true);

        // Klik tombol Lihat KK pada hasil pencarian individu
        await wrapper
            .find('[data-testid="btn-lihat-kk-individu-102"]')
            .trigger('click');

        // Modal pencarian individu tertutup dan Modal Detail KK langsung terbuka dengan highlight pada individu tersebut
        expect(
            wrapper.find('[data-testid="modal-search-individu"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="modal-detail-kk"]').exists(),
        ).toBe(true);

        const matchedRow = wrapper.get('[data-testid="member-row-102"]');
        const unmatchedRow = wrapper.get('[data-testid="member-row-101"]');

        expect(matchedRow.classes()).toContain('bg-amber-50/90');
        expect(matchedRow.find('td').classes()).toContain('border-l-4');
        expect(matchedRow.find('td').classes()).toContain('border-l-amber-500');
        expect(matchedRow.text()).toContain('Cocok');

        expect(unmatchedRow.classes()).not.toContain('bg-amber-50/90');
        expect(unmatchedRow.text()).not.toContain('Cocok');
    });

    it('supports extended hubungan keluarga options (Cucu, Orang Tua, Kakek, Nenek, Famili Lain, Lainnya) and conditionally displays KIP/PIP & Kelas Sekolah for unmarried Anak/Cucu/Famili Lain/Lainnya', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        // Buka Modal Detail KK lalu klik + Anggota
        await wrapper
            .find('[data-testid="row-action-detail-0"]')
            .trigger('click');
        await wrapper
            .find('[data-testid="modal-detail-kk-btn-add-member"]')
            .trigger('click');

        // Pastikan opsi hubungan keluarga lengkap tersedia (termasuk Cucu, Orang Tua, Kakek, Nenek, Famili Lain, Lainnya)
        const hubunganSelectText = wrapper
            .get('[data-testid="select-hubungan"]')
            .text();
        expect(hubunganSelectText).toContain('Kepala Keluarga');
        expect(hubunganSelectText).toContain('Istri');
        expect(hubunganSelectText).toContain('Anak');
        expect(hubunganSelectText).toContain('Cucu');
        expect(hubunganSelectText).toContain('Orang Tua');
        expect(hubunganSelectText).toContain('Kakek');
        expect(hubunganSelectText).toContain('Nenek');
        expect(hubunganSelectText).toContain('Famili Lain');
        expect(hubunganSelectText).toContain('Lainnya');

        // Karena KK sudah memiliki Kepala Keluarga, default form adalah hubungan = 'anak' & status_perkawinan = 'belum_kawin'
        expect(
            wrapper.find('[data-testid="select-peserta-kip-pip"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="input-kelas-sekolah"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="input-pendidikan"]').exists(),
        ).toBe(true);

        // Saat Kelas Sekolah diisi (misal: '2 SMP'), input Pendidikan Terakhir otomatis disembunyikan & tercatat otomatis
        await wrapper
            .get('[data-testid="input-kelas-sekolah"]')
            .setValue('2 SMP');
        expect(
            wrapper.find('[data-testid="input-pendidikan"]').exists(),
        ).toBe(false);
        expect(
            wrapper
                .get('[data-testid="note-auto-pendidikan-penduduk"]')
                .text(),
        ).toContain('Tamat SD/Sederajat');

        // Jika hubungan diubah menjadi 'cucu' (dan masih 'belum_kawin'), field KIP/PIP & Kelas Sekolah tetap tampil
        await wrapper.get('[data-testid="select-hubungan"]').setValue('cucu');
        expect(
            wrapper.find('[data-testid="select-peserta-kip-pip"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="input-kelas-sekolah"]').exists(),
        ).toBe(true);

        // Jika hubungan diubah menjadi 'kakek' (atau 'nenek'/'orang_tua'/'istri'), field KIP/PIP & Kelas Sekolah otomatis disembunyikan dan Pendidikan Terakhir tampil kembali
        await wrapper.get('[data-testid="select-hubungan"]').setValue('kakek');
        expect(
            wrapper.find('[data-testid="select-peserta-kip-pip"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="input-kelas-sekolah"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="input-pendidikan"]').exists(),
        ).toBe(true);

        // Uji juga pada Modal Tambah KK (saat hubungan anggota diubah menjadi Anak/Cucu, status_perkawinan otomatis menjadi belum_kawin sehingga input KIP/PIP & Kelas Sekolah langsung tampil)
        await wrapper
            .find('[data-testid="btn-open-create-kk"]')
            .trigger('click');
        expect(
            wrapper.find('[data-testid="select-kk-member-kip-0"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="input-kk-member-kelas-0"]').exists(),
        ).toBe(false);

        await wrapper
            .get('[data-testid="select-kk-member-hubungan-0"]')
            .setValue('anak');
        expect(
            wrapper.find('[data-testid="select-kk-member-kip-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="input-kk-member-kelas-0"]').exists(),
        ).toBe(true);
    });

    it('renders expanded 6xl Detail KK modal with subtitle, domisili, explicit asset labels, 10 structured member columns, and auto-calculated age', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const props = createSampleProps();
        const kk = props.kartu_keluarga.data[0];
        kk.status_kepemilikan_rumah = 'Milik Sendiri';
        kk.kondisi_rumah = 'Semi Permanen';
        kk.jenis_listrik = 'PLN 900W';
        kk.kamar_mandi = 'Ada';
        kk.penghasilan_bulanan = 'Rp 1.500.000';
        kk.kepemilikan_usaha = 'Warung Kelontong';
        kk.kepemilikan_hewan_ternak = '2 Ekor Sapi';
        kk.kepemilikan_kendaraan_elektronik = '1 Motor, 1 TV';
        kk.kepemilikan_shml = '1';
        kk.kepemilikan_tanah_bidang = '2';
        kk.kepemilikan_tanah_sawah = '1';
        kk.tanaman = 'Kelapa & Kakao';

        kk.anggota[0].suku = 'Kaili';
        kk.anggota[0].jenis_bpjs = 'Pemerintah';
        kk.anggota.push({
            id: 103,
            kartu_keluarga_id: 10,
            nik: '7203081506140003',
            nama_lengkap: 'Ahmad Fauzi',
            tempat_lahir: 'Donggala',
            tanggal_lahir: '2014-06-15',
            jenis_kelamin: 'L',
            agama: 'Islam',
            suku: 'Kaili',
            kewarganegaraan: 'WNI',
            pendidikan: 'Belum Tamat SD/Sederajat',
            kelas_sekolah: '5 SD',
            peserta_kip_pip: 'PIP',
            pekerjaan: 'Pelajar',
            status_perkawinan: 'belum_kawin',
            hubungan_keluarga: 'anak',
            jenis_bpjs: 'Pemerintah',
            status_kesejahteraan: 'dtsen_desil_1',
            status_kependudukan: 'aktif',
        });
        kk.jumlah_anggota = 3;
        kk.jumlah_anggota_aktif = 2;

        const wrapper = mount(KependudukanIndex, { props });

        await wrapper
            .find('[data-testid="row-action-detail-0"]')
            .trigger('click');

        // 1. BaseModal menggunakan ukuran 6xl dan menampilkan subtitle domisili
        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.classes()).toContain('max-w-6xl');
        const subtitleEl = wrapper.get('[data-testid="base-modal-subtitle"]');
        expect(subtitleEl.text()).toContain('Dusun I');
        expect(subtitleEl.text()).toContain('RT 001 / RW 001');
        expect(subtitleEl.text()).toContain('Jl. Trans Sulawesi No. 1');

        // 2. Baris ringkasan KK menampilkan Domisili & Alamat secara eksplisit
        const domisiliSummary = wrapper.get(
            '[data-testid="kk-summary-domisili"]',
        );
        expect(domisiliSummary.text()).toContain('Domisili & Alamat');
        expect(domisiliSummary.text()).toContain('Dusun I');
        expect(domisiliSummary.text()).toContain('Jl. Trans Sulawesi No. 1');

        // 3. Kartu Profil Hunian, Sosial-Ekonomi & Aset menampilkan seluruh label 12 field secara jelas
        const assetsSummaryText = wrapper
            .get('[data-testid="kk-assets-summary-10"]')
            .text();
        expect(assetsSummaryText).toContain('Kepemilikan Rumah:');
        expect(assetsSummaryText).toContain('Milik Sendiri');
        expect(assetsSummaryText).toContain('Kondisi Bangunan:');
        expect(assetsSummaryText).toContain('Semi Permanen');
        expect(assetsSummaryText).toContain('Listrik:');
        expect(assetsSummaryText).toContain('PLN 900W');
        expect(assetsSummaryText).toContain('Kamar Mandi:');
        expect(assetsSummaryText).toContain('Ada');
        expect(assetsSummaryText).toContain('Penghasilan / Bulan:');
        expect(assetsSummaryText).toContain('Rp 1.500.000');
        expect(assetsSummaryText).toContain('Kepemilikan Usaha:');
        expect(assetsSummaryText).toContain('Warung Kelontong');
        expect(assetsSummaryText).toContain('Hewan Ternak:');
        expect(assetsSummaryText).toContain('2 Ekor Sapi');
        expect(assetsSummaryText).toContain('Kendaraan / Elektronik:');
        expect(assetsSummaryText).toContain('1 Motor, 1 TV');
        expect(assetsSummaryText).toContain('Sertifikat (SHML):');
        expect(assetsSummaryText).toContain('Tanah Bidang:');
        expect(assetsSummaryText).toContain('Tanah Sawah:');
        expect(assetsSummaryText).toContain('Tanaman:');
        expect(assetsSummaryText).toContain('Kelapa & Kakao');

        // 4. Tabel anggota memiliki 10 kolom informasi terstruktur (+ 1 kolom Aksi)
        const subtable = wrapper.get('[data-testid="subtable-anggota-kk-10"]');
        const headers = subtable.findAll('thead th').map((th) => th.text());
        expect(headers).toEqual([
            'No',
            'Nama & NIK',
            'L/P',
            'Hubungan',
            'TTL & Usia',
            'Status Kawin & Identitas',
            'Pendidikan',
            'Pekerjaan',
            'BPJS & Bantuan',
            'Status Warga',
            'Aksi',
        ]);

        // 5. Verifikasi konten baris anggota ke-1 (Kepala Keluarga mutasi + Tgl mutasi + Suku + Usia + BPJS)
        const row1Text = wrapper.get('[data-testid="member-row-101"]').text();
        expect(row1Text).toContain('1');
        expect(row1Text).toContain('Hasanuddin');
        expect(row1Text).toContain('Laki-laki');
        expect(row1Text).toContain('Kepala Keluarga');
        expect(row1Text).toContain('Usia:');
        expect(row1Text).toContain('Kaili');
        expect(row1Text).toContain('BPJS: Pemerintah');
        expect(row1Text).toContain('Tgl:');

        // 6. Verifikasi konten baris anak sekolah (Ahmad Fauzi: Kelas Sekolah & Bantuan Pendidikan PIP)
        const row3Text = wrapper.get('[data-testid="member-row-103"]').text();
        expect(row3Text).toContain('3');
        expect(row3Text).toContain('Ahmad Fauzi');
        expect(row3Text).toContain('Anak');
        expect(row3Text).toContain('Kelas 5 SD');
        expect(row3Text).toContain('Pendidikan: PIP');
        expect(row3Text).toContain('Pelajar');
    });
});
