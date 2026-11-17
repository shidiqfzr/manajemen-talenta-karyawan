// SUT: Jabatan Penampungan (tab Master Formasi: Advisor dan Diperbantukan). Satu berkas uji per fitur (ENGINEERING.md §3.5).
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const scanMain = (page) => new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
const scanDialog = (page) => new AxeBuilder({ page }).include('[role=dialog]').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();

const FORBIDDEN_TERMS = /AGENTS\.md|DOMAIN\.md|DESIGN\.md|ENGINEERING\.md|SECURITY\.md|SSOT/;
const horizontalOverflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

async function openTab(page) {
    await page.goto('/admin/master/penampungan', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Jabatan Penampungan' }).first()).toBeVisible();
    // Clicks before Livewire/Alpine finish starting are silently lost under load.
    await page.waitForFunction(() => window.Livewire && window.Alpine && document.querySelector('[wire\\:id]'));
    await page.waitForLoadState('load');
}

// Open a dialog even when the first click lands before the page is interactive.
async function showDialog(page, trigger, name) {
    const dialog = page.getByRole('dialog', { name });
    await expect(async () => {
        if (!(await dialog.isVisible())) {
            await trigger.click();
        }
        await expect(dialog).toBeVisible({ timeout: 3000 });
    }).toPass({ timeout: 30000 });
    return dialog;
}

test('tab menampilkan jabatan bawaan, KPI, dan keterangan bahwa bukan formasi', async ({ page }) => {
    await openTab(page);

    await expect(page.getByRole('navigation', { name: 'Bagian Master Data' }).getByRole('link', { name: 'Jabatan Penampungan' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByText('Advisor RM-2', { exact: true }).first()).toBeVisible();
    await expect(page.getByText('Karyawan Diperbantukan RM-3', { exact: true }).first()).toBeVisible();
    await expect(page.getByText(/bukan bagian dari Master Formasi/)).toBeVisible();

    const text = (await page.locator('body').innerText()).toLowerCase();
    expect(text).not.toMatch(FORBIDDEN_TERMS);
    for (const word of ['kursi', 'seat', 'pegawai']) {
        expect(text).not.toContain(word);
    }
});

test('alur lengkap: buat jabatan, buka pada unit, lihat bezetting, tutup dengan alasan', async ({ page }) => {
    await openTab(page);

    // Buat jabatan baru
    const createDialog = await showDialog(page, page.getByRole('button', { name: 'Tambah Jabatan' }), 'Tambah Jabatan Penampungan');
    await createDialog.getByLabel(/Nama Jabatan/).fill('Asisten Diperbantukan Uji');
    await createDialog.getByLabel(/Jenis/).selectOption('diperbantukan');
    await createDialog.getByLabel(/RM Band/).selectOption('RM-3');
    await createDialog.getByRole('button', { name: 'Simpan Jabatan' }).click();
    await expect(page.getByRole('status').filter({ hasText: "Jabatan penampungan 'Asisten Diperbantukan Uji' dibuat" })).toBeVisible();

    // Buka pada unit
    const row = page.locator('tbody tr', { hasText: 'Asisten Diperbantukan Uji' });
    await row.getByRole('button', { name: /^Aksi untuk/ }).click();
    await page.getByRole('menuitem', { name: 'Buka di Unit' }).click();
    const openDialog = page.getByRole('dialog', { name: 'Buka Jabatan pada Unit' });
    await expect(openDialog).toBeVisible();
    await openDialog.getByLabel(/Unit Kerja/).selectOption({ label: 'Unit Group Kalimantan Barat' });
    await openDialog.getByRole('button', { name: 'Buka pada Unit' }).click();
    await expect(page.getByRole('status').filter({ hasText: "dibuka pada unit 'Unit Group Kalimantan Barat'" })).toBeVisible();

    // Bezetting per unit
    await page.getByRole('radio', { name: 'Per Unit' }).click();
    const unitRow = page.locator('tbody tr', { hasText: 'Asisten Diperbantukan Uji' });
    await expect(unitRow).toContainText('Unit Group Kalimantan Barat');
    await expect(unitRow).toContainText('Dibuka');

    // Tutup: alasan wajib
    await unitRow.getByRole('button', { name: /^Aksi untuk/ }).click();
    await page.getByRole('menuitem', { name: 'Tutup di Unit' }).click();
    const closeDialog = page.getByRole('dialog', { name: 'Tutup Jabatan pada Unit' });
    await closeDialog.getByLabel(/Alasan Perubahan/).fill('pendek');
    await closeDialog.getByRole('button', { name: 'Tutup pada Unit' }).click();
    await expect(closeDialog.getByRole('alert')).toContainText('minimal 10 karakter');
    await closeDialog.getByLabel(/Alasan Perubahan/).fill('Tidak lagi dipakai pada unit ini');
    await closeDialog.getByRole('button', { name: 'Tutup pada Unit' }).click();
    await expect(page.getByRole('status').filter({ hasText: 'ditutup pada unit' })).toBeVisible();
});

test('dialog: fokus masuk ke dialog, Esc menutup, dan fokus kembali ke tombol pemicu', async ({ page }) => {
    await openTab(page);

    const trigger = page.getByRole('button', { name: 'Tambah Jabatan' });
    await trigger.focus();
    await trigger.click();
    const dialog = page.getByRole('dialog', { name: 'Tambah Jabatan Penampungan' });
    await expect(dialog).toBeVisible();
    expect(await dialog.evaluate((el) => el.contains(document.activeElement))).toBe(true);

    await page.keyboard.press('Escape');
    await expect(dialog).toHaveCount(0);
    await expect(trigger).toBeFocused();
});

test('halaman lolos pemindaian aksesibilitas dan tanpa overflow horizontal', async ({ page }) => {
    await openTab(page);
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

    const result = await scanMain(page);
    expect(result.violations, JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))).toEqual([]);
});

test('dialog tambah jabatan lolos pemindaian aksesibilitas', async ({ page }) => {
    await openTab(page);

    await page.getByRole('button', { name: 'Tambah Jabatan' }).click();
    await expect(page.getByRole('dialog', { name: 'Tambah Jabatan Penampungan' })).toBeVisible();
    const result = await scanDialog(page);
    expect(result.violations, JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))).toEqual([]);
});

