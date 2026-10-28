    <!-- ── 2. LIVE TOPOLOGY MONITORING & FLOW VISUALIZER (CLEAN ENTERPRISE WHITE) ── -->
    <div id="topologyMonitoringCard" class="bg-white rounded-2xl border border-surface-200 shadow-xs p-6 space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-surface-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center font-bold text-base shadow-2xs">
                    <i class="fas fa-diagram-project" aria-hidden="true"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-surface-900 tracking-tight">Topologi Aliran Rantai Mutasi</h3>
                    <p class="text-xs text-surface-500">Deteksi otomatis siklus tertutup, pengisian formasi kosong, dan keterkaitan alih tugas</p>
                </div>
            </div>

            <!-- Topology Status Badge -->
            <div>
                @if ($chainAnalysis)
                    @if ($chainAnalysis['topology'] === 'cyclic')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-primary-50 text-primary-800 border border-primary-300">
                            <i class="fas fa-arrows-rotate fa-spin text-[11px]" style="animation-duration: 6s;" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @elseif ($chainAnalysis['topology'] === 'multi_cyclic')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-primary-50 text-primary-800 border border-primary-300">
                            <i class="fas fa-arrows-spin text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @elseif ($chainAnalysis['topology'] === 'domino')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-info-50 text-info-800 border border-info-200">
                            <i class="fas fa-forward-step text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @elseif ($chainAnalysis['topology'] === 'multi_domino')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-info-50 text-info-800 border border-info-200">
                            <i class="fas fa-network-wired text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @elseif ($chainAnalysis['topology'] === 'multi_mixed')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-earth-50 text-earth-800 border border-earth-300">
                            <i class="fas fa-sitemap text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @elseif ($chainAnalysis['topology'] === 'open')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-surface-50 text-surface-800 border border-surface-300">
                            <i class="fas fa-door-open text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @elseif ($chainAnalysis['topology'] === 'incomplete')
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-info-50 text-info-800 border border-info-200">
                            <i class="fas fa-clock-rotate-left text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-bold bg-danger-50 text-danger-800 border border-danger-200">
                            <i class="fas fa-triangle-exclamation text-[11px]" aria-hidden="true"></i>
                            <span>{{ $chainAnalysis['topology_label'] }}</span>
                        </span>
                    @endif
                @else
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-surface-100 text-surface-700 border border-surface-200">
                        <i class="fas fa-circle-notch fa-spin text-[10px]" aria-hidden="true"></i>
                        <span>Menunggu Input Pegawai</span>
                    </span>
                @endif
            </div>
        </div>

        <!-- 3 KPI Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
            <div class="p-3.5 rounded-xl border border-surface-200 bg-surface-50/60 flex items-center justify-between">
                <div>
                    <span class="text-xs text-surface-500 font-medium block">Total Pegawai Terlibat</span>
                    <span class="text-xl font-bold text-surface-900 mt-0.5 block">{{ count($nodes) }} Orang</span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-users" aria-hidden="true"></i>
                </div>
            </div>

            <div class="p-3.5 rounded-xl border border-surface-200 bg-surface-50/60 flex items-center justify-between">
                <div>
                    <span class="text-xs text-surface-500 font-medium block">Formasi Kosong Ditutup</span>
                    <span class="text-xl font-bold text-info-700 mt-0.5 block">
                        {{ count($chainAnalysis['closed_vacancies'] ?? []) }} Posisi
                    </span>
                </div>
                <div class="w-9 h-9 rounded-xl bg-info-100 text-info-700 flex items-center justify-center font-bold text-sm">
                    <i class="fas fa-door-closed" aria-hidden="true"></i>
                </div>
            </div>

            <div class="p-3.5 rounded-xl border border-surface-200 bg-surface-50/60 flex items-center justify-between">
                <div>
                    <span class="text-xs text-surface-500 font-medium block">Lowongan Baru Ditinggalkan</span>
                    <span class="text-xl font-bold {{ !empty($chainAnalysis['unfilled_vacancies']) ? 'text-warning-700' : 'text-primary-700' }} mt-0.5 block">
                        {{ count($chainAnalysis['unfilled_vacancies'] ?? []) }} Posisi
                    </span>
                </div>
                <div class="w-9 h-9 rounded-xl {{ !empty($chainAnalysis['unfilled_vacancies']) ? 'bg-warning-100 text-warning-700' : 'bg-primary-100 text-primary-700' }} flex items-center justify-center font-bold text-sm">
                    <i class="fas {{ !empty($chainAnalysis['unfilled_vacancies']) ? 'fa-door-open' : 'fa-check' }}" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <!-- Executive Flow Visualizer Header -->
        <div class="flex items-center justify-between pb-1">
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-surface-800 tracking-tight">Visualisasi Aliran Formasi</span>
                <span class="text-[10px] text-surface-500 hidden sm:inline">&bull; Presentasi Rapat Direksi &amp; Pemetaan Mutasi</span>
            </div>
            <span class="text-[11px] font-semibold text-primary-700 bg-primary-50 px-2.5 py-0.5 rounded-full border border-primary-200/60 flex items-center gap-1.5">
                <i class="fas fa-project-diagram text-[10px]" aria-hidden="true"></i>
                <span>Diagram Alur Eksekutif</span>
            </span>
        </div>

        @php
            $flowClusters = $this->getOrderedFlowClusters();
        @endphp

        <!-- ── UNIFIED COMPACT EXECUTIVE MULTI-CLUSTER FLOW DIAGRAM ── -->
        <div class="space-y-5">
            @forelse ($flowClusters as $cluster)
                <div class="bg-white rounded-2xl border border-surface-200 shadow-xs overflow-hidden">
                    <!-- Cluster Header Strip -->
                    <div class="px-5 py-3.5 bg-surface-50/90 border-b border-surface-200 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="w-8 h-8 rounded-xl {{ $cluster['type'] === 'cyclic' ? 'bg-primary-100 text-primary-800 border border-primary-200' : ($cluster['type'] === 'domino' ? 'bg-info-100 text-info-800 border border-info-200' : 'bg-surface-100 text-surface-700 border border-surface-200') }} flex items-center justify-center font-bold text-xs shadow-2xs">
                                <i class="fas {{ $cluster['type'] === 'cyclic' ? 'fa-arrows-rotate' : ($cluster['type'] === 'domino' ? 'fa-forward-step' : 'fa-network-wired') }}" aria-hidden="true"></i>
                            </span>
                            <div>
                                <h4 class="text-xs sm:text-sm font-bold text-surface-900 tracking-tight">{{ $cluster['title'] }}</h4>
                                <span class="text-[10px] text-surface-500 block">Subgraf alur mutasi mandiri dalam SK Direksi Bersama</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($cluster['is_zero_vacancy'])
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-primary-50 text-primary-800 border border-primary-300">
                                    <i class="fas fa-check-circle text-primary-600" aria-hidden="true"></i> Net Zero Vacancy
                                </span>
                            @endif
                            <span class="px-2.5 py-0.5 rounded-md text-[10px] font-bold bg-surface-100 text-surface-700 border border-surface-200">
                                {{ count($cluster['nodes']) }} Pegawai
                            </span>
                        </div>
                    </div>

                    <!-- Horizontal Flow Diagram for this Cluster -->
                    <div class="p-4 sm:p-5 bg-surface-50/70 overflow-x-auto shadow-inner relative"
                         style="background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px); background-size: 20px 20px;">
                        
                        <div class="flex items-center min-w-max py-2 px-1">
                            
                            <!-- Entry Vacancy Node (If Domino Open Loop has closed vacancies in this cluster) -->
                            @if ($cluster['type'] === 'domino' && !empty($cluster['closed_vacancies']))
                                <div class="flex items-center">
                                    <!-- Dashed Entry Card -->
                                    <div class="bg-info-50/90 border-2 border-dashed border-info-200 rounded-2xl p-3.5 w-56 shadow-2xs space-y-2 flex flex-col justify-between shrink-0">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-info-100 text-info-800 border border-info-200 uppercase tracking-wider inline-flex items-center gap-1">
                                                <i class="fas fa-door-closed text-[10px]" aria-hidden="true"></i> Titik Awal
                                            </span>
                                            <span class="text-[10px] font-bold text-info-700">Formasi Ditutup</span>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div class="font-bold text-xs text-info-800 leading-snug line-clamp-1">
                                                {{ $cluster['closed_vacancies'][0]['jabatan'] ?? 'Formasi Kosong' }}
                                            </div>
                                            <div class="text-[10px] text-info-700 font-medium truncate">
                                                {{ $cluster['closed_vacancies'][0]['unit_kerja'] ?? '' }}
                                            </div>
                                        </div>
                                        <div class="text-[10px] text-surface-500 pt-1.5 border-t border-info-100 flex items-center gap-1">
                                            <i class="fas fa-info-circle text-[10px] text-info" aria-hidden="true"></i>
                                            <span>Lowongan terisi oleh alur</span>
                                        </div>
                                    </div>

                                    <!-- Compact Connector Arrow -->
                                    <div class="w-14 sm:w-16 flex flex-col items-center justify-center shrink-0 relative px-0">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-info-700 bg-info-100/90 px-1.5 py-0.2 rounded-full border border-info-200 mb-1 shadow-2xs whitespace-nowrap">
                                            Diisi
                                        </span>
                                        <svg class="w-full h-4 overflow-visible" viewBox="0 0 60 16" fill="none" aria-hidden="true" focusable="false">
                                            <circle cx="2" cy="8" r="2" fill="#0284c7" />
                                            <path d="M 0 8 L 52 8" stroke="#0284c7" stroke-width="2" stroke-dasharray="4 2" />
                                            <polygon points="50,3.5 61,8 50,12.5" fill="#0284c7" />
                                        </svg>
                                    </div>
                                </div>
                            @endif

                            <!-- Employee Nodes in Directed Flow -->
                            @foreach ($cluster['nodes'] as $i => $n)
                                <div class="flex items-center">
                                    <!-- Compact Executive Employee Node Card -->
                                    <div class="bg-white rounded-2xl border border-surface-200 hover:border-primary-400 shadow-xs hover:shadow-sm transition-all duration-200 p-3.5 w-64 sm:w-68 space-y-2.5 shrink-0 flex flex-col justify-between">
                                        
                                        <!-- Node Top Bar: Slot # & RM Badge -->
                                        <div class="flex items-center justify-between pb-2 border-b border-surface-100">
                                            <div class="flex items-center gap-1.5">
                                                <span class="w-5 h-5 rounded-md bg-surface-900 text-white font-bold text-[10px] flex items-center justify-center shadow-2xs">
                                                    #{{ $n['slot_number'] }}
                                                </span>
                                                <span class="text-[11px] font-semibold text-surface-600" title="Nomor slot formulir">
                                                    Slot {{ $n['slot_number'] }}
                                                </span>
                                            </div>

                                            {{-- RM Level Badge per DOMAIN.md §10 & DESIGN.md --}}
                                            @php
                                                $rmStyle = match($n['current_rm']) {
                                                    'RM-1' => 'bg-orange-100 text-orange-800 border-orange-200',
                                                    'RM-2' => 'bg-sky-100 text-sky-800 border-sky-200',
                                                    'RM-3' => 'bg-purple-100 text-purple-800 border-purple-200',
                                                    'RM-4' => 'bg-surface-100 text-surface-700 border-surface-200',
                                                    default => 'bg-surface-100 text-surface-700 border-surface-200'
                                                };
                                            @endphp
                                            <span class="px-2 py-0.2 rounded text-[10px] font-bold border {{ $rmStyle }}">
                                                {{ $n['current_rm'] ?: '—' }}
                                            </span>
                                        </div>

                                        <!-- Employee Profile -->
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-9 h-9 rounded-xl {{ $n['link_type'] === 'chain' ? 'bg-primary-100 text-primary-800 border border-primary-300' : ($n['link_type'] === 'conflict' ? 'bg-danger-100 text-danger-800 border border-danger-200' : 'bg-primary-100 text-primary-800 border border-primary-200') }} flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
                                                @if (!empty($n['employee_nama']))
                                                    {{ strtoupper(substr($n['employee_nama'], 0, 2)) }}
                                                @else
                                                    <i class="fas fa-user text-[11px]" aria-hidden="true"></i>
                                                @endif
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h5 class="text-xs font-bold text-surface-900 leading-tight truncate" title="{{ $n['employee_nama'] ?: 'Belum Dipilih' }}">
                                                    {{ $n['employee_nama'] ?: '— Belum Dipilih —' }}
                                                </h5>
                                                <div class="text-[10px] font-mono text-surface-500 mt-0.5">
                                                    {{ $n['employee_nik'] ? 'NIK: ' . $n['employee_nik'] : 'Slot Kosong' }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modern Transition Pathway Block -->
                                        <div class="p-2.5 rounded-xl bg-surface-50/90 border border-surface-200/80 space-y-1.5">
                                            <!-- Posisi Asal -->
                                            <div class="space-y-0.5">
                                                <div class="text-[11px] font-semibold text-surface-700 leading-tight truncate" title="{{ $n['current_jabatan'] ?: '—' }}">
                                                    {{ $n['current_jabatan'] ?: '—' }}
                                                </div>
                                                <div class="text-[10px] text-surface-500 truncate" title="{{ $n['current_unit'] ?: '—' }}">
                                                    {{ $n['current_unit'] ?: '—' }}
                                                </div>
                                            </div>

                                            <!-- Transition Arrow with Mutation Type -->
                                            <div class="flex items-center gap-1.5 py-0.5 text-primary-600">
                                                <i class="fas fa-arrow-down text-[10px]" aria-hidden="true"></i>
                                                <span class="text-[10px] font-bold text-primary-700 uppercase tracking-wider bg-primary-50 px-1.5 py-0.2 rounded border border-primary-200/60 shadow-2xs truncate">
                                                    {{ str_replace('_', ' ', $n['jenis_mutasi'] ?? 'MUTASI') }}
                                                </span>
                                            </div>

                                            <!-- Formasi Baru -->
                                            <div class="space-y-0.5">
                                                <div class="text-[11px] font-bold text-primary-950 leading-tight truncate" title="{{ $n['target_jabatan'] ?: '—' }}">
                                                    {{ $n['target_jabatan'] ?: '—' }}
                                                </div>
                                                <div class="text-[10px] text-primary-700 font-semibold truncate" title="{{ $n['target_unit_kerja'] ?: '—' }}">
                                                    {{ $n['target_unit_kerja'] ?: '—' }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Node Interactive Action Links -->
                                        <div class="flex items-center justify-between pt-2 border-t border-surface-100 text-[11px]">
                                            @if (!empty($n['employee_nik']))
                                                <button type="button" wire:click="openTalentDrawer('{{ $n['employee_nik'] }}')"
                                                    class="text-primary-700 hover:text-primary-900 font-bold transition inline-flex items-center gap-1 cursor-pointer py-0.5 px-1.5 rounded hover:bg-primary-50"
                                                    title="Buka panel rekam jejak talenta 9-Box">
                                                    <i class="fas fa-chart-line text-[10px]" aria-hidden="true"></i>
                                                    <span>9-Box</span>
                                                </button>
                                            @else
                                                <span></span>
                                            @endif

                                            <button type="button" @click="document.getElementById('node-card-{{ $n['original_slot_index'] }}')?.scrollIntoView({behavior: 'smooth', block: 'center'})"
                                                class="text-surface-500 hover:text-surface-800 font-medium transition inline-flex items-center gap-1 cursor-pointer py-0.5 px-1.5 rounded hover:bg-surface-100"
                                                title="Lompat ke kartu formulir slot #{{ $n['slot_number'] }}">
                                                <span>Slot #{{ $n['slot_number'] }}</span>
                                                <i class="fas fa-arrow-down-long text-[10px]" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Compact Connector Pipeline to Next Node WITHIN THIS CLUSTER -->
                                    @if ($i < count($cluster['nodes']) - 1)
                                        <div class="w-14 sm:w-16 flex flex-col items-center justify-center shrink-0 relative px-0">
                                            @if ($n['link_type'] === 'chain')
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-primary-50 text-primary-800 border border-primary-300 shadow-2xs whitespace-nowrap mb-1" title="Mengisi posisi {{ $n['occupant_nama'] }}">
                                                    <i class="fas fa-arrows-rotate text-[10px] text-primary-600" aria-hidden="true"></i>
                                                    <span>Isi</span>
                                                </span>
                                            @elseif ($n['link_type'] === 'vacant')
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-info-50 text-info-800 border border-info-200 shadow-2xs whitespace-nowrap mb-1" title="Menutup Formasi Kosong">
                                                    <i class="fas fa-door-open text-[10px] text-info" aria-hidden="true"></i>
                                                    <span>Tutup</span>
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-surface-100 text-surface-700 border border-surface-300 shadow-2xs whitespace-nowrap mb-1">
                                                    <span>Alur</span>
                                                </span>
                                            @endif
                                            
                                            @php
                                                $strokeColor = $n['link_type'] === 'chain' ? '#10b981' : ($n['link_type'] === 'vacant' ? '#0284c7' : '#94a3b8');
                                                $fillColor = $n['link_type'] === 'chain' ? '#059669' : ($n['link_type'] === 'vacant' ? '#0284c7' : '#64748b');
                                            @endphp
                                            <svg class="w-full h-4 overflow-visible" viewBox="0 0 60 16" fill="none" aria-hidden="true" focusable="false">
                                                <circle cx="2" cy="8" r="2" fill="{{ $fillColor }}" />
                                                <path d="M 0 8 L 52 8" stroke="{{ $strokeColor }}" stroke-width="2" stroke-dasharray="4 2" />
                                                <polygon points="50,3.5 61,8 50,12.5" fill="{{ $fillColor }}" />
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            <!-- Exit Vacancy Node (If Domino Open Loop leaves an unfilled vacancy in this cluster) -->
                            @if ($cluster['type'] === 'domino' && !empty($cluster['unfilled_vacancies']))
                                <div class="flex items-center">
                                    <!-- Compact Arrow to Final Vacancy -->
                                    <div class="w-14 sm:w-16 flex flex-col items-center justify-center shrink-0 relative px-0">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-warning-700 bg-warning-100/90 px-1.5 py-0.2 rounded-full border border-warning-200 mb-1 shadow-2xs whitespace-nowrap">
                                            Lepas
                                        </span>
                                        <svg class="w-full h-4 overflow-visible" viewBox="0 0 60 16" fill="none" aria-hidden="true" focusable="false">
                                            <circle cx="2" cy="8" r="2" fill="#d97706" />
                                            <path d="M 0 8 L 52 8" stroke="#f59e0b" stroke-width="2" stroke-dasharray="4 2" />
                                            <polygon points="50,3.5 61,8 50,12.5" fill="#d97706" />
                                        </svg>
                                    </div>

                                    <div class="bg-warning-50/90 border-2 border-dashed border-warning-200 rounded-2xl p-3.5 w-56 shadow-2xs space-y-2 flex flex-col justify-between shrink-0">
                                        <div class="flex items-center justify-between">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-warning-100 text-warning-800 border border-warning-200 uppercase tracking-wider inline-flex items-center gap-1">
                                                <i class="fas fa-door-open text-[10px]" aria-hidden="true"></i> Lowongan Baru
                                            </span>
                                            <span class="text-[10px] font-bold text-warning-700">Perlu Diisi</span>
                                        </div>
                                        <div class="space-y-0.5">
                                            <div class="font-bold text-xs text-warning-800 leading-snug line-clamp-1">
                                                {{ $cluster['unfilled_vacancies'][0]['jabatan'] ?? 'Posisi Ditinggalkan' }}
                                            </div>
                                            <div class="text-[10px] text-warning-700 font-medium truncate">
                                                {{ $cluster['unfilled_vacancies'][0]['unit_kerja'] ?? '' }}
                                            </div>
                                        </div>
                                        <div class="text-[10px] text-surface-500 pt-1.5 border-t border-warning-100 flex items-center gap-1">
                                            <i class="fas fa-exclamation-triangle text-[10px] text-warning-700" aria-hidden="true"></i>
                                            <span>Menjadi lowongan di ujung rantai</span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>

                        <!-- Cluster Cyclic Loop Return Pathway -->
                        @if ($cluster['type'] === 'cyclic' && !empty($cluster['loopback']))
                            <div class="mt-4 pt-3.5 border-t border-primary-200/90 flex items-center justify-between gap-3 bg-primary-50/90 rounded-xl p-3 border border-primary-300 shadow-2xs">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-primary-600 text-white flex items-center justify-center text-xs shrink-0 shadow-2xs">
                                        <i class="fas fa-arrows-rotate fa-spin" style="animation-duration: 8s;" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-primary-950 flex items-center gap-1.5">
                                            <span>Siklus Tertutup Sempurna ({{ $cluster['type_label'] }})</span>
                                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-primary-200/80 text-primary-900">100% Balanced</span>
                                        </h4>
                                        <p class="text-[11px] text-primary-800 mt-0.5 leading-tight">
                                            Posisi awal <strong>{{ $cluster['loopback']['to_nama'] }}</strong> ({{ $cluster['loopback']['to_jabatan'] }} di {{ $cluster['loopback']['to_unit'] }}) secara langsung diisi kembali oleh <strong>{{ $cluster['loopback']['from_nama'] }}</strong>. Alur siklus ini terhubung bulat mandiri tanpa menyisakan kekosongan formasi.
                                        </p>
                                    </div>
                                </div>
                                <div class="hidden sm:inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-white border border-primary-300 text-primary-800 font-bold text-[11px] shrink-0 shadow-2xs">
                                    <i class="fas fa-check-circle text-primary-600 text-[10px]" aria-hidden="true"></i>
                                    <span>Zero Vacancy</span>
                                </div>
                            </div>
                        @endif

                    </div>
                </div>
            @empty
                <div class="p-8 text-center bg-white rounded-2xl border border-surface-200">
                    <p class="text-xs text-surface-500">Pilih minimal 2 pegawai untuk melihat visualisasi topologi alur mutasi.</p>
                </div>
            @endforelse
        </div>

        <!-- Errors & Warnings List -->
        @if ($chainAnalysis && (!empty($chainAnalysis['errors']) || !empty($chainAnalysis['warnings'])))
            <div class="space-y-2 pt-1">
                @if ($chainAnalysis['topology'] === 'incomplete')
                    @foreach ($chainAnalysis['errors'] as $msg)
                        <div class="flex items-start gap-2.5 text-xs text-info-800 bg-info-50 px-3.5 py-2.5 rounded-xl border border-info-200">
                            <i class="fas fa-circle-info text-info mt-0.5 shrink-0 text-sm" aria-hidden="true"></i>
                            <span class="font-medium leading-relaxed">{{ $msg }}</span>
                        </div>
                    @endforeach
                @else
                    @foreach ($chainAnalysis['errors'] as $err)
                        <div class="flex items-start gap-2.5 text-xs text-danger-800 bg-danger-50 px-3.5 py-2.5 rounded-xl border border-danger-200">
                            <i class="fas fa-circle-xmark text-danger mt-0.5 shrink-0 text-sm" aria-hidden="true"></i>
                            <span class="font-medium leading-relaxed">{{ $err }}</span>
                        </div>
                    @endforeach
                @endif
                @foreach ($chainAnalysis['warnings'] as $warn)
                    <div class="flex items-start gap-2.5 text-xs text-warning-800 bg-warning-50 px-3.5 py-2.5 rounded-xl border border-warning-200">
                        <i class="fas fa-triangle-exclamation text-warning-700 mt-0.5 shrink-0 text-sm" aria-hidden="true"></i>
                        <span class="font-medium leading-relaxed">{{ $warn }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
