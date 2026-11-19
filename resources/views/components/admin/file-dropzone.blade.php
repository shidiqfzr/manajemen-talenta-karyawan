{{--
    Area unggah berkas berbasis Alpine untuk alur yang mengirim berkas sendiri (fetch/FormData), bukan lewat Livewire.
    Pasangan x-admin.file-upload (isian ringkas berbasis Livewire): satu komponen bersama untuk area tarik-dan-lepas, sehingga
    layar tidak menulis <input type="file"> sendiri (DESIGN.md §6, §17 D20).

    Dipakai di dalam komponen Alpine yang memegang keadaan berkasnya:

    <x-admin.file-dropzone id="page_import_file" accept=".xlsx, .xls" file="selectedFile" error="fileErrorMessage"
        select="handleFileSelected($event)" clear="clearFile()" size="formatFileSize(selectedFile.size)" />

    file   : ekspresi Alpine untuk berkas terpilih (objek File atau null); nama berkasnya ditampilkan.
    error  : ekspresi Alpine untuk pesan galat berkas (string; kosong bila sah).
    select : ekspresi yang dijalankan saat berkas dipilih atau dilepas (menerima $event dari input).
    clear  : ekspresi yang menghapus pilihan berkas.
    size   : ekspresi yang menghasilkan teks ukuran berkas.
    id     : id input berkas (tertaut ke area lewat aria-controls, dan ke wizard bila perlu menyetel ulang nilainya).
    inputLabel: nama aksesibel input berkas yang disembunyikan secara visual (WCAG 4.1.2); bawaannya "Pilih berkas untuk diunggah".

    Aksesibilitas: area adalah tombol yang dapat difokus dan dibuka dengan Enter atau Spasi; berkas yang dipilih dan galatnya
    diumumkan (role="status" / role="alert"); input berkas yang disembunyikan tetap ada di pohon aksesibilitas lewat sr-only.
--}}
@props([
    'id' => 'file_dropzone',
    'accept' => '.xlsx, .xls',
    'file' => 'selectedFile',
    'error' => 'fileErrorMessage',
    'select' => '',
    'clear' => 'clearFile()',
    'size' => '""',
    'icon' => 'fas fa-file-excel',
    'title' => 'Tarik & Lepas berkas di sini, atau',
    'action' => 'Pilih Berkas',
    'hint' => 'Mendukung format Microsoft Excel .xlsx atau .xls (Maks. 10MB)',
    'errorHint' => 'Silakan pilih berkas Excel (.xlsx / .xls) maksimal 10MB.',
    'readyText' => 'Berkas siap dianalisis dan divalidasi.',
    'inputLabel' => 'Pilih berkas untuk diunggah',
])

<div x-data="{
        dragging: false,
        drop(event) {
            this.dragging = false;
            const files = event.dataTransfer?.files;
            if (files && files.length > 0) {
                this.$refs.input.files = files;
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
    }"
    class="flex flex-col flex-1">
    <input type="file" id="{{ $id }}" x-ref="input" accept="{{ $accept }}" class="sr-only" tabindex="-1" aria-label="{{ $inputLabel }}" @change="{{ $select }}">

    <div role="button" tabindex="0" aria-controls="{{ $id }}" aria-label="{{ $action }} (tarik dan lepas didukung): {{ $hint }}"
        @click="$refs.input.click()"
        @keydown.enter.prevent="$refs.input.click()"
        @keydown.space.prevent="$refs.input.click()"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="drop($event)"
        :class="dragging ? 'border-primary-500 bg-primary-50/60' : 'border-surface-300 bg-surface-50/50'"
        class="border-2 border-dashed rounded-2xl p-5 text-center cursor-pointer transition-all duration-200 flex flex-col items-center justify-center gap-2.5 group hover:bg-primary-50/40 hover:border-primary-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 flex-1 min-h-[140px]">

        {{-- Belum ada berkas --}}
        <template x-if="!{{ $file }} && !{{ $error }}">
            <div class="space-y-2.5">
                <div class="w-12 h-12 mx-auto rounded-2xl bg-white border border-surface-200 text-surface-500 group-hover:text-primary-600 group-hover:border-primary-200 group-hover:scale-105 transition flex items-center justify-center text-xl shadow-2xs">
                    <i class="{{ $icon }}" aria-hidden="true"></i>
                </div>
                <div>
                    <p class="text-xs sm:text-sm font-bold text-surface-800">
                        {{ $title }} <span class="text-primary-600 group-hover:underline">{{ $action }}</span>
                    </p>
                    <p class="text-[11px] text-surface-500 mt-0.5">{{ $hint }}</p>
                </div>
            </div>
        </template>

        {{-- Berkas terpilih --}}
        <template x-if="{{ $file }} && !{{ $error }}">
            <div class="w-full space-y-2.5" @click.stop role="status">
                <div class="p-3.5 bg-primary-50 border border-primary-200 rounded-xl flex items-center justify-between gap-3 text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-primary-600 text-white flex items-center justify-center text-lg shrink-0 shadow-xs">
                            <i class="{{ $icon }}" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-primary-950 truncate" x-text="{{ $file }}.name"></p>
                            <p class="text-[11px] text-primary-700 mt-0.5" x-text="{{ $size }}"></p>
                        </div>
                    </div>
                    <button type="button" @click="{{ $clear }}" aria-label="Hapus berkas terpilih" title="Hapus Berkas"
                        class="p-2 text-surface-500 hover:text-danger transition cursor-pointer rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        <i class="fas fa-trash-alt text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="text-[11px] text-primary-700 font-semibold flex items-center justify-center gap-1.5">
                    <i class="fas fa-circle-check text-xs" aria-hidden="true"></i> {{ $readyText }}
                </p>
            </div>
        </template>

        {{-- Galat validasi berkas --}}
        <template x-if="{{ $error }}">
            <div class="w-full space-y-2.5" @click.stop role="alert">
                <div class="p-3.5 bg-danger-50 border border-danger-200 rounded-xl flex items-center justify-between gap-3 text-left">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl bg-danger-100 text-danger flex items-center justify-center text-lg shrink-0">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold text-danger truncate" x-text="{{ $error }}"></p>
                            <p class="text-[11px] text-surface-500 mt-0.5">{{ $errorHint }}</p>
                        </div>
                    </div>
                    <button type="button" @click="{{ $clear }}" aria-label="Hapus berkas terpilih"
                        class="p-2 text-surface-500 hover:text-surface-700 transition cursor-pointer rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        <i class="fas fa-xmark text-sm" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        </template>
    </div>
</div>
