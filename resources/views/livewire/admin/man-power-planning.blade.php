<div class="space-y-6">

    <!-- MPP Executive KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Total Formasi Baku -->
        <div class="bg-white rounded-lg border border-surface-200 p-4 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Standar Formasi Baku</p>
                    <p class="text-2xl lg:text-3xl font-bold text-surface-900 tabular-nums font-mono">{{ number_format($stats['total_formasi']) }}</p>
                    <p class="text-xs text-primary-700 font-medium">Headcount ceiling disahkan</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-primary-50 text-primary-700 flex items-center justify-center text-xl shrink-0 border border-primary-200">
                    <i class="fas fa-sitemap"></i>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-primary-600"></div>
        </div>

        <!-- Card 2: Realisasi Terisi -->
        <div class="bg-white rounded-lg border border-surface-200 p-4 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Realisasi Terisi</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl lg:text-3xl font-bold text-surface-900 tabular-nums font-mono">{{ number_format($stats['total_realisasi']) }}</span>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                            {{ $stats['fill_rate'] }}%
                        </span>
                    </div>
                    <p class="text-xs text-surface-500">Karyawan aktif menjabat</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center text-xl shrink-0 border border-emerald-200">
                    <i class="fas fa-users-viewfinder"></i>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-emerald-600"></div>
        </div>

        <!-- Card 3: Proyeksi Pensiun -->
        <div class="bg-white rounded-lg border border-surface-200 p-4 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Proyeksi Pensiun</p>
                    <p class="text-2xl lg:text-3xl font-bold text-amber-700 tabular-nums font-mono">{{ number_format($stats['total_pensiun']) }}</p>
                    <p class="text-xs text-amber-600 font-medium">BUP periode {{ $stats['planning_year'] }} – {{ $stats['planning_year_next'] }}</p>
                </div>
                <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center text-xl shrink-0 border border-amber-200">
                    <i class="fas fa-user-clock"></i>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 bg-amber-500"></div>
        </div>

        <!-- Card 4: Kebutuhan Bersih / Status Pemenuhan -->
        <div class="bg-white rounded-lg border border-surface-200 p-4 shadow-sm relative overflow-hidden group">
            <div class="flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Kebutuhan Bersih {{ $stats['planning_year'] }}</p>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl lg:text-3xl font-bold {{ $stats['total_kebutuhan'] <= 0 ? 'text-emerald-700' : 'text-danger' }} tabular-nums font-mono">
                            {{ $stats['total_kebutuhan'] > 0 ? '+' : '' }}{{ $stats['total_kebutuhan'] }}
                        </span>
                        @if($stats['total_kebutuhan'] <= 0)
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800">
                                Surplus Pasokan
                            </span>
                        @else
                            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-red-100 text-danger">
                                Defisit Formasi
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-surface-500">Pasokan rekrutmen vs gap</p>
                </div>
                <div class="w-12 h-12 rounded-lg {{ $stats['total_kebutuhan'] <= 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-danger border-red-200' }} flex items-center justify-center text-xl shrink-0 border">
                    <i class="fas fa-scale-balanced"></i>
                </div>
            </div>
            <div class="absolute bottom-0 inset-x-0 h-1 {{ $stats['total_kebutuhan'] <= 0 ? 'bg-emerald-600' : 'bg-danger' }}"></div>
        </div>
    </div>

    <!-- Main Enterprise MPP Matrix Table with Integrated Header Toolbar -->
    <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
        <!-- Tier 1: Title, Enterprise Scope Badge & Primary Actions -->
        <div class="px-5 py-3.5 bg-white border-b border-surface-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block"></span>
                    <h3 class="text-sm font-bold text-surface-900 tracking-tight">
                        Matriks Formasi &amp; Kebutuhan Tenaga Kerja (MPP)
                    </h3>
                </div>
                <p class="text-xs text-surface-500 mt-1">Pemetaan kekuatan formasi manajerial (RM-1, RM-2, RM-3) berdasarkan bidang fungsional operasional.</p>
            </div>

            <!-- Action Buttons Cluster -->
            <div class="flex items-center gap-2 shrink-0">
                <!-- Export Excel / CSV -->
                <button type="button" wire:click="exportCsv"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-300/80 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 hover:border-emerald-400 text-xs font-semibold transition-all shadow-2xs">
                    <i class="fas fa-file-excel text-emerald-700"></i>
                    <span>Export Excel / CSV</span>
                </button>

                <!-- Cetak -->
                <button type="button" onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-surface-900 text-xs font-medium transition-all shadow-2xs">
                    <i class="fas fa-print text-surface-400"></i>
                    <span>Cetak</span>
                </button>
            </div>
        </div>

        <!-- Tier 2: Sub-Header Filter Strip (Source Mode + Year & Bidang Filters) -->
        <div class="px-5 py-2.5 bg-surface-50 border-b border-surface-200 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Sisi Kiri: Segmented Source Selector -->
            <div class="inline-flex items-center p-0.5 bg-surface-200/70 rounded-lg border border-surface-200 shadow-2xs self-start md:self-auto">
                <button type="button" wire:click="setSourceMode('baseline')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all duration-150 {{ $sourceMode === 'baseline' ? 'bg-white text-emerald-900 shadow-xs ring-1 ring-black/5 font-bold' : 'text-surface-600 hover:text-surface-900' }}">
                    <i class="fas fa-file-shield text-xs {{ $sourceMode === 'baseline' ? 'text-emerald-700' : 'text-surface-400' }}"></i>
                    <span>Formasi Baku Korporasi</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-mono {{ $sourceMode === 'baseline' ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-200 text-surface-600' }}">444</span>
                </button>
                <button type="button" wire:click="setSourceMode('live')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold transition-all duration-150 {{ $sourceMode === 'live' ? 'bg-white text-emerald-900 shadow-xs ring-1 ring-black/5 font-bold' : 'text-surface-600 hover:text-surface-900' }}">
                    <i class="fas fa-rotate text-xs {{ $sourceMode === 'live' ? 'text-emerald-700' : 'text-surface-400' }}"></i>
                    <span>Sync Karyawan Database</span>
                </button>
            </div>

            <!-- Sisi Kanan: Tahun & Bidang Filter Cluster -->
            <div class="flex items-center gap-2.5 self-start md:self-auto">
                <!-- Filter Tahun Perencanaan -->
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white border border-surface-200 text-xs shadow-2xs">
                    <i class="fas fa-calendar-alt text-surface-400 text-xs"></i>
                    <span class="text-surface-500 font-medium">Tahun:</span>
                    <select wire:model.live="planningYear" class="bg-transparent font-bold text-surface-900 text-xs focus:outline-hidden cursor-pointer">
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}">{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="h-4 w-px bg-surface-300 hidden sm:block"></div>

                <!-- Filter Bidang Fungsional -->
                <div class="inline-flex items-center p-0.5 rounded-md bg-surface-200/70 border border-surface-200 text-xs">
                    @foreach(['ALL' => 'Semua', 'KEU' => 'KEU', 'TAN' => 'TAN', 'TEK' => 'TEK', 'UMU' => 'UMU'] as $code => $lbl)
                        <button type="button" wire:click="setFilterBidang('{{ $code }}')"
                            class="px-2.5 py-1 text-xs rounded font-medium transition-all {{ $filterBidang === $code ? 'bg-primary-700 text-white font-bold shadow-xs' : 'text-surface-600 hover:text-surface-900 hover:bg-white/60' }}">
                            {{ $lbl }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[900px]">
                <!-- Level 1 Header: Groups -->
                <thead>
                    <tr class="border-b border-surface-200 select-none">
                        <th rowspan="2" class="w-12 px-3 py-3 text-center font-bold text-surface-700 bg-surface-100 border-r border-surface-200">
                            No.
                        </th>
                        <th rowspan="2" class="min-w-[200px] px-4 py-3 text-center font-bold text-surface-800 bg-surface-100 border-r border-surface-200">
                            URAIAN INDIKATOR
                        </th>

                        <!-- RM-1 Group Header -->
                        <th colspan="{{ $filterBidang === 'ALL' ? 5 : 2 }}" class="px-3 py-2 text-center font-bold text-orange-950 bg-orange-100/90 border-r border-orange-200 {{ $filterBidang !== 'ALL' ? 'min-w-[220px]' : '' }}">
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <span>RM-1</span>
                                <span class="text-[10px] font-normal text-orange-800">(Pimpinan Puncak / Gol. IV)</span>
                            </div>
                        </th>

                        <!-- RM-2 Group Header -->
                        <th colspan="{{ $filterBidang === 'ALL' ? 5 : 2 }}" class="px-3 py-2 text-center font-bold text-sky-950 bg-sky-100/90 border-r border-sky-200 {{ $filterBidang !== 'ALL' ? 'min-w-[220px]' : '' }}">
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <span>RM-2</span>
                                <span class="text-[10px] font-normal text-sky-800">(Manajer / Kabag / Gol. III-C–D)</span>
                            </div>
                        </th>

                        <!-- RM-3 Group Header -->
                        <th colspan="{{ $filterBidang === 'ALL' ? 5 : 2 }}" class="px-3 py-2 text-center font-bold text-purple-950 bg-purple-100/90 border-r border-purple-200 {{ $filterBidang !== 'ALL' ? 'min-w-[240px]' : '' }}">
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <span>RM-3</span>
                                <span class="text-[10px] font-normal text-purple-800">(Asisten / Staf Pratama / Gol. III-A–B)</span>
                            </div>
                        </th>

                        <!-- TOTAL Column Header -->
                        <th rowspan="2" class="w-20 px-3 py-3 text-center font-bold text-white bg-primary-800">
                            TOTAL
                        </th>
                    </tr>

                    <!-- Level 2 Header: Functional Columns per RM -->
                    <tr class="border-b-2 border-surface-300 text-[11px] font-bold select-none text-surface-700">
                        @foreach(['RM-1' => 'bg-orange-50/80', 'RM-2' => 'bg-sky-50/80', 'RM-3' => 'bg-purple-50/80'] as $rm => $bg)
                            @if($filterBidang === 'ALL')
                                <th class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">KEU</th>
                                <th class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">TAN</th>
                                <th class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">TEK</th>
                                <th class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">UMU</th>
                                <th class="w-16 px-2 py-2 text-center {{ $bg }} font-extrabold text-surface-900 border-r border-surface-300">JUMLAH</th>
                            @else
                                <th class="px-3 py-2 text-center {{ $bg }} border-r border-surface-200 font-bold">{{ $filterBidang }}</th>
                                <th class="px-3 py-2 text-center {{ $bg }} font-extrabold text-surface-900 border-r border-surface-300">JUMLAH</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>

                <!-- Table Body -->
                <tbody class="divide-y divide-surface-200">
                    @foreach($matrix as $row)
                        @php
                            $rowHighlight = match($row['highlight']) {
                                'formasi' => 'bg-emerald-50/30 font-semibold',
                                'realisasi' => 'bg-blue-50/30 font-semibold',
                                'kebutuhan' => 'bg-amber-50/40 font-bold border-y-2 border-amber-300',
                                'supply' => 'bg-purple-50/20',
                                default => 'hover:bg-surface-50/70'
                            };
                        @endphp
                        <tr class="{{ $rowHighlight }} transition duration-150 group">
                            <!-- Column 1: No -->
                            <td class="px-3 py-2.5 text-center font-mono text-surface-500 border-r border-surface-200">
                                {{ $row['no'] }}
                            </td>

                            <!-- Column 2: Indicator Label -->
                            <td class="px-4 py-2.5 text-left text-surface-800 border-r border-surface-200">
                                <span class="{{ $row['highlight'] === 'kebutuhan' ? 'text-surface-900 font-bold' : '' }}">
                                    {{ $row['label'] }}
                                </span>
                            </td>

                            <!-- RM-1, RM-2, RM-3 Cells -->
                            @foreach(['RM-1', 'RM-2', 'RM-3'] as $rm)
                                @php
                                    $levelData = $row['levels'][$rm] ?? ['fields' => [], 'subtotal' => 0];
                                @endphp

                                @if($filterBidang === 'ALL')
                                    @foreach(['KEU', 'TAN', 'TEK', 'UMU'] as $field)
                                        @php
                                            $val = $levelData['fields'][$field] ?? 0;
                                            $isClickable = ($val !== 0 && $val !== '0' && $val !== '-' && $val !== null);
                                        @endphp
                                        @if($isClickable)
                                            <td wire:click="openDrilldown('{{ $rm }}', '{{ $field }}', '{{ $row['label'] }}')"
                                                class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200 cursor-pointer hover:bg-emerald-50 hover:text-emerald-800 transition"
                                                title="Klik untuk melihat rincian personil">
                                                @if($row['highlight'] === 'kebutuhan')
                                                    <span class="{{ $val < 0 ? 'text-emerald-700 font-bold' : 'text-amber-800 font-bold' }}">
                                                        {{ $val }}
                                                    </span>
                                                @else
                                                    <span class="text-surface-800 font-medium underline decoration-dotted decoration-surface-300 underline-offset-2">{{ $val }}</span>
                                                @endif
                                            </td>
                                        @else
                                            <td class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200 select-none">
                                                <span class="text-surface-300">-</span>
                                            </td>
                                        @endif
                                    @endforeach
                                @else
                                    @php
                                        $val = $levelData['fields'][$filterBidang] ?? 0;
                                        $isClickable = ($val !== 0 && $val !== '0' && $val !== '-' && $val !== null);
                                    @endphp
                                    @if($isClickable)
                                        <td wire:click="openDrilldown('{{ $rm }}', '{{ $filterBidang }}', '{{ $row['label'] }}')"
                                            class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200 cursor-pointer hover:bg-emerald-50 hover:text-emerald-800 transition"
                                            title="Klik untuk melihat rincian personil">
                                            @if($row['highlight'] === 'kebutuhan')
                                                <span class="{{ $val < 0 ? 'text-emerald-700 font-bold' : 'text-amber-800 font-bold' }}">
                                                    {{ $val }}
                                                </span>
                                            @else
                                                <span class="text-surface-800 font-medium underline decoration-dotted decoration-surface-300 underline-offset-2">{{ $val }}</span>
                                            @endif
                                        </td>
                                    @else
                                        <td class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200 select-none">
                                            <span class="text-surface-300">-</span>
                                        </td>
                                    @endif
                                @endif

                                <!-- Subtotal JUMLAH -->
                                <td class="px-2 py-2 text-center font-mono font-bold tabular-nums bg-surface-100/60 text-surface-900 border-r border-surface-300">
                                    @if($levelData['subtotal'] === 0 || $levelData['subtotal'] === '0')
                                        <span class="font-bold text-surface-900">0</span>
                                    @elseif($row['highlight'] === 'kebutuhan')
                                        <span class="inline-block px-1.5 py-0.5 rounded text-xs font-bold {{ $levelData['subtotal'] < 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                            {{ $levelData['subtotal'] }}
                                        </span>
                                    @else
                                        <span class="font-bold text-surface-900">{{ $levelData['subtotal'] }}</span>
                                    @endif
                                </td>
                            @endforeach

                            <!-- Grand TOTAL Column -->
                            <td class="px-3 py-2 text-center font-mono font-extrabold tabular-nums bg-surface-100 text-surface-900">
                                @if($row['highlight'] === 'kebutuhan')
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold {{ $row['grand_total'] < 0 ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">
                                        {{ $row['grand_total'] }}
                                    </span>
                                @elseif($row['grand_total'] === 0 || $row['grand_total'] === '0')
                                    <span class="font-extrabold text-surface-900">0</span>
                                @else
                                    <span class="font-extrabold text-surface-900 {{ $row['highlight'] === 'formasi' ? 'text-primary-800' : '' }}">
                                        {{ $row['grand_total'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Matrix Footer Legend & Guide -->
        <div class="px-5 py-4 bg-surface-50 border-t border-surface-200 grid grid-cols-1 md:grid-cols-3 gap-4 text-xs text-surface-600">
            <!-- Legend 1 -->
            <div class="space-y-1">
                <p class="font-bold text-surface-800 uppercase tracking-wider text-[10px]">Tingkat Golongan Jabatan (RM):</p>
                <ul class="space-y-0.5 text-surface-600 text-[11px]">
                    <li><strong class="text-orange-900">RM-1:</strong> Pimpinan Puncak / General Manager (Gol. IV)</li>
                    <li><strong class="text-sky-900">RM-2:</strong> Manajer Kebun / Kepala Bagian / Askep (Gol. III-C–D)</li>
                    <li><strong class="text-purple-900">RM-3:</strong> Asisten Afdeling / Pabrik / KTU / Staf (Gol. III-A–B)</li>
                </ul>
            </div>

            <!-- Legend 2 -->
            <div class="space-y-1">
                <p class="font-bold text-surface-800 uppercase tracking-wider text-[10px]">Bidang Fungsional:</p>
                <ul class="space-y-0.5 text-surface-600 text-[11px]">
                    <li><strong>KEU:</strong> Keuangan, Akuntansi &amp; Perbendaharaan</li>
                    <li><strong>TAN:</strong> Tanaman, Agronomi &amp; Afdeling Kebun</li>
                    <li><strong>TEK:</strong> Teknik, Pengolahan Pabrik &amp; Bengkel</li>
                    <li><strong>UMU:</strong> Umum, SDM, Pengadaan, Legal &amp; Sekretariat</li>
                </ul>
            </div>

            <!-- Legend 3 -->
            <div class="space-y-1">
                <p class="font-bold text-surface-800 uppercase tracking-wider text-[10px]">Keterangan Formula Kebutuhan:</p>
                <p class="text-[11px] leading-relaxed text-surface-600">
                    <span class="font-semibold text-surface-700">Kebutuhan = (Formasi - Realisasi + Pensiun) - Total Pasokan (CKP + Scouting + RBB)</span>.
                    Angka bertanda minus (<span class="text-emerald-700 font-bold">-</span>) menandakan kondisi <strong>surplus pasokan talenta</strong> di atas batas kebutuhan minimal.
                </p>
            </div>
        </div>
    </div>

    <!-- Slide-Over Drawer: Modern Employee Drilldown Panel -->
    @if($showDrilldown)
        <div class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <!-- Background Backdrop: Soft semi-transparent overlay without blur -->
            <div class="fixed inset-0 bg-slate-900/30 transition-opacity duration-300" wire:click="closeDrilldown"></div>

            <div class="fixed inset-y-0 right-0 max-w-full flex pl-6 sm:pl-10">
                <div class="w-screen max-w-md sm:max-w-lg bg-white shadow-2xl border-l border-surface-200 flex flex-col" x-data="{ search: '' }">
                    <!-- Drawer Header & Controls -->
                    <div class="px-5 py-4 bg-surface-50 border-b border-surface-200 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 inline-block"></span>
                                    <h3 class="text-sm font-bold text-surface-900" id="slide-over-title">
                                        {{ $drilldownTitle }}
                                    </h3>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-100 text-emerald-800">
                                        {{ count($drilldownEmployees) }} Personil
                                    </span>
                                </div>
                                
                                <!-- Context Badges -->
                                <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <span class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-orange-100 text-orange-800 border border-orange-200">
                                        {{ $drilldownRm }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-sky-100 text-sky-800 border border-sky-200">
                                        Bidang {{ $drilldownBidang }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] bg-surface-200/80 text-surface-800">
                                        {{ $drilldownIndicator }}
                                    </span>
                                </div>
                            </div>

                            <button type="button" wire:click="closeDrilldown"
                                class="w-8 h-8 rounded-lg text-surface-400 hover:text-surface-700 hover:bg-surface-200/60 flex items-center justify-center transition"
                                title="Tutup Panel">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>

                        <!-- Instant Personnel Search Input -->
                        @if(!empty($drilldownEmployees))
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-surface-400 text-xs"></i>
                                <input x-model="search" type="text"
                                    placeholder="Cari nama, NIK, atau jabatan di panel..."
                                    class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-surface-200 bg-white placeholder:text-surface-400 focus:outline-hidden focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition shadow-2xs">
                            </div>
                        @endif

                        <!-- Formula Calculation Breakdown Card (When clicking on Kebutuhan Rows) -->
                        @if($drilldownFormula)
                            <div class="bg-amber-50/70 border border-amber-200/90 rounded-xl p-3 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-amber-950 flex items-center gap-1.5">
                                        <i class="fas fa-calculator text-amber-600"></i>
                                        Rincian Formula Kebutuhan Bersih
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $drilldownFormula['is_surplus'] ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-danger' }}">
                                        {{ $drilldownFormula['is_surplus'] ? 'Surplus Pasokan (-)' : 'Defisit Formasi (+)' }}
                                    </span>
                                </div>
                                
                                <!-- Formula Math Grid -->
                                <div class="grid grid-cols-5 gap-1 text-center text-[11px] bg-white rounded-lg p-2 border border-amber-200/60 font-mono shadow-2xs">
                                    <div class="space-y-0.5">
                                        <p class="text-[9px] text-surface-500 font-sans uppercase">Formasi</p>
                                        <p class="font-bold text-surface-900">{{ $drilldownFormula['formasi'] }}</p>
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-[9px] text-surface-500 font-sans uppercase">- Realisasi</p>
                                        <p class="font-bold text-blue-700">{{ $drilldownFormula['realisasi'] }}</p>
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-[9px] text-surface-500 font-sans uppercase">+ Pensiun</p>
                                        <p class="font-bold text-amber-700">{{ $drilldownFormula['pensiun'] }}</p>
                                    </div>
                                    <div class="space-y-0.5">
                                        <p class="text-[9px] text-surface-500 font-sans uppercase">- Pasokan</p>
                                        <p class="font-bold text-purple-700">{{ $drilldownFormula['pasokan'] }}</p>
                                    </div>
                                    <div class="space-y-0.5 border-l border-amber-200 bg-amber-50/70 rounded">
                                        <p class="text-[9px] text-amber-800 font-sans uppercase font-bold">= Kebutuhan</p>
                                        <p class="font-extrabold {{ $drilldownFormula['is_surplus'] ? 'text-emerald-700' : 'text-danger' }}">
                                            {{ $drilldownFormula['kebutuhan'] }}
                                        </p>
                                    </div>
                                </div>
                                <p class="text-[10px] text-amber-900/80 leading-tight">
                                    *Daftar di bawah merupakan <strong>pemangku jabatan aktif saat ini (incumbents)</strong> pada formasi ini.
                                </p>
                            </div>
                        @endif
                    </div>

                    <!-- Drawer Body: Scrollable Rich Employee Cards List -->
                    <div class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-3 bg-surface-50/40">
                        @if(empty($drilldownEmployees))
                            <div class="text-center py-12 px-4 bg-white rounded-xl border border-surface-200">
                                <div class="w-12 h-12 rounded-full bg-surface-100 text-surface-400 flex items-center justify-center mx-auto mb-3 text-lg">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <h4 class="text-sm font-bold text-surface-800">Tidak ada personil lokal terdata</h4>
                                <p class="text-xs text-surface-500 max-w-xs mx-auto mt-1 leading-relaxed">
                                    Angka pada sel ini bersumber dari rekapitulasi formasi dokumen holding PTPN (baseline korporasi).
                                </p>
                            </div>
                        @else
                            @foreach($drilldownEmployees as $emp)
                                <div x-show="search === '' || $el.innerText.toLowerCase().includes(search.toLowerCase())"
                                    class="bg-white rounded-xl border border-surface-200 p-4 shadow-2xs hover:shadow-xs transition duration-150 group">
                                    <div class="flex items-start justify-between gap-3">
                                        <!-- Employee Info with Avatar Initial -->
                                        <div class="flex items-start gap-3">
                                            <div class="w-10 h-10 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center justify-center font-bold text-sm shrink-0">
                                                {{ strtoupper(substr($emp['nama'], 0, 2)) }}
                                            </div>
                                            <div class="space-y-0.5">
                                                <h4 class="text-xs font-bold text-surface-900 group-hover:text-primary-700 transition">
                                                    {{ $emp['nama'] }}
                                                </h4>
                                                <p class="text-[11px] font-mono text-surface-500 flex items-center gap-1.5">
                                                    <span>NIK: {{ $emp['nik'] }}</span>
                                                    <span class="inline-block w-1 h-1 rounded-full bg-surface-300"></span>
                                                    <span class="font-medium text-emerald-700">{{ $emp['status_karyawan'] }}</span>
                                                </p>
                                            </div>
                                        </div>

                                        @if(!empty($emp['nik']))
                                            <a href="{{ route('admin.employees.show', $emp['nik']) }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-600 text-emerald-800 hover:text-white border border-emerald-200/80 hover:border-emerald-600 text-xs font-semibold transition-all shadow-2xs group/btn shrink-0"
                                                title="Buka Profil Lengkap Karyawan (Tab Baru)">
                                                <span>Lihat Profil</span>
                                                <i class="fas fa-arrow-up-right-from-square text-[10px] group-hover/btn:translate-x-0.5 group-hover/btn:-translate-y-0.5 transition-transform"></i>
                                            </a>
                                        @endif
                                    </div>

                                    <!-- Divider -->
                                    <div class="my-2.5 border-t border-surface-100"></div>

                                    <!-- Job & Unit -->
                                    <div class="text-xs space-y-1">
                                        <div class="flex items-center gap-1.5 text-surface-800 font-medium">
                                            <i class="fas fa-briefcase text-surface-400 text-[11px] w-3.5"></i>
                                            <span class="truncate">{{ $emp['jabatan'] }}</span>
                                        </div>
                                        <div class="flex items-center gap-1.5 text-surface-500 text-[11px]">
                                            <i class="fas fa-location-dot text-surface-400 text-[11px] w-3.5"></i>
                                            <span class="truncate">{{ $emp['unit_kerja'] }}</span>
                                        </div>
                                    </div>

                                    <!-- Badges: Golongan & Pensiun -->
                                    <div class="mt-3 pt-2.5 border-t border-surface-100 flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-1 text-surface-600">
                                            <span class="text-surface-400">Golongan:</span>
                                            <span class="font-bold font-mono px-2 py-0.5 rounded-md bg-surface-100 text-surface-800 border border-surface-200">
                                                {{ $emp['golongan'] ?? '—' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center gap-1 text-surface-600">
                                            <i class="fas fa-calendar-day text-amber-500 text-[10px]"></i>
                                            <span class="text-surface-400">Pensiun:</span>
                                            <span class="font-semibold text-surface-800">{{ $emp['tanggal_pensiun'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                    <!-- Drawer Footer with CSV Export & Close Button -->
                    <div class="px-5 py-3.5 bg-surface-50 border-t border-surface-200 flex items-center justify-between gap-2">
                        <button type="button" wire:click="exportDrilldownCsv"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-800 text-xs font-semibold transition shadow-2xs"
                            title="Export daftar personil ini ke CSV">
                            <i class="fas fa-file-excel text-emerald-600"></i>
                            <span>Export CSV</span>
                        </button>

                        <button type="button" wire:click="closeDrilldown"
                            class="px-4 py-1.5 text-xs font-semibold rounded-lg bg-surface-200 text-surface-700 hover:bg-surface-300 transition">
                            Tutup Panel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
