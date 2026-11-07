{{--
    Kepala tabel data Livewire (DESIGN.md §7.5): header sticky, <th scope="col">, sort lewat x-admin.table.th
    (tombol wire:click="sortBy(...)" + aria-sort), dan kolom centang "pilih semua di halaman ini" bila tabel
    punya aksi massal. Kolom khusus ekspor (Column::exportOnly) tidak tampil.

    <x-admin.table.head
        :columns="$this->columns()"
        :sort-field="$sortField"
        :sort-direction="$sortDirection"
        :selectable="$this->bulkActions() !== []"
        :page-keys="$pageKeys"
        :selected="$selected"
        :select-all-matching="$selectAllMatching">
        <x-slot:trailing><x-admin.table.th align="right"><span class="sr-only">Aksi</span></x-admin.table.th></x-slot:trailing>
    </x-admin.table.head>

    page-keys : kunci baris halaman ini sebagai string (mis. $this->rows->getCollection()->map(fn ($r) => (string) $r->getAttribute($this->rowKey()))->all()).
    trailing  : slot opsional untuk header kolom tambahan milik modul (mis. kolom menu aksi).
--}}
@props([
    'columns' => [],
    'sortField' => null,
    'sortDirection' => 'asc',
    'selectable' => false,
    'pageKeys' => [],
    'selected' => [],
    'selectAllMatching' => false,
])

@php
    $visibleColumns = array_values(array_filter($columns, fn ($column) => ! $column->isExportOnly()));
    $selectedOnPage = $selectAllMatching ? count($pageKeys) : count(array_intersect($pageKeys, $selected));
    $pageChecked = $pageKeys !== [] && $selectedOnPage === count($pageKeys);
    $pagePartial = $selectedOnPage > 0 && ! $pageChecked;
    $checkboxState = $pageChecked ? 'all' : ($pagePartial ? 'some' : 'none');
@endphp

<thead class="bg-surface-50 sticky top-0 z-10">
    <tr class="border-b border-surface-200 text-[11px] font-bold text-surface-700 uppercase tracking-wider">
        @if ($selectable)
            <th scope="col" class="w-10 px-4 py-3">
                {{-- wire:key ikut status agar Alpine membaca ulang x-bind:indeterminate setiap kali status berubah. --}}
                <input type="checkbox" wire:key="select-page-{{ md5(implode(',', $pageKeys)) }}-{{ $checkboxState }}"
                    x-data x-bind:indeterminate="{{ $pagePartial ? 'true' : 'false' }}"
                    wire:click="toggleSelectPage" @checked($pageChecked) @disabled($pageKeys === [])
                    aria-label="Pilih semua di halaman ini"
                    class="h-4 w-4 rounded border-surface-300 accent-primary-600 cursor-pointer disabled:cursor-not-allowed focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            </th>
        @endif

        @foreach ($visibleColumns as $column)
            <x-admin.table.th
                :sortable="$column->isSortable()"
                :field="$column->key"
                :sort-field="$sortField"
                :sort-direction="$sortDirection"
                :align="$column->alignment"
                @class(['whitespace-nowrap', 'font-mono tabular-nums' => $column->isMono(), $column->width => $column->width !== null])>{{ $column->label }}</x-admin.table.th>
        @endforeach

        {{ $trailing ?? '' }}
    </tr>
</thead>
