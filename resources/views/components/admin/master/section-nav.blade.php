{{--
    Navigasi Master Data dua tingkat (DESIGN.md §8.5): tiga bagian utama menurut tujuan pengguna, dan pengalih tampilan khusus
    di dalam Formasi.

    Bagian utama (tab bergaris bawah, satu entri sidebar bernama Formasi): Struktur & Formasi (struktur dan formasi jabatan) · Surat Keputusan (alur
    perubahan struktur dari Holding) · Riwayat Perubahan (log audit). Formasi memuat empat tampilan yang saling berkaitan dan
    hanya tampil di bagian itu: Unit Kerja (kelola), Sebaran Jabatan, Jabatan Penampungan, dan Versi Struktur.

    Setiap bagian dan tampilan adalah halaman dengan URL sendiri, maka semantiknya <nav> + aria-current="page" (bukan role="tab",
    yang untuk panel dalam satu halaman).
--}}
@php
    $formasiRoutes = ['admin.master.formasi.*', 'admin.master.sebaran.*', 'admin.master.penampungan.*', 'admin.master.versi.*'];

    $sections = [
        ['label' => 'Struktur & Formasi', 'route' => 'admin.master.formasi.index', 'match' => $formasiRoutes, 'icon' => 'fa-table-list'],
        ['label' => 'Surat Keputusan', 'route' => 'admin.master.sk.index', 'match' => ['admin.master.sk.*'], 'icon' => 'fa-file-signature'],
        ['label' => 'Riwayat Perubahan', 'route' => 'admin.master.riwayat.index', 'match' => ['admin.master.riwayat.*'], 'icon' => 'fa-clock-rotate-left'],
    ];

    $views = [
        ['label' => 'Unit Kerja', 'route' => 'admin.master.formasi.index', 'match' => ['admin.master.formasi.*'], 'icon' => 'fa-building'],
        ['label' => 'Sebaran Jabatan', 'route' => 'admin.master.sebaran.index', 'match' => ['admin.master.sebaran.*'], 'icon' => 'fa-table-cells'],
        ['label' => 'Jabatan Penampungan', 'route' => 'admin.master.penampungan.index', 'match' => ['admin.master.penampungan.*'], 'icon' => 'fa-user-tag'],
        ['label' => 'Versi Struktur', 'route' => 'admin.master.versi.index', 'match' => ['admin.master.versi.*'], 'icon' => 'fa-code-branch'],
    ];

    $inFormasi = request()->routeIs(...$formasiRoutes);
@endphp
<div class="space-y-3">
    <nav aria-label="Bagian Master Data" class="border-b border-surface-200">
        <ul class="-mb-px flex gap-4 sm:gap-8 overflow-x-auto">
            @foreach ($sections as $section)
                @php($active = request()->routeIs(...$section['match']))
                <li>
                    <a wire:navigate href="{{ route($section['route']) }}" @if ($active) aria-current="page" @endif
                        class="group inline-flex items-center gap-1.5 sm:gap-2 py-3 px-0.5 sm:px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap
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

    @if ($inFormasi)
        <nav aria-label="Tampilan Formasi">
            <ul class="flex flex-wrap gap-2">
                @foreach ($views as $view)
                    @php($active = request()->routeIs(...$view['match']))
                    <li>
                        <a wire:navigate href="{{ route($view['route']) }}" @if ($active) aria-current="page" @endif
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500
                                {{ $active ? 'bg-primary-600 border-primary-600 text-white' : 'bg-white border-surface-200 text-surface-700 hover:bg-surface-50' }}">
                            <i class="fas {{ $view['icon'] }} text-[11px]" aria-hidden="true"></i>
                            <span>{{ $view['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif
</div>
