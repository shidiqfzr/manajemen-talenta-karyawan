// SUT: Impor Data Karyawan (wizard tiga tahap: unggah, pratinjau, konfirmasi). Satu berkas uji per fitur (ENGINEERING.md §3.5, §3.6).
// Tahap 3 (komit) tidak dijalankan di sini agar data bersama spesifikasi lain tidak berubah; komit diuji di EmployeeImportTest
// dan employee-import-wizard.test.js. Berkas uji dibuat oleh tests/e2e/support/make-import-fixtures.php dari kursi formasi nyata.
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const fixtures = JSON.parse(readFileSync(resolve('tests/e2e/.tmp/import-fixtures.json'), 'utf-8'));
const VALID_FILE = resolve('tests/e2e/.tmp/import-valid.xlsx');
const ERRORS_FILE = resolve('tests/e2e/.tmp/import-with-errors.xlsx');

const FORBIDDEN_TERMS = /AGENTS\.md|DOMAIN\.md|DESIGN\.md|ENGINEERING\.md|SECURITY\.md|SSOT/;
const scanMain = (page) => new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
const overflowOf = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

// Hanya baris tabel yang terlihat: modal tersembunyi pada halaman ini juga memuat tabel.
const visibleRows = (page) => page.locator('tbody tr:visible');
const startButton = (page) => page.getByRole('button', { name: /Mulai Analisis Validasi Spreadsheet/ });

async function openWizard(page) {
    await page.goto('/admin/employees/import', { waitUntil: 'domcontentloaded' });
    await expect(page.getByText('Unggah Berkas Spreadsheet')).toBeVisible();
}

// Server uji PHP berutas tunggal: pratinjau (unggah, validasi, muat tabel Livewire) dapat memakan puluhan detik.
const SERVER_BOUND = { timeout: 180_000 };

async function previewOf(page, file) {
    await openWizard(page);
    await page.locator('#page_import_file').setInputFiles(file);
    await expect(startButton(page)).toBeEnabled();
    await startButton(page).click();
    await expect(page.locator('[aria-current="step"]')).toContainText('Analisis & Pratinjau Perubahan', SERVER_BOUND);
    // Tabel pratinjau dimuat Livewire sesudah tahap 2 terbuka.
    await expect(visibleRows(page).first()).toBeVisible(SERVER_BOUND);
}

test('tahap 1: tiga tombol satu tinggi, label tidak terpotong, tanpa overflow, dan lolos aksesibilitas', async ({ page }) => {
    await openWizard(page);

    const start = startButton(page);
    const template = page.getByRole('link', { name: 'Unduh Template Excel Resmi' });
    const dictionary = page.getByRole('button', { name: /Kamus Format/ });
    await expect(start).toBeDisabled();

    const [a, b, c] = await Promise.all([start.boundingBox(), template.boundingBox(), dictionary.boundingBox()]);
    expect(Math.abs(a.height - b.height), 'Mulai Analisis dan Unduh Template satu tinggi').toBeLessThanOrEqual(1);
    expect(Math.abs(b.height - c.height), 'Unduh Template dan Kamus Format satu tinggi (label tidak membungkus)').toBeLessThanOrEqual(1);
    expect(a.width, 'tombol utama selebar area aksi').toBeGreaterThan(b.width);

    for (const width of [375, 1366, 1920]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await overflowOf(page), `overflow horizontal pada ${width}px`).toBeLessThanOrEqual(0);
    }
    await page.setViewportSize({ width: 1366, height: 900 });

    expect((await scanMain(page)).violations).toEqual([]);
    expect(await page.locator('body').innerText()).not.toMatch(FORBIDDEN_TERMS);
});

test('tahap 1: area unggah dibuka dengan keyboard dan berkas yang salah ditolak dengan pesan jelas', async ({ page }) => {
    await openWizard(page);

    const dropzone = page.getByRole('button', { name: /Pilih Berkas/ });
    await dropzone.focus();
    const [chooser] = await Promise.all([page.waitForEvent('filechooser'), dropzone.press('Enter')]);
    expect(chooser.isMultiple()).toBe(false);

    await page.locator('#page_import_file').setInputFiles({ name: 'catatan.pdf', mimeType: 'application/pdf', buffer: Buffer.from('%PDF-1.4') });
    await expect(page.getByRole('alert').filter({ hasText: 'Format berkas tidak didukung: .pdf' })).toBeVisible();
    await expect(startButton(page)).toBeDisabled();

    await page.getByRole('button', { name: 'Hapus berkas terpilih' }).click();
    await expect(page.getByText('Tarik & Lepas berkas di sini')).toBeVisible();

    await page.locator('#page_import_file').setInputFiles(VALID_FILE);
    await expect(page.getByRole('status').filter({ hasText: 'import-valid.xlsx' })).toBeVisible();
    await expect(startButton(page)).toBeEnabled();
});

