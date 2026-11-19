    {{-- Rantai mutasi kolektif (gerbong): cangkang x-admin.modal, hanya baca --}}
    @if ($selectedBatchId && $batchItems)
        <x-admin.modal open="true" close="$wire.closeBatchModal()" size="xl" icon="fas fa-diagram-project" title-id="modal-batch-title" cancel="Tutup" scroll>
            <x-slot:heading>Rantai Mutasi Kolektif (Gerbong)</x-slot:heading>
            <x-slot:subtitle><span class="font-mono text-xs">Batch ID: {{ substr($selectedBatchId, 0, 8) }}... | Total {{ count($batchItems) }} Karyawan Terlibat</span></x-slot:subtitle>

            <div class="space-y-2.5">
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
        </x-admin.modal>
    @endif
