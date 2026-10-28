@props([
    'column',
    'label',
    'align' => 'left',
])

@php
    $currSort = request('sort', 'nama');
    $currDir = request('direction', 'asc');
    $isActive = $currSort === $column;
    $nextDir = ($isActive && $currDir === 'asc') ? 'desc' : 'asc';
    $sortUrl = request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDir]);
    $iconClass = $isActive
        ? ($currDir === 'asc' ? 'fas fa-sort-up text-primary-600' : 'fas fa-sort-down text-primary-600')
        : 'fas fa-sort text-surface-300 group-hover:text-surface-500';

    $alignmentClass = match($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

    $linkAlignClass = match($align) {
        'center' => 'justify-center w-full',
        'right' => 'justify-end w-full',
        default => '',
    };
@endphp

<th scope="col" @if ($isActive) aria-sort="{{ $currDir === 'asc' ? 'ascending' : 'descending' }}" @endif {{ $attributes->merge(['class' => "py-3.5 px-5 {$alignmentClass}"]) }}>
    <a href="{{ $sortUrl }}" class="group inline-flex items-center gap-1.5 {{ $linkAlignClass }} hover:text-surface-900 transition cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500" title="Urutkan berdasarkan {{ $label }}">
        <span>{{ $label }}</span>
        <i class="{{ $iconClass }} text-[11px]"></i>
    </a>
</th>
