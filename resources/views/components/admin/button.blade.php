@props([
    'variant' => 'primary', // primary, secondary, ghost, danger
    'size' => 'md', // sm, md, lg
    'type' => 'button', // button, submit, reset
    'icon' => null,
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';
    
    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs rounded-lg',
        'md' => 'px-5 py-2.5 text-sm rounded-xl',
        'lg' => 'px-6 py-3 text-base rounded-xl',
    ][$size] ?? 'px-5 py-2.5 text-sm rounded-xl';

    $variantClasses = [
        'primary' => 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs focus:ring-emerald-500 border border-transparent',
        'secondary' => 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-xs focus:ring-slate-500',
        'ghost' => 'hover:bg-slate-100 text-slate-600 border border-transparent focus:ring-slate-500',
        'danger' => 'bg-red-600 hover:bg-red-700 text-white shadow-xs focus:ring-red-500 border border-transparent',
    ][$variant] ?? 'bg-emerald-600 hover:bg-emerald-700 text-white';
@endphp

@if(isset($attributes['href']))
<a 
    {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}
>
    @if($icon)
        <i class="{{ $icon }} {{ $size === 'sm' ? 'text-[10px]' : 'text-xs' }}"></i>
    @endif
    <span>{{ $slot }}</span>
</a>
@else
<button 
    type="{{ $type }}" 
    {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}
>
    @if($icon)
        <i class="{{ $icon }} {{ $size === 'sm' ? 'text-[10px]' : 'text-xs' }}"></i>
    @endif
    <span>{{ $slot }}</span>
</button>
@endif
