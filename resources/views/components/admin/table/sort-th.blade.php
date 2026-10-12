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
        ? ($currDir === 'asc' ? 'fas fa-sort-up text-emerald-600' : 'fas fa-sort-down text-emerald-600')
        : 'fas fa-sort text-slate-300 group-hover:text-slate-500';

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

<th scope="col" {{ $attributes->merge(['class' => "py-3.5 px-5 {$alignmentClass}"]) }}>
    <a href="{{ $sortUrl }}" class="group inline-flex items-center gap-1.5 {{ $linkAlignClass }} hover:text-slate-900 transition cursor-pointer" title="Urutkan berdasarkan {{ $label }}">
        <span>{{ $label }}</span>
        <i class="{{ $iconClass }} text-[11px]"></i>
    </a>
</th>
