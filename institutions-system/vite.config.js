import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js', 'resources/css/app.css'],
            refresh: true,
        }),
    ],
    server: {
        host: 'localhost',
        port: 3000,
        strictPort: true,
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
    },
});