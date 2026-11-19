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
                Pilih berkas Excel (.xlsx / .xls) untuk penambahan karyawan baru atau pembaruan massal data formasi.
            </p>
        </div>

        <x-admin.file-dropzone id="page_import_file" accept=".xlsx, .xls" file="selectedFile" error="fileErrorMessage"
            select="handleFileSelected($event)" clear="clearFile()" size="formatFileSize(selectedFile.size)" />

        <!-- Action Buttons: Primary & Secondary -->
        <div class="space-y-2.5 pt-3 border-t border-surface-100">
            <!-- Aksi utama: satu tinggi (md) dengan tombol lain pada area aksi ini; hierarki lewat warna dan lebar penuh -->
            <x-admin.button type="button" variant="primary" class="w-full" @click="runDryRunPreview()" x-bind:disabled="!selectedFile || isValidating">
                <template x-if="!isValidating">
                    <span class="inline-flex items-center gap-2">
                        <span>Mulai Analisis Validasi Spreadsheet</span>
                        <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                    </span>
                </template>

                <template x-if="isValidating">
                    <span class="inline-flex items-center gap-2">
                        <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i>
                        <span>Menganalisis Format &amp; {{ $guideTotal }} Kolom...</span>
                    </span>
                </template>
            </x-admin.button>

            <!-- Aksi bantu: Unduh Template & Buka Kamus, dua kolom -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <x-admin.button href="{{ route('admin.employees.downloadTemplate') }}" variant="secondary" icon="fas fa-file-arrow-down">Unduh Template Excel Resmi</x-admin.button>

                <x-admin.button type="button" variant="soft" icon="fas fa-book-bookmark" @click="openDictionaryDrawer()">Kamus Format ({{ $guideTotal }} Kolom)</x-admin.button>
            </div>
        </div>
    </div>

    {{-- KOLOM KANAN: SPESIFIKASI & TATA KELOLA HR (Tahap 1) --}}
    @include('admin.employees.partials.import-step1-governance')

</div>
