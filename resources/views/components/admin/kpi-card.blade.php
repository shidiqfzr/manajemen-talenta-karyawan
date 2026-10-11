@props([
    'title',
    'value',
    'subtitle' => null,
    'icon',
    'theme' => 'primary',
    'badge' => null,
    'href' => null,
    'active' => false,
])

@php
    $themeStyles = match ($theme) {
        'sky' => [
            'iconBg' => 'bg-sky-50 text-sky-700',
            'badge' => 'bg-sky-50 text-sky-700 border border-sky-200/80',
            'activeCard' => 'bg-sky-50/50 border-sky-300/80 shadow-xs ring-1 ring-sky-200/50',
        ],
        'earth', 'amber' => [
            'iconBg' => 'bg-amber-50 text-amber-700',
            'badge' => 'bg-amber-50 text-amber-800 border border-amber-200/80',
            'activeCard' => 'bg-amber-50/50 border-amber-300/80 shadow-xs ring-1 ring-amber-200/50',
        ],
        'purple' => [
            'iconBg' => 'bg-purple-50 text-purple-700',
            'badge' => 'bg-purple-50 text-purple-700',
            'activeCard' => 'bg-purple-50/50 border-purple-300/80 shadow-xs ring-1 ring-purple-200/50',
        ],
        'teal' => [
            'iconBg' => 'bg-teal-50 text-teal-700',
            'badge' => 'bg-teal-50 text-teal-800 border border-teal-200/80',
            'activeCard' => 'bg-teal-50/50 border-teal-300/80 shadow-xs ring-1 ring-teal-200/50',
        ],
        default => [
            'iconBg' => 'bg-emerald-50 text-emerald-700',
            'badge' => 'bg-emerald-50 text-emerald-800 border border-emerald-200/80',
            'activeCard' => 'bg-emerald-50/50 border-emerald-300/80 shadow-xs ring-1 ring-emerald-200/50',
        ],
    };

    $containerClass = $active 
        ? $themeStyles['activeCard'] 
        : 'bg-white border-slate-200/80 shadow-2xs hover:border-slate-300 hover:shadow-xs';
@endphp

@if ($href)
    <a href="{{ $href }}"
        class="group rounded-2xl border p-5 transition duration-200 flex items-center justify-between cursor-pointer {{ $containerClass }}"
        {{ $attributes }}>
@else
    <div class="group rounded-2xl border p-5 transition duration-200 flex items-center justify-between {{ $containerClass }}"
        {{ $attributes }}>
@endif
    <div class="space-y-1 min-w-0 pr-3">
        <p class="text-[11px] font-bold text-slate-500 uppercase tracking-wider truncate">
            {{ $title }}
        </p>

        <div class="flex items-baseline gap-2">
            <span class="text-2xl lg:text-3xl font-extrabold text-slate-900 tabular-nums font-mono tracking-tight">
                {{ is_numeric($value) ? number_format($value) : $value }}
            </span>
            @if ($badge)
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full border {{ $themeStyles['badge'] }}">
                    {{ $badge }}
                </span>
            @endif
        </div>

        @if ($subtitle)
            <p class="text-xs text-slate-400 font-normal truncate mt-0.5">
                {{ $subtitle }}
            </p>
        @endif
    </div>

    <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-lg shrink-0 {{ $themeStyles['iconBg'] }} group-hover:scale-105 transition-transform duration-200">
        <i class="{{ $icon }}"></i>
    </div>
@if ($href)
    </a>
@else
    </div>
@endif
