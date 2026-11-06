// SUT: Riwayat & Persetujuan Impor (daftar dan detail). Satu berkas uji per fitur (ENGINEERING.md §3.5).
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Pindai area konten halaman; kerangka layout bersama diuji pada spesifikasi lain, dan pemindaian seluruh dokumen sangat lambat pada server PHP satu proses.
const scanMain = (page) => new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();

const FORBIDDEN_TERMS = /AGENTS\.md|DOMAIN\.md|DESIGN\.md|ENGINEERING\.md|SECURITY\.md|SSOT/;

async function openDetail(page) {
    await page.goto('/admin/employees/import/batches', { waitUntil: 'domcontentloaded' });
    await page.getByRole('link', { name: /karyawan_import/ }).click();
    await expect(page.getByRole('heading', { name: 'Jejak Keputusan' })).toBeVisible();
}

test('daftar menampilkan hasil berlabel, tanggal baku, dan tanpa istilah internal', async ({ page }) => {
    await page.goto('/admin/employees/import/batches', { waitUntil: 'domcontentloaded' });

    const row = page.locator('tbody tr', { hasText: 'karyawan_import_bez_oktober_2026.xlsx' });
    await expect(row).toContainText(/319\s*baru/);
    await expect(row).toContainText(/65\s*dilewati/);
    await expect(row.getByText(/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}$/)).toBeVisible();
    await expect(page.getByText('Persetujuan dua pihak (empat mata) belum diaktifkan.')).toBeVisible();
    await expect(page.getByRole('link', { name: /Perlu Tindakan/ })).toHaveAttribute('title', /belum diaktifkan/);
    expect(await page.locator('body').innerText()).not.toMatch(FORBIDDEN_TERMS);
});

test('detail: tanpa overflow horizontal, linimasa dan kode verifikasi utuh', async ({ page }) => {
    await openDetail(page);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);

    const timeline = page.locator('#timeline-title').locator('xpath=ancestor::section[1]');
    await expect(timeline.getByText('Diimpor')).toBeVisible();
    await expect(timeline.getByText('Disimpan')).toBeVisible();

    const verify = page.locator('#verify-title').locator('xpath=ancestor::section[1]');
    await expect(verify.getByRole('button', { name: 'Salin kode verifikasi berkas' })).toBeVisible();
    const hash = verify.locator('.font-mono');
    const [card, text] = await Promise.all([verify.boundingBox(), hash.boundingBox()]);
    expect(text.x + text.width).toBeLessThanOrEqual(card.x + card.width);
});

test('detail: metadata, KPI tidak terpotong, dan KPI nol dibuat netral', async ({ page }) => {
    await openDetail(page);

    const summary = page.locator('#result-title').locator('xpath=ancestor::section[1]');
    await expect(summary.getByText(/384\s*baris dalam berkas/)).toBeVisible();
    await expect(summary.getByText('Admin User')).toBeVisible();

    for (const text of ['Baru', 'Diperbarui', 'Tetap', 'Dilewati', 'Ditambahkan', 'Atribut disesuaikan', 'Sudah mutakhir', 'Tidak diproses']) {
        const label = summary.getByText(text, { exact: true }).first();
        const truncated = await label.evaluate((el) => el.scrollWidth > el.clientWidth);
        expect(truncated, `teks KPI "${text}" terpotong`).toBe(false);
    }
});

test('detail: baris dilewati bisa ditindaklanjuti', async ({ page }) => {
    await openDetail(page);

    const panel = page.locator('#skipped-title').locator('xpath=ancestor::section[1]');
    // Cache berkas koreksi tidak dibagi antarproses pada server uji, sehingga keadaan "sudah tidak tersedia" yang diuji di sini;
    // keadaan tersedia (tombol dan batas waktu) diuji di EmployeeImportTest.
    await expect(panel.getByRole('button', { name: 'Unduh Berkas Koreksi' })).toHaveCount(0);
    await expect(panel.getByText(/Berkas koreksi sudah tidak tersedia karena memuat data pribadi/)).toBeVisible();
    await expect(panel.getByText(/Contoh: Kuota formasi jabatan 'Asisten Kepala'/)).toBeVisible();

    await panel.getByText('Lihat seluruh baris beserta pesannya').click();
    await expect(panel.locator('tbody tr')).toHaveCount(65);
});

