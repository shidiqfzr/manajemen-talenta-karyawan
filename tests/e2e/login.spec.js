// SUT: halaman Masuk (/login). Satu berkas uji per fitur (ENGINEERING.md §3.5).
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// The login page is public: run it without the shared admin session.
test.use({ storageState: { cookies: [], origins: [] } });

async function open(page) {
    await page.goto('/login', { waitUntil: 'networkidle' });
    await expect(page.locator('#loginForm')).toBeVisible();
}

test.describe('desktop 1366px', () => {
    test('kartu mengambang: panel foto di kiri, form di kanan', async ({ page }, info) => {
        await open(page);
        const panel = page.locator('aside');
        await expect(panel).toBeVisible();

        const p = await panel.boundingBox();
        const f = await page.locator('main').boundingBox();
        const c = await panel.locator('xpath=..').boundingBox();
        expect(p.x + p.width).toBeLessThanOrEqual(f.x + 1);
        // The photo panel is inset inside the white card.
        expect(p.x).toBeGreaterThan(c.x);
        expect(p.y).toBeGreaterThan(c.y);
        expect(f.width / c.width).toBeGreaterThan(0.4);
        expect(f.width / c.width).toBeLessThan(0.5);
        // The card floats: the page backdrop stays visible around it.
        expect(c.x).toBeGreaterThan(0);
        expect(c.y).toBeGreaterThan(0);

        await page.screenshot({ path: info.outputPath('login-desktop.png'), fullPage: true });
    });

    test('teks di panel foto terbaca (kontras ≥ 4.5:1)', async ({ page }) => {
        await open(page);
        // axe cannot resolve background images, so hide the text and sample the real pixels behind it.
        await page.addStyleTag({ content: 'aside h2, aside p { color: transparent !important; }' });
        const h = await page.locator('aside h2').boundingBox();
        const list = await page.locator('aside p').boundingBox();
        const shot = await page.screenshot({ clip: { x: h.x, y: h.y, width: Math.max(list.width, h.width), height: list.y + list.height - h.y } });
        const worst = await page.evaluate(async (b64) => {
            const img = new Image();
            img.src = 'data:image/png;base64,' + b64;
            await img.decode();
            const cv = document.createElement('canvas');
            cv.width = img.width;
            cv.height = img.height;
            const ctx = cv.getContext('2d');
            ctx.drawImage(img, 0, 0);
            const d = ctx.getImageData(0, 0, cv.width, cv.height).data;
            const lin = (x) => (x / 255 <= 0.03928 ? x / 255 / 12.92 : ((x / 255 + 0.055) / 1.055) ** 2.4);
            let max = 0;
            for (let i = 0; i < d.length; i += 4) {
                max = Math.max(max, 0.2126 * lin(d[i]) + 0.7152 * lin(d[i + 1]) + 0.0722 * lin(d[i + 2]));
            }
            return max;
        }, shot.toString('base64'));
        // White text has luminance 1.0; even the brightest backdrop pixel must give ≥ 4.5:1.
        expect(1.05 / (worst + 0.05)).toBeGreaterThanOrEqual(4.5);
    });

    test('tanpa pelanggaran aksesibilitas WCAG 2 AA', async ({ page }) => {
        await open(page);
        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target).join(' | ')}`)).toEqual([]);
    });

    test('satu h1, tanpa landmark ganda, bahasa dokumen id', async ({ page }) => {
        await open(page);
        await expect(page.locator('h1')).toHaveCount(1);
        await expect(page.locator('h1')).toHaveText('Selamat Datang');
        await expect(page.locator('html')).toHaveAttribute('lang', 'id');
        await expect(page.locator('main')).toHaveCount(1);
    });

    test('kosakata baku: Indonesia, tanpa nama dokumen internal', async ({ page }) => {
        await open(page);
        // The photo panel carries a headline and a description only.
        await expect(page.locator('aside h2')).toHaveText('Kelola Seluruh Siklus Talenta Pegawai');
        await expect(page.locator('aside li')).toHaveCount(0);
        const text = await page.locator('body').innerText();
        for (const banned of ['Admin Login', 'Secure', 'Efficient', 'Modern', 'All rights reserved', 'AGENTS', 'DOMAIN.md', 'DESIGN.md', 'SSOT']) {
            expect(text).not.toContain(banned);
        }
        await expect(page.getByText(`© ${new Date().getFullYear()} PTPN IV Regional V`)).toBeVisible();
    });

    test('fokus awal di username, urutan Tab: username → password → tampilkan → masuk', async ({ page }) => {
        await open(page);
        await expect(page.locator('#username')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.locator('#password')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.locator('#togglePassword')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.locator('#submitBtn')).toBeFocused();
    });

    test('tombol tampilkan password mengubah tipe input dan label', async ({ page }) => {
        await open(page);
        await page.locator('#password').fill('rahasia123');
        await page.locator('#togglePassword').click();
        await expect(page.locator('#password')).toHaveAttribute('type', 'text');
        await expect(page.locator('#togglePassword')).toHaveAttribute('aria-label', 'Sembunyikan password');
        await expect(page.locator('#togglePassword')).toHaveAttribute('aria-pressed', 'true');
        await page.locator('#togglePassword').click();
        await expect(page.locator('#password')).toHaveAttribute('type', 'password');
    });

    test('login gagal: pesan terbaca, username dipertahankan, password dikosongkan', async ({ page }, info) => {
        await open(page);
        await page.locator('#username').fill('bukan.pengguna');
        await page.locator('#password').fill('salah-total');
        await page.locator('#submitBtn').click();

        await expect(page.getByRole('alert')).toContainText('Username/Email atau password salah.');
        await expect(page.locator('#username')).toHaveValue('bukan.pengguna');
        await expect(page.locator('#username')).toHaveAttribute('aria-invalid', 'true');
        await expect(page.locator('#password')).toHaveValue('');
        await expect(page.locator('#submitBtn')).toBeEnabled();
        await expect(page.locator('#btnText')).toHaveText('Masuk');

        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(results.violations).toEqual([]);
        await page.screenshot({ path: info.outputPath('login-error.png'), fullPage: true });
    });

    test('Caps Lock aktif memunculkan peringatan di bawah password', async ({ page }) => {
        await open(page);
        await page.locator('#password').focus();
        await expect(page.locator('#capsLockHint')).toBeHidden();
        // Synthetic key presses cannot toggle the OS Caps Lock state, so dispatch an event that carries the modifier.
        await page.locator('#password').dispatchEvent('keydown', { key: 'A', modifierCapsLock: true });
        await expect(page.locator('#capsLockHint')).toBeVisible();
        await page.locator('#username').focus();
        await expect(page.locator('#capsLockHint')).toBeHidden();
    });

    test('setelah login gagal fokus pindah ke password, bukan username', async ({ page }) => {
        await open(page);
        await page.locator('#username').fill('bukan.pengguna');
        await page.locator('#password').fill('salah-total');
        await page.locator('#submitBtn').click();
        await expect(page.getByRole('alert')).toBeVisible();
        await expect(page.locator('#password')).toBeFocused();
    });

    test('kolom form tidak berubah ukuran saat fokus (tanpa lompatan tata letak)', async ({ page }) => {
        await open(page);
        const before = await page.locator('#password').boundingBox();
        await page.locator('#password').focus();
        const after = await page.locator('#password').boundingBox();
        expect(after).toEqual(before);
    });
});

test.describe('kepatuhan Design System (DESIGN.md)', () => {
    test('tanpa gradien dekoratif dan tanpa efek kaca buram', async ({ page }) => {
        await open(page);
        const offenders = await page.evaluate(() =>
            [...document.querySelectorAll('body, body *')]
                .filter((el) => {
                    const cs = getComputedStyle(el);
                    return cs.backgroundImage.includes('gradient') || (cs.backdropFilter && cs.backdropFilter !== 'none');
                })
                .map((el) => el.tagName.toLowerCase() + (el.id ? '#' + el.id : ''))
        );
        expect(offenders).toEqual([]);
    });

    test('input: tinggi 44px, radius 8px, border surface-300 (bukan surface-200)', async ({ page }) => {
        await open(page);
        // The username field is autofocused; blur it so the resting border is measured.
        await page.locator('#username').blur();
        const tokens = await page.evaluate(() => {
            const probe = document.createElement('i');
            document.body.appendChild(probe);
            const rgb = (v) => {
                probe.style.color = `var(${v})`;
                return getComputedStyle(probe).color;
            };
            const out = { s300: rgb('--color-surface-300') };
            probe.remove();
            return out;
        });
        for (const sel of ['#username', '#password']) {
            const m = await page.locator(sel).evaluate((el) => {
                const cs = getComputedStyle(el);
                return { h: el.getBoundingClientRect().height, r: cs.borderTopLeftRadius, b: cs.borderTopColor };
            });
            expect(m.h, sel).toBe(44);
            expect(m.r, sel).toBe('8px');
            // Retries: the border fades over 150ms after the field loses focus.
            await expect(page.locator(sel)).toHaveCSS('border-top-color', tokens.s300);
        }
    });

    test('fokus keyboard: border primary-600 + ring, tidak pernah tanpa border', async ({ page }) => {
        await open(page);
        await page.locator('body').click({ position: { x: 5, y: 5 } });
        await page.keyboard.press('Tab');
        await expect(page.locator('#username')).toBeFocused();
        const m = await page.locator('#username').evaluate((el) => {
            const cs = getComputedStyle(el);
            const probe = document.createElement('i');
            document.body.appendChild(probe);
            probe.style.color = 'var(--color-primary-600)';
            const primary = getComputedStyle(probe).color;
            probe.remove();
            return { border: cs.borderTopColor, primary, shadow: cs.boxShadow };
        });
        expect(m.border).toBe(m.primary);
        expect(m.shadow).not.toBe('none');
    });

    test('tombol Masuk: datar (primary-600), radius 12px, tinggi ≥ 44px, ring fokus ber-offset', async ({ page }) => {
        await open(page);
        const m = await page.locator('#submitBtn').evaluate((el) => {
            const cs = getComputedStyle(el);
            const probe = document.createElement('i');
            document.body.appendChild(probe);
            probe.style.color = 'var(--color-primary-600)';
            const primary = getComputedStyle(probe).color;
            probe.remove();
            return { bg: cs.backgroundColor, img: cs.backgroundImage, r: cs.borderTopLeftRadius, h: el.getBoundingClientRect().height, primary };
        });
        expect(m.img).toBe('none');
        expect(m.bg).toBe(m.primary);
        expect(m.r).toBe('12px');
        expect(m.h).toBeGreaterThanOrEqual(44);
    });

    test('foto panel masih terbaca: overlay tidak lebih pekat dari 70%', async ({ page }) => {
        await open(page);
        const alpha = await page.locator('aside [data-overlay]').evaluate((el) => {
            // Tailwind v4 may report the colour as rgba(), oklab() or color(): read the alpha from any of them.
            const bg = getComputedStyle(el).backgroundColor;
            const m = bg.match(/\/\s*([\d.]+)%?\s*\)/) || bg.match(/rgba\([^)]*,\s*([\d.]+)\)/);
            if (!m) return 1;
            const v = parseFloat(m[1]);
            return bg.includes('%') && v > 1 ? v / 100 : v;
        });
        expect(alpha).toBeLessThanOrEqual(0.7);
    });
});

test.describe('seluler 375px', () => {
    test.use({ viewport: { width: 375, height: 667 }, hasTouch: true, isMobile: true });

    test('satu kolom: panel foto tersembunyi, tanpa gulir horizontal', async ({ page }, info) => {
        await open(page);
        await expect(page.locator('aside')).toBeHidden();
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow).toBeLessThanOrEqual(0);
        await page.screenshot({ path: info.outputPath('login-mobile.png'), fullPage: true });
    });

    test('target sentuh ≥ 44px dan seluruh form muat di layar tanpa gulir', async ({ page }) => {
        await open(page);
        for (const sel of ['#username', '#password', '#togglePassword', '#submitBtn']) {
            const box = await page.locator(sel).boundingBox();
            expect(box.height, sel).toBeGreaterThanOrEqual(44);
            expect(box.width, sel).toBeGreaterThanOrEqual(44);
        }
        const bottom = await page.locator('#submitBtn').boundingBox();
        expect(bottom.y + bottom.height).toBeLessThanOrEqual(667);
    });

    test('teks input ≥ 16px agar iOS tidak memperbesar halaman saat fokus', async ({ page }) => {
        await open(page);
        const size = await page.locator('#username').evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
        expect(size).toBeGreaterThanOrEqual(16);
    });

    test('tanpa pelanggaran aksesibilitas WCAG 2 AA', async ({ page }) => {
        await open(page);
        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa']).analyze();
        expect(results.violations).toEqual([]);
    });
});

// The PTPN4 logo and the heading must read as one centered group at every width DESIGN.md requires.
for (const [width, height] of [[375, 667], [1366, 768], [1920, 1080]]) {
    test.describe(`logo dan judul ${width}px`, () => {
        test.use({ viewport: { width, height } });

        test('logo PTPN4 di tengah, tepat di atas judul yang juga di tengah', async ({ page }, info) => {
            await open(page);
            const logo = page.locator('main img[alt="Logo PTPN IV"]');
            await expect(logo).toBeVisible();
            expect(await logo.evaluate((el) => el.complete && el.naturalWidth > 0)).toBe(true);
            // File names are case-sensitive on Linux.
            expect(await logo.getAttribute('src')).toContain('/images/logo-PTPN4.png');
            // The only logo on the page for now.
            await expect(page.locator('img')).toHaveCount(1);

            const form = await page.locator('#loginForm').boundingBox();
            const center = form.x + form.width / 2;
            const l = await logo.boundingBox();
            const h = await page.locator('h1').boundingBox();
            expect(Math.abs(l.x + l.width / 2 - center)).toBeLessThanOrEqual(1);
            expect(l.y + l.height).toBeLessThanOrEqual(h.y);
            // Directly above the heading, not parked at the top of the column.
            expect(h.y - (l.y + l.height)).toBeLessThanOrEqual(24);

            for (const sel of ['h1', 'h1 + p']) {
                await expect(page.locator(sel)).toHaveCSS('text-align', 'center');
            }
            // Field labels stay left-aligned so they read straight into their inputs.
            await expect(page.locator('label[for=username]')).toHaveCSS('text-align', 'start');

            await page.screenshot({ path: info.outputPath(`login-${width}.png`), fullPage: true });
        });

        test('seluruh isi muat di layar tanpa gulir', async ({ page }) => {
            await open(page);
            const scroll = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
            expect(scroll).toBeLessThanOrEqual(0);
        });
    });
}

// Real page heights: the screen minus the browser toolbar and the OS taskbar, including Windows display scaling.
for (const [width, height] of [[1366, 655], [1536, 730], [1280, 600], [1093, 490], [375, 560]]) {
    test.describe(`tanpa scrollbar ${width}x${height}`, () => {
        test.use({ viewport: { width, height } });

        test('halaman muat tanpa gulir vertikal maupun horizontal', async ({ page }, info) => {
            await open(page);
            const overflow = await page.evaluate(() => ({
                y: document.documentElement.scrollHeight - window.innerHeight,
                x: document.documentElement.scrollWidth - document.documentElement.clientWidth,
            }));
            expect(overflow.y).toBeLessThanOrEqual(0);
            expect(overflow.x).toBeLessThanOrEqual(0);
            await page.screenshot({ path: info.outputPath(`fit-${width}x${height}.png`) });
        });
    });
}

// The error alert adds height, so it must fit on the real laptop viewports too.
for (const [width, height] of [[1366, 651], [1280, 600]]) {
    test.describe(`login gagal tanpa scrollbar ${width}x${height}`, () => {
        test.use({ viewport: { width, height } });

        test('pesan galat tidak memunculkan scrollbar', async ({ page }, info) => {
            await open(page);
            await page.locator('#username').fill('bukan.pengguna');
            await page.locator('#password').fill('salah-total');
            await page.locator('#submitBtn').click();
            await expect(page.getByRole('alert')).toBeVisible();
            const y = await page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight);
            expect(y).toBeLessThanOrEqual(0);
            await page.screenshot({ path: info.outputPath(`fit-error-${width}x${height}.png`) });
        });
    });
}

// A laptop window with the browser toolbar must look the same as full screen, not a compact variant.
test.describe('tampilan jendela biasa sama dengan layar penuh', () => {
    const measure = (page) =>
        page.evaluate(() => {
            const px = (sel, prop) => getComputedStyle(document.querySelector(sel))[prop];
            return {
                h1: px('h1', 'fontSize'),
                h2: px('aside h2', 'fontSize'),
                subtitleGap: px('h1 + p', 'marginBottom'),
                logo: document.querySelector('main img').getBoundingClientRect().height,
                card: document.querySelector('aside').parentElement.getBoundingClientRect().width,
                fieldGap: document.querySelector('#password').getBoundingClientRect().top - document.querySelector('#username').getBoundingClientRect().bottom,
                headlineLines: Math.round(document.querySelector('aside h2').getBoundingClientRect().height / parseFloat(px('aside h2', 'lineHeight'))),
            };
        });

    test('ukuran teks, logo, dan jarak identik pada 1366x655 dan 1366x768', async ({ page }) => {
        await page.setViewportSize({ width: 1366, height: 768 });
        await open(page);
        const full = await measure(page);

        await page.setViewportSize({ width: 1366, height: 655 });
        await open(page);
        expect(await measure(page)).toEqual(full);
    });
});

test.describe('tablet 820px', () => {
    test.use({ viewport: { width: 820, height: 1180 } });

    test('form tetap terpusat dan tidak melebar', async ({ page }) => {
        await open(page);
        const box = await page.locator('#loginForm').boundingBox();
        expect(box.width).toBeLessThanOrEqual(384 + 1);
        expect(Math.abs(box.x + box.width / 2 - 410)).toBeLessThanOrEqual(2);
    });
});
