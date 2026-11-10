{{-- Formasi Lowong: daftar lintas unit formasi jabatan yang belum terisi pejabat definitif (komponen tabel bersama, berbasis koleksi). --}}
<div class="space-y-6">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">
        <x-admin.kpi-card title="Formasi Lowong" :value="$stats['vacant_formasi']" :subtitle="'dari '.number_format($stats['total_seats'], 0, ',', '.').' formasi jabatan'" icon="fas fa-briefcase" theme="warning" />
        <x-admin.kpi-card title="Total Lowongan" :value="$stats['vacant_slots']" :subtitle="'dari '.number_format($stats['total_kuota'], 0, ',', '.').' kuota formasi'" icon="fas fa-table-cells" theme="danger" />
        <x-admin.kpi-card title="Diisi Pj/Plt" :value="$stats['acting_count']" subtitle="Dijabat sementara, belum definitif" icon="fas fa-user-clock" theme="earth" />
        <x-admin.kpi-card title="Keterisian" :value="$stats['fill_rate'].'%'" subtitle="Kuota terisi pejabat definitif" icon="fas fa-user-check" theme="primary" />
    </div>

    <x-admin.table.card>
        <div wire:loading.delay wire:target="search,unitFilter,statusFilter,severityFilter,resetFilters,perPage" class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$filters"
            :filter-values="$filterValues"
            :has-active-filters="$hasActiveFilters"
            :search="$search"
            search-placeholder="Cari jabatan atau unit kerja..." />
        <x-admin.table.header title="Daftar Formasi Lowong" :paginator="$vacancies" noun="Formasi" :export="true" export-action="exportVacancies" />

        <p class="px-5 py-2.5 bg-surface-50 border-b border-surface-200 text-[11px] text-surface-500">
            Hanya pejabat definitif yang mengisi kuota; jabatan yang dijabat Pj/Plt tetap tercatat lowong. Cakupan formasi saat ini: seluruh Karpim dan Karpel pada unit Regional Office.
        </p>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="search,unitFilter,statusFilter,severityFilter,resetFilters,perPage">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200">
                        <x-admin.table.th class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Jabatan</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Kuota / Terisi</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Status</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5">Kosong Sejak</x-admin.table.th>
                        <x-admin.table.th class="py-3.5 px-5 text-right">Opsi Pengisian</x-admin.table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-surface-800 transition-opacity" wire:loading.class="opacity-50" wire:target="search,unitFilter,statusFilter,severityFilter,resetFilters,perPage">
                    @forelse ($vacancies as $row)
                        <tr class="hover:bg-surface-50/75 transition" wire:key="vacancy-{{ $row->position_unit_id }}">
                            <td class="py-3.5 px-5 font-semibold text-surface-900">{{ $row->unit_kerja }}</td>
                            <td class="py-3.5 px-5">
                                <p class="font-semibold text-surface-900 text-sm leading-tight">{{ $row->jabatan }}</p>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ \App\Models\Position::rmTheme($row->rm_level) }}">{{ $row->rm_level ?? '-' }}</span>
                                    @if ($row->bidang)
                                        <span class="px-2 py-0.5 rounded-md font-semibold text-[10px] bg-surface-200/80 text-surface-800">Bidang {{ $row->bidang }}</span>
                                    @endif
                                    @if ($row->is_reserved)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-warning-100 text-warning-800 border border-warning-200" title="Dipesan oleh {{ $row->reserved_occupant_name }} (TMT: {{ $row->reserved_tmt }})">
                                            <i class="fas fa-lock text-[10px]" aria-hidden="true"></i> Dipesan: {{ $row->reserved_tmt }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-5 tabular-nums">
                                <span class="font-semibold">{{ $row->filled }}</span><span class="text-surface-500"> / {{ $row->kuota }}</span>
                                <span class="block text-[11px] text-danger-700 font-semibold">{{ $row->vacant_slots }} lowong</span>
                                @if ($row->acting > 0)
                                    <span class="block text-[11px] text-surface-500">{{ $row->acting }} Pj/Plt</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5">
                                @if ($row->status === 'acting')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-earth-100 text-earth-800 border border-earth-300"><span class="w-1.5 h-1.5 rounded-full bg-earth-700" aria-hidden="true"></span>{{ $row->status_label }}</span>
                                @elseif ($row->status === 'partial')
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-warning-50 text-warning-800 border border-warning-200"><span class="w-1.5 h-1.5 rounded-full bg-warning" aria-hidden="true"></span>{{ $row->status_label }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-danger-50 text-danger-700 border border-danger-200"><span class="w-1.5 h-1.5 rounded-full bg-danger" aria-hidden="true"></span>{{ $row->status_label }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5">
                                @if ($row->days_vacant === null)
                                    <span class="text-surface-500 italic">Belum pernah terisi</span>
                                @else
                                    <p class="font-medium text-surface-900 tabular-nums">{{ $row->vacant_since_formatted }}</p>
                                    <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-[11px] font-semibold tabular-nums border
                                        {{ match ($row->severity) {
                                            'red' => 'bg-danger-50 text-danger-700 border-danger-200',
                                            'yellow' => 'bg-warning-50 text-warning-800 border-warning-200',
                                            default => 'bg-primary-100 text-primary-800 border-primary-200',
                                        } }}">{{ $row->days_vacant }} hari</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-5 text-right whitespace-nowrap">
                                @if ($row->is_reserved)
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-warning-800"><i class="fas fa-lock text-[10px]" aria-hidden="true"></i> Terjadwal (SK)</span>
                                @else
                                    <div class="inline-flex flex-col gap-1.5 w-32 items-stretch">
                                        <a href="{{ route('admin.mutasi.create', ['jabatan' => $row->jabatan, 'unit' => $row->unit_kerja]) }}"
                                            class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition shadow-2xs"
                                            title="Isi melalui transfer atau promosi karyawan internal">
                                            <i class="fas fa-exchange-alt text-[10px]" aria-hidden="true"></i><span>Mutasi Internal</span>
                                        </a>
                                        <a href="{{ route('admin.employees.create', ['jabatan' => $row->jabatan, 'unit_kerja' => $row->unit_kerja, 'level' => $row->level]) }}"
                                            class="inline-flex items-center justify-center gap-1.5 w-full py-1.5 px-2.5 rounded-lg bg-white hover:bg-surface-50 text-surface-700 border border-surface-300 text-xs font-medium transition shadow-2xs"
                                            title="Rekrut karyawan baru untuk mengisi formasi ini">
                                            <i class="fas fa-user-plus text-[10px] text-surface-400" aria-hidden="true"></i><span>Rekrut Baru</span>
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.table.empty-state :colspan="6" icon="fas fa-check-double"
                            :message="$hasActiveFilters ? 'Tidak ada formasi lowong yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter.' : 'Seluruh formasi jabatan saat ini terisi pejabat definitif.'">
                            @if ($hasActiveFilters)
                                <x-admin.button variant="secondary" size="sm" wire:click="resetFilters">Reset Filter</x-admin.button>
                            @endif
                        </x-admin.table.empty-state>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-admin.table.pagination :paginator="$vacancies" noun="formasi" :per-page-options="$perPageOptions" />
    </x-admin.table.card>
</div>
