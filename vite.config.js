import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.woff') || assetInfo.name.endsWith('.woff2')) {
                        return 'webfonts/[name][extname]';
                    }
                    return 'assets/[name][extname]';
                },
            },
        },
    },
});
