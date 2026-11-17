{{--
    Versi Struktur: daftar versi (satu per SK yang diterapkan), lalu struktur pada versi atau tanggal terpilih. Tabel per unit
    di atas komponen tabel bersama (toolbar, bilah judul, paginasi); jabatan dan pemangku dibuka per unit. Baca-saja; NIK tersamar.
--}}
@php
    $report = $this->report;
    $versions = $this->versions;
    $units = $this->pagedUnits;
    $selectedId = $report['decree']->id ?? null;
    $format = fn (int $n) => number_format($n, 0, ',', '.');
    $loading = 'date,search,perPage,compare,selectVersion,gotoPage,previousPage,nextPage';
@endphp
<div class="space-y-6">
    <div class="flex items-start gap-3 rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 text-sm leading-relaxed text-primary-900" role="status">
        <i class="fas fa-circle-info mt-1" aria-hidden="true"></i>
        <p>
            <span class="font-semibold">Setiap SK yang diterapkan menambah satu versi struktur.</span>
            Halaman ini dipakai untuk menelusuri struktur dan pemangku jabatan di masa lalu, misalnya untuk audit atau pemeriksaan. Struktur yang berlaku saat ini dikelola di tab Formasi.
        </p>
    </div>

    {{-- Daftar versi --}}
    <section class="bg-white rounded-2xl border border-surface-200 shadow-xs" aria-labelledby="versions-title">
        <div class="px-5 py-4 border-b border-surface-200">
            <h2 id="versions-title" class="text-base font-bold text-surface-900">Versi Struktur</h2>
            <p class="text-sm text-surface-600">Pilih versi untuk melihat struktur pada hari pertama versi itu berlaku.</p>
        </div>

        @if ($versions === [])
            <div class="px-6 py-10 text-center" role="status">
                <i class="fas fa-box-archive text-2xl text-surface-400 mb-2" aria-hidden="true"></i>
                <p class="font-semibold text-surface-900">Belum ada SK yang diterapkan</p>
                <p class="mt-1 text-sm text-surface-600">Versi pertama muncul setelah SK struktur diterapkan.</p>
            </div>
        @else
            <ul class="divide-y divide-surface-100">
                @foreach ($versions as $version)
                    @php
                        $decree = $version['decree'];
                        $selected = $decree->id === $selectedId;
                    @endphp
                    <li wire:key="version-{{ $decree->id }}" class="flex flex-col sm:flex-row sm:items-center gap-3 px-5 py-3.5 {{ $selected ? 'bg-primary-50/60' : '' }}">
                        <button type="button" wire:click="selectVersion({{ $decree->id }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                            class="flex-1 min-w-0 text-left rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 cursor-pointer">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-surface-900">{{ $decree->sk_number }}</span>
                                @if ($version['is_current'])
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800"><i class="fas fa-circle-check text-[10px]" aria-hidden="true"></i> Berlaku sekarang</span>
                                @endif
                                @if ($selected)
                                    <span class="text-xs font-semibold text-primary-700">Sedang dilihat</span>
                                @endif
                            </span>
                            <span class="block text-sm text-surface-600 truncate" title="{{ $decree->title }}">{{ $decree->title }}</span>
                            <span class="block text-xs text-surface-500 tabular-nums">
                                {{ $version['from']->locale('id')->translatedFormat('d M Y') }} – {{ $version['to'] ? $version['to']->locale('id')->translatedFormat('d M Y') : 'sekarang' }}
                                · {{ $format($version['units']) }} unit · {{ $format($version['seats']) }} jabatan · kuota {{ $format($version['kuota']) }}
                            </span>
                        </button>
                        <x-admin.button href="{{ route('admin.master.sk.show', $decree) }}" variant="secondary" size="sm" icon="fas fa-file-signature" wire:navigate>Lihat SK</x-admin.button>
                    </li>
                @endforeach
            </ul>

            @if (count($versions) === 1)
                <div class="px-5 py-3.5 border-t border-surface-200 bg-surface-50 rounded-b-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm text-surface-600">
                    <p>Baru ada satu versi. Versi berikutnya muncul setelah SK struktur baru dicatat dan diterapkan.</p>
                    <x-admin.button href="{{ route('admin.master.sk.create') }}" variant="secondary" size="sm" icon="fas fa-file-circle-plus" wire:navigate>Catat SK dari Holding</x-admin.button>
                </div>
            @endif
        @endif
    </section>

    @if ($versions !== [])
        {{-- Tanggal tertentu --}}
        <section class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5" aria-labelledby="date-title">
            <h2 id="date-title" class="text-base font-bold text-surface-900">Lihat pada tanggal tertentu</h2>
            <p class="text-sm text-surface-600 mb-4">Untuk pertanyaan seperti "siapa yang menjabat pada 15 Oktober?". Tanggal dicocokkan dengan versi yang berlaku saat itu.</p>
            <div class="flex flex-col md:flex-row md:items-end gap-4">
                <div class="md:w-64">
                    <x-admin.form.input name="date" type="date" label="Tanggal" icon="fas fa-calendar-day" wire:model.live="date" max="{{ now()->toDateString() }}" />
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-surface-700 cursor-pointer md:pb-2.5">
                    <input type="checkbox" wire:model.live="compare" class="rounded border-surface-300 text-primary-600 focus-visible:ring-primary-500">
                    Bandingkan dengan struktur sekarang
                </label>
            </div>
        </section>

        @if ($report === null)
            <div class="bg-white rounded-2xl border border-surface-200 shadow-xs px-6 py-10 text-center" role="status">
                <i class="fas fa-box-archive text-2xl text-surface-400 mb-2" aria-hidden="true"></i>
                <p class="font-semibold text-surface-900">Struktur belum tersedia untuk tanggal ini</p>
                <p class="mt-1 text-sm text-surface-600">Struktur yang tercatat dimulai dari SK pertama yang diterapkan. Pilih tanggal pada atau setelah tanggal berlaku SK tersebut.</p>
            </div>
        @else
            <p class="flex items-start gap-2 text-sm text-surface-700" role="status">
                <i class="fas fa-file-signature mt-1 text-primary-600" aria-hidden="true"></i>
                <span>
                    Struktur pada <span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('d F Y') }}</span>
                    mengacu pada <a href="{{ route('admin.master.sk.show', $report['decree']) }}" class="font-semibold text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">{{ $report['decree']->sk_number }}</a>,
                    berlaku sejak {{ $report['decree']->effective_date->locale('id')->translatedFormat('d F Y') }}.
                </span>
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-admin.kpi-card title="Unit" :value="$report['totals']['units']" subtitle="Unit aktif" icon="fas fa-building" theme="neutral" />
                <x-admin.kpi-card title="Jabatan" :value="$report['totals']['seats']" subtitle="Formasi jabatan" icon="fas fa-table-list" theme="neutral" />
                <x-admin.kpi-card title="Total Kuota" :value="$report['totals']['kuota']" subtitle="Seluruh formasi" icon="fas fa-users" theme="neutral" />
                <x-admin.kpi-card title="Terisi" :value="$report['totals']['filled']" :subtitle="'dari '.$format($report['totals']['kuota']).' kuota'" icon="fas fa-user-check" theme="primary" />
            </div>

            @if ($report['holder_total'] === 0)
                <div class="flex items-start gap-3 rounded-xl border border-surface-200 bg-surface-50 px-4 py-3 text-sm text-surface-700" role="status">
                    <i class="fas fa-circle-info mt-1" aria-hidden="true"></i>
                    <p>Belum ada riwayat jabatan karyawan pada tanggal ini, sehingga seluruh jabatan tampil kosong.</p>
                </div>
            @elseif ($report['outside_structure'] > 0)
                <div class="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900" role="status">
                    <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
                    <p>{{ $format($report['outside_structure']) }} karyawan pada tanggal ini menjabat di unit atau jabatan yang tidak tercantum pada struktur SK (misalnya jabatan penampungan). Mereka tidak ditampilkan pada tabel di bawah.</p>
                </div>
            @endif

            @if ($compare)
                <section class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5" aria-labelledby="compare-title">
                    <h2 id="compare-title" class="text-base font-bold text-surface-900">Selisih dengan struktur sekarang</h2>
                    @if ($this->diff === null || $this->diff->isEmpty())
                        <p class="mt-2 text-sm text-surface-600">Tidak ada perbedaan: struktur pada tanggal ini sama dengan struktur sekarang.</p>
                    @else
                        <p class="mt-1 mb-3 text-sm text-surface-600">Perubahan dari struktur pada tanggal ini menuju struktur sekarang.</p>
                        <x-admin.structure.diff-list :diff="$this->diff" />
                    @endif
                </section>
            @endif

            <x-admin.table.card>
                <x-admin.table.toolbar
                    table-id="structure-versions"
                    :has-active-filters="$search !== ''"
                    :search="$search"
                    search-placeholder="Cari unit, wilayah, atau jabatan..." />
                <x-admin.table.header title="Struktur per Unit" :paginator="$units" noun="Unit" :export="true" />

                <div class="relative overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loading }}">
                        <thead>
                            <tr class="bg-surface-50 border-b border-surface-200">
                                <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Wilayah</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Kuota</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Terisi</x-admin.table.th>
                            </tr>
                        </thead>
                        @forelse ($units as $unit)
                            <tbody wire:key="unit-{{ $units->currentPage() }}-{{ $loop->index }}" x-data="{ open: {{ $search !== '' ? 'true' : 'false' }} }" class="border-b border-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loading }}">
                                <tr class="hover:bg-surface-50/75 transition">
                                    <td class="px-5 py-3">
                                        <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="unit-seats-{{ $units->currentPage() }}-{{ $loop->index }}"
                                            class="inline-flex items-center gap-2 text-left font-semibold text-surface-900 rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 cursor-pointer">
                                            <i class="fas fa-chevron-right text-[10px] text-surface-500 transition-transform" :class="open ? 'rotate-90' : ''" aria-hidden="true"></i>
                                            <span>{{ $unit['unit'] }}</span>
                                            @unless ($unit['is_active'])
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-surface-100 text-surface-700">Nonaktif</span>
                                            @endunless
                                        </button>
                                    </td>
                                    <td class="px-5 py-3 text-surface-600">{{ $unit['wilayah'] ?? '-' }}</td>
                                    <td class="px-5 py-3 tabular-nums">{{ $format($unit['seat_count']) }}</td>
                                    <td class="px-5 py-3 tabular-nums">{{ $format($unit['kuota']) }}</td>
                                    <td class="px-5 py-3 tabular-nums">{{ $format($unit['filled']) }} / {{ $format($unit['kuota']) }}</td>
                                </tr>
                                <tr x-show="open" x-cloak id="unit-seats-{{ $units->currentPage() }}-{{ $loop->index }}">
                                    <td colspan="5" class="px-5 pb-4 pt-0 bg-surface-50/60">
                                        @if ($unit['seats'] === [])
                                            <p class="py-3 text-surface-500">Unit ini tidak memiliki formasi jabatan.</p>
                                        @else
                                            <table class="w-full text-left border-collapse text-xs">
                                                <thead>
                                                    <tr class="border-b border-surface-200">
                                                        <x-admin.table.th class="py-2.5 px-3">Jabatan</x-admin.table.th>
                                                        <x-admin.table.th class="py-2.5 px-3">RM Band</x-admin.table.th>
                                                        <x-admin.table.th class="py-2.5 px-3">Bidang</x-admin.table.th>
                                                        <x-admin.table.th class="py-2.5 px-3">Kuota</x-admin.table.th>
                                                        <x-admin.table.th class="py-2.5 px-3">Pemangku Jabatan</x-admin.table.th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-surface-100">
                                                    @foreach ($unit['seats'] as $seat)
                                                        <tr wire:key="seat-{{ $units->currentPage() }}-{{ $loop->parent->index }}-{{ $loop->index }}" class="align-top">
                                                            <td class="px-3 py-2.5 font-medium text-surface-900">{{ $seat['jabatan'] }}</td>
                                                            <td class="px-3 py-2.5 whitespace-nowrap">{{ $seat['rm_level'] ?? '-' }}</td>
                                                            <td class="px-3 py-2.5 whitespace-nowrap">{{ $seat['bidang'] ?? '-' }}</td>
                                                            <td class="px-3 py-2.5 whitespace-nowrap tabular-nums">{{ $seat['holder_count'] }} / {{ $seat['kuota'] }}</td>
                                                            <td class="px-3 py-2.5">
                                                                @forelse ($seat['holders'] as $holder)
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
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                @if ($search !== '')
                                    <x-admin.table.empty-state :colspan="5" icon="fas fa-filter" message="Tidak ada unit yang cocok dengan pencarian ini.">
                                        <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                                    </x-admin.table.empty-state>
                                @else
                                    <x-admin.table.empty-state :colspan="5" icon="fas fa-sitemap" message="SK ini tidak memuat unit." />
                                @endif
                            </tbody>
                        @endforelse
                        <x-admin.table.skeleton :columns="5" :target="$loading" />
                    </table>
                </div>

                <x-admin.table.pagination :paginator="$units" noun="unit" :per-page-options="$perPageOptions" />
            </x-admin.table.card>
        @endif
    @endif
</div>
