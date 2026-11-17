{{--
    Versi Struktur: satu kendali waktu (versi atau tanggal), lalu ringkasan dan tabel yang bisa dibaca per jabatan (bawaan, datar)
    atau per unit (dikelompokkan dan dapat dibuka). Perbandingan antarversi dan riwayat jabatan karyawan ada pada tab terpisah.
    Baca-saja; NIK tersamar. Komponen tabel bersama: toolbar, bilah judul, paginasi.
--}}
@php
    $report = $this->report;
    $versions = $this->versions;
    $rows = $this->pagedRows;
    $format = fn (int $n) => number_format($n, 0, ',', '.');
    $day = fn ($value) => $value->locale('id')->translatedFormat('d M Y');
    $loading = 'date,version,search,perPage,compare,view,wilayahFilter,rmFilter,bidangFilter,isiFilter,gotoPage,previousPage,nextPage';
    $tabs = ['struktur' => 'Struktur', 'karyawan' => 'Riwayat Jabatan Karyawan'];
    $selectClass = 'w-full h-10 pl-3 pr-8 appearance-none bg-surface-50/70 select-chevron border border-surface-200 rounded-lg text-xs sm:text-sm font-medium text-surface-800 focus:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/30 focus-visible:border-primary-500 transition cursor-pointer';
