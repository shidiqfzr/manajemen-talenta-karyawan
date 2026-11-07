{{-- Manajemen Penilaian di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
    $categoryColors = [
        'Sangat Baik' => 'bg-primary-100 text-primary-800 border-primary-200',
        'Baik' => 'bg-info-100 text-info-800 border-info-200',
        'Cukup' => 'bg-warning-100 text-warning-800 border-warning-200',
        'Kurang' => 'bg-danger-100 text-danger-800 border-danger-200',
    ];
@endphp
<div>
    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            search-placeholder="Cari NIK, nama, jabatan, atau unit kerja..." />
        <x-admin.table.header title="Daftar Penilaian" :paginator="$rows" noun="Penilaian" :export="$this->exportName() !== null" />

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                <x-admin.table.head
                    :columns="$columns"
                    :sort-field="$sortField"
                    :sort-direction="$sortDirection"
                    :selectable="$selectable"
                    :page-keys="$pageKeys"
                    :selected="$selected"
                    :select-all-matching="$selectAllMatching" />

                <tbody class="divide-y divide-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($rows as $eval)
                        @php $key = (string) $eval->getAttribute($rowKey); @endphp
                        <tr wire:key="row-{{ $key }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3">
                                <div class="font-medium text-surface-900">{{ $eval->employee->nama ?? '-' }}</div>
                                <div class="text-[11px] text-surface-500 font-mono">{{ $eval->employee_nik }}</div>
                            </td>
                            <td class="px-4 py-3 text-surface-700">
                                <div>{{ $eval->employee->jabatan ?? '-' }}</div>
                                <div class="text-[11px] text-surface-500">{{ $eval->employee->unit_kerja ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono">{{ is_null($eval->nilai_tertimbang) ? '-' : number_format($eval->nilai_tertimbang, 2) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ is_null($eval->hasil_skor_asesmen) ? '-' : number_format($eval->hasil_skor_asesmen, 2) }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $colorClass = $categoryColors[$eval->kategori_asesmen] ?? 'bg-surface-100 text-surface-800 border-surface-200';
                                @endphp
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $colorClass }}">
                                    {{ $eval->kategori_asesmen ?? 'Belum Dikategorikan' }}
                                </span>
                                @if ($eval->kategori_9box)
                                    <div class="mt-1 text-[11px] text-surface-500">9-Box: {{ $eval->kategori_9box }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-surface-700">
                                {{ $eval->tanggal_pelaksanaan_asesmen ? $eval->tanggal_pelaksanaan_asesmen->format('d M Y') : '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-center">
                                    <a href="{{ route('admin.evaluations.show', $eval->id) }}"
                                        class="p-2 bg-info text-white rounded-md hover:bg-info transition"
                                        title="Lihat Detail">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="{{ route('admin.evaluations.edit', $eval->id) }}"
                                        class="p-2 bg-warning-700 text-white rounded-md hover:bg-warning-700 transition"
                                        title="Edit Data">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    <form action="{{ route('admin.evaluations.destroy', $eval->id) }}"
                                        method="POST" onsubmit="return confirm('Hapus penilaian ini?')"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 bg-danger text-white rounded-md hover:bg-danger transition"
                                            title="Hapus Penilaian">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada penilaian yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-clipboard-list" message="Belum ada data penilaian." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="penilaian" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
