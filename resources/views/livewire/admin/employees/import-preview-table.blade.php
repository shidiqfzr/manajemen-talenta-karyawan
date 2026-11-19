{{-- Tabel pratinjau impor (tahap 2): koleksi di memori pada komponen bersama x-admin.table.* (DESIGN.md §8.4). --}}
<div>
    @php
        // Daftar target yang sama untuk baris langsung dan kerangka muat, agar keduanya bertukar bersamaan.
        $loadingTargets = 'search,perPage,resetFilters,gotoPage,nextPage,previousPage,setPage,setTab,load';
        $columnCount = 5;
    @endphp

    <x-admin.table.card>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="[]"
            :filter-values="[]"
            :has-active-filters="$search !== ''"
            :search="$search"
            :search-shortcut="null"
            search-placeholder="Cari NIK, nama, jabatan, atau unit kerja..." />
        <x-admin.table.header :title="$title" :paginator="$rows" noun="Baris" />

        @if ($isErrors && $failedCount > 0)
            <div class="px-5 py-3 border-b border-danger-200 bg-danger-50 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs" role="status">
                <div class="flex items-start gap-3 text-surface-800">
                    <i class="fas fa-triangle-exclamation text-danger-700 mt-0.5" aria-hidden="true"></i>
                    <div>
                        <p class="font-semibold text-surface-900">{{ number_format($failedCount, 0, ',', '.') }} baris terkendala validasi dan tidak diimpor.</p>
                        <p class="text-surface-700">Unduh berkas koreksi untuk memeriksa dan memperbaiki data di Excel, lalu unggah kembali.</p>
                    </div>
                </div>
                <form action="{{ route('admin.employees.import.downloadFailed') }}" method="POST" target="_blank" class="shrink-0 w-full sm:w-auto">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <x-admin.button type="submit" variant="secondary" size="sm" icon="fas fa-file-arrow-down" class="w-full sm:w-auto">Unduh Berkas Koreksi</x-admin.button>
                </form>
            </div>
        @endif

        <div class="relative overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200">
                        @if ($isErrors)
                            <x-admin.table.th class="px-4 py-3.5 w-20">Baris</x-admin.table.th>
                            <x-admin.table.th class="px-4 py-3.5 w-[22%]">Karyawan Terkait</x-admin.table.th>
                            <x-admin.table.th class="px-4 py-3.5 w-[24%]">Formasi &amp; Penempatan</x-admin.table.th>
                            <x-admin.table.th align="center" class="px-4 py-3.5 w-[12%]">Status</x-admin.table.th>
                            <x-admin.table.th class="px-4 py-3.5">Penyebab Kendala</x-admin.table.th>
                        @else
                            <x-admin.table.th class="px-4 py-3.5 w-[26%]">Karyawan</x-admin.table.th>
                            <x-admin.table.th class="px-4 py-3.5 w-[30%]">Formasi &amp; Penempatan</x-admin.table.th>
                            <x-admin.table.th class="px-4 py-3.5 w-[18%]">Strata &amp; Golongan</x-admin.table.th>
                            <x-admin.table.th align="center" class="px-4 py-3.5 w-[12%]">Status Aksi</x-admin.table.th>
                            <x-admin.table.th align="right" class="px-4 py-3.5 w-[14%]">Rincian Perubahan</x-admin.table.th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($rows as $row)
                        @if ($isErrors)
                            <tr wire:key="err-{{ $rows->currentPage() }}-{{ $loop->index }}" class="align-top hover:bg-surface-50/75 transition">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-surface-100 text-surface-700 border border-surface-200 font-mono font-semibold tabular-nums">{{ $row['row'] ?? '-' }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-surface-900">{{ ($row['nama'] ?? '') !== '' ? $row['nama'] : '-' }}</div>
                                    <div class="text-surface-500 font-mono tabular-nums mt-0.5">NIK: {{ $this->maskedNik($row['nik'] ?? '') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-surface-800 leading-snug">{{ ! in_array($row['jabatan'] ?? '', ['', '-'], true) ? $row['jabatan'] : 'Posisi tidak terbaca' }}</div>
                                    @if (! in_array($row['unit_kerja'] ?? '', ['', '-'], true))
                                        <div class="text-surface-500 mt-0.5 flex items-center gap-1">
                                            <i class="fas fa-building text-[10px] text-surface-400" aria-hidden="true"></i>
                                            <span>{{ $row['unit_kerja'] }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-danger-100 text-danger-800 border border-danger-200 font-semibold text-[10px] uppercase tracking-wider">
                                        <i class="fas fa-circle-exclamation text-[10px]" aria-hidden="true"></i> Perlu Koreksi
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1">
                                        @foreach ((array) ($row['errors'] ?? []) as $message)
                                            <div class="flex items-start gap-1.5 text-danger-800 font-medium leading-relaxed">
                                                <i class="fas fa-circle-exclamation text-[11px] mt-0.5 shrink-0 text-danger" aria-hidden="true"></i>
                                                <span>{{ $message }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @else
                            <tr wire:key="row-{{ $rows->currentPage() }}-{{ $loop->index }}" class="align-top hover:bg-surface-50/75 transition">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-surface-900">{{ $row['nama'] }}</div>
                                    <div class="text-surface-500 font-mono tabular-nums mt-0.5">NIK: {{ $this->maskedNik($row['nik'] ?? '') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-surface-800 leading-snug">{{ $row['jabatan'] }}</div>
                                    <div class="text-surface-500 mt-0.5 flex items-center gap-1">
                                        <i class="fas fa-building text-[10px] text-surface-400" aria-hidden="true"></i>
                                        <span>{{ $row['unit_kerja'] }}</span>
                                    </div>
                                    @if (! empty($row['warnings']))
                                        <div class="mt-1 flex items-start gap-1 text-[11px] font-medium text-warning-800 leading-snug" title="{{ implode(' ', $row['warnings']) }}">
                                            <i class="fas fa-triangle-exclamation mt-0.5 text-warning-700" aria-hidden="true"></i>
                                            <span>{{ $row['warnings'][0] }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-surface-800">{{ $row['level'] ?: '-' }}</div>
                                    <div class="text-surface-500 font-mono tabular-nums mt-0.5">
                                        <span class="font-semibold text-surface-700">{{ ($row['golongan'] ?? '-') ?: '-' }}</span>
                                        <span aria-hidden="true" class="mx-1">&bull;</span>
                                        <span class="font-sans text-surface-600">{{ ($row['rm_level'] ?? '-') ?: '-' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if ($row['action'] === 'CREATE')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-primary-100 text-primary-800 border border-primary-200 font-semibold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-plus text-[10px]" aria-hidden="true"></i> Baru
                                        </span>
                                    @elseif ($row['action'] === 'UPDATE')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-warning-100 text-warning-800 border border-warning-200 font-semibold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-pen text-[10px]" aria-hidden="true"></i> Update
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-surface-100 text-surface-700 border border-surface-200 font-semibold text-[10px] uppercase tracking-wider">
                                            <i class="fas fa-check text-[10px]" aria-hidden="true"></i> Tetap
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @if (! empty($row['diff']))
                                        @php
                                            // Hanya baris ini yang dikirim ke modal: NIK samaran, nilai sensitif sudah disamarkan oleh pengimpor.
                                            $detail = ['nama' => $row['nama'], 'nik' => $this->maskedNik($row['nik'] ?? ''), 'diff' => $row['diff']];
                                        @endphp
                                        <div class="flex flex-col items-end gap-1">
                                            <button type="button" x-on:click="$dispatch('open-import-diff', @js($detail))"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-warning-50 hover:bg-warning-100 text-warning-800 border border-warning-200 font-semibold transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                title="Inspeksi {{ count($row['diff']) }} perbedaan data">
                                                <i class="fas fa-pen-to-square text-[10px] text-warning-700" aria-hidden="true"></i>
                                                <span>{{ count($row['diff']) }} Perubahan</span>
                                                <span class="text-[10px]">Detail &rarr;</span>
                                            </button>
                                            <span class="text-[11px] text-surface-500 truncate max-w-[170px]" title="{{ collect($row['diff'])->map(fn ($d) => $d['label'] ?? $d['field'])->implode(', ') }}">{{ collect($row['diff'])->map(fn ($d) => $d['label'] ?? $d['field'])->implode(', ') }}</span>
                                        </div>
                                    @else
                                        <span class="text-surface-500 font-medium">Data mutakhir</span>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        @if (! $loaded)
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-inbox" message="Belum ada pratinjau. Unggah berkas pada tahap pertama." />
                        @elseif ($isErrors && $search === '')
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-circle-check" message="Tidak ada baris terkendala. Seluruh baris lolos verifikasi format dan aturan master data." />
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-inbox" message="Tidak ada baris yang cocok dengan pencarian atau tab ini.">
                                @if ($search !== '')
                                    <x-admin.button wire:click="resetFilters" variant="soft" size="sm" icon="fas fa-rotate-left">Reset Pencarian</x-admin.button>
                                @endif
                            </x-admin.table.empty-state>
                        @endif
                    @endforelse
                </tbody>
                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="baris" :per-page-options="$perPageOptions" />
    </x-admin.table.card>
</div>
