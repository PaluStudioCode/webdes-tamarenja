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

        // 2. Klik tombol expand pada baris KK untuk menampilkan sub-tabel anggota keluarga
        expect(
            wrapper.find('[data-testid="subtable-anggota-kk-10"]').exists(),
        ).toBe(false);

        await wrapper
            .find('[data-testid="toggle-expand-kk-10"]')
            .trigger('click');

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

        // 3. Sub-tabel anggota sudah menampilkan seluruh data individu secara langsung tanpa tombol Detail maupun SlideOverDrawer
        expect(
            wrapper.find('[data-testid="member-action-detail-101"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-testid="slide-over-drawer"]').exists()).toBe(
            false,
        );

        // 4. Tidak ada duplikasi tombol Tambah Penduduk di Header atas maupun di dalam sub-tabel;
        // Desktop (>= md): aksi ditampilkan langsung inline tanpa harus klik titik tiga
        // Mobile (< md): aksi dibungkus rapi dalam tombol titik tiga (MoreHorizontal)
        expect(
            wrapper.find('[data-testid="btn-open-create-penduduk"]').exists(),
        ).toBe(false);
        expect(
            wrapper.find('[data-testid="btn-add-member-kk-10"]').exists(),
        ).toBe(false);

        // Aksi baris KK di Desktop (inline buttons)
        expect(
            wrapper.find('[data-testid="row-action-tambah_anggota-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="row-action-edit-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="row-action-hapus-0"]').exists(),
        ).toBe(true);

        // Aksi baris KK di Mobile (tombol titik tiga membuka dropdown menu)
        expect(wrapper.find('[data-testid="row-more-btn-0"]').exists()).toBe(
            true,
        );
        await wrapper.find('[data-testid="row-more-btn-0"]').trigger('click');
        expect(
            wrapper.find('[data-testid="row-action-menu-tambah_anggota-0"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-testid="row-action-menu-edit-0"]').exists(),
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

        // Buka rincian anggota KK terlebih dahulu -> klik Mutasi pada anggota pertama (id: 101)
        await wrapper
            .find('[data-testid="toggle-expand-kk-10"]')
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

        // Pada baris tabel KK, tombol Tambah Anggota/Edit/Hapus disembunyikan untuk kepala_desa
        expect(
            wrapper.find('[data-testid="row-action-tambah_anggota-0"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-testid="row-action-edit-0"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-testid="row-action-hapus-0"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-testid="row-more-btn-0"]').exists()).toBe(
            false,
        );

        // Saat sub-tabel anggota dibuka, seluruh tombol aksi anggota disembunyikan untuk kepala_desa (Read-Only)
        await wrapper
            .find('[data-testid="toggle-expand-kk-10"]')
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

    it('highlights matching family member row clearly with amber highlight and left accent border when search filter is active', () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const props = createSampleProps();
        props.filters.search = 'Siti Aminah';

        const wrapper = mount(KependudukanIndex, { props });

        // Baris KK otomatis terbuka saat ada filter pencarian anggota aktif
        expect(
            wrapper.find('[data-testid="subtable-anggota-kk-10"]').exists(),
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
});
