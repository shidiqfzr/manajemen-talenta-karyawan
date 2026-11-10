{{-- Sebaran Formasi: gambaran lintas unit formasi jabatan terisi dan kosong, per rumpun jabatan atau per unit (komponen tabel bersama, berbasis koleksi). --}}
@php
    $isJabatan = $view === 'jabatan';
    $loadingTargets = 'search,unitFilter,wilayahFilter,rmFilter,bidangFilter,statusFilter,severityFilter,resetFilters,toggleKosongFilter,setView,sortBy,perPage';
    $kosongActive = $statusFilter === \App\Services\JobVacancyService::STATUS_ANY_VACANT;
    $fmt = fn (int $n) => number_format($n, 0, ',', '.');
@endphp
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">
        <x-admin.kpi-card title="Formasi Jabatan" :value="$stats['total_seats']" :subtitle="'Total kuota '.$fmt($stats['total_kuota'])" icon="fas fa-briefcase" theme="primary" />
        <x-admin.kpi-card title="Terisi" :value="$stats['filled']" :badge="$stats['fill_rate'].'%'" :subtitle="'dari '.$fmt($stats['total_kuota']).' kuota'" icon="fas fa-user-check" theme="teal" />
        <x-admin.kpi-card title="Formasi Kosong" :value="$stats['vacant_formasi']" :subtitle="$fmt($stats['vacant_slots']).' kuota kosong'" icon="fas fa-door-open" :theme="$stats['vacant_formasi'] > 0 ? 'warning' : 'neutral'" :active="$kosongActive"
            role="button" tabindex="0" aria-pressed="{{ $kosongActive ? 'true' : 'false' }}" wire:click="toggleKosongFilter" wire:keydown.enter="toggleKosongFilter" wire:keydown.space.prevent="toggleKosongFilter" />
        <x-admin.kpi-card title="Diisi Pj/Plt" :value="$stats['acting_count']" subtitle="Dijabat sementara" icon="fas fa-user-clock" theme="earth" />
    </div>

    <x-admin.table.card>
        <div wire:loading.delay wire:target="{{ $loadingTargets }}" class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$filters"
            :filter-values="$filterValues"
            :has-active-filters="$hasActiveFilters"
            :search="$search"
            search-placeholder="Cari jabatan atau unit kerja..." />
        <x-admin.table.header :title="$isJabatan ? 'Sebaran per Jabatan' : 'Sebaran per Unit'" :paginator="$rows" :noun="$isJabatan ? 'Jabatan' : 'Formasi'" :export="true">
            <x-slot:actions>
                <div role="radiogroup" aria-label="Tampilan sebaran" class="inline-flex rounded-lg border border-surface-300 bg-white p-0.5">
                    @foreach (['jabatan' => 'Per Jabatan', 'unit' => 'Per Unit'] as $key => $label)
                        <button type="button" role="radio" aria-checked="{{ $view === $key ? 'true' : 'false' }}" wire:click="setView('{{ $key }}')"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $view === $key ? 'bg-primary-600 text-white shadow-2xs' : 'text-surface-600 hover:bg-surface-100' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </x-slot:actions>
        </x-admin.table.header>

        <p class="px-5 py-2.5 bg-surface-50 border-b border-surface-200 text-[11px] text-surface-500">
            Hanya pejabat definitif yang mengisi kuota; jabatan yang dijabat Pj/Plt tetap tercatat kosong. Cakupan formasi saat ini: seluruh Karpim dan Karpel pada unit Regional Office.
        </p>

        <div class="overflow-x-auto">
            @if ($isJabatan)
                <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200">
                            <x-admin.table.th sortable field="family" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th sortable field="unit_count" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Unit</x-admin.table.th>
                            <x-admin.table.th sortable field="kuota" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Kuota</x-admin.table.th>
                            <x-admin.table.th sortable field="filled" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Terisi</x-admin.table.th>
                            <x-admin.table.th sortable field="vacant_slots" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Kosong</x-admin.table.th>
                            <x-admin.table.th sortable field="fill_rate" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Keterisian</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-surface-800 transition-opacity" wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}">
                        @forelse ($rows as $row)
                            @php
                                $key = md5($row->family);
                                $open = $expanded === $row->family;
                            @endphp
                            <tr class="hover:bg-surface-50/75 transition" wire:key="family-{{ $key }}">
                                <td class="py-3.5 px-5">
                                    <button type="button" wire:click="toggleFamily(@js($row->family))" aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="family-panel-{{ $key }}"
                                        class="group flex items-start gap-2.5 text-left rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                                        <i class="fas fa-chevron-right mt-1 text-[10px] text-surface-400 transition motion-reduce:transition-none {{ $open ? 'rotate-90' : '' }}" aria-hidden="true"></i>
                                        <span>
                                            <span class="block font-semibold text-surface-900 text-sm leading-tight group-hover:text-primary-700">{{ $row->family }}</span>
                                            <span class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                                @foreach ($row->rm_levels as $rm)
                                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ \App\Models\Position::rmTheme($rm) }}">{{ $rm }}</span>
                                                @endforeach
                                                <span class="text-[11px] text-surface-500">{{ $row->jabatan_count }} jabatan</span>
                                            </span>
                                        </span>
                                    </button>
                                </td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $row->unit_count }} unit</td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $row->kuota }}</td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $row->filled }}@if ($row->acting > 0)<span class="block text-[11px] text-surface-500">{{ $row->acting }} Pj/Plt</span>@endif</td>
                                <td class="py-3.5 px-5 tabular-nums">
                                    @if ($row->vacant_slots > 0)
                                        <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold bg-warning-50 text-warning-800 border border-warning-200">{{ $row->vacant_slots }} di {{ $row->vacant_units }} unit</span>
                                    @else
                                        <span class="text-surface-400">0</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-20 rounded-full bg-surface-200" role="img" aria-label="Keterisian {{ $row->fill_rate }} persen"><div class="h-1.5 rounded-full bg-primary-600" style="width: {{ $row->fill_rate }}%"></div></div>
                                        <span class="tabular-nums text-surface-700">{{ $row->fill_rate }}%</span>
                                    </div>
                                </td>
                            </tr>
                            @if ($open)
                                <tr wire:key="family-panel-{{ $key }}" class="bg-surface-50/60">
                                    <td colspan="6" id="family-panel-{{ $key }}" class="px-5 py-4">
                                        <table class="w-full text-left border-collapse text-xs bg-white rounded-xl border border-surface-200 overflow-hidden">
                                            <thead>
                                                <tr class="bg-surface-50 border-b border-surface-200">
                                                    <x-admin.table.th class="py-2.5 px-4">Unit Kerja</x-admin.table.th>
                                                    <x-admin.table.th class="py-2.5 px-4">Jabatan</x-admin.table.th>
                                                    <x-admin.table.th class="py-2.5 px-4">Terisi / Kuota</x-admin.table.th>
                                                    <x-admin.table.th class="py-2.5 px-4">Status</x-admin.table.th>
                                                    <x-admin.table.th class="py-2.5 px-4">Kosong Sejak</x-admin.table.th>
                                                    <x-admin.table.th class="py-2.5 px-4 text-right">Aksi</x-admin.table.th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-surface-200">
                                                @foreach ($row->seats as $seat)
                                                    <tr wire:key="family-{{ $key }}-seat-{{ $seat->position_unit_id }}">
                                                        <td class="py-3 px-4">
                                                            <a href="{{ route('admin.master.formasi.show', $seat->unit_id) }}" wire:navigate class="font-semibold text-surface-900 hover:text-primary-700 transition">{{ $seat->unit_kerja }}</a>
                                                            <span class="block text-[11px] text-surface-500">{{ $seat->wilayah }}</span>
                                                        </td>
                                                        <td class="py-3 px-4">{{ $seat->jabatan }}@if ($seat->bidang)<span class="block text-[11px] text-surface-500">Bidang {{ $seat->bidang }}</span>@endif</td>
                                                        <td class="py-3 px-4 tabular-nums"><span class="font-semibold">{{ $seat->filled }}</span><span class="text-surface-500"> / {{ $seat->kuota }}</span>@if ($seat->acting > 0)<span class="block text-[11px] text-surface-500">{{ $seat->acting }} Pj/Plt</span>@endif</td>
                                                        <td class="py-3 px-4"><x-admin.master.seat-status :status="$seat->status" :label="$seat->status_label" /></td>
                                                        <td class="py-3 px-4"><x-admin.master.seat-since :seat="$seat" /></td>
                                                        <td class="py-3 px-4 text-right whitespace-nowrap"><x-admin.master.seat-action :seat="$seat" /></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <x-admin.table.empty-state :colspan="6" icon="fas fa-table-cells"
                                :message="$hasActiveFilters ? 'Tidak ada formasi yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter.' : 'Belum ada formasi jabatan pada unit aktif.'">
                                @if ($hasActiveFilters)
                                    <x-admin.button variant="secondary" size="sm" wire:click="resetFilters">Reset Filter</x-admin.button>
                                @endif
                            </x-admin.table.empty-state>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200">
                            <x-admin.table.th sortable field="unit_kerja" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                            <x-admin.table.th sortable field="jabatan" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th sortable field="filled" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Terisi / Kuota</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Status</x-admin.table.th>
                            <x-admin.table.th sortable field="days_vacant" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Kosong Sejak</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5 text-right">Aksi</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-surface-800 transition-opacity" wire:loading.class="opacity-50" wire:target="{{ $loadingTargets }}">
                        @forelse ($rows as $seat)
                            <tr class="hover:bg-surface-50/75 transition" wire:key="seat-{{ $seat->position_unit_id }}">
                                <td class="py-3.5 px-5">
                                    <a href="{{ route('admin.master.formasi.show', $seat->unit_id) }}" wire:navigate class="font-semibold text-surface-900 hover:text-primary-700 transition">{{ $seat->unit_kerja }}</a>
                                    <span class="block text-[11px] text-surface-500">{{ $seat->wilayah }}</span>
                                </td>
                                <td class="py-3.5 px-5">
                                    <p class="font-semibold text-surface-900 text-sm leading-tight">{{ $seat->jabatan }}</p>
                                    <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                        <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ \App\Models\Position::rmTheme($seat->rm_level) }}">{{ $seat->rm_level ?? '-' }}</span>
                                        @if ($seat->bidang)
                                            <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] bg-surface-200/80 text-surface-800">Bidang {{ $seat->bidang }}</span>
                                        @endif
                                        @if ($seat->is_reserved && $seat->vacant_slots > 0)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-warning-100 text-warning-800 border border-warning-200" title="Dipesan oleh {{ $seat->reserved_occupant_name }} (TMT: {{ $seat->reserved_tmt }})"><i class="fas fa-lock text-[10px]" aria-hidden="true"></i> Dipesan: {{ $seat->reserved_tmt }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3.5 px-5 tabular-nums">
                                    <span class="font-semibold">{{ $seat->filled }}</span><span class="text-surface-500"> / {{ $seat->kuota }}</span>
                                    @if ($seat->vacant_slots > 0)
                                        <span class="block text-[11px] text-danger-700 font-semibold">{{ $seat->vacant_slots }} kosong</span>
                                    @endif
                                    @if ($seat->acting > 0)
                                        <span class="block text-[11px] text-surface-500">{{ $seat->acting }} Pj/Plt</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5"><x-admin.master.seat-status :status="$seat->status" :label="$seat->status_label" /></td>
                                <td class="py-3.5 px-5"><x-admin.master.seat-since :seat="$seat" /></td>
                                <td class="py-3.5 px-5 text-right whitespace-nowrap"><x-admin.master.seat-action :seat="$seat" /></td>
                            </tr>
                        @empty
                            <x-admin.table.empty-state :colspan="6" icon="fas fa-table-cells"
                                :message="$hasActiveFilters ? 'Tidak ada formasi yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter.' : 'Belum ada formasi jabatan pada unit aktif.'">
                                @if ($hasActiveFilters)
                                    <x-admin.button variant="secondary" size="sm" wire:click="resetFilters">Reset Filter</x-admin.button>
                                @endif
                            </x-admin.table.empty-state>
                        @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        <x-admin.table.pagination :paginator="$rows" :noun="$isJabatan ? 'jabatan' : 'formasi'" :per-page-options="$perPageOptions" />
    </x-admin.table.card>
</div>
