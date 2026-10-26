    <!-- ── 2.5 DEDICATED FULL-WIDTH EXECUTIVE SIMULATION BANNER: TUKAR JABATAN 2-ARAH (LATERAL SWAP) ── -->
    @if ($targetAnalysis && $targetAnalysis['type'] === 'occupied' && $isSwapAllowed && $employeeDetails)
        <div class="bg-white rounded-2xl border border-earth-300 shadow-md p-5 sm:p-6 space-y-5 animate-fade-in">
            <!-- Header Simulation Banner -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-earth-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-earth-600 text-white flex items-center justify-center text-base shadow-sm">
                        <i class="fas fa-arrows-rotate"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm sm:text-base font-bold text-surface-900">
                                Simulasi Alur Tukar Jabatan 2-Arah (Lateral Swap)
                            </h3>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                <i class="fas fa-check-circle text-emerald-600 text-[9px]"></i>
                                <span>Net Zero Vacancy</span>
                            </span>
                        </div>
                        <p class="text-xs text-surface-500 mt-0.5">
                            Pertukaran posisi jabatan secara bilateral dan atomik tanpa meninggalkan formasi kosong baru sesuai regulasi tata kelola SDM.
                        </p>
                    </div>
                </div>
                <div class="text-[11px] font-semibold text-earth-800 bg-earth-50 border border-earth-200 px-3 py-1.5 rounded-xl flex items-center gap-2 shrink-0 self-start sm:self-center">
                    <i class="fas fa-scale-balanced text-earth-600"></i>
                    <span>Tingkat Eselon Setara: <strong>{{ $employeeDetails['rm_level'] ?? '—' }}</strong> &harr; <strong>{{ $targetAnalysis['occupant']['rm_level'] ?? '—' }}</strong></span>
                </div>
            </div>

            <!-- 2-Column Bilateral Flow Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 lg:gap-6">
                <!-- SISI KIRI: Pegawai Pemohon -->
                <div class="p-4 sm:p-5 bg-gradient-to-br from-white to-primary-50/30 rounded-xl border border-primary-200 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="flex items-center justify-between pb-2 border-b border-primary-100">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-primary-600"></span>
                            <span class="text-[11px] font-bold text-primary-800 uppercase tracking-wider">Pegawai Pemohon</span>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary-100 text-primary-800 border border-primary-200">
                            {{ $employeeDetails['rm_level'] ?? '—' }}
                        </span>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-700 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-2xs">
                            {{ strtoupper(substr($employeeDetails['nama'] ?? 'P', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-bold text-surface-900 text-sm truncate" title="{{ $employeeDetails['nama'] ?? '' }}">
                                {{ $employeeDetails['nama'] ?? '' }}
                            </h4>
                            <p class="text-xs text-surface-500 font-mono">
                                NIK: {{ $form->employeeNik }} &bull; Gol: {{ $employeeDetails['golongan'] ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <!-- Flow Transition Box: Asal -> Menempati -->
                    <div class="p-3 rounded-xl bg-white border border-primary-200/90 shadow-2xs space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-[10px] font-semibold text-surface-400">
                            <span>Posisi Semula:</span>
                            <span class="text-surface-600 truncate max-w-[200px]" title="{{ $employeeDetails['jabatan'] }}">{{ $employeeDetails['jabatan'] }}</span>
                        </div>
                        <div class="pt-1.5 border-t border-dashed border-primary-200">
                            <div class="flex items-center gap-1.5 text-[10px] font-bold text-primary-700 uppercase tracking-wider">
                                <i class="fas fa-arrow-right text-[9px]"></i> Menempati Posisi Baru:
                            </div>
                            <div class="font-bold text-surface-900 text-xs sm:text-sm mt-0.5" title="{{ $form->targetJabatan }}">
                                {{ $form->targetJabatan }}
                            </div>
                            <div class="text-[11px] text-surface-600 flex items-center gap-1.5 mt-0.5" title="{{ $form->targetUnitKerja }}">
                                <i class="fas fa-building text-[10px] text-surface-400"></i>
                                <span>{{ $form->targetUnitKerja }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SISI KANAN: Pejabat Petahana yang Bertukar Posisi -->
                <div class="p-4 sm:p-5 bg-gradient-to-br from-white to-earth-50/40 rounded-xl border border-earth-300 shadow-2xs space-y-3 flex flex-col justify-between">
                    <div class="flex items-center justify-between pb-2 border-b border-earth-200">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-earth-600"></span>
                            <span class="text-[11px] font-bold text-earth-800 uppercase tracking-wider">Pejabat Petahana (Bertukar Posisi)</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-earth-100 text-earth-800 border border-earth-300">
                                {{ $targetAnalysis['occupant']['rm_level'] ?? '—' }}
                            </span>
                            @if (!empty($targetAnalysis['occupant']['nik']))
                                <button type="button" wire:click="openTalentDrawer('{{ $targetAnalysis['occupant']['nik'] }}')"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold text-earth-800 bg-white hover:bg-earth-100 border border-earth-300 shadow-2xs transition cursor-pointer"
                                    title="Lihat matriks 9-box dan profil petahana">
                                    <i class="fas fa-th-large text-[9px] text-earth-600"></i>
                                    <span>Lihat 9-Box</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-earth-700 text-white font-bold text-sm flex items-center justify-center shrink-0 shadow-2xs">
                            {{ strtoupper(substr($targetAnalysis['occupant']['nama'] ?? 'P', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-bold text-surface-900 text-sm truncate" title="{{ $targetAnalysis['occupant']['nama'] ?? '' }}">
                                {{ $targetAnalysis['occupant']['nama'] ?? '' }}
                            </h4>
                            <p class="text-xs text-surface-500 font-mono">
                                NIK: {{ $targetAnalysis['occupant']['nik'] ?? '-' }} &bull; Gol: {{ $targetAnalysis['occupant']['golongan'] ?? '—' }}
                            </p>
                        </div>
                    </div>

                    <!-- Flow Transition Box: Asal -> Menempati Posisi Pemohon -->
                    <div class="p-3 rounded-xl bg-white border border-earth-200/90 shadow-2xs space-y-1.5 text-xs">
                        <div class="flex items-center justify-between text-[10px] font-semibold text-surface-400">
                            <span>Posisi Semula:</span>
                            <span class="text-surface-600 truncate max-w-[200px]" title="{{ $form->targetJabatan }}">{{ $form->targetJabatan }}</span>
                        </div>
                        <div class="pt-1.5 border-t border-dashed border-earth-300">
                            <div class="flex items-center gap-1.5 text-[10px] font-bold text-earth-800 uppercase tracking-wider">
                                <i class="fas fa-arrow-right text-[9px]"></i> Menempati Posisi Baru:
                            </div>
                            <div class="font-bold text-surface-900 text-xs sm:text-sm mt-0.5" title="{{ $employeeDetails['jabatan'] ?? '' }}">
                                {{ $employeeDetails['jabatan'] ?? '' }}
                            </div>
                            <div class="text-[11px] text-surface-600 flex items-center gap-1.5 mt-0.5" title="{{ $employeeDetails['unit_kerja'] ?? '' }}">
                                <i class="fas fa-building text-[10px] text-surface-400"></i>
                                <span>{{ $employeeDetails['unit_kerja'] ?? '' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Swap Confirmation Checkbox -->
            <div class="p-4 bg-earth-50/80 rounded-xl border border-earth-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox" wire:model.live="form.swapConfirmed"
                        class="mt-0.5 w-4 h-4 rounded text-earth-600 border-earth-400 focus:ring-earth-500 cursor-pointer">
                    <span class="text-xs sm:text-sm font-semibold text-surface-900 leading-snug">
                        Saya mengonfirmasi bahwa pertukaran posisi kedua karyawan di atas telah diverifikasi dan disetujui secara resmi. <span class="text-rose-500">*</span>
                    </span>
                </label>
                @error('swapConfirmed')
                    <p class="text-rose-600 text-xs font-semibold pl-7 sm:pl-0">{{ $message }}</p>
                @enderror
            </div>
        </div>
    @endif
