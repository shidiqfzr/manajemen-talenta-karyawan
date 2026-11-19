/**
 * Komponen Alpine "Impor Data Karyawan" (alur tiga tahap: unggah, pratinjau, konfirmasi).
 *
 * Nilai dari server (izin persetujuan, kamus kolom, URL rute, token CSRF) tidak ditulis ke dalam skrip pada Blade: halaman
 * menyerahkannya sebagai satu objek konfigurasi, `x-data="employeeImportWizard(@js($wizard))"`, sehingga seluruh logika
 * berada di berkas ini dan dapat diuji tanpa peramban (`npm run test:js`). Fungsi murni diekspor terpisah.
 *
 * Tabel pratinjau adalah komponen Livewire (ImportPreviewTable); komponen ini hanya memberi tahu token dan tab lewat
 * peristiwa `import-preview-loaded` dan `import-preview-tab`.
 */

export const MAX_FILE_BYTES = 10 * 1024 * 1024;
export const ALLOWED_EXTENSIONS = ['xlsx', 'xls'];

/** Teks ukuran berkas: KB di bawah 1 MB, selain itu MB. */
export function formatFileSize(bytes) {
    if (!bytes) return '0 B';

    return bytes < 1024 * 1024
        ? (bytes / 1024).toFixed(1) + ' KB'
        : (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

/** Pesan galat untuk berkas yang tidak sah (format atau ukuran); string kosong bila berkas sah. */
export function validateFile(file) {
    const extension = file.name.split('.').pop().toLowerCase();

    if (!ALLOWED_EXTENSIONS.includes(extension)) {
        return 'Format berkas tidak didukung: .' + extension;
    }

    if (file.size > MAX_FILE_BYTES) {
        return 'Ukuran berkas (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) melebihi batas 10MB';
    }

    return '';
}

/** Jumlah baris terkendala dari ringkasan pratinjau. */
export function failedCount(summary) {
    return summary?.failed_count ?? summary?.skipped ?? 0;
}

/** Apakah kolom pada kamus format cocok dengan kata kunci pencarian (judul, kategori, wajib/opsional, tag, jenis, uraian). */
export function columnMatchesQuery(column, query) {
    const needle = (query || '').toLowerCase().trim();
    if (needle === '') return true;

    const haystack = [
        column.key || '',
        column.short_title || '',
        column.category || '',
        column.is_mandatory ? 'wajib mandatory' : 'opsional optional',
        column.search_tags || '',
        column.type_label || '',
        column.description || '',
    ].join(' ').toLowerCase();

    return haystack.includes(needle);
}

/** Cap waktu penyimpanan untuk tahap 3, mis. "19 Nov 2026 • 10.15 WIB". */
export function formatCommitTimestamp(date = new Date()) {
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
        + ' • '
        + date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })
        + ' WIB';
}

/**
 * @param {{requiresApproval?: boolean, columns?: Array<object>, urls?: {preview: string, commit: string, status: string}, csrf?: string}} config
 *   `urls.status` memuat penanda `__TOKEN__` yang diganti token sesi impor.
 */
