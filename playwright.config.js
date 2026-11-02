import { defineConfig, devices } from '@playwright/test';

const PORT = 8765;

/**
 * Evaluasi UI/UX otomatis di browser nyata (ENGINEERING.md §3.8).
 * Server uji memakai basis data SQLite sementara yang di-seed ulang setiap proses,
 * sehingga data pengembangan tidak tersentuh.
 */
export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './test-results',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 90_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { outputFolder: 'playwright-report', open: 'never' }]],
    use: {
        baseURL: `http://127.0.0.1:${PORT}`,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
        locale: 'id-ID',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.js/ },
        {
            name: 'chromium',
            dependencies: ['setup'],
            testIgnore: /auth\.setup\.js/,
            use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 }, storageState: 'tests/e2e/.tmp/admin.json' },
        },
    ],
    webServer: {
        command: 'node tests/e2e/support/serve.mjs',
        url: `http://127.0.0.1:${PORT}/login`,
        timeout: 180_000,
        reuseExistingServer: false,
    },
});
