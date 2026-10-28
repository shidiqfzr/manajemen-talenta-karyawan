    <!-- ── 4. REVIEW & CONFIRMATION MODAL ── -->
    @if ($showSummaryModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4 animate-fade-in"
             role="dialog" aria-modal="true" aria-labelledby="summary-modal-title"
             x-data x-trap.noscroll="true" tabindex="-1" @keydown.escape.window="$wire.closeSummaryModal()">
            <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full overflow-hidden border border-surface-200 transition-all transform scale-100">
                <!-- Modal Header -->
                <div class="bg-surface-50 px-6 py-4 border-b border-surface-200 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-bold shadow-2xs">
                            <i class="fas fa-clipboard-check" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 id="summary-modal-title" class="text-base font-bold text-surface-900 leading-tight">Konfirmasi Pengesahan Mutasi</h3>
                            <p class="text-xs text-surface-500 mt-0.5">Harap periksa kembali rincian SK &amp; dampak formasi sebelum commit ke database.</p>
                        </div>
                    </div>
                    <button aria-label="Tutup" type="button" wire:click="closeSummaryModal" class="text-surface-500 hover:text-surface-600 transition p-1.5 rounded-lg hover:bg-surface-200 cursor-pointer">
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- Movement Flow Summary -->
                    <div class="p-4 bg-primary-50/50 rounded-xl border border-primary-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-primary-800">
                                Jenis Mutasi: {{ \App\Models\JobHistory::labelFor($form->jenisMutasi) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-primary-100 text-primary-800 border border-primary-200">
                                Status: {{ $form->statusJabatan }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-white rounded-xl border border-surface-200 space-y-1">
                                <span class="text-[10px] font-bold text-surface-500 uppercase tracking-wider block">Posisi Asal</span>
                                <div class="font-bold text-surface-900 text-sm">{{ $employeeDetails['nama'] ?? '—' }}</div>
                                <div class="text-surface-600 font-medium">{{ $employeeDetails['jabatan'] ?? '—' }}</div>
                                <div class="text-surface-500 text-[11px]">{{ $employeeDetails['unit_kerja'] ?? '—' }}</div>
                                <div class="text-[10px] font-mono text-surface-500 mt-1">Gol: {{ $employeeDetails['golongan'] ?? '—' }} | {{ $employeeDetails['rm_level'] ?? '—' }}</div>
                            </div>

                            <div class="p-3 bg-white rounded-xl border border-surface-200 space-y-1">
                                <span class="text-[10px] font-bold text-primary-600 uppercase tracking-wider block">Posisi Tujuan</span>
                                <div class="font-bold text-primary-900 text-sm">{{ $form->targetJabatan }}</div>
                                <div class="text-primary-800 font-medium">{{ $form->targetUnitKerja }}</div>
                                <div class="text-[10px] font-mono text-surface-600 mt-1">
                                    Gol Baru: {{ $form->newGolongan ?: ($employeeDetails['golongan'] ?? '—') }} 
                                    @if($form->newJobGrade) | Grade: {{ $form->newJobGrade }} @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Swap Details (if occupied) -->
                    @if ($targetAnalysis && $targetAnalysis['type'] === 'occupied')
                        <div class="p-4 bg-earth-50 rounded-xl border border-earth-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-arrows-rotate text-earth-700 text-xs" aria-hidden="true"></i>
                                <span class="text-xs font-bold text-earth-900 uppercase tracking-wider">Dampak Tukar Jabatan (Lateral Swap)</span>
                            </div>
                            <p class="text-xs text-earth-800 leading-relaxed">
                                Pejabat <strong>{{ $targetAnalysis['occupant']['nama'] }}</strong> (NIK: {{ $targetAnalysis['occupant']['nik'] }}) secara otomatis akan dipindahkan menempati posisi <strong>{{ $employeeDetails['jabatan'] }}</strong> di <strong>{{ $employeeDetails['unit_kerja'] }}</strong>.
                            </p>
                        </div>
                    @endif

                    <!-- Legal Details -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs bg-surface-50 p-3.5 rounded-xl border border-surface-200">
                        <div>
                            <span class="text-[10px] text-surface-500 block font-medium">Nomor SK:</span>
                            <span class="font-mono font-bold text-surface-800 text-[11px]">{{ $form->nomorSk }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-surface-500 block font-medium">Tanggal SK:</span>
                            <span class="font-semibold text-surface-800">{{ $form->tanggalSk ? \Carbon\Carbon::parse($form->tanggalSk)->format('d M Y') : '-' }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-surface-500 block font-medium">TMT Efektif:</span>
                            <span class="font-semibold text-primary-700">{{ $form->tmtAwal ? \Carbon\Carbon::parse($form->tmtAwal)->format('d M Y') : '-' }}</span>
                        </div>
                    </div>

                    <!-- Dispensations Notice (if any) -->
                    @if ($form->dispensasiTenure || $form->dispensasiPromosi)
                        <div class="p-3 bg-warning-50 border border-warning-200 rounded-xl space-y-1 text-xs">
                            <span class="font-bold text-warning-800 flex items-center gap-1.5">
                                <i class="fas fa-shield-alt text-warning-700" aria-hidden="true"></i> Dispensasi Direksi Terlampir:
                            </span>
                            @if ($form->dispensasiTenure)
                                <p class="text-warning-800 text-[11px]">• Masa Dinas Unit: {{ $form->alasanDispensasiTenure }}</p>
                            @endif
                            @if ($form->dispensasiPromosi)
                                <p class="text-warning-800 text-[11px]">• Kriteria Promosi: {{ $form->alasanDispensasiPromosi }}</p>
                            @endif
                        </div>
                    @endif

                    <!-- File SK notice -->
                    @if ($form->fileSk)
                        <div class="text-xs text-surface-600 flex items-center gap-2">
                            <i class="fas fa-file-pdf text-danger" aria-hidden="true"></i>
                            <span>Dokumen SK Digital: <strong>{{ $form->fileSk->getClientOriginalName() }}</strong> (Disimpan di Storage Privat Terproteksi)</span>
                        </div>
                    @endif
                </div>

                <!-- Modal Footer -->
                <div class="bg-surface-50 px-6 py-3.5 border-t border-surface-200 flex items-center justify-between">
                    <button type="button" wire:click="closeSummaryModal" class="px-4 py-2 bg-white border border-surface-300 rounded-xl text-xs font-semibold text-surface-700 hover:bg-surface-100 transition cursor-pointer">
                        Kembali / Ubah Data
                    </button>

                    <button type="button" wire:click="confirmSubmitMutasi"
                        wire:loading.attr="disabled"
                        class="px-5 py-2.5 bg-primary-600 text-white rounded-xl text-xs font-bold hover:bg-primary-700 transition shadow-sm flex items-center gap-2 cursor-pointer">
                        <span wire:loading.remove wire:target="confirmSubmitMutasi">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> Sahkan &amp; Eksekusi Mutasi
                        </span>
                        <span wire:loading wire:target="confirmSubmitMutasi" class="flex items-center gap-1.5">
                            <i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Menyimpan Perubahan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
