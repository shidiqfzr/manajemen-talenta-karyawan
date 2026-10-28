{{--
    Tombol standar aplikasi (DESIGN.md §6.1). Render <a> bila diberi href, selain itu <button>.

    <x-admin.button variant="primary" type="submit" icon="fas fa-check">Simpan</x-admin.button>
    <x-admin.button href="{{ route('admin.employees.index') }}" variant="secondary" icon="fas fa-arrow-left">Kembali</x-admin.button>

    Perlindungan klik ganda (wajib untuk aksi yang mengubah data):
    - Aksi Livewire : target="save" -> tombol disabled + spinner selama action `save` berjalan.
                      <x-admin.button wire:click="save" target="save">Simpan</x-admin.button>
    - Form biasa    : once -> tombol disabled setelah form benar-benar dikirim (lolos validasi browser).
                      <x-admin.button type="submit" once>Simpan</x-admin.button>
    Varian danger wajib didahului modal konfirmasi.
--}}
@props([
    'variant' => 'primary', // primary, secondary, ghost, danger
    'size' => 'md', // sm, md, lg
    'type' => 'button', // button, submit, reset
    'icon' => null,
    'target' => null, // nama action Livewire yang dipantau (wire:target)
    'once' => false, // nonaktifkan setelah submit form biasa
])

@php
    $baseClasses = 'inline-flex items-center justify-center gap-2 font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed';

    $sizeClasses = [
        'sm' => 'px-3 py-1.5 text-xs rounded-lg',
        'md' => 'px-5 py-2.5 text-sm rounded-xl',
        'lg' => 'px-6 py-3 text-base rounded-xl',
    ][$size] ?? 'px-5 py-2.5 text-sm rounded-xl';

    $variantClasses = [
        'primary' => 'bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white shadow-xs focus-visible:ring-primary-500 border border-transparent',
        'secondary' => 'bg-white hover:bg-surface-50 active:bg-surface-100 text-surface-700 border border-surface-300 shadow-xs focus-visible:ring-primary-500',
        'ghost' => 'hover:bg-surface-100 active:bg-surface-200 text-surface-600 border border-transparent focus-visible:ring-primary-500',
        'danger' => 'bg-danger hover:bg-danger-700 active:bg-danger-800 text-white shadow-xs focus-visible:ring-danger border border-transparent',
    ][$variant] ?? 'bg-primary-600 hover:bg-primary-700 text-white';

    $iconSize = $size === 'sm' ? 'text-[10px]' : 'text-xs';
@endphp

@if(isset($attributes['href']))
<a
    {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}
>
    @if($icon)
        <i class="{{ $icon }} {{ $iconSize }}" aria-hidden="true"></i>
    @endif
    <span>{{ $slot }}</span>
</a>
@else
<button
    type="{{ $type }}"
    @if($target) wire:loading.attr="disabled" wire:target="{{ $target }}" @endif
    @if($once) x-data x-init="$el.form?.addEventListener('submit', () => setTimeout(() => $el.disabled = true, 0))" @endif
    {{ $attributes->merge(['class' => "$baseClasses $sizeClasses $variantClasses"]) }}
>
    @if($icon)
        <i class="{{ $icon }} {{ $iconSize }}" aria-hidden="true" @if($target) wire:loading.remove wire:target="{{ $target }}" @endif></i>
    @endif
    @if($target)
        <i class="fas fa-spinner fa-spin {{ $iconSize }}" aria-hidden="true" wire:loading wire:target="{{ $target }}"></i>
    @endif
    <span>{{ $slot }}</span>
</button>
@endif
