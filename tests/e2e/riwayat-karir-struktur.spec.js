// SUT: kolom "Struktur yang Berlaku" pada Riwayat Karir di profil karyawan (riwayat jabatan terhadap versi struktur).
// Basis data E2E memuat dua versi SK (352 pada 1 Sep 2026 dan 401 pada 1 Okt 2026) dan karyawan uji Budi dengan dua penempatan
// (seed-structure-versions.php).
import { test, expect as baseExpect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Server uji PHP berutas tunggal: halaman dapat memakan belasan detik di bawah beban, jadi batas tunggu 30 detik.
const expect = baseExpect.configure({ timeout: 30_000 });

async function openCareerTab(page) {
    await page.goto('/admin/employees/90000001/detail', { waitUntil: 'domcontentloaded' });
    await page.getByRole('tab', { name: 'Riwayat Karir' }).click();
    await expect(page.getByRole('columnheader', { name: 'Struktur yang Berlaku' })).toBeVisible();
}

test('riwayat karir: setiap penempatan menampilkan SK struktur yang berlaku pada tanggal mulainya', async ({ page }) => {
    await openCareerTab(page);

    const lama = page.getByRole('row', { name: /SK\/MUTASI\/UJI\/1/ });
    await expect(lama).toContainText('DPSB/SKPTS/352/IX/2026');

    const sekarang = page.getByRole('row').filter({ hasText: 'Sekarang' }).first();
    await expect(sekarang).toContainText('DPSB/SKPTS/401/X/2026');
    await expect(page.getByText('Tidak tercantum pada struktur SK')).toHaveCount(0);
});

test('riwayat karir: kolom struktur lolos pemindaian aksesibilitas dan tanpa istilah internal', async ({ page }) => {
    await openCareerTab(page);

    const scan = await new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(scan.violations, JSON.stringify(scan.violations.map((v) => `${v.id}: ${v.nodes.length}`))).toEqual([]);

    const text = await page.locator('body').innerText();
    expect(text).not.toMatch(/AGENTS\.md|DOMAIN\.md|DESIGN\.md|ENGINEERING\.md|SECURITY\.md|SSOT/);
});