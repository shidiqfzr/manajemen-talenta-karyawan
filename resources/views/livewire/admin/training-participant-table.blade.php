{{-- Daftar peserta pada detail pelatihan di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
@endphp
<div>
    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            :search-shortcut="null"
            search-placeholder="Cari NIK, nama, jabatan, atau unit..." />
        <x-admin.table.header :paginator="$rows" noun="Peserta" />

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
                    @forelse ($rows as $employee)
                        @php
                            $key = (string) $employee->getAttribute($rowKey);
                            $sertifikat = $employee->trainings->first()?->pivot?->sertifikat;
                        @endphp
                        <tr wire:key="row-{{ $key }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-surface-900">{{ $employee->nama }}</div>
                                <div class="text-xs text-surface-500 font-mono">{{ $employee->nik }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $employee->jabatan }}</td>
                            <td class="px-4 py-3">{{ $employee->unit_kerja }}</td>
                            <td class="px-4 py-3">{{ $employee->level }}</td>
                            <td class="px-4 py-3">
                                @if ($sertifikat)
                                    <a href="{{ asset('storage/' . $sertifikat) }}" target="_blank" class="text-info underline">Unduh</a>
                                @else
                                    <span class="text-surface-500">Tidak ada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-center">
                                    <a href="{{ route('admin.trainings.participants.edit', ['training' => $trainingId, 'employee' => $employee->nik]) }}"
                                        class="p-2 bg-warning-700 text-white rounded-md hover:bg-warning-700 transition"
                                        title="Edit Sertifikat">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    <form
                                        action="{{ route('admin.trainings.participants.destroy', ['training' => $trainingId, 'employee' => $employee->nik]) }}"
                                        method="POST" onsubmit="return confirm('Yakin hapus peserta?')"
                                        class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 bg-danger text-white rounded-md hover:bg-danger transition"
                                            title="Hapus Peserta">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada peserta yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-inbox" message="Belum ada data peserta pelatihan yang ditambahkan saat ini." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="peserta" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
