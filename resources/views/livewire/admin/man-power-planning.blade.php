<div class="space-y-6">

    <!-- MPP Executive KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        {{-- Kartu statis seperti modul lain; tema menurut arti data (DESIGN.md §5.3), satu kartu danger. --}}
        <x-admin.kpi-card
            title="Standar Formasi Baku"
            :value="$stats['total_formasi']"
            subtitle="Kursi Karpim dari master formasi"
            icon="fas fa-sitemap"
            theme="info"
        />

        <x-admin.kpi-card
            title="Realisasi Terisi"
            :value="$stats['total_realisasi']"
            subtitle="Pejabat struktural per {{ $stats['as_of_label'] }}"
            icon="fas fa-users-viewfinder"
            theme="primary"
            :badge="$stats['fill_rate'] . '%'"
        />

        <x-admin.kpi-card
            title="Proyeksi Pensiun"
            :value="$stats['total_pensiun']"
            subtitle="Kursi kosong sejak MBT · {{ $stats['pensiun_window_label'] }}"
            icon="fas fa-user-clock"
            theme="warning"
        />

        {{-- Kekurangan dan surplus ditampilkan terpisah agar surplus satu bidang tidak menutupi kebutuhan bidang lain. --}}
        <x-admin.kpi-card
            title="Kekurangan Formasi {{ $stats['planning_year'] }}"
            :value="$stats['total_kekurangan']"
            subtitle="Surplus {{ $stats['total_surplus'] }} · Kebutuhan Bersih {{ sprintf('%+d', $stats['total_kebutuhan']) }}"
            icon="fas fa-scale-balanced"
            :theme="$stats['total_kekurangan'] > 0 ? 'danger' : 'primary'"
            :badge="$stats['total_kekurangan'] > 0 ? 'Defisit Formasi' : 'Formasi Terpenuhi'"
            :coloredValue="$stats['total_kekurangan'] > 0"
        />
    </div>

    <!-- Main Enterprise MPP Matrix Table with Integrated Header Toolbar -->
    <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
        <!-- Tier 1: Title, Enterprise Scope Badge & Primary Actions -->
        <div class="px-5 py-3.5 bg-white border-b border-surface-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-primary-600 inline-block"></span>
                    <h3 class="text-sm font-bold text-surface-900 tracking-tight">
                        Matriks Formasi &amp; Kebutuhan Tenaga Kerja (MPP)
                    </h3>
                </div>
                <p class="text-xs text-surface-500 mt-1">Pemetaan kekuatan formasi manajerial (RM-1, RM-2, RM-3) berdasarkan bidang fungsional operasional.</p>
                @foreach($period ? [] : $stats['supply_plan_provenance'] as $plan)
                    <p class="text-[11px] text-surface-500 mt-0.5 flex items-center gap-1.5">
                        <i class="fas fa-clock-rotate-left text-surface-400" aria-hidden="true"></i>
                        @if($plan['by'])
                            <span>Rencana pasokan {{ $plan['year'] }} diperbarui oleh {{ $plan['by'] }} · {{ $plan['at']->translatedFormat('d M Y H:i') }}</span>
                        @else
                            <span>Rencana pasokan {{ $plan['year'] }} tersimpan</span>
                        @endif
                    </p>
                @endforeach
            </div>

            <!-- Action Buttons Cluster -->
            <div class="flex items-center gap-2 shrink-0">
                {{-- Kelola Rencana Pasokan (tidak untuk bulan berisi snapshot: angkanya sudah dibekukan) --}}
                @unless($period)
                    <button type="button" wire:click="$dispatch('open-mpp-supply-plan-editor', { year: {{ $planningYear }}, month: {{ $asOfMonth }} })"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-surface-900 text-xs font-semibold transition-all shadow-2xs">
                        <i class="fas fa-pen-to-square text-surface-400" aria-hidden="true"></i>
                        <span>Kelola Rencana Pasokan</span>
                    </button>
                @endunless

                <!-- Export Excel -->
                <button type="button" wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-primary-300/80 bg-primary-50 text-primary-800 hover:bg-primary-100 hover:border-primary-400 text-xs font-semibold transition-all shadow-2xs disabled:opacity-60 disabled:cursor-wait">
                    <i class="fas fa-file-excel text-primary-700" wire:loading.remove wire:target="exportExcel" aria-hidden="true"></i>
                    <i class="fas fa-spinner fa-spin text-primary-700" wire:loading wire:target="exportExcel" aria-hidden="true"></i>
                    <span>Export Excel</span>
                </button>
            </div>
        </div>

        <!-- Tier 2: Sub-Header Filter Strip (Tahun, Posisi per, Bidang) -->
        <div class="px-5 py-2.5 bg-surface-50 border-b border-surface-200 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Sisi Kiri: Tahun perencanaan dan tanggal posisi -->
            <div class="flex flex-wrap items-center gap-2.5 self-start md:self-auto">
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white border border-surface-200 text-xs shadow-2xs">
                    <i class="fas fa-calendar-alt text-surface-400 text-xs" aria-hidden="true"></i>
                    <span class="text-surface-500 font-medium">Tahun:</span>
                    <select wire:model.live="planningYear" class="bg-transparent font-bold text-surface-900 text-xs focus:outline-hidden cursor-pointer">
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}">{{ $yr }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-white border border-surface-200 text-xs shadow-2xs"
                    title="Realisasi dihitung pada akhir bulan ini; Pensiun mencakup bulan berikutnya s.d. Desember tahun depan">
                    <i class="fas fa-calendar-day text-surface-400 text-xs" aria-hidden="true"></i>
                    <span class="text-surface-500 font-medium">Posisi per:</span>
                    <select wire:model.live="asOfMonth" class="bg-transparent font-bold text-surface-900 text-xs focus:outline-hidden cursor-pointer">
                        @foreach($months as $num => $name)
                            <option value="{{ $num }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>

                <span wire:loading.delay wire:target="planningYear,asOfMonth" class="text-[11px] text-surface-500">
                    <i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Menghitung…
                </span>
            </div>

            <!-- Sisi Kanan: Filter Bidang -->
            <div class="flex items-center gap-2.5 self-start md:self-auto">
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

        @if($flash)
            <div role="status" class="px-5 py-2.5 bg-primary-50 border-b border-primary-200 flex items-center justify-between gap-2 text-xs font-semibold text-primary-800">
                <p class="flex items-center gap-2">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <span>{{ $flash }}</span>
                </p>
                <button type="button" wire:click="$set('flash', null)" class="p-1 rounded hover:bg-primary-100" aria-label="Tutup pemberitahuan">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        @include('livewire.admin.partials.mpp-period-banner', [
            'periodState' => $periodState,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'periodStamps' => $periodStamps,
            'periodAction' => $periodAction,
            'periodError' => $periodError,
        ])

        @if($stats['supply_plan_missing'] && ! $period)
            <div role="status" class="px-5 py-2.5 bg-warning-50 border-b border-warning-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-warning-800">
                <p class="flex items-center gap-2">
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <span>
                        @foreach($stats['supply_plan_missing_years'] as $missingYear)
                            Rencana pasokan {{ $missingYear }} belum diisi. Angka pasokan dihitung 0.
                        @endforeach
                    </span>
                </p>
                <button type="button" wire:click="$dispatch('open-mpp-supply-plan-editor', { year: {{ $stats['supply_plan_missing_years'][0] }}{{ $stats['supply_plan_missing_years'][0] === $planningYear ? ', month: '.$asOfMonth : '' }} })"
                    class="self-start sm:self-auto font-semibold underline underline-offset-2 hover:text-warning-900">
                    Isi rencana pasokan
                </button>
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left border-collapse min-w-[900px]">
                <!-- Level 1 Header: Groups -->
                <thead>
                    <tr class="border-b border-surface-200 select-none">
                        <th scope="col" rowspan="2" class="w-12 px-3 py-3 text-center font-bold text-surface-700 bg-surface-100 border-r border-surface-200">
                            No.
                        </th>
                        <th scope="col" rowspan="2" class="min-w-[200px] px-4 py-3 text-center font-bold text-surface-800 bg-surface-100 border-r border-surface-200">
                            URAIAN INDIKATOR
                        </th>

                        <!-- RM-1 Group Header -->
                        <th scope="col" colspan="{{ $filterBidang === 'ALL' ? 5 : 2 }}" class="px-3 py-2 text-center font-bold text-orange-950 bg-orange-100/90 border-r border-orange-200 {{ $filterBidang !== 'ALL' ? 'min-w-[220px]' : '' }}">
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <span>RM-1</span>
                                <span class="text-[10px] font-normal text-orange-800">({{ $rmBands['RM-1']['short'] }})</span>
                            </div>
                        </th>

                        <!-- RM-2 Group Header -->
                        <th scope="col" colspan="{{ $filterBidang === 'ALL' ? 5 : 2 }}" class="px-3 py-2 text-center font-bold text-sky-950 bg-sky-100/90 border-r border-sky-200 {{ $filterBidang !== 'ALL' ? 'min-w-[220px]' : '' }}">
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <span>RM-2</span>
                                <span class="text-[10px] font-normal text-sky-800">({{ $rmBands['RM-2']['short'] }})</span>
                            </div>
                        </th>

                        <!-- RM-3 Group Header -->
                        <th scope="col" colspan="{{ $filterBidang === 'ALL' ? 5 : 2 }}" class="px-3 py-2 text-center font-bold text-purple-950 bg-purple-100/90 border-r border-purple-200 {{ $filterBidang !== 'ALL' ? 'min-w-[240px]' : '' }}">
                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                <span>RM-3</span>
                                <span class="text-[10px] font-normal text-purple-800">({{ $rmBands['RM-3']['short'] }})</span>
                            </div>
                        </th>

                        <!-- TOTAL Column Header -->
                        <th scope="col" rowspan="2" class="w-20 px-3 py-3 text-center font-bold text-white bg-primary-800">
                            TOTAL
                        </th>
                    </tr>

                    <!-- Level 2 Header: Functional Columns per RM -->
                    <tr class="border-b-2 border-surface-300 text-[11px] font-bold select-none text-surface-700">
                        @foreach(['RM-1' => 'bg-orange-50/80', 'RM-2' => 'bg-sky-50/80', 'RM-3' => 'bg-purple-50/80'] as $rm => $bg)
                            @if($filterBidang === 'ALL')
                                <th scope="col" class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">KEU</th>
                                <th scope="col" class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">TAN</th>
                                <th scope="col" class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">TEK</th>
                                <th scope="col" class="w-12 px-2 py-2 text-center {{ $bg }} border-r border-surface-200">UMU</th>
                                <th scope="col" class="w-16 px-2 py-2 text-center {{ $bg }} font-extrabold text-surface-900 border-r border-surface-300">JUMLAH</th>
                            @else
                                <th scope="col" class="px-3 py-2 text-center {{ $bg }} border-r border-surface-200 font-bold">{{ $filterBidang }}</th>
                                <th scope="col" class="px-3 py-2 text-center {{ $bg }} font-extrabold text-surface-900 border-r border-surface-300">JUMLAH</th>
                            @endif
                        @endforeach
                    </tr>
                </thead>

                <!-- Table Body -->
                <tbody class="divide-y divide-surface-200">
                    @foreach($matrix as $row)
                        @php
                            $rowHighlight = match($row['highlight']) {
                                'formasi' => 'bg-primary-50/30 font-semibold',
                                'realisasi' => 'bg-info-50/30 font-semibold',
                                'kebutuhan' => 'bg-warning-50/40 font-bold border-y-2 border-warning-200',
                                'supply' => 'bg-earth-50/20',
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

                                @foreach($filterBidang === 'ALL' ? ['KEU', 'TAN', 'TEK', 'UMU'] : [$filterBidang] as $field)
                                    @include('livewire.admin.partials.mpp-cell', [
                                        'row' => $row,
                                        'rm' => $rm,
                                        'field' => $field,
                                        'val' => $levelData['fields'][$field] ?? 0,
                                    ])
                                @endforeach

                                <!-- Subtotal JUMLAH -->
                                <td class="px-2 py-2 text-center font-mono font-bold tabular-nums bg-surface-100/60 text-surface-900 border-r border-surface-300">
                                    @if($row['highlight'] === 'kebutuhan')
                                        @include('livewire.admin.partials.mpp-shortage-surplus', [
                                            'shortage' => $levelData['shortage'],
                                            'surplus' => $levelData['surplus'],
                                            'net' => $levelData['subtotal'],
                                        ])
                                    @elseif($levelData['subtotal'] === 0 || $levelData['subtotal'] === '0')
                                        <span class="font-bold text-surface-900">0</span>
                                    @else
                                        <span class="font-bold text-surface-900">{{ $levelData['subtotal'] }}</span>
                                    @endif
                                </td>
                            @endforeach

                            <!-- Grand TOTAL Column -->
                            <td class="px-3 py-2 text-center font-mono font-extrabold tabular-nums bg-surface-100 text-surface-900">
                                @if($row['highlight'] === 'kebutuhan')
                                    @include('livewire.admin.partials.mpp-shortage-surplus', [
                                        'shortage' => $row['shortage_total'],
                                        'surplus' => $row['surplus_total'],
                                        'net' => $row['grand_total'],
                                    ])
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
        <div id="mpp-legend" class="px-5 py-4 bg-surface-50 border-t border-surface-200 grid grid-cols-1 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_minmax(0,1.5fr)] gap-x-8 gap-y-4 text-xs text-surface-700">
            <!-- Legend 1: label colors follow DESIGN.md §5.2 (RM-1 orange, RM-2 sky, RM-3 purple) -->
            <section aria-labelledby="legend-rm" class="space-y-1">
                <h3 id="legend-rm" class="font-semibold text-surface-900">Tingkat golongan jabatan (RM)</h3>
                <ul class="space-y-0.5 leading-snug">
                    @foreach (['RM-1' => 'text-orange-800', 'RM-2' => 'text-sky-800', 'RM-3' => 'text-purple-800'] as $rm => $tone)
                        <li><strong class="{{ $tone }}">{{ $rm }}:</strong> {{ $rmBands[$rm]['positions'] }}</li>
                    @endforeach
                </ul>
            </section>

            <!-- Legend 2: Bidang, plain bold codes (no chips, DESIGN.md §1 badge soup) -->
            <section aria-labelledby="legend-bidang" class="space-y-1 md:pl-4">
                <h3 id="legend-bidang" class="font-semibold text-surface-900">Bidang fungsional</h3>
                <ul class="space-y-0.5 leading-snug">
                    <li><strong class="text-surface-900">KEU:</strong> Keuangan, Akuntansi &amp; Perbendaharaan</li>
                    <li><strong class="text-surface-900">TAN:</strong> Tanaman, Agronomi &amp; Afdeling Kebun</li>
                    <li><strong class="text-surface-900">TEK:</strong> Teknik, Pengolahan Pabrik &amp; Bengkel</li>
                    <li><strong class="text-surface-900">UMU:</strong> Umum, SDM, Pengadaan, Legal &amp; Sekretariat</li>
                </ul>
            </section>

            <!-- Legend 3 -->
            <section aria-labelledby="legend-formula" class="space-y-1">
                <h3 id="legend-formula" class="font-semibold text-surface-900">Keterangan formula kebutuhan</h3>
                <p class="leading-relaxed">
                    <span class="font-semibold text-surface-900">Kebutuhan = (Formasi − Realisasi + Pensiun) − Sisa Pasokan (CKP + Scouting + RBB)</span>.
                    Angka bertanda minus (<span class="text-primary-700 font-bold">–</span>) menandakan kondisi <strong>surplus pasokan talenta</strong> di atas batas kebutuhan minimal.
                    Realisasi dihitung pada akhir bulan <strong>Posisi per</strong>; kursi dihitung kosong sejak <strong>Masa Bebas Tugas (MBT)</strong>, satu tahun sebelum tanggal pensiun.
                    Sisa pasokan = rencana tahun berjalan dikurangi pegawai jalur tersebut yang sudah masuk hingga tanggal posisi.
                </p>
            </section>
        </div>
    </div>

    {{-- Drawer rincian personil (komponen bersama dengan Master Formasi, DESIGN.md §7.6) --}}
    @if($showDrilldown)
        <x-admin.personnel-drawer
            :title="$drilldownTitle"
            close-action="closeDrilldown"
            title-id="slide-over-title"
            :employees="$drilldownEmployees"
            :count="in_array($drilldownMode, ['people', 'supply'], true) ? count($drilldownEmployees) : null"
            :show-nik="true"
            :show-placement="$drilldownMode === 'supply'"
            :hide-list="$drilldownMode === 'formula'">
            <x-slot:badges>
                <span class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-orange-100 text-orange-800 border border-orange-200">
                    {{ $drilldownRm }}
                </span>
                <span class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-sky-100 text-sky-800 border border-sky-200">
                    Bidang {{ $drilldownBidang }}
                </span>
                <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] bg-surface-200/80 text-surface-800">
                    {{ $drilldownIndicator }}
                </span>
            </x-slot:badges>

            <x-slot:header>
                <!-- Rencana vs realisasi pasokan (baris Sisa CKP / Talent Scouting / RBB) -->
                @if($drilldownSupply)
                    @php
                        $over = $drilldownSupply['terealisasi'] - $drilldownSupply['rencana'];
                        $supplyNotes = [];
                        if ($over > 0) {
                            $supplyNotes[] = ['text' => "Terealisasi melebihi rencana: {$over}. Sisa dihitung 0; kelebihannya sudah tercatat di Realisasi.", 'class' => 'font-semibold text-warning-800'];
                        }
                        $supplyNotes[] = ['text' => "Daftar di bawah: pegawai jalur ini yang ditempatkan sebagai Karpim pada {$planningYear} s.d. akhir {$stats['as_of_label']}. Mereka sudah tercatat di Realisasi, sehingga hanya sisa rencana yang mengurangi Kebutuhan.", 'class' => 'text-warning-800/80'];
                    @endphp
                    @include('livewire.admin.partials.mpp-breakdown-card', [
                        'kind' => 'supply',
                        'title' => 'Rincian Rencana vs Realisasi Pasokan',
                        'icon' => 'fas fa-people-arrows',
                        'badge' => match (true) {
                            $over > 0 => ['label' => 'Melebihi Rencana', 'class' => 'bg-primary-100 text-primary-800'],
                            $drilldownSupply['sisa'] === 0 => ['label' => 'Rencana Tercapai', 'class' => 'bg-primary-100 text-primary-800'],
                            default => ['label' => 'Sisa '.$drilldownSupply['sisa'], 'class' => 'bg-warning-100 text-warning-800'],
                        },
                        'terms' => [
                            ['op' => null, 'label' => 'Rencana '.$planningYear, 'value' => $drilldownSupply['rencana'], 'class' => 'text-surface-900'],
                            ['op' => '−', 'label' => 'Terealisasi', 'value' => $drilldownSupply['terealisasi'], 'class' => 'text-primary-700'],
                        ],
                        'result' => ['label' => 'Sisa', 'value' => $drilldownSupply['sisa'], 'class' => 'text-earth-700'],
                        'notes' => $supplyNotes,
                    ])
                @endif

                <!-- Formula Calculation Breakdown Card (When clicking on Kebutuhan Rows) -->
                @if($drilldownFormula)
                    @include('livewire.admin.partials.mpp-breakdown-card', [
                        'kind' => 'formula',
                        'title' => 'Rincian Formula Kebutuhan Bersih',
                        'icon' => 'fas fa-calculator',
                        'badge' => $drilldownFormula['is_surplus']
                            ? ['label' => 'Surplus Pasokan (-)', 'class' => 'bg-primary-100 text-primary-800']
                            : ['label' => 'Defisit Formasi (+)', 'class' => 'bg-danger-100 text-danger'],
                        'terms' => [
                            ['op' => null, 'label' => 'Formasi', 'value' => $drilldownFormula['formasi'], 'class' => 'text-surface-900'],
                            ['op' => '−', 'label' => 'Realisasi', 'value' => $drilldownFormula['realisasi'], 'class' => 'text-info-700'],
                            ['op' => '+', 'label' => 'Pensiun', 'value' => $drilldownFormula['pensiun'], 'class' => 'text-warning-700'],
                            ['op' => '−', 'label' => 'Pasokan', 'value' => $drilldownFormula['pasokan'], 'class' => 'text-earth-700'],
                        ],
                        'result' => [
                            'label' => 'Kebutuhan',
                            'value' => $drilldownFormula['kebutuhan'],
                            'class' => $drilldownFormula['is_surplus'] ? 'text-primary-700' : 'text-danger',
                        ],
                        'notes' => [
                            ['text' => 'Rincian personil tersedia dengan mengeklik angka pada baris Realisasi, Pensiun, atau Sisa pasokan.', 'class' => 'text-warning-800/80'],
                        ],
                    ])
                @endif
            </x-slot:header>

            @if(in_array($drilldownMode, ['people', 'supply'], true) && ! empty($drilldownEmployees))
                <x-slot:footer>
                    <button type="button" wire:click="exportDrilldownExcel" wire:loading.attr="disabled" wire:target="exportDrilldownExcel"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-primary-300 bg-white hover:bg-primary-50 text-primary-800 text-xs font-semibold transition shadow-2xs disabled:opacity-60 disabled:cursor-wait"
                        title="Export daftar personil ini ke Excel">
                        <i class="fas fa-file-excel text-primary-600" wire:loading.remove wire:target="exportDrilldownExcel" aria-hidden="true"></i>
                        <i class="fas fa-spinner fa-spin text-primary-600" wire:loading wire:target="exportDrilldownExcel" aria-hidden="true"></i>
                        <span>Export Excel</span>
                    </button>
                </x-slot:footer>
            @endif
        </x-admin.personnel-drawer>
    @endif

    {{-- Laci editor rencana pasokan (dibuka dari tombol Kelola Rencana Pasokan) --}}
    @unless($period)
        <livewire:admin.mpp-supply-plan-editor />
    @endunless

</div>
