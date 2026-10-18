<!-- ============================================================ -->
<!-- ALPINE.JS COMPONENT LOGIC & EVENT LISTENERS                  -->
<!-- ============================================================ -->
<script>
function employeeImportWizard() {
    return {
        currentStep: 1,
        showDictionaryDrawer: false,
        specsFilter: 'all',
        searchColumn: '',
        selectedFile: null,
        fileErrorMessage: '',
        isValidating: false,
        isCommitting: false,
        isAsync: false,
        jobProgress: 0,
        jobMessage: '',
        pollInterval: null,
        importToken: '',
        importFileName: '',
        importFileSize: '',
        validationSummary: null,
        sampleRows: [],
        validationErrors: [],
        activeFilterTab: 'all',
        previewSearch: '',
        perPage: 25,
        currentPage: 1,
        commitSummary: null,
        commitTimestamp: '',
        showDiffModal: false,
        activeDiffRow: null,

        get updatedRowsCount() {
            return this.validationSummary?.updated ?? this.sampleRows.filter(r => r.status === 'UPDATE').length;
        },
        get createdRowsCount() {
            return this.validationSummary?.created ?? this.sampleRows.filter(r => r.status === 'BARU' || r.action === 'CREATE').length;
        },
        get unchangedRowsCount() {
            return this.validationSummary?.unchanged ?? this.sampleRows.filter(r => r.status === 'UNCHANGED' || r.status === 'TETAP').length;
        },
        get filteredList() {
            const q = (this.previewSearch || '').toLowerCase().trim();
            if (this.activeFilterTab === 'errors') {
                if (!q) return this.validationErrors;
                return this.validationErrors.filter(err => {
                    const rowStr = (err.row + ' ' + (err.nik || '') + ' ' + (err.nama || '') + ' ' + (err.jabatan || '') + ' ' + (err.unit_kerja || '') + ' ' + (Array.isArray(err.errors) ? err.errors.join(' ') : (err.errors || ''))).toLowerCase();
                    return rowStr.includes(q);
                });
            }

            let baseList = this.sampleRows;
            if (this.activeFilterTab === 'updated') {
                baseList = baseList.filter(r => r.status === 'UPDATE');
            } else if (this.activeFilterTab === 'created') {
                baseList = baseList.filter(r => r.status === 'BARU' || r.action === 'CREATE');
            } else if (this.activeFilterTab === 'unchanged') {
                baseList = baseList.filter(r => r.status === 'UNCHANGED' || r.status === 'TETAP');
            }

            if (!q) return baseList;
            return baseList.filter(r => {
                const str = ((r.nik || '') + ' ' + (r.nama || '') + ' ' + (r.jabatan || '') + ' ' + (r.unit_kerja || '') + ' ' + (r.golongan || '')).toLowerCase();
                return str.includes(q);
            });
        },
        get totalPages() {
            const count = this.filteredList.length;
            return Math.max(1, Math.ceil(count / this.perPage));
        },
        get paginatedRows() {
            const start = (this.currentPage - 1) * this.perPage;
            return this.filteredList.slice(start, start + this.perPage);
        },
        setFilterTab(tab) {
            this.activeFilterTab = tab;
            this.currentPage = 1;
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        openDictionaryDrawer() {
            this.showDictionaryDrawer = true;
            this.$nextTick(() => {
                this.$refs.searchColInput?.focus();
            });
        },

        columnsMetadata: @json(\App\Services\Import\EmployeeImportDictionary::getColumns()),

        hasAnyVisibleColumn() {
            if (!this.searchColumn.trim()) return true;
            const q = this.searchColumn.toLowerCase().trim();
            return this.columnsMetadata.some(c => {
                const key = c.key || '';
                const shortTitle = c.short_title || '';
                const cat = c.category || '';
                const isMandatory = c.is_mandatory ? 'wajib mandatory' : 'opsional optional';
                const tags = c.search_tags || '';
                const typeLabel = c.type_label || '';
                const desc = c.description || '';
                const str = (key + ' ' + shortTitle + ' ' + cat + ' ' + isMandatory + ' ' + tags + ' ' + typeLabel + ' ' + desc).toLowerCase();
                return str.includes(q);
            });
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
            // Live Search across all columns
            if (this.searchColumn.trim() !== '') {
                const q = this.searchColumn.toLowerCase().trim();
                const str = (colName + ' ' + category + ' ' + (isMandatory ? 'wajib mandatory' : 'opsional optional') + ' ' + searchTags).toLowerCase();
                return str.includes(q);
            }
            return true;
        },

        isCategoryBannerVisible(category) {
            // Sembunyikan header banner saat live search aktif agar hasil pencarian tampil rapat
            return this.searchColumn.trim() === '';
        },

        handleFileSelected(event) {
            const input = event.target;
            this.fileErrorMessage = '';

            if (input.files && input.files[0]) {
                const file = input.files[0];
                const validExtensions = ['xlsx', 'xls'];
                const ext = file.name.split('.').pop().toLowerCase();
                const maxSize = 10 * 1024 * 1024; // 10MB

                if (!validExtensions.includes(ext)) {
                    this.fileErrorMessage = 'Format berkas tidak didukung: .' + ext;
                    this.selectedFile = null;
                    return;
                }

                if (file.size > maxSize) {
                    this.fileErrorMessage = 'Ukuran berkas (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) melebihi batas 10MB';
                    this.selectedFile = null;
                    return;
                }

                this.selectedFile = file;
            } else {
                this.selectedFile = null;
            }
        },

        clearFile() {
            this.selectedFile = null;
            this.fileErrorMessage = '';
            const input = document.getElementById('page_import_file');
            if (input) input.value = '';
        },

        formatFileSize(bytes) {
            if (!bytes) return '0 B';
            return bytes < 1024 * 1024
                ? (bytes / 1024).toFixed(1) + ' KB'
                : (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        },

        resetToStep1() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
            this.currentStep = 1;
            this.clearFile();
            this.importToken = '';
            this.isAsync = false;
            this.jobProgress = 0;
            this.jobMessage = '';
            this.validationSummary = null;
            this.sampleRows = [];
            this.validationErrors = [];
            this.commitSummary = null;
            this.commitTimestamp = '';
            this.showDiffModal = false;
            this.activeDiffRow = null;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },

        pollJobStatus(token, type) {
            if (this.pollInterval) clearInterval(this.pollInterval);
            let pollElapsedSeconds = 0;

            this.pollInterval = setInterval(async () => {
                pollElapsedSeconds += 1.5;

                // SLA Fail-Safe: Jika antrean latar belakang belum memproses dalam 5 detik, picu fallback sinkron
                const queryParam = pollElapsedSeconds >= 5 ? '?fallback_sync=1' : '';

                if (pollElapsedSeconds >= 5) {
                    this.jobMessage = 'Antrean belum merespons, mengalihkan ke pemrosesan sinkron langsung (SLA Fail-Safe)...';
                }

                try {
                    const res = await fetch(`/admin/employees/import/status/${token}${queryParam}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const data = await res.json();

                    if (data.success) {
                        this.jobProgress = data.progress || 50;
                        if (!queryParam) {
                            this.jobMessage = data.message || 'Memproses berkas di antrean latar belakang...';
                        }

                        if (data.status === 'completed') {
                            clearInterval(this.pollInterval);
                            this.pollInterval = null;
                            this.isAsync = false;

                            if (type === 'preview') {
                                this.isValidating = false;
                                this.validationSummary = data.summary;
                                this.sampleRows = data.summary.valid_rows || data.summary.sample_rows || [];
                                this.validationErrors = data.summary.errors || [];
                                this.importFileName = data.file_name;
                                this.importFileSize = this.formatFileSize(data.file_size);
                                this.activeFilterTab = (this.validationErrors.length > 0) ? 'errors' : 'all';
                                this.previewSearch = '';
                                this.currentPage = 1;
                                this.currentStep = 2;
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            } else if (type === 'commit') {
                                this.isCommitting = false;
                                this.commitSummary = data.summary;
                                const now = new Date();
                                this.commitTimestamp = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' \u2022 ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
                                this.currentStep = 3;
                                window.scrollTo({ top: 0, behavior: 'smooth' });
                            }
                        } else if (data.status === 'failed') {
                            clearInterval(this.pollInterval);
                            this.pollInterval = null;
                            this.isAsync = false;
                            this.isValidating = false;
                            this.isCommitting = false;
                            alert(data.error || 'Terjadi kendala pada antrean pemrosesan berkas.');
                        }
                    }
                } catch (err) {
                    console.error('Polling error', err);
                }
            }, 1500);
        },

        async runDryRunPreview() {
            if (!this.selectedFile) return;

            this.isValidating = true;
            const formData = new FormData();
            formData.append('import_file', this.selectedFile);
            formData.append('_token', '{{ csrf_token() }}');

            try {
                const response = await fetch('{{ route('admin.employees.import.preview') }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    }
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.importToken = data.token;

                    if (data.is_async) {
                        this.isAsync = true;
                        this.jobMessage = data.message;
                        this.jobProgress = 20;
                        this.pollJobStatus(data.token, 'preview');
                    } else {
                        this.validationSummary = data.summary;
                        this.sampleRows = data.summary.valid_rows || data.summary.sample_rows || [];
                        this.validationErrors = data.summary.errors || [];
                        this.importFileName = data.file_name;
                        this.importFileSize = this.formatFileSize(data.file_size);
                        this.activeFilterTab = (this.validationErrors.length > 0) ? 'errors' : 'all';
                        this.previewSearch = '';
                        this.currentPage = 1;
                        this.currentStep = 2;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                } else {
                    alert(data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Gagal memvalidasi berkas spreadsheet. Pastikan format sesuai template.'));
                }
            } catch (e) {
                alert('Terjadi kendala jaringan saat menganalisis berkas.');
            } finally {
                if (!this.isAsync) {
                    this.isValidating = false;
                }
            }
        },

        async executeCommitImport() {
            if (!this.importToken || this.isCommitting) return;

            this.isCommitting = true;

            try {
                const response = await fetch('{{ route('admin.employees.import.commit') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        token: this.importToken
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    if (data.is_async) {
                        this.isAsync = true;
                        this.jobMessage = data.message;
                        this.jobProgress = 25;
                        this.pollJobStatus(this.importToken, 'commit');
                    } else {
                        this.commitSummary = data.summary;
                        const now = new Date();
                        this.commitTimestamp = now.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) + ' \u2022 ' + now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB';
                        this.currentStep = 3;
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                } else {
                    alert(data.message || 'Gagal menyimpan data pegawai.');
                }
            } catch (e) {
                alert('Terjadi kesalahan saat memproses penyimpanan data pegawai.');
            } finally {
                if (!this.isAsync) {
                    this.isCommitting = false;
                }
            }
        }
    };
}

// Drag and drop event listeners
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('pageDropZone');
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.add('border-emerald-500', 'bg-emerald-50/60');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.remove('border-emerald-500', 'bg-emerald-50/60');
            }, false);
        });

        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('page_import_file');
                if (fileInput) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            }
        }, false);
    }
});
</script>
