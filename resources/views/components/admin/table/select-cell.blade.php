{{--
    Sel centang baris tabel data Livewire (DESIGN.md §7.5): <input type="checkbox"> sungguhan berlabel nama baris,
    terikat ke properti $selected milik trait WithDataTable.

    <x-admin.table.select-cell :key="(string) $row->getKey()" :label="$row->nama" />
--}}
@props(['key', 'label'])

<td {{ $attributes->merge(['class' => 'w-10 px-4 py-3 align-middle']) }}>
    <input type="checkbox" wire:model.live="selected" value="{{ $key }}" aria-label="Pilih {{ $label }}"
        class="h-4 w-4 rounded border-surface-300 accent-primary-600 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
</td>
