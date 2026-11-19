{{--
    Kerangka pemuatan tabel data Livewire (DESIGN.md §7.5: tabel tidak boleh kosong sesaat tanpa indikasi).
    Tampil menggantikan <tbody> modul selama request pencarian, filter, sort, atau pindah halaman.

    <x-admin.table.skeleton :columns="4" :target="$loadingTargets" />

    columns : jumlah kolom yang tampil (termasuk kolom centang dan kolom aksi).
    target  : daftar wire:target yang SAMA dengan <tbody wire:loading.delay.remove> milik modul, agar keduanya
              bertukar pada saat yang sama.
--}}
@props(['columns' => 1, 'rows' => 5, 'target' => ''])

<tbody class="hidden divide-y divide-surface-100" aria-hidden="true" wire:loading.delay.class.remove="hidden"@if ($target !== '') wire:target="{{ $target }}"@endif>
    @for ($row = 0; $row < $rows; $row++)
        <tr>
            @for ($column = 0; $column < $columns; $column++)
                <td class="px-4 py-3.5">
                    <div class="h-3 rounded bg-surface-100 motion-safe:animate-pulse {{ $column === 0 ? 'w-3/4' : 'w-1/2' }}"></div>
                </td>
            @endfor
        </tr>
    @endfor
</tbody>
