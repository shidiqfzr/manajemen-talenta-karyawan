{{--
    Navigasi seksi modul Master Data: satu entri sidebar, empat bagian (masing-masing halaman dengan URL sendiri).
    Tampilan mengikuti tab bergaris bawah modul Mutasi; karena setiap bagian adalah halaman terpisah, semantiknya
    <nav> + aria-current="page" (bukan role="tab", yang untuk panel dalam satu halaman).
--}}
@php
    $sections = [
        ['label' => 'Formasi', 'route' => 'admin.master.formasi.index', 'match' => 'admin.master.formasi.*', 'icon' => 'fa-table-list'],
        ['label' => 'Unit Kerja', 'route' => 'admin.master.units.index', 'match' => 'admin.master.units.*', 'icon' => 'fa-building-flag'],
        ['label' => 'Jabatan & Posisi', 'route' => 'admin.master.positions.index', 'match' => 'admin.master.positions.*', 'icon' => 'fa-id-card-clip'],
        ['label' => 'Riwayat Perubahan', 'route' => 'admin.master.riwayat.index', 'match' => 'admin.master.riwayat.*', 'icon' => 'fa-clock-rotate-left'],
    ];
@endphp
<nav aria-label="Bagian Master Data" class="border-b border-surface-200">
    <ul class="-mb-px flex gap-6 sm:gap-8 overflow-x-auto">
        @foreach ($sections as $section)
            @php($active = request()->routeIs($section['match']))
            <li>
                <a wire:navigate href="{{ route($section['route']) }}" @if ($active) aria-current="page" @endif
                    class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap
                        {{ $active
                            ? 'border-primary-600 text-primary-900 font-bold'
                            : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium' }}">
                    <i class="fas {{ $section['icon'] }} text-xs {{ $active ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600' }}" aria-hidden="true"></i>
                    <span>{{ $section['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
