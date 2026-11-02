// Login admin satu kali, lalu sesi dipakai ulang oleh seluruh pengujian E2E.
import { test as setup, expect } from '@playwright/test';

// Akun bawaan seeder pada basis data sementara E2E; bukan kredensial lingkungan nyata.
const ADMIN = { email: 'admin@example.com', password: 'ptpn4reg5' };

setup('login admin', async ({ page }) => {
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.locator('#loginForm input:not([type=hidden]):not([type=password]):not([type=checkbox])').first().fill(ADMIN.email);
    await page.locator('#loginForm input[type=password]').first().fill(ADMIN.password);
    await Promise.all([
        page.waitForURL((url) => !url.pathname.startsWith('/login'), { waitUntil: 'domcontentloaded' }),
        page.locator('#loginForm button[type=submit]').click(),
    ]);
    await expect(page).not.toHaveURL(/\/login/);
    await page.context().storageState({ path: 'tests/e2e/.tmp/admin.json' });
});
