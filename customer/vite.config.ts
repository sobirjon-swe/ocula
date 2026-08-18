import { fileURLToPath, URL } from 'node:url'

import tailwindcss from '@tailwindcss/vite'
import react from '@vitejs/plugin-react'
import { defineConfig, loadEnv } from 'vite'

// Dev'da Mini App `/api/v1` ga o'z originidan murojaat qiladi va Vite uni
// backendga uzatadi — lokalda CORS umuman qatnashmaydi (admin/ bilan bir xil
// naqsh). Haqiqiy Telegram ichida sinash uchun tunnel (masalan ngrok) kerak
// bo'ladi — Telegram webview tashqi HTTPS manzilni talab qiladi.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')

  return {
    plugins: [react(), tailwindcss()],
    resolve: {
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
      },
    },
    server: {
      port: 5174,
      proxy: {
        '/api': {
          target: env.VITE_BACKEND_URL || 'http://localhost:8000',
          changeOrigin: true,
        },
      },
    },
  }
})
