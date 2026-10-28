@props([
    'node',
    'index',
    'totalNodes',
    'groupedUnits',
    'filteredJobs' => [],
])

<div id="node-card-{{ $index }}" 
    x-data="{ isCollapsed: false }"
    @collapse-all-nodes.window="isCollapsed = true"
    @expand-all-nodes.window="isCollapsed = false"
    class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5 sm:p-6 space-y-4 transition hover:border-surface-300" 
    wire:key="node-{{ $index }}">

    <!-- Card Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-surface-100">
        <div class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-xl bg-surface-900 text-white font-bold text-xs flex items-center justify-center shadow-2xs shrink-0">
                #{{ $index + 1 }}
            </span>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h4 class="text-sm font-bold text-surface-900">
                        {{ $node['employee_nama'] ? $node['employee_nama'] : 'Slot Mata Rantai #' . ($index + 1) }}
                    </h4>
                    @if (!empty($node['employee_nik']))
                        <span class="text-[11px] text-surface-500 font-mono">({{ $node['employee_nik'] }})</span>
                    @endif

                    <!-- Summary Pills visible when Collapsed -->
                    <template x-if="isCollapsed">
                        <div class="inline-flex items-center gap-1.5 ml-1">
                            @if ($node['link_type'] === 'chain')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-primary-50 text-primary-700 border border-primary-200">
                                    <i class="fas fa-link text-[10px] mr-0.5" aria-hidden="true"></i> Saling Isi
                                </span>
                            @elseif ($node['link_type'] === 'vacant')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-info-50 text-info-700 border border-info-200">
                                    <i class="fas fa-door-open text-[10px] mr-0.5" aria-hidden="true"></i> Isi Formasi
                                </span>
                            @elseif ($node['link_type'] === 'conflict')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-danger-50 text-danger-700 border border-danger-200">
                                    <i class="fas fa-circle-exclamation text-[10px] mr-0.5" aria-hidden="true"></i> Belum Sambung
                                </span>
                            @endif

                            @php
                                $needsTenureDisp = !($node['tenure_compliant'] ?? true);
                                $needsPromoDisp = !($node['promotion_eligible'] ?? true);
                                $tenureDispOk = !empty($node['dispensasi_tenure']) || !empty($node['is_dispensasi']);
                                $promoDispOk = !empty($node['dispensasi_promosi']) || !empty($node['is_dispensasi']);
                                $allDispApproved = (!$needsTenureDisp || $tenureDispOk) && (!$needsPromoDisp || $promoDispOk);
                            @endphp
                            @if (($needsTenureDisp || $needsPromoDisp) && !empty($node['employee_nik']))
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $allDispApproved ? 'bg-primary-50 text-primary-700 border border-primary-200' : 'bg-warning-50 text-warning-700 border border-warning-200' }}">
                                    {{ $allDispApproved ? 'Dispensasi Disetujui' : 'Perlu Dispensasi' }}
                                </span>
                            @endif
                        </div>
                    </template>
                </div>

                @if (!empty($node['employee_nik']))
                    <!-- Destination summary line when collapsed -->
                    <div x-show="isCollapsed" class="text-xs text-surface-600 flex items-center gap-1.5 mt-0.5">
                        <span class="text-surface-500">{{ $node['current_jabatan'] ?: '—' }} ({{ $node['current_unit'] ?: '—' }})</span>
                        <i class="fas fa-arrow-right text-[10px] text-surface-400" aria-hidden="true"></i>
                        <span class="font-bold text-primary-800">{{ $node['target_jabatan'] ?: '—' }} ({{ $node['target_unit_kerja'] ?: '—' }})</span>
                    </div>
                    <div x-show="!isCollapsed" class="text-[11px] text-surface-500 mt-0.5">
                        {{ $node['current_jabatan'] }} &bull; {{ $node['current_unit'] }}
                    </div>
                @else
                    <p class="text-[11px] text-surface-500 mt-0.5">Pilih pegawai untuk memulai konfigurasi rantai</p>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-center flex-wrap">
            @if (!empty($node['employee_nik']))
                <button type="button" wire:click="openTalentDrawer('{{ $node['employee_nik'] }}')"
                    wire:loading.attr="disabled"
                    wire:target="openTalentDrawer('{{ $node['employee_nik'] }}')"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-primary-700 bg-primary-50 hover:bg-primary-100 border border-primary-200/80 transition cursor-pointer shadow-2xs"
                    title="Buka panel rekam jejak talenta, matriks 9-box &amp; masa dinas pegawai ini">
                    <i wire:loading.remove wire:target="openTalentDrawer('{{ $node['employee_nik'] }}')" class="fas fa-chart-line text-[11px]" aria-hidden="true"></i>
                    <i wire:loading wire:target="openTalentDrawer('{{ $node['employee_nik'] }}')" class="fas fa-spinner fa-spin text-[11px]" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Rekam Jejak &amp; 9-Box</span>
                    <span class="sm:hidden">9-Box</span>
                </button>
            @endif

            <!-- Collapse / Expand Toggle Button -->
            <button type="button" @click="isCollapsed = !isCollapsed"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-surface-600 hover:text-surface-900 bg-surface-100 hover:bg-surface-200 transition cursor-pointer"
                :title="isCollapsed ? 'Buka detail formulir slot ini' : 'Lipat detail formulir slot ini'">
                <i class="fas text-[10px]" :class="isCollapsed ? 'fa-chevron-down' : 'fa-chevron-up'" aria-hidden="true"></i>
                <span x-text="isCollapsed ? 'Buka Slot' : 'Lipat'"></span>
            </button>

            @if ($totalNodes > 2)
                <button type="button" wire:click="removeNode({{ $index }})"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-danger hover:bg-danger-50 transition cursor-pointer"
                    title="Hapus slot pegawai ini dari rantai mutasi">
                    <i class="fas fa-trash-can text-xs" aria-hidden="true"></i>
                    <span class="hidden sm:inline">Hapus</span>
                </button>
            @endif
        </div>
    </div>

    <!-- Spacious 2-Column Responsive Layout (Collapsible Body) -->
    <div x-show="!isCollapsed" x-transition.opacity.duration.200ms class="grid grid-cols-1 lg:grid-cols-12 gap-6 pt-2">

        <!-- ── KOLOM KIRI: DATA PEGAWAI ASAL (Col 5) ── -->
        <x-admin.mutasi.employee-selector 
            :node="$node"
            :index="$index"
        />

        <!-- ── KOLOM KANAN: TARGET PENEMPATAN & ANALISIS (Col 7) ── -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Jenis Mutasi & Status Jabatan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                        Jenis Mutasi <span class="text-danger font-bold">*</span>
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                            <i class="fas fa-arrows-split-up-and-left text-xs" aria-hidden="true"></i>
                        </div>
                        <select wire:model.live="nodes.{{ $index }}.jenis_mutasi"
                            class="w-full pl-9.5 pr-8 py-2.5 rounded-xl border border-surface-300 bg-white text-surface-800 text-xs sm:text-sm font-medium focus:outline-none focus:border-primary-600 focus:ring-2 focus:ring-primary-100 transition appearance-none cursor-pointer">
                            @foreach (\App\Models\JobHistory::MUTASI_TYPES as $type)
                                <option value="{{ $type }}">{{ \App\Models\JobHistory::labelFor($type) }}</option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 pr-3.5 flex items-center text-surface-500">
                            <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                        Status Penugasan <span class="text-danger font-bold">*</span>
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                            <i class="fas fa-user-check text-xs" aria-hidden="true"></i>
                        </div>
                        <select wire:model.live="nodes.{{ $index }}.status_jabatan"
                            class="w-full pl-9.5 pr-8 py-2.5 rounded-xl border border-surface-300 bg-white text-surface-800 text-xs sm:text-sm font-medium focus:outline-none focus:border-primary-600 focus:ring-2 focus:ring-primary-100 transition appearance-none cursor-pointer">
                            <option value="Definitif">Definitif (Pejabat Tetap)</option>
                            <option value="Pj">Pj (Pejabat Sementara)</option>
                            <option value="Plt">Plt (Pelaksana Tugas)</option>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 pr-3.5 flex items-center text-surface-500">
                            <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Target Unit Kerja & Smart Cascading Target Jabatan -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Target Unit Kerja -->
                <div>
                    <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                        Target Unit Kerja <span class="text-danger font-bold">*</span>
                    </label>
                    @if (empty($node['employee_nik']))
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                                <i class="fas fa-building text-xs" aria-hidden="true"></i>
                            </div>
                            <select disabled class="w-full pl-9.5 pr-8 py-2.5 rounded-xl border border-surface-200 bg-surface-100 text-surface-500 cursor-not-allowed text-xs sm:text-sm font-medium appearance-none">
                                <option value="">-- Pilih Pegawai Terlebih Dahulu --</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 pr-3.5 flex items-center text-surface-500">
                                <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                            </div>
                        </div>
                        <p class="text-[11px] text-surface-500 mt-1">Pilih pegawai pada slot ini terlebih dahulu.</p>
                    @elseif ($node['jenis_mutasi'] === 'ROTASI')
                        <div class="p-2.5 bg-surface-50 border border-surface-300 rounded-xl flex items-center justify-between text-xs sm:text-sm">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-lock text-surface-400 text-xs" aria-hidden="true"></i>
                                <span class="font-bold text-surface-800">{{ $node['target_unit_kerja'] ?: $node['current_unit'] }}</span>
                            </div>
                            <span class="text-[10px] font-bold text-primary-700 bg-primary-50 border border-primary-200 px-2 py-0.5 rounded-md">
                                Rotasi Internal Unit
                            </span>
                        </div>
                        <p class="text-[11px] text-surface-500 mt-1">Unit kerja terkunci otomatis untuk rotasi internal dalam kebun/unit yang sama.</p>
                    @else
                        <div wire:key="unit-combobox-wrapper-{{ $index }}-{{ $node['employee_nik'] }}"
                             x-data="{ unit: @entangle('nodes.' . $index . '.target_unit_kerja').live }"
                             @unit-selected="if ($event.detail.unit !== undefined) { unit = $event.detail.unit; }">
                            <x-admin.unit-combobox 
                                :grouped-units="$groupedUnits" 
                                :selected="$node['target_unit_kerja']" 
                                id="unitKerjaInputMutasi_{{ $index }}"
                                name="nodes[{{ $index }}][target_unit_kerja]" />
                        </div>
                    @endif
                    @error("nodes.{$index}.target_unit_kerja")
                        <p class="text-danger text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Target Formasi Jabatan (Cascading Dependent) -->
                <div>
                    <label class="block text-xs font-semibold text-surface-700 mb-1.5">
                        Target Formasi Jabatan <span class="text-danger font-bold">*</span>
                    </label>
                    @if (empty($node['target_unit_kerja']))
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-surface-500">
                                <i class="fas fa-briefcase text-xs" aria-hidden="true"></i>
                            </div>
                            <select disabled class="w-full pl-9.5 pr-8 py-2.5 rounded-xl border border-surface-200 bg-surface-100 text-surface-500 cursor-not-allowed text-xs sm:text-sm font-medium appearance-none">
                                <option value="">-- Pilih Unit Kerja Terlebih Dahulu --</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 pr-3.5 flex items-center text-surface-500">
                                <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                            </div>
                        </div>
                        <p class="text-[11px] text-surface-500 mt-1">Pilih Unit Kerja Target terlebih dahulu untuk melihat formasi yang tersedia.</p>
                    @else
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 pl-3.5 flex items-center text-primary-600">
                                <i class="fas fa-briefcase text-xs" aria-hidden="true"></i>
                            </div>
                            <select wire:model.live="nodes.{{ $index }}.target_jabatan"
                                class="w-full pl-9.5 pr-8 py-2.5 rounded-xl border border-surface-300 bg-white text-surface-800 text-xs sm:text-sm font-medium focus:outline-none focus:border-primary-600 focus:ring-2 focus:ring-primary-100 transition appearance-none cursor-pointer">
                                <option value="">-- Pilih Formasi Jabatan Target --</option>
                                @forelse ($filteredJobs as $pos)
                                    <option value="{{ $pos }}">{{ $pos }}</option>
                                @empty
                                    <option value="" disabled>-- Tidak Ada Formasi Eselon Sesuai di Unit Ini --</option>
                                @endforelse
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 pr-3.5 flex items-center text-surface-500">
                                <i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i>
                            </div>
                        </div>
                        @if (empty($filteredJobs) && !empty($node['target_unit_kerja']))
                            <div class="mt-1.5 p-2.5 rounded-lg bg-warning-50 border border-warning-200 text-warning-800 text-[11px] flex items-start gap-2">
                                <i class="fas fa-circle-exclamation text-warning-700 text-xs shrink-0 mt-0.5" aria-hidden="true"></i>
                                <span>
                                    Tidak ditemukan formasi yang memenuhi kualifikasi tingkat eselon (<strong>{{ $node['current_rm'] ?? 'RM' }}</strong>) untuk jenis <strong>{{ \App\Models\JobHistory::labelFor($node['jenis_mutasi'] ?? 'MUTASI_UNIT') }}</strong> di unit <strong>{{ $node['target_unit_kerja'] }}</strong>. Silakan ubah jenis mutasi atau pilih unit kerja lain.
                                </span>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Penyesuaian Golongan & Job Grade Baru (Promosi/Demosi) -->
            @if (in_array($node['jenis_mutasi'], ['PROMOSI', 'DEMOSI']))
                <div class="p-3.5 rounded-xl bg-surface-50 border border-surface-200 space-y-2.5">
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 rounded-md bg-primary-100 text-primary-700 flex items-center justify-center text-[10px] shrink-0">
                            <i class="fas fa-layer-group" aria-hidden="true"></i>
                        </div>
                        <h4 class="text-xs font-bold text-surface-900 uppercase tracking-wide">
                            Penyesuaian Golongan &amp; Job Grade Baru ({{ \App\Models\JobHistory::labelFor($node['jenis_mutasi']) }})
                        </h4>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 mb-1">
                                Golongan Baru
                            </label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $index }}.new_golongan" placeholder="Contoh: IIIC/00 atau IVB/02"
                                class="w-full px-3 py-2 bg-white border border-surface-300 rounded-lg text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                            <span class="text-[10px] text-surface-500 mt-1 block">Saat Ini: {{ $node['current_golongan'] ?: '—' }}</span>
                            @error("nodes.{$index}.new_golongan") <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-surface-700 mb-1">
                                Job Grade Baru
                            </label>
                            <input type="text" wire:model.live.debounce.300ms="nodes.{{ $index }}.new_job_grade" placeholder="Contoh: 11 s.d. 16"
                                class="w-full px-3 py-2 bg-white border border-surface-300 rounded-lg text-xs font-medium text-surface-800 placeholder-surface-400 focus:outline-none focus:ring-2 focus:ring-primary-300 focus:border-primary-600 transition">
                            <span class="text-[10px] text-surface-500 mt-1 block">Saat Ini: —</span>
                            @error("nodes.{$index}.new_job_grade") <p class="text-danger text-xs mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            @endif

            <!-- Live Occupant Inspection Banner -->
            @if (!empty($node['target_jabatan']) && !empty($node['target_unit_kerja']))
                <div class="p-4 rounded-xl text-xs {{ $node['link_type'] === 'chain' ? 'bg-primary-50 border border-primary-200 text-primary-950' : ($node['link_type'] === 'vacant' ? 'bg-info-50 border border-info-200 text-info-800' : 'bg-warning-50 border border-warning-200 text-warning-800') }}">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-start gap-2.5">
                            @if ($node['link_type'] === 'chain')
                                <i class="fas fa-check-circle text-primary-600 text-base mt-0.5 shrink-0" aria-hidden="true"></i>
                                <div>
                                    <span class="font-bold text-primary-900 block text-xs">Formasi Terhubung dalam Rantai:</span>
                                    <p class="text-[11px] text-primary-800 leading-relaxed mt-0.5">
                                        Posisi ini saat ini diduduki oleh <strong class="font-bold">{{ $node['occupant_nama'] }}</strong> yang juga telah masuk ke dalam antrean rantai mutasi ini.
                                    </p>
                                </div>
                            @elseif ($node['link_type'] === 'vacant')
                                <i class="fas fa-door-open text-info text-base mt-0.5 shrink-0" aria-hidden="true"></i>
                                <div>
                                    <span class="font-bold text-info-800 block text-xs">Pengisian Formasi Lowong (Vacant):</span>
                                    <p class="text-[11px] text-info-800 leading-relaxed mt-0.5">
                                        Posisi ini sedang kosong definitif. Mutasi ini akan menutup formasi lowong secara otomatis.
                                    </p>
                                </div>
                            @elseif ($node['link_type'] === 'pool')
                                <i class="fas fa-layer-group text-earth-600 text-base mt-0.5 shrink-0" aria-hidden="true"></i>
                                <div>
                                    <span class="font-bold text-earth-900 block text-xs">Formasi Penampungan Fungsional:</span>
                                    <p class="text-[11px] text-earth-800 leading-relaxed mt-0.5">
                                        Posisi penampungan tanpa batas plafon tunggal.
                                    </p>
                                </div>
                            @else
                                <i class="fas fa-triangle-exclamation text-warning-700 text-base mt-0.5 shrink-0" aria-hidden="true"></i>
                                <div>
                                    <span class="font-bold text-warning-800 block text-xs">Target Masih Terisi (Occupied):</span>
                                    <p class="text-[11px] text-warning-800 leading-relaxed mt-0.5">
                                        Saat ini diduduki oleh <strong class="font-bold">{{ $node['occupant_nama'] }}</strong> (NIK: {{ $node['occupant_nik'] }}).
                                    </p>
                                </div>
                            @endif
                        </div>

                        <!-- Quick Connect Button if occupied by outsider -->
                        @if ($node['link_type'] === 'conflict' && !empty($node['occupant_nik']))
                            <button type="button" wire:click="connectOccupantToChain({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="connectOccupantToChain({{ $index }})"
                                class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-warning-700 hover:bg-warning-700 text-white text-xs font-bold transition shadow-xs cursor-pointer shrink-0 disabled:opacity-60 disabled:cursor-not-allowed">
                                <i wire:loading.remove wire:target="connectOccupantToChain({{ $index }})" class="fas fa-link text-[10px]" aria-hidden="true"></i>
                                <i wire:loading wire:target="connectOccupantToChain({{ $index }})" class="fas fa-spinner fa-spin text-[10px]" aria-hidden="true"></i>
                                <span>Sambungkan {{ explode(' ', $node['occupant_nama'])[0] }} ke Rantai</span>
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Compliance & Dispensasi Guard (Unified Enterprise Component) -->
            @if (!empty($node['employee_nik']) && !empty($node['target_jabatan']) && !empty($node['target_unit_kerja']))
                <div class="pt-2">
                    <x-admin.mutasi.compliance-guard 
                        :tenure-analysis="$node['tenure_analysis'] ?? null"
                        :promotion-analysis="$node['promotion_analysis'] ?? null"
                        :current-unit="$node['current_unit'] ?? 'unit saat ini'"
                        :current-rm="$node['current_rm'] ?? 'RM'"
                        tenure-model="nodes.{{ $index }}.dispensasi_tenure"
                        tenure-reason-model="nodes.{{ $index }}.alasan_dispensasi_tenure"
                        promotion-model="nodes.{{ $index }}.dispensasi_promosi"
                        promotion-reason-model="nodes.{{ $index }}.alasan_dispensasi_promosi"
                        :is-tenure-checked="!empty($node['dispensasi_tenure']) || !empty($node['is_dispensasi'])"
                        :is-promotion-checked="!empty($node['dispensasi_promosi']) || !empty($node['is_dispensasi'])"
                        error-tenure-key="nodes.{{ $index }}.dispensasi_tenure"
                        error-tenure-reason-key="nodes.{{ $index }}.alasan_dispensasi_tenure"
                        error-promo-key="nodes.{{ $index }}.dispensasi_promosi"
                        error-promo-reason-key="nodes.{{ $index }}.alasan_dispensasi_promosi"
                    />
                </div>
            @endif
        </div>
    </div>
</div>
