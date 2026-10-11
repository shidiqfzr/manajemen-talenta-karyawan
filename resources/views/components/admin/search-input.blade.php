@props([
    'name' => 'search',
    'id' => null,
    'model' => null,
    'value' => null,
    'placeholder' => 'Cari data...',
    'shortcut' => '/',
    'clearUrl' => null,
    'debounce' => '300ms',
    'containerClass' => 'relative flex-1',
])

@php
    $resolvedId = $id ?? ($model ? 'search_' . str_replace('.', '_', $model) : 'search_' . $name);
    $resolvedValue = $value ?? ($name ? request($name, '') : '');
    $resolvedClearUrl = $clearUrl ?? ($name ? request()->fullUrlWithQuery([$name => null]) : null);
@endphp

<div class="{{ $containerClass }}"
    @if($shortcut)
        @keydown.window="if ($event.key === '{{ $shortcut }}' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.searchInput.focus(); }"
    @endif
    x-data="{
        hasValue: {{ $model ? 'false' : ($resolvedValue ? 'true' : 'false') }},
        init() {
            @if($model)
                this.hasValue = !!(@this.get('{{ $model }}'));
                $watch('$wire.{{ $model }}', val => { this.hasValue = !!val; });
            @else
                this.hasValue = !!$refs.searchInput.value;
            @endif
        },
        clear() {
            @if($model)
                @this.set('{{ $model }}', '');
                this.hasValue = false;
            @else
                @if($resolvedClearUrl)
                    window.location.href = '{{ $resolvedClearUrl }}';
                @else
                    $refs.searchInput.value = '';
                    this.hasValue = false;
                    $refs.searchInput.form?.submit();
                @endif
            @endif
        }
    }">

    <!-- Search Icon -->
    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
        <i class="fas fa-search text-xs"></i>
    </div>

    <!-- Input Field -->
    <input type="text"
        x-ref="searchInput"
        id="{{ $resolvedId }}"
        @if($model)
            wire:model.live.debounce.{{ $debounce }}="{{ $model }}"
        @else
            name="{{ $name }}"
            value="{{ $resolvedValue }}"
            @input="hasValue = !!$event.target.value"
        @endif
        placeholder="{{ $placeholder }}"
        class="w-full pl-10 pr-16 py-2.5 bg-white border border-slate-300 rounded-xl text-xs sm:text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition shadow-2xs">

    <!-- Right-side Actions: Clear Button / Keyboard Shortcut Hint -->
    <div class="absolute inset-y-0 right-0 pr-3 flex items-center gap-1.5">
        <!-- Clear Action (Visible when input has value) -->
        <button type="button"
            x-show="hasValue"
            x-cloak
            @click="clear()"
            class="text-slate-400 hover:text-red-500 transition p-1 cursor-pointer"
            title="Hapus pencarian">
            <i class="fas fa-times-circle text-sm"></i>
        </button>

        @if($shortcut)
            <!-- Shortcut Badge (Visible when empty) -->
            <span x-show="!hasValue"
                @click="$refs.searchInput.focus()"
                class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[10px] font-medium text-slate-400 bg-slate-100 border border-slate-200 rounded shadow-2xs cursor-pointer select-none"
                title="Tekan tombol '{{ $shortcut }}' untuk mencari">
                {{ $shortcut }}
            </span>
        @endif
    </div>
</div>
