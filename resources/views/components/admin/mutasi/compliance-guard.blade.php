{{--
    Hasil cek aturan masa dinas unit & kualifikasi promosi pada alur mutasi (DESIGN.md §7.2).
    Semua lolos -> strip ringkas primary; ada pelanggaran -> panel warning dengan centang dispensasi + alasan wajib.
    locked=true mengunci dispensasi selama masih ada kendala penempatan yang memblokir.

    <x-admin.mutasi.compliance-guard :tenure-analysis="$tenure" :promotion-analysis="$promo" :locked="$hasBlocker" />
--}}
@props([
    'tenureAnalysis' => null,
    'promotionAnalysis' => null,
    'currentUnit' => 'unit saat ini',
    'currentRm' => null,
    'tenureModel' => 'form.dispensasiTenure',
    'tenureReasonModel' => 'form.alasanDispensasiTenure',
    'promotionModel' => 'form.dispensasiPromosi',
    'promotionReasonModel' => 'form.alasanDispensasiPromosi',
    'isTenureChecked' => false,
    'isPromotionChecked' => false,
    'errorTenureKey' => 'dispensasiTenure',
    'errorTenureReasonKey' => 'alasanDispensasiTenure',
    'errorPromoKey' => 'dispensasiPromosi',
    'errorPromoReasonKey' => 'alasanDispensasiPromosi',
    'locked' => false,
])

@php
    $isTenureNonCompliant = $tenureAnalysis && !($tenureAnalysis['is_compliant'] ?? true) && ($tenureAnalysis['is_applicable'] ?? true);
    $isPromoNonCompliant = $promotionAnalysis && !($promotionAnalysis['is_eligible'] ?? true) && ($promotionAnalysis['is_applicable'] ?? false);
    $isPromotionApplicable = $promotionAnalysis && ($promotionAnalysis['is_applicable'] ?? false);
    $isAllCompliant = !$isTenureNonCompliant && !$isPromoNonCompliant;
@endphp

@if ($isAllCompliant)
    <!-- ── 1. CLEAN COMPLIANT STRIP (Ringkas, Hemat Ruang, Enterprise Grade) ── -->
    <div class="p-3 bg-primary-50/70 border border-primary-200/90 rounded-xl flex items-center justify-between gap-3 text-xs text-primary-950 animate-fade-in shadow-2xs">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-6 h-6 rounded-md bg-primary-600 text-white flex items-center justify-center text-xs shrink-0 shadow-2xs">
                <i class="fas fa-clipboard-check" aria-hidden="true"></i>
            </div>
            <div class="truncate">
                <span class="font-bold text-primary-950">Kepatuhan Regulasi Terpenuhi:</span>
                <span class="text-primary-800 ml-1 text-xs">
                    @if ($tenureAnalysis && ($tenureAnalysis['is_applicable'] ?? false))
                        Masa dinas unit ({{ \App\Services\Mutasi\TalentProfileBuilder::formatMonths($tenureAnalysis['months'] ?? 0) }}) dan jenjang eselon memenuhi regulasi.
                    @else
                        Kualifikasi eselon dan rotasi internal unit kerja telah memenuhi standar regulasi kepegawaian.
                    @endif
                </span>
            </div>
        </div>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-primary-100 text-primary-800 border border-primary-300 shrink-0">
            @if ($isPromotionApplicable)
                Promosi Memenuhi Syarat
            @else
                Lateral • {{ $currentRm ?? 'RM' }}
            @endif
        </span>
    </div>
