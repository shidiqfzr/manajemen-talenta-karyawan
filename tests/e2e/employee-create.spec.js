// SUT: form Tambah Karyawan (admin/employees/create). Satu berkas uji per fitur (ENGINEERING.md §3.5).
import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const NIK = '88123401';

async function pickUnitAndPosition(page, unit, positionIndex = 1) {
    await page.getByPlaceholder('Cari atau pilih unit kerja...').fill(unit);
    await page.getByRole('button', { name: new RegExp(unit) }).first().click();
    await expect(page.locator('#jabatanInput')).toBeEnabled();
    await page.locator('#jabatanInput').selectOption({ index: positionIndex });
}

test.beforeEach(async ({ page }) => {
    await page.goto('/admin/employees/create', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#employeeForm')).toBeVisible();
    await expect(page.locator('#nikInput')).toBeEditable();
});

test('mengikuti position control dan kosakata baku', async ({ page }) => {
    await expect(page.getByText(/Unit Baru|Jabatan Baru/)).toHaveCount(0);
    await expect(page.getByText('Opsional', { exact: true })).toHaveCount(0);

    const channels = await page.locator('#jalur_masuk option').evaluateAll((o) => o.map((x) => x.value));
    expect(channels.sort()).toEqual(['CKP', 'RBB', 'Reguler', 'Talent Scouting']);
});

test('legenda wajib diisi berada di header kartu pertama', async ({ page }) => {
    const legend = page.getByText('wajib diisi').first();
    const card = page.locator('h3', { hasText: 'Identitas Pribadi' }).locator('xpath=ancestor::div[contains(@class,"rounded-2xl")][1]');
    await expect(card.getByText('wajib diisi')).toBeVisible();
    const box = await legend.boundingBox();
    expect(box.x).toBeGreaterThan(700);
});

test('Level Strata sejajar dengan Jalur Masuk', async ({ page }) => {
    const level = await page.locator('#levelLabel').boundingBox();
    const jalur = await page.locator('label[for=jalur_masuk]').boundingBox();
    const card = await page.locator('[role=radiogroup] label').first().boundingBox();
    const select = await page.locator('#jalur_masuk').boundingBox();

    expect(Math.abs(level.y - jalur.y)).toBeLessThanOrEqual(1);
    expect(Math.abs(card.y - select.y)).toBeLessThanOrEqual(1);
    expect(Math.abs(card.height - select.height)).toBeLessThanOrEqual(1);
});

test('RM Band dan Bidang kosong sebelum jabatan dipilih, lalu mengikuti Master Formasi', async ({ page }) => {
    await expect(page.locator('#rmLevelInput')).toHaveValue('');
    await expect(page.getByText('Terisi otomatis setelah Jabatan Formasi dipilih.')).toHaveCount(2);

    await pickUnitAndPosition(page, 'Kebun Tajati');

    await expect(page.locator('#rmLevelInput')).toHaveValue(/^RM-[123]$/);
    await expect(page.locator('#rmLevelInput')).toHaveJSProperty('readOnly', true);
    const form = page.locator('#employeeForm');
    await expect(form.getByText('Master Formasi', { exact: true }).locator('visible=true')).toHaveCount(2);
    await expect(form.getByText('Perkiraan', { exact: true }).locator('visible=true')).toHaveCount(0);
});

test('menyimpan karyawan, lalu NIK yang sama terdeteksi langsung', async ({ page }) => {
    await page.locator('#nikInput').fill(NIK);
    await expect(page.getByText('NIK tersedia.')).toBeVisible();

    await page.locator('#nama').fill('Karyawan Uji E2E');
    await pickUnitAndPosition(page, 'Kebun Tajati');
    await page.locator('#job_grade').fill('12');
    await page.locator('#personGradeInput').fill('12');

    await Promise.all([page.waitForURL(/\/admin\/employees$/, { waitUntil: 'domcontentloaded' }), page.getByRole('button', { name: 'Simpan Karyawan Baru' }).click()]);

    await page.goto('/admin/employees/create');
    await page.locator('#nikInput').fill(NIK);
    const taken = page.getByText('NIK sudah terdaftar.');
    await expect(taken).toBeVisible();
    await expect(page.getByRole('link', { name: 'buka data karyawan tersebut' })).toHaveAttribute('href', new RegExp(`/admin/employees/${NIK}/detail`));
    await expect(page.locator('#nikInput')).toHaveAttribute('aria-invalid', 'true');
});

test('menampilkan ringkasan error dengan fokus dan tautan ke isian', async ({ page }) => {
    await page.evaluate(() => { document.getElementById('employeeForm').noValidate = true; });
    await page.locator('#nikInput').fill('12');
    await page.getByRole('button', { name: 'Simpan Karyawan Baru' }).click();

    const summary = page.locator('#errorSummary');
    await expect(summary).toBeVisible();
    await expect(summary).toBeFocused();
    await expect(summary).toContainText('isian perlu diperbaiki');
    await expect(summary.getByRole('link').first()).toHaveAttribute('href', /^#/);
    await expect(page.locator('#nikInput')).toHaveClass(/border-danger/);
});

for (const width of [375, 1366, 1920]) {
    test(`tidak ada scroll horizontal pada lebar ${width}px`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.waitForTimeout(200);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
        expect(overflow).toBe(false);
        await page.screenshot({ path: `test-results/employee-create-${width}.png`, fullPage: true });
    });
}

test('tidak ada pelanggaran aksesibilitas serius (WCAG 2.1 A/AA)', async ({ page }) => {
    const results = await new AxeBuilder({ page })
        .include('#employeeForm')
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .analyze();

    const serious = results.violations.filter((v) => ['serious', 'critical'].includes(v.impact));
    expect(serious.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).slice(0, 3).join(' | ')}`)).toEqual([]);
});
