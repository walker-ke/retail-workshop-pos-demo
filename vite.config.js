import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    css: {preprocessorOptions: {scss: {silenceDeprecations: ['import','global-builtin','color-functions','legacy-js-api','if-function']}}},
    plugins: [
        laravel({
            input: [
                'resources/sass/app.scss',
                'resources/js/app.js',
                'resources/sass/login.scss',
                'resources/js/login.js',
            ],
            refresh: true,
        }),
    ],
});
