import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js',
                // Public website only (landing/*), so the staff bundle stays lean.
                'resources/scss/landing.scss',
                'resources/js/landing.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        // ApexCharts is split into its own lazily-loaded chunk (~200 kB gzip),
        // fetched only on pages that render an x-ui.chart.
        chunkSizeWarningLimit: 800,
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3's SCSS still uses @import and global built-ins;
                // silence those upstream deprecations so real warnings stand out.
                quietDeps: true,
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
            },
        },
    },
});
