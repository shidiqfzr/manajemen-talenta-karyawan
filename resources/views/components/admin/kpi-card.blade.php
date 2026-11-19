{{--
    Kartu KPI/statistik (DESIGN.md §5.3, §6.1).

    <x-admin.kpi-card title="Total Karyawan" :value="$total" icon="fas fa-users" theme="primary" subtitle="Karyawan aktif" />

    theme dipilih menurut arti data: primary · info · earth · teal · warning · danger · neutral.
    Alias lama (sky, blue, rose, red, amber, slate, gray, emerald) hanya untuk kompatibilitas.
    tabKey menjadikan kartu filter tab Alpine; href menjadikannya link.
--}}
@props([
    'title' => null,
    'label' => null,
    'value' => null,
    'xText' => null,
    'subtitle' => null,
    'xSubtitle' => null,
    'icon' => null,
    'hasPulse' => false,
    'theme' => 'primary',
    'badge' => null,
    'href' => null,
    'tabKey' => null,
    'activeTabVar' => 'activeFilterTab',
    'active' => false,
    'coloredValue' => null,
    'bottomBar' => false,
    'class' => '',
])

@php
    $cardTitle = $title ?? $label;
    $isTab = !empty($tabKey);
    $isLink = !empty($href);
    $isInteractive = $isTab || $isLink || $attributes->has('wire:click') || $attributes->has('@click');
    $useColoredValue = $coloredValue ?? $isTab;

    $themeConfig = match ($theme) {
        'primary' => [
            'label' => 'text-primary-700',
            'icon' => 'text-primary-600',
            'iconBg' => 'bg-primary-50 text-primary-700 border border-primary-200/80',
            'value' => 'text-primary-700',
            'sub' => 'text-primary-700/80',
            'badge' => 'bg-primary-50 text-primary-800 border border-primary-200/80',
            'activeCard' => 'border-primary-300 bg-primary-50/50 shadow-xs ring-1 ring-primary-200/50',
            'activeBar' => 'bg-primary-600',
            'hoverInteractive' => 'hover:border-primary-400 hover:shadow-sm hover:-translate-y-0.5',
        ],
        'info', 'sky', 'blue' => [
            'label' => 'text-info-700',
            'icon' => 'text-info',
            'iconBg' => 'bg-info-50 text-info-700 border border-info-200/80',
            'value' => 'text-info-700',
            'sub' => 'text-info-700',
            'badge' => 'bg-info-50 text-info-800 border border-info-200/80',
            'activeCard' => 'border-info-200 bg-info-50/50 shadow-xs ring-1 ring-info-200/50',
            'activeBar' => 'bg-info',
            'hoverInteractive' => 'hover:border-info/50 hover:shadow-sm hover:-translate-y-0.5',
        ],
        'earth' => [
            'label' => 'text-earth-700',
            'icon' => 'text-earth-600',
            'iconBg' => 'bg-earth-50 text-earth-700 border border-earth-200/80',
            'value' => 'text-earth-700',
            'sub' => 'text-earth-700',
            'badge' => 'bg-earth-50 text-earth-800 border border-earth-200/80',
            'activeCard' => 'border-earth-300 bg-earth-50/50 shadow-xs ring-1 ring-earth-200/50',
            'activeBar' => 'bg-earth-600',
            'hoverInteractive' => 'hover:border-earth-400 hover:shadow-sm hover:-translate-y-0.5',
        ],
        'warning', 'amber' => [
            'label' => 'text-warning-700',
            'icon' => 'text-warning',
            'iconBg' => 'bg-warning-50 text-warning-700 border border-warning-200/80',
            'value' => 'text-warning-700',
            'sub' => 'text-warning-700',
            'badge' => 'bg-warning-50 text-warning-800 border border-warning-200/80',
            'activeCard' => 'border-warning-200 bg-warning-50/50 shadow-xs ring-1 ring-warning-200/50',
            'activeBar' => 'bg-warning',
            'hoverInteractive' => 'hover:border-warning/50 hover:shadow-sm hover:-translate-y-0.5',
        ],
        'danger', 'rose', 'red' => [
            'label' => 'text-danger-700',
            'icon' => 'text-danger',
            'iconBg' => 'bg-danger-50 text-danger-700 border border-danger-200/80',
            'value' => 'text-danger-700',
            'sub' => 'text-danger-700',
            'badge' => 'bg-danger-50 text-danger-700 border border-danger-200/80',
            'activeCard' => 'border-danger-200 bg-danger-50/60 shadow-xs ring-1 ring-danger-200/50',
            'activeBar' => 'bg-danger',
            'hoverInteractive' => 'hover:border-danger/50 hover:shadow-sm hover:-translate-y-0.5',
        ],
        'teal' => [
            'label' => 'text-teal-700',
            'icon' => 'text-teal-600',
            'iconBg' => 'bg-teal-50 text-teal-700 border border-teal-200/80',
            'value' => 'text-teal-700',
            'sub' => 'text-teal-700/80',
            'badge' => 'bg-teal-50 text-teal-800 border border-teal-200/80',
            'activeCard' => 'border-teal-300 bg-teal-50/50 shadow-xs ring-1 ring-teal-200/50',
            'activeBar' => 'bg-teal-600',
            'hoverInteractive' => 'hover:border-teal-400 hover:shadow-sm hover:-translate-y-0.5',
        ],
        'neutral', 'slate', 'gray' => [
            'label' => 'text-surface-600',
            'icon' => 'text-surface-500',
            'iconBg' => 'bg-surface-100 text-surface-700 border border-surface-200/80',
            'value' => 'text-surface-900',
            'sub' => 'text-surface-500',
            'badge' => 'bg-surface-100 text-surface-700 border border-surface-200/80',
            'activeCard' => 'border-surface-300 bg-surface-50/80 shadow-xs ring-1 ring-surface-200/50',
            'activeBar' => 'bg-surface-800',
            'hoverInteractive' => 'hover:border-surface-400 hover:shadow-sm hover:-translate-y-0.5',
        ],
        default => [ // emerald, success
            'label' => 'text-primary-700',
            'icon' => 'text-primary-600',
            'iconBg' => 'bg-primary-50 text-primary-700 border border-primary-200/80',
            'value' => 'text-primary-700',
            'sub' => 'text-primary-700/80',
            'badge' => 'bg-primary-50 text-primary-800 border border-primary-200/80',
            'activeCard' => 'border-primary-300 bg-primary-50/50 shadow-xs ring-1 ring-primary-200/50',
            'activeBar' => 'bg-primary-600',
            'hoverInteractive' => 'hover:border-primary-400 hover:shadow-sm hover:-translate-y-0.5',
        ],
    };

    $valueColorClass = $useColoredValue ? $themeConfig['value'] : 'text-surface-900';
    $cursorClass = $isInteractive ? 'cursor-pointer' : 'cursor-default';
    $containerBase = 'relative group rounded-2xl border p-4 sm:p-5 transition-all duration-200 flex flex-col justify-between bg-white text-left overflow-hidden shadow-2xs ' . $cursorClass . ' ' . $class;
    
    $staticActiveClasses = $active 
        ? ($themeConfig['activeCard'] . ($isInteractive ? ' hover:shadow-sm hover:-translate-y-0.5' : ''))
        : ($isInteractive ? ('border-surface-200/80 ' . $themeConfig['hoverInteractive']) : 'border-surface-200/80');
