import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig({
    build: {
        emptyOutDir: true,
        outDir: 'resources/dist',
        minify: 'esbuild',
        rollupOptions: {
            input: {
                'filament-right-click': resolve(__dirname, 'resources/js/filament-right-click.js'),
                'filament-right-click-style': resolve(__dirname, 'resources/css/filament-right-click.css'),
            },
            output: {
                assetFileNames: 'filament-right-click.css',
                entryFileNames: '[name].js',
                format: 'es',
            },
        },
    },
});
