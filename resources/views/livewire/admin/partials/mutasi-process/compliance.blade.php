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
                    <div class="w-9 h-9 rounded-xl {{ $this->blockingReason ? 'bg-danger-100 text-danger-800' : ($hasAnyWarning ? 'bg-warning-100 text-warning-800' : 'bg-primary-100 text-primary-800') }} flex items-center justify-center font-bold text-sm shadow-2xs transition">
                        <i aria-hidden="true" class="fas {{ $this->blockingReason ? 'fa-ban' : ($hasAnyWarning ? 'fa-shield-halved' : 'fa-clipboard-check') }}"></i>
                    </div>
                    <div>
                        <h3 class="text-sm sm:text-base font-bold text-surface-900">Uji Kelayakan Regulasi &amp; Kepatuhan Penugasan</h3>
                        {{-- Status is plain text: the header icon box already carries the state color --}}
                        @if ($this->blockingReason)
                            {{-- "Tunggal" only fits an occupied formasi that a chain mutation can free up --}}
                            <p class="text-xs font-semibold text-danger-800 mt-0.5">{{ $isSwapBlocked && ! ($employeeDetails['is_retired'] ?? false) ? 'Tidak Dapat Diproses Tunggal' : 'Tidak Dapat Diproses' }}</p>
                        @elseif ($hasAnyWarning)
                            <p class="text-xs font-semibold text-warning-800 mt-0.5">Perlu Dispensasi</p>
                        @else
                            <p class="text-xs font-semibold text-primary-800 mt-0.5">Memenuhi Standar Regulasi</p>
                        @endif
                        <p class="text-xs text-surface-500 mt-0.5">
                            Pemeriksaan kesesuaian masa dinas unit, kualifikasi jenjang eselon, dan ketersediaan formasi jabatan tujuan
                        </p>
                        @if (count($this->outstandingIssues) >= 2)
                            <div class="mt-2 text-xs text-surface-700">
                                <p class="font-bold">{{ count($this->outstandingIssues) }} hal perlu diselesaikan</p>
                                <ol class="list-decimal pl-4 mt-0.5 space-y-0.5">
                                    @foreach ($this->outstandingIssues as $issue)
                                        <li>{{ $issue }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 1. Top Banner: Status Okupansi & Aksesibilitas Formasi -->
            @if ($targetAnalysis['type'] === 'vacant')
                <div class="p-3.5 bg-info-50 border border-info-200 rounded-xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-info text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                        <i class="fas fa-door-open" aria-hidden="true"></i>
                    </div>
                    <div class="text-xs text-info-800 leading-relaxed flex-1">
                        <span class="font-bold text-info-800 block">Pengisian Formasi Lowong (Clean Transfer)</span>
                        <p class="mt-1 text-info-800">
                            Formasi ini saat ini sedang kosong definitif. Penempatan mutasi ini akan menutup formasi lowong secara otomatis.
                        </p>
                    </div>
                </div>
            @elseif ($targetAnalysis['type'] === 'occupied')
                @if ($isSwapAllowed)
                    <div class="p-3.5 bg-earth-50/80 border border-earth-200 rounded-xl flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-earth-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                            <i class="fas fa-arrows-rotate" aria-hidden="true"></i>
                        </div>
                        <div class="text-xs text-surface-900 leading-relaxed flex-1">
                            <span class="font-bold text-earth-800 block">Target Terisi: Tukar Jabatan 2-Arah (Lateral Swap)</span>
                            <p class="mt-1 text-surface-600 text-xs">
                                Diduduki oleh <strong class="font-bold text-surface-900">{{ $targetAnalysis['occupant']['nama'] }}</strong> 
                                (NIK: {{ $targetAnalysis['occupant']['nik'] }}, Eselon: {{ $targetAnalysis['occupant']['rm_level'] ?? '—' }}).
                                Kedua posisi memiliki eselon setara (Net Zero Vacancy). Simulasi visual alur pertukaran dan konfirmasi verifikasi ditampilkan pada panel di bawah.
                            </p>
                        </div>
                    </div>
                @else
                    <div class="p-4 sm:p-5 bg-danger-50/80 border border-danger-200 rounded-2xl space-y-3.5 shadow-2xs" role="alert">
                        <!-- Header Warning -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-9 h-9 rounded-xl bg-danger text-white flex items-center justify-center text-sm shrink-0 mt-0.5 shadow-2xs">
                                    <i class="fas fa-user-lock" aria-hidden="true"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-danger-800 text-sm">Formasi Masih Terisi Pejabat Definitif</span>
                                    </div>
                                    <p class="mt-1 text-xs text-danger-800 leading-relaxed">
                                        {{ $swapIncompatibilityMessage ?? 'Formasi target saat ini masih terisi oleh pejabat lain dan tidak dapat diproses melalui Mutasi Tunggal.' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card Profil Pejabat Petahana (Incumbent Occupant Profile) -->
                        @if (!empty($targetAnalysis['occupant']))
                            @php $occ = $targetAnalysis['occupant']; @endphp
                            <div class="space-y-1.5">
                                <span class="text-xs font-bold text-surface-500 uppercase tracking-wider flex items-center gap-1.5">
                                    <i class="fas fa-id-card text-surface-400" aria-hidden="true"></i>
                                    Profil Pejabat Petahana Saat Ini
                                </span>
                                <x-admin.employee-profile-card
                                    :employee="$occ"
                                    :show-grade-badge="false"
                                    :unit-tenure="$occ['tenure_text'] ?? null"
                                />
                            </div>
                        @endif

                        <!-- Solusi Prosedural Enterprise: Call-to-Action to Mutasi Berantai -->
                        <div class="p-3 bg-white/90 rounded-xl border border-danger-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
                            <div class="space-y-0.5">
                                <span class="font-bold text-surface-800 block">Rekomendasi Prosedur Resmi:</span>
                                <p class="text-xs text-surface-600 leading-relaxed">
                                    Gunakan <strong>Mutasi Berantai</strong> untuk memindahkan pejabat petahana secara berantai (domino) ke formasi lain dalam satu SK kolektif.
                                </p>
                            </div>
                            <a href="{{ route('admin.mutasi.chain') }}"
                               aria-label="Selesaikan melalui Mutasi Berantai untuk memindahkan {{ $occ['nama'] ?? 'pejabat petahana' }}"
                               class="inline-flex items-center justify-center gap-2 min-h-11 px-3.5 py-2 rounded-xl bg-primary-700 hover:bg-primary-800 text-white font-bold text-xs shadow-xs transition shrink-0 cursor-pointer">
                                <i class="fas fa-link text-xs" aria-hidden="true"></i>
                                <span>Selesaikan melalui Mutasi Berantai</span>
                            </a>
                        </div>
                    </div>
                @endif
            @elseif ($targetAnalysis['type'] === 'pool')
                <div class="p-3.5 bg-earth-50 border border-earth-200 rounded-xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-earth-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                        <i class="fas fa-users" aria-hidden="true"></i>
                    </div>
                    <div class="text-xs text-earth-900 leading-relaxed flex-1">
                        <span class="font-bold text-earth-900 block">Formasi Penampungan Multi-Occupancy (Pool)</span>
                        <p class="mt-0.5 text-earth-800">
                            Formasi ini tidak dibatasi kuota tunggal. Penempatan karyawan ke posisi ini tidak menggeser pejabat lain.
                        </p>
                    </div>
                </div>
            @elseif ($targetAnalysis['type'] === 'reserved')
                <div class="p-3.5 bg-warning-50 border border-warning-200 rounded-xl flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-warning-700 text-white flex items-center justify-center text-xs shrink-0 mt-0.5 shadow-2xs">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                    </div>
                    <div class="text-xs text-warning-800 leading-relaxed flex-1">
                        <span class="font-bold text-warning-800 block">Formasi Telah Dipesan (Reserved)</span>
                        <p class="mt-0.5 text-warning-800">
                            Posisi ini telah dipesan oleh mutasi terjadwal atas nama <strong>{{ $targetAnalysis['reserved_occupant']['nama'] ?? 'karyawan lain' }}</strong> (TMT Efektif: {{ $targetAnalysis['reserved_occupant']['tmt_awal'] ?? '-' }}). Pilih formasi lain yang tersedia.
                        </p>
                    </div>
                </div>
            @elseif ($targetAnalysis['type'] === 'same')
                <div class="p-3.5 bg-danger-50 border border-danger-200 rounded-xl flex items-center gap-2.5 text-xs text-danger-800 font-bold">
                    <i class="fas fa-ban text-danger text-sm" aria-hidden="true"></i>
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
                :locked="(bool) $this->blockingReason"
                error-tenure-key="dispensasiTenure"
                error-tenure-reason-key="alasanDispensasiTenure"
                error-promo-key="dispensasiPromosi"
                error-promo-reason-key="alasanDispensasiPromosi"
            />
        </div>
    @endif
