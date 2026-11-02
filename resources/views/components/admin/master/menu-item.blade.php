{{--
    Butir menu aksi Master Data. Tiga bentuk, satu tampilan:
      - :action  = payload dialog, membuka dialog bersama lewat event "master-action" (lihat action-dialog)
      - href     = tautan navigasi (mis. Kelola Formasi)
      - wire:click="..." = aksi Livewire
    tone danger untuk aksi destruktif; separated memberi garis pemisah di atas butir (wajib sebelum aksi danger).
--}}
@props(['action' => null, 'href' => null, 'icon' => 'fas fa-pencil-alt', 'tone' => 'default', 'separated' => false])

@php
    $classes = 'w-full flex items-center gap-2 px-3 py-2 rounded-lg transition font-medium cursor-pointer text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 '
        .($tone === 'danger' ? 'text-danger-700 hover:bg-danger-50' : 'text-surface-700 hover:bg-surface-50');
@endphp

@if ($separated)
    <div class="my-1 border-t border-surface-100" role="separator"></div>
@endif

@if ($href)
    <a href="{{ $href }}" wire:navigate role="menuitem" tabindex="-1" {{ $attributes->merge(['class' => $classes]) }}>
        <i class="{{ $icon }} w-3.5 {{ $tone === 'danger' ? 'text-danger-700' : 'text-surface-500' }}" aria-hidden="true"></i>
        <span>{{ $slot }}</span>
    </a>
@else
    <button type="button" role="menuitem" tabindex="-1"
        @if ($action) @click="$dispatch('master-action', {{ \Illuminate\Support\Js::from($action) }})" @endif
        {{ $attributes->merge(['class' => $classes]) }}>
        <i class="{{ $icon }} w-3.5 {{ $tone === 'danger' ? 'text-danger-700' : 'text-surface-500' }}" aria-hidden="true"></i>
        <span>{{ $slot }}</span>
    </button>
@endif
