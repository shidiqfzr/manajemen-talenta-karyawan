    <!-- ── 2. PANEL UJI KELAYAKAN REGULASI & DISPENSASI (PRE-FLIGHT CLEARANCE PANEL) ── -->
    @if (!empty($form->targetJabatan) && !empty($form->targetUnitKerja) && $targetAnalysis)
        @php
            $isTenureNonCompliant = $tenureAnalysis && !$tenureAnalysis['is_compliant'];
            $isPromoNonCompliant = $promotionAnalysis && $promotionAnalysis['is_applicable'] && !$promotionAnalysis['is_eligible'];
            $isSwapBlocked = ($targetAnalysis['type'] === 'occupied') && (!($isSwapAllowed ?? true));
            $isOccupancyIssue = $isSwapBlocked || in_array($targetAnalysis['type'], ['reserved', 'same']);
            $hasAnyWarning = $isTenureNonCompliant || $isPromoNonCompliant || $isOccupancyIssue;
        @endphp

        <div class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5 sm:p-6 space-y-4 animate-fade-in"
             wire:key="preflight-compliance-panel-{{ $form->employeeNik }}-{{ $form->targetJabatan }}-{{ $form->targetUnitKerja }}">

            <!-- Header Panel Kepatuhan -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-surface-100">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl {{ $this->blockingReason ? 'bg-rose-100 text-rose-800' : ($hasAnyWarning ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800') }} flex items-center justify-center font-bold text-sm shadow-2xs transition">
                        <i class="fas {{ $this->blockingReason ? 'fa-ban' : ($hasAnyWarning ? 'fa-shield-halved' : 'fa-clipboard-check') }}"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-bold text-surface-900">Uji Kelayakan Regulasi &amp; Kepatuhan Penugasan</h3>
                            @if ($this->blockingReason)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-300 shadow-2xs">
                                    <i class="fas fa-ban text-rose-600 text-[9px]"></i>
                                    {{-- "Tunggal" only fits an occupied formasi that a chain mutation can free up --}}
                                    <span>{{ $isSwapBlocked && ! ($employeeDetails['is_retired'] ?? false) ? 'Tidak Dapat Diproses Tunggal' : 'Tidak Dapat Diproses' }}</span>
                                </span>
                            @elseif ($hasAnyWarning)
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-300 shadow-2xs">
                                    <i class="fas fa-triangle-exclamation text-amber-600 text-[9px]"></i>
                                    <span>Perlu Dispensasi</span>
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300 shadow-2xs">
                                    <i class="fas fa-circle-check text-emerald-600 text-[9px]"></i>
                                    <span>Memenuhi Standar Regulasi</span>
                                </span>
                            @endif
                        </div>
                        <p class="text-xs text-surface-500 mt-0.5">
                            Pemeriksaan kesesuaian masa dinas unit, kualifikasi jenjang eselon, dan ketersediaan formasi jabatan tujuan
                        </p>
                    </div>
                </div>
            </div>

            <!-- 1. Top Banner: Status Okupansi & Aksesibilitas Formasi -->
            @if ($targetAnalysis['type'] === 'vacant')
                <div class="p-3.5 bg-sky-50 border border-sky-200 rounded-xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-sky-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="text-xs text-sky-950 leading-relaxed flex-1">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-sky-900">Pengisian Formasi Lowong (Clean Transfer)</span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-100 text-sky-800 border border-sky-300">Vacant</span>
                        </div>
                        <p class="mt-1 text-sky-800">
                            Formasi ini saat ini sedang kosong definitif. Penempatan mutasi ini akan menutup formasi lowong secara otomatis.
                        </p>
                    </div>
                </div>
            @elseif ($targetAnalysis['type'] === 'occupied')
                @if ($isSwapAllowed)
                    <div class="p-3.5 bg-earth-50/80 border border-earth-200 rounded-xl flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-earth-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                            <i class="fas fa-arrows-rotate"></i>
                        </div>
                        <div class="text-xs text-surface-900 leading-relaxed flex-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-earth-800">Target Terisi: Tukar Jabatan 2-Arah (Lateral Swap)</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-earth-100 text-earth-800 border border-earth-300">Occupied</span>
                            </div>
                            <p class="mt-1 text-surface-600 text-xs">
                                Diduduki oleh <strong class="font-bold text-surface-900">{{ $targetAnalysis['occupant']['nama'] }}</strong> 
                                (NIK: {{ $targetAnalysis['occupant']['nik'] }}, Eselon: {{ $targetAnalysis['occupant']['rm_level'] ?? '—' }}).
                                Kedua posisi memiliki eselon setara (Net Zero Vacancy). Simulasi visual alur pertukaran dan konfirmasi verifikasi ditampilkan pada panel di bawah.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="p-4 sm:p-5 bg-rose-50/80 border border-rose-200 rounded-2xl space-y-3.5 shadow-2xs">
                        <!-- Header Warning -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-rose-600 text-white flex items-center justify-center text-sm shrink-0 mt-0.5 shadow-2xs">
                                    <i class="fas fa-user-lock"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-rose-950 text-sm">Formasi Masih Terisi Pejabat Definitif</span>
                                    </div>
                                    <p class="mt-1 text-xs text-rose-800 leading-relaxed">
                                        {{ $swapIncompatibilityMessage ?? 'Formasi target saat ini masih terisi oleh pejabat lain dan tidak dapat diproses melalui Mutasi Tunggal.' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card Profil Pejabat Petahana (Incumbent Occupant Profile) -->
                        @if (!empty($targetAnalysis['occupant']))
                            @php $occ = $targetAnalysis['occupant']; @endphp
                            <div class="p-3.5 sm:p-4 rounded-xl bg-white border border-rose-200 shadow-2xs space-y-3">
                                <div class="flex items-center justify-between pb-2.5 border-b border-surface-100">
                                    <span class="text-[11px] font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1.5">
                                        <i class="fas fa-id-card text-surface-400"></i>
                                        Profil Pejabat Petahana Saat Ini
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-900 border border-rose-300">
                                        {{ $occ['rm_level'] ?? 'RM' }} &bull; Definitif
                                    </span>
                                </div>

                                <div class="flex items-center gap-3.5">
                                    <div class="w-11 h-11 rounded-xl bg-surface-100 border border-surface-200 text-surface-700 font-bold text-sm flex items-center justify-center shrink-0 shadow-2xs">
                                        {{ strtoupper(substr($occ['nama'] ?? 'P', 0, 1)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="font-bold text-surface-900 text-sm truncate">
                                            {{ $occ['nama'] }}
                                        </h4>
                                        <p class="text-xs text-surface-500 font-mono mt-0.5">
                                            NIK: {{ $occ['nik'] }} &bull; Gol: {{ $occ['golongan'] ?? '—' }}
                                        </p>
                                    </div>
                                    @if (!empty($occ['tenure_text']))
                                        <div class="text-right shrink-0">
                                            <span class="text-[10px] text-surface-400 block">Masa Dinas Unit:</span>
                                            <span class="text-xs font-semibold text-surface-700">{{ $occ['tenure_text'] }}</span>
                                        </div>
                                    @endif
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2 border-t border-surface-100 text-xs">
                                    <div class="flex items-center justify-between text-surface-600 bg-surface-50 px-3 py-2 rounded-lg border border-surface-200/80 gap-2">
                                        <span class="text-[11px] text-surface-400 shrink-0">Jabatan:</span>
                                        <span class="font-semibold text-surface-800 text-right truncate" title="{{ $occ['jabatan'] }}">{{ $occ['jabatan'] }}</span>
                                    </div>
                                    <div class="flex items-center justify-between text-surface-600 bg-surface-50 px-3 py-2 rounded-lg border border-surface-200/80 gap-2">
                                        <span class="text-[11px] text-surface-400 shrink-0">Unit Kerja:</span>
                                        <span class="font-semibold text-surface-800 text-right truncate" title="{{ $occ['unit_kerja'] }}">{{ $occ['unit_kerja'] }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Solusi Prosedural Enterprise: Call-to-Action to Mutasi Berantai -->
                        <div class="p-3 bg-white/90 rounded-xl border border-rose-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
                            <div class="space-y-0.5">
                                <span class="font-bold text-surface-800 block">Rekomendasi Prosedur Resmi:</span>
                                <p class="text-[11px] text-surface-600 leading-relaxed">
                                    Gunakan <strong>Mutasi Berantai</strong> untuk memindahkan pejabat petahana secara berantai (domino) ke formasi lain dalam satu SK kolektif.
                                </p>
                            </div>
                            <a href="{{ route('admin.mutasi.chain') }}"
                               class="inline-flex items-center justify-center gap-2 px-3.5 py-2 rounded-xl bg-primary-700 hover:bg-primary-800 text-white font-bold text-xs shadow-xs transition shrink-0 cursor-pointer">
                                <i class="fas fa-link text-[11px]"></i>
                                <span>Selesaikan melalui Mutasi Berantai</span>
                            </a>
                        </div>
                    </div>
                @endif
            @elseif ($targetAnalysis['type'] === 'pool')
                <div class="p-3.5 bg-purple-50 border border-purple-200 rounded-xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-purple-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="text-xs text-purple-950 leading-relaxed flex-1">
                        <span class="font-bold text-purple-900 block">Formasi Penampungan Multi-Occupancy (Pool)</span>
                        <p class="mt-0.5 text-purple-800">
                            Formasi ini tidak dibatasi kuota tunggal. Penempatan karyawan ke posisi ini tidak menggeser pejabat lain.
                        </p>
                    </div>
                </div>
            @elseif ($targetAnalysis['type'] === 'reserved')
                <div class="p-3.5 bg-amber-50 border border-amber-300 rounded-xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="text-xs text-amber-950 leading-relaxed flex-1">
                        <span class="font-bold text-amber-900 block">Formasi Telah Dipesan (Reserved)</span>
                        <p class="mt-0.5 text-amber-800">
                            Posisi ini telah dipesan oleh mutasi terjadwal atas nama <strong>{{ $targetAnalysis['reserved_occupant']['nama'] ?? 'pegawai lain' }}</strong> (TMT Efektif: {{ $targetAnalysis['reserved_occupant']['tmt_awal'] ?? '-' }}). Pilih formasi lain yang tersedia.
                        </p>
                    </div>
                </div>
            @elseif ($targetAnalysis['type'] === 'same')
                <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl flex items-center gap-2.5 text-xs text-rose-800 font-bold">
                    <i class="fas fa-ban text-rose-600 text-sm"></i>
                    <span>Karyawan yang dipilih sudah menjabat pada formasi dan unit kerja yang sama.</span>
                </div>
            @endif

            <!-- 2. Adaptive Compliance Guard (Clean Enterprise HRIS) -->
            <x-admin.mutasi.compliance-guard 
                :tenure-analysis="$tenureAnalysis"
                :promotion-analysis="$promotionAnalysis"
                :current-unit="$employeeDetails['unit_kerja'] ?? 'unit saat ini'"
                :current-rm="$employeeDetails['rm_level'] ?? 'RM'"
                tenure-model="form.dispensasiTenure"
                tenure-reason-model="form.alasanDispensasiTenure"
                promotion-model="form.dispensasiPromosi"
                promotion-reason-model="form.alasanDispensasiPromosi"
                :is-tenure-checked="(bool) $form->dispensasiTenure"
                :is-promotion-checked="(bool) $form->dispensasiPromosi"
                error-tenure-key="dispensasiTenure"
                error-tenure-reason-key="alasanDispensasiTenure"
                error-promo-key="dispensasiPromosi"
                error-promo-reason-key="alasanDispensasiPromosi"
            />
        </div>
    @endif
