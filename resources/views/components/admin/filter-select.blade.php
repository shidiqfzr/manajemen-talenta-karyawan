{{--
    Filter dropdown pada strip toolbar tabel Livewire (DESIGN.md §7.5).

    <x-admin.filter-select id="formasi-filter-rm" model="rmFilter" label="RM Band" all-label="Semua RM Band" icon="fas fa-layer-group" :options="['RM-1' => 'RM-1']" />
    options = [nilai => label]; label dipakai sebagai label tersembunyi untuk pembaca layar.

    show-label : true menampilkan label di atas kolom (untuk panel filter toolbar), sehingga pengguna tahu bidang
                 mana yang disaring; bawaan false (label hanya untuk pembaca layar, keluaran tidak berubah).
--}}
@props(['id', 'model', 'label', 'allLabel', 'options', 'icon' => 'fas fa-filter', 'minWidth' => 'min-w-[150px]', 'showLabel' => false])

@if ($showLabel)
<div class="flex-1 sm:flex-initial {{ $minWidth }}">
    <label for="{{ $id }}" class="mb-1 block text-xs font-medium text-surface-600">{{ $label }}</label>
    <div class="relative">
@else
<div class="relative flex-1 sm:flex-initial {{ $minWidth }}">
    <label for="{{ $id }}" class="sr-only">{{ $label }}</label>
@endif
    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500"><i class="{{ $icon }} text-[11px]" aria-hidden="true"></i></div>
    <select id="{{ $id }}" wire:model.live="{{ $model }}" class="w-full pl-7.5 pr-7 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs">
        <option value="">{{ $allLabel }}</option>
        @foreach ($options as $value => $optionLabel)
            <option value="{{ $value }}">{{ $optionLabel }}</option>
        @endforeach
    </select>
    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500"><i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i></div>
@if ($showLabel)
    </div>
@endif
</div>