test('pembatalan impor: kartu berisiko, konfirmasi ketik, dan fokus awal pada Batal', async ({ page }) => {
    await openDetail(page);

    await expect(page.getByText('Baris yang dilewati tidak pernah disimpan sehingga tidak terpengaruh.')).toBeVisible();
    await expect(page.getByText(/Siap dibatalkan\./)).toBeVisible();
    await page.getByRole('button', { name: 'Batalkan Impor' }).first().click();

    const dialog = page.getByRole('dialog', { name: /Batalkan impor #1\?/ });
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('button', { name: 'Batal', exact: true })).toBeFocused();
    await expect(dialog.getByRole('button', { name: 'Batalkan Impor' })).toBeDisabled();
    await dialog.getByRole('button', { name: 'Batal', exact: true }).click();
    await expect(dialog).toBeHidden();
});

test('aksesibilitas WCAG 2.1 AA pada daftar dan detail', async ({ page }) => {
    test.setTimeout(240_000); // dua pemuatan halaman (aset CDN) dan dua pemindaian pada server PHP satu proses
    for (const open of [() => page.goto('/admin/employees/import/batches', { waitUntil: 'domcontentloaded' }), () => openDetail(page)]) {
        await open();
        const result = await scanMain(page);
        expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`)).toEqual([]);
    }
});

async function openByName(page, fileName) {
    await page.goto('/admin/employees/import/batches', { waitUntil: 'domcontentloaded' });
    await page.getByRole('link', { name: fileName }).click();
    await expect(page.getByRole('heading', { name: 'Jejak Keputusan' })).toBeVisible();
}

const timelineOf = (page) => page.locator('#timeline-title').locator('xpath=ancestor::section[1]');

test('tab status menyaring pengajuan dan impor yang sudah ditutup', async ({ page }) => {
    await page.goto('/admin/employees/import/batches', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('link', { name: /Perlu Tindakan\s*2/ })).toBeVisible();

    await page.getByRole('link', { name: /Perlu Tindakan/ }).click();
    await expect(page.locator('tbody tr')).toHaveCount(2);
    await expect(page.locator('tbody').getByText('Menunggu Persetujuan')).toHaveCount(2);

    await page.getByRole('link', { name: /Ditutup/ }).click();
    await expect(page.locator('tbody tr')).toHaveCount(3);
    for (const label of ['Ditolak', 'Kedaluwarsa', 'Dibatalkan']) {
        await expect(page.locator('tbody').getByText(label, { exact: true }).first()).toBeVisible();
    }

    await page.getByRole('searchbox').fill('tidak-ada-berkas-ini');
    await page.getByRole('button', { name: 'Cari' }).click();
    await expect(page.getByText(/Tidak ada impor yang cocok dengan filter ini/)).toBeVisible();
});

test('pengajuan admin lain: penyetuju melihat kegagalan berkas dan alasan penolakan wajib diisi', async ({ page }) => {
    await openByName(page, 'pengajuan_rina.xlsx');

    await expect(page.getByRole('alert').filter({ hasText: 'Berkas pengajuan tidak ditemukan' })).toBeVisible();
    await expect(page.getByRole('button', { name: /Setujui & Simpan Data/ })).toHaveCount(0);
    await expect(timelineOf(page).getByText('Menunggu persetujuan admin lain')).toBeVisible();

    await page.getByRole('button', { name: 'Tolak Pengajuan' }).click();
    const dialog = page.getByRole('dialog', { name: 'Tolak pengajuan impor ini?' });
    await expect(dialog.getByRole('button', { name: 'Batal', exact: true })).toBeFocused();
    await dialog.getByLabel(/Alasan/).fill('pendek');
    await dialog.getByRole('button', { name: 'Tolak Pengajuan' }).click();
    await expect(dialog).toBeVisible(); // alasan kurang dari 10 karakter ditahan oleh validasi peramban
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
});

test('pengajuan sendiri: tidak dapat menyetujui, hanya menarik pengajuan', async ({ page }) => {
    await openByName(page, 'pengajuan_admin.xlsx');

    await expect(page.getByText(/Anda pengaju impor ini, sehingga tidak dapat menyetujuinya sendiri/)).toBeVisible();
    await expect(page.getByRole('button', { name: /Setujui/ })).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Tarik Pengajuan' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tolak Pengajuan' })).toHaveCount(0);
});

test('impor dibatalkan, ditolak, dan kedaluwarsa: linimasa mencatat pelaku dan alasan', async ({ page }) => {
    await openByName(page, 'dibatalkan.xlsx');
    await expect(timelineOf(page).getByText('Dibatalkan', { exact: true })).toBeVisible();
    await expect(timelineOf(page).getByText(/Berkas salah periode/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan Impor' })).toHaveCount(0);

    await openByName(page, 'ditolak.xlsx');
    await expect(timelineOf(page).getByText('Ditolak', { exact: true })).toBeVisible();
    await expect(timelineOf(page).getByText(/Kolom unit kerja belum sesuai Master Formasi/)).toBeVisible();

    await openByName(page, 'kedaluwarsa.xlsx');
    await expect(timelineOf(page).getByText('Kedaluwarsa', { exact: true })).toBeVisible();
});

test.describe('layar ponsel 390px', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('daftar dan detail tidak melebar dan lolos pemindaian aksesibilitas', async ({ page }) => {
        test.setTimeout(240_000);
        const overflow = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        const scan = async () => (await scanMain(page)).violations.map((v) => v.id);

        await page.goto('/admin/employees/import/batches', { waitUntil: 'domcontentloaded' });
        expect(await overflow()).toBeLessThanOrEqual(0);
        expect(await scan()).toEqual([]);

        await openByName(page, 'karyawan_import_bez_oktober_2026.xlsx');
        expect(await overflow()).toBeLessThanOrEqual(0);
        expect(await scan()).toEqual([]);
    });
});
