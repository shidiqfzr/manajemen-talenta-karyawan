{{-- Manajemen Penilaian di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets('clearKpiFilters');
    $columnCount = $this->tableColumnCount();
    $kpi = $this->kpi();
    $share = fn (int $count) => $kpi['total'] > 0 ? round(($count / $kpi['total']) * 100).'%' : null;
    $noKpiFilter = $hasil === '';
    $hasilCards = [
        ['key' => \App\Livewire\Admin\EvaluationIndexTable::HASIL_SANGAT_BAIK, 'title' => 'Sangat Baik', 'subtitle' => 'Kinerja melampaui standar', 'icon' => 'fas fa-star', 'theme' => 'primary', 'count' => $kpi['sangat_baik']],
        ['key' => \App\Livewire\Admin\EvaluationIndexTable::HASIL_BAIK, 'title' => 'Baik', 'subtitle' => 'Kinerja sesuai standar', 'icon' => 'fas fa-thumbs-up', 'theme' => 'info', 'count' => $kpi['baik']],
        ['key' => \App\Livewire\Admin\EvaluationIndexTable::HASIL_PERLU_PEMBINAAN, 'title' => 'Perlu Pembinaan', 'subtitle' => 'Kategori Cukup & Kurang', 'icon' => 'fas fa-triangle-exclamation', 'theme' => 'warning', 'count' => $kpi['perlu_pembinaan']],
    ];
    $categoryColors = [
        'Sangat Baik' => 'bg-primary-100 text-primary-800 border-primary-200',
        'Baik' => 'bg-info-100 text-info-800 border-info-200',
        'Cukup' => 'bg-warning-100 text-warning-800 border-warning-200',
        'Kurang' => 'bg-danger-100 text-danger-800 border-danger-200',
    ];
@endphp
{{-- Kartu hasil penilaian mengubah filter hasil tabel lewat $set / clearKpiFilters; href menjadi cadangan tanpa JS.
     Angka kartu dihitung dari seluruh penilaian, tidak mengikuti pencarian dan filter. --}}
<div class="space-y-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <x-admin.kpi-card
            title="Total Penilaian"
            :value="$kpi['total']"
            :subtitle="$kpi['rata_rata'] === null ? 'Belum ada nilai tertimbang' : 'Rata-rata nilai '.number_format($kpi['rata_rata'], 2, ',', '.')"
            icon="fas fa-clipboard-check"
            theme="neutral"
            :active="$noKpiFilter"
            :aria-current="$noKpiFilter ? 'true' : null"
            :href="$this->kpiUrl(['hasil' => ''])"
            wire:click.prevent="clearKpiFilters" />

        @foreach ($hasilCards as $card)
            @php $isActive = $hasil === $card['key']; @endphp
            <x-admin.kpi-card
                :title="$card['title']"
                :value="$card['count']"
                :badge="$share($card['count'])"
                :subtitle="$card['subtitle']"
                :icon="$card['icon']"
                :theme="$card['theme']"
                :active="$isActive"
                :aria-current="$isActive ? 'true' : null"
                :href="$this->kpiUrl(['hasil' => $isActive ? '' : $card['key']])"
                wire:click.prevent="$set('hasil', '{{ $isActive ? '' : $card['key'] }}')" />
        @endforeach
    </div>

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
                            <td class="px-4 py-3 text-center">
                                @php $evalName = $eval->employee->nama ?? $eval->employee_nik; @endphp
                                <x-admin.row-menu :label="'penilaian '.$evalName">
                                    <x-admin.master.menu-item icon="fas fa-eye" :href="route('admin.evaluations.show', $eval->id)">Lihat Detail</x-admin.master.menu-item>
                                    <x-admin.master.menu-item icon="fas fa-pencil-alt" :href="route('admin.evaluations.edit', $eval->id)">Ubah Penilaian</x-admin.master.menu-item>
                                    <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated
                                        @click="$dispatch('confirm-delete', { action: {{ \Illuminate\Support\Js::from(route('admin.evaluations.destroy', $eval->id)) }}, name: {{ \Illuminate\Support\Js::from($evalName.' ('.($eval->tanggal_pelaksanaan_asesmen?->format('d M Y') ?? 'tanpa tanggal').')') }} })">Hapus Penilaian</x-admin.master.menu-item>
                                </x-admin.row-menu>
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

    <x-admin.confirm-delete-modal title="Hapus Penilaian" noun="penilaian" consequence="Skor dan kategori 9-Box pada penilaian ini ikut terhapus permanen. Tindakan ini tidak dapat dibatalkan." />
</div>