export function employeeImportWizard(config = {}) {
    const urls = config.urls ?? {};
    const csrf = config.csrf ?? '';
    const jsonHeaders = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const scrollToTop = () => document.getElementById('admin-main')?.scrollTo({ top: 0, behavior: 'smooth' });

    return {
        currentStep: 1,
        showDictionaryDrawer: false,
        specsFilter: 'all',
        searchColumn: '',
        selectedFile: null,
        fileErrorMessage: '',
        isValidating: false,
        isCommitting: false,
        requiresApproval: Boolean(config.requiresApproval),
        pendingApproval: false,
        batchUrl: '',
        errorMessage: '',
        isAsync: false,
        jobProgress: 0,
        jobMessage: '',
        pollInterval: null,
        importToken: '',
        importFileName: '',
        importFileSize: '',
        validationSummary: null,
        activeFilterTab: 'all',
        commitSummary: null,
        commitTimestamp: '',
        showDiffModal: false,
        activeDiffRow: null,
        columnsMetadata: config.columns ?? [],

        /** Tab dipilih dari kartu KPI; tabelnya komponen Livewire (ImportPreviewTable) yang menerima tab lewat peristiwa. */
        setFilterTab(tab) {
            this.activeFilterTab = tab;
            window.Livewire?.dispatch('import-preview-tab', { tab });
        },

        /** Pratinjau selesai: tampilkan tabel untuk token ini; baris terkendala lebih dulu bila ada. */
        showPreviewTable() {
            this.activeFilterTab = failedCount(this.validationSummary) > 0 ? 'errors' : 'all';
            this.$nextTick(() => window.Livewire?.dispatch('import-preview-loaded', { token: this.importToken, tab: this.activeFilterTab }));
        },

        openDictionaryDrawer() {
            this.showDictionaryDrawer = true;
            this.$nextTick(() => {
                document.getElementById('column_guide_search')?.focus();
            });
        },

        hasAnyVisibleColumn() {
            if (!this.searchColumn.trim()) return true;

            return this.columnsMetadata.some((column) => columnMatchesQuery(column, this.searchColumn));
        },

        openDiffModal(row) {
            this.activeDiffRow = row;
            this.showDiffModal = true;
        },

        closeDiffModal() {
            this.showDiffModal = false;
            this.activeDiffRow = null;
        },

        isColumnVisibleInTab(colName, category, isMandatory, searchTags) {
            // Pencarian langsung pada seluruh kolom
            if (this.searchColumn.trim() !== '') {
                const q = this.searchColumn.toLowerCase().trim();
                const str = (colName + ' ' + category + ' ' + (isMandatory ? 'wajib mandatory' : 'opsional optional') + ' ' + searchTags).toLowerCase();
                return str.includes(q);
            }
            return true;
        },

        isCategoryBannerVisible() {
            // Sembunyikan header banner saat pencarian langsung aktif agar hasil pencarian tampil rapat
            return this.searchColumn.trim() === '';
        },

        handleFileSelected(event) {
            const input = event.target;
            this.fileErrorMessage = '';

            if (!input.files || !input.files[0]) {
                this.selectedFile = null;
                return;
            }

            const file = input.files[0];
            const problem = validateFile(file);

            this.fileErrorMessage = problem;
            this.selectedFile = problem === '' ? file : null;
        },

        clearFile() {
            this.selectedFile = null;
            this.fileErrorMessage = '';
            const input = document.getElementById('page_import_file');
            if (input) input.value = '';
        },

        formatFileSize,

        showError(message) {
            this.errorMessage = message;
        },

        resetToStep1() {
            this.stopPolling();
            this.currentStep = 1;
            this.errorMessage = '';
            this.clearFile();
            this.importToken = '';
            this.isAsync = false;
            this.jobProgress = 0;
            this.jobMessage = '';
            this.validationSummary = null;
            window.Livewire?.dispatch('import-preview-loaded', { token: '' });
            this.commitSummary = null;
            this.commitTimestamp = '';
            this.pendingApproval = false;
            this.batchUrl = '';
            this.showDiffModal = false;
            this.activeDiffRow = null;
            scrollToTop();
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        /** Menampilkan pratinjau yang sudah selesai: ringkasan angka, nama berkas, lalu tahap 2. */
        showPreview(data) {
            this.validationSummary = data.summary;
            this.importFileName = data.file_name;
            this.importFileSize = this.formatFileSize(data.file_size);
            this.showPreviewTable();
            this.currentStep = 2;
            scrollToTop();
        },

        showCommitResult(summary, timestamp = formatCommitTimestamp()) {
            this.commitSummary = summary;
            this.commitTimestamp = timestamp;
            this.currentStep = 3;
            scrollToTop();
        },

        pollJobStatus(token, type) {
            this.stopPolling();
            let pollElapsedSeconds = 0;

            this.pollInterval = setInterval(async () => {
                pollElapsedSeconds += 1.5;

                // SLA Fail-Safe: Jika antrean latar belakang belum memproses dalam 5 detik, picu fallback sinkron
                const queryParam = pollElapsedSeconds >= 5 ? '?fallback_sync=1' : '';

                if (pollElapsedSeconds >= 5) {
                    this.jobMessage = 'Antrean belum merespons, mengalihkan ke pemrosesan sinkron langsung (SLA Fail-Safe)...';
                }

                try {
                    const res = await fetch(urls.status.replace('__TOKEN__', encodeURIComponent(token)) + queryParam, { headers: jsonHeaders });
                    const data = await res.json();

                    if (!data.success) return;

                    this.jobProgress = data.progress || 50;
                    if (!queryParam) {
                        this.jobMessage = data.message || 'Memproses berkas di antrean latar belakang...';
                    }

                    if (data.status === 'completed') {
                        this.stopPolling();
                        this.isAsync = false;

                        if (type === 'preview') {
                            this.isValidating = false;
                            this.showPreview(data);
                        } else if (type === 'commit') {
                            this.isCommitting = false;
                            this.showCommitResult(data.summary);
                        }
                    } else if (data.status === 'failed') {
                        this.stopPolling();
                        this.isAsync = false;
                        this.isValidating = false;
                        this.isCommitting = false;
                        this.showError(data.error || 'Terjadi kendala pada antrean pemrosesan berkas.');
                    }
                } catch (err) {
                    console.error('Polling error', err);
                }
            }, 1500);
        },

        async runDryRunPreview() {
            if (!this.selectedFile) return;

            this.isValidating = true;
            this.errorMessage = '';
            const formData = new FormData();
            formData.append('import_file', this.selectedFile);
            formData.append('_token', csrf);

            try {
                const response = await fetch(urls.preview, { method: 'POST', body: formData, headers: jsonHeaders });
                const data = await response.json();

                if (response.ok && data.success) {
                    this.importToken = data.token;

                    if (data.is_async) {
                        this.isAsync = true;
                        this.jobMessage = data.message;
                        this.jobProgress = 20;
                        this.pollJobStatus(data.token, 'preview');
                    } else {
                        this.showPreview(data);
                    }
                } else {
                    this.showError(data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Gagal memvalidasi berkas spreadsheet. Pastikan format sesuai template.'));
                }
            } catch (e) {
                this.showError('Terjadi kendala jaringan saat menganalisis berkas.');
            } finally {
                if (!this.isAsync) {
                    this.isValidating = false;
                }
            }
        },

        async executeCommitImport() {
            if (!this.importToken || this.isCommitting) return;

            this.isCommitting = true;
            this.errorMessage = '';

            try {
                const response = await fetch(urls.commit, {
                    method: 'POST',
                    headers: { ...jsonHeaders, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ token: this.importToken }),
                });
                const data = await response.json();

                if (response.ok && data.success) {
                    if (data.is_async) {
                        this.isAsync = true;
                        this.jobMessage = data.message;
                        this.jobProgress = 25;
                        this.pollJobStatus(this.importToken, 'commit');
                    } else if (data.pending_approval) {
                        this.pendingApproval = true;
                        this.batchUrl = data.batch_url;
                        this.showCommitResult(data.summary, 'Menunggu persetujuan');
                    } else {
                        this.showCommitResult(data.summary);
                    }
                } else {
                    this.showError(data.message || 'Gagal menyimpan data karyawan.');
                }
            } catch (e) {
                this.showError('Terjadi kesalahan saat memproses penyimpanan data karyawan.');
            } finally {
                if (!this.isAsync) {
                    this.isCommitting = false;
                }
            }
        },
    };
}
