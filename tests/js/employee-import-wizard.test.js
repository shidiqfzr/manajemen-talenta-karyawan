// SUT: resources/js/admin/employee-import-wizard.js (komponen Alpine Impor Data Karyawan). Dijalankan dengan `npm run test:js`
// memakai pelari uji bawaan Node (tanpa dependensi tambahan); jaringan, Livewire, dan DOM diganti penampung.
import { test, beforeEach, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';

import {
    employeeImportWizard,
    formatFileSize,
    validateFile,
    failedCount,
    columnMatchesQuery,
    formatCommitTimestamp,
    MAX_FILE_BYTES,
} from '../../resources/js/admin/employee-import-wizard.js';

const URLS = { preview: '/admin/employees/import/preview', commit: '/admin/employees/import/commit', status: '/admin/employees/import/status/__TOKEN__' };
const settle = () => new Promise((resolve) => setImmediate(resolve));

let dispatched;
let fetched;
let scrolled;
let inputCleared;
let focused;

function installGlobals() {
    dispatched = [];
    fetched = [];
    scrolled = 0;
    inputCleared = 0;
    focused = 0;
    globalThis.window = { Livewire: { dispatch: (name, payload) => dispatched.push([name, payload]) } };
    globalThis.document = {
        getElementById: (id) => {
            if (id === 'admin-main') return { scrollTo: () => scrolled++ };
            if (id === 'column_guide_search') return { focus: () => focused++ };

            return { set value(v) { inputCleared++; } };
        },
    };
}

/** Tanggapan jaringan palsu: antrean tanggapan dipakai berurutan, tiap panggilan dicatat. */
function respondWith(...responses) {
    globalThis.fetch = async (url, options) => {
        fetched.push({ url, options });
        const next = responses.length > 1 ? responses.shift() : responses[0];
        if (next instanceof Error) throw next;

        return { ok: next.ok ?? true, json: async () => next.body };
    };
}

function make(config = {}) {
    const wizard = employeeImportWizard({ requiresApproval: false, columns: [], urls: URLS, csrf: 'csrf-123', ...config });
    wizard.$nextTick = (callback) => callback();
    wizard.$refs = {};

    return wizard;
}

const file = (name = 'karyawan.xlsx', size = 2048) => ({ name, size });
const pick = (wizard, f) => wizard.handleFileSelected({ target: { files: f ? [f] : [] } });

beforeEach(installGlobals);
afterEach(() => {
    mock.timers.reset();
    delete globalThis.window;
    delete globalThis.document;
    delete globalThis.fetch;
});

// ---------------------------------------------------------------- fungsi murni

test('formatFileSize memakai KB di bawah 1 MB dan MB sesudahnya', () => {
    assert.equal(formatFileSize(0), '0 B');
    assert.equal(formatFileSize(2048), '2.0 KB');
    assert.equal(formatFileSize(3 * 1024 * 1024), '3.00 MB');
});

test('validateFile menerima xlsx/xls (tanpa membedakan huruf) dan menolak format atau ukuran lain', () => {
    assert.equal(validateFile(file('a.xlsx')), '');
    assert.equal(validateFile(file('B.XLS')), '');
    assert.equal(validateFile(file('a.pdf')), 'Format berkas tidak didukung: .pdf');
    assert.match(validateFile(file('besar.xlsx', MAX_FILE_BYTES + 1)), /melebihi batas 10MB/);
    assert.equal(validateFile(file('pas.xlsx', MAX_FILE_BYTES)), '');
});

test('failedCount memilih failed_count, lalu skipped, lalu nol', () => {
    assert.equal(failedCount({ failed_count: 3, skipped: 9 }), 3);
    assert.equal(failedCount({ skipped: 2 }), 2);
    assert.equal(failedCount(null), 0);
});

test('columnMatchesQuery mencari pada judul, kategori, wajib/opsional, tag, jenis, dan uraian', () => {
    const column = { key: 'nik', short_title: null, is_mandatory: true, search_tags: 'nomor induk', type_label: 'Teks', description: '8 digit angka' };

    assert.equal(columnMatchesQuery(column, ''), true);
    assert.equal(columnMatchesQuery(column, '  NIK '), true);
    assert.equal(columnMatchesQuery(column, 'wajib'), true);
    assert.equal(columnMatchesQuery(column, 'opsional'), false);
    assert.equal(columnMatchesQuery(column, '8 digit'), true);
    assert.equal(columnMatchesQuery(column, 'tidak ada'), false);
});

test('formatCommitTimestamp berakhir dengan WIB dan memuat pemisah titik', () => {
    const text = formatCommitTimestamp(new Date('2026-11-19T10:15:00'));

    assert.match(text, /•/);
    assert.match(text, /WIB$/);
});

// ---------------------------------------------------------------- pemilihan berkas

test('handleFileSelected menyimpan berkas sah dan menolak berkas tidak sah dengan pesan', () => {
    const wizard = make();

    pick(wizard, file('ok.xlsx'));
    assert.equal(wizard.selectedFile.name, 'ok.xlsx');
    assert.equal(wizard.fileErrorMessage, '');

    pick(wizard, file('salah.pdf'));
    assert.equal(wizard.selectedFile, null);
    assert.equal(wizard.fileErrorMessage, 'Format berkas tidak didukung: .pdf');

    pick(wizard, file('ok.xlsx'));
    pick(wizard, null);
    assert.equal(wizard.selectedFile, null, 'pemilihan kosong menghapus berkas');
});

test('clearFile mengosongkan berkas, galat, dan nilai input', () => {
    const wizard = make();
    pick(wizard, file('salah.pdf'));

    wizard.clearFile();

    assert.equal(wizard.selectedFile, null);
    assert.equal(wizard.fileErrorMessage, '');
    assert.equal(inputCleared, 1);
});

// ---------------------------------------------------------------- pratinjau

test('pratinjau sinkron: mengirim berkas dengan token CSRF, membuka tahap 2, dan memberi tahu tabel Livewire', async () => {
    respondWith({ body: { success: true, token: 'tok-1', file_name: 'a.xlsx', file_size: 4096, summary: { failed_count: 0, valid_count: 3 } } });
    const wizard = make();
    pick(wizard, new File([new Uint8Array(4096)], 'a.xlsx')); // File nyata: FormData harus membawa berkasnya, bukan teks objek

    await wizard.runDryRunPreview();

    assert.equal(fetched.length, 1);
    assert.equal(fetched[0].url, URLS.preview);
    assert.equal(fetched[0].options.method, 'POST');
    assert.equal(fetched[0].options.body.get('_token'), 'csrf-123');
    assert.equal(fetched[0].options.body.get('import_file').name, 'a.xlsx');
    assert.equal(wizard.currentStep, 2);
    assert.equal(wizard.importToken, 'tok-1');
    assert.equal(wizard.importFileSize, '4.0 KB');
    assert.equal(wizard.activeFilterTab, 'all');
    assert.equal(wizard.isValidating, false);
    assert.deepEqual(dispatched.at(-1), ['import-preview-loaded', { token: 'tok-1', tab: 'all' }]);
});

test('pratinjau dengan baris terkendala membuka tab terkendala lebih dulu', async () => {
    respondWith({ body: { success: true, token: 'tok-2', file_name: 'a.xlsx', file_size: 10, summary: { failed_count: 4 } } });
    const wizard = make();
    pick(wizard, file());

    await wizard.runDryRunPreview();

    assert.equal(wizard.activeFilterTab, 'errors');
    assert.deepEqual(dispatched.at(-1), ['import-preview-loaded', { token: 'tok-2', tab: 'errors' }]);
});

test('pratinjau gagal: menampilkan pesan server atau pesan umum dan tetap di tahap 1', async () => {
    const wizard = make();
    pick(wizard, file());

    respondWith({ ok: false, body: { success: false, message: 'Berkas tidak dapat dibaca.' } });
    await wizard.runDryRunPreview();
    assert.equal(wizard.errorMessage, 'Berkas tidak dapat dibaca.');
    assert.equal(wizard.currentStep, 1);

    respondWith({ ok: false, body: { success: false, errors: { import_file: ['Wajib diisi.'] } } });
    await wizard.runDryRunPreview();
    assert.equal(wizard.errorMessage, 'Wajib diisi.');

    respondWith(new Error('offline'));
    await wizard.runDryRunPreview();
    assert.equal(wizard.errorMessage, 'Terjadi kendala jaringan saat menganalisis berkas.');
    assert.equal(wizard.isValidating, false, 'tombol tidak boleh terkunci setelah galat');
});

test('pratinjau tanpa berkas tidak memanggil server', async () => {
    respondWith({ body: {} });
    const wizard = make();

    await wizard.runDryRunPreview();

    assert.equal(fetched.length, 0);
});

test('pratinjau asinkron: memantau status, memakai URL dari konfigurasi, dan beralih ke fallback sinkron setelah 5 detik', async () => {
    mock.timers.enable({ apis: ['setInterval'] });
    respondWith(
        { body: { success: true, token: 'tok-a', is_async: true, message: 'Diantrekan.' } },
        { body: { success: true, status: 'processing', progress: 40 } },
        { body: { success: true, status: 'processing', progress: 60 } },
        { body: { success: true, status: 'processing', progress: 70 } },
        { body: { success: true, status: 'completed', progress: 100, file_name: 'a.xlsx', file_size: 2048, summary: { failed_count: 0 } } },
    );
    const wizard = make();
    pick(wizard, file());

    await wizard.runDryRunPreview();
    assert.equal(wizard.isAsync, true);
    assert.equal(wizard.jobProgress, 20);
    assert.equal(wizard.isValidating, true, 'tombol tetap terkunci selama antrean berjalan');

    for (let tick = 0; tick < 3; tick++) {
        mock.timers.tick(1500);
        await settle();
    }
    assert.equal(fetched[1].url, '/admin/employees/import/status/tok-a');
    assert.equal(fetched[3].url, '/admin/employees/import/status/tok-a', 'belum 5 detik: tanpa fallback');
    assert.match(wizard.jobMessage, /Memproses|antrean/i);

    mock.timers.tick(1500);
    await settle();

    assert.equal(fetched[4].url, '/admin/employees/import/status/tok-a?fallback_sync=1', 'setelah 5 detik: fallback sinkron');
    assert.equal(wizard.currentStep, 2);
    assert.equal(wizard.isAsync, false);
    assert.equal(wizard.isValidating, false);
    assert.equal(wizard.pollInterval, null);
});

test('pemantauan berhenti dan menampilkan galat saat antrean gagal', async () => {
    mock.timers.enable({ apis: ['setInterval'] });
    respondWith({ body: { success: true, status: 'failed', error: 'Antrean gagal.' } });
    const wizard = make();
    wizard.isValidating = true;

    wizard.pollJobStatus('tok-x', 'preview');
    mock.timers.tick(1500);
    await settle();

    assert.equal(wizard.errorMessage, 'Antrean gagal.');
    assert.equal(wizard.isValidating, false);
    assert.equal(wizard.pollInterval, null);
});

// ---------------------------------------------------------------- komit

test('komit tanpa token atau saat sedang berjalan tidak memanggil server', async () => {
    respondWith({ body: {} });
    const wizard = make();

    await wizard.executeCommitImport();
    wizard.importToken = 'tok';
    wizard.isCommitting = true;
    await wizard.executeCommitImport();

    assert.equal(fetched.length, 0);
});

test('komit sinkron: mengirim token dengan header CSRF lalu membuka tahap 3 dengan cap waktu', async () => {
    respondWith({ body: { success: true, summary: { created: 5, skipped: 1 } } });
    const wizard = make();
    wizard.importToken = 'tok-c';

    await wizard.executeCommitImport();

    assert.equal(fetched[0].url, URLS.commit);
    assert.equal(fetched[0].options.headers['X-CSRF-TOKEN'], 'csrf-123');
    assert.equal(JSON.parse(fetched[0].options.body).token, 'tok-c');
    assert.equal(wizard.currentStep, 3);
    assert.deepEqual(wizard.commitSummary, { created: 5, skipped: 1 });
    assert.match(wizard.commitTimestamp, /WIB$/);
    assert.equal(wizard.isCommitting, false);
});

test('komit yang menunggu persetujuan menampilkan tautan batch tanpa cap waktu simpan', async () => {
    respondWith({ body: { success: true, pending_approval: true, batch_url: '/admin/employees/import/batches/9', summary: { created: 2 } } });
    const wizard = make({ requiresApproval: true });
    wizard.importToken = 'tok-p';

    await wizard.executeCommitImport();

    assert.equal(wizard.requiresApproval, true);
    assert.equal(wizard.pendingApproval, true);
    assert.equal(wizard.batchUrl, '/admin/employees/import/batches/9');
    assert.equal(wizard.commitTimestamp, 'Menunggu persetujuan');
    assert.equal(wizard.currentStep, 3);
});

test('komit gagal menampilkan pesan dan melepas kunci tombol', async () => {
    const wizard = make();
    wizard.importToken = 'tok-f';

    respondWith({ ok: false, body: { success: false, message: 'Sesi impor berakhir.' } });
    await wizard.executeCommitImport();
    assert.equal(wizard.errorMessage, 'Sesi impor berakhir.');
    assert.equal(wizard.isCommitting, false);

    respondWith(new Error('offline'));
    await wizard.executeCommitImport();
    assert.equal(wizard.errorMessage, 'Terjadi kesalahan saat memproses penyimpanan data karyawan.');
    assert.equal(wizard.isCommitting, false);
});

// ---------------------------------------------------------------- tab, modal, kamus, reset

test('setFilterTab memberi tahu tabel dan resetToStep1 mengosongkan sesi serta menghentikan pemantauan', () => {
    mock.timers.enable({ apis: ['setInterval'] });
    const wizard = make();
    wizard.importToken = 'tok';
    wizard.currentStep = 2;
    wizard.validationSummary = { failed_count: 1 };
    wizard.pollJobStatus('tok', 'preview');
    wizard.openDiffModal({ nama: 'X' });

    wizard.setFilterTab('created');
    assert.deepEqual(dispatched.at(-1), ['import-preview-tab', { tab: 'created' }]);

    wizard.resetToStep1();

    assert.equal(wizard.currentStep, 1);
    assert.equal(wizard.importToken, '');
    assert.equal(wizard.validationSummary, null);
    assert.equal(wizard.pollInterval, null);
    assert.equal(wizard.showDiffModal, false);
    assert.equal(wizard.activeDiffRow, null);
    assert.deepEqual(dispatched.at(-1), ['import-preview-loaded', { token: '' }]);
    assert.ok(scrolled >= 1, 'kembali ke atas halaman');
});

test('kamus format: pencarian menyaring kolom dan banner kategori tersembunyi saat mencari', () => {
    const wizard = make({ columns: [{ key: 'nik', is_mandatory: true, description: 'Nomor induk' }, { key: 'agama', is_mandatory: false, description: 'Agama resmi' }] });

    assert.equal(wizard.hasAnyVisibleColumn(), true);
    assert.equal(wizard.isCategoryBannerVisible(), true);

    wizard.searchColumn = 'agama';
    assert.equal(wizard.hasAnyVisibleColumn(), true);
    assert.equal(wizard.isCategoryBannerVisible(), false);
    assert.equal(wizard.isColumnVisibleInTab('agama', 'cat1', false, 'tag'), true);
    assert.equal(wizard.isColumnVisibleInTab('nik', 'cat1', true, 'tag'), false);

    wizard.searchColumn = 'tidak-ada';
    assert.equal(wizard.hasAnyVisibleColumn(), false);

    wizard.openDictionaryDrawer();
    assert.equal(wizard.showDictionaryDrawer, true);
    assert.equal(focused, 1, 'kolom pencarian kamus mendapat fokus saat kamus dibuka');
});
