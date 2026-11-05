import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import * as InertiaVue from '@inertiajs/vue3';
import { useNotification } from '@/Composables/useNotification';
import Login from '@/Pages/Auth/Login.vue';

describe('Sub-Fase 4.1: Halaman Auth/Login.vue di dalam AuthLayout.vue', () => {
    beforeEach(() => {
        useNotification().clear();
    });

    it('merender identitas desa tanpa tautan registrasi maupun lupa kata sandi bawaan Breeze', () => {
        const wrapper = mount(Login);

        expect(wrapper.find('[data-testid="auth-layout"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Pemerintah Desa Tamarenja');
        expect(wrapper.text()).toContain('Panel Operasional & Administrasi Desa');

        // Dilarang menampilkan tautan Forgot your password / Lupa kata sandi atau Register / Daftar
        expect(wrapper.text()).not.toMatch(/forgot your password|lupa kata sandi|register|daftar akun/i);
        expect(wrapper.find('a[href*="register"]').exists()).toBe(false);
        expect(wrapper.find('a[href*="forgot-password"]').exists()).toBe(false);

        // Placeholder tidak mengulang kata label
        expect(wrapper.get('[data-testid="login-email-input"]').attributes('placeholder')).not.toMatch(
            /masukkan email/i,
        );
    });

    it('menampilkan inline field error tanpa memicu toast ganda serta mengunci tombol + spinner saat processing', () => {
        const customForm = reactive({
            email: 'operator@desa.id',
            password: '',
            remember: false,
            errors: {
                email: 'Kombinasi email dan kata sandi tidak sesuai.',
            },
            processing: true,
            post: vi.fn(),
            reset: vi.fn(),
        });

        const useFormSpy = vi
            .spyOn(InertiaVue, 'useForm')
            .mockReturnValueOnce(customForm);

        const wrapper = mount(Login);

        // Inline field error tampil di bawah kolom email
        expect(wrapper.get('[data-testid="login-email-error"]').text()).toBe(
            'Kombinasi email dan kata sandi tidak sesuai.',
        );
        expect(wrapper.get('[data-testid="login-email-input"]').classes()).toContain(
            'border-red-500',
        );

        // Tidak ada toast duplikat yang terpicu untuk error validasi field
        expect(useNotification().toasts.value).toHaveLength(0);

        // Saat processing = true tombol terkunci disabled & menampilkan spinner
        const submitBtn = wrapper.get('[data-testid="login-submit-btn"]');
        expect(submitBtn.attributes('disabled')).toBeDefined();
        expect(submitBtn.find('[data-testid="button-spinner"]').exists()).toBe(true);

        useFormSpy.mockRestore();
    });
});
