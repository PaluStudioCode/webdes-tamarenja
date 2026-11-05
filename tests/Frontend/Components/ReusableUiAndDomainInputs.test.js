import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseModal from '@/Components/BaseModal.vue';
import CurrencyInput from '@/Components/CurrencyInput.vue';
import DataTable from '@/Components/DataTable.vue';
import DusunCombobox from '@/Components/DusunCombobox.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FileDropzone from '@/Components/FileDropzone.vue';
import NikSearchAutocomplete from '@/Components/NikSearchAutocomplete.vue';
import ProjectPhotoCheckpoints from '@/Components/ProjectPhotoCheckpoints.vue';
import SlideOverDrawer from '@/Components/SlideOverDrawer.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import UnifiedFilterBar from '@/Components/UnifiedFilterBar.vue';

describe('Sub-Fase 3.3: Komponen UI Dasar, Tabel Anti-Penumpukan & Input Khusus Domain Desa', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('BaseCard, BaseButton, StatCard, BaseModal & SlideOverDrawer', () => {
        it('mencegah border dan shadow ganda pada nested BaseCard', () => {
            const NestedHarness = defineComponent({
                components: { BaseCard },
                template: `
                    <BaseCard title="Kartu Induk">
                        <BaseCard>Isi Anak</BaseCard>
                    </BaseCard>
                `,
            });

            const wrapper = mount(NestedHarness);
            const cards = wrapper.findAll('[data-testid="base-card"]');
            expect(cards).toHaveLength(2);
            expect(cards[0].attributes('data-nested')).toBe('false');
            expect(cards[0].classes()).toContain('border');
            expect(cards[1].attributes('data-nested')).toBe('true');
            expect(cards[1].classes()).not.toContain('border');
        });

        it('merender seluruh varian BaseButton dan mengunci tombol beserta spinner saat loading', () => {
            const variants = [
                ['primary', 'bg-blue-700'],
                ['secondary', 'bg-white'],
                ['success-wa', 'bg-emerald-600'],
                ['ghost', 'text-slate-600'],
                ['destructive', 'bg-red-600'],
            ];

            for (const [variant, expectedClass] of variants) {
                const btn = mount(BaseButton, {
                    props: { variant },
                    slots: { default: 'Aksi' },
                });
                expect(btn.classes()).toContain(expectedClass);
            }

            const loadingBtn = mount(BaseButton, {
                props: { loading: true },
                slots: { default: 'Menyimpan...' },
            });
            expect(loadingBtn.attributes('disabled')).toBeDefined();
            expect(loadingBtn.find('[data-testid="button-spinner"]').exists()).toBe(true);
        });

        it('merender StatCard tabular-nums serta menutup BaseModal dan SlideOverDrawer dengan Escape', async () => {
            const stat = mount(StatCard, {
                props: {
                    label: 'Total Pagu APBDes',
                    value: 'Rp 1.450.000.000',
                    subtext: 'Serapan 72%',
                    tone: 'emerald',
                },
            });
            expect(stat.get('[data-testid="stat-card-value"]').classes()).toContain('tabular-nums');
            expect(stat.get('[data-testid="stat-card-subtext"]').text()).toBe('Serapan 72%');

            const modal = mount(BaseModal, {
                props: { show: true, title: 'Tambah Perangkat SOTK' },
                slots: { default: '<p>Form SOTK</p>' },
            });
            expect(modal.get('[data-testid="base-modal"]').text()).toContain('Tambah Perangkat SOTK');
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
            expect(modal.emitted('close')).toBeTruthy();

            const drawer = mount(SlideOverDrawer, {
                props: { show: true, title: 'Detail Anggota KK' },
                slots: { default: '<p>Daftar Anggota</p>' },
            });
            expect(drawer.get('[data-testid="slide-over-drawer"]').text()).toContain('Detail Anggota KK');
            await drawer.get('[data-testid="drawer-close-btn"]').trigger('click');
            expect(drawer.emitted('close')).toBeTruthy();
        });
    });

    describe('StatusBadge.vue (Pemetaan Warna PRD 1.15)', () => {
        it.each([
            // Slate
            ['belum_dimulai', 'slate', 'Belum Dimulai'],
            ['draft', 'slate', 'Draft'],
            ['pindah_keluar', 'slate', 'Pindah Keluar'],
            ['graduasi', 'slate', 'Graduasi'],
            ['murni_terkunci', 'slate', 'APBDes Murni - Arsip Terkunci'],
            ['APBDes Murni - Arsip Terkunci', 'slate', 'APBDes Murni - Arsip Terkunci'],
            // Amber
            ['sedang_dikerjakan', 'amber', 'Sedang Dikerjakan'],
            ['menunggu_verifikasi', 'amber', 'Menunggu Verifikasi'],
            ['perubahan', 'amber', 'APBDes Perubahan'],
            // Emerald
            ['selesai', 'emerald', 'Selesai'],
            ['terbit', 'emerald', 'Terbit / Dicetak'],
            ['surat_asli', 'emerald', 'Surat Asli'],
            ['aktif', 'emerald', 'Aktif'],
            ['published', 'emerald', 'Published'],
            // Red
            ['ditolak', 'red', 'Ditolak'],
            ['dibatalkan', 'red', 'Dibatalkan'],
            ['meninggal_dunia', 'red', 'Meninggal Dunia'],
            ['tidak_terdaftar', 'red', 'Tidak Terdaftar'],
        ])('memetakan status "%s" ke warna "%s" dan label "%s"', (status, expectedColor, expectedLabel) => {
            const wrapper = mount(StatusBadge, {
                props: { status },
            });

            const badge = wrapper.get('[data-testid="status-badge"]');
            expect(badge.attributes('data-color')).toBe(expectedColor);
            expect(badge.text()).toBe(expectedLabel);
        });
    });

    describe('EmptyState, DataTable & UnifiedFilterBar (Visibilitas Role & Anti-Penumpukan)', () => {
        it('menyembunyikan tombol tambah pada EmptyState bagi kepala_desa dan menampilkannya bagi operator', async () => {
            const page = usePage();
            page.props.auth.user = { id: 1, name: 'Operator', role: 'operator' };

            const wrapper = mount(EmptyState, {
                props: {
                    title: 'Belum ada data proyek pembangunan pada Tahun Anggaran 2026',
                    actionLabel: '+ Tambah Pembangunan',
                },
            });

            expect(wrapper.find('[data-testid="empty-state-action"]').exists()).toBe(true);

            page.props.auth.user = { id: 2, name: 'Kepala Desa', role: 'kepala_desa' };
            await nextTick();
            expect(wrapper.find('[data-testid="empty-state-action"]').exists()).toBe(false);
        });

        it('membatasi DataTable maks 7 kolom, meratakan kanan Rupiah tabular-nums, serta menyembunyikan aksi edit/hapus bagi kepala_desa', async () => {
            const page = usePage();
            page.props.auth.user = { id: 1, name: 'Operator', role: 'operator' };

            const eightColumns = Array.from({ length: 8 }, (_, idx) => ({
                key: idx === 2 ? 'pagu_anggaran' : `col_${idx + 1}`,
                label: idx === 2 ? 'Pagu Anggaran' : `Kolom ${idx + 1}`,
                isCurrency: idx === 2,
            }));

            const rows = {
                data: [
                    {
                        id: 10,
                        col_1: 'Rabat Beton Dusun I',
                        col_2: 'Dusun I',
                        pagu_anggaran: 125000000,
                        col_4: '100%',
                    },
                ],
                current_page: 1,
                last_page: 2,
                from: 1,
                to: 1,
                total: 16,
            };

            const rowActions = [
                { key: 'detail', label: 'Lihat Detail', primary: true, operatorOnly: false },
                { key: 'cetak', label: 'Cetak PDF', primary: true, operatorOnly: false },
                { key: 'edit', label: 'Edit Proyek' },
                { key: 'delete', label: 'Hapus Proyek', variant: 'danger' },
            ];

            const wrapper = mount(DataTable, {
                props: { columns: eightColumns, rows, rowActions },
            });

            // Kolom ke-8 dipangkas sehingga maksimal 7 kolom data + 1 kolom Aksi
            expect(wrapper.find('[data-testid="th-col_8"]').exists()).toBe(false);

            // Kolom Rupiah rata kanan tabular-nums & terformat Rp titik
            const currencyCell = wrapper.get('[data-testid="td-pagu_anggaran-0"]');
            expect(currencyCell.classes()).toContain('text-right');
            expect(currencyCell.classes()).toContain('tabular-nums');
            expect(currencyCell.text()).toBe('Rp 125.000.000');

            // Pagination server-side tampil
            expect(wrapper.get('[data-testid="data-table-pagination"]').text()).toContain(
                'dari 16 data',
            );

            // Sebagai operator: menu tiga titik menampilkan Edit & Hapus
            await wrapper.get('[data-testid="row-more-btn-0"]').trigger('click');
            expect(wrapper.find('[data-testid="row-action-edit-0"]').exists()).toBe(true);
            expect(wrapper.find('[data-testid="row-action-delete-0"]').exists()).toBe(true);

            // Sebagai kepala_desa: hanya Lihat Detail & Cetak PDF, tanpa menu Edit/Hapus
            page.props.auth.user = { id: 2, name: 'Kepala Desa', role: 'kepala_desa' };
            await nextTick();

            expect(wrapper.find('[data-testid="row-action-detail-0"]').exists()).toBe(true);
            expect(wrapper.find('[data-testid="row-action-cetak-0"]').exists()).toBe(true);
            expect(wrapper.find('[data-testid="row-more-btn-0"]').exists()).toBe(false);
            expect(wrapper.find('[data-testid="row-action-edit-0"]').exists()).toBe(false);
            expect(wrapper.find('[data-testid="row-action-delete-0"]').exists()).toBe(false);
        });

        it('menjalankan pencarian debounced pada UnifiedFilterBar dan menampilkan tombol Reset secara kondisional', async () => {
            const wrapper = mount(UnifiedFilterBar, {
                props: {
                    modelValue: '',
                    filters: [
                        {
                            key: 'dusun',
                            label: 'Dusun',
                            value: '',
                            options: [{ value: 'Dusun I', label: 'Dusun I' }],
                        },
                    ],
                },
            });

            expect(wrapper.find('[data-testid="filter-reset-btn"]').exists()).toBe(false);

            const searchInput = wrapper.get('[data-testid="filter-search-input"]');
            await searchInput.setValue('Rabat Beton');

            // Tombol Reset muncul begitu ada ketikan
            expect(wrapper.find('[data-testid="filter-reset-btn"]').exists()).toBe(true);

            // Sebelum 300ms belum emit search
            vi.advanceTimersByTime(299);
            expect(wrapper.emitted('search')).toBeFalsy();

            vi.advanceTimersByTime(1);
            expect(wrapper.emitted('search')?.[0]).toEqual(['Rabat Beton']);

            await wrapper.get('[data-testid="filter-reset-btn"]').trigger('click');
            expect(wrapper.emitted('reset')).toBeTruthy();
        });
    });

    describe('Input Khusus Domain Desa & Bebas Pengulangan Label', () => {
        it('memformat tampilan ribuan titik Rp pada CurrencyInput dan meng-emit integer murni', async () => {
            const wrapper = mount(CurrencyInput, {
                props: {
                    label: 'Pagu Anggaran',
                    modelValue: 55000000,
                    required: true,
                },
            });

            const input = wrapper.get('[data-testid="currency-input"]');
            expect(input.element.value).toBe('Rp 55.000.000');
            expect(input.attributes('placeholder')).not.toMatch(/masukkan|isi dengan/i);

            // Simulasikan pengetikan angka
            input.element.value = 'Rp 78.500.000';
            await input.trigger('input');

            const emittedVal = wrapper.emitted('update:modelValue')?.[0]?.[0];
            expect(emittedVal).toBe(78500000);
            expect(typeof emittedVal).toBe('number');
            expect(Number.isInteger(emittedVal)).toBe(true);
        });

        it('menjalankan debounce 300ms minimal 3 huruf pada NikSearchAutocomplete dan menampilkan kartu ringkasan tanpa 12 input disabled', async () => {
            const mockSearchFn = vi.fn(async () => [
                {
                    id: 42,
                    nik: '7203081205900001',
                    nama_lengkap: 'Budi Santoso',
                    dusun: 'Dusun II',
                    status_kesejahteraan: 'dtsen_desil_1',
                    status_kependudukan: 'aktif',
                },
            ]);

            const wrapper = mount(NikSearchAutocomplete, {
                props: {
                    label: 'Pemohon Surat',
                    searchFn: mockSearchFn,
                },
            });

            const input = wrapper.get('[data-testid="nik-search-input"]');
            expect(input.attributes('placeholder')).not.toMatch(/masukkan pemohon/i);

            // Ketik 2 huruf -> tidak memanggil pencarian meskipun lewat 300ms
            await input.setValue('Bu');
            vi.advanceTimersByTime(400);
            expect(mockSearchFn).not.toHaveBeenCalled();

            // Ketik >= 3 huruf -> dipanggil tepat setelah 300ms
            await input.setValue('Budi');
            vi.advanceTimersByTime(299);
            expect(mockSearchFn).not.toHaveBeenCalled();

            vi.advanceTimersByTime(1);
            await vi.runAllTimersAsync();
            await nextTick();

            expect(mockSearchFn).toHaveBeenCalledWith('Budi');
            expect(wrapper.find('[data-testid="nik-search-dropdown"]').exists()).toBe(true);

            // Pilih warga -> tampilkan kartu ringkasan tunggal & emit ID warga
            await wrapper.get('[data-testid="nik-option-42"]').trigger('click');
            expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([42]);

            const summary = wrapper.get('[data-testid="selected-resident-summary"]');
            expect(summary.text()).toContain('Budi Santoso');
            expect(summary.text()).toContain('7203081205900001');
            expect(summary.text()).toContain('Dusun II');
            // Pastikan tidak ada penyalinan input disabled yang memanjangkan halaman
            expect(wrapper.findAll('input[disabled]')).toHaveLength(0);
        });

        it('menampilkan saran dusun resmi dari profil_desa.daftar_dusun pada DusunCombobox', async () => {
            const wrapper = mount(DusunCombobox, {
                props: {
                    label: 'Lokasi Dusun',
                    modelValue: '',
                },
            });

            const input = wrapper.get('[data-testid="dusun-combobox-input"]');
            expect(input.attributes('placeholder')).not.toMatch(/masukkan lokasi dusun/i);

            await input.trigger('focus');
            const optionsList = wrapper.get('[data-testid="dusun-combobox-options"]');
            expect(optionsList.text()).toContain('Dusun I');
            expect(optionsList.text()).toContain('Dusun IV');

            await wrapper.get('[data-testid="dusun-option-Dusun II"]').trigger('click');
            expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['Dusun II']);
        });

        it('merender 3 titik foto 0%/50%/100% pada ProjectPhotoCheckpoints dan nama+ukuran berkas pada FileDropzone', async () => {
            const checkpoints = mount(ProjectPhotoCheckpoints, {
                props: {
                    progresFisik: 55,
                },
            });

            expect(checkpoints.find('[data-testid="checkpoint-box-foto_0"]').exists()).toBe(true);
            expect(checkpoints.find('[data-testid="checkpoint-box-foto_50"]').exists()).toBe(true);
            expect(checkpoints.find('[data-testid="checkpoint-box-foto_100"]').exists()).toBe(true);

            // Progres 55% -> foto_0 & foto_50 wajib, foto_100 belum wajib
            expect(checkpoints.find('[data-testid="checkpoint-required-foto_0"]').exists()).toBe(true);
            expect(checkpoints.find('[data-testid="checkpoint-required-foto_50"]').exists()).toBe(true);
            expect(checkpoints.find('[data-testid="checkpoint-required-foto_100"]').exists()).toBe(false);

            const samplePdf = new File([new Uint8Array(2048)], 'perdes-apbdes-2026.pdf', {
                type: 'application/pdf',
            });

            const dropzone = mount(FileDropzone, {
                props: {
                    label: 'Berkas Dokumen PPID',
                    modelValue: samplePdf,
                },
            });

            const fileList = dropzone.get('[data-testid="file-dropzone-list"]');
            expect(fileList.text()).toContain('perdes-apbdes-2026.pdf');
            expect(fileList.text()).toContain('2.0 KB');
        });
    });
});
