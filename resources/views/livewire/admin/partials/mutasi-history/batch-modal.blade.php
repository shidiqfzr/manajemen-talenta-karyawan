    <!-- ── 6. BATCH COLLECTIVE MODAL (GERBONG EXPLORER) ── -->
    @if ($selectedBatchId && $batchItems)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-surface-900/60 backdrop-blur-xs animate-fade-in"
             x-data x-trap.noscroll="true" tabindex="-1" @keydown.escape.window="$wire.closeBatchModal()"
             role="dialog"
             aria-modal="true"
             aria-labelledby="modal-batch-title">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-2xl w-full p-6 space-y-4 max-h-[85vh] flex flex-col animate-scale-in">
                <!-- Modal Header -->
                <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-800 flex items-center justify-center font-bold text-base shadow-2xs">
                            <i class="fas fa-diagram-project" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 id="modal-batch-title" class="text-sm font-bold text-surface-900">Rantai Mutasi Kolektif (Gerbong)</h3>
                            <p class="text-[11px] text-surface-500 font-mono">Batch ID: {{ substr($selectedBatchId, 0, 8) }}... | Total {{ count($batchItems) }} Pegawai Terlibat</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeBatchModal" class="text-surface-500 hover:text-surface-700 transition p-1 cursor-pointer" title="Tutup jendela (Esc)">
                        <i class="fas fa-times text-sm" aria-hidden="true"></i>
                    </button>
                </div>

                <!-- Batch Items List -->
                <div class="overflow-y-auto space-y-2.5 flex-1 pr-1">
                    @foreach ($batchItems as $item)
                        <div class="p-3.5 rounded-xl border border-surface-200 bg-surface-50/70 hover:bg-surface-50 transition flex items-center justify-between gap-3 text-xs">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-7 h-7 rounded-lg bg-surface-900 text-white font-bold text-[10px] flex items-center justify-center shrink-0 shadow-2xs">
                                    #{{ $item->chain_order ?? 1 }}
                                </span>
                                <div class="min-w-0">
                                    <div class="font-bold text-surface-900 truncate">{{ $item->employee?->nama ?? $item->employee_nik }}</div>
                                    <div class="text-[10px] text-surface-500 font-mono truncate">NIK: {{ $item->employee_nik }}</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="font-bold text-primary-900">{{ $item->jabatan }}</div>
                                <div class="text-[10px] text-surface-500 flex items-center justify-end gap-1 mt-0.5">
                                    <i class="fas fa-building text-[10px] text-surface-400" aria-hidden="true"></i>
                                    <span>{{ $item->unit_kerja }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Modal Footer -->
                <div class="pt-3 border-t border-surface-100 flex justify-end">
                    <button type="button" wire:click="closeBatchModal"
                        class="px-4 py-2 rounded-xl bg-surface-100 hover:bg-surface-200 text-surface-800 text-xs font-semibold transition cursor-pointer">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    @endif