@endphp
<div class="space-y-6">
    <nav class="flex flex-wrap items-center gap-1 border-b border-surface-200" aria-label="Tampilan Versi Struktur">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:key="vtab-{{ $key }}" wire:click="$set('tab', '{{ $key }}')" @if ($tab === $key) aria-current="page" @endif
                class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px rounded-t cursor-pointer transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $tab === $key ? 'border-primary-600 text-primary-800' : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300' }}">{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'karyawan')
        <livewire:admin.master.structure-employee-timeline wire:key="employee-timeline" />
    @elseif ($versions === [])
        <div class="bg-white rounded-2xl border border-surface-200 shadow-xs px-6 py-10 text-center" role="status">
            <i class="fas fa-box-archive text-2xl text-surface-400 mb-2" aria-hidden="true"></i>
            <p class="font-semibold text-surface-900">Belum ada SK yang diterapkan</p>
            <p class="mt-1 text-sm text-surface-600">Versi pertama muncul setelah SK struktur diterapkan.</p>
        </div>
    @else
        {{-- Satu kendali waktu: versi dan tanggal saling mengikuti; pembanding memakai pilihan yang sama. --}}
        <section class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5" aria-label="Pilih waktu struktur">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-6">
                    <label for="version" class="block text-xs font-bold text-surface-700 mb-1.5">Versi Struktur</label>
                    <select id="version" wire:model.live="version" class="{{ $selectClass }}">
                        @if ($version === '')
                            <option value="">Sebelum SK pertama</option>
                        @endif
                        @foreach ($versions as $item)
                            <option value="{{ $item['decree']->id }}">{{ $item['decree']->sk_number }} · {{ $day($item['from']) }} – {{ $item['to'] ? $day($item['to']) : 'sekarang' }}{{ $item['is_current'] ? ' (berlaku sekarang)' : '' }}</option>
                        @endforeach
                    </select>
                    @if (count($versions) === 1)
                        <p class="mt-1 text-[11px] text-surface-600">Baru ada satu versi. Versi berikutnya muncul setelah SK struktur baru diterapkan. <a href="{{ route('admin.master.sk.create') }}" wire:navigate class="font-semibold text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">Catat SK dari Holding</a></p>
                    @endif
                </div>
                <div class="md:col-span-3">
                    <x-admin.form.input name="date" type="date" label="atau Tanggal" icon="fas fa-calendar-day" wire:model.live="date" max="{{ now()->toDateString() }}" />
                </div>
                <div class="md:col-span-3">
                    <label for="compare" class="block text-xs font-bold text-surface-700 mb-1.5">Bandingkan dengan</label>
                    <select id="compare" wire:model.live="compare" class="{{ $selectClass }}" @disabled($report === null)>
                        <option value="">Tanpa pembanding</option>
                        @foreach ($this->compareOptions as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($report)
                <p class="mt-4 flex items-start gap-2 text-sm text-surface-700" role="status">
                    <i class="fas fa-file-signature mt-1 text-primary-600" aria-hidden="true"></i>
                    <span>
                        Struktur pada <span class="font-semibold">{{ \Illuminate\Support\Carbon::parse($date)->locale('id')->translatedFormat('d F Y') }}</span>
                        mengacu pada <a href="{{ route('admin.master.sk.show', $report['decree']) }}" class="font-semibold text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">{{ $report['decree']->sk_number }}</a>,
                        berlaku sejak {{ $report['decree']->effective_date->locale('id')->translatedFormat('d F Y') }}.
                    </span>
                </p>
            @endif
        </section>

        @if ($report === null)
            <div class="bg-white rounded-2xl border border-surface-200 shadow-xs px-6 py-10 text-center" role="status">
                <i class="fas fa-box-archive text-2xl text-surface-400 mb-2" aria-hidden="true"></i>
                <p class="font-semibold text-surface-900">Struktur belum tersedia untuk tanggal ini</p>
                <p class="mt-1 text-sm text-surface-600">Struktur yang tercatat dimulai dari SK pertama yang diterapkan. Pilih tanggal pada atau setelah tanggal berlaku SK tersebut.</p>
            </div>
        @else
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

            @if ($compare !== '')
                <section class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5" aria-labelledby="compare-title">
                    <h2 id="compare-title" class="text-base font-bold text-surface-900">Selisih dengan {{ $this->compareLabel }}</h2>
                    @if ($this->diff === null || $this->diff->isEmpty())
                        <p class="mt-2 text-sm text-surface-600">Tidak ada perbedaan: struktur pada tanggal ini sama dengan {{ $this->compareLabel }}.</p>
                    @else
                        <p class="mt-1 mb-3 text-sm text-surface-600">Perubahan dari struktur pada tanggal ini menuju {{ $this->compareLabel }}.</p>
                        <x-admin.structure.diff-list :diff="$this->diff" />
                    @endif
                </section>
            @endif

            <x-admin.table.card>
                <x-admin.table.toolbar
                    table-id="structure-versions"
                    :filters="$this->filters()"
                    :filter-values="$this->activeFilterValues()"
                    :has-active-filters="$this->hasActiveFilters()"
                    :search="$search"
                    search-placeholder="Cari unit, wilayah, jabatan, atau nama pemangku..." />
                <x-admin.table.header :title="$view === 'unit' ? 'Struktur per Unit' : 'Daftar Jabatan'" :paginator="$rows" :noun="$view === 'unit' ? 'Unit' : 'Jabatan'" :export="true">
                    <x-slot:actions>
                        <x-admin.segmented label="Tampilan tabel" :options="['jabatan' => 'Per Jabatan', 'unit' => 'Per Unit']" :current="$view" />
                    </x-slot:actions>
                </x-admin.table.header>

                @if ($view === 'unit')
                    <div class="flex items-center gap-2 px-5 py-2.5 border-b border-surface-200 bg-white text-xs">
                        <button type="button" @click="$dispatch('units-toggle', true)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-surface-300 bg-white font-semibold text-surface-700 hover:bg-surface-50 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"><i class="fas fa-angles-down text-[10px]" aria-hidden="true"></i> Buka semua unit</button>
                        <button type="button" @click="$dispatch('units-toggle', false)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-surface-300 bg-white font-semibold text-surface-700 hover:bg-surface-50 cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"><i class="fas fa-angles-up text-[10px]" aria-hidden="true"></i> Tutup semua unit</button>
                    </div>
                @endif

                <div class="relative overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loading }}">
                        @if ($view === 'unit')
                            <thead>
                                <tr class="bg-surface-50 border-b border-surface-200">
                                    <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Wilayah</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Kuota</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Terisi</x-admin.table.th>
                                </tr>
                            </thead>
                            @forelse ($rows as $unit)
                                @php $key = $rows->currentPage().'-'.$loop->index; @endphp
                                <tbody wire:key="unit-{{ $key }}" x-data="{ open: {{ $search !== '' ? 'true' : 'false' }} }" @units-toggle.window="open = $event.detail" class="border-b border-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loading }}">
                                    <tr class="hover:bg-surface-50/75 transition">
                                        <td class="px-5 py-3">
                                            <button type="button" @click="open = ! open" :aria-expanded="open.toString()" aria-controls="unit-seats-{{ $key }}"
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
                                    <tr x-show="open" x-cloak id="unit-seats-{{ $key }}">
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
                                                            <tr wire:key="seat-{{ $key }}-{{ $loop->index }}" class="align-top">
                                                                <td class="px-3 py-2.5 font-medium text-surface-900">{{ $seat['jabatan'] }}</td>
                                                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $seat['rm_level'] ?? '-' }}</td>
                                                                <td class="px-3 py-2.5 whitespace-nowrap">{{ $seat['bidang'] ?? '-' }}</td>
                                                                <td class="px-3 py-2.5 whitespace-nowrap tabular-nums">{{ $seat['holder_count'] }} / {{ $seat['kuota'] }}@if ($seat['holder_count'] > $seat['kuota'])<span class="ml-1 text-warning-800" title="Melebihi kuota"><i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i><span class="sr-only"> melebihi kuota</span></span>@endif</td>
                                                                <td class="px-3 py-2.5">@include('livewire.admin.master.partials.structure-holders', ['holders' => $seat['holders']])</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            @empty
                                <tbody>@include('livewire.admin.master.partials.structure-empty', ['colspan' => 5, 'search' => $this->hasActiveFilters()])</tbody>
                            @endforelse
                        @else
                            <thead>
                                <tr class="bg-surface-50 border-b border-surface-200">
                                    <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">RM Band</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Bidang</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Terisi</x-admin.table.th>
                                    <x-admin.table.th class="py-3.5 px-5">Pemangku Jabatan</x-admin.table.th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loading }}">
                                @forelse ($rows as $row)
                                    <tr wire:key="row-{{ $rows->currentPage() }}-{{ $loop->index }}" class="hover:bg-surface-50/75 transition align-top">
                                        <td class="px-5 py-3 max-w-[260px]">
                                            <span class="block font-semibold text-surface-900">{{ $row['unit'] }}</span>
                                            <span class="block text-surface-500">{{ $row['wilayah'] ?? '-' }}@unless ($row['is_active']) · Nonaktif @endunless</span>
                                        </td>
                                        <td class="px-5 py-3 font-medium text-surface-900">{{ $row['jabatan'] }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap">{{ $row['rm_level'] ?? '-' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap">{{ $row['bidang'] ?? '-' }}</td>
                                        <td class="px-5 py-3 whitespace-nowrap tabular-nums">{{ $row['holder_count'] }} / {{ $row['kuota'] }}@if ($row['holder_count'] > $row['kuota'])<span class="ml-1 text-warning-800" title="Melebihi kuota"><i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i><span class="sr-only"> melebihi kuota</span></span>@endif</td>
                                        <td class="px-5 py-3">@include('livewire.admin.master.partials.structure-holders', ['holders' => $row['holders']])</td>
                                    </tr>
                                @empty
                                    @include('livewire.admin.master.partials.structure-empty', ['colspan' => 6, 'search' => $this->hasActiveFilters()])
                                @endforelse
                            </tbody>
                        @endif
                        <x-admin.table.skeleton :columns="$view === 'unit' ? 5 : 6" :target="$loading" />
                    </table>
                </div>

                <x-admin.table.pagination :paginator="$rows" :noun="$view === 'unit' ? 'unit' : 'jabatan'" :per-page-options="$perPageOptions" />
            </x-admin.table.card>
        @endif
    @endif
</div>
