{{--
    Toolbar tabel data Livewire (DESIGN.md §7.5, §8.4), dipakai di dalam x-admin.table.card bersama trait WithDataTable.
    Baris judul (hanya bila ada slot title): judul + tombol Ekspor Excel di kanan atas (posisi sama di semua modul).
    Baris pencarian: pencarian selebar baris (debounce 300ms, maks. 100 karakter), tombol "Filter" berhitungan yang
    membuka panel filter berlabel, dan tombol Ekspor Excel di kanan bila tidak ada judul.
    Panel filter: kolom berlabel terlihat (pilihan, rentang tanggal, saklar ya/tidak) dan Reset filter; tertutup
    secara bawaan. Saat panel tertutup, filter aktif tampil sebagai chip yang dapat dihapus satu per satu.

    <x-admin.table.toolbar
        :table-id="$this->getId()"
        :filters="$this->filters()"
        :filter-values="$this->activeFilterValues()"
        :export="$this->exportName() !== null"
        :has-active-filters="$this->hasActiveFilters()"
        :search="$search"
        search-placeholder="Cari nama pegawai...">
        <x-slot:title><h2 class="text-sm font-bold text-surface-900">Daftar Pegawai</h2></x-slot:title>
    </x-admin.table.toolbar>

    table-id        : awalan id unik elemen form (beberapa tabel dapat tampil di satu halaman).
    filters         : list<App\Support\DataTable\Filter>; select -> x-admin.filter-select berlabel, boolean -> saklar,
                      date_from/date_to -> input tanggal berlabel (pasangan dari-sampai berdampingan). Filter yang
                      withoutControl() tidak punya kolom di panel (dikendalikan dari luar, mis. kartu KPI) tetapi tetap
                      tampil sebagai chip dan ikut Reset filter.
    filter-values   : [properti => nilai] filter yang sedang aktif, untuk hitungan tombol Filter, saklar, dan chip.
    search          : kata kunci pencarian saat ini ($search), untuk chip "Pencarian".
    search-shortcut : tombol pintas fokus pencarian; null untuk tabel sematan agar tidak berebut pintasan.
    result-count    : jumlah baris hasil saat ini ($rows->total()); bila diisi, bilah ringkasan menampilkan jumlah data.
    total-count     : jumlah data tanpa pencarian/filter ($this->unfilteredCount) saat ada filter aktif, selain itu null;
                      memunculkan "12 dari 339 ... sesuai filter".
    noun            : sebutan data pada ringkasan (mis. catatan, riwayat); sama dengan noun penomoran.
    Tabel tanpa filter hanya menampilkan pencarian: tidak ada tombol Filter dan tidak ada panel.
    Bilah ringkasan (di bawah panel, selalu terlihat): jumlah data pada wilayah live permanen (role="status"), chip
    "Filter Aktif", dan tombol Reset filter di kanan. Modul tidak boleh menambah wilayah pengumuman jumlah sendiri.
--}}
@props([
    'tableId' => 'table',
    'filters' => [],
    'filterValues' => [],
    'export' => false,
    'hasActiveFilters' => false,
    'search' => '',
    'searchPlaceholder' => 'Cari...',
    'searchShortcut' => '/',
    'resultCount' => null,
    'totalCount' => null,
    'noun' => 'data',
])

