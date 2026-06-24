import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // app.js — legacy Blade pages (gradually migrated away).
            // app.tsx — Inertia/React entry for converted pages.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
