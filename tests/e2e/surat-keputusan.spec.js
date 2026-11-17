// SUT: Surat Keputusan Struktur (tab Master Formasi): daftar, formulir "Catat SK dari Holding", tinjau, dan detail.
// Satu berkas uji per fitur (ENGINEERING.md §3.5). Basis data E2E adalah SQLite sementara hasil seed (SK baseline sudah ada).
import { test, expect as baseExpect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

// Server uji PHP berutas tunggal: tiap halaman butuh beberapa detik pada cache dingin, jadi batas tunggu 30 detik (bukan 10).
const expect = baseExpect.configure({ timeout: 30_000 });

const scanMain = (page) => new AxeBuilder({ page }).include('main').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();

const FORBIDDEN_TERMS = /AGENTS\.md|DOMAIN\.md|DESIGN\.md|ENGINEERING\.md|SECURITY\.md|SSOT/;

const horizontalOverflow = (page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

const BASELINE_NUMBER = 'DPSB/SKPTS/352/IX/2026';

async function openIndex(page) {
    await page.goto('/admin/master/sk', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Surat Keputusan Struktur' }).first()).toBeVisible();
}

// Dari daftar ke halaman detail SK baseline. Navigasi Livewire (wire:navigate) berjalan di server uji berutas tunggal,
// jadi tunggu perpindahan URL dan beri waktu lebih sebelum memeriksa judul.
async function openBaselineDetail(page) {
    await openIndex(page);
    await page.getByRole('row', { name: new RegExp(BASELINE_NUMBER) }).getByRole('link', { name: 'Lihat' }).click();
    await page.waitForURL(/\/admin\/master\/sk\/\d+$/, { timeout: 30_000 });
    await expect(page.getByRole('heading', { name: BASELINE_NUMBER }).first()).toBeVisible({ timeout: 30_000 });
}

async function openForm(page) {
    await page.goto('/admin/master/sk/baru', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Catat & Terapkan SK Struktur' }).first()).toBeVisible();
}

test('daftar: bagian Surat Keputusan aktif, SK baseline tampil, dan tanpa istilah internal', async ({ page }) => {
    await openIndex(page);

    const nav = page.getByRole('navigation', { name: 'Bagian Master Data' });
    for (const label of ['Formasi', 'Sebaran Formasi', 'Jabatan Penampungan', 'Surat Keputusan', 'Riwayat Perubahan']) {
        await expect(nav.getByRole('link', { name: label, exact: true })).toBeVisible();
    }
    await expect(nav.getByRole('link', { name: 'Surat Keputusan', exact: true })).toHaveAttribute('aria-current', 'page');

    const row = page.getByRole('row', { name: new RegExp(BASELINE_NUMBER) });
    await expect(row).toBeVisible();
    await expect(row.getByText('Diterapkan')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Catat SK dari Holding' })).toBeVisible();

    const text = (await page.locator('body').innerText()).toLowerCase();
    expect(text).not.toMatch(FORBIDDEN_TERMS);
    expect(text).not.toContain('buat sk baru');
});

test('formulir: menjelaskan SK dari Holding, template dapat diunduh, dan isian bertanda wajib', async ({ page }) => {
    await openIndex(page);
    await page.getByRole('link', { name: 'Catat SK dari Holding' }).click();
    await expect(page.getByRole('heading', { name: 'Catat & Terapkan SK Struktur' }).first()).toBeVisible();

    await expect(page.getByText('SK struktur diterbitkan Holding').first()).toBeVisible();
    await page.getByText('Cara menyiapkan lampiran').click();
    await expect(page.getByText('Unduh template. Isinya struktur organisasi saat ini')).toBeVisible();

    const template = page.getByRole('link', { name: 'Unduh Template Lampiran' });
    await expect(template).toBeVisible();
    const response = await page.request.get(await template.getAttribute('href'));
    expect(response.status()).toBe(200);
    expect(response.headers()['content-disposition']).toContain('template-struktur-organisasi.xlsx');

    for (const label of ['Nomor SK', 'Tanggal SK', 'Tentang', 'Tanggal Berlaku', 'Mencabut / Menggantikan', 'Catatan', 'Lampiran SK (.xlsx)', 'Salinan SK (.pdf)']) {
        await expect(page.getByLabel(label).first()).toBeVisible();
    }
    // Petunjuk isian tidak boleh tampil sebagai kode HTML mentah.
    await expect(page.getByText('Judul SK pada baris "Tentang".')).toBeVisible();
    expect((await page.locator('body').innerText())).not.toContain('&quot;');
});

test('alur: template tanpa perubahan menunjukkan tidak ada perubahan dan tidak dapat diajukan; SK dapat dibatalkan dengan alasan', async ({ page }) => {
    await openForm(page);

    const template = await page.request.get('/admin/master/sk/template');
    expect(template.status()).toBe(200);

    await page.getByLabel('Nomor SK').fill('UJI/E2E/1/XII/2026');
    await page.getByLabel('Tanggal SK').fill('2026-12-01');
    await page.getByLabel('Tentang').fill('Uji alur Catat SK dari Holding');
    await page.getByLabel('Lampiran SK (.xlsx)').setInputFiles({
        name: 'lampiran.xlsx',
        mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        buffer: await template.body(),
    });
    await expect(page.getByText('Mengunggah berkas...').first()).toBeHidden();
    await page.getByRole('button', { name: 'Lanjut ke Tinjauan' }).click();

    // Langkah paling berat: membaca lampiran Excel (+ pustaka lama yang memicu ribuan notis pada PHP 8.5), menyimpan SK,
    // dan menyusun tinjauan. Di server uji berutas tunggal dengan basis data berkas ini bisa memakan puluhan detik.
    await expect(page.getByText('Tidak ada perubahan dibandingkan dengan struktur saat ini.').first()).toBeVisible({ timeout: 90_000 });
    await expect(page.getByText('Tinjau Perubahan').first()).toBeVisible();
    await expect(page.getByText('SK belum dapat diajukan')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Ajukan untuk Persetujuan' })).toBeDisabled();

    const scan = await scanMain(page);
    expect(scan.violations, JSON.stringify(scan.violations.map((v) => `${v.id}: ${v.nodes.length}`))).toEqual([]);

    // Batalkan draf dari halaman detail: dialog konfirmasi dengan alasan wajib.
    await page.getByRole('link', { name: 'Simpan sebagai Draf' }).click();
    await page.waitForURL(/\/admin\/master\/sk$/);
    const row = page.getByRole('row', { name: /UJI\/E2E\/1\/XII\/2026/ });
    await expect(row.getByText('Draf')).toBeVisible();
    await row.getByRole('link', { name: 'Lihat' }).click();
    await page.waitForURL(/\/admin\/master\/sk\/\d+$/);

    await page.getByRole('button', { name: 'Batalkan SK' }).first().click();
    const dialog = page.getByRole('dialog', { name: 'Batalkan SK ini?' });
    await expect(dialog).toBeVisible();
    await dialog.getByLabel('Alasan').fill('pendek');
    await dialog.getByRole('button', { name: 'Batalkan SK' }).click();
    await expect(dialog.getByText('Alasan wajib diisi minimal 10 karakter.')).toBeVisible();

    await dialog.getByLabel('Alasan').fill('Uji pembatalan draf pada pengujian E2E.');
    await dialog.getByRole('button', { name: 'Batalkan SK' }).click();
    await expect(page.getByText('Dibatalkan').first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan SK' })).toHaveCount(0);
});

test('detail SK baseline: diterapkan, tidak dapat dibatalkan, dan tanpa unduhan lampiran', async ({ page }) => {
    await openBaselineDetail(page);
    await expect(page.getByText('struktur awal dimuat dari data baseline')).toBeVisible();
    await expect(page.getByText('Diterapkan').first()).toBeVisible();
    await expect(page.getByText('SK yang sudah diterapkan tidak dapat dibatalkan')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan SK' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Unduh Lampiran SK' })).toHaveCount(0);

    const text = (await page.locator('body').innerText()).toLowerCase();
    expect(text).not.toMatch(FORBIDDEN_TERMS);
});

for (const [name, width] of [['ponsel 375px', 375], ['laptop 1366px', 1366], ['layar lebar 1920px', 1920]]) {
    test(`tanpa overflow horizontal pada ${name}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });

        await openIndex(page);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await openForm(page);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await openBaselineDetail(page);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
    });
}

test('daftar, formulir, dan detail lolos pemindaian aksesibilitas', async ({ page }) => {
    await openIndex(page);
    let result = await scanMain(page);
    expect(result.violations, `daftar: ${JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))}`).toEqual([]);

    await openForm(page);
    result = await scanMain(page);
    expect(result.violations, `formulir: ${JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))}`).toEqual([]);

    await openBaselineDetail(page);
    result = await scanMain(page);
    expect(result.violations, `detail: ${JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))}`).toEqual([]);
});
