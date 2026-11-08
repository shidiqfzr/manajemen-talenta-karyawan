{{--
    Header kolom tabel Livewire (DESIGN.md §7.5). Varian sortable memakai <button> agar bisa dioperasikan
    dengan keyboard, dan aria-sort pada <th> agar arah urutan terbaca pembaca layar.

    <x-admin.table.th sortable field="jabatan" :sort-field="$sortField" :sort-direction="$sortDirection">Jabatan</x-admin.table.th>
    align = left (bawaan) | center | right.
--}}
@props([
    'sortable' => false,
    'field' => null,
    'sortField' => null,
    'sortDirection' => 'asc',
    'align' => 'left',
])

{{-- Typography lives on the cell itself so every table header renders identically, whatever the <tr> carries. --}}
@php
    $alignClass = (['center' => 'text-center', 'right' => 'text-right'][$align] ?? 'text-left')
        .' font-sans text-[11px] font-bold uppercase tracking-wider text-surface-700 whitespace-nowrap';
    // Padding bawaan hanya dipakai bila modul tidak menetapkan padding sendiri (tabel padat di modal), agar kelas tidak bertabrakan.
    $alignClass .= preg_match('/(^|\s)p[xy]?-/', (string) $attributes->get('class')) ? '' : ' px-4 py-3';
@endphp

@if ($sortable && $field)
    @php $isSorted = $sortField === $field; @endphp
    <th scope="col" @if ($isSorted) aria-sort="{{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}" @endif {{ $attributes->merge(['class' => "{$alignClass}"]) }}>
        <button type="button" wire:click="sortBy('{{ $field }}')"
            class="inline-flex items-center gap-1 rounded [text-transform:inherit] cursor-pointer hover:text-surface-900 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
            <span>{{ $slot }}</span>
            @if ($isSorted)
                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-primary-600" aria-hidden="true"></i>
            @else
                <i class="fas fa-sort text-surface-300" aria-hidden="true"></i>
            @endif
        </button>
    </th>
@else
    <th scope="col" {{ $attributes->merge(['class' => "{$alignClass}"]) }}>
        {{ $slot }}
    </th>
@endif
