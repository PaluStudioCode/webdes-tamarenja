import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: './tests/Frontend/setup.js',
    },
    resolve: {
        alias: {
            '@': '/resources/js',
            'ziggy-js': '/vendor/tightenco/ziggy',
        },
    },
});
