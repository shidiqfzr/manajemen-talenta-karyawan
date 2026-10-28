{{--
    Header kolom tabel Livewire (DESIGN.md §7.5). Varian sortable memakai <button> agar bisa dioperasikan
    dengan keyboard, dan aria-sort pada <th> agar arah urutan terbaca pembaca layar.

    <x-admin.table.th sortable field="jabatan" :sort-field="$sortField" :sort-direction="$sortDirection">Jabatan</x-admin.table.th>
--}}
@props([
    'sortable' => false,
    'field' => null,
    'sortField' => null,
    'sortDirection' => 'asc',
])

@if ($sortable && $field)
    @php $isSorted = $sortField === $field; @endphp
    <th scope="col" @if ($isSorted) aria-sort="{{ $sortDirection === 'asc' ? 'ascending' : 'descending' }}" @endif {{ $attributes->merge(['class' => 'px-4 py-3 text-left']) }}>
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
    <th scope="col" {{ $attributes->merge(['class' => 'px-4 py-3 text-left']) }}>
        {{ $slot }}
    </th>
@endif
