import { defineConfig } from '@playwright/test'
export default defineConfig({
    testDir: './tests',
    testMatch: '**/*.pwa.ts',
    timeout: 60000,
    use: {
        baseURL: 'http://127.0.0.1:4176',
        channel: 'chrome',
        headless: true,
        serviceWorkers: 'allow',
        permissions: ['geolocation'],
        geolocation: { latitude: 41.3, longitude: 69.2 },
        viewport: { width: 390, height: 844 },
    },
    webServer: {
        command: 'npm run preview -- --host 127.0.0.1 --port 4176',
        url: 'http://127.0.0.1:4176',
        reuseExistingServer: false,
    },
    reporter: 'list',
})