test('layar ponsel tanpa overflow horizontal', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await openTab(page);
    expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
});

test('kontrol tampilan dapat dioperasikan dengan papan ketik', async ({ page }) => {
    await openTab(page);

    const group = page.getByRole('radiogroup', { name: 'Tampilan jabatan penampungan' });
    const daftar = group.getByRole('radio', { name: 'Daftar Jabatan' });
    const perUnit = group.getByRole('radio', { name: 'Per Unit' });
    await expect(daftar).toHaveAttribute('aria-checked', 'true');

    await daftar.focus();
    await page.keyboard.press('ArrowRight');
    await expect(perUnit).toHaveAttribute('aria-checked', 'true');
    await expect(perUnit).toBeFocused();

    await page.keyboard.press('ArrowLeft');
    await expect(daftar).toHaveAttribute('aria-checked', 'true');
});

test('jabatan yang masih dibuka tidak dapat dihapus dan pesan menunjuk tab ini', async ({ page }) => {
    await openTab(page);

    const createDialog = await showDialog(page, page.getByRole('button', { name: 'Tambah Jabatan' }), 'Tambah Jabatan Penampungan');
    await createDialog.getByLabel(/Nama Jabatan/).fill('Advisor Hapus Uji');
    await createDialog.getByLabel(/Jenis/).selectOption('advisor');
    await createDialog.getByLabel(/RM Band/).selectOption('RM-2');
    await createDialog.getByRole('button', { name: 'Simpan Jabatan' }).click();

    const row = page.locator('tbody tr', { hasText: 'Advisor Hapus Uji' });
    await row.getByRole('button', { name: /^Aksi untuk/ }).click();
    await page.getByRole('menuitem', { name: 'Buka di Unit' }).click();
    await page.getByRole('dialog', { name: 'Buka Jabatan pada Unit' }).getByLabel(/Unit Kerja/).selectOption({ label: 'Unit Group Kalimantan Barat' });
    await page.getByRole('dialog', { name: 'Buka Jabatan pada Unit' }).getByRole('button', { name: 'Buka pada Unit' }).click();

    await row.getByRole('button', { name: /^Aksi untuk/ }).click();
    await page.getByRole('menuitem', { name: 'Hapus Jabatan' }).click();
    const deleteDialog = page.getByRole('dialog', { name: 'Hapus Jabatan Penampungan' });
    await deleteDialog.getByLabel(/Alasan Perubahan/).fill('Dibuat keliru, belum pernah dipakai');
    await deleteDialog.getByRole('button', { name: 'Hapus Jabatan' }).click();
    await expect(deleteDialog.getByRole('alert')).toContainText('tab Jabatan Penampungan');
});