@endphp

@if ($href)
    <a href="{{ $href }}"
        class="{{ $containerBase }} {{ $staticActiveClasses }}"
        {{ $attributes->except('class') }}>
        @if ($active || $bottomBar)
            <span class="absolute bottom-0 left-0 right-0 h-1 {{ $themeConfig['activeBar'] }} rounded-b-2xl"></span>
        @endif
@elseif ($isTab)
    <div @click="setFilterTab('{{ $tabKey }}')"
        class="{{ $containerBase }}"
        :class="{{ $activeTabVar }} === '{{ $tabKey }}' ? '{{ $themeConfig['activeCard'] }} hover:shadow-sm hover:-translate-y-0.5' : 'border-surface-200/80 {{ $themeConfig['hoverInteractive'] }}'"
        {{ $attributes->except('class') }}>
        <span class="absolute bottom-0 left-0 right-0 h-1 {{ $themeConfig['activeBar'] }} rounded-b-2xl transition-opacity duration-150"
            :class="{{ $activeTabVar }} === '{{ $tabKey }}' ? 'opacity-100' : 'opacity-0'"></span>
@else
    <div class="{{ $containerBase }} {{ $staticActiveClasses }}"
        {{ $attributes->except('class') }}>
        @if ($active || $bottomBar)
            <span class="absolute bottom-0 left-0 right-0 h-1 {{ $themeConfig['activeBar'] }} rounded-b-2xl"></span>
        @endif
@endif

    <div>
        <!-- Top Row: Label on Left, Mini Icon Chip or Pulse Dot on Right (Flush Top Aligned) -->
        <div class="flex items-start justify-between gap-2">
            <span class="text-[11px] font-bold uppercase tracking-wide truncate leading-tight {{ $themeConfig['label'] }}">
                {{ $cardTitle }}
            </span>
            @if ($hasPulse)
                <span class="w-2.5 h-2.5 rounded-full bg-primary-500 motion-safe:animate-pulse shrink-0 mt-0.5"></span>
            @elseif ($icon)
                <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs shrink-0 {{ $themeConfig['iconBg'] }} group-hover:scale-105 transition-transform duration-200">
                    <i class="{{ $icon }}" aria-hidden="true"></i>
                </div>
            @endif
        </div>

        <!-- Middle Row: Big Bold Metric Value + Optional Badge -->
        <div class="flex items-baseline gap-2 mt-1.5">
            <span class="text-2xl sm:text-3xl font-black tabular-nums font-mono tracking-tight {{ $valueColorClass }}">
                @if ($xText)
                    <span x-text="{{ $xText }}">0</span>
                @else
                    {{ is_numeric($value) ? number_format($value) : ($value ?? '0') }}
                @endif
            </span>

            @if ($badge)
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-full border shrink-0 {{ $themeConfig['badge'] }}">
                    {{ $badge }}
                </span>
            @endif
        </div>
    </div>

    <!-- Bottom Row: Subtitle or Custom Action Slot (Spans 100% width) -->
    <div class="text-xs mt-2 font-normal truncate {{ $themeConfig['sub'] }}">
        @if ($slot->isNotEmpty())
            {{ $slot }}
        @elseif ($xSubtitle)
            <p class="truncate" x-text="{{ $xSubtitle }}"></p>
        @elseif ($subtitle)
            <p class="text-surface-500 truncate">{{ $subtitle }}</p>
        @endif
    </div>

@if ($href)
    </a>
@else
    </div>
@endif
