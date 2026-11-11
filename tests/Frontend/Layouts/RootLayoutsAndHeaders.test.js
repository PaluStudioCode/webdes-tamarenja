import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import ConditionalBreadcrumb from '@/Components/ConditionalBreadcrumb.vue';
import ContextualTabs from '@/Components/ContextualTabs.vue';
import GlobalNotificationHost from '@/Components/GlobalNotificationHost.vue';
import PageHeader from '@/Components/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';

describe('Sub-Fase 3.2: Tiga Root Layout (PublicLayout, AuthLayout, AppLayout) & Header Kontekstual', () => {
    it('memasang GlobalNotificationHost tepat satu kali pada PublicLayout, AuthLayout, dan AppLayout', () => {
        const publicWrap = mount(PublicLayout, {
            slots: { default: '<p>Konten Publik</p>' },
        });
        expect(publicWrap.findAllComponents(GlobalNotificationHost)).toHaveLength(1);

        const authWrap = mount(AuthLayout, {
            slots: { default: '<form>Form Login</form>' },
        });
        expect(authWrap.findAllComponents(GlobalNotificationHost)).toHaveLength(1);

        usePage().props.auth.user = { id: 1, name: 'Operator', role: 'operator' };
        const appWrap = mount(AppLayout, {
            slots: { default: '<p>Konten Panel</p>' },
        });
        expect(appWrap.findAllComponents(GlobalNotificationHost)).toHaveLength(1);
    });

    describe('PublicLayout.vue', () => {
        it('menampilkan 6 item menu datar di Top Navbar tanpa tombol Login Internal, kontainer max-w-7xl, serta kontak lengkap di Footer', async () => {
            const wrapper = mount(PublicLayout, {
                slots: { default: '<div>Isi Halaman</div>' },
            });

            const navbar = wrapper.get('[data-testid="public-navbar"]');
            const desktopNav = wrapper.get('[data-testid="public-desktop-nav"]');

            expect(desktopNav.text()).toContain('Beranda');
            expect(desktopNav.text()).toContain('Profil Desa');
            expect(desktopNav.text()).toContain('Transparansi');
            expect(desktopNav.text()).toContain('Kegiatan');
            expect(desktopNav.text()).toContain('Unduhan PPID');
            expect(desktopNav.text()).toContain('Layanan Mandiri');

            // Tidak boleh ada tautan /login atau tombol Login Internal mencolok di Top Navbar
            expect(navbar.find('a[href="/login"]').exists()).toBe(false);
            expect(navbar.text()).not.toContain('Panel Perangkat Desa');

            // Dropdown Transparansi berisi 3 submenu publik
            await wrapper.get('[data-testid="public-transparansi-trigger"]').trigger('click');
            const dropdown = wrapper.get('[data-testid="public-transparansi-dropdown"]');
            expect(dropdown.text()).toContain('APBDes');
            expect(dropdown.text()).toContain('Pembangunan');
            expect(dropdown.text()).toContain('Statistik Penduduk');

            // Kontainer utama max-w-7xl
            expect(wrapper.get('[data-testid="public-main-container"]').classes()).toContain('max-w-7xl');

            // Footer wajib memuat alamat, jam pelayanan, email, WA, tautan PPID, & tautan halus Panel Perangkat Desa
            const footer = wrapper.get('[data-testid="public-footer"]');
            expect(footer.text()).toContain('Jl. Trans Sulawesi No. 12, Desa Tamarenja');
            expect(footer.text()).toContain('Senin - Jumat, 08.00 - 15.00 WITA');
            expect(footer.text()).toContain('pemdes@tamarenja.desa.id');
            expect(footer.text()).toContain('6281234567890');
            expect(footer.get('[data-testid="footer-ppid-link"]').attributes('href')).toBe('/unduhan');
            expect(footer.get('[data-testid="footer-panel-link"]').attributes('href')).toBe('/login');
            expect(footer.get('[data-testid="footer-panel-link"]').text()).toBe('Panel Perangkat Desa');
        });
    });

    describe('AuthLayout.vue', () => {
        it('merender kartu login minimalis beridentitas desa dan tautan kembali ke portal publik', () => {
            const wrapper = mount(AuthLayout, {
                slots: { default: '<button type="submit">Masuk</button>' },
            });

            expect(wrapper.text()).toContain('Pemerintah Desa Tamarenja');
            expect(wrapper.text()).toContain('Panel Operasional & Administrasi Desa');
            expect(wrapper.text()).toContain('Masuk');
            expect(wrapper.find('a[href="/"]').exists()).toBe(true);
        });
    });

    describe('AppLayout.vue', () => {
        it('membedakan visibilitas menu Pengaturan Sistem antara operator dan kepala_desa serta menampilkan badge antrean surat', async () => {
            const page = usePage();
            page.url = '/panel/dashboard';
            page.props.antrean_surat_count = 4;
            page.props.auth.user = {
                id: 1,
                name: 'Operator Loket',
                role: 'operator',
            };

            const operatorWrap = mount(AppLayout, {
                slots: { default: '<div>Area Kerja</div>' },
            });

            expect(operatorWrap.find('[data-testid="sidebar-link-pengaturan"]').exists()).toBe(true);
            expect(operatorWrap.find('[data-testid="topbar-role-badge"]').exists()).toBe(false);
            expect(operatorWrap.get('[data-testid="persuratan-antrean-badge"]').text()).toBe('4');
            expect(operatorWrap.text()).not.toContain('Selamat Datang Kembali');

            // Uji collapsible & posisi fixed sidebar desktop
            const desktopSidebar = operatorWrap.get('[data-testid="desktop-sidebar"]');
            const mainWrapper = operatorWrap.get('[data-testid="app-main-wrapper"]');
            expect(desktopSidebar.classes()).toContain('fixed');
            expect(desktopSidebar.classes()).toContain('inset-y-0');
            expect(desktopSidebar.classes()).toContain('left-0');
            expect(desktopSidebar.classes()).toContain('w-64');
            expect(mainWrapper.classes()).toContain('lg:pl-64');
            await operatorWrap.get('[data-testid="desktop-sidebar-toggle"]').trigger('click');
            expect(desktopSidebar.classes()).toContain('w-20');
            expect(mainWrapper.classes()).toContain('lg:pl-20');

            // Ganti peran ke kepala_desa -> Pengaturan Sistem wajib disembunyikan sepenuhnya
            page.props.auth.user = {
                id: 2,
                name: 'Kepala Desa',
                role: 'kepala_desa',
            };
            await nextTick();

            expect(operatorWrap.find('[data-testid="sidebar-link-pengaturan"]').exists()).toBe(false);
            expect(operatorWrap.find('[data-testid="topbar-role-badge"]').exists()).toBe(false);
        });

        it('memisahkan menu Bantuan Sosial dan Kegiatan Desa menjadi menu tingkat utama independen di sidebar', () => {
            const page = usePage();
            page.url = '/panel/dashboard';
            page.props.auth.user = { id: 1, name: 'Operator', role: 'operator' };

            const wrapper = mount(AppLayout, {
                slots: { default: '<div>Konten</div>' },
            });

            // Menu Bantuan Sosial dan Kegiatan Desa berdiri sendiri sebagai menu tingkat utama
            const bansosLink = wrapper.find('[data-testid="sidebar-link-bansos"]');
            const kegiatanLink = wrapper.find('[data-testid="sidebar-link-kegiatan"]');
            expect(bansosLink.exists()).toBe(true);
            expect(bansosLink.text()).toContain('Bantuan Sosial');
            expect(bansosLink.attributes('href')).toBe('/panel/sosial/bansos');

            expect(kegiatanLink.exists()).toBe(true);
            expect(kegiatanLink.text()).toContain('Kegiatan Desa');
            expect(kegiatanLink.attributes('href')).toBe('/panel/sosial/kegiatan');

            // Tidak ada lagi menu gabungan sosial dengan sublink
            expect(wrapper.find('[data-testid="sidebar-link-sosial"]').exists()).toBe(false);
            expect(wrapper.find('[data-testid="sidebar-sublink-bansos"]').exists()).toBe(false);
            expect(wrapper.find('[data-testid="sidebar-sublink-kegiatan"]').exists()).toBe(false);
        });

        it('menampilkan dropdown Tahun Anggaran Sesi di rute tahunan dan menyembunyikannya di rute lintas-waktu (kependudukan, pengaturan, akun)', async () => {
            const page = usePage();
            page.props.auth.user = { id: 1, name: 'Operator', role: 'operator' };
            page.props.filter_tahun = 2026;
            page.props.available_tahun = [2026, 2025];
            page.url = '/panel/pembangunan';

            const wrapper = mount(AppLayout, {
                slots: { default: '<div>Konten</div>' },
            });

            // Di /panel/pembangunan pemilih tahun tampil dan dapat mengganti tahun sesi
            const selector = wrapper.get('[data-testid="topbar-year-selector"]');
            expect(selector.element.value).toBe('2026');
            await selector.setValue('2025');
            expect(router.post).toHaveBeenCalledWith(
                '/panel/filter-tahun',
                { tahun: 2025 },
                { preserveScroll: true },
            );

            // Di rute lintas-waktu pemilih tahun wajib tersembunyi
            for (const crossTimeUrl of [
                '/panel/kependudukan',
                '/panel/pengaturan/profil-desa',
                '/panel/akun',
            ]) {
                page.url = crossTimeUrl;
                await nextTick();
                expect(wrapper.find('[data-testid="topbar-year-selector"]').exists()).toBe(false);
            }

            // Kembali ke rute tahunan -> muncul kembali
            page.url = '/panel/apbdes';
            await nextTick();
            expect(wrapper.find('[data-testid="topbar-year-selector"]').exists()).toBe(true);
        });

        it('menutup slide-over drawer mobile secara otomatis saat navigasi berpindah atau item menu diklik', async () => {
            const page = usePage();
            page.props.auth.user = { id: 1, name: 'Operator', role: 'operator' };
            page.url = '/panel/dashboard';

            const wrapper = mount(AppLayout, {
                slots: { default: '<div>Konten</div>' },
            });

            expect(wrapper.find('[data-testid="mobile-sidebar-drawer"]').exists()).toBe(false);

            // Buka drawer mobile
            await wrapper.get('[data-testid="mobile-sidebar-toggle"]').trigger('click');
            expect(wrapper.find('[data-testid="mobile-sidebar-drawer"]').exists()).toBe(true);

            // Klik salah satu menu -> drawer otomatis menutup
            await wrapper.get('[data-testid="mobile-sidebar-link-pembangunan"]').trigger('click');
            expect(wrapper.find('[data-testid="mobile-sidebar-drawer"]').exists()).toBe(false);

            // Buka kembali lalu ubah URL navigasi Inertia -> drawer otomatis menutup
            await wrapper.get('[data-testid="mobile-sidebar-toggle"]').trigger('click');
            expect(wrapper.find('[data-testid="mobile-sidebar-drawer"]').exists()).toBe(true);

            page.url = '/panel/apbdes';
            await nextTick();
            expect(wrapper.find('[data-testid="mobile-sidebar-drawer"]').exists()).toBe(false);
        });
    });

    describe('PageHeader, ConditionalBreadcrumb & ContextualTabs', () => {
        it('merender PageHeader dengan H1 tunggal di kiri dan slot Primary Action di kanan', () => {
            const wrapper = mount(PageHeader, {
                props: {
                    title: 'Proyek Pembangunan Fisik',
                },
                slots: {
                    actions: '<button type="button">+ Tambah Proyek</button>',
                },
            });

            expect(wrapper.findAll('h1')).toHaveLength(1);
            expect(wrapper.get('[data-testid="page-header-title"]').text()).toBe(
                'Proyek Pembangunan Fisik',
            );
            expect(wrapper.get('[data-testid="page-header-actions"]').text()).toContain(
                '+ Tambah Proyek',
            );
        });

        it('menyembunyikan ConditionalBreadcrumb di halaman indeks tingkat pertama dan hanya menampilkannya di sub-halaman ke-2+', async () => {
            const page = usePage();

            // 1. Halaman indeks tingkat pertama (/panel/pembangunan) -> wajib disembunyikan meskipun diberi items
            page.url = '/panel/pembangunan';
            const indexWrap = mount(ConditionalBreadcrumb, {
                props: {
                    items: [
                        { label: 'Panel', href: '/panel/dashboard' },
                        { label: 'Pembangunan Fisik' },
                    ],
                },
            });
            expect(indexWrap.find('[data-testid="conditional-breadcrumb"]').exists()).toBe(false);

            // 2. Sub-halaman kedalaman ke-2+ (/panel/pembangunan/tambah) -> wajib tampil
            page.url = '/panel/pembangunan/tambah';
            await nextTick();
            expect(indexWrap.find('[data-testid="conditional-breadcrumb"]').exists()).toBe(true);

            // 3. Jika items kurang dari 2 level -> tetap disembunyikan
            const singleItemWrap = mount(ConditionalBreadcrumb, {
                props: {
                    items: [{ label: 'Pembangunan Fisik' }],
                },
            });
            expect(singleItemWrap.find('[data-testid="conditional-breadcrumb"]').exists()).toBe(false);
        });

        it('mendukung ContextualTabs berbasis v-model maupun berbasis sub-prefix URL beserta badge', async () => {
            // Mode v-model pada halaman yang sama
            const stateTabsWrap = mount(ContextualTabs, {
                props: {
                    modelValue: 'penduduk',
                    tabs: [
                        { key: 'penduduk', label: 'Data Penduduk' },
                        { key: 'kk', label: 'Kartu Keluarga' },
                    ],
                },
            });

            expect(
                stateTabsWrap.get('[data-testid="contextual-tab-penduduk"]').attributes('aria-selected'),
            ).toBe('true');

            await stateTabsWrap.get('[data-testid="contextual-tab-kk"]').trigger('click');
            expect(stateTabsWrap.emitted('update:modelValue')?.[0]).toEqual(['kk']);

            // Mode tautan sub-prefix URL (misal Persuratan)
            usePage().url = '/panel/persuratan/antrean';
            const routeTabsWrap = mount(ContextualTabs, {
                props: {
                    tabs: [
                        {
                            key: 'antrean',
                            label: 'Antrean Online',
                            href: '/panel/persuratan/antrean',
                            badge: 3,
                        },
                        {
                            key: 'surat-keluar',
                            label: 'Surat Keluar',
                            href: '/panel/persuratan/surat-keluar',
                        },
                    ],
                },
            });

            const antreanTab = routeTabsWrap.get('[data-testid="contextual-tab-antrean"]');
            expect(antreanTab.attributes('aria-current')).toBe('page');
            expect(antreanTab.text()).toContain('3');
        });
    });
});
