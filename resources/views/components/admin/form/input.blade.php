@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => '',
    'required' => false,
    'icon' => null,
    'hint' => null,
])

@php
    $hasError = $errors->has($name);
@endphp
<div>
    @if($label || isset($headerRight))
    <div class="flex items-center justify-between mb-1.5">
        @if($label)
        <label for="{{ $name }}" class="block text-xs font-bold text-surface-700">
            {{ $label }} @if($required) <span class="text-danger">*</span> @endif
        </label>
        @endif
        @if(isset($headerRight))
            {{ $headerRight }}
        @endif
    </div>
    @endif
    
    <div class="relative">
        @if($icon)
        <span class="absolute left-3.5 inset-y-0 flex items-center text-surface-500 text-xs">
            <i class="{{ $icon }}"></i>
        </span>
        @endif
        
        <input 
            type="{{ $type }}" 
            id="{{ $name }}" 
            name="{{ $name }}" 
            value="{{ old($name, $value) }}" 
            {{ $required ? 'required aria-required=true' : '' }}
            @if($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
            {{ $attributes->merge([
                'class' => 'w-full py-2.5 bg-surface-50/70 border '.($hasError ? 'border-danger ring-2 ring-danger/20' : 'border-surface-200').' rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition disabled:bg-surface-100 disabled:text-surface-500 disabled:cursor-not-allowed readonly:bg-surface-100 readonly:text-surface-500 readonly:cursor-not-allowed ' . ($icon ? 'pl-9 pr-3.5' : 'px-3.5')
            ]) }}
        >
    </div>
    
    @if($hint)
        <p class="text-[10px] text-surface-500 mt-1">{!! $hint !!}</p>
    @endif
    
    @error($name) 
        <p id="{{ $name }}-error" class="text-danger-700 text-sm mt-1">{{ $message }}</p> 
    @enderror
</div>
