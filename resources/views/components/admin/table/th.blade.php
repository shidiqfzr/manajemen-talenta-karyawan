@props([
    'sortable' => false,
    'field' => null,
    'sortField' => null,
    'sortDirection' => 'asc',
])

@if ($sortable && $field)
    <th scope="col" wire:click="sortBy('{{ $field }}')" {{ $attributes->merge(['class' => 'px-4 py-3 text-left cursor-pointer hover:bg-gray-100 transition']) }}>
        <div class="flex items-center gap-1">
            <span>{{ $slot }}</span>
            @if ($sortField === $field)
                <i class="fas fa-sort-{{ $sortDirection === 'asc' ? 'up' : 'down' }} text-emerald-600"></i>
            @else
                <i class="fas fa-sort text-gray-300"></i>
            @endif
        </div>
    </th>
@else
    <th scope="col" {{ $attributes->merge(['class' => 'px-4 py-3 text-left']) }}>
        {{ $slot }}
    </th>
@endif
