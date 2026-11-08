{{--
    Sel centang baris tabel data Livewire (DESIGN.md §7.5): <input type="checkbox"> sungguhan berlabel nama baris,
    terikat ke properti $selected milik trait WithDataTable. Dua varian, aturan pemicunya sama:

    1) Baris data biasa — kotak centang tersamar yang muncul saat hover, fokus keyboard, tercentang, atau ada pilihan:
       <x-admin.table.select-cell :key="(string) $row->getAttribute($this->rowKey())" :label="$row->nama" />

    2) Baris yang mewakili orang — avatar (foto asli / 2 huruf inisial) yang berubah menjadi kotak centang bulat:
       <x-admin.table.select-cell :key="$employee->nik" :label="$employee->nama" :employee="$employee" />
       Pasangkan dengan <x-admin.table.head avatar-leading ...> agar kotak centang header sejajar dengan avatar.

    Prasyarat: <tr> baris wajib berkelas "group" dan <tbody> tabel membungkus baris (aturan "ada pilihan" memakai
    tbody:has(:checked)). Baris terpilih: lingkaran primary-600 berisi centang; baris lain tetap menampilkan avatar
    (foto/inisial) meski ada pilihan; hover/fokus menampilkan pratinjau centang hijau muda.
--}}
@props(['key', 'label', 'employee' => null])

@if ($employee)
    <td {{ $attributes->merge(['class' => 'w-12 px-0 py-3 text-center whitespace-nowrap']) }}
        @click.stop="$event.target === $el && $el.querySelector('input[type=checkbox]').click()">
        <div class="relative mx-auto h-9 w-9">
            <input type="checkbox" wire:model.live="selected" value="{{ $key }}" aria-label="Pilih {{ $label }}"
                title="{{ $label }} (Klik atau Spasi untuk memilih)"
                class="peer absolute inset-0 z-10 h-9 w-9 appearance-none cursor-pointer rounded-full border-2 border-surface-300 bg-white opacity-0 transition duration-150 checked:border-primary-600 checked:bg-primary-600 checked:opacity-100 group-hover:not-checked:border-primary-300 group-hover:not-checked:bg-primary-50 group-hover:opacity-100 focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500">
            <x-employee-avatar :employee="$employee" size="h-9 w-9" text="text-xs"
                class="pointer-events-none rounded-full border border-surface-200 transition-opacity duration-150 group-hover:opacity-0 [td:has(:checked)_&]:opacity-0 [td:has(:focus-visible)_&]:opacity-0" />
            {{-- Ikon dibungkus span flex agar benar-benar di tengah lingkaran (ikon FontAwesome bukan elemen flex). --}}
            <span class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center text-xs text-primary-500 opacity-0 transition duration-150 group-hover:opacity-100 peer-focus-visible:opacity-100 peer-checked:text-white peer-checked:opacity-100" aria-hidden="true">
                <i class="fas fa-check" aria-hidden="true"></i>
            </span>
        </div>
    </td>
@else
    <td {{ $attributes->merge(['class' => 'w-10 px-4 py-3 align-middle']) }}>
        <input type="checkbox" wire:model.live="selected" value="{{ $key }}" aria-label="Pilih {{ $label }}"
            class="h-4 w-4 rounded border-surface-300 accent-primary-600 cursor-pointer transition-opacity duration-150 opacity-0 [tr:hover_&]:opacity-100 focus-visible:opacity-100 checked:opacity-100 [tbody:has(:checked)_&]:opacity-100 [@media(hover:none)]:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">
    </td>
@endif
