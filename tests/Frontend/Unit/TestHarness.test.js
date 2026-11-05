import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

describe('Frontend Test Harness & Setup Mocks', () => {
    it('memverifikasi route() helper Ziggy tersedia secara global', () => {
        expect(typeof route).toBe('function');
        expect(route('panel.dashboard')).toBe('/panel/dashboard');
        expect(typeof route().current).toBe('function');
    });

    it('memverifikasi komponen Vue dapat di-mount dengan @vue/test-utils di environment jsdom', () => {
        const TestComponent = defineComponent({
            props: {
                title: { type: String, required: true },
            },
            setup(props) {
                return () => h('div', { class: 'tabular-nums text-blue-700' }, props.title);
            },
        });

        const wrapper = mount(TestComponent, {
            props: {
                title: 'Desa Tamarenja',
            },
        });

        expect(wrapper.text()).toBe('Desa Tamarenja');
        expect(wrapper.classes()).toContain('tabular-nums');
        expect(wrapper.classes()).toContain('text-blue-700');
    });

    it('memverifikasi mock komponen Link dan Head Inertia terpasang', () => {
        const SamplePage = defineComponent({
            template: `
                <div>
                    <Head title="Beranda Desa" />
                    <Link href="/layanan-mandiri">Ajukan Surat</Link>
                </div>
            `,
        });

        const wrapper = mount(SamplePage);
        const link = wrapper.find('a');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe('/layanan-mandiri');
        expect(link.text()).toBe('Ajukan Surat');
    });
});
