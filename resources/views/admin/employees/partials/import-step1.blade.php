<!-- ============================================================ -->
<!-- STEP 1: WORKBENCH 2-KOLOM (UNGGAH BERKAS & TATA KELOLA HR)   -->
<!-- ============================================================ -->
<div x-show="currentStep === 1" x-transition.opacity.duration.300ms class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
    
    <!-- KOLOM KIRI: DROPZONE & AKSI (col-span-7) -->
    <div class="lg:col-span-7 bg-white rounded-2xl border border-surface-200/80 p-6 shadow-xs flex flex-col justify-between space-y-5 h-full">
        <!-- Header -->
        <div class="border-b border-surface-100 pb-3.5">
            <h3 class="text-base font-extrabold text-surface-900 flex items-center gap-2">
                <i class="fas fa-cloud-arrow-up text-primary-600" aria-hidden="true"></i>
                <span>Unggah Berkas Spreadsheet</span>
            </h3>
            <p class="text-xs text-surface-500 mt-0.5 leading-relaxed">
                Pilih berkas Excel (.xlsx / .xls) untuk penambahan pegawai baru atau pembaruan massal data formasi.
            </p>
        </div>

        <!-- Hidden Input -->
        <input type="file" id="page_import_file" accept=".xlsx, .xls" class="hidden" @change="handleFileSelected($event)">

        <!-- Dropzone Box (Kompak & Proporsional Setara Tahap 3) -->
        <div id="pageDropZone"
            @click="document.getElementById('page_import_file').click()"
            class="border-2 border-dashed rounded-2xl p-5 text-center cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-2.5 group bg-surface-50/50 hover:bg-primary-50/40 hover:border-primary-400 border-surface-300 flex-1 min-h-[140px]">
            
            <!-- State: Default (Belum Ada File) -->
            <template x-if="!selectedFile && !fileErrorMessage">
                <div class="space-y-2.5">
                    <div class="w-12 h-12 mx-auto rounded-2xl bg-white border border-surface-200 text-surface-500 group-hover:text-primary-600 group-hover:border-primary-200 group-hover:scale-105 transition flex items-center justify-center text-xl shadow-2xs">
                        <i class="fas fa-file-excel" aria-hidden="true"></i>
                    </div>
                    <div>
                        <p class="text-xs sm:text-sm font-bold text-surface-800">
                            Tarik &amp; Lepas berkas di sini, atau <span class="text-primary-600 group-hover:underline">Pilih Berkas</span>
                        </p>
                        <p class="text-[11px] text-surface-500 mt-0.5">Mendukung format Microsoft Excel .xlsx atau .xls (Maks. 10MB)</p>
                    </div>
                </div>
            </template>

            <!-- State: Berkas Terpilih -->
            <template x-if="selectedFile && !fileErrorMessage">
                <div class="w-full space-y-2.5" @click.stop>
                    <div class="p-3.5 bg-primary-50 border border-primary-200 rounded-xl flex items-center justify-between gap-3 text-left">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-primary-600 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                                <i class="fas fa-file-excel" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-primary-950 truncate" x-text="selectedFile.name"></p>
                                <p class="text-[11px] text-primary-700 mt-0.5" x-text="formatFileSize(selectedFile.size)"></p>
                            </div>
                        </div>
                        <button type="button" @click="clearFile()" class="p-2 text-surface-500 hover:text-danger transition cursor-pointer" title="Hapus Berkas">
                            <i class="fas fa-trash-alt text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="text-[11px] text-primary-700 font-semibold flex items-center justify-center gap-1.5">
                        <i class="fas fa-circle-check text-xs" aria-hidden="true"></i> Berkas siap dianalisis dan divalidasi.
                    </p>
                </div>
            </template>

            <!-- State: Error Validasi Berkas -->
            <template x-if="fileErrorMessage">
                <div class="w-full space-y-2.5" @click.stop>
                    <div class="p-3.5 bg-danger-50 border border-danger-200 rounded-xl flex items-center justify-between gap-3 text-left">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-danger-100 text-danger flex items-center justify-center text-lg shrink-0">
                                <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-danger truncate" x-text="fileErrorMessage"></p>
                                <p class="text-[11px] text-surface-500 mt-0.5">Silakan pilih berkas Excel (.xlsx / .xls) maksimal 10MB.</p>
                            </div>
                        </div>
                        <button aria-label="Hapus berkas terpilih" type="button" @click="clearFile()" class="p-2 text-surface-500 hover:text-surface-700 transition cursor-pointer">
                            <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Action Buttons: Primary & Secondary -->
        <div class="space-y-2.5 pt-3 border-t border-surface-100">
            <!-- Primary CTA -->
            <button type="button" 
                @click="runDryRunPreview()"
                :disabled="!selectedFile || isValidating"
                class="w-full py-3 px-4 rounded-xl text-xs sm:text-sm font-bold text-white transition flex items-center justify-center gap-2 shadow-xs cursor-pointer"
                :class="(!selectedFile || isValidating) ? 'bg-surface-300 cursor-not-allowed' : 'bg-primary-600 hover:bg-primary-700 active:scale-[0.99] shadow-md shadow-primary-600/20'">
                
                <template x-if="!isValidating">
                    <span class="inline-flex items-center gap-2">
                        <span>Mulai Analisis Validasi Spreadsheet</span>
                        <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                    </span>
                </template>

                <template x-if="isValidating">
                    <span class="inline-flex items-center gap-2">
                        <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
                        <span>Menganalisis Format &amp; 26 Kolom...</span>
                    </span>
                </template>
            </button>

            <!-- Secondary Actions Row: Unduh Template & Buka Kamus -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <a href="{{ route('admin.employees.downloadTemplate') }}"
                    class="py-2.5 px-3 rounded-xl border border-surface-200 bg-white hover:bg-surface-50 text-surface-700 text-xs font-bold transition flex items-center justify-center gap-2 shadow-2xs">
                    <i class="fas fa-file-arrow-down text-primary-600 text-sm" aria-hidden="true"></i>
                    <span>Unduh Template Excel Resmi (.xlsx)</span>
                </a>

                <button type="button"
                    @click="openDictionaryDrawer()"
                    class="py-2.5 px-3 rounded-xl border border-primary-200 bg-primary-50/50 hover:bg-primary-100/60 text-primary-800 text-xs font-bold transition flex items-center justify-center gap-2 shadow-2xs cursor-pointer">
                    <i class="fas fa-book-bookmark text-primary-600 text-sm" aria-hidden="true"></i>
                    <span>Kamus Format (26 Kolom)</span>
                    <i class="fas fa-arrow-up-right-from-square text-[10px] text-primary-600" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN: SPESIFIKASI & TATA KELOLA HR (Tahap 1) --}}
    @include('admin.employees.partials.import-step1-governance')

</div>
