    <!-- ── 3. DATA SK DIREKSI MUTASI & LEGALITAS (AUDIT TRAIL) ── -->
    <div class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5 sm:p-6 space-y-5 animate-fade-in">
        <div class="flex items-center justify-between pb-4 border-b border-surface-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold text-lg shadow-2xs">
                    <i class="fas fa-file-signature text-base"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-surface-900 tracking-tight">Data SK Direksi Mutasi &amp; Legalitas</h3>
                    <p class="text-xs text-surface-500">Nomor SK, tanggal penetapan, dan TMT efektif yang menjadi dasar hukum penugasan definitif</p>
                </div>
            </div>
        </div>

        @if ($this->blockingReason)
            <p class="text-xs text-surface-500">Data SK dapat dilengkapi setelah kendala formasi di atas diselesaikan.</p>
        @endif

        <fieldset @disabled($this->blockingReason) class="{{ $this->blockingReason ? 'opacity-50' : '' }} space-y-5 min-w-0">
        <!-- Klausul Penyesuaian Golongan & Job Grade Baru (Promosi/Demosi) -->
        @if (in_array($form->jenisMutasi, ['PROMOSI', 'DEMOSI']))
            <div class="p-4 sm:p-5 rounded-2xl bg-surface-50/80 border border-surface-200 space-y-3.5 shadow-2xs animate-fade-in">
                <div class="flex items-center justify-between pb-2 border-b border-surface-200/70">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-lg bg-primary-700 text-white flex items-center justify-center text-xs shadow-2xs shrink-0">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-surface-900 uppercase tracking-wider">
                                Klausul Penetapan Golongan &amp; Job Grade Baru ({{ \App\Models\JobHistory::labelFor($form->jenisMutasi) }})
                            </h4>
                            <p class="text-[11px] text-surface-500">Klausul hukum penyesuaian kepangkatan/grade yang disahkan dalam SK Direksi ini</p>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-1">
                    <div>
                        <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                            Golongan Baru <span class="text-rose-500 font-bold">*</span>
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-400">
                                <i class="fas fa-id-badge text-xs"></i>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="form.newGolongan" placeholder="Contoh: IIID/00 atau IVA/00"
                                class="w-full pl-9.5 pr-3.5 py-2.5 bg-white border rounded-xl text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none transition {{ ($errors->has('newGolongan') || $errors->has('form.newGolongan')) ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                        </div>
                        <span class="text-[11px] text-surface-500 mt-1.5 block">Saat Ini: <strong class="text-surface-800 font-semibold">{{ $employeeDetails['golongan'] ?? '—' }}</strong></span>
                        @if ($errors->has('newGolongan') || $errors->has('form.newGolongan'))
                            <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $errors->first('newGolongan') ?: $errors->first('form.newGolongan') }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                            Job Grade Baru <span class="text-surface-400 font-normal">(Opsional)</span>
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-400">
                                <i class="fas fa-chart-simple text-xs"></i>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="form.newJobGrade" placeholder="Contoh: 11 s.d. 16"
                                class="w-full pl-9.5 pr-3.5 py-2.5 bg-white border rounded-xl text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none transition {{ ($errors->has('newJobGrade') || $errors->has('form.newJobGrade')) ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                        </div>
                        <span class="text-[11px] text-surface-500 mt-1.5 block">Saat Ini: <strong class="text-surface-800 font-semibold">{{ $employeeDetails['job_grade'] ?? '—' }}</strong></span>
                        @if ($errors->has('newJobGrade') || $errors->has('form.newJobGrade'))
                            <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $errors->first('newJobGrade') ?: $errors->first('form.newJobGrade') }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <!-- Baris 1: 3 Kolom -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <!-- Nomor SK -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    Nomor SK Direksi <span class="text-rose-500 font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-400">
                        <i class="fas fa-file-contract text-xs"></i>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="form.nomorSk"
                        placeholder="Contoh: SK.DIR/REG5/MUT-SDM/01/2026"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white font-medium text-surface-800 placeholder-surface-400 focus:outline-none transition {{ ($errors->has('nomorSk') || $errors->has('form.nomorSk')) ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @if ($errors->has('nomorSk') || $errors->has('form.nomorSk'))
                    <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $errors->first('nomorSk') ?: $errors->first('form.nomorSk') }}</p>
                @endif
            </div>

            <!-- Tanggal Penetapan SK -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    Tanggal Penetapan SK <span class="text-rose-500 font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-400">
                        <i class="fas fa-calendar-day text-xs"></i>
                    </div>
                    <input type="date" wire:model.live="form.tanggalSk"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white font-medium text-surface-800 focus:outline-none transition {{ ($errors->has('tanggalSk') || $errors->has('form.tanggalSk')) ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @if ($errors->has('tanggalSk') || $errors->has('form.tanggalSk'))
                    <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $errors->first('tanggalSk') ?: $errors->first('form.tanggalSk') }}</p>
                @endif
            </div>

            <!-- TMT Efektif Bertugas -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    TMT Efektif Bertugas <span class="text-rose-500 font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-400">
                        <i class="fas fa-calendar-check text-xs"></i>
                    </div>
                    <input type="date" wire:model.live="form.tmtAwal"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border text-xs sm:text-sm bg-white font-medium text-surface-800 focus:outline-none transition {{ ($errors->has('tmtAwal') || $errors->has('form.tmtAwal')) ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @if ($errors->has('tmtAwal') || $errors->has('form.tmtAwal'))
                    <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $errors->first('tmtAwal') ?: $errors->first('form.tmtAwal') }}</p>
                @endif
            </div>
        </div>

        <!-- Baris 2: 2 Kolom -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-1">
            <!-- Berkas Lampiran SK Digital -->
            <x-admin.file-upload 
                wire-model="form.fileSk"
                :file="$form->fileSk"
                label="Berkas Lampiran SK Digital"
                helper="(PDF, Maks. 5MB)"
                button-text="Pilih Berkas"
                change-button-text="Ganti Berkas"
                error-key="fileSk"
            />

            <!-- Catatan Pokok / Perihal Mutasi -->
            <div>
                <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                    Catatan Pokok / Perihal Mutasi <span class="text-rose-500 font-bold">*</span>
                </label>
                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-400">
                        <i class="fas fa-align-left text-xs"></i>
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="form.catatan"
                        placeholder="Contoh: Pengisian Formasi Asisten Tanaman Afdeling III Kebun Inti"
                        class="w-full pl-9.5 pr-3.5 py-2.5 rounded-xl border bg-white font-medium text-surface-800 placeholder-surface-400 text-xs sm:text-sm focus:outline-none transition {{ ($errors->has('catatan') || $errors->has('form.catatan')) ? 'border-rose-400 focus:border-rose-500 focus:ring-2 focus:ring-rose-200' : 'border-surface-300 focus:border-primary-600 focus:ring-2 focus:ring-primary-100' }}">
                </div>
                @if ($errors->has('catatan') || $errors->has('form.catatan'))
                    <p class="text-[11px] text-rose-600 font-medium mt-1">{{ $errors->first('catatan') ?: $errors->first('form.catatan') }}</p>
                @endif
            </div>
        </div>
        </fieldset>
    </div>
