{{--
    Struktur Organisasi per Tanggal: tabel baca-saja di atas komponen tabel bersama (toolbar, bilah judul, paginasi).
    Pemangku dibaca dari riwayat jabatan; NIK selalu tersamar.
--}}
@php
    $report = $this->report;
    $rows = $this->pagedRows;
    $format = fn (int $n) => number_format($n, 0, ',', '.');
@endphp
<div class="space-y-6">
    <div class="bg-white rounded-2xl border border-surface-200 shadow-xs p-5">
        <div class="flex flex-col md:flex-row md:items-end gap-4">
            <div class="md:w-64">
                <x-admin.form.input name="date" type="date" label="Tanggal Struktur" icon="fas fa-calendar-day" wire:model.live="date" max="{{ now()->toDateString() }}" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-surface-700 cursor-pointer md:pb-2.5">
                <input type="checkbox" wire:model.live="compare" class="rounded border-surface-300 text-primary-600 focus-visible:ring-primary-500">
                Bandingkan dengan struktur sekarang
            </label>
        </div>

        @if ($report)
            <p class="mt-4 flex items-start gap-2 text-sm text-surface-700" role="status">
                <i class="fas fa-file-signature mt-1 text-primary-600" aria-hidden="true"></i>
                <span>
                    Mengacu pada <a href="{{ route('admin.master.sk.show', $report['decree']) }}" class="font-semibold text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">{{ $report['decree']->sk_number }}</a>,
                    berlaku sejak {{ $report['decree']->effective_date->translatedFormat('d F Y') }}.
                    Pemangku jabatan dibaca dari riwayat jabatan karyawan pada tanggal ini.
                </span>
            </p>
        @endif
    </div>

    @if ($report === null)
        <div class="bg-white rounded-2xl border border-surface-200 shadow-xs px-6 py-10 text-center" role="status">
            <i class="fas fa-box-archive text-2xl text-surface-400 mb-2" aria-hidden="true"></i>
            <p class="font-semibold text-surface-900">Struktur belum tersedia untuk tanggal ini</p>
            <p class="mt-1 text-sm text-surface-600">Struktur yang tercatat dimulai dari SK pertama yang diterapkan. Pilih tanggal pada atau setelah tanggal berlaku SK tersebut.</p>
        </div>
    @else
        @if ($report['outside_structure'] > 0)
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

        <div>
            <x-admin.table.card>
                <x-admin.table.toolbar
                    table-id="structure-as-of"
                    :has-active-filters="$search !== ''"
                    :search="$search"
                    search-placeholder="Cari unit, wilayah, atau jabatan..." />
                <x-admin.table.header title="Struktur per Tanggal" :paginator="$rows" noun="Jabatan" :export="true" />

                <div class="relative overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="date,search,perPage,compare,gotoPage,previousPage,nextPage">
                        <thead>
                            <tr class="bg-surface-50 border-b border-surface-200">
                                <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">RM Band</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Bidang</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Kuota</x-admin.table.th>
                                <x-admin.table.th class="py-3.5 px-5">Pemangku Jabatan</x-admin.table.th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-surface-100 text-surface-800" wire:loading.delay.remove wire:target="date,search,perPage,compare,gotoPage,previousPage,nextPage">
                            @forelse ($rows as $row)
                                <tr wire:key="seat-{{ $loop->index }}-{{ $rows->currentPage() }}" class="hover:bg-surface-50/75 transition align-top">
                                    <td class="px-5 py-3 max-w-[260px]">
                                        <span class="block font-semibold text-surface-900">{{ $row['unit'] }}</span>
                                        <span class="block text-surface-500">{{ $row['wilayah'] ?? '-' }}@unless ($row['is_active']) · Nonaktif @endunless</span>
                                    </td>
                                    <td class="px-5 py-3">{{ $row['jabatan'] }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">{{ $row['rm_level'] ?? '-' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap">{{ $row['bidang'] ?? '-' }}</td>
                                    <td class="px-5 py-3 whitespace-nowrap tabular-nums">{{ $row['holder_count'] }} / {{ $row['kuota'] }}</td>
                                    <td class="px-5 py-3">
                                        @forelse ($row['holders'] as $holder)
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
                            @empty
                                @if ($search !== '')
                                    <x-admin.table.empty-state :colspan="6" icon="fas fa-filter" message="Tidak ada jabatan yang cocok dengan pencarian ini.">
                                        <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                                    </x-admin.table.empty-state>
                                @else
                                    <x-admin.table.empty-state :colspan="6" icon="fas fa-sitemap" message="SK ini tidak memuat formasi jabatan." />
                                @endif
                            @endforelse
                        </tbody>
                        <x-admin.table.skeleton :columns="6" target="date,search,perPage,compare,gotoPage,previousPage,nextPage" />
                    </table>
                </div>

                <x-admin.table.pagination :paginator="$rows" noun="jabatan" :per-page-options="$perPageOptions" />
            </x-admin.table.card>
        </div>
    @endif
</div>
