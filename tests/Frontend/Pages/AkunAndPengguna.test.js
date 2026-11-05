import { beforeEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useConfirm } from '@/Composables/useConfirm';
import AkunEdit from '@/Pages/Akun/Edit.vue';
import PenggunaIndex from '@/Pages/Pengaturan/Pengguna.vue';

describe('Sub-Fase 4.2: Halaman Akun/Edit.vue & Pengaturan/Pengguna.vue', () => {
    beforeEach(() => {
        useConfirm().reset();
    });

    describe('Pages/Akun/Edit.vue', () => {
        it('merender form ubah nama dan kata sandi di dalam AppLayout tanpa form hapus akun mandiri Breeze', () => {
            const page = usePage();
            page.url = '/panel/akun';
            page.props.auth.user = {
                id: 1,
                name: 'Operator Desa',
                email: 'operator@desa.id',
                role: 'operator',
            };

            const wrapper = mount(AkunEdit, {
                props: {
                    user: page.props.auth.user,
                },
            });

            expect(wrapper.find('[data-testid="akun-profile-form"]').exists()).toBe(true);
            expect(wrapper.find('[data-testid="akun-password-form"]').exists()).toBe(true);
            expect(wrapper.get('[data-testid="akun-name-input"]').element.value).toBe('Operator Desa');

            // Pastikan fitur hapus akun mandiri bawaan Breeze sudah dihapus
            expect(wrapper.text()).not.toMatch(/delete account|hapus akun/i);
        });
    });

    describe('Pages/Pengaturan/Pengguna.vue', () => {
        it('mengunci tombol nonaktif dan hapus pada baris akun sendiri, membuka BaseModal form, dan memanggil useConfirm() untuk akun lain', async () => {
            const page = usePage();
            page.url = '/panel/pengaturan/pengguna';
            page.props.auth.user = {
                id: 1,
                name: 'Operator Utama',
                email: 'operator@desa.id',
                role: 'operator',
            };

            const usersPayload = {
                data: [
                    {
                        id: 1,
                        name: 'Operator Utama',
                        email: 'operator@desa.id',
                        role: 'operator',
                        is_active: true,
                        last_login_at: '2026-11-05T08:00:00Z',
                    },
                    {
                        id: 2,
                        name: 'Kepala Desa Tamarenja',
                        email: 'kades@desa.id',
                        role: 'kepala_desa',
                        is_active: true,
                        last_login_at: null,
                    },
                ],
                current_page: 1,
                last_page: 1,
                from: 1,
                to: 2,
                total: 2,
            };

            const wrapper = mount(PenggunaIndex, {
                props: {
                    users: usersPayload,
                    filters: { search: '', role: '' },
                    activeOperatorCount: 2,
                },
            });

            // Baris 1 (Akun sendiri id=1): tombol Nonaktifkan & Hapus wajib terkunci (disabled)
            expect(wrapper.get('[data-testid="toggle-status-btn-1"]').attributes('disabled')).toBeDefined();
            expect(wrapper.get('[data-testid="delete-user-btn-1"]').attributes('disabled')).toBeDefined();

            // Baris 2 (Akun lain id=2): tombol Nonaktifkan & Hapus aktif
            expect(wrapper.get('[data-testid="toggle-status-btn-2"]').attributes('disabled')).toBeUndefined();
            expect(wrapper.get('[data-testid="delete-user-btn-2"]').attributes('disabled')).toBeUndefined();

            // Klik Tambah Pengguna -> membuka BaseModal form pengguna
            expect(wrapper.find('[data-testid="base-modal"]').exists()).toBe(false);
            await wrapper.get('[data-testid="add-user-btn"]').trigger('click');
            expect(wrapper.find('[data-testid="base-modal"]').exists()).toBe(true);
            expect(wrapper.find('[data-testid="pengguna-modal-form"]').exists()).toBe(true);

            // Tutup modal
            await wrapper.get('[data-testid="base-modal-close"]').trigger('click');
            expect(wrapper.find('[data-testid="base-modal"]').exists()).toBe(false);

            // Klik Hapus pada akun lain (id=2) -> wajib membuka dialog konfirmasi global useConfirm()
            await wrapper.get('[data-testid="delete-user-btn-2"]').trigger('click');
            await nextTick();

            expect(useConfirm().state.isOpen).toBe(true);
            expect(useConfirm().state.title).toContain('Hapus Akun Pengguna?');

            // Konfirmasi eksekusi -> memanggil router.delete
            useConfirm().accept();
            await nextTick();

            expect(router.delete).toHaveBeenCalledWith(
                '/panel/pengaturan/pengguna/2',
                { preserveScroll: true },
            );
        });
    });
});
