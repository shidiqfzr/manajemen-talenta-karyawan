{{--
    Filter dropdown pada strip toolbar tabel Livewire (DESIGN.md §7.5).

    <x-admin.filter-select id="formasi-filter-rm" model="rmFilter" label="RM Band" all-label="Semua RM Band" icon="fas fa-layer-group" :options="['RM-1' => 'RM-1']" />
    options = [nilai => label]; label dipakai sebagai label tersembunyi untuk pembaca layar.

    show-label : true menampilkan label di atas kolom (untuk panel filter toolbar), sehingga pengguna tahu bidang
                 mana yang disaring; bawaan false (label hanya untuk pembaca layar, keluaran tidak berubah).
    label-icon : bersama show-label, label bergaya Daftar Karyawan (kecil, tebal, dengan ikon di depan teks).
    clearable  : true menampilkan tombol "×" di dalam kolom bila ada pilihan, untuk menghapus pilihan itu saja.
    value      : nilai model saat ini; diperlukan oleh clearable (komponen anonim tidak membaca state Livewire).
--}}
@props(['id', 'model', 'label', 'allLabel', 'options', 'icon' => 'fas fa-filter', 'minWidth' => 'min-w-[150px]', 'showLabel' => false, 'labelIcon' => false, 'clearable' => false, 'value' => ''])

@php $canClear = $clearable && (string) $value !== ''; @endphp

@if ($showLabel)
<div class="flex-1 sm:flex-initial {{ $minWidth }}">
    @if ($labelIcon)
        <label for="{{ $id }}" class="block text-[11px] font-semibold text-surface-700 mb-1"><i class="{{ $icon }} text-surface-400 text-[10px] mr-1" aria-hidden="true"></i> {{ $label }}</label>
    @else
        <label for="{{ $id }}" class="mb-1 block text-xs font-medium text-surface-600">{{ $label }}</label>
    @endif
    <div class="relative">
@else
<div class="relative flex-1 sm:flex-initial {{ $minWidth }}">
    <label for="{{ $id }}" class="sr-only">{{ $label }}</label>
@endif
    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500"><i class="{{ $icon }} text-[11px]" aria-hidden="true"></i></div>
    <select id="{{ $id }}" wire:model.live="{{ $model }}" class="w-full pl-7.5 {{ $canClear ? 'pr-14' : 'pr-7' }} py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs">
        <option value="">{{ $allLabel }}</option>
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($canClear)
        <button type="button" wire:click="$set('{{ $model }}', '')" aria-label="Hapus pilihan {{ $label }}" class="absolute inset-y-0 right-6 flex w-8 items-center justify-center text-surface-400 hover:text-danger cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"><i class="fas fa-times text-[10px]" aria-hidden="true"></i></button>
    @endif
    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500"><i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i></div>
@if ($showLabel)
    </div>
@endif
</div>
