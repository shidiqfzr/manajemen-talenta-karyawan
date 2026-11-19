// SUT: Sebaran Formasi (tab Master Formasi) dan Monitoring Formasi Kosong. Satu berkas uji per fitur (ENGINEERING.md §3.5).
import { test, expect as baseExpect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Server uji PHP berutas tunggal: halaman dan aksi Livewire dapat memakan belasan detik di bawah beban, jadi batas tunggu 30 detik (bukan 10).
const expect = baseExpect.configure({ timeout: 30_000 });

// Pindai area konten halaman; kerangka layout bersama diuji pada spesifikasi lain.
const scanMain = (page) => new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();

const FORBIDDEN_TERMS = /AGENTS\.md|DOMAIN\.md|DESIGN\.md|ENGINEERING\.md|SECURITY\.md|SSOT/;

const horizontalOverflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

async function openSebaran(page) {
    await page.goto('/admin/master/sebaran', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Sebaran Formasi' }).first()).toBeVisible();
}

test('navigasi: tiga bagian utama, Formasi aktif dengan tampilan Sebaran Jabatan, KPI tampil, tanpa istilah internal', async ({ page }) => {
    await openSebaran(page);

    const nav = page.getByRole('navigation', { name: 'Bagian Master Data' });
    for (const label of ['Struktur & Formasi', 'Surat Keputusan', 'Riwayat Perubahan']) {
        await expect(nav.getByRole('link', { name: label, exact: true })).toBeVisible();
    }
    await expect(nav.getByRole('link', { name: 'Struktur & Formasi', exact: true })).toHaveAttribute('aria-current', 'page');

    // Empat tampilan Formasi berada pada satu pengalih, bukan tab terpisah.
    const views = page.getByRole('navigation', { name: 'Tampilan Formasi' });
    for (const label of ['Unit Kerja', 'Sebaran Jabatan', 'Jabatan Penampungan', 'Versi Struktur']) {
        await expect(views.getByRole('link', { name: label, exact: true })).toBeVisible();
    }
    await expect(views.getByRole('link', { name: 'Sebaran Jabatan', exact: true })).toHaveAttribute('aria-current', 'page');

    for (const kpi of ['Formasi Jabatan', 'Terisi', 'Formasi Kosong', 'Diisi Pj/Plt']) {
        await expect(page.getByText(kpi, { exact: true }).first()).toBeVisible();
    }

    const text = (await page.locator('body').innerText()).toLowerCase();
    expect(text).not.toMatch(FORBIDDEN_TERMS);
    expect(text).not.toContain('lowong');
});

test('per jabatan: satu baris rumpun, dapat dibuka menjadi rincian per unit', async ({ page }) => {
    await openSebaran(page);

    // The debounced search update collapses any open row (by design), so wait for it before opening one.
    await Promise.all([
        page.waitForResponse((response) => response.url().includes('/livewire') && response.request().method() === 'POST'),
        page.getByPlaceholder('Cari jabatan atau unit kerja...').fill('Asisten Afdeling'),
    ]);
    const toggle = page.getByRole('button', { name: /^Asisten Afdeling\b/ }).first();
    await expect(toggle).toBeVisible();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    // Retry the whole open step: a late Livewire re-render under load can collapse the row again.
    const detail = page.locator(`#${await toggle.getAttribute('aria-controls')}`);
    await expect(async () => {
        if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
            await toggle.click();
        }
        await expect(toggle).toHaveAttribute('aria-expanded', 'true', { timeout: 3000 });
        await expect(detail.getByRole('cell', { name: /^Asisten Afdeling I Bidang/ }).first()).toBeVisible({ timeout: 3000 });
    }).toPass({ timeout: 30000 });
    await expect(detail.getByRole('link').first()).toHaveAttribute('href', /\/admin\/master\/formasi\/\d+/);

    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
});

test('kontrol tampilan dapat dioperasikan dengan papan ketik', async ({ page }) => {
    await openSebaran(page);

    const group = page.getByRole('radiogroup', { name: 'Tampilan sebaran' });
    const perJabatan = group.getByRole('radio', { name: 'Per Jabatan' });
    const perUnit = group.getByRole('radio', { name: 'Per Unit' });
    await expect(perJabatan).toHaveAttribute('aria-checked', 'true');
    await expect(perJabatan).toHaveAttribute('tabindex', '0');
    await expect(perUnit).toHaveAttribute('tabindex', '-1');

    await perJabatan.focus();
    await page.keyboard.press('ArrowRight');
    await expect(perUnit).toHaveAttribute('aria-checked', 'true');
    await expect(page.getByRole('columnheader', { name: /Unit Kerja/ }).first()).toBeVisible();

    await page.keyboard.press('ArrowLeft');
    await expect(perJabatan).toHaveAttribute('aria-checked', 'true');
});

test('tanpa overflow horizontal di layar lebar maupun ponsel, dan lolos pemindaian aksesibilitas', async ({ page }) => {
    await openSebaran(page);
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

    const result = await scanMain(page);
    expect(result.violations, JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))).toEqual([]);

    await page.setViewportSize({ width: 390, height: 844 });
    await openSebaran(page);
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
});

test('baris formasi kosong menyediakan tautan Isi lewat Mutasi ke formulir mutasi', async ({ page }) => {
    await openSebaran(page);

    const perUnit = page.getByRole('radio', { name: 'Per Unit' });
    await perUnit.click();
    await expect(perUnit).toHaveAttribute('aria-checked', 'true');
    const link = page.getByRole('link', { name: 'Isi lewat Mutasi' }).first();
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', /\/admin\/mutasi\/create\?.*jabatan=/);
});

test('Monitoring Formasi Kosong di Mutasi memakai istilah resmi dan jumlah yang sama dengan Sebaran', async ({ page }) => {
    await openSebaran(page);
    const kosongCard = page.getByText('Formasi Kosong', { exact: true }).first().locator('xpath=ancestor::*[self::button or self::div][1]');
    const kosongNumber = (await kosongCard.innerText()).match(/\d[\d.]*/)?.[0];

    await page.goto('/admin/mutasi?tab=vacancies', { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('Daftar Formasi Kosong').first()).toBeVisible();
    const body = (await page.locator('main').innerText()).toLowerCase();
    expect(body).not.toContain('jabatan lowong');
    if (kosongNumber) {
        await expect(page.getByText(new RegExp(`${kosongNumber.replace('.', '\\.')}\\s*Formasi`)).first()).toBeVisible();
    }
});
