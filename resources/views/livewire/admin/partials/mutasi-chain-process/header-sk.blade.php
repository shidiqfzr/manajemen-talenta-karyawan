    <!-- ── 1. HEADER SK DIREKSI KOLEKTIF ── -->
    <div class="bg-white rounded-2xl border border-surface-200 shadow-xs p-6 space-y-5">
        <div class="flex items-center justify-between pb-4 border-b border-surface-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold text-lg shadow-2xs">
                    <i class="fas fa-file-signature text-base" aria-hidden="true"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-surface-900 tracking-tight">Data SK Direksi Mutasi Kolektif</h3>
                    <p class="text-xs text-surface-500">Nomor SK, tanggal penetapan, dan TMT efektif yang berlaku serentak untuk seluruh mata rantai alih tugas</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 border border-primary-200/60">
                <i class="fas fa-shield-halved text-[10px]" aria-hidden="true"></i>
                <span>SK Bersama (Batch)</span>
            </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Nomor SK -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    Nomor SK Direksi <span class="text-danger font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                        <i class="fas fa-file-contract text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="nomorSk"
                        placeholder="Contoh: SK/DIR/MUTASI/X/2026"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white font-medium text-surface-800 placeholder-surface-400 focus:outline-none transition {{ $errors->has('nomorSk') ? 'border-danger focus:border-danger focus:ring-2 focus:ring-danger-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @error('nomorSk') <p class="text-[11px] text-danger font-medium mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Tanggal SK -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    Tanggal Penetapan SK <span class="text-danger font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                        <i class="fas fa-calendar-day text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="date" wire:model.live="tanggalSk"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white font-medium text-surface-800 focus:outline-none transition {{ $errors->has('tanggalSk') ? 'border-danger focus:border-danger focus:ring-2 focus:ring-danger-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @error('tanggalSk') <p class="text-[11px] text-danger font-medium mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- TMT Efektif -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    TMT Efektif Bertugas <span class="text-danger font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                        <i class="fas fa-calendar-check text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="date" wire:model.live="tmtAwal"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white font-medium text-surface-800 focus:outline-none transition {{ $errors->has('tmtAwal') ? 'border-danger focus:border-danger focus:ring-2 focus:ring-danger-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @error('tmtAwal') <p class="text-[11px] text-danger font-medium mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-1">
            <!-- Dokumen SK PDF -->
            <x-admin.file-upload 
                wire-model="fileSk"
                :file="$fileSk"
                label="Berkas Lampiran SK Kolektif"
                helper="(PDF, Maks. 10MB)"
                button-text="Pilih Berkas"
                change-button-text="Ganti Berkas"
                error-key="fileSk"
            />

            <!-- Catatan SK -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    Catatan Pokok / Perihal SK
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                        <i class="fas fa-align-left text-xs" aria-hidden="true"></i>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="catatanHeader"
                        placeholder="Contoh: Rotasi & Pengisian Pejabat Struktural Wilayah Regional V"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border border-surface-300 bg-white font-medium text-surface-800 placeholder-surface-400 text-xs sm:text-sm focus:border-primary-600 focus:ring-2 focus:ring-primary-100 focus:outline-none transition">
                </div>
            </div>
        </div>
    </div>
