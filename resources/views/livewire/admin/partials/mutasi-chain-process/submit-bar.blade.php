    <!-- ── 4. STICKY SUBMIT / REVIEW BAR ── -->
    <div class="sticky bottom-4 z-20 space-y-2">
        @if ($errors->has('chain_topology') || $errors->any())
            <div class="bg-rose-50/95 backdrop-blur-md border border-rose-200 rounded-2xl p-4 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs animate-fade-in"
                x-data="{ show: true }" x-show="show">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="space-y-0.5 text-rose-900">
                        <span class="font-bold block">Pemberitahuan Validasi Rantai Mutasi:</span>
                        @if ($errors->has('chain_topology'))
                            <p class="font-medium text-rose-800 leading-relaxed">{{ $errors->first('chain_topology') }}</p>
                        @endif
                        @if ($errors->has('nodes'))
                            <p class="font-medium text-rose-800 leading-relaxed">{{ $errors->first('nodes') }}</p>
                        @endif
                        @if ($errors->has('nomorSk') || $errors->has('tanggalSk') || $errors->has('tmtAwal'))
                            <p class="text-[11px] text-rose-700 font-medium leading-relaxed">
                                Periksa kelengkapan Nomor SK, Tanggal Penetapan, atau TMT Efektif pada bagian Data SK Kolektif di atas.
                            </p>
                        @endif
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-center shrink-0">
                    <button type="button" @click="
                        const target = document.getElementById('topologyMonitoringCard');
                        if (target) {
                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    " class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-2xs">
                        <i class="fas fa-arrow-up text-[10px]"></i>
                        <span>Tinjau Konfigurasi</span>
                    </button>
                    <button type="button" @click="show = false" class="text-rose-400 hover:text-rose-700 p-1.5 cursor-pointer" title="Tutup pemberitahuan">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>
            </div>
        @endif

        <div class="bg-white/95 backdrop-blur-md rounded-2xl border border-surface-200 shadow-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 text-xs text-surface-600">
                <div class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center font-bold text-surface-700">
                    <i class="fas fa-layer-group text-xs"></i>
                </div>
                <div>
                    <span class="font-bold text-surface-900">{{ count($nodes) }} Karyawan</span> dalam antrean SK mutasi kolektif.
                    <p class="text-[11px] text-surface-500">Seluruh perpindahan disimpan sekaligus; bila satu gagal, tidak ada yang berubah.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('admin.mutasi.index') }}"
                    class="px-4 py-2.5 rounded-xl border border-surface-200 text-surface-700 text-xs sm:text-sm font-semibold hover:bg-surface-50 transition cursor-pointer">
                    Batal
                </a>

                <button type="button" wire:click="openReviewModal"
                    wire:loading.attr="disabled"
                    wire:target="openReviewModal, submitChain"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    <i wire:loading.remove wire:target="openReviewModal" class="fas fa-magnifying-glass-check text-xs"></i>
                    <i wire:loading wire:target="openReviewModal" class="fas fa-spinner fa-spin text-xs"></i>
                    <span wire:loading.remove wire:target="openReviewModal">Tinjau &amp; Eksekusi Rantai Mutasi</span>
                    <span wire:loading wire:target="openReviewModal">Memvalidasi Topologi...</span>
                </button>
            </div>
        </div>
    </div>
