{{--
    Isian teks panjang dengan label tertaut, penghitung batas karakter, dan galat inline (DESIGN.md §7.1).

    <x-admin.form.textarea name="keterangan" label="Keterangan" :value="old('keterangan', $model->keterangan ?? '')" maxlength="5000" />
--}}
@props([
    'name',
    'label' => null,
    'value' => '',
    'required' => false,
    'rows' => 3,
    'maxlength' => null,
    'placeholder' => null,
    'hint' => null,
])

@php
    $hasError = $errors->has($name);
@endphp
<div>
    @if($label)
        <label for="{{ $name }}" class="block text-xs font-bold text-surface-700 mb-1.5">
            {{ $label }} @if($required) <span class="text-danger">*</span> @endif
        </label>
    @endif

    <textarea
        id="{{ $name }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        {{ $required ? 'required aria-required=true' : '' }}
        @if($maxlength) maxlength="{{ $maxlength }}" @endif
        @if($placeholder) placeholder="{{ $placeholder }}" @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
        {{ $attributes->merge([
            'class' => 'w-full px-3.5 py-2.5 bg-surface-50/70 border '.($hasError ? 'border-danger ring-2 ring-danger/20' : 'border-surface-200').' rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition',
        ]) }}
    >{{ old($name, $value) }}</textarea>

    @if($hint)
        <p class="text-[10px] text-surface-500 mt-1">{{ $hint }}</p>
    @endif

    @error($name)
        <p id="{{ $name }}-error" class="text-danger-700 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>
