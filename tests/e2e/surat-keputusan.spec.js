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
    await page.getByRole('row', { name: new RegExp(BASELINE_NUMBER) }).getByRole('link', { name: 'Lihat Detail' }).click();
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
    for (const label of ['Struktur & Formasi', 'Surat Keputusan', 'Riwayat Perubahan']) {
        await expect(nav.getByRole('link', { name: label, exact: true })).toBeVisible();
    }
    // Pengalih tampilan Formasi hanya ada di dalam Formasi, bukan pada alur Surat Keputusan.
    await expect(page.getByRole('navigation', { name: 'Tampilan Formasi' })).toHaveCount(0);
    await expect(nav.getByRole('link', { name: 'Surat Keputusan', exact: true })).toHaveAttribute('aria-current', 'page');

    const row = page.getByRole('row', { name: new RegExp(BASELINE_NUMBER) });
    await expect(row).toBeVisible();
    await expect(row.getByText('Diterapkan')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Catat SK dari Holding' })).toBeVisible();

    // Banner kebijakan persetujuan dua pihak, seperti pada Riwayat & Persetujuan Impor.
    await expect(page.getByText(/Persetujuan dua pihak \(empat mata\)/).first()).toBeVisible();

    const text = (await page.locator('body').innerText()).toLowerCase();
    expect(text).not.toMatch(FORBIDDEN_TERMS);
    expect(text).not.toContain('buat sk baru');
});

test('daftar: memakai tabel data bersama (pencarian, tab status, bilah judul, paginasi)', async ({ page }) => {
    await openIndex(page);

    const tabs = page.getByRole('navigation', { name: 'Filter status SK' });
    for (const label of ['Semua', 'Berjalan', 'Diterapkan', 'Ditutup']) {
        await expect(tabs.getByRole('button', { name: new RegExp('^' + label) })).toBeVisible();
    }
    await expect(tabs.getByRole('button', { name: /^Semua/ })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByPlaceholder('Cari nomor SK atau perihal...')).toBeVisible();
    await expect(page.getByText(/Menampilkan/).first()).toBeVisible();
    await expect(page.getByLabel('baris per halaman', { exact: false }).or(page.getByText('baris per halaman')).first()).toBeVisible();

    // Tab Diterapkan menyaring ke SK baseline; pencarian tanpa hasil memperlihatkan empty state yang mengarahkan tindakan.
    await tabs.getByRole('button', { name: /^Diterapkan/ }).click();
    await expect(page.getByRole('row', { name: new RegExp(BASELINE_NUMBER) })).toBeVisible();
    await page.getByPlaceholder('Cari nomor SK atau perihal...').fill('tidak-ada-yang-cocok');
    await expect(page.getByText('Tidak ada SK yang cocok dengan filter ini.')).toBeVisible();
    await page.getByRole('button', { name: 'Reset Filter' }).first().click();
    await expect(page.getByRole('row', { name: new RegExp(BASELINE_NUMBER) })).toBeVisible();
});

