{{-- Sel JUMLAH/TOTAL baris Kebutuhan: angka bersih sebagai nilai utama; rincian kekurangan dan surplus hanya
     tampil bila keduanya ada, supaya surplus satu bidang tidak tersembunyi di balik angka bersih. --}}
<div class="flex flex-col items-center leading-tight">
    <span class="font-bold {{ $net > 0 ? 'text-warning-800' : ($net < 0 ? 'text-primary-700' : 'text-surface-900') }}">{{ $net }}</span>
    @if($shortage > 0 && $surplus > 0)
        <span class="mt-0.5 text-[10px] font-medium text-surface-600 whitespace-nowrap">kurang {{ $shortage }} · surplus {{ $surplus }}</span>
    @endif
</div>
