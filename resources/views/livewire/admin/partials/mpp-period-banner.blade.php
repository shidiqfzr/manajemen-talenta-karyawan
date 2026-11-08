{{--
    Banner status periode bulanan MPP beserta tombol rekam, tutup, dan buka kembali. Tidak tampil untuk bulan berjalan atau
    mendatang ($periodState = 'live'). Panel konfirmasi tutup/buka kembali memakai state $periodAction pada komponen.

    @include('livewire.admin.partials.mpp-period-banner', [
        'periodState' => 'closed',        // closed | draft | unclosed | live
        'period' => $period,              // ?App\Models\MppPeriod
        'periodLabel' => 'Okt 2026',
        'periodStamps' => ['captured' => '05 Nov 2026 09:00', 'closed' => '05 Nov 2026 09:30'],
        'periodAction' => '',             // '' | 'close' | 'reopen'
        'periodError' => null,
    ])
--}}
@if($periodState !== 'live')
    <div role="status" class="px-5 py-2.5 border-b flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs
        {{ $periodState === 'closed' ? 'bg-primary-50 border-primary-200 text-primary-800' : 'bg-warning-50 border-warning-200 text-warning-800' }}">
        <div class="space-y-0.5">
            <p class="flex items-center gap-2 font-semibold">
                <i class="fas {{ $periodState === 'closed' ? 'fa-lock' : 'fa-triangle-exclamation' }}" aria-hidden="true"></i>
                <span>
                    @if($periodState === 'closed')
                        Periode {{ $periodLabel }} ditutup oleh {{ $period->closer?->name ?? 'sistem' }} · {{ $periodStamps['closed'] }} · Revisi {{ $period->revision }}
                    @elseif($periodState === 'draft')
                        Draf periode {{ $periodLabel }} direkam oleh {{ $period->capturer?->name ?? 'sistem' }} · {{ $periodStamps['captured'] }}. Belum ditutup.
                    @else
                        Periode {{ $periodLabel }} belum pernah ditutup: angka dihitung dari data karyawan saat ini.
                    @endif
                </span>
            </p>
            @if($periodState === 'closed' && $period->close_note)
                <p class="pl-6 text-[11px]">Catatan: {{ $period->close_note }}</p>
            @endif
        </div>

        @if($periodAction === '')
            <div class="flex items-center gap-2 self-start sm:self-auto shrink-0">
                @if($periodState === 'unclosed')
                    <button type="button" wire:click="capturePeriod" wire:loading.attr="disabled" wire:target="capturePeriod"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-warning-200 text-warning-800 hover:bg-warning-100 disabled:opacity-60 transition">
                        <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="capturePeriod" aria-hidden="true"></i>
                        <span>Rekam Draf</span>
                    </button>
                @elseif($periodState === 'draft')
                    <button type="button" wire:click="capturePeriod" wire:loading.attr="disabled" wire:target="capturePeriod"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-warning-200 text-warning-800 hover:bg-warning-100 disabled:opacity-60 transition">
                        <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="capturePeriod" aria-hidden="true"></i>
                        <span>Perbarui Draf</span>
                    </button>
                    <button type="button" wire:click="requestPeriodAction('close')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-700 text-white hover:bg-primary-800 transition">
                        Tutup Periode
                    </button>
                @else
                    <button type="button" wire:click="requestPeriodAction('reopen')"
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-primary-300 text-primary-800 hover:bg-primary-100 transition">
                        Buka Kembali
                    </button>
                @endif
            </div>
        @endif
    </div>

    @if($periodAction !== '')
        {{-- Konfirmasi: panel satu langkah dengan isian catatan (tutup, opsional) atau alasan (buka kembali, wajib). --}}
        <div role="alertdialog" aria-labelledby="period-action-title" class="px-5 py-3 border-b border-warning-200 bg-warning-50 text-xs text-warning-800 space-y-2.5">
            @if($periodAction === 'close')
                <p id="period-action-title" class="font-bold">Tutup periode {{ $periodLabel }}?</p>
                <p>Angka matriks dan daftar personil periode ini dibekukan sesuai draf. Perubahan data karyawan berikutnya tidak memengaruhinya.</p>
                <div>
                    <label for="period-note" class="block font-semibold mb-1">Catatan penutupan (opsional)</label>
                    <textarea id="period-note" wire:model="periodNote" rows="2" maxlength="500"
                        placeholder="Contoh: Disetujui rapat SDM"
                        class="w-full px-3 py-2 bg-white border border-surface-200 text-surface-800 rounded-lg text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500"></textarea>
                </div>
            @else
                <p id="period-action-title" class="font-bold">Buka kembali periode {{ $periodLabel }}?</p>
                <p>Periode kembali menjadi draf dengan nomor revisi bertambah dan harus ditutup ulang. Alasan tercatat pada riwayat perubahan.</p>
                <div>
                    <label for="period-reason" class="block font-semibold mb-1">Alasan membuka kembali <span class="text-danger-700">*</span></label>
                    <textarea id="period-reason" wire:model="periodReason" rows="2" required aria-required="true"
                        placeholder="Contoh: Koreksi mutasi susulan"
                        class="w-full px-3 py-2 bg-white border border-surface-200 text-surface-800 rounded-lg text-xs focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500"></textarea>
                    <p class="text-[11px] text-surface-500 mt-1">Minimal 10 karakter.</p>
                </div>
            @endif

            @if($periodError)
                <p role="alert" class="font-semibold text-danger-700">{{ $periodError }}</p>
            @endif

            <div class="flex items-center gap-2">
                <button type="button" wire:click="cancelPeriodAction"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-white border border-surface-200 text-surface-700 hover:bg-surface-50 transition">
                    Batal
                </button>
                <button type="button" wire:click="confirmPeriodAction" wire:loading.attr="disabled" wire:target="confirmPeriodAction"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-700 text-white hover:bg-primary-800 disabled:opacity-60 transition">
                    <i class="fas fa-circle-notch fa-spin" wire:loading wire:target="confirmPeriodAction" aria-hidden="true"></i>
                    <span wire:loading.remove wire:target="confirmPeriodAction">{{ $periodAction === 'close' ? 'Tutup Periode' : 'Buka Kembali' }}</span>
                    <span wire:loading wire:target="confirmPeriodAction">Memproses…</span>
                </button>
            </div>
        </div>
    @endif
@endif
@if($periodError && $periodAction === '')
    <div role="alert" class="px-5 py-2.5 bg-danger-50 border-b border-danger-200 text-xs font-semibold text-danger-700">
        {{ $periodError }}
    </div>
@endif
