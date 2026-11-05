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

    it('switches between Data Penduduk and Kartu Keluarga tabs, opens SlideOverDrawer with KK members & Kepala Keluarga reminder, and opens KK/Penduduk modals with DusunCombobox', async () => {
        const page = usePage();
        page.props.auth.user = {
            id: 1,
            name: 'Operator Desa',
            role: 'operator',
        };

        const wrapper = mount(KependudukanIndex, {
            props: createSampleProps(),
        });

        // 1. Default berada di Tab Data Penduduk
        expect(wrapper.find('[data-testid="tab-panel-penduduk"]').exists()).toBe(
            true,
        );
        expect(
            wrapper
                .find('[data-testid="callout-kepala-keluarga-reminder"]')
                .exists(),
        ).toBe(true);

        // 2. Klik nama warga untuk membuka SlideOverDrawer detail warga & anggota 1 KK
        await wrapper
            .find('[data-testid="open-penduduk-drawer-101"]')
            .trigger('click');

        expect(wrapper.find('[data-testid="slide-over-drawer"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="drawer-kk-warning"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-testid="drawer-kk-members"]').text(),
        ).toContain('7203080101260001');
        expect(
            wrapper.find('[data-testid="drawer-kk-members"]').text(),
        ).toContain('Siti Aminah');

        // Tutup drawer
        await wrapper.find('[data-testid="drawer-close-btn"]').trigger('click');
        expect(wrapper.find('[data-testid="slide-over-drawer"]').exists()).toBe(
            false,
        );

        // 3. Pindah ke Tab Kartu Keluarga
        await wrapper
            .find('[data-testid="contextual-tab-kk"]')
            .trigger('click');

        expect(wrapper.find('[data-testid="tab-panel-kk"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-testid="badge-kk-warning"]').exists()).toBe(
            true,
        );

        // Klik nomor KK untuk membuka SlideOverDrawer anggota KK dari Tab KK
        await wrapper
            .find('[data-testid="open-kk-drawer-10"]')
            .trigger('click');
        expect(wrapper.find('[data-testid="slide-over-drawer"]').exists()).toBe(
            true,
        );
        expect(
            wrapper.find('[data-testid="drawer-kk-members"]').text(),
        ).toContain('Hasanuddin');
        await wrapper.find('[data-testid="drawer-close-btn"]').trigger('click');

        // 4. Buka Modal Tambah KK (memuat DusunCombobox)
        await wrapper
            .find('[data-testid="btn-open-create-kk"]')
            .trigger('click');
        expect(wrapper.find('[data-testid="form-kk"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-testid="dusun-combobox-input"]').exists(),
        ).toBe(true);
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
                berhasil: 3,
                ditambahkan: 2,
                diperbarui: 1,
                dipulihkan: 0,
                gagal: 2,
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

        // Buka menu aksi baris pertama (MoreHorizontal) -> klik Mutasi / Reaktivasi
        await wrapper.find('[data-testid="row-more-btn-0"]').trigger('click');
        await wrapper
            .find('[data-testid="row-action-mutasi-0"]')
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

        // Uji klik Hapus Penduduk -> wajib memicu dialog konfirmasi global useConfirm()
        await wrapper.find('[data-testid="row-more-btn-0"]').trigger('click');
        await wrapper
            .find('[data-testid="row-action-hapus-0"]')
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

    it('hides all CRUD, Import, Export, and row mutation/delete buttons for kepala_desa (Read-Only)', () => {
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

        // Pada baris tabel, tombol Lihat Detail tetap tampil namun tombol Edit/Mutasi/Hapus disembunyikan
        expect(
            wrapper.find('[data-testid="row-action-detail-0"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-testid="row-action-edit-0"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-testid="row-more-btn-0"]').exists()).toBe(
            false,
        );
    });
});
