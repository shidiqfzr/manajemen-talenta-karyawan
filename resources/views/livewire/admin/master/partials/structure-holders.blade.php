{{-- Daftar pemangku jabatan pada satu formasi: nama, NIK tersamar, dan lencana status penugasan selain Definitif. --}}
@forelse ($holders as $holder)
    <span class="block">
        {{ $holder['nama'] }}
        <span class="font-mono text-surface-500">{{ $holder['nik_masked'] }}</span>
        @if ($holder['status_penugasan'] && $holder['status_penugasan'] !== 'Definitif')
            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-surface-100 text-surface-700">{{ $holder['status_penugasan'] }}</span>
        @endif
    </span>
@empty
    <span class="text-surface-500">Kosong</span>
@endforelse