@props([
    'name',
    'label' => null,
    'options' => [], // Flat array or key-value array
    'selected' => '',
    'required' => false,
    'icon' => null,
    'hint' => null,
    'placeholder' => '-- Pilih --'
])

<div>
    @if($label || isset($headerRight))
    <div class="flex items-center justify-between mb-1.5">
        @if($label)
        <label for="{{ $name }}" class="block text-xs font-bold text-slate-700">
            {{ $label }} @if($required) <span class="text-red-500">*</span> @endif
        </label>
        @endif
        @if(isset($headerRight))
            {{ $headerRight }}
        @endif
    </div>
    @endif
    
    <div class="relative">
        @if($icon)
        <span class="absolute left-3.5 inset-y-0 flex items-center text-slate-400 text-xs">
            <i class="{{ $icon }}"></i>
        </span>
        @endif
        
        <select 
            id="{{ $name }}" 
            name="{{ $name }}" 
            {{ $required ? 'required' : '' }}
            {{ $attributes->merge([
                'class' => 'w-full py-2.5 bg-slate-50/70 border border-slate-200 rounded-xl text-xs sm:text-sm font-medium text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition appearance-none disabled:bg-slate-100 disabled:text-slate-500 disabled:cursor-not-allowed ' . ($icon ? 'pl-9 pr-8' : 'px-3.5 pr-8')
            ]) }}
        >
            @if($placeholder)
                <option value="">{{ $placeholder }}</option>
            @endif
            
            @foreach($options as $val => $text)
                @php
                    $isIndexed = array_keys($options) === range(0, count($options) - 1);
                    $optValue = $isIndexed ? $text : $val;
                    $optSelected = old($name, $selected) == $optValue;
                @endphp
                <option value="{{ $optValue }}" {{ $optSelected ? 'selected' : '' }}>
                    {{ $text }}
                </option>
            @endforeach
        </select>
        
        <div class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-slate-400">
            <i class="fas fa-chevron-down text-[10px]"></i>
        </div>
    </div>
    
    @if($hint)
        <p class="text-[10px] text-slate-400 mt-1">{!! $hint !!}</p>
    @endif
    
    @error($name) 
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p> 
    @enderror
</div>