@php
    $ofType = fn (array $list, array $types) => array_values(array_filter($list, fn ($filter) => in_array($filter->type, $types, true)));
    $controlFilters = array_values(array_filter($filters, fn ($filter) => $filter->hasControl()));
    $selectFilters = $ofType($controlFilters, ['select']);
    $booleanFilters = $ofType($controlFilters, ['boolean']);
    $dateFilters = $ofType($controlFilters, ['date_from', 'date_to']);
    $formatCount = fn (int $number) => number_format($number, 0, ',', '.');
    // Count sentence built here (not with inline @if) so the live region stays one plain text node:
    // "339 riwayat" when nothing is filtered, "12 dari 339 riwayat sesuai filter" otherwise.
    $countLead = '';
    $countRest = '';
    if ($resultCount !== null) {
        if ($hasActiveFilters) {
            $countLead = $formatCount($resultCount);
            $countRest = ($totalCount !== null ? ' dari '.$formatCount($totalCount) : '').' '.$noun.' sesuai filter';
        } else {
            $countLead = $formatCount($totalCount ?? $resultCount);
            $countRest = ' '.$noun;
        }
    }
    $hasPanel = $controlFilters !== [];
    $panelId = $tableId.'-filter-panel';
    $panelCount = count(array_filter($controlFilters, fn ($filter) => (string) ($filterValues[$filter->property] ?? '') !== ''));

    $dateClass = 'w-full px-3 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition shadow-2xs';
    $labelClass = 'mb-1 block text-xs font-medium text-surface-600';
    $chipBase = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
    $chipClass = $chipBase.' bg-surface-100 text-surface-800 border-surface-300 hover:bg-surface-200';
    $resetClass = 'inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-danger-50 hover:text-danger-700 text-surface-600 border border-surface-300 hover:border-danger-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';

    // Chips list every active filter, including those without a control in the panel.
    $activeChips = [];
    foreach (array_merge($ofType($filters, ['select']), $ofType($filters, ['boolean']), $ofType($filters, ['date_from', 'date_to'])) as $filter) {
        $value = (string) ($filterValues[$filter->property] ?? '');
        if ($value === '') {
            continue;
        }
        $activeChips[] = [
            'property' => $filter->property,
            'label' => $filter->label,
            'text' => match ($filter->type) {
                'select' => $filter->label.': '.($filter->options()[$value] ?? $value),
                'boolean' => $filter->label,
                default => $filter->label.': '.$value,
            },
        ];
    }
    $hasTitle = isset($title) && $title->isNotEmpty();
    $searchId = $tableId.'-search';
@endphp

