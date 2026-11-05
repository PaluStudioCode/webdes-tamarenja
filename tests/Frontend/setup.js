import { config, enableAutoUnmount } from '@vue/test-utils';
import { reactive } from 'vue';
import { afterEach, beforeEach, vi } from 'vitest';

enableAutoUnmount(afterEach);

const createDefaultPageState = () => ({
    props: {
        auth: { user: null },
        flash: { notification: null },
        profil_desa: {
            nama_desa: 'Tamarenja',
            kecamatan: 'Sindue Tobata',
            kabupaten: 'Donggala',
            provinsi: 'Sulawesi Tengah',
            alamat_kantor: 'Jl. Trans Sulawesi No. 12, Desa Tamarenja',
            email_desa: 'pemdes@tamarenja.desa.id',
            no_wa_pelayanan: '6281234567890',
            jam_pelayanan: 'Senin - Jumat, 08.00 - 15.00 WITA',
            tahun_anggaran_aktif: 2026,
            logo_desa_path: null,
            daftar_dusun: ['Dusun I', 'Dusun II', 'Dusun III', 'Dusun IV'],
        },
        filter_tahun: 2026,
        available_tahun: [2026, 2025],
        antrean_surat_count: 0,
    },
    url: '/',
    component: 'TestPage',
});

const mockPage = reactive(createDefaultPageState());

const resetMockPage = () => {
    const defaults = createDefaultPageState();
    Object.keys(mockPage.props).forEach((key) => {
        delete mockPage.props[key];
    });
    Object.assign(mockPage.props, defaults.props);
    mockPage.url = defaults.url;
    mockPage.component = defaults.component;
};

// Mock Ziggy route() helper
const mockRoute = vi.fn((name, params) => {
    if (!name) return { current: mockRoute.current };
    let url = '/' + name.replace(/\./g, '/');
    if (params !== undefined && params !== null) {
        if (typeof params === 'object') {
            const query = new URLSearchParams(params).toString();
            if (query) url += '?' + query;
        } else {
            url += '/' + String(params);
        }
    }
    return url;
});
mockRoute.current = vi.fn(() => false);

global.route = mockRoute;
config.global.mocks = {
    ...config.global.mocks,
    route: mockRoute,
};

// Mock Link and Head components dari Inertia
config.global.components = {
    ...config.global.components,
    Link: {
        name: 'Link',
        props: ['href', 'method', 'as', 'data'],
        template: '<a :href="href" @click.prevent><slot /></a>',
    },
    Head: {
        name: 'Head',
        props: ['title'],
        template: '<!-- Head: <title>{{ title }}</title> -->',
    },
};

const mockRouter = {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    reload: vi.fn(),
    visit: vi.fn(),
    on: vi.fn(() => () => {}),
};

beforeEach(() => {
    resetMockPage();
    mockRoute.mockClear();
    mockRoute.current.mockReset();
    mockRoute.current.mockReturnValue(false);
    Object.values(mockRouter).forEach((fn) => {
        if (typeof fn?.mockClear === 'function') {
            fn.mockClear();
        }
    });
});

// Mock @inertiajs/vue3 composables & helpers
vi.mock('@inertiajs/vue3', async (importOriginal) => {
    let actual = {};
    try {
        actual = await importOriginal();
    } catch {
        // Fallback jika importOriginal tidak tersedia di environment mock
    }

    return {
        ...actual,
        Link: {
            name: 'Link',
            props: ['href', 'method', 'as', 'data'],
            template: '<a :href="href" @click.prevent><slot /></a>',
        },
        Head: {
            name: 'Head',
            props: ['title'],
            template: '<!-- Head: <title>{{ title }}</title> -->',
        },
        usePage: () => mockPage,
        useForm: (initialData = {}) =>
            reactive({
                ...initialData,
                data: () => ({ ...initialData }),
                post: vi.fn(),
                put: vi.fn(),
                patch: vi.fn(),
                delete: vi.fn(),
                get: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
                setError: vi.fn(),
                errors: {},
                hasErrors: false,
                processing: false,
                progress: null,
                wasSuccessful: false,
                recentlySuccessful: false,
            }),
        router: mockRouter,
    };
});
