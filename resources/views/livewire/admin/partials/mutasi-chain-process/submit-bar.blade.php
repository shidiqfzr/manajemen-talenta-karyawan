    <!-- ── 4. STICKY SUBMIT / REVIEW BAR ── -->
    <div class="sticky bottom-4 z-20 space-y-2">
        @if ($errors->has('chain_topology') || $errors->any())
            <div class="bg-danger-50/95 backdrop-blur-md border border-danger-200 rounded-2xl p-4 shadow-lg flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs animate-fade-in"
                role="alert" x-data="{ show: true }" x-show="show">
                <div class="flex items-start gap-2.5">
                    <div class="w-7 h-7 rounded-lg bg-danger-100 text-danger-700 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div class="space-y-0.5 text-danger-800">
                        <span class="font-bold block">Pemberitahuan Validasi Rantai Mutasi:</span>
                        @if ($errors->has('chain_topology'))
                            <p class="font-medium text-danger-800 leading-relaxed">{{ $errors->first('chain_topology') }}</p>
                        @endif
                        @if ($errors->has('nodes'))
                            <p class="font-medium text-danger-800 leading-relaxed">{{ $errors->first('nodes') }}</p>
                        @endif
                        @if ($errors->has('nomorSk') || $errors->has('tanggalSk') || $errors->has('tmtAwal'))
                            <p class="text-[11px] text-danger-700 font-medium leading-relaxed">
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
                    " class="px-3 py-1.5 rounded-lg bg-danger hover:bg-danger-700 text-white font-semibold text-xs transition cursor-pointer flex items-center gap-1.5 shadow-2xs">
                        <i class="fas fa-arrow-up text-[10px]" aria-hidden="true"></i>
                        <span>Tinjau Konfigurasi</span>
                    </button>
                    <button type="button" @click="show = false" class="text-danger hover:text-danger-700 p-1.5 cursor-pointer" title="Tutup pemberitahuan">
                        <i class="fas fa-times text-xs" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        @endif

        @php
            $mode = $this->reviewMode;
            $canWithdraw = $proposal?->isPending() && (int) $proposal->submitted_by === (int) auth()->id();
            $canDiscard = ! $locked && $proposal && $proposal->submitted_at === null;
            $primaryLabel = match ($mode) {
                'approve' => 'Tinjau & Setujui Usulan',
                'submit' => 'Tinjau & Ajukan Persetujuan',
                default => 'Tinjau & Eksekusi Rantai Mutasi',
            };
        @endphp
        <div class="bg-white/95 backdrop-blur-md rounded-2xl border border-surface-200 shadow-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3 text-xs text-surface-600">
                <div class="w-8 h-8 rounded-lg bg-surface-100 flex items-center justify-center font-bold text-surface-700">
                    <i class="fas {{ $locked ? 'fa-lock' : 'fa-layer-group' }} text-xs" aria-hidden="true"></i>
                </div>
                <div>
                    <span class="font-bold text-surface-900">{{ count($nodes) }} Karyawan</span> {{ $proposal ? 'dalam usulan SK mutasi kolektif.' : 'dalam antrean SK mutasi kolektif.' }}
                    {{-- Ringkasan kendala (DESIGN.md §7.2): sama dengan Mutasi Tunggal - jumlah lebih dulu bila lebih dari satu, lalu rincian --}}
                    <div aria-live="polite" aria-atomic="true">
                        @php $issues = $locked && $mode !== 'approve' ? [] : $this->outstandingIssues; @endphp
                        @if (count($issues) > 1)
                            <p class="text-xs text-warning-800 font-semibold">{{ count($issues) }} hal perlu diselesaikan:</p>
                            <ul class="text-xs text-warning-800 font-medium list-disc pl-4">
                                @foreach ($issues as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @elseif (count($issues) === 1)
                            <p class="text-xs text-warning-800 font-medium">{{ $issues[0] }}</p>
                        @elseif ($mode === 'submit')
                            <p class="text-[11px] text-surface-500">SK diterbitkan setelah disetujui admin lain. Simpan draf untuk melanjutkan nanti.</p>
                        @elseif ($mode === 'approve')
                            <p class="text-[11px] text-surface-500">Persetujuan langsung menerbitkan SK; seluruh perpindahan disimpan sekaligus.</p>
                        @elseif ($locked)
                            <p class="text-[11px] text-surface-500">Usulan ini terkunci dan hanya dapat dilihat.</p>
                        @else
                            <p class="text-[11px] text-surface-500">Seluruh perpindahan disimpan sekaligus; bila satu gagal, tidak ada yang berubah.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2.5">
                <a href="{{ route('admin.mutasi.index', $proposal ? ['tab' => 'usulan'] : []) }}"
                    class="px-4 py-2.5 rounded-xl border border-surface-200 text-surface-700 text-xs sm:text-sm font-semibold hover:bg-surface-50 transition cursor-pointer">
                    {{ $proposal ? 'Kembali' : 'Batal' }}
                </a>

                @if ($canDiscard)
                    {{-- Konfirmasi dua langkah: draf yang belum pernah diajukan dihapus permanen --}}
                    <div x-data="{ confirming: false }" class="inline-flex items-center gap-1.5">
                        <button type="button" x-show="!confirming" @click="confirming = true"
                            class="px-3 py-2.5 rounded-xl text-danger hover:bg-danger-50 text-xs sm:text-sm font-semibold transition cursor-pointer">
                            Hapus Draf
                        </button>
                        <span x-show="confirming" x-cloak class="inline-flex items-center gap-1.5" role="group" aria-label="Konfirmasi hapus draf">
                            <span class="text-xs text-danger-800 font-medium">Hapus permanen?</span>
                            <button type="button" wire:click="discardDraft" wire:loading.attr="disabled" wire:target="discardDraft"
                                class="px-3 py-2 rounded-lg bg-danger hover:bg-danger-700 text-white text-xs font-bold transition cursor-pointer disabled:opacity-60">Ya, Hapus</button>
                            <button type="button" @click="confirming = false"
                                class="px-2.5 py-2 rounded-lg text-surface-600 hover:bg-surface-100 text-xs font-semibold transition cursor-pointer">Tidak</button>
                        </span>
                    </div>
                @endif

                @if ($canWithdraw)
                    <button type="button" wire:click="withdrawProposal" wire:loading.attr="disabled" wire:target="withdrawProposal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-surface-300 text-surface-800 text-xs sm:text-sm font-semibold hover:bg-surface-50 transition cursor-pointer disabled:opacity-60">
                        <i class="fas fa-rotate-left text-xs" aria-hidden="true"></i> Tarik Usulan
                    </button>
                @endif

                @unless ($locked)
                    <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft, fileSk"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-primary-300 text-primary-800 bg-white hover:bg-primary-50 text-xs sm:text-sm font-semibold transition cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        <i wire:loading.remove wire:target="saveDraft" class="fas fa-floppy-disk text-xs" aria-hidden="true"></i>
                        <i wire:loading wire:target="saveDraft" class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                        <span>Simpan Draf</span>
                    </button>
                @endunless

                @if ($mode === 'approve')
                    <button type="button" wire:click="openRejectModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-warning-300 text-warning-800 bg-white hover:bg-warning-50 text-xs sm:text-sm font-semibold transition cursor-pointer">
                        <i class="fas fa-reply text-xs" aria-hidden="true"></i> Kembalikan
                    </button>
                @endif

                @if ($mode !== 'none')
                    <button type="button" wire:click="openReviewModal"
                        wire:loading.attr="disabled"
                        wire:target="openReviewModal, submitChain, submitForApproval, approveProposal"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                        <i wire:loading.remove wire:target="openReviewModal" class="fas fa-magnifying-glass-check text-xs" aria-hidden="true"></i>
                        <i wire:loading wire:target="openReviewModal" class="fas fa-spinner fa-spin text-xs" aria-hidden="true"></i>
                        <span wire:loading.remove wire:target="openReviewModal">{{ $primaryLabel }}</span>
                        <span wire:loading wire:target="openReviewModal">Memeriksa Rantai Mutasi...</span>
                    </button>
                @endif
            </div>
        </div>
    </div>
