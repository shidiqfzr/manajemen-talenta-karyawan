{{-- Manajemen Pelatihan di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
    $summary = $this->summary();
    $totalManHours = $summary['man_hours'];
    $totalKaryawan = $summary['karyawan'];
@endphp
<div class="space-y-6">
    <!-- Statistics Cards: dihitung dari kueri yang sama dengan baris tabel -->
    <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-primary-50 to-white border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-800 flex items-center">
                <i class="fas fa-chart-pie text-primary-500 mr-2" aria-hidden="true"></i> Ringkasan Statistik
                @if ($startDate !== '' || $endDate !== '')
                    <span class="ml-2 text-sm font-normal text-surface-500">
                        ({{ $startDate !== '' ? \Carbon\Carbon::parse($startDate)->format('d M Y') : 'Awal' }}
                        -
                        {{ $endDate !== '' ? \Carbon\Carbon::parse($endDate)->format('d M Y') : 'Sekarang' }})
                    </span>
                @endif
            </h2>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                <x-admin.kpi-card
                    title="Total Man Hours"
                    :value="number_format($totalManHours, 0, ',', '.')"
                    subtitle="Total jam pelatihan seluruh peserta"
                    icon="fas fa-clock"
                    theme="primary"
                    :coloredValue="false"
                />

                <x-admin.kpi-card
                    title="Total Tiket Peserta"
                    value="Rp {{ number_format($summary['tiket'], 0, ',', '.') }}"
                    subtitle="Total tiket peserta pelatihan"
                    icon="fas fa-ticket-alt"
                    theme="info"
                    :coloredValue="false"
                />

                <x-admin.kpi-card
                    title="Total Biaya Investasi"
                    value="Rp {{ number_format($summary['biaya'], 0, ',', '.') }}"
                    subtitle="Total anggaran pelatihan"
                    icon="fas fa-money-bill-wave"
                    theme="neutral"
                    :coloredValue="false"
                />

                @if ($totalKaryawan !== null && $totalKaryawan > 0)
                    <x-admin.kpi-card
                        title="Total Karyawan"
                        :value="number_format($totalKaryawan, 0, ',', '.')"
                        subtitle="Jumlah karyawan aktif"
                        icon="fas fa-users"
                        theme="teal"
                        :coloredValue="false"
                    />

                    <x-admin.kpi-card
                        title="Rata-rata JPL / Karyawan"
                        :value="number_format($totalManHours / $totalKaryawan, 2, ',', '.')"
                        subtitle="Jam pelatihan per karyawan"
                        icon="fas fa-chart-line"
                        theme="earth"
                        :coloredValue="true"
                    />
                @else
                    <div class="bg-danger-50/80 border border-danger-200 rounded-2xl p-4 flex items-center col-span-full">
                        <div class="w-10 h-10 rounded-xl bg-danger-100 text-danger flex items-center justify-center text-lg mr-3 shrink-0">
                            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-danger-800 text-sm">Data Belum Lengkap</h3>
                            <p class="text-xs text-danger mt-0.5">Data total karyawan belum tersedia untuk periode ini.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            search-placeholder="Cari judul atau bidang pelatihan..." />
        <x-admin.table.header title="Daftar Pelatihan" :paginator="$rows" noun="Pelatihan" :export="$this->exportName() !== null" />

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
                    @forelse ($rows as $training)
                        @php $key = (string) $training->getAttribute($rowKey); @endphp
                        <tr wire:key="row-{{ $key }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 font-medium text-surface-900">{{ $training->judul }}</td>
                            <td class="px-4 py-3">{{ $training->jenis }}</td>
                            <td class="px-4 py-3">{{ $training->metode }}</td>
                            <td class="px-4 py-3">{{ $training->bidang_pelatihan }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-surface-700">{{ \Carbon\Carbon::parse($training->tanggal_mulai)->format('d M Y') }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono">{{ $training->jumlah_man_hours }}</td>
                            <td class="px-4 py-3 whitespace-nowrap font-mono">{{ $training->employees_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2 justify-center">
                                    <a href="{{ route('admin.trainings.show', $training->id) }}"
                                        class="p-2 bg-info text-white rounded-md hover:bg-info transition"
                                        title="Lihat Detail">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="{{ route('admin.trainings.edit', $training->id) }}"
                                        class="p-2 bg-warning-700 text-white rounded-md hover:bg-warning-700 transition"
                                        title="Edit Data">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    <form action="{{ route('admin.trainings.destroy', $training->id) }}"
                                        method="POST" class="inline-block"
                                        onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelatihan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="p-2 bg-danger text-white rounded-md hover:bg-danger transition"
                                            title="Hapus Pelatihan">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada pelatihan yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-inbox" message="Belum ada data pelatihan." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="pelatihan" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