<div x-data="{ open: false }" @keydown.escape="if (open) { open = false; $nextTick(() => $refs.filterToggle?.focus()) }" class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200 space-y-3">
    @if ($hasTitle)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">{{ $title }}</div>
            @if ($export)
                <x-admin.button variant="secondary" size="sm" icon="fas fa-file-excel" wire:click="export" target="export">Ekspor Excel</x-admin.button>
            @endif
        </div>
    @endif

    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <label for="{{ $searchId }}" class="sr-only">Pencarian</label>
        <x-admin.search-input model="search" :id="$searchId" :placeholder="$searchPlaceholder" :shortcut="$searchShortcut" maxlength="100" containerClass="relative flex-1 min-w-0" />

        @if ($hasPanel)
            <button type="button" x-ref="filterToggle" @click="open = ! open" aria-expanded="false" :aria-expanded="open" aria-controls="{{ $panelId }}"
                :class="{ 'border-primary-300 bg-primary-50 text-primary-800': open }"
                class="inline-flex items-center justify-center gap-2 px-3.5 py-2 bg-white hover:bg-surface-100 text-surface-700 border border-surface-300 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                <i class="fas fa-sliders text-[11px]" aria-hidden="true"></i>
                <span>Filter</span>
                @if ($panelCount > 0)
                    <span data-filter-count="{{ $panelCount }}" class="inline-flex min-w-5 h-5 items-center justify-center rounded-full bg-primary-600 px-1.5 text-[10px] font-bold text-white">{{ $panelCount }}<span class="sr-only"> filter aktif</span></span>
                @endif
                <i class="fas fa-chevron-down text-[10px] transition" :class="{ 'rotate-180': open }" aria-hidden="true"></i>
            </button>
        @endif

        @if (! $hasTitle && $export)
            <x-admin.button variant="secondary" size="sm" icon="fas fa-file-excel" wire:click="export" target="export">Ekspor Excel</x-admin.button>
        @endif
    </div>

    @if ($hasPanel)
        <div id="{{ $panelId }}" x-show="open" x-cloak class="rounded-xl border border-surface-200 bg-white p-3 sm:p-4 shadow-2xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($selectFilters as $filter)
                    <x-admin.filter-select
                        :id="$tableId.'-filter-'.$filter->property"
                        :model="$filter->property"
                        :label="$filter->label"
                        :all-label="$filter->allLabel ?? 'Semua '.$filter->label"
                        :options="$filter->options()"
                        :icon="$filter->icon ?? 'fas fa-filter'"
                        :show-label="true"
                        min-width="min-w-0" />
                @endforeach

                @if ($dateFilters !== [])
                    <div class="sm:col-span-2 flex items-end gap-2">
                        @foreach ($dateFilters as $filter)
                            <div class="min-w-0 flex-1">
                                <label for="{{ $tableId.'-filter-'.$filter->property }}" class="{{ $labelClass }}">{{ $filter->label }}</label>
                                <input type="date" id="{{ $tableId.'-filter-'.$filter->property }}" wire:model.live="{{ $filter->property }}" class="{{ $dateClass }}">
                            </div>
                            @if ($filter->type === 'date_from' && ($dateFilters[$loop->index + 1]->type ?? null) === 'date_to')
                                <span class="pb-2 text-xs text-surface-500" aria-hidden="true">–</span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            @if ($booleanFilters !== [])
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @foreach ($booleanFilters as $filter)
                        @php $pressed = ($filterValues[$filter->property] ?? '') === '1'; @endphp
                        <button type="button" wire:key="{{ $tableId }}-chip-{{ $filter->property }}"
                            wire:click="$set('{{ $filter->property }}', '{{ $pressed ? '' : '1' }}')"
                            aria-pressed="{{ $pressed ? 'true' : 'false' }}"
                            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $pressed ? 'bg-primary-50 text-primary-800 border-primary-200 hover:bg-primary-100' : 'bg-white text-surface-700 border-surface-300 hover:bg-surface-100' }}">
                            <i class="{{ $pressed ? 'fas fa-check' : ($filter->icon ?? 'fas fa-filter') }} text-[11px]" aria-hidden="true"></i>
                            <span>{{ $filter->label }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

        </div>
    @endif

    {{-- Summary bar: result count (permanent live region), active filter chips, and Reset on the right. Always visible, panel open or closed. --}}
    @if ($resultCount !== null || $hasActiveFilters)
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            @if ($resultCount !== null)
                <p role="status" aria-live="polite" aria-atomic="true" class="text-xs text-surface-600"><strong class="font-semibold text-surface-900">{{ $countLead }}</strong>{{ $countRest }}</p>
            @endif

            @if ($hasActiveFilters && ($search !== '' || $activeChips !== []))
                <span class="text-xs font-medium text-surface-500">Filter Aktif:</span>
                @if ($search !== '')
                    <button type="button" wire:key="{{ $tableId }}-active-search" wire:click="$set('search', '')" aria-label="Hapus filter Pencarian"
                        class="{{ $chipBase }} bg-primary-50 text-primary-800 border-primary-200 hover:bg-primary-100">
                        <span>Pencarian: {{ $search }}</span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endif
                @foreach ($activeChips as $chip)
                    <button type="button" wire:key="{{ $tableId }}-active-{{ $chip['property'] }}" wire:click="$set('{{ $chip['property'] }}', '')" aria-label="Hapus filter {{ $chip['label'] }}"
                        class="{{ $chipClass }}">
                        <span>{{ $chip['text'] }}</span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endforeach
            @endif

            @if ($hasActiveFilters)
                <button type="button" wire:click="resetFilters" class="{{ $resetClass }} ml-auto">
                    <i class="fas fa-rotate-left text-[11px]" aria-hidden="true"></i>
                    <span>Reset filter</span>
                </button>
            @endif
        </div>
    @endif
</div>
