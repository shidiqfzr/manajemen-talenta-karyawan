{{--
    Satu sel bidang pada matriks MPP.
    @include('livewire.admin.partials.mpp-cell', ['row' => $row, 'rm' => 'RM-1', 'field' => 'KEU', 'val' => 3])

    Angka selalu tampil; sel hanya dapat diklik bila barisnya punya rincian (kunci 'drill' dari service):
    daftar personil yang persis dihitung sel itu, rincian rencana vs realisasi pasokan, atau rincian rumus
    Kebutuhan. Angka 0 tampil sebagai "-", kecuali sel pasokan yang sisanya 0 karena sudah terealisasi.
--}}
@php
    $cellId = $row['no'].'-'.$rm.'-'.$field;
    $isKebutuhan = $row['highlight'] === 'kebutuhan';
    $isClickable = $row['drill'] !== null && ($row['cell_drillable'][$rm][$field] ?? $val !== 0);
    $valueClass = $isKebutuhan
        ? ($val < 0 ? 'text-primary-700 font-bold' : 'text-warning-800 font-bold')
        : 'text-surface-800 font-medium';
@endphp
@if($val === 0 && ! $isClickable)
    <td class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200 select-none">
        <span class="text-surface-300" data-cell="{{ $cellId }}">-</span>
    </td>
@elseif($isClickable)
    <td wire:click="openDrilldown('{{ $rm }}', '{{ $field }}', {{ $row['no'] }})"
        class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200 cursor-pointer hover:bg-primary-50 hover:text-primary-800 transition"
        title="{{ match (true) { $row['drill'] === 'formula' => 'Klik untuk melihat rincian rumus', str_starts_with($row['drill'], 'supply_') => 'Klik untuk melihat rencana dan realisasi', default => 'Klik untuk melihat rincian personil' } }}">
        <span class="{{ $valueClass }} {{ $isKebutuhan ? '' : 'underline decoration-dotted decoration-surface-300 underline-offset-2' }}" data-cell="{{ $cellId }}">{{ $val }}</span>
    </td>
@else
    <td class="px-2 py-2 text-center font-mono tabular-nums border-r border-surface-200">
        <span class="{{ $valueClass }}" data-cell="{{ $cellId }}">{{ $val }}</span>
    </td>
@endif
