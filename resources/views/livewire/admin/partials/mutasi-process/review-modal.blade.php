    {{-- Konfirmasi pengesahan mutasi: cangkang x-admin.modal (DESIGN.md §7.6) --}}
    @if ($showSummaryModal)
        <x-admin.modal open="true" close="$wire.closeSummaryModal()" size="xl" icon="fas fa-clipboard-check" title-id="summary-modal-title" cancel="Kembali / Ubah Data" scroll>
            <x-slot:heading>Konfirmasi Pengesahan Mutasi</x-slot:heading>
            <x-slot:subheading>Harap periksa kembali rincian SK &amp; dampak formasi sebelum commit ke database.</x-slot:subheading>
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
                        <p class="text-warning-800 text-[11px]">• Kualifikasi Promosi: {{ $form->alasanDispensasiPromosi }}</p>
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
            <x-slot:footer>
                <x-admin.button wire:click="confirmSubmitMutasi" target="confirmSubmitMutasi" icon="fas fa-check-circle">Sahkan &amp; Eksekusi Mutasi</x-admin.button>
            </x-slot:footer>
        </x-admin.modal>
    @endif
