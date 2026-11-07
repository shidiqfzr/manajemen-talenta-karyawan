{{--
    Toolbar pencarian dan filter tabel data Livewire (DESIGN.md §7.5, §8.4), dipakai di dalam x-admin.table.card bersama
    trait WithDataTable. Tata letaknya mengikuti Direktori Pegawai supaya semua tabel terlihat dan terasa sama:
    Baris pencarian: kolom pencarian ringkas (debounce 300ms, maks. 100 karakter), tombol "Filter Lanjutan" berhitungan,
    dan "Reset Filter" (tampil hanya bila ada pencarian atau filter aktif). Kedua tombol berukuran sama.
    Panel filter: kolom berlabel terlihat dengan ikon (pilihan, rentang tanggal, saklar ya-tidak); tertutup secara
    bawaan, Esc menutupnya dan mengembalikan fokus ke tombol. Filter langsung diterapkan saat kolom berubah.
    Baris "Filter Aktif": chip yang dapat dihapus satu per satu; menghapus semuanya cukup dengan "Reset Filter".
    Judul tabel, jumlah data, rentang, dan Ekspor Excel ada di x-admin.table.header tepat di bawah toolbar ini.

    <x-admin.table.toolbar
        :table-id="$this->getId()"
        :filters="$this->filters()"
        :filter-values="$this->activeFilterValues()"
        :has-active-filters="$this->hasActiveFilters()"
        :search="$search"
        search-placeholder="Cari nama pegawai..." />

    table-id        : awalan id unik elemen form (beberapa tabel dapat tampil di satu halaman).
    filters         : list<App\Support\DataTable\Filter>; select -> x-admin.filter-select berlabel dan dapat dihapus,
                      boolean -> saklar, date_from/date_to -> input tanggal berlabel (pasangan dari-sampai berdampingan).
                      Filter yang withoutControl() tidak punya kolom di panel (dikendalikan dari luar) tetapi tetap
                      tampil sebagai chip dan ikut Reset Filter.
    filter-values   : [properti => nilai] filter yang sedang aktif, untuk hitungan tombol, saklar, kolom, dan chip.
    search          : kata kunci pencarian saat ini ($search), untuk chip "Pencarian".
    search-model    : properti Livewire yang menampung kata kunci (bawaan 'search'); isi bila halaman punya lebih dari
                      satu tabel/pencarian. Dipakai untuk kolom pencarian dan chip "Pencarian".
    reset-action    : nama method Livewire untuk "Reset Filter" (bawaan 'resetFilters').
    search-shortcut : tombol pintas fokus pencarian; null untuk tabel sematan agar tidak berebut pintasan.
    Tabel tanpa filter hanya menampilkan pencarian: tidak ada tombol Filter Lanjutan dan tidak ada panel.
--}}
@props([
    'tableId' => 'table',
    'filters' => [],
    'filterValues' => [],
    'hasActiveFilters' => false,
    'search' => '',
    'searchModel' => 'search',
    'resetAction' => 'resetFilters',
    'searchPlaceholder' => 'Cari...',
    'searchShortcut' => '/',
])

@php
    $ofType = fn (array $list, array $types) => array_values(array_filter($list, fn ($filter) => in_array($filter->type, $types, true)));
    $controlFilters = array_values(array_filter($filters, fn ($filter) => $filter->hasControl()));
    $selectFilters = $ofType($controlFilters, ['select']);
    $booleanFilters = $ofType($controlFilters, ['boolean']);
    $dateFilters = $ofType($controlFilters, ['date_from', 'date_to']);
    $hasPanel = $controlFilters !== [];
    $panelId = $tableId.'-filter-panel';
    $panelCount = count(array_filter($controlFilters, fn ($filter) => (string) ($filterValues[$filter->property] ?? '') !== ''));

    $dateClass = 'w-full px-3 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition shadow-2xs';
    $labelClass = 'block text-[11px] font-semibold text-surface-700 mb-1';
    $chipBase = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium border transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
    $chipClass = $chipBase.' bg-surface-100 text-surface-800 border-surface-300 hover:bg-surface-200';

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
            'value' => match ($filter->type) {
                'select' => $filter->options()[$value] ?? $value,
                'boolean' => null,
                default => $value,
            },
        ];
    }
    $searchId = $tableId.'-search';
    // Both toolbar buttons share one width and height so the row reads as a single control group.
    $toolbarButton = 'inline-flex items-center justify-center gap-1.5 h-10 px-2.5 sm:w-40 rounded-xl border border-surface-200 bg-white text-xs font-semibold shadow-2xs shrink-0 cursor-pointer whitespace-nowrap transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500';
