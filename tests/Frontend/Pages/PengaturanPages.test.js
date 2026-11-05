import { mount } from '@vue/test-utils';
import { router, usePage } from '@inertiajs/vue3';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Backup from '@/Pages/Pengaturan/Backup.vue';
import Ppid from '@/Pages/Pengaturan/Ppid.vue';
import ProfilDesa from '@/Pages/Pengaturan/ProfilDesa.vue';
import Sotk from '@/Pages/Pengaturan/Sotk.vue';

function setupAuthRole(role = 'operator', currentUrl = '/panel/pengaturan/profil-desa') {
    const page = usePage();
    page.url = currentUrl;
    page.props.auth = {
        user: {
            id: 1,
            name: 'Operator Desa',
            email: 'operator@desa.id',
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
    page.props.antrean_surat_count = 0;
    page.props.flash = {};
}

describe('Halaman Pengaturan Sistem (/panel/pengaturan/*)', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
        setupAuthRole('operator', '/panel/pengaturan/profil-desa');
    });

    it('merender navigasi 5 tab sub-prefix Pengaturan dan mengelola array dinamis daftar_dusun & misi pada ProfilDesa.vue', async () => {
        const postSpy = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onFinish?.();
            });

        const wrapper = mount(ProfilDesa, {
            props: {
                profil: {
                    nama_desa: 'Tamarenja',
                    kode_desa_surat: 'DS-TMR',
                    kecamatan: 'Sindue Tobata',
                    kabupaten: 'Donggala',
                    provinsi: 'Sulawesi Tengah',
                    daftar_dusun: ['Dusun I', 'Dusun II'],
                    alamat_kantor: 'Jl. Trans Sulawesi',
                    nama_kepala_desa: 'H. Moh. Ridwan',
                    nama_sekdes: 'Andi Saputra',
                    misi: ['Misi Awal 1'],
                    tahun_anggaran_aktif: 2026,
                },
            },
        });

        expect(wrapper.text()).toContain('Profil & Kop');
        expect(wrapper.text()).toContain('SOTK');
        expect(wrapper.text()).toContain('Dokumen PPID');
        expect(wrapper.text()).toContain('Pengguna');
        expect(wrapper.text()).toContain('Backup Data');

        // Tambah dan hapus baris dusun
        expect(wrapper.findAll('[data-testid^="input-dusun-"]')).toHaveLength(2);
        await wrapper.get('[data-testid="add-dusun-btn"]').trigger('click');
        expect(wrapper.findAll('[data-testid^="input-dusun-"]')).toHaveLength(3);

        await wrapper.get('[data-testid="input-dusun-2"]').setValue('Dusun III Pantai');
        await wrapper.get('[data-testid="remove-dusun-1"]').trigger('click');
        expect(wrapper.findAll('[data-testid^="input-dusun-"]')).toHaveLength(2);

        // Tambah dan isi baris misi
        expect(wrapper.findAll('[data-testid^="input-misi-"]')).toHaveLength(1);
        await wrapper.get('[data-testid="add-misi-btn"]').trigger('click');
        expect(wrapper.findAll('[data-testid^="input-misi-"]')).toHaveLength(2);
        await wrapper.get('[data-testid="input-misi-1"]').setValue('Meningkatkan layanan digital desa');

        // Submit form
        await wrapper.get('[data-testid="save-profil-btn"]').trigger('click');

        expect(postSpy).toHaveBeenCalledTimes(1);
        const [url, payload] = postSpy.mock.calls[0];
        expect(url).toBe('/panel/pengaturan/profil-desa');
        expect(payload._method).toBe('PUT');
        expect(payload.daftar_dusun).toEqual(['Dusun I', 'Dusun III Pantai']);
        expect(payload.misi).toEqual(['Misi Awal 1', 'Meningkatkan layanan digital desa']);
    });

    it('membuka modal CRUD perangkat desa pada Sotk.vue dan mengirim data ke endpoint /panel/pengaturan/sotk', async () => {
        setupAuthRole('operator', '/panel/pengaturan/sotk');
        const postSpy = vi
            .spyOn(router, 'post')
            .mockImplementation((_url, _data, options) => {
                options?.onSuccess?.();
                options?.onFinish?.();
            });

        const wrapper = mount(Sotk, {
            props: {
                perangkatList: {
                    data: [
                        {
                            id: 7,
                            nama_lengkap: 'Andi Saputra, S.AP.',
                            jabatan: 'Sekretaris Desa',
                            kelompok_sotk: 'sekretariat',
                            kelompok_label: 'Sekretariat Desa (Sekdes & Kaur)',
                            nip_niap: '198604122010011008',
                            urutan: 1,
                            is_aktif: true,
                        },
                    ],
                    total: 1,
                    from: 1,
                    to: 1,
                },
            },
        });

        expect(wrapper.text()).toContain('Andi Saputra, S.AP.');
        expect(wrapper.text()).toContain('Sekretaris Desa');

        // Buka modal tambah
        await wrapper.get('[data-testid="add-sotk-btn"]').trigger('click');
        await wrapper.get('[data-testid="sotk-input-nama"]').setValue('Budi Raharjo');
        await wrapper.get('[data-testid="sotk-input-jabatan"]').setValue('Kasi Pemerintahan');
        await wrapper.get('[data-testid="sotk-select-kelompok"]').setValue('pelaksana_teknis');

        await wrapper.get('#sotk-form').trigger('submit.prevent');

        expect(postSpy).toHaveBeenCalledTimes(1);
        expect(postSpy.mock.calls[0][0]).toBe('/panel/pengaturan/sotk');
        expect(postSpy.mock.calls[0][1]).toMatchObject({
            nama_lengkap: 'Budi Raharjo',
            jabatan: 'Kasi Pemerintahan',
            kelompok_sotk: 'pelaksana_teknis',
        });
    });

    it('membuka modal CRUD dokumen resmi pada Ppid.vue dan memicu toggle publikasi', async () => {
        setupAuthRole('operator', '/panel/pengaturan/ppid');
        const patchSpy = vi.spyOn(router, 'patch').mockImplementation(() => {});

        const wrapper = mount(Ppid, {
            props: {
                dokumenList: {
                    data: [
                        {
                            id: 12,
                            judul_dokumen: 'Perdes APBDes Tahun 2026',
                            kategori: 'perdes_apbdes',
                            kategori_label: 'Perdes APBDes',
                            tahun_dokumen: 2026,
                            ukuran_file_kb: 420,
                            jumlah_unduhan: 5,
                            is_publik: true,
                        },
                    ],
                    total: 1,
                    from: 1,
                    to: 1,
                },
            },
        });

        expect(wrapper.text()).toContain('Perdes APBDes Tahun 2026');
        expect(wrapper.text()).toContain('420 KB');

        await wrapper.get('[data-testid="toggle-ppid-btn-12"]').trigger('click');
        expect(patchSpy).toHaveBeenCalledWith(
            '/panel/pengaturan/ppid/12/publikasi',
            {},
            { preserveScroll: true },
        );

        await wrapper.get('[data-testid="edit-ppid-btn-12"]').trigger('click');
        expect(wrapper.get('[data-testid="ppid-input-judul"]').element.value).toBe(
            'Perdes APBDes Tahun 2026',
        );
    });

    it('menguji state loading pada tombol unduh Backup SQL (.sql) dan Export Excel 8 Sheet (.xlsx) di Backup.vue', async () => {
        setupAuthRole('operator', '/panel/pengaturan/backup');
        vi.useFakeTimers();

        const assignMock = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { assign: assignMock },
            writable: true,
        });

        const wrapper = mount(Backup, {
            props: {
                moduleStats: [
                    { sheet: 'Kartu Keluarga', count: 28 },
                    { sheet: 'Penduduk', count: 104 },
                    { sheet: 'Proyek Pembangunan', count: 6 },
                    { sheet: 'APBDes', count: 2 },
                    { sheet: 'Surat Keluar', count: 15 },
                    { sheet: 'Surat Masuk', count: 8 },
                    { sheet: 'Penerima Bansos', count: 42 },
                    { sheet: 'Kegiatan Desa', count: 5 },
                ],
            },
        });

        expect(wrapper.text()).toContain('Kartu Keluarga');
        expect(wrapper.text()).toContain('104');

        const sqlBtn = wrapper.get('[data-testid="download-sql-backup-btn"]');
        await sqlBtn.trigger('click');
        expect(assignMock).toHaveBeenCalledWith('/panel/pengaturan/backup/sql');
        expect(sqlBtn.attributes('disabled')).toBeDefined();

        const excelBtn = wrapper.get('[data-testid="download-excel-backup-btn"]');
        await excelBtn.trigger('click');
        expect(assignMock).toHaveBeenCalledWith('/panel/pengaturan/backup/excel');
        expect(excelBtn.attributes('disabled')).toBeDefined();

        vi.advanceTimersByTime(1600);
        vi.useRealTimers();
    });
});
