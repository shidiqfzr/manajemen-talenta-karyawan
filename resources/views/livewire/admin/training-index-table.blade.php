{{-- Manajemen Pelatihan di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets('clearKpiFilters');
    $columnCount = $this->tableColumnCount();
    $summary = $this->summary();
    $totalManHours = $summary['man_hours'];
    $totalKaryawan = $summary['karyawan'];
@endphp
{{-- Kartu status (Total, Akan Datang, Berlangsung, Selesai) mengubah filter status tabel lewat $set / clearKpiFilters; href menjadi cadangan tanpa JS.
     Baris kedua adalah ringkasan investasi yang mengikuti pencarian dan filter yang sedang aktif. --}}
<div class="space-y-6">
    @php
        $counts = $this->statusCounts();
        $share = fn (int $count) => $counts['total'] > 0 ? round(($count / $counts['total']) * 100).'%' : null;
        $noKpiFilter = $status === '';
        $statusCards = [
            ['key' => \App\Livewire\Admin\TrainingIndexTable::STATUS_AKAN_DATANG, 'title' => 'Akan Datang', 'subtitle' => 'Belum dimulai', 'icon' => 'fas fa-calendar-plus', 'theme' => 'info', 'count' => $counts['akan_datang']],
            ['key' => \App\Livewire\Admin\TrainingIndexTable::STATUS_BERLANGSUNG, 'title' => 'Sedang Berlangsung', 'subtitle' => 'Pelatihan aktif hari ini', 'icon' => 'fas fa-person-chalkboard', 'theme' => 'earth', 'count' => $counts['berlangsung']],
            ['key' => \App\Livewire\Admin\TrainingIndexTable::STATUS_SELESAI, 'title' => 'Selesai', 'subtitle' => 'Sudah terlaksana', 'icon' => 'fas fa-circle-check', 'theme' => 'teal', 'count' => $counts['selesai']],
        ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <x-admin.kpi-card
            title="Total Pelatihan"
            :value="$counts['total']"
            subtitle="Seluruh pelatihan terdaftar"
            icon="fas fa-graduation-cap"
            theme="primary"
            :active="$noKpiFilter"
            :aria-current="$noKpiFilter ? 'true' : null"
            :href="$this->kpiUrl(['status' => ''])"
            wire:click.prevent="clearKpiFilters" />

        @foreach ($statusCards as $card)
            @php $isActive = $status === $card['key']; @endphp
            <x-admin.kpi-card
                :title="$card['title']"
                :value="$card['count']"
                :badge="$share($card['count'])"
                :subtitle="$card['subtitle']"
                :icon="$card['icon']"
                :theme="$card['theme']"
                :active="$isActive"
                :aria-current="$isActive ? 'true' : null"
                :href="$this->kpiUrl(['status' => $isActive ? '' : $card['key']])"
                wire:click.prevent="$set('status', '{{ $isActive ? '' : $card['key'] }}')" />
        @endforeach
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5" role="group" aria-label="Ringkasan investasi pelatihan sesuai pencarian dan filter">
        <x-admin.kpi-card
            title="Total Man Hours"
            :value="number_format($totalManHours, 0, ',', '.')"
            subtitle="Total jam pelatihan seluruh peserta"
            icon="fas fa-clock"
            theme="primary" />

        <x-admin.kpi-card
            title="Total Tiket Peserta"
            value="Rp {{ number_format($summary['tiket'], 0, ',', '.') }}"
            subtitle="Total tiket peserta pelatihan"
            icon="fas fa-ticket-alt"
            theme="info" />

        <x-admin.kpi-card
            title="Total Biaya Investasi"
            value="Rp {{ number_format($summary['biaya'], 0, ',', '.') }}"
            subtitle="Total anggaran pelatihan"
            icon="fas fa-money-bill-wave"
            theme="neutral" />

        <x-admin.kpi-card
            title="Rata-rata JPL / Karyawan"
            :value="$totalKaryawan > 0 ? number_format($totalManHours / $totalKaryawan, 2, ',', '.') : '-'"
            :subtitle="$totalKaryawan > 0 ? 'Jam pelatihan per '.number_format($totalKaryawan, 0, ',', '.').' karyawan' : 'Belum ada data karyawan'"
            icon="fas fa-chart-line"
            theme="earth" />
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
                            <td class="px-4 py-3 text-center">
                                <x-admin.row-menu :label="$training->judul">
                                    <x-admin.master.menu-item icon="fas fa-eye" :href="route('admin.trainings.show', $training->id)">Lihat Detail</x-admin.master.menu-item>
                                    <x-admin.master.menu-item icon="fas fa-pencil-alt" :href="route('admin.trainings.edit', $training->id)">Ubah Pelatihan</x-admin.master.menu-item>
                                    <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated
                                        @click="$dispatch('confirm-delete', { action: {{ \Illuminate\Support\Js::from(route('admin.trainings.destroy', $training->id)) }}, name: {{ \Illuminate\Support\Js::from($training->judul) }} })">Hapus Pelatihan</x-admin.master.menu-item>
                                </x-admin.row-menu>
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

    <x-admin.confirm-delete-modal title="Hapus Pelatihan" noun="pelatihan" consequence="Data peserta pada pelatihan ini ikut terhapus permanen. Tindakan ini tidak dapat dibatalkan." />
</div>
