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
    server: {
        // An IPv4 address, not "localhost": on Windows that resolves to [::1], and an IPv6
        // address cannot be allowed in the Content-Security-Policy (SecurityHeaders), so the
        // dev server's CSS and JS were blocked.
        host: '127.0.0.1',
    },
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
