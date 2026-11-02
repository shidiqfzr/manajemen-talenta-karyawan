    <!-- ── 4. ACTION SUBMIT / REVIEW BAR ── -->
    <div class="pt-2 space-y-2 sticky bottom-4 z-30">
        @if ($errors->any())
            <div class="bg-danger-50 border border-danger-200 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs animate-fade-in"
                role="alert" x-data="{ show: true }" x-show="show">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-danger-100 text-danger-700 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div class="space-y-0.5 text-danger-800">
                        <span class="font-bold block">Pemberitahuan Kelengkapan Data:</span>
                        <p class="font-medium text-danger-800 leading-relaxed">
                            {{ $errors->first() }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-danger hover:text-danger-700 p-1.5 cursor-pointer self-end sm:self-center" title="Tutup pemberitahuan">
                    <i class="fas fa-times text-xs" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        <div data-blocked="{{ $this->blockingReason ? 'true' : 'false' }}"
            class="bg-white rounded-2xl border border-surface-200 shadow-xs p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 text-xs text-surface-600">
                <div class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center font-bold text-surface-700 shrink-0">
                    <i class="fas fa-user-check text-xs" aria-hidden="true"></i>
                </div>
                <div>
                    @if ($employeeDetails && $form->targetJabatan)
                        <span class="font-bold text-surface-900">{{ $employeeDetails['nama'] }}</span> &rarr; <span class="font-semibold text-primary-800">{{ $form->targetJabatan }}</span> ({{ $form->targetUnitKerja }})
                    @else
                        <span class="font-bold text-surface-900">Formulir Mutasi Individual</span>
                    @endif
                    <div aria-live="polite" aria-atomic="true">
                        @if ($this->resolvableByChain)
                            <p id="blocking-reason" class="text-xs text-danger-800 font-medium">Pejabat petahana perlu dipindahkan lebih dulu.</p>
                        @elseif ($this->blockingReason)
                            <p id="blocking-reason" class="text-xs text-danger-800 font-medium">{{ $this->blockingReason }}</p>
                        @else
                            <p class="text-xs text-surface-600">Seluruh perubahan disimpan sekaligus; bila gagal, tidak ada data yang berubah.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.mutasi.index') }}"
                    class="inline-flex items-center justify-center min-h-11 px-4 py-2.5 rounded-xl border border-surface-200 text-surface-700 text-xs sm:text-sm font-semibold hover:bg-surface-50 transition cursor-pointer">
                    Batal
                </a>

                @if ($this->resolvableByChain)
                    <a href="{{ route('admin.mutasi.chain') }}"
                        aria-describedby="blocking-reason"
                        class="inline-flex items-center justify-center gap-2 min-h-11 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer">
                        <i class="fas fa-link text-xs" aria-hidden="true"></i>
                        <span>Selesaikan melalui Mutasi Berantai</span>
                    </a>
                @else
                    <button type="button" wire:click="openSummaryModal"
                        @disabled($this->blockingReason)
                        @if ($this->blockingReason) aria-describedby="blocking-reason" @endif
                        wire:loading.attr="disabled"
                        wire:target="openSummaryModal, submitMutasi"
                        class="inline-flex items-center justify-center gap-2 min-h-11 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        <i wire:loading.remove wire:target="openSummaryModal" class="fas fa-magnifying-glass-check text-xs" aria-hidden="true"></i>
                        <i wire:loading wire:target="openSummaryModal" class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                        <span wire:loading.remove wire:target="openSummaryModal">Tinjau &amp; Eksekusi Mutasi</span>
                        <span wire:loading wire:target="openSummaryModal">Memvalidasi Data...</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