test('formulir: menjelaskan SK dari Holding, template dapat diunduh, dan isian bertanda wajib', async ({ page }) => {
    await openIndex(page);
    await page.getByRole('link', { name: 'Catat SK dari Holding' }).click();
    await expect(page.getByRole('heading', { name: 'Catat & Terapkan SK Struktur' }).first()).toBeVisible();

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
    await page.locator('#workbook').setInputFiles({
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
    await expect(page.getByText(/hal perlu diselesaikan sebelum SK dapat diajukan/)).toBeVisible();
    await expect(page.getByText(/Ajukan nonaktif sampai/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Ajukan untuk Persetujuan' })).toBeDisabled();

    const scan = await scanMain(page);
    expect(scan.violations, JSON.stringify(scan.violations.map((v) => `${v.id}: ${v.nodes.length}`))).toEqual([]);

    // Batalkan draf dari halaman detail: dialog konfirmasi dengan alasan wajib.
    await page.getByRole('link', { name: 'Simpan sebagai Draf' }).click();
    await page.waitForURL(/\/admin\/master\/sk$/);
    const row = page.getByRole('row', { name: /UJI\/E2E\/1\/XII\/2026/ });
    await expect(row.getByText('Draf')).toBeVisible();
    await row.getByRole('link', { name: 'Lihat Detail' }).click();
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
    // Pola layar detail yang sama dengan Riwayat & Persetujuan Impor.
    for (const heading of ['Ringkasan SK', 'Perubahan Struktur', 'Keputusan', 'Jejak Keputusan', 'Verifikasi Berkas']) {
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
    }
    await expect(page.getByText('Total Kuota', { exact: true })).toBeVisible();
    await expect(page.getByText('Diterapkan').first()).toBeVisible();
    await expect(page.getByText('SK yang sudah diterapkan tidak dapat dibatalkan')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Batalkan SK' })).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Unduh Lampiran SK' })).toHaveCount(0);

    const text = (await page.locator('body').innerText()).toLowerCase();
    expect(text).not.toMatch(FORBIDDEN_TERMS);
});

async function openVersions(page) {
    await page.goto('/admin/master/versi', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Versi Struktur', exact: true }).first()).toBeVisible();
}

// Basis data E2E memuat dua versi: SK 352 (1 Sep 2026) dan SK 401 (1 Okt 2026), serta dua karyawan uji (seed-structure-versions.php).
const SECOND_NUMBER = 'DPSB/SKPTS/401/X/2026';

test('versi struktur: tampilan Formasi, satu kendali waktu, data langsung terlihat, dan tanpa NIK utuh', async ({ page }) => {
    await openIndex(page);
    // Daftar SK tidak memuat jalan masuk ke Versi Struktur; tampilannya ada di dalam bagian Formasi.
    await expect(page.getByRole('link', { name: 'Versi Struktur' })).toHaveCount(0);

    await page.getByRole('navigation', { name: 'Bagian Master Data' }).getByRole('link', { name: 'Struktur & Formasi', exact: true }).click();
    await page.waitForURL(/\/admin\/master\/formasi$/, { timeout: 30_000 });
    const views = page.getByRole('navigation', { name: 'Tampilan Formasi' });
    await views.getByRole('link', { name: 'Versi Struktur', exact: true }).click();
    await page.waitForURL(/\/admin\/master\/versi$/, { timeout: 30_000 });
    await expect(views.getByRole('link', { name: 'Versi Struktur', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('heading', { name: 'Versi Struktur', exact: true }).first()).toBeVisible({ timeout: 30_000 });

    // Tanpa banner penjelasan panjang; ringkasan dan tabel jabatan langsung tampil.
    await expect(page.getByText('Setiap SK yang diterapkan menambah satu versi struktur')).toHaveCount(0);
    for (const title of ['Unit', 'Jabatan', 'Total Kuota', 'Terisi']) {
        await expect(page.getByText(title, { exact: true }).first()).toBeVisible();
    }
    await expect(page.getByRole('columnheader', { name: 'Pemangku Jabatan' })).toBeVisible();
    await expect(page.getByText(/baris per halaman/)).toBeVisible();

    // Satu pemilih versi berisi kedua SK; versi terbaru terpilih dan konteksnya disebut.
    const version = page.locator('#version');
    await expect(version.locator('option')).toHaveCount(2);
    await expect(version.locator('option:checked')).toContainText(SECOND_NUMBER);
    await expect(page.getByText(/mengacu pada/)).toContainText(SECOND_NUMBER);

    expect(await page.locator('main').innerText()).not.toMatch(/\b\d{8}\b/);
    expect((await page.locator('body').innerText()).toLowerCase()).not.toMatch(FORBIDDEN_TERMS);
});

test('versi struktur: memilih versi lampau, mencari pemangku, tampilan per unit, dan perbandingan antarversi', async ({ page }) => {
    await openVersions(page);

    // Memilih SK 352 memindahkan tanggal ke hari pertama berlakunya; pemangku mengikuti riwayat jabatan pada tanggal itu.
    await page.locator('#version').selectOption({ index: 1 }); // terbaru dahulu: indeks 1 adalah SK 352
    await expect(page.getByLabel('atau Tanggal')).toHaveValue('2026-09-01');
    await expect(page.getByText(/mengacu pada/)).toContainText('DPSB/SKPTS/352/IX/2026');

    // Pencarian nama pemangku bekerja lintas unit pada tabel datar (tanpa membuka unit satu per satu).
    await page.getByPlaceholder('Cari unit, wilayah, jabatan, atau nama pemangku...').fill('Uji Versi');
    await expect(page.getByText('Budi Uji Versi')).toBeVisible();
    await expect(page.getByText('Sari Uji Versi')).toBeVisible();
    expect(await page.locator('main').innerText()).toMatch(/\*{5}001/);

    // Tampilan per unit: buka semua unit sekaligus.
    await page.getByRole('radio', { name: 'Per Unit' }).click();
    await page.getByRole('button', { name: 'Buka semua unit' }).click();
    await expect(page.locator('tbody button[aria-expanded="true"]').first()).toBeVisible();
    await page.getByRole('button', { name: 'Tutup semua unit' }).click();
    await expect(page.locator('tbody button[aria-expanded="true"]')).toHaveCount(0);
    await page.getByRole('radio', { name: 'Per Jabatan' }).click();

    // Perbandingan antarversi: SK 352 menuju SK 401 menambahkan satu unit.
    await page.getByPlaceholder('Cari unit, wilayah, jabatan, atau nama pemangku...').fill('');
    await page.locator('#compare').selectOption({ label: SECOND_NUMBER });
    await expect(page.getByRole('heading', { name: `Selisih dengan ${SECOND_NUMBER}` })).toBeVisible();
    await expect(page.getByText('Unit Uji Versi Baru').first()).toBeVisible();

    // Satu hari sebelum SK pertama berlaku: tidak ada struktur karangan, pemilih waktu tetap ada.
    await page.getByLabel('atau Tanggal').fill('2026-08-31');
    await expect(page.getByText('Struktur belum tersedia untuk tanggal ini')).toBeVisible();
    await expect(page.locator('#version')).toBeVisible();
});

for (const [name, width] of [['ponsel 375px', 375], ['laptop 1366px', 1366], ['layar lebar 1920px', 1920]]) {
    test(`tanpa overflow horizontal pada ${name}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });

        await openIndex(page);
        expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

        await openVersions(page);
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

    await openVersions(page);
    result = await scanMain(page);
    expect(result.violations, `versi struktur: ${JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))}`).toEqual([]);

    await openBaselineDetail(page);
    result = await scanMain(page);
    expect(result.violations, `detail: ${JSON.stringify(result.violations.map((v) => `${v.id}: ${v.nodes.length}`))}`).toEqual([]);
});
