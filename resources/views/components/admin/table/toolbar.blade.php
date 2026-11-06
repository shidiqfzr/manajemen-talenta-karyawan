{{--
    Toolbar tabel data Livewire (DESIGN.md §7.5), dipakai di dalam x-admin.table.card bersama trait WithDataTable.
    Baris 1: judul opsional + tombol Ekspor Excel di kanan atas (posisi sama di semua modul).
    Baris 2: pencarian (debounce 300ms, maks. 100 karakter), filter dropdown, chip filter ya/tidak, Reset filter.

    <x-admin.table.toolbar
        :table-id="$this->getId()"
        :filters="$this->filters()"
        :filter-values="$this->activeFilterValues()"
        :export="$this->exportName() !== null"
        :has-active-filters="$this->hasActiveFilters()"
        search-placeholder="Cari nama pegawai...">
        <x-slot:title><h2 class="text-sm font-bold text-surface-900">Daftar Pegawai</h2></x-slot:title>
    </x-admin.table.toolbar>

    table-id        : awalan id unik elemen form (beberapa tabel dapat tampil di satu halaman).
    filters         : list<App\Support\DataTable\Filter>; select -> x-admin.filter-select, boolean -> chip.
    filter-values   : [properti => nilai] filter yang sedang aktif, untuk status tekan chip.
    search-shortcut : tombol pintas fokus pencarian; null untuk tabel sematan agar tidak berebut pintasan.
--}}
@props([
    'tableId' => 'table',
    'filters' => [],
    'filterValues' => [],
    'export' => false,
    'hasActiveFilters' => false,
    'searchPlaceholder' => 'Cari...',
    'searchShortcut' => '/',
])

@php
    $selectFilters = array_values(array_filter($filters, fn ($filter) => $filter->type === 'select'));
    $booleanFilters = array_values(array_filter($filters, fn ($filter) => $filter->type === 'boolean'));
    $hasTitle = isset($title) && $title->isNotEmpty();
    $searchId = $tableId.'-search';
@endphp

<div class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200 space-y-3">
    @if ($hasTitle || $export)
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">{{ $title ?? '' }}</div>
            @if ($export)
                <x-admin.button variant="secondary" size="sm" icon="fas fa-file-excel" wire:click="export" target="export">Ekspor Excel</x-admin.button>
            @endif
        </div>
    @endif

    <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2.5">
        <label for="{{ $searchId }}" class="sr-only">Pencarian</label>
        <x-admin.search-input model="search" :id="$searchId" :placeholder="$searchPlaceholder" :shortcut="$searchShortcut" maxlength="100" containerClass="relative flex-1 min-w-[260px]" />

        @if ($selectFilters !== [] || $booleanFilters !== [] || $hasActiveFilters)
            <div class="flex flex-wrap items-center gap-2">
                @foreach ($selectFilters as $filter)
                    <x-admin.filter-select
                        :id="$tableId.'-filter-'.$filter->property"
                        :model="$filter->property"
                        :label="$filter->label"
                        :all-label="$filter->allLabel ?? 'Semua '.$filter->label"
                        :options="$filter->options()"
                        :icon="$filter->icon ?? 'fas fa-filter'" />
                @endforeach

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

                @if ($hasActiveFilters)
                    <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-danger-50 hover:text-danger-700 text-surface-600 border border-surface-300 hover:border-danger-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
                        <i class="fas fa-rotate-left text-[11px]" aria-hidden="true"></i>
                        <span>Reset filter</span>
                    </button>
                @endif
            </div>
        @endif
    </div>
</div>