test('pratinjau berkas valid: KPI, tabel bersama, NIK samaran, lencana, tab, pencarian, paginasi, dan bar konfirmasi lengket', async ({ page }) => {
    test.slow(); // beberapa perjalanan ke server uji berutas tunggal
    await previewOf(page, VALID_FILE);

    // KPI dan judul tabel dari komponen bersama.
    const created = page.getByRole('button', { name: /Karyawan Baru/ });
    await expect(created).toContainText(String(fixtures.validCount));
    await expect(page.getByText('Semua Data Karyawan', { exact: true })).toBeVisible();

    // Lencana Status Aksi tidak kosong (kosakata baku: Baru), dan NIK tidak pernah tampil utuh (UU PDP).
    const firstRow = visibleRows(page).first();
    await expect(firstRow).toContainText(fixtures.validFirstName);
    await expect(firstRow.getByText('Baru', { exact: true })).toBeVisible();
    const body = await page.locator('body').innerText();
    for (const nik of fixtures.validNiks) {
        expect(body, `NIK ${nik} tampil utuh`).not.toContain(nik);
    }
    expect(body).toContain('*****001');
    expect(body).not.toMatch(FORBIDDEN_TERMS);

    // Kartu KPI dapat dioperasikan keyboard (Enter) dan menandai tab terpilih.
    const unchanged = page.getByRole('button', { name: /Data Tetap/ });
    await unchanged.focus();
    await unchanged.press('Enter');
    await expect(page.getByText('Data Tetap / Sudah Mutakhir', { exact: true })).toBeVisible();
    await expect(unchanged).toHaveAttribute('aria-pressed', 'true');
    await created.click();
    await expect(page.getByText('Data Karyawan Baru', { exact: true })).toBeVisible();
    await expect(created).toHaveAttribute('aria-pressed', 'true');

    // Pencarian di server (NIK utuh) dan paginasi dengan pilihan baku.
    const search = page.getByPlaceholder('Cari NIK, nama, jabatan, atau unit kerja...');
    await search.fill(fixtures.validNiks[4]);
    await expect(visibleRows(page)).toHaveCount(1);
    await expect(visibleRows(page).first()).toContainText('KARYAWAN UJI 05');
    await search.fill('');
    await expect(visibleRows(page).nth(1)).toBeVisible();

    await page.getByLabel('Tampilkan').selectOption('10');
    await expect(page.getByText(/Menampilkan\s*1\s*–\s*10\s*dari\s*30\s*baris/)).toBeVisible();
    await page.getByRole('button', { name: 'Halaman berikutnya' }).click();
    await expect(page.getByText(/Menampilkan\s*11\s*–\s*20\s*dari\s*30\s*baris/)).toBeVisible();

    // Bar konfirmasi bersama menempel di bawah layar dan memuat jumlah yang diimpor.
    const bar = page.locator('.sticky.bottom-4');
    await expect(bar).toContainText(`Siap mengimpor ${fixtures.validCount} data karyawan yang valid.`);
    await expect(bar).toBeInViewport();
    await expect(page.getByRole('button', { name: `Konfirmasi & Impor ${fixtures.validCount} Data Valid` })).toBeEnabled();

    expect(await overflowOf(page)).toBeLessThanOrEqual(0);
    expect((await scanMain(page)).violations).toEqual([]);

    // Batal mengembalikan ke tahap 1 dan mengosongkan tabel.
    await bar.getByRole('button', { name: 'Batal' }).click();
    await expect(page.locator('[aria-current="step"]')).toContainText('Unggah & Panduan Kolom');
});

test('pratinjau berkas dengan kendala: tab terkendala lebih dulu, penyebab dan petunjuk unit, serta unduhan koreksi', async ({ page }) => {
    test.slow(); // beberapa perjalanan ke server uji berutas tunggal
    await previewOf(page, ERRORS_FILE);

    await expect(page.getByText('Daftar Baris Terkendala', { exact: true })).toBeVisible();
    await expect(page.getByText(`${fixtures.errors.failedCount} baris terkendala validasi dan tidak diimpor`)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Unduh Berkas Koreksi' })).toBeVisible();

    for (const name of fixtures.errors.failedNames) {
        await expect(page.locator('tbody tr', { hasText: name })).toBeVisible();
    }
    await expect(page.getByText('Jabatan karyawan wajib diisi.')).toBeVisible();
    await expect(page.locator('tbody tr', { hasText: 'KENDALA RANGKAP TANPA UNIT' })).toContainText(`Jabatan ini ada pada unit: ${fixtures.errors.hintUnit}`);

    const body = await page.locator('body').innerText();
    for (const nik of fixtures.errors.niks) {
        expect(body, `NIK ${nik} tampil utuh`).not.toContain(nik);
    }

    // Baris valid tetap tersedia pada tab Semua Data.
    await page.getByRole('button', { name: /Semua Data/ }).click();
    await expect(visibleRows(page)).toHaveCount(fixtures.errors.validCount);
    await expect(page.getByRole('button', { name: `Konfirmasi & Impor ${fixtures.errors.validCount} Data Valid` })).toBeEnabled();

    expect((await scanMain(page)).violations).toEqual([]);
});

test('pratinjau pada layar sempit (375px): tanpa overflow horizontal dan bar konfirmasi tetap terjangkau', async ({ page }) => {
    test.slow(); // beberapa perjalanan ke server uji berutas tunggal
    await page.setViewportSize({ width: 375, height: 800 });
    await previewOf(page, VALID_FILE);

    expect(await overflowOf(page)).toBeLessThanOrEqual(0);
    await expect(page.locator('.sticky.bottom-4')).toBeInViewport();
    await expect(page.getByRole('button', { name: /Konfirmasi & Impor/ })).toBeVisible();
});

test('kamus format: pencarian memakai komponen bersama, menyaring kolom, dan dapat dihapus', async ({ page }) => {
    await openWizard(page);
    await page.getByRole('button', { name: /Kamus Format/ }).click();

    const search = page.locator('#column_guide_search');
    await expect(search).toBeVisible();
    await expect(search).toBeFocused();

    await search.fill('tidak-ada-kolom-seperti-ini');
    await expect(page.getByText('Reset Pencarian').first()).toBeVisible();

    const clear = page.getByRole('button', { name: 'Hapus pencarian' });
    await expect(clear).toBeVisible();
    await clear.click();
    await expect(search).toHaveValue('');
    await expect(search).toBeFocused();

    await page.getByRole('button', { name: 'Tutup kamus format' }).click();
    await expect(search).toBeHidden();
    expect((await scanMain(page)).violations).toEqual([]);
});
