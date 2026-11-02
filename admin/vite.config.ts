import { fileURLToPath, URL } from 'node:url'

import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// Dev'da SPA `/api/v1` ga o'z originidan murojaat qiladi va Vite uni
// backendga uzatadi — shu sababli lokalda CORS umuman qatnashmaydi.
// Prodda `VITE_API_URL` to'liq manzilga qo'yiladi (o'shanda `config/cors.php`
// dagi `FRONTEND_URL` ishlaydi).
export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')

    return {
        plugins: [react(), tailwindcss()],
        build: {
            rollupOptions: {
                output: {
                    manualChunks: {
                        'react-vendor': [
                            'react',
                            'react-dom',
                            'react-router-dom',
                        ],
                        'data-vendor': ['@tanstack/react-query', 'zustand'],
                        'i18n-vendor': ['i18next', 'react-i18next'],
                    },
                },
            },
        },
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url)),
            },
        },
        server: {
            port: 5173,
            proxy: {
                '/api': {
                    target: env.VITE_BACKEND_URL || 'http://localhost:8000',
                    changeOrigin: true,
                },
            },
        },
    }
})
