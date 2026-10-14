<!-- MODAL: Import Excel & Template (Comprehensive Enterprise Import Dialog) -->
<div x-data="importModalHandler()"
    @open-import-modal.window="openModal()"
    @keydown.escape.window="if (show && !isValidating && !isCommitting) closeModal()"
    x-cloak>
    
    <!-- Backdrop -->
    <div x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity z-50"
        @click="closeModal()"></div>

    <div x-show="show" class="fixed inset-0 z-50 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
            <div x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-auto w-full sm:max-w-3xl border border-surface-200 flex flex-col"
                style="max-width: 48rem; max-height: calc(100vh - 3.5rem);">
                
                <!-- Modal Header -->
                <div class="px-6 py-3.5 sm:py-4 border-b border-surface-200 flex items-center justify-between bg-surface-50/90 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-lg border border-primary-200 shadow-2xs shrink-0">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-bold text-surface-900" id="modal-title">
                                    <span x-show="importStep === 'upload'">Import Data Pegawai</span>
                                    <span x-show="importStep === 'validating'">Memvalidasi Spreadsheet...</span>
                                    <span x-show="importStep === 'preview'">Pratinjau Hasil Validasi Data</span>
                                </h3>
                                <span x-show="importStep === 'preview'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                                    Dry-Run Preview
                                </span>
                            </div>
                            <p class="text-xs text-surface-500 mt-0.5">
                                <span x-show="importStep === 'upload'">Unggah spreadsheet Excel (.xlsx, .xls) untuk penambahan atau pembaruan massal.</span>
                                <span x-show="importStep === 'validating'">Sistem sedang memeriksa integritas data dan kepatuhan aturan bisnis SSOT...</span>
                                <span x-show="importStep === 'preview'">Tinjau hasil analisis validasi data sebelum dikomit ke basis data.</span>
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="closeModal()"
                        class="w-8 h-8 rounded-lg text-surface-400 hover:text-surface-700 hover:bg-surface-200/60 transition flex items-center justify-center cursor-pointer shrink-0"
                        aria-label="Tutup modal">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <!-- Navigation Tabs (Only in upload step) -->
                <div x-show="importStep === 'upload'" class="px-6 py-2.5 bg-surface-50/70 border-b border-surface-200/80 flex items-center shrink-0">
                    <div class="inline-flex p-1 bg-surface-200/60 rounded-xl gap-1">
                        <button type="button" @click="importModalTab = 'upload'"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap"
                            :class="importModalTab === 'upload' ? 'bg-white text-surface-900 shadow-xs font-bold' : 'text-surface-600 hover:text-surface-900 hover:bg-white/40'">
                            <i class="fas fa-cloud-arrow-up text-[11px]" :class="importModalTab === 'upload' ? 'text-primary-600' : 'text-surface-400'"></i>
                            <span>Unggah Berkas</span>
                        </button>
                        <button type="button" @click="importModalTab = 'specs'"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap"
                            :class="importModalTab === 'specs' ? 'bg-white text-surface-900 shadow-xs font-bold' : 'text-surface-600 hover:text-surface-900 hover:bg-white/40'">
                            <i class="fas fa-table-list text-[11px]" :class="importModalTab === 'specs' ? 'text-primary-600' : 'text-surface-400'"></i>
                            <span>Panduan Format Kolom</span>
                        </button>
                    </div>
                </div>

                <!-- TAB 1: UNGGAH BERKAS -->
                <div x-show="importStep === 'upload' && importModalTab === 'upload'" class="px-6 py-4 space-y-3.5 flex-1 min-h-0 overflow-y-auto">
                    <!-- Step 1: Download Template Area -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                <label class="text-xs font-bold text-surface-900">Format Template Resmi PTPN IV</label>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-[10px] font-semibold text-emerald-800 border border-emerald-200">
                                <i class="fas fa-check-circle text-[9px]"></i> 2 Sheet & Dropdown Validasi
                            </span>
                        </div>

                        <div class="bg-primary-50/60 border border-primary-200/90 rounded-2xl p-4 sm:p-4.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-2xs">
                            <div class="space-y-1 flex-1">
                                <h5 class="text-xs font-bold text-primary-950 flex items-center gap-1.5">
                                    <i class="fas fa-file-circle-check text-emerald-600 text-xs"></i>
                                    <span>Format Spreadsheet Terstandarisasi</span>
                                </h5>
                                <p class="text-xs text-surface-600 leading-relaxed">
                                    Dilengkapi pilihan <strong>dropdown otomatis</strong> dan <strong>lembar referensi</strong> untuk memastikan akurasi data serta mencegah kesalahan pengetikan.
                                </p>
                            </div>
                            <a href="{{ route('admin.employees.downloadTemplate') }}"
                                class="h-9 shrink-0 inline-flex items-center gap-2 px-4 rounded-xl bg-white border border-primary-300 text-primary-800 text-xs font-semibold hover:bg-primary-50 hover:border-primary-400 transition-all shadow-2xs group whitespace-nowrap active:scale-[0.98] cursor-pointer">
                                <i class="fas fa-file-excel text-emerald-600 group-hover:scale-110 transition-transform"></i>
                                <span>Unduh Template Excel</span>
                            </a>
                        </div>
                    </div>

                    <!-- Step 2: Upload File Area -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                                <label class="text-xs font-bold text-surface-900">Pilih Berkas Excel untuk Diunggah</label>
                            </div>
                            <button type="button" @click="importModalTab = 'specs'"
                                class="text-xs text-primary-700 hover:text-primary-800 font-semibold hover:underline inline-flex items-center gap-1 cursor-pointer">
                                <i class="fas fa-circle-question text-[11px]"></i>
                                <span>Lihat Spesifikasi 26 Kolom</span>
                            </button>
                        </div>

                        <form action="{{ route('admin.employees.import') }}" method="POST" enctype="multipart/form-data" id="modalImportForm">
                            @csrf
                            <div id="modalDropZone"
                                class="relative border-2 border-dashed border-surface-300 hover:border-primary-500 rounded-2xl p-4 sm:p-5 text-center cursor-pointer transition-all duration-200 bg-surface-50/60 hover:bg-primary-50/30 group">
                                <input type="file" name="import_file" id="modal_import_file"
                                    accept=".xls,.xlsx" required
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    onchange="handleModalFileChange(this)">

                                <!-- Default Dropzone State -->
                                <div id="modalFileDefault" class="py-1">
                                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-surface-100 text-surface-500 group-hover:bg-primary-100 group-hover:text-primary-700 group-hover:scale-105 flex items-center justify-center mx-auto text-lg sm:text-xl transition duration-200 shadow-2xs border border-surface-200 group-hover:border-primary-200">
                                        <i class="fas fa-cloud-arrow-up"></i>
                                    </div>
                                    <p class="text-sm font-bold text-surface-800 mt-2.5 group-hover:text-primary-900 transition">Klik untuk memilih berkas atau seret ke sini</p>
                                    <p class="text-xs text-surface-400 mt-1">Mendukung format Excel <span class="font-semibold text-surface-600">.xlsx, .xls</span> &bull; Ukuran maksimal <span class="font-semibold text-surface-600">10 MB</span></p>
                                </div>

                                <!-- Enterprise Selected File Card -->
                                <div id="modalFileSelected" class="hidden">
                                    <div class="p-3.5 bg-emerald-50/80 border border-emerald-200 rounded-xl flex items-center justify-between gap-3 text-left shadow-2xs">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg shrink-0 border border-emerald-200 shadow-2xs">
                                                <i class="fas fa-file-excel"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <p class="text-xs font-bold text-surface-900 truncate max-w-xs" id="modalFileName"></p>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold border border-emerald-200 shrink-0">
                                                        <i class="fas fa-check text-[9px]"></i> Siap Diimpor
                                                    </span>
                                                </div>
                                                <p class="text-[11px] text-surface-500 mt-0.5 flex items-center gap-2">
                                                    <span id="modalFileSize" class="font-mono font-medium text-surface-700"></span>
                                                    <span>&bull;</span>
                                                    <span class="text-emerald-700 font-medium">Format tervalidasi</span>
                                                </p>
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearModalFile(event)" class="shrink-0 px-3 py-1.5 text-xs text-danger hover:text-red-700 hover:bg-red-50 rounded-lg transition font-medium border border-transparent hover:border-red-200 cursor-pointer">
                                            <i class="fas fa-trash-can mr-1"></i> Ganti Berkas
                                        </button>
                                    </div>
                                </div>

                                <!-- Error State -->
                                <div id="modalFileError" class="hidden">
                                    <div class="p-3.5 bg-red-50/80 border border-red-200 rounded-xl flex items-center justify-between gap-3 text-left shadow-2xs">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-red-100 text-danger flex items-center justify-center text-lg shrink-0 border border-red-200">
                                                <i class="fas fa-triangle-exclamation"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-danger truncate" id="modalFileErrorMsg"></p>
                                                <p class="text-[11px] text-surface-500 mt-0.5">Pilih berkas berekstensi .xlsx atau .xls dengan ukuran maksimal 10MB.</p>
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearModalFile(event)" class="shrink-0 px-3 py-1.5 text-xs text-primary-700 hover:text-primary-800 hover:bg-primary-50 rounded-lg transition font-medium border border-transparent hover:border-primary-200 cursor-pointer">
                                            Pilih Berkas Lain
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TAB 2: PANDUAN SPESIFIKASI 26 KOLOM -->
                <div x-show="importStep === 'upload' && importModalTab === 'specs'" class="flex-1 min-h-0 flex flex-col">
                    <!-- Pinned Subheader & Filter Bar -->
                    <div class="px-6 py-3.5 border-b border-surface-200 bg-surface-50/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shrink-0">
                        <div>
                            <h4 class="text-xs font-bold text-surface-900">Kamus Kolom Impor Data Pegawai</h4>
                            <p class="text-[11px] text-surface-500 mt-0.5">Panduan penamaan atribut kolom dan aturan format pengisian spreadsheet.</p>
                        </div>
                        <div class="inline-flex items-center gap-1 p-1 bg-surface-100 rounded-xl border border-surface-200/70 shadow-2xs text-[11px] font-semibold shrink-0">
                            <button type="button" @click="specsFilter = 'all'"
                                class="h-7 px-3 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                :class="specsFilter === 'all' ? 'bg-white text-surface-900 shadow-2xs font-bold' : 'text-surface-500 hover:text-surface-800'">
                                Semua (26)
                            </button>
                            <button type="button" @click="specsFilter = 'mandatory'"
                                class="h-7 px-3 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                :class="specsFilter === 'mandatory' ? 'bg-white text-danger shadow-2xs font-bold' : 'text-surface-500 hover:text-danger'">
                                Wajib (6)
                            </button>
                            <button type="button" @click="specsFilter = 'optional'"
                                class="h-7 px-3 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                :class="specsFilter === 'optional' ? 'bg-white text-surface-900 shadow-2xs font-bold' : 'text-surface-500 hover:text-surface-800'">
                                Opsional (20)
                            </button>
                        </div>
                    </div>

                    <!-- Scrolling Table Area -->
                    <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4">
                        <div class="border border-surface-200 rounded-xl overflow-hidden shadow-2xs overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs min-w-[540px] sm:min-w-full">
                                <thead class="sticky top-0 bg-surface-100 border-b border-surface-200 text-surface-700 text-[11px] uppercase tracking-wider font-semibold z-10 shadow-2xs">
                                    <tr>
                                        <th class="py-2.5 px-3.5 font-bold w-[24%] bg-surface-100">Kolom & Status</th>
                                        <th class="py-2.5 px-3.5 font-bold w-[26%] bg-surface-100">Tipe & Dropdown</th>
                                        <th class="py-2.5 px-3.5 font-bold w-[50%] bg-surface-100">Aturan & Contoh Pengisian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-100 text-[11px] bg-white">
                                    <!-- nik -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                                <span>nik</span>
                                                <span class="text-danger" title="Wajib Diisi">*</span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <span class="text-[11px] text-surface-700 font-medium">Teks (8 Digit)</span>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Format Teks Baku</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            8 digit angka numerik unik. Auto-padding nol di depan jika terpotong Excel. Contoh: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">13009988</code>
                                        </td>
                                    </tr>
                                    <!-- nama -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                                <span>nama</span>
                                                <span class="text-danger" title="Wajib Diisi">*</span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <span class="text-[11px] text-surface-700 font-medium">Teks Bebas</span>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Nama Lengkap KTP</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Nama lengkap pegawai sesuai KTP. Otomatis distandarkan menjadi UPPERCASE oleh sistem.
                                        </td>
                                    </tr>
                                    <!-- status_karyawan -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                                <span>status_karyawan</span>
                                                <span class="text-danger" title="Wajib Diisi">*</span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                                <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                            </div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Pilihan Validasi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Pilih dari dropdown: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Tetap (PKWTT)</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Kontrak (PKWT)</code>, atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Calon Pegawai</code>
                                        </td>
                                    </tr>
                                    <!-- jabatan -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                                <span>jabatan</span>
                                                <span class="text-danger" title="Wajib Diisi">*</span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                                <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                            </div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Posisi Aktif</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Nomenklatur jabatan resmi sesuai formasi unit. Contoh: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Asisten Afdeling</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Mandor Panen</code>
                                        </td>
                                    </tr>
                                    <!-- level -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                                <span>level</span>
                                                <span class="text-danger" title="Wajib Diisi">*</span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                                <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                            </div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Kategori Strata</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Pilih dari dropdown: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Karpim</code> (Karyawan Pimpinan) atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Karpel</code> (Karyawan Pelaksana)
                                        </td>
                                    </tr>
                                    <!-- unit_kerja -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                                <span>unit_kerja</span>
                                                <span class="text-danger" title="Wajib Diisi">*</span>
                                            </div>
                                            <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                                <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                            </div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">43 Unit Resmi</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Pilih salah satu dari 43 unit kerja resmi PTPN IV Regional V. Contoh: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Kebun Inti Gunung Meliau</code>
                                        </td>
                                    </tr>
                                    <!-- golongan -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50/60 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="font-mono font-bold text-surface-800">golongan</div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Opsional</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <span class="text-[11px] text-surface-700 font-medium">Format Baku</span>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">[Gol]/[Berkala]</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Format: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">IIIA/00</code> atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">IVA/05</code>
                                        </td>
                                    </tr>
                                    <!-- rm_level -->
                                    <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50/60 transition">
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="font-mono font-bold text-surface-800">rm_level</div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">Opsional</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top">
                                            <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                                <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                            </div>
                                            <span class="text-[10px] text-surface-400 block mt-0.5">RM-1 s.d. RM-4</span>
                                        </td>
                                        <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                            Tingkat RM Band. Jika kosong, sistem otomatis menurunkan dari nomenklatur jabatan (DOMAIN.md).
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- STEP 2: VALIDATING SPINNER -->
                <div x-show="importStep === 'validating'" class="px-6 py-16 flex flex-col items-center justify-center text-center space-y-4 flex-1">
                    <div class="relative w-16 h-16">
                        <div class="w-16 h-16 rounded-full border-4 border-emerald-100 border-t-emerald-600 animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-emerald-600 text-lg">
                            <i class="fas fa-file-excel animate-pulse"></i>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <h4 class="text-sm font-bold text-surface-900">Menganalisis & Memvalidasi Format Spreadsheet...</h4>
                        <p class="text-xs text-surface-500 max-w-sm mx-auto leading-relaxed">
                            Sistem sedang memeriksa NIK 8 digit, master unit & formasi resmi, batas usia pensiun, serta validasi strata pegawai.
                        </p>
                    </div>
                </div>

                <!-- STEP 3: DRY-RUN PREVIEW SCREEN -->
                <div x-show="importStep === 'preview'" class="flex-1 min-h-0 flex flex-col overflow-hidden">
                    <!-- File & Dry-run Banner -->
                    <div class="px-6 py-3 bg-surface-50 border-b border-surface-200 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 shrink-0">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs shrink-0">
                                <i class="fas fa-file-excel"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-surface-900 truncate" x-text="importFileName"></p>
                                <p class="text-[10px] text-surface-500" x-text="importFileSize + ' • Analisis Selesai'"></p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-200 shrink-0">
                            <i class="fas fa-shield-halved text-[9px]"></i>
                            <span>Data Belum Disimpan (Pratinjau)</span>
                        </span>
                    </div>

                    <!-- Scrollable Preview Area -->
                    <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4 space-y-4">
                        <!-- 4 Metrics KPI Grid -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                            <div class="p-3 bg-surface-50 border border-surface-200 rounded-xl">
                                <span class="text-[10px] font-semibold text-surface-500 uppercase tracking-wider block">Total Baris</span>
                                <span class="text-lg font-bold text-surface-900 block mt-0.5" x-text="validationSummary?.total_processed || 0"></span>
                            </div>
                            <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl">
                                <span class="text-[10px] font-semibold text-emerald-800 uppercase tracking-wider block">Data Baru</span>
                                <span class="text-lg font-bold text-emerald-900 block mt-0.5" x-text="validationSummary?.created || 0"></span>
                            </div>
                            <div class="p-3 bg-blue-50/70 border border-blue-200 rounded-xl">
                                <span class="text-[10px] font-semibold text-blue-800 uppercase tracking-wider block">Diperbarui</span>
                                <span class="text-lg font-bold text-blue-900 block mt-0.5" x-text="validationSummary?.updated || 0"></span>
                            </div>
                            <div class="p-3 bg-red-50/70 border border-red-200 rounded-xl">
                                <span class="text-[10px] font-semibold text-red-800 uppercase tracking-wider block">Ditolak / Invalid</span>
                                <span class="text-lg font-bold text-red-900 block mt-0.5" x-text="validationSummary?.skipped || 0"></span>
                            </div>
                        </div>

                        <!-- Warning Alert if any skipped rows -->
                        <template x-if="validationSummary && validationSummary.skipped > 0">
                            <div class="p-3.5 bg-red-50/90 border border-red-200 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-red-100 text-danger flex items-center justify-center text-sm shrink-0 mt-0.5">
                                        <i class="fas fa-triangle-exclamation"></i>
                                    </div>
                                    <div>
                                        <h5 class="text-xs font-bold text-danger">Terdapat <span x-text="validationSummary.skipped"></span> Baris yang Tidak Lolos Validasi</h5>
                                        <p class="text-[11px] text-surface-600 mt-0.5">Baris bermasalah akan dilewati secara otomatis. Unduh berkas di bawah untuk memeriksa alasan penolakan.</p>
                                    </div>
                                </div>
                                <form action="{{ route('admin.employees.import.downloadFailed') }}" method="POST" target="_blank" class="shrink-0">
                                    @csrf
                                    <input type="hidden" name="token" :value="importToken">
                                    <button type="submit" class="h-8 inline-flex items-center gap-1.5 px-3 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow-2xs transition cursor-pointer whitespace-nowrap">
                                        <i class="fas fa-file-arrow-down text-[11px]"></i>
                                        <span>Unduh Baris Gagal (.xlsx)</span>
                                    </button>
                                </form>
                            </div>
                        </template>

                        <!-- Sub-tab Navigation for Preview: Data Valid vs Detail Error -->
                        <div class="flex items-center justify-between border-b border-surface-200 pb-2">
                            <div class="inline-flex p-1 bg-surface-100 rounded-lg gap-1">
                                <button type="button" @click="previewActiveTab = 'sample'"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md transition cursor-pointer"
                                    :class="previewActiveTab === 'sample' ? 'bg-white text-surface-900 shadow-2xs font-bold' : 'text-surface-500 hover:text-surface-800'">
                                    <span>Pratinjau Data Valid</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                        :class="previewActiveTab === 'sample' ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-200 text-surface-600'"
                                        x-text="(validationSummary?.created + validationSummary?.updated) || 0"></span>
                                </button>
                                <button type="button" @click="previewActiveTab = 'errors'"
                                    x-show="validationSummary && validationSummary.skipped > 0"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 text-xs font-semibold rounded-md transition cursor-pointer"
                                    :class="previewActiveTab === 'errors' ? 'bg-white text-danger shadow-2xs font-bold' : 'text-surface-500 hover:text-danger'">
                                    <span>Detail Kendala Baris</span>
                                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                                        :class="previewActiveTab === 'errors' ? 'bg-red-100 text-danger' : 'bg-surface-200 text-surface-600'"
                                        x-text="validationSummary?.skipped || 0"></span>
                                </button>
                            </div>
                            <span class="text-[11px] text-surface-400" x-show="previewActiveTab === 'sample' && sampleRows.length > 0">
                                Menampilkan maks. 15 baris pertama
                            </span>
                        </div>

                        <!-- Tab 1 of Preview: Sample Valid Rows Table -->
                        <div x-show="previewActiveTab === 'sample'">
                            <template x-if="sampleRows.length > 0">
                                <div class="border border-surface-200 rounded-xl overflow-hidden shadow-2xs overflow-x-auto">
                                    <table class="w-full text-left border-collapse text-xs">
                                        <thead class="bg-surface-100 border-b border-surface-200 text-surface-700 text-[10px] uppercase font-semibold">
                                            <tr>
                                                <th class="py-2 px-3">Aksi</th>
                                                <th class="py-2 px-3">NIK</th>
                                                <th class="py-2 px-3">Nama Pegawai</th>
                                                <th class="py-2 px-3">Jabatan</th>
                                                <th class="py-2 px-3">Unit Kerja</th>
                                                <th class="py-2 px-3">Strata</th>
                                                <th class="py-2 px-3">Golongan</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-surface-100 text-[11px] bg-white font-mono">
                                            <template x-for="(row, idx) in sampleRows" :key="idx">
                                                <tr class="hover:bg-surface-50/80 transition">
                                                    <td class="py-2 px-3 font-sans">
                                                        <span x-show="row.action === 'CREATE'" class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-emerald-100 text-emerald-800">BARU</span>
                                                        <span x-show="row.action === 'UPDATE'" class="px-2 py-0.5 text-[9px] font-bold rounded-full bg-blue-100 text-blue-800">UPDATE</span>
                                                    </td>
                                                    <td class="py-2 px-3 font-bold text-surface-900" x-text="row.nik"></td>
                                                    <td class="py-2 px-3 font-sans text-surface-800 font-medium" x-text="row.nama"></td>
                                                    <td class="py-2 px-3 font-sans text-surface-700">
                                                        <div x-text="row.jabatan"></div>
                                                        <template x-if="row.tugas_rangkap">
                                                            <span class="inline-flex items-center gap-1 mt-0.5 px-1.5 py-0.5 text-[9px] font-semibold rounded bg-purple-100 text-purple-800" :title="row.tugas_rangkap">
                                                                <i class="fas fa-layer-group text-[8px]"></i>
                                                                <span class="truncate max-w-[140px]" x-text="row.tugas_rangkap"></span>
                                                            </span>
                                                        </template>
                                                    </td>
                                                    <td class="py-2 px-3 font-sans text-surface-700" x-text="row.unit_kerja"></td>
                                                    <td class="py-2 px-3 font-sans text-surface-600" x-text="row.level"></td>
                                                    <td class="py-2 px-3 text-surface-600" x-text="row.golongan"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="sampleRows.length === 0">
                                <div class="py-8 text-center text-xs text-surface-500 bg-surface-50 rounded-xl border border-dashed border-surface-200">
                                    <i class="fas fa-folder-open text-2xl text-surface-300 mb-2"></i>
                                    <p class="font-medium text-surface-700">Tidak ada baris data valid yang dapat diimpor.</p>
                                    <p class="text-[11px] text-surface-400 mt-0.5">Periksa kembali berkas Excel Anda dan sesuaikan dengan format template.</p>
                                </div>
                            </template>
                        </div>

                        <!-- Tab 2 of Preview: Error Details List -->
                        <div x-show="previewActiveTab === 'errors'" class="space-y-2">
                            <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
                                <template x-for="(err, idx) in validationErrors" :key="idx">
                                    <div class="p-3 bg-red-50/60 border border-red-200/80 rounded-xl text-xs flex items-start gap-2.5">
                                        <span class="px-2 py-0.5 rounded font-mono font-bold text-[10px] bg-red-100 text-danger shrink-0 mt-0.5"
                                            x-text="'Baris ' + (err.row || '?')"></span>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-surface-900 font-mono text-[11px]" x-show="err.nik && err.nik !== 'Unknown'" x-text="'NIK: ' + err.nik"></p>
                                            <p class="text-surface-700 text-[11px] mt-0.5 leading-relaxed"
                                                x-text="Array.isArray(err.errors) ? err.errors.join('; ') : err.errors"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-3.5 bg-surface-50 border-t border-surface-200 flex items-center justify-between gap-3 shrink-0">
                    <div class="min-w-0 flex-1">
                        <span x-show="importStep === 'upload' && importModalTab === 'specs'" class="hidden sm:inline-flex items-center gap-1.5 text-[11px] text-surface-500 font-medium truncate">
                            <i class="fas fa-circle-check text-emerald-600 text-[10px]"></i>
                            <span>Format tervalidasi sistem</span>
                        </span>
                        <button x-show="importStep === 'preview'" type="button" @click="resetModal()"
                            class="inline-flex items-center gap-1.5 text-xs text-primary-700 hover:text-primary-800 font-semibold cursor-pointer">
                            <i class="fas fa-arrow-left text-xs"></i>
                            <span>Ganti Berkas / Ulangi</span>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" @click="closeModal()"
                            class="h-9 px-4 rounded-lg text-surface-600 hover:text-surface-900 hover:bg-surface-200/60 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap shrink-0">
                            Batal
                        </button>
                        
                        <!-- Upload Step: Button Lanjut Unggah Berkas (from specs tab) -->
                        <button x-show="importStep === 'upload' && importModalTab === 'specs'" type="button" @click="importModalTab = 'upload'"
                            class="h-9 inline-flex items-center gap-2 px-4.5 rounded-lg bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-xs font-semibold transition-all shadow-xs active:scale-[0.98] cursor-pointer whitespace-nowrap shrink-0">
                            <span>Lanjut Unggah Berkas</span>
                            <i class="fas fa-arrow-right text-xs"></i>
                        </button>
                        
                        <!-- Upload Step: Button Cek & Pratinjau Data (Dry-Run) -->
                        <button x-show="importStep === 'upload' && importModalTab === 'upload'" type="button" @click="runDryRunPreview()" id="modalImportSubmitBtn"
                            :disabled="isValidating"
                            class="h-9 inline-flex items-center gap-2 px-5 rounded-lg bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-xs font-semibold transition-all shadow-xs active:scale-[0.98] cursor-pointer whitespace-nowrap shrink-0 disabled:opacity-50">
                            <i class="fas" :class="isValidating ? 'fa-spinner fa-spin' : 'fa-magnifying-glass-chart'"></i>
                            <span>Analisis & Pratinjau Data</span>
                        </button>
                        
                        <!-- Preview Step: Button Konfirmasi & Komit Impor -->
                        <button x-show="importStep === 'preview'" type="button" @click="executeCommitImport()"
                            :disabled="isCommitting || !validationSummary || (validationSummary.created + validationSummary.updated === 0)"
                            class="h-9 inline-flex items-center gap-2 px-5 rounded-lg bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white text-xs font-semibold transition-all shadow-xs active:scale-[0.98] cursor-pointer whitespace-nowrap shrink-0 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas" :class="isCommitting ? 'fa-spinner fa-spin' : 'fa-check-circle'"></i>
                            <span x-text="isCommitting ? 'Menyimpan Data...' : 'Konfirmasi & Impor ' + (validationSummary?.created + validationSummary?.updated || 0) + ' Data Valid'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function importModalHandler() {
    return {
        show: false,
        importModalTab: 'upload',
        specsFilter: 'all',
        importStep: 'upload',
        importToken: '',
        importFileName: '',
        importFileSize: '',
        isValidating: false,
        isCommitting: false,
        validationSummary: null,
        sampleRows: [],
        validationErrors: [],
        previewActiveTab: 'sample',
        openModal() {
            this.show = true;
            this.resetModal();
        },
        closeModal() {
            if (this.isValidating || this.isCommitting) return;
            this.show = false;
            this.resetModal();
        },
        resetModal() {
            this.importStep = 'upload';
            this.importModalTab = 'upload';
            this.importToken = '';
            this.importFileName = '';
            this.importFileSize = '';
            this.isValidating = false;
            this.isCommitting = false;
            this.validationSummary = null;
            this.sampleRows = [];
            this.validationErrors = [];
            this.previewActiveTab = 'sample';
            const fileInput = document.getElementById('modal_import_file');
            if (fileInput) fileInput.value = '';
            const def = document.getElementById('modalFileDefault');
            const sel = document.getElementById('modalFileSelected');
            const err = document.getElementById('modalFileError');
            if (def) def.classList.remove('hidden');
            if (sel) sel.classList.add('hidden');
            if (err) err.classList.add('hidden');
        },
        async runDryRunPreview() {
            const fileInput = document.getElementById('modal_import_file');
            if (!fileInput || !fileInput.files || !fileInput.files[0]) {
                alert('Silakan pilih berkas Excel (.xlsx / .xls) terlebih dahulu.');
                return;
            }
            this.isValidating = true;
            this.importStep = 'validating';

            const formData = new FormData();
            formData.append('import_file', fileInput.files[0]);
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
                    this.validationSummary = data.summary;
                    this.sampleRows = data.summary.sample_rows || [];
                    this.validationErrors = data.summary.errors || [];
                    this.importFileName = data.file_name;
                    this.importFileSize = data.file_size < 1024 * 1024
                        ? (data.file_size / 1024).toFixed(1) + ' KB'
                        : (data.file_size / (1024 * 1024)).toFixed(2) + ' MB';
                    this.previewActiveTab = (this.sampleRows.length > 0) ? 'sample' : 'errors';
                    this.importStep = 'preview';
                } else {
                    alert(data.message || (data.errors ? Object.values(data.errors).flat().join('\n') : 'Gagal memvalidasi berkas spreadsheet. Pastikan format sesuai template.'));
                    this.importStep = 'upload';
                }
            } catch (e) {
                alert('Terjadi kendala jaringan saat menganalisis berkas.');
                this.importStep = 'upload';
            } finally {
                this.isValidating = false;
            }
        },
        async executeCommitImport() {
            if (!this.importToken) return;
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
                    body: JSON.stringify({ token: this.importToken })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    window.location.href = data.redirect_url || '{{ route('admin.employees.index') }}';
                } else {
                    alert(data.message || 'Gagal menyimpan data impor ke database.');
                    this.isCommitting = false;
                }
            } catch (e) {
                alert('Terjadi kesalahan saat mengeksekusi komit impor data.');
                this.isCommitting = false;
            }
        }
    };
}