@else
    <!-- ── 2. ADAPTIVE COMPLIANCE GUARD (Stacked / Full-Width agar Input & Teks Lega) ── -->
    <div class="space-y-3.5 animate-fade-in">

        <!-- ── KARTU MASA DINAS UNIT KERJA (Tampil jika Melanggar Masa Dinas) ── -->
        @if ($isTenureNonCompliant)
            <div class="rounded-xl border border-warning-200 bg-warning-50/70 p-4 space-y-3 transition shadow-2xs">
                <div class="flex items-center justify-between pb-2 border-b border-warning-200/80 gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="w-6 h-6 rounded-md bg-warning text-white flex items-center justify-center text-xs shrink-0 shadow-2xs">
                            <i class="fas fa-history" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-xs font-bold text-warning-800 uppercase tracking-wider">
                            Masa Dinas Unit Kerja
                        </h4>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-warning-100 text-warning-800 border border-warning-200 shrink-0">
                        Perlu Dispensasi
                    </span>
                </div>

                <div class="text-xs text-warning-800 leading-relaxed space-y-1">
                    <p class="font-semibold text-warning-800">Peringatan Standar Regulasi Masa Dinas (2–3 Tahun):</p>
                    <p class="text-warning-700 text-xs">
                        Pegawai baru bertugas <strong class="font-bold text-warning-800">{{ \App\Services\Mutasi\TalentProfileBuilder::formatMonths($tenureAnalysis['months'] ?? 0) }}</strong> di {{ $currentUnit }}. Standar regulasi rotasi unit minimal 2–3 tahun demi kestabilan operasional perkebunan.
                    </p>
                </div>

                <div class="pt-3 border-t border-warning-200/90 space-y-2">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="{{ $tenureModel }}" @disabled($locked) @if ($locked) aria-describedby="dispensasi-tenure-locked-note" @endif class="mt-0.5 w-4 h-4 rounded text-warning-700 border-warning focus:ring-warning cursor-pointer shrink-0">
                        <span class="text-xs font-semibold text-warning-800">
                            Rotasi unit sebelum 2 tahun ini telah disetujui melalui Dispensasi Khusus Direksi. <span class="text-danger-700 font-bold" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                        </span>
                    </label>
                    @if ($locked)
                        <p id="dispensasi-tenure-locked-note" class="text-xs text-surface-700">Dapat diisi setelah kendala penempatan di atas diselesaikan.</p>
                    @endif
                    @if ($errors->has($errorTenureKey))
                        <p class="text-danger-700 text-xs font-semibold">{{ $errors->first($errorTenureKey) }}</p>
                    @endif
                    @if (! $locked && $isTenureChecked)
                        <input type="text" wire:model.live.debounce.300ms="{{ $tenureReasonModel }}" placeholder="Alasan pertimbangan / Nomor Nota Persetujuan Direksi..."
                            class="w-full px-3.5 py-2 bg-white border border-warning-200 rounded-lg text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-1 focus:ring-warning transition shadow-2xs">
                        @if ($errors->has($errorTenureReasonKey))
                            <p class="text-danger-700 text-xs mt-1">{{ $errors->first($errorTenureReasonKey) }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif

        <!-- ── KARTU SYARAT PROMOSI (Tampil jika Melanggar Syarat Promosi) ── -->
        @if ($isPromoNonCompliant)
            <div class="rounded-xl border border-warning-200/90 bg-warning-50/50 p-4 sm:p-5 space-y-3.5 transition shadow-2xs">
                <div class="flex items-center justify-between pb-2 border-b border-warning-200/80 gap-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="w-6 h-6 rounded-md bg-warning-700 text-white flex items-center justify-center text-xs shrink-0 shadow-2xs">
                            <i class="fas fa-chart-line" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-xs font-bold text-warning-800 uppercase tracking-wider">
                            Kualifikasi Promosi ({{ $promotionAnalysis['current_rm'] ?? 'RM' }} &rarr; {{ $promotionAnalysis['target_rm'] ?? 'RM' }})
                        </h4>
                        @if ($tenureAnalysis && ($tenureAnalysis['is_applicable'] ?? false) && ($tenureAnalysis['is_compliant'] ?? false))
                            <span class="px-2 py-0.5 rounded text-xs font-bold bg-primary-100 text-primary-800 border border-primary-200">
                                Masa Dinas Unit Memenuhi Syarat
                            </span>
                        @endif
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-warning-100 text-warning-800 border border-warning-200 shrink-0">
                        Perlu Dispensasi
                    </span>
                </div>

                <div class="space-y-2 text-xs">
                    @foreach ($promotionAnalysis['criteria'] ?? [] as $crit)
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-white/95 border border-warning-100/90 shadow-2xs text-xs gap-3">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <i class="fas {{ !empty($crit['met']) ? 'fa-circle-check text-primary-600' : 'fa-circle-xmark text-danger' }} text-sm shrink-0" aria-hidden="true"></i>
                                <span class="font-medium text-surface-800 truncate">{{ $crit['label'] }}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-xs font-bold shrink-0 ml-2 {{ !empty($crit['met']) ? 'bg-primary-50 text-primary-800 border border-primary-200' : 'bg-danger-50 text-danger-800 border border-danger-200' }}">
                                {{ $crit['value'] }}
                            </span>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-warning-200/80 space-y-2">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="checkbox" wire:model.live="{{ $promotionModel }}" @disabled($locked) @if ($locked) aria-describedby="dispensasi-promo-locked-note" @endif class="mt-0.5 w-4 h-4 rounded text-warning-700 border-warning-200 focus:ring-warning cursor-pointer shrink-0">
                        <span class="text-xs font-semibold text-warning-800 leading-relaxed">
                            Pengajuan promosi ini telah memperoleh persetujuan Dispensasi Promosi Dipercepat / Keputusan Khusus Direksi. <span class="text-danger-700 font-bold" aria-hidden="true">*</span><span class="sr-only">(wajib)</span>
                        </span>
                    </label>
                    @if ($locked)
                        <p id="dispensasi-promo-locked-note" class="text-xs text-surface-700">Dapat diisi setelah kendala penempatan di atas diselesaikan.</p>
                    @endif
                    @if ($errors->has($errorPromoKey))
                        <p class="text-danger-700 text-xs font-semibold">{{ $errors->first($errorPromoKey) }}</p>
                    @endif
                    @if (! $locked && $isPromotionChecked)
                        <input type="text" wire:model.live.debounce.300ms="{{ $promotionReasonModel }}" placeholder="Alasan pertimbangan promosi / nomor rekomendasi asesmen..."
                            class="w-full px-3.5 py-2 bg-white border border-warning-200 rounded-lg text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:border-warning-700 focus:ring-1 focus:ring-warning-200 transition shadow-2xs">
                        @if ($errors->has($errorPromoReasonKey))
                            <p class="text-danger-700 text-xs mt-1">{{ $errors->first($errorPromoReasonKey) }}</p>
                        @endif
                    @endif
                </div>
            </div>
        @endif

    </div>
@endif
