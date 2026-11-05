import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import BaseCallout from '@/Components/BaseCallout.vue';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';
import { useConfirm } from '@/Composables/useConfirm';
import { useNotification } from '@/Composables/useNotification';
import ErrorPage from '@/Pages/ErrorPage.vue';

describe('Sub-Fase 3.1: Global Notification, Confirm Dialog, BaseCallout & ErrorPage', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        useNotification().clear();
        useConfirm().reset();
    });

    afterEach(() => {
        useNotification().clear();
        useConfirm().reset();
        vi.useRealTimers();
    });

    describe('useNotification & GlobalToast via GlobalNotificationHost', () => {
        it('membatasi antrean maksimal 3 toast di pojok kanan atas dan mengganti toast terlama saat toast ke-4 masuk', async () => {
            const notify = useNotification();
            const wrapper = mount(GlobalNotificationHost);

            const container = wrapper.get('[data-testid="global-toast-container"]');
            expect(container.classes()).toContain('fixed');
            expect(container.classes()).toContain('right-4');
            expect(container.classes()).toContain('top-4');

            notify.success('Pesan Pertama');
            notify.info('Pesan Kedua');
            notify.warning('Pesan Ketiga');
            await nextTick();

            expect(notify.toasts.value).toHaveLength(3);
            expect(wrapper.text()).toContain('Pesan Pertama');
            expect(wrapper.text()).toContain('Pesan Kedua');
            expect(wrapper.text()).toContain('Pesan Ketiga');

            // Toast ke-4 harus menggeser keluar Pesan Pertama sehingga tetap maks 3 tumpukan
            notify.error('Pesan Keempat');
            await nextTick();

            expect(notify.toasts.value).toHaveLength(3);
            expect(wrapper.text()).not.toContain('Pesan Pertama');
            expect(wrapper.text()).toContain('Pesan Kedua');
            expect(wrapper.text()).toContain('Pesan Ketiga');
            expect(wrapper.text()).toContain('Pesan Keempat');
        });

        it('menghapus toast secara otomatis tepat setelah timer 4 detik (4000ms) dan mendukung tutup manual', async () => {
            const notify = useNotification();
            const wrapper = mount(GlobalNotificationHost);

            notify.success('Data penduduk berhasil disimpan');
            await nextTick();
            expect(notify.toasts.value).toHaveLength(1);
            expect(wrapper.text()).toContain('Data penduduk berhasil disimpan');

            // Pada 3999ms toast masih tampil
            vi.advanceTimersByTime(3999);
            await nextTick();
            expect(notify.toasts.value).toHaveLength(1);

            // Tepat pada 4000ms toast otomatis tertutup
            vi.advanceTimersByTime(1);
            await nextTick();
            expect(notify.toasts.value).toHaveLength(0);
            expect(wrapper.text()).not.toContain('Data penduduk berhasil disimpan');

            // Tutup manual sebelum 4 detik
            notify.warning('Dokumen APBDes Murni terkunci');
            await nextTick();
            expect(notify.toasts.value).toHaveLength(1);

            await wrapper.get('[data-testid="toast-dismiss-btn"]').trigger('click');
            expect(notify.toasts.value).toHaveLength(0);
        });

        it('memicu toast secara otomatis ketika page.props.flash.notification berubah dari Inertia', async () => {
            const page = usePage();
            const notify = useNotification();

            const flashObj = {
                type: 'success',
                title: 'Berhasil',
                message: 'Baliho APBDes berhasil diperbarui',
            };
            page.props.flash.notification = flashObj;

            const wrapper = mount(GlobalNotificationHost);
            await nextTick();

            expect(notify.toasts.value).toHaveLength(1);
            expect(wrapper.text()).toContain('Berhasil');
            expect(wrapper.text()).toContain('Baliho APBDes berhasil diperbarui');

            // Remount dengan referensi objek flash yang sama tidak menduplikasi toast
            wrapper.unmount();
            const remounted = mount(GlobalNotificationHost);
            await nextTick();
            expect(notify.toasts.value).toHaveLength(1);

            // Perubahan flash.notification ke objek baru otomatis memicu toast baru
            page.props.flash.notification = {
                type: 'error',
                message: 'Akses ditolak sesuai peran pengguna',
            };
            await nextTick();

            expect(notify.toasts.value).toHaveLength(2);
            expect(remounted.text()).toContain('Akses ditolak sesuai peran pengguna');
        });
    });

    describe('useConfirm & GlobalConfirmDialog', () => {
        it('meresolusi Promise menjadi true saat tombol konfirmasi diklik', async () => {
            const confirm = useConfirm();
            const wrapper = mount(GlobalNotificationHost);

            expect(wrapper.find('[data-testid="global-confirm-dialog"]').exists()).toBe(false);

            const pendingPromise = confirm.ask({
                title: 'Hapus Proyek Pembangunan?',
                message: 'Data proyek akan dipindahkan ke arsip terhapus dan realisasi Bidang 2 disesuaikan.',
                variant: 'danger',
                confirmText: 'Hapus Proyek',
            });
            await nextTick();

            const dialog = wrapper.get('[data-testid="global-confirm-dialog"]');
            expect(dialog.text()).toContain('Hapus Proyek Pembangunan?');
            expect(dialog.text()).toContain('realisasi Bidang 2 disesuaikan');

            const acceptBtn = wrapper.get('[data-testid="confirm-accept-btn"]');
            expect(acceptBtn.text()).toBe('Hapus Proyek');
            expect(acceptBtn.classes()).toContain('bg-red-600');

            await acceptBtn.trigger('click');
            const result = await pendingPromise;

            expect(result).toBe(true);
            expect(wrapper.find('[data-testid="global-confirm-dialog"]').exists()).toBe(false);
        });

        it('meresolusi Promise menjadi false saat tombol Batal diklik atau tombol Escape ditekan', async () => {
            const confirm = useConfirm();
            const wrapper = mount(GlobalNotificationHost);

            // 1. Uji klik tombol Batal
            const cancelPromise = confirm.ask({
                title: 'Batalkan Surat Terbit?',
                message: 'QR Code surat ini akan ditandai Dibatalkan secara permanen.',
                variant: 'warning',
                confirmText: 'Batalkan Surat',
            });
            await nextTick();

            expect(wrapper.get('[data-testid="confirm-accept-btn"]').classes()).toContain('bg-amber-600');
            await wrapper.get('[data-testid="confirm-cancel-btn"]').trigger('click');

            expect(await cancelPromise).toBe(false);
            expect(wrapper.find('[data-testid="global-confirm-dialog"]').exists()).toBe(false);

            // 2. Uji tombol Escape keyboard
            const escapePromise = confirm.ask({
                title: 'Terbitkan Surat Kematian?',
                message: 'Status kependudukan warga akan otomatis berubah menjadi Meninggal Dunia.',
                variant: 'danger',
            });
            await nextTick();

            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
            await nextTick();

            expect(await escapePromise).toBe(false);
            expect(wrapper.find('[data-testid="global-confirm-dialog"]').exists()).toBe(false);
        });
    });

    describe('BaseCallout.vue', () => {
        it('merender keterangan aturan bisnis statis sesuai varian tanpa tombol dismiss duplikat', () => {
            const wrapper = mount(BaseCallout, {
                props: {
                    variant: 'locked',
                    title: 'Dokumen APBDes Murni Terkunci',
                },
                slots: {
                    default:
                        'Dokumen APBDes Murni telah dikunci sebagai arsip pembanding karena APBDes Perubahan sudah dibuat.',
                },
            });

            expect(wrapper.attributes('role')).toBe('note');
            expect(wrapper.text()).toContain('Dokumen APBDes Murni Terkunci');
            expect(wrapper.text()).toContain('arsip pembanding karena APBDes Perubahan sudah dibuat');
            // BaseCallout statis dilarang memiliki tombol close/dismiss seperti alert banner aksi
            expect(wrapper.find('button').exists()).toBe(false);
        });
    });

    describe('ErrorPage.vue (HTTP 403, 404, 419, 500)', () => {
        it.each([
            [403, 'Akses Dibatasi Sesuai Peran', 'pembagian peran perangkat desa'],
            [404, 'Halaman atau Dokumen Tidak Ditemukan', 'tidak tersedia, telah dipindahkan'],
            [419, 'Sesi Telah Berakhir', 'kedaluwarsa karena tidak ada aktivitas'],
            [500, 'Gangguan Layanan Internal', 'kendala teknis pada layanan internal desa'],
        ])('menampilkan judul dan keterangan formal untuk status %i', (status, expectedTitle, expectedPhrase) => {
            const wrapper = mount(ErrorPage, {
                props: { status },
            });

            expect(wrapper.get('[data-testid="error-status-code"]').text()).toContain(String(status));
            expect(wrapper.get('[data-testid="error-title"]').text()).toBe(expectedTitle);
            expect(wrapper.get('[data-testid="error-description"]').text()).toContain(expectedPhrase);
            expect(wrapper.findAll('[data-testid="error-action-btn"]')).toHaveLength(1);
        });

        it('mengarahkan tombol kembali ke /panel/dashboard bagi pengguna login internal pada 403 dan otomatis me-redirect sesi 419', async () => {
            const page = usePage();
            page.props.auth.user = { id: 2, name: 'Kepala Desa', role: 'kepala_desa' };
            page.url = '/panel/pengaturan/pengguna';

            const forbiddenWrapper = mount(ErrorPage, {
                props: { status: 403 },
            });
            const backBtn = forbiddenWrapper.get('[data-testid="error-action-btn"]');
            expect(backBtn.attributes('href')).toBe('/panel/dashboard');
            expect(backBtn.text()).toContain('Kembali ke Dashboard');

            // Uji auto-redirect 419 ke /login pada halaman internal
            const expiredWrapper = mount(ErrorPage, {
                props: { status: 419 },
            });
            expect(expiredWrapper.get('[data-testid="error-action-btn"]').text()).toContain('Masuk Kembali');

            vi.advanceTimersByTime(3500);
            expect(router.visit).toHaveBeenCalledWith('/login');
            expiredWrapper.unmount();
        });
    });
});