function handleModalFileChange(input) {
    const fileDefault = document.getElementById('modalFileDefault');
    const fileSelected = document.getElementById('modalFileSelected');
    const fileError = document.getElementById('modalFileError');
    const fileName = document.getElementById('modalFileName');
    const fileSize = document.getElementById('modalFileSize');
    const errorMsg = document.getElementById('modalFileErrorMsg');
    const submitBtn = document.getElementById('modalImportSubmitBtn');

    fileDefault?.classList.add('hidden');
    fileSelected?.classList.add('hidden');
    fileError?.classList.add('hidden');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        const validExtensions = ['xlsx', 'xls'];
        const ext = file.name.split('.').pop().toLowerCase();
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (!validExtensions.includes(ext)) {
            if (errorMsg) errorMsg.textContent = 'Format berkas tidak didukung: .' + ext;
            fileError?.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        if (file.size > maxSize) {
            if (errorMsg) errorMsg.textContent = 'Ukuran berkas (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) melebihi batas 10MB';
            fileError?.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        if (fileName) fileName.textContent = file.name;
        if (fileSize) {
            fileSize.textContent = file.size < 1024 * 1024
                ? (file.size / 1024).toFixed(1) + ' KB'
                : (file.size / (1024 * 1024)).toFixed(2) + ' MB';
        }
        fileSelected?.classList.remove('hidden');
        if (submitBtn) submitBtn.disabled = false;
    } else {
        fileDefault?.classList.remove('hidden');
        if (submitBtn) submitBtn.disabled = false;
    }
}

function clearModalFile(e) {
    e.preventDefault();
    e.stopPropagation();
    const fileInput = document.getElementById('modal_import_file');
    if (fileInput) fileInput.value = '';
    handleModalFileChange(fileInput);
}

document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('modalDropZone');
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.add('border-primary-500', 'bg-primary-50/50');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.remove('border-primary-500', 'bg-primary-50/50');
            }, false);
        });

        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('modal_import_file');
                if (fileInput) {
                    fileInput.files = files;
                    handleModalFileChange(fileInput);
                }
            }
        }, false);
    }
});
</script>