@endphp

<div x-data="{ open: false }" @keydown.escape="if (open) { open = false; $nextTick(() => $refs.filterToggle?.focus()) }" class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200 space-y-3">
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        <label for="{{ $searchId }}" class="sr-only">Pencarian</label>
        <x-admin.search-input :model="$searchModel" :id="$searchId" :placeholder="$searchPlaceholder" :shortcut="$searchShortcut" maxlength="100" containerClass="relative w-full sm:w-60 lg:w-72 sm:flex-none min-w-0" />

        @if ($hasPanel)
            <button type="button" x-ref="filterToggle" @click="open = ! open" aria-expanded="false" :aria-expanded="open" aria-controls="{{ $panelId }}"
                class="{{ $toolbarButton }} text-surface-700 hover:bg-surface-50">
                <i class="fas fa-sliders text-primary-600 text-xs" aria-hidden="true"></i>
                <span>Filter Lanjutan</span>
                @if ($panelCount > 0)
                    <span data-filter-count="{{ $panelCount }}" class="inline-flex min-w-5 h-5 items-center justify-center rounded-full bg-primary-600 px-1.5 text-[10px] font-bold text-white">{{ $panelCount }}<span class="sr-only"> filter aktif</span></span>
                @endif
                <i class="fas fa-chevron-down text-[10px] text-surface-400 transition" :class="{ 'rotate-180': open }" aria-hidden="true"></i>
            </button>
        @endif

        @if ($hasActiveFilters)
            <button type="button" wire:click="{{ $resetAction }}" title="Reset semua filter" aria-label="Reset Filter"
                class="{{ $toolbarButton }} text-surface-600 hover:text-danger hover:border-danger-200 hover:bg-danger-50/50">
                <i class="fas fa-rotate-left text-xs" aria-hidden="true"></i>
                <span class="hidden sm:inline">Reset Filter</span>
            </button>
        @endif
    </div>

    @if ($hasPanel)
        <div id="{{ $panelId }}" x-show="open" x-cloak class="pt-4 mt-1 border-t border-surface-200/80 space-y-3.5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                @foreach ($selectFilters as $filter)
                    <x-admin.filter-select
                        :id="$tableId.'-filter-'.$filter->property"
                        :model="$filter->property"
                        :label="$filter->label"
                        :all-label="$filter->allLabel ?? 'Semua '.$filter->label"
                        :options="$filter->options()"
                        :icon="$filter->icon ?? 'fas fa-filter'"
                        :value="(string) ($filterValues[$filter->property] ?? '')"
                        :show-label="true"
                        :label-icon="true"
                        :clearable="true"
                        min-width="min-w-0" />
                @endforeach

                @if ($dateFilters !== [])
                    <div class="sm:col-span-2 flex items-end gap-2">
                        @foreach ($dateFilters as $filter)
                            <div class="min-w-0 flex-1">
                                <label for="{{ $tableId.'-filter-'.$filter->property }}" class="{{ $labelClass }}"><i class="fas fa-calendar text-surface-400 text-[10px] mr-1" aria-hidden="true"></i> {{ $filter->label }}</label>
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
                <div class="flex flex-wrap items-center gap-2">
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

    @if ($hasActiveFilters && ($search !== '' || $activeChips !== []))
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-surface-200">
            <span class="text-xs font-medium text-surface-500">Filter Aktif:</span>
            @if ($search !== '')
                <button type="button" wire:key="{{ $tableId }}-active-search" wire:click="$set('{{ $searchModel }}', '')" aria-label="Hapus filter Pencarian"
                    class="{{ $chipBase }} bg-primary-50 text-primary-800 border-primary-200 hover:bg-primary-100">
                    <span>Pencarian: "<strong>{{ $search }}</strong>"</span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                </button>
            @endif
            @foreach ($activeChips as $chip)
                <button type="button" wire:key="{{ $tableId }}-active-{{ $chip['property'] }}" wire:click="$set('{{ $chip['property'] }}', '')" aria-label="Hapus filter {{ $chip['label'] }}"
                    class="{{ $chipClass }}">
                    @if ($chip['value'] === null)
                        <span>{{ $chip['label'] }}</span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    @else
                        <span>{{ $chip['label'] }}: <strong>{{ $chip['value'] }}</strong></span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>
