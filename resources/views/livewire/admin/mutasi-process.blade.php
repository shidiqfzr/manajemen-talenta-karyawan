<div class="space-y-5">

    {{-- Success Flash Message --}}
    @if (session()->has('mutasi_success'))
        <div class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-lg flex items-center justify-between shadow-sm animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-primary-600 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-xs"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-primary-900">Mutasi Berhasil Diproses!</h4>
                    <p class="text-xs text-primary-700 mt-0.5">{{ session('mutasi_success') }}</p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-primary-600 hover:text-primary-800 transition p-1">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    @endif

    {{-- Guided Banner --}}
    <div class="bg-primary-50 rounded-lg p-4 sm:p-5 shadow-sm flex items-start gap-4" style="border: 1px solid #C9E8D5;">
        <div class="w-10 h-10 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center text-lg shrink-0 border border-primary-200 shadow-inner">
            <i class="fas fa-lightbulb"></i>
        </div>
        <div class="space-y-1.5">
            <h4 class="text-xs sm:text-sm font-bold text-primary-900 uppercase tracking-wide">Sistem Penempatan Cerdas (Smart Assignment)</h4>
            <p class="text-xs text-primary-800 leading-relaxed max-w-4xl">
                Pilih jenis mutasi dan karyawan. Sistem akan <strong class="text-primary-900 font-extrabold">secara otomatis memfilter</strong> jabatan tujuan yang sesuai (misal: Promosi hanya menampilkan jabatan eselon lebih tinggi). Status formasi (<span class="text-primary-700 font-bold bg-primary-100 px-1.5 py-0.5 rounded">Transfer Bersih</span> atau <span class="text-earth-700 font-bold bg-earth-100 px-1.5 py-0.5 rounded">Tukar Jabatan</span>) akan dikalkulasi real-time.
            </p>
        </div>
    </div>

    <form wire:submit="submitMutasi" class="space-y-5">

        {{-- ═══════════════════ MAIN ASSIGNMENT CARDS ═══════════════════ --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-stretch">

            {{-- ── 1. SOURCE EMPLOYEE (5 Cols) ── --}}
            <div class="lg:col-span-5 bg-white rounded-lg border border-surface-200 shadow-sm p-5 space-y-4 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-bold flex items-center justify-center">1</span>
                            <h3 class="text-sm font-bold text-surface-900">Jenis Mutasi &amp; Karyawan</h3>
                        </div>
                        <span class="text-[11px] font-semibold text-surface-400 uppercase tracking-wider">Asal Posisi</span>
                    </div>

                    <div class="mt-4 space-y-4">
                        {{-- JENIS MUTASI --}}
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Jenis Mutasi <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <select wire:model.live="form.jenisMutasi"
                                    class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none cursor-pointer">
                                    @foreach ($mutasiTypes as $type)
                                        <option value="{{ $type }}">{{ ucwords(strtolower(str_replace('_', ' ', $type))) }}</option>
                                    @endforeach
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-surface-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                        </div>

                        {{-- STATUS JABATAN --}}
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Status Penugasan <span class="text-danger">*</span>
                            </label>
                            @php
                                $isStatusLocked = in_array($jenisMutasi, ['SANKSI_INDISIPLINER', 'PENUGASAN_KHUSUS']);
                            @endphp
                            <div class="relative">
                                <select wire:model.live="form.statusJabatan"
                                    {{ $isStatusLocked ? 'disabled' : '' }}
                                    class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition appearance-none {{ $isStatusLocked ? 'bg-surface-100 cursor-not-allowed opacity-80' : 'cursor-pointer' }}">
                                    <option value="Definitif">Definitif (Posisi Utama)</option>
                                    <option value="Pj">Pejabat Sementara (Pj)</option>
                                    <option value="Plt">Pelaksana Tugas (Plt)</option>
                                </select>
                                <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-surface-400">
                                    <i class="fas fa-chevron-down text-xs"></i>
                                </div>
                            </div>
                            <p class="mt-1.5 text-[10px] text-surface-500 leading-tight">
                                <i class="fas fa-info-circle mr-1 text-primary-500"></i> Pj/Plt tidak akan menutup jabatan utama (merangkap).
                            </p>
                        </div>
                        {{-- FILTER UNIT ASAL (OPSIONAL) --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-surface-700">
                                    Filter Unit Asal
                                </label>
                                <span class="text-[10px] text-surface-400 font-medium bg-surface-100 px-1.5 py-0.5 rounded">Opsional</span>
                            </div>
                            <x-admin.searchable-select 
                                model="form.sourceUnitFilter" 
                                :options="$unitList" 
                                placeholder="-- Semua Unit Kerja --"
                                icon="fas fa-filter"
                                emptyText="Unit kerja tidak ditemukan"
                                :hasAllOption="true"
                                allOptionText="-- Semua Unit Kerja --"
                            />
                        </div>

                        {{-- KARYAWAN --}}
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Pilih Karyawan Aktif <span class="text-danger">*</span>
                            </label>
                            @php
                                $empOptions = $employees->map(function($e) {
                                    return ['value' => $e->nik, 'label' => $e->nama . ' — ' . $e->nik];
                                })->values()->toArray();
                            @endphp
                            <x-admin.searchable-select 
                                model="form.employeeNik" 
                                :options="$empOptions" 
                                placeholder="-- Pilih Karyawan --"
                                emptyText="Karyawan tidak ditemukan"
                            />
                            @error('employeeNik')
                                <p class="text-danger text-xs mt-1.5 flex items-center gap-1">
                                    <i class="fas fa-exclamation-circle text-xs"></i> {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Current Position Details Card --}}
                        @if ($employeeDetails)
                            <div class="p-4 rounded-lg {{ $employeeDetails['has_job'] ? 'bg-surface-50 border border-surface-200' : 'bg-amber-50 border border-amber-200' }} transition">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider {{ $employeeDetails['has_job'] ? 'text-surface-500' : 'text-amber-800' }}">
                                        Jabatan Terkini
                                    </span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold bg-earth-100 text-earth-800 border border-earth-300 tabular-nums">
                                        Gol: {{ $employeeDetails['golongan'] ?? '—' }} | {{ $employeeDetails['rm_level'] ?? '—' }}
                                    </span>
                                </div>
                                @if ($employeeDetails['has_job'])
                                    <h4 class="text-sm font-bold text-surface-900 leading-snug">{{ $employeeDetails['jabatan'] }}</h4>
                                    <p class="text-xs text-surface-600 mt-1 flex items-center gap-1.5">
                                        <i class="fas fa-building text-surface-400 text-[11px]"></i>
                                        {{ $employeeDetails['unit_kerja'] }}
                                    </p>
                                    @if($employeeDetails['level'])
                                        <p class="text-[11px] text-surface-500 mt-1">Level: {{ $employeeDetails['level'] }}</p>
                                    @endif
                                @else
                                    <p class="text-xs font-semibold text-amber-800 flex items-center gap-1.5">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Belum memiliki data jabatan aktif
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="p-6 rounded-lg bg-surface-50 border border-dashed border-surface-300 text-center">
                                <div class="w-10 h-10 mx-auto rounded-full bg-surface-100 text-surface-400 flex items-center justify-center text-sm mb-2">
                                    <i class="fas fa-user"></i>
                                </div>
                                <p class="text-xs font-medium text-surface-500">Pilih karyawan di atas untuk melihat rincian jabatan saat ini</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($employeeDetails && $targetAnalysis && $targetAnalysis['type'] === 'vacant')
                    <div class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex items-start gap-2">
                        <i class="fas fa-info-circle text-amber-600 mt-0.5 shrink-0"></i>
                        <span>
                            <strong>Peringatan Formasi Kosong:</strong> Memindahkan karyawan ini akan otomatis meninggalkan formasi kosong pada <em>{{ $employeeDetails['jabatan'] }}</em> di unit saat ini.
                        </span>
                    </div>
                @endif
            </div>

            {{-- ── 2. CENTER CONNECTOR (2 Cols) ── --}}
            <div class="lg:col-span-2 flex flex-col items-center justify-center py-4 lg:py-0">
                @php
                    $impactConfig = match ($targetAnalysis['type'] ?? null) {
                        'pool' => [
                            'label' => 'Penempatan Pool',
                            'badge' => 'bg-blue-50 border-blue-200 text-blue-800',
                            'iconClass' => 'bg-blue-600 text-white shadow-sm',
                            'icon' => 'fa-users',
                        ],
                        'vacant' => [
                            'label' => 'Transfer Bersih',
                            'badge' => 'bg-primary-50 border-primary-200 text-primary-800',
                            'iconClass' => 'bg-primary-600 text-white shadow-sm',
                            'icon' => 'fa-arrow-right',
                        ],
                        'occupied' => [
                            'label' => 'Tukar Jabatan',
                            'badge' => 'bg-earth-100 border-earth-300 text-earth-800',
                            'iconClass' => 'bg-earth-600 text-white shadow-sm',
                            'icon' => 'fa-exchange-alt',
                        ],
                        'same' => [
                            'label' => 'Posisi Sama',
                            'badge' => 'bg-red-50 border-red-200 text-danger',
                            'iconClass' => 'bg-danger text-white shadow-sm',
                            'icon' => 'fa-ban',
                        ],
                        default => [
                            'label' => 'Menunggu Target',
                            'badge' => 'bg-surface-100 border-surface-200 text-surface-500',
                            'iconClass' => 'bg-surface-200 text-surface-500 shadow-none',
                            'icon' => 'fa-arrow-right',
                        ],
                    };
                @endphp

                <div class="flex flex-col items-center gap-2">
                    <div class="w-11 h-11 rounded-full {{ $impactConfig['iconClass'] }} flex items-center justify-center text-sm transition-all duration-300">
                        <i class="fas {{ $impactConfig['icon'] }}"></i>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $impactConfig['badge'] }} text-center transition">
                        {{ $impactConfig['label'] }}
                    </span>
                </div>
            </div>

            {{-- ── 3. TARGET POSITION (5 Cols) ── --}}
            <div class="lg:col-span-5 bg-white rounded-lg border border-surface-200 shadow-sm p-5 space-y-4 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                        <div class="flex items-center gap-2.5">
                            <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-bold flex items-center justify-center">2</span>
                            <h3 class="text-sm font-bold text-surface-900">Formasi &amp; Posisi Tujuan</h3>
                        </div>
                        <span class="text-[11px] font-semibold text-surface-400 uppercase tracking-wider">Tujuan Penempatan</span>
                    </div>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Unit Kerja Tujuan <span class="text-danger">*</span>
                            </label>
                            @if(!$employeeNik)
                                <div class="relative">
                                    <span class="absolute left-3.5 inset-y-0 flex items-center text-xs text-surface-400">
                                        <i class="fas fa-building"></i>
                                    </span>
                                    <select disabled class="w-full pl-9 pr-3.5 py-2.5 bg-surface-100 border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-400 cursor-not-allowed appearance-none">
                                        <option>-- Pilih Unit Kerja Tujuan --</option>
                                    </select>
                                </div>
                                <p class="text-[11px] text-surface-500 mt-1">Pilih karyawan terlebih dahulu.</p>
                            @elseif($jenisMutasi === 'ROTASI')
                                <div class="p-2.5 bg-surface-50 border border-surface-300 rounded-lg flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fas fa-lock text-surface-400 text-xs"></i>
                                        <span class="text-xs sm:text-sm font-bold text-surface-800">{{ $targetUnitKerja ?: ($employeeDetails['unit_kerja'] ?? '') }}</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-primary-700 bg-primary-50 border border-primary-200 px-2 py-0.5 rounded">
                                        Rotasi Internal Unit
                                    </span>
                                </div>
                                <p class="text-[11px] text-surface-500 mt-1">Unit kerja terkunci otomatis karena jenis mutasi adalah Rotasi &amp; Alih Tugas dalam unit yang sama (DOMAIN.md §5.4.3).</p>
                            @else
                                <div x-data="{ unit: @entangle('form.targetUnitKerja').live }"
                                     @unit-selected.window="if($event.detail.unit !== undefined) { unit = $event.detail.unit; }">
                                    <x-admin.unit-combobox 
                                        :grouped-units="$groupedUnits" 
                                        :selected="$targetUnitKerja" 
                                        id="unitKerjaInputMutasi"
                                        name="unit_kerja" />
                                </div>
                            @endif
                            @error('targetUnitKerja')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror

                            {{-- Tenure Guard Warning (DOMAIN.md §5.2.1) --}}
                            @if ($tenureAnalysis && ! $tenureAnalysis['is_compliant'])
                                <div class="mt-2.5 p-3.5 bg-amber-50 border border-amber-300 rounded-lg space-y-2.5 shadow-2xs animate-fade-in">
                                    <div class="flex items-start gap-2.5">
                                        <div class="w-6 h-6 rounded-md bg-amber-500 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                            <i class="fas fa-history"></i>
                                        </div>
                                        <div class="text-xs text-amber-950 leading-relaxed">
                                            <span class="font-bold text-amber-900">Peringatan Masa Dinas Unit Kerja (DOMAIN.md §5.2.1)</span><br>
                                            Pegawai baru bertugas <strong class="font-bold text-amber-950">{{ $tenureAnalysis['years'] }} tahun ({{ $tenureAnalysis['months'] }} bulan)</strong> di {{ $employeeDetails['unit_kerja'] ?? 'unit saat ini' }}. Standar regulasi rotasi unit minimal 2–3 tahun untuk kestabilan operasional.
                                        </div>
                                    </div>
                                    <div class="pt-2 border-t border-amber-200 space-y-2">
                                        <label class="flex items-start gap-2 cursor-pointer">
                                            <input type="checkbox" wire:model.live="form.dispensasiTenure" class="mt-0.5 w-4 h-4 rounded text-amber-600 border-amber-400 focus:ring-amber-500 cursor-pointer">
                                            <span class="text-xs font-semibold text-amber-950">
                                                Rotasi unit sebelum 2 tahun ini telah disetujui melalui Dispensasi Khusus Direksi. <span class="text-danger">*</span>
                                            </span>
                                        </label>
                                        @error('dispensasiTenure')
                                            <p class="text-danger text-xs font-semibold">{{ $message }}</p>
                                        @enderror
                                        @if ($dispensasiTenure)
                                            <div>
                                                <input type="text" wire:model="form.alasanDispensasiTenure" placeholder="Alasan pertimbangan / Nomor Nota Persetujuan Direksi..."
                                                    class="w-full px-3 py-1.5 bg-white border border-amber-300 rounded-md text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-1 focus:ring-amber-500 transition">
                                                @error('alasanDispensasiTenure')
                                                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1.5">
                                Jabatan Tujuan <span class="text-danger">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3.5 inset-y-0 flex items-center text-xs transition-colors duration-300" :class="'{{ $targetUnitKerja }}' ? 'text-primary-600' : 'text-surface-400'">
                                    <i class="fas fa-briefcase"></i>
                                </span>
                                <select wire:model.live="form.targetJabatan"
                                    @if(!$targetUnitKerja) disabled @endif
                                    class="w-full pl-9 pr-8 py-2.5 rounded-lg text-xs sm:text-sm font-medium focus:outline-none focus:ring-2 transition-all duration-300 appearance-none @if(!$targetUnitKerja) bg-surface-100 border border-surface-300 text-surface-400 cursor-not-allowed @else bg-white border border-surface-300 text-surface-800 focus:border-primary-600 focus:ring-primary-300 @endif">
                                    
                                    @if(!$targetUnitKerja)
                                        <option value="">-- Pilih Unit Kerja Terlebih Dahulu --</option>
                                    @elseif(count($jabatanList) === 0)
                                        <option value="">-- Formasi Kosong / Memuat --</option>
                                    @else
                                        <option value="">-- Pilih Formasi Jabatan --</option>
                                    @endif

                                    @foreach ($jabatanList as $j)
                                        <option value="{{ $j }}">{{ $j }}</option>
                                    @endforeach
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3.5 text-surface-400">
                                    <i class="fas fa-chevron-down text-[10px]"></i>
                                </div>
                            </div>
                            @if(!$targetUnitKerja)
                                <p class="text-[11px] text-surface-500 mt-1">Pilih Unit Kerja terlebih dahulu untuk melihat daftar formasi.</p>
                            @endif
                            @error('targetJabatan')
                                <p class="text-danger text-xs mt-1">{{ $message }}</p>
                            @enderror

                            {{-- Promotion Readiness & Eligibility Card (DOMAIN.md §5.2.3 & §6) --}}
                            @if ($promotionAnalysis && $promotionAnalysis['is_applicable'])
                                <div class="mt-2.5 p-3.5 rounded-lg border {{ $promotionAnalysis['is_eligible'] ? 'bg-primary-50/60 border-primary-200' : 'bg-purple-50/70 border-purple-200' }} space-y-2.5 shadow-2xs animate-fade-in">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-6 h-6 rounded-md {{ $promotionAnalysis['is_eligible'] ? 'bg-primary-600' : 'bg-purple-600' }} text-white flex items-center justify-center text-xs shrink-0">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <h4 class="text-xs font-bold {{ $promotionAnalysis['is_eligible'] ? 'text-primary-900' : 'text-purple-900' }} uppercase tracking-wider">
                                                Syarat Formal Promosi ({{ $promotionAnalysis['current_rm'] }} &rarr; {{ $promotionAnalysis['target_rm'] }})
                                            </h4>
                                        </div>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $promotionAnalysis['is_eligible'] ? 'bg-primary-100 text-primary-800 border border-primary-200' : 'bg-purple-100 text-purple-800 border border-purple-200' }}">
                                            {{ $promotionAnalysis['is_eligible'] ? 'Memenuhi Syarat' : 'Perlu Dispensasi' }}
                                        </span>
                                    </div>

                                    <div class="space-y-1.5 text-xs">
                                        @foreach ($promotionAnalysis['criteria'] as $crit)
                                            <div class="flex items-center justify-between p-2 rounded bg-white/90 border border-surface-200 text-xs">
                                                <div class="flex items-center gap-2 min-w-0">
                                                    <i class="fas {{ $crit['met'] ? 'fa-check-circle text-primary-600' : 'fa-times-circle text-danger' }} text-xs shrink-0"></i>
                                                    <span class="font-medium text-surface-800 truncate">{{ $crit['label'] }}</span>
                                                </div>
                                                <span class="font-semibold text-surface-600 text-[11px] shrink-0 ml-2">{{ $crit['value'] }}</span>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (! $promotionAnalysis['is_eligible'])
                                        <div class="pt-2 border-t border-purple-200 space-y-2">
                                            <label class="flex items-start gap-2 cursor-pointer">
                                                <input type="checkbox" wire:model.live="form.dispensasiPromosi" class="mt-0.5 w-4 h-4 rounded text-purple-600 border-purple-400 focus:ring-purple-500 cursor-pointer">
                                                <span class="text-xs font-semibold text-purple-950">
                                                    Pengajuan promosi ini telah memperoleh persetujuan Dispensasi Promosi Dipercepat / Keputusan Khusus. <span class="text-danger">*</span>
                                                </span>
                                            </label>
                                            @error('dispensasiPromosi')
                                                <p class="text-danger text-xs font-semibold">{{ $message }}</p>
                                            @enderror
                                            @if ($dispensasiPromosi)
                                                <div>
                                                    <input type="text" wire:model="form.alasanDispensasiPromosi" placeholder="Alasan pertimbangan promosi / nomor rekomendasi asesmen..."
                                                        class="w-full px-3 py-1.5 bg-white border border-purple-300 rounded-md text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-1 focus:ring-purple-500 transition">
                                                    @error('alasanDispensasiPromosi')
                                                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Diperban Warning --}}
                        @if ($isTargetDiperban)
                            <div class="p-4 bg-amber-50 border border-amber-300 rounded-lg space-y-3 shadow-inner">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-amber-500 text-white flex items-center justify-center shrink-0">
                                        <i class="fas fa-exclamation-triangle text-xs"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-amber-900 leading-tight">Perhatian: Formasi Sanksi (Diperban)</h4>
                                </div>
                                <p class="text-xs text-amber-800 font-medium">Jabatan ini ditandai sebagai formasi khusus demosi/sanksi. Anda wajib menentukan masa berlaku (tanggal kedaluwarsa) sanksi pada bagian Legalitas di bawah.</p>
                            </div>
                        @endif

                        {{-- Advisor / Overflow Warning --}}
                        @if ($isTargetOverflow && !$isTargetDiperban)
                            <div class="p-4 bg-primary-50 border border-primary-200 rounded-lg space-y-3 shadow-inner">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-primary-600 text-white flex items-center justify-center shrink-0">
                                        <i class="fas fa-info-circle text-xs"></i>
                                    </div>
                                    <h4 class="text-sm font-bold text-primary-900 leading-tight">Formasi Penampungan / Advisor</h4>
                                </div>
                                <p class="text-xs text-primary-800 font-medium leading-relaxed">Jabatan ini adalah formasi penampungan non-struktural (Overflow). Karyawan yang dipindahkan ke sini tidak akan memakan kuota struktural Man Power Planning, namun gaji dan hak level/golongan tetap mengikuti kebijakan yang berlaku.</p>
                            </div>
                        @endif

                        {{-- Target Analysis Result Banner --}}
                        @if ($targetAnalysis)
                            @if ($targetAnalysis['type'] === 'pool')
                                <div class="p-3.5 bg-blue-50 border border-blue-200 rounded-lg flex items-start gap-2.5">
                                    <div class="w-6 h-6 rounded-md bg-blue-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="text-xs text-blue-950 leading-relaxed">
                                        <span class="font-bold text-blue-900">Multi-Occupancy (Pool)</span><br>
                                        Kapasitas penempatan untuk formasi khusus ini tidak dibatasi. Karyawan akan ditambahkan ke dalam pool tanpa menggeser (*swap*) dengan karyawan saat ini.
                                    </div>
                                </div>
                            @elseif ($targetAnalysis['type'] === 'vacant')
                                <div class="p-3.5 bg-primary-50 border border-primary-200 rounded-lg flex items-start gap-2.5">
                                    <div class="w-6 h-6 rounded-md bg-primary-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                        <i class="fas fa-door-open"></i>
                                    </div>
                                    <div class="text-xs text-primary-950 leading-relaxed">
                                        <span class="font-bold text-primary-900">Formasi Kosong (Open Seat)</span><br>
                                        Tidak ada pejabat aktif di posisi ini. Karyawan akan langsung ditugaskan mengisi formasi ini secara bersih.
                                    </div>
                                </div>
                            @elseif ($targetAnalysis['type'] === 'occupied')
                                @if (!$isSwapAllowed)
                                    <div class="p-3.5 bg-red-50 border border-red-200 rounded-lg space-y-2.5 shadow-2xs">
                                        <div class="flex items-start gap-2.5">
                                            <div class="w-6 h-6 rounded-md bg-danger text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                                <i class="fas fa-ban"></i>
                                            </div>
                                            <div class="text-xs text-red-950 leading-relaxed">
                                                <span class="font-bold text-danger uppercase tracking-wider">Tukar Jabatan (Swap) Ditolak</span><br>
                                                {{ $swapIncompatibilityMessage }}
                                            </div>
                                        </div>
                                        <div class="p-2 bg-white/80 rounded border border-red-200 text-[11px] text-surface-700">
                                            Pejabat saat ini: <strong>{{ $targetAnalysis['occupant']['nama'] }}</strong> ({{ $targetAnalysis['occupant']['rm_level'] ?? '—' }}) | Karyawan asal: <strong>{{ $employeeDetails['nama'] ?? '—' }}</strong> ({{ $employeeDetails['rm_level'] ?? '—' }})
                                        </div>
                                    </div>
                                @else
                                    <div class="p-3.5 bg-earth-100/70 border border-earth-300 rounded-lg space-y-3">
                                        <div class="flex items-start gap-2.5">
                                            <div class="w-6 h-6 rounded-md bg-earth-600 text-white flex items-center justify-center text-xs shrink-0 mt-0.5">
                                                <i class="fas fa-user-check"></i>
                                            </div>
                                            <div class="text-xs text-surface-900 leading-relaxed">
                                                <span class="font-bold text-earth-800">Posisi Sedang Ditempati:</span>
                                                <div class="font-bold text-surface-900 text-sm mt-0.5">{{ $targetAnalysis['occupant']['nama'] }}</div>
                                                <div class="text-[11px] text-surface-600 font-mono">NIK: {{ $targetAnalysis['occupant']['nik'] }} | Gol: {{ $targetAnalysis['occupant']['golongan'] ?? '—' }} | Eselon: {{ $targetAnalysis['occupant']['rm_level'] ?? '—' }}</div>
                                                <p class="mt-1 text-surface-700">
                                                    Sistem akan menjalankan <strong>Tukar Jabatan 2 Arah (Lateral Swap)</strong> secara simultan. Tidak ada lowongan baru yang tertinggal.
                                                </p>
                                            </div>
                                        </div>

                                        {{-- Occupant Tenure Evaluation --}}
                                        @if ($occupantTenureAnalysis && ! $occupantTenureAnalysis['is_compliant'])
                                            <div class="p-2.5 bg-amber-50 border border-amber-200 rounded text-xs text-amber-900 flex items-start gap-2">
                                                <i class="fas fa-exclamation-triangle text-amber-600 mt-0.5 shrink-0"></i>
                                                <span><strong>Peringatan Masa Dinas Pejabat Tergeser:</strong> {{ $targetAnalysis['occupant']['nama'] }} baru bertugas {{ $occupantTenureAnalysis['years'] }} tahun di unit ini (kurang dari batas 2 tahun). Pastikan nota persetujuan direksi juga mencakup pergeseran pejabat ini.</span>
                                            </div>
                                        @endif

                                        {{-- Swap Guard Checkbox --}}
                                        <label class="flex items-start gap-2.5 cursor-pointer pt-2.5 border-t border-earth-300">
                                            <input type="checkbox" wire:model.live="form.swapConfirmed"
                                                class="mt-0.5 w-4 h-4 rounded text-earth-600 border-earth-400 focus:ring-earth-500 cursor-pointer">
                                            <span class="text-xs font-semibold text-surface-900 leading-tight">
                                                Saya mengonfirmasi bahwa pertukaran posisi kedua karyawan ini telah disetujui secara resmi.
                                            </span>
                                        </label>
                                        @error('swapConfirmed')
                                            <p class="text-danger text-xs font-semibold">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
                            @elseif ($targetAnalysis['type'] === 'same')
                                <div class="p-3.5 bg-red-50 border border-red-200 rounded-lg flex items-center gap-2.5">
                                    <i class="fas fa-ban text-danger text-sm shrink-0"></i>
                                    <p class="text-xs text-danger font-bold">Karyawan sudah berada di posisi dan unit kerja ini.</p>
                                </div>
                            @endif
                        @else
                            <div class="p-6 rounded-lg bg-surface-50 border border-dashed border-surface-300 text-center">
                                <div class="w-10 h-10 mx-auto rounded-full bg-surface-100 text-surface-400 flex items-center justify-center text-sm mb-2">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <p class="text-xs font-medium text-surface-500">Pilih nama jabatan &amp; unit tujuan untuk melihat analisis formasi</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ ROW 2: AFTER-SWAP PREVIEW (Only for Occupied) ═══════════════════ --}}
        @if ($targetAnalysis && $targetAnalysis['type'] === 'occupied' && $employeeDetails)
            <div class="bg-earth-50 rounded-lg border border-earth-200 p-5 shadow-sm space-y-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-eye text-earth-700 text-sm"></i>
                    <h3 class="text-xs sm:text-sm font-bold text-earth-900 uppercase tracking-wider">
                        Hasil Akhir Setelah Pertukaran Jabatan
                    </h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-3.5 bg-white rounded-lg border border-earth-300 shadow-xs">
                        <span class="text-[10px] font-bold text-earth-700 uppercase tracking-wider block mb-1">
                            {{ $employeeDetails['nama'] }} akan menjabat:
                        </span>
                        <p class="text-sm font-bold text-surface-900">{{ $targetAnalysis['occupant']['jabatan'] }}</p>
                        <p class="text-xs text-surface-600 mt-0.5">{{ $targetAnalysis['occupant']['unit_kerja'] }}</p>
                    </div>
                    <div class="p-3.5 bg-white rounded-lg border border-earth-300 shadow-xs">
                        <span class="text-[10px] font-bold text-primary-700 uppercase tracking-wider block mb-1">
                            {{ $targetAnalysis['occupant']['nama'] }} akan menjabat:
                        </span>
                        <p class="text-sm font-bold text-surface-900">{{ $employeeDetails['jabatan'] }}</p>
                        <p class="text-xs text-surface-600 mt-0.5">{{ $employeeDetails['unit_kerja'] }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- ═══════════════════ ROW 3: LEGALITY & DECREE (SK) ═══════════════════ --}}
        <div class="bg-white rounded-lg border border-surface-200 shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-surface-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-6 h-6 rounded-full bg-primary-100 text-primary-800 text-xs font-bold flex items-center justify-center">3</span>
                    <h3 class="text-sm font-bold text-surface-900">Detail Surat Keputusan (SK) &amp; Legalitas</h3>
                </div>
                <span class="text-[11px] font-semibold text-surface-400 uppercase tracking-wider">Audit Trail</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Nomor SK / Surat Tugas <span class="text-danger">*</span>
                    </label>
                    <input type="text" wire:model="form.nomorSk" placeholder="Contoh: SK.DIR/REG5/MUT-SDM/01/2026"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    @error('nomorSk')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Tanggal Penerbitan SK <span class="text-danger">*</span>
                    </label>
                    <input type="date" wire:model="form.tanggalSk"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    @error('tanggalSk')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        TMT Berlaku (Efektif) <span class="text-danger">*</span>
                    </label>
                    <input type="date" wire:model="form.tmtAwal"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    @error('tmtAwal')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                @if (in_array($statusJabatan, ['Pj', 'Plt']) && !$isTargetDiperban)
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Tanggal Estimasi Selesai <span class="text-surface-400 font-normal">(Opsional)</span>
                    </label>
                    <input type="date" wire:model="form.tmtAkhirEstimasi"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    <p class="text-[10px] text-surface-500 mt-1">Sebagai pengingat masa berlaku Plt/Pj.</p>
                    @error('tmtAkhirEstimasi')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @elseif ($isTargetDiperban)
                <div>
                    <label class="block text-xs font-bold text-surface-700 mb-1.5">
                        Tanggal Berakhir Sanksi <span class="text-danger">*</span>
                    </label>
                    <input type="date" wire:model="form.tanggalBerakhirSanksi"
                        class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                    <p class="text-[10px] text-surface-500 mt-1">Wajib diisi sesuai masa hukuman/demosi.</p>
                    @error('tanggalBerakhirSanksi')
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                @else
                <div class="hidden sm:block"></div> {{-- Placeholder to keep grid aligned if Definitif --}}
                @endif
            </div>

            {{-- Digital Decree (SK) PDF Upload --}}
            <div class="mt-4 pt-4 border-t border-surface-100">
                <label class="block text-xs font-bold text-surface-700 mb-1.5">
                    Unggah Berkas SK / Surat Tugas Digital (PDF) <span class="text-surface-400 font-normal">(Opsional, Dokumen Resmi Maks. 5MB)</span>
                </label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <label class="relative inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-surface-300 rounded-lg text-xs font-semibold text-surface-700 hover:bg-surface-50 hover:border-surface-400 transition cursor-pointer shadow-2xs">
                        <i class="fas fa-file-pdf text-danger text-sm"></i>
                        <span>Pilih Berkas PDF SK</span>
                        <input type="file" wire:model="form.fileSk" accept="application/pdf" class="sr-only">
                    </label>

                    <div wire:loading wire:target="form.fileSk" class="text-xs text-primary-600 flex items-center gap-1.5 font-medium">
                        <i class="fas fa-spinner fa-spin text-xs"></i> Mengunggah berkas...
                    </div>

                    @if ($fileSk)
                        <div class="flex items-center gap-2 text-xs text-primary-800 font-semibold bg-primary-50 px-3 py-1.5 rounded-lg border border-primary-200">
                            <i class="fas fa-check-circle text-xs text-primary-600"></i>
                            <span class="truncate max-w-xs">{{ $fileSk->getClientOriginalName() }}</span>
                            <button type="button" wire:click="$set('form.fileSk', null)" class="text-surface-400 hover:text-danger ml-1 p-0.5 cursor-pointer" title="Hapus berkas">
                                <i class="fas fa-times text-xs"></i>
                            </button>
                        </div>
                    @endif
                </div>
                @error('fileSk')
                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Penyesuaian Golongan & Job Grade Baru (DOMAIN.md §4 & §5.2.3) --}}
            @if (in_array($jenisMutasi, ['PROMOSI', 'DEMOSI']))
                <div class="mt-4 pt-4 border-t border-surface-100 bg-surface-50 p-4 rounded-lg border border-surface-200 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-layer-group text-primary-600 text-xs"></i>
                            <h4 class="text-xs font-bold text-surface-900 uppercase tracking-wide">
                                Penyesuaian Golongan &amp; Job Grade Baru ({{ ucwords(strtolower($jenisMutasi)) }})
                            </h4>
                        </div>
                        <span class="text-[10px] text-surface-500 font-medium bg-white px-2 py-0.5 rounded border border-surface-200">
                            Opsional SK
                        </span>
                    </div>
                    <p class="text-[11px] text-surface-600 leading-relaxed">
                        Jika SK penetapan promosi/demosi memuat penyesuaian golongan atau kenaikan Job Grade, Anda dapat memperbaruinya langsung di bawah ini. Jika tidak diubah, profil akan mempertahankan data saat ini.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1">
                                Golongan Baru
                            </label>
                            <input type="text" wire:model="form.newGolongan" placeholder="Contoh: IIIC/00 atau IVB/02"
                                class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                            <span class="text-[10px] text-surface-400 mt-1 block">Golongan Saat Ini: {{ $employeeDetails['golongan'] ?? '—' }}</span>
                            @error('newGolongan') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-surface-700 mb-1">
                                Job Grade Baru
                            </label>
                            <input type="text" wire:model="form.newJobGrade" placeholder="Contoh: 11 s.d. 16"
                                class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                            <span class="text-[10px] text-surface-400 mt-1 block">Job Grade Saat Ini: {{ $employeeDetails['job_grade'] ?? '—' }}</span>
                            @error('newJobGrade') <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-4">
                <label class="block text-xs font-bold text-surface-700 mb-1.5">
                    Catatan / Dasar Pertimbangan <span class="text-danger">*</span>
                </label>
                <input type="text" wire:model="form.catatan" placeholder="Keterangan rotasi unit, promosi, dsb..."
                    class="w-full px-3.5 py-2.5 bg-white border border-surface-300 rounded-lg text-xs sm:text-sm font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                @error('catatan')
                    <p class="text-danger text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ═══════════════════ SUBMIT ACTIONS ═══════════════════ --}}
        <div class="flex items-center justify-between pt-2">
            <x-admin.button href="{{ route('admin.mutasi.index') }}" variant="secondary" icon="fas fa-arrow-left">
                Kembali ke Hub Mutasi
            </x-admin.button>

            <x-admin.button type="button" wire:click="openSummaryModal" variant="primary"
                wire:loading.attr="disabled"
                wire:target="openSummaryModal">
                <span wire:loading.remove wire:target="openSummaryModal">
                    <i class="fas fa-check-double text-xs mr-1"></i> Tinjau &amp; Sahkan Mutasi
                </span>
                <span wire:loading wire:target="openSummaryModal" class="flex items-center gap-2">
                    <i class="fas fa-spinner fa-spin text-xs"></i> Memvalidasi Data...
                </span>
            </x-admin.button>
        </div>

    </form>

    {{-- ═══════════════════ REVIEW SUMMARY MODAL (DESIGN.md) ═══════════════════ --}}
    @if ($showSummaryModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4 animate-fade-in">
            <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full overflow-hidden border border-surface-200 transition-all transform scale-100">
                {{-- Modal Header --}}
                <div class="bg-surface-50 px-6 py-4 border-b border-surface-200 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-sm font-bold shadow-inner">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-surface-900 leading-tight">Konfirmasi Pengesahan Mutasi</h3>
                            <p class="text-xs text-surface-500 mt-0.5">Harap periksa kembali rincian SK &amp; dampak formasi sebelum commit ke database.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeSummaryModal" class="text-surface-400 hover:text-surface-600 transition p-1.5 rounded-lg hover:bg-surface-200 cursor-pointer">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    {{-- Movement Flow Summary --}}
                    <div class="p-4 bg-primary-50/50 rounded-lg border border-primary-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-primary-800">
                                Jenis Mutasi: {{ ucwords(strtolower(str_replace('_', ' ', $jenisMutasi))) }}
                            </span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-primary-100 text-primary-800 border border-primary-200">
                                Status: {{ $statusJabatan }}
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3 bg-white rounded-lg border border-surface-200 space-y-1">
                                <span class="text-[10px] font-bold text-surface-400 uppercase tracking-wider block">Posisi Asal</span>
                                <div class="font-bold text-surface-900 text-sm">{{ $employeeDetails['nama'] ?? '—' }}</div>
                                <div class="text-surface-600 font-medium">{{ $employeeDetails['jabatan'] ?? '—' }}</div>
                                <div class="text-surface-500 text-[11px]">{{ $employeeDetails['unit_kerja'] ?? '—' }}</div>
                                <div class="text-[10px] font-mono text-surface-400 mt-1">Gol: {{ $employeeDetails['golongan'] ?? '—' }} | {{ $employeeDetails['rm_level'] ?? '—' }}</div>
                            </div>

                            <div class="p-3 bg-white rounded-lg border border-surface-200 space-y-1">
                                <span class="text-[10px] font-bold text-primary-600 uppercase tracking-wider block">Posisi Tujuan</span>
                                <div class="font-bold text-primary-900 text-sm">{{ $targetJabatan }}</div>
                                <div class="text-primary-800 font-medium">{{ $targetUnitKerja }}</div>
                                <div class="text-[10px] font-mono text-surface-600 mt-1">
                                    Gol Baru: {{ $newGolongan ?: ($employeeDetails['golongan'] ?? '—') }} 
                                    @if($newJobGrade) | Grade: {{ $newJobGrade }} @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Swap Details (if occupied) --}}
                    @if ($targetAnalysis && $targetAnalysis['type'] === 'occupied')
                        <div class="p-4 bg-earth-50 rounded-lg border border-earth-200 space-y-2">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-exchange-alt text-earth-700 text-xs"></i>
                                <span class="text-xs font-bold text-earth-900 uppercase tracking-wider">Dampak Tukar Jabatan (Swap)</span>
                            </div>
                            <p class="text-xs text-earth-800 leading-relaxed">
                                Pejabat <strong>{{ $targetAnalysis['occupant']['nama'] }}</strong> (NIK: {{ $targetAnalysis['occupant']['nik'] }}) secara otomatis akan dipindahkan menempati posisi <strong>{{ $employeeDetails['jabatan'] }}</strong> di <strong>{{ $employeeDetails['unit_kerja'] }}</strong>.
                            </p>
                        </div>
                    @endif

                    {{-- Legal Details --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs bg-surface-50 p-3.5 rounded-lg border border-surface-200">
                        <div>
                            <span class="text-[10px] text-surface-400 block font-medium">Nomor SK:</span>
                            <span class="font-mono font-bold text-surface-800 text-[11px]">{{ $nomorSk }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-surface-400 block font-medium">Tanggal SK:</span>
                            <span class="font-semibold text-surface-800">{{ \Carbon\Carbon::parse($tanggalSk)->format('d M Y') }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-surface-400 block font-medium">TMT Efektif:</span>
                            <span class="font-semibold text-primary-700">{{ \Carbon\Carbon::parse($tmtAwal)->format('d M Y') }}</span>
                        </div>
                    </div>

                    {{-- Dispensations Notice (if any) --}}
                    @if ($dispensasiTenure || $dispensasiPromosi)
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg space-y-1 text-xs">
                            <span class="font-bold text-amber-900 flex items-center gap-1.5">
                                <i class="fas fa-shield-alt text-amber-600"></i> Dispensasi Direksi Terlampir:
                            </span>
                            @if ($dispensasiTenure)
                                <p class="text-amber-800 text-[11px]">• Masa Dinas Unit: {{ $alasanDispensasiTenure }}</p>
                            @endif
                            @if ($dispensasiPromosi)
                                <p class="text-amber-800 text-[11px]">• Kriteria Promosi: {{ $alasanDispensasiPromosi }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- File SK notice --}}
                    @if ($fileSk)
                        <div class="text-xs text-surface-600 flex items-center gap-2">
                            <i class="fas fa-file-pdf text-danger"></i>
                            <span>Dokumen SK Digital: <strong>{{ $fileSk->getClientOriginalName() }}</strong> (Disimpan di Storage Privat Terproteksi)</span>
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="bg-surface-50 px-6 py-3.5 border-t border-surface-200 flex items-center justify-between">
                    <button type="button" wire:click="closeSummaryModal" class="px-4 py-2 bg-white border border-surface-300 rounded-lg text-xs font-semibold text-surface-700 hover:bg-surface-100 transition cursor-pointer">
                        Kembali / Ubah Data
                    </button>

                    <button type="button" wire:click="confirmSubmitMutasi"
                        wire:loading.attr="disabled"
                        class="px-5 py-2 bg-primary-600 text-white rounded-lg text-xs font-bold hover:bg-primary-700 transition shadow-sm flex items-center gap-2 cursor-pointer">
                        <span wire:loading.remove wire:target="confirmSubmitMutasi">
                            <i class="fas fa-check-circle"></i> Sahkan &amp; Eksekusi Mutasi
                        </span>
                        <span wire:loading wire:target="confirmSubmitMutasi" class="flex items-center gap-1.5">
                            <i class="fas fa-spinner fa-spin"></i> Menyimpan Perubahan...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
