    <!-- ── 4. ACTION SUBMIT / REVIEW BAR ── -->
    <div class="pt-2 space-y-2 sticky bottom-4 z-30">
        @if ($errors->any())
            <div class="bg-rose-50 border border-rose-200 rounded-2xl p-4 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs animate-fade-in"
                x-data="{ show: true }" x-show="show">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="space-y-0.5 text-rose-900">
                        <span class="font-bold block">Pemberitahuan Kelengkapan Data:</span>
                        <p class="font-medium text-rose-800 leading-relaxed">
                            {{ $errors->first() }}
                        </p>
                    </div>
                </div>
                <button type="button" @click="show = false" class="text-rose-400 hover:text-rose-700 p-1.5 cursor-pointer self-end sm:self-center" title="Tutup pemberitahuan">
                    <i class="fas fa-times text-xs"></i>
                </button>
            </div>
        @endif

        <div data-blocked="{{ $this->blockingReason ? 'true' : 'false' }}"
            class="bg-white rounded-2xl border border-surface-200 shadow-xs p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 text-xs text-surface-600">
                <div class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center font-bold text-surface-700 shrink-0">
                    <i class="fas fa-user-check text-xs"></i>
                </div>
                <div>
                    @if ($employeeDetails && $form->targetJabatan)
                        <span class="font-bold text-surface-900">{{ $employeeDetails['nama'] }}</span> &rarr; <span class="font-semibold text-primary-800">{{ $form->targetJabatan }}</span> ({{ $form->targetUnitKerja }})
                    @else
                        <span class="font-bold text-surface-900">Formulir Mutasi Individual</span>
                    @endif
                    @if ($this->resolvableByChain)
                        <p class="text-xs text-rose-700 font-medium">Pejabat petahana perlu dipindahkan lebih dulu.</p>
                    @elseif ($this->blockingReason)
                        <p class="text-xs text-rose-700 font-medium">{{ $this->blockingReason }}</p>
                    @else
                        <p class="text-[11px] text-surface-500">Seluruh perubahan disimpan sekaligus; bila gagal, tidak ada data yang berubah.</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.mutasi.index') }}"
                    class="px-4 py-2.5 rounded-xl border border-surface-200 text-surface-700 text-xs sm:text-sm font-semibold hover:bg-surface-50 transition cursor-pointer">
                    Batal
                </a>

                @if ($this->resolvableByChain)
                    <a href="{{ route('admin.mutasi.chain') }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer">
                        <i class="fas fa-link text-xs"></i>
                        <span>Selesaikan melalui Mutasi Berantai</span>
                    </a>
                @else
                    <button type="button" wire:click="openSummaryModal"
                        @disabled($this->blockingReason)
                        wire:loading.attr="disabled"
                        wire:target="openSummaryModal, submitMutasi"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        <i wire:loading.remove wire:target="openSummaryModal" class="fas fa-magnifying-glass-check text-xs"></i>
                        <i wire:loading wire:target="openSummaryModal" class="fas fa-spinner fa-spin text-xs"></i>
                        <span wire:loading.remove wire:target="openSummaryModal">Tinjau &amp; Eksekusi Mutasi</span>
                        <span wire:loading wire:target="openSummaryModal">Memvalidasi Data...</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
