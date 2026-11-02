@php
    $fieldClass = 'w-full px-3 py-2 bg-surface-50/70 border border-surface-200 rounded-xl text-xs font-medium text-surface-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/30 focus:border-primary-500 transition';
@endphp
<div class="space-y-6">
    @if ($notice)
        <div role="status" class="flex items-start gap-2.5 p-4 bg-primary-50 text-primary-800 border border-primary-200 rounded-xl shadow-xs text-sm font-medium">
            <i class="fas fa-circle-check mt-0.5" aria-hidden="true"></i>
            <span>{{ $notice }}</span>
        </div>
    @endif

    <div class="space-y-6">
    @if ($this->unit)
        @php
            $unit = $this->unit;
            $seats = $this->seats;
            $filteredSeats = $this->filteredSeats;
            $pagedSeats = $this->pagedSeats;
            $overQuota = $seats->where('over_quota', true)->count();
        @endphp

        <!-- Ringkasan -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">
            <x-admin.kpi-card title="Jumlah Jabatan" :value="$seats->count()" subtitle="Jabatan pada formasi unit ini" icon="fas fa-briefcase" theme="primary" />
            <x-admin.kpi-card title="Total Kuota" :value="$seats->sum('kuota')" subtitle="Kuota formasi" icon="fas fa-table-cells" theme="info" />
            <x-admin.kpi-card title="Terisi" :value="$seats->sum('filled')" subtitle="Pegawai menempati formasi" icon="fas fa-user-check" theme="teal" />
            @if ($overQuota > 0 || $seatStatusFilter === 'over')
                <x-admin.kpi-card title="Melebihi Kuota" :value="$overQuota" :subtitle="$seatStatusFilter === 'over' ? 'Filter aktif, klik untuk menampilkan semua' : 'Klik untuk menampilkan yang perlu ditinjau'" icon="fas fa-triangle-exclamation" theme="danger" :active="$seatStatusFilter === 'over'"
                    role="button" tabindex="0" aria-pressed="{{ $seatStatusFilter === 'over' ? 'true' : 'false' }}" wire:click="toggleOverQuotaFilter" wire:keydown.enter="toggleOverQuotaFilter" wire:keydown.space.prevent="toggleOverQuotaFilter" />
            @else
                <x-admin.kpi-card title="Melebihi Kuota" :value="0" subtitle="Tidak ada formasi melebihi kuota" icon="fas fa-triangle-exclamation" theme="neutral" />
            @endif
        </div>

        <!-- Formasi jabatan: judul, pencarian, filter, dan tabel dalam satu kartu (pola yang sama dengan daftar unit dan modul Pegawai) -->
        <div class="bg-white rounded-2xl border border-surface-200/80 shadow-xs overflow-hidden">
            <div wire:loading.delay wire:target="seatQuery,rmFilter,bidangFilter,seatStatusFilter,toggleOverQuotaFilter,resetSeatFilters,sortBy" class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>
            <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ $filteredSeats->count() }} jabatan ditemukan</div>

            <div class="px-5 py-3 border-b border-surface-200 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h2 class="text-sm font-bold text-surface-900">Formasi Jabatan — {{ $unit->nama }}</h2>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-700">{{ $this->hasSeatFilters() ? $filteredSeats->count().' dari '.$seats->count() : $seats->count() }} Jabatan</span>
                </div>
                <div class="flex items-center gap-2">
                    <x-admin.button variant="secondary" size="sm" icon="fas fa-file-excel" wire:click="exportFormasi" target="exportFormasi">Ekspor</x-admin.button>
                    <x-admin.button size="sm" icon="fas fa-plus" wire:click="openAddSeat">Tambah Jabatan</x-admin.button>
                </div>
            </div>

            <div class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2.5">
                    <x-admin.search-input model="seatQuery" shortcut="/" placeholder="Cari nama jabatan..." containerClass="relative flex-1 min-w-[260px]" />
                    <div class="flex flex-wrap items-center gap-2">
                        <x-admin.filter-select id="formasi-filter-rm" model="rmFilter" label="RM Band" all-label="Semua RM Band" icon="fas fa-layer-group" :options="array_combine(array_keys($rmList), array_keys($rmList))" min-width="min-w-[160px]" />
                        <x-admin.filter-select id="formasi-filter-bidang" model="bidangFilter" label="Bidang" all-label="Semua Bidang" icon="fas fa-sitemap" :options="$bidangList" min-width="min-w-[190px]" />
                        <x-admin.filter-select id="formasi-filter-seat-status" model="seatStatusFilter" label="Status" all-label="Semua Status" icon="fas fa-toggle-on"
                            :options="$seatStateOptions" min-width="min-w-[170px]" />
                        @if ($this->hasSeatFilters())
                            <button type="button" wire:click="resetSeatFilters" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-danger-50 hover:text-danger-700 text-surface-600 border border-surface-300 hover:border-danger-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap" title="Reset semua filter">
                                <i class="fas fa-rotate-left text-[11px]" aria-hidden="true"></i><span class="hidden sm:inline">Reset</span>
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200 text-[11px] font-bold text-surface-700 uppercase tracking-wider">
                            <x-admin.table.th sortable field="jabatan" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th sortable field="rm_level" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">RM Band</x-admin.table.th>
                            <x-admin.table.th sortable field="bidang" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Bidang</x-admin.table.th>
                            <x-admin.table.th sortable field="kuota" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Kuota</x-admin.table.th>
                            <x-admin.table.th sortable field="filled" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Terisi</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5">Status</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5 text-right">Aksi</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-surface-800 transition-opacity" wire:loading.class="opacity-50" wire:target="sortBy,seatQuery,rmFilter,bidangFilter,seatStatusFilter,startUpdateSeat,startRemoveSeat,confirm">
                        @forelse ($pagedSeats as $seat)
                            <tr class="hover:bg-surface-50/75 transition" wire:key="seat-{{ $seat->id }}">
                                <td class="py-3.5 px-5 font-semibold text-surface-900">
                                    {{ $seat->jabatan }}
                                    @unless ($seat->position_active)
                                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-100 text-surface-600">Nonaktif</span>
                                    @endunless
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ \App\Models\Position::rmTheme($seat->rm_level) }}">{{ $seat->rm_level ?? '-' }}</span>
                                </td>
                                <td class="py-3.5 px-5 text-surface-600">{{ $seat->bidang ?? '-' }}</td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $seat->kuota }}</td>
                                <td class="py-3.5 px-5 tabular-nums">
                                    @if ($seat->filled > 0)
                                        <button type="button" wire:click="viewOccupants({{ $seat->id }})" aria-label="Lihat {{ $seat->filled }} pegawai pada formasi {{ $seat->jabatan }}"
                                            class="font-semibold text-primary-700 underline decoration-dotted underline-offset-2 hover:text-primary-900 cursor-pointer rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500">{{ $seat->filled }}</button>
                                    @else
                                        {{ $seat->filled }}
                                    @endif
                                </td>
                                <td class="py-3.5 px-5">
                                    @if ($seat->state === 'over')
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-danger-50 text-danger-700 border border-danger-200"><span class="w-1.5 h-1.5 rounded-full bg-danger" aria-hidden="true"></span>Melebihi kuota</span>
                                    @elseif ($seat->state === 'empty')
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-100 text-surface-600 border border-surface-200"><span class="w-1.5 h-1.5 rounded-full bg-surface-400" aria-hidden="true"></span>Kosong</span>
                                    @elseif ($seat->state === 'vacant')
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-warning-50 text-warning-800 border border-warning-200"><span class="w-1.5 h-1.5 rounded-full bg-warning" aria-hidden="true"></span>Ada lowongan</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-primary-100 text-primary-800"><span class="w-1.5 h-1.5 rounded-full bg-primary-600" aria-hidden="true"></span>Penuh</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    <x-admin.row-menu :label="$seat->jabatan">
                                        <x-admin.master.menu-item icon="fas fa-sliders" wire:click="openEditSeat({{ $seat->id }})">Ubah Kuota &amp; Bidang</x-admin.master.menu-item>
                                        <x-admin.master.menu-item icon="fas fa-pencil-alt" :action="$dialogs->editPosition($seat, $unit)">Ubah Jabatan</x-admin.master.menu-item>
                                        <x-admin.master.menu-item :icon="$seat->position_active ? 'fas fa-ban' : 'fas fa-circle-check'" :action="$dialogs->togglePosition($seat, $unit)">{{ $seat->position_active ? 'Nonaktifkan Jabatan' : 'Aktifkan Jabatan' }}</x-admin.master.menu-item>
                                        <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated wire:click="startRemoveSeat({{ $seat->id }})">Hapus Formasi Jabatan</x-admin.master.menu-item>
                                    </x-admin.row-menu>
                                </td>
                            </tr>
                        @empty
                            <x-admin.table.empty-state :colspan="7" icon="fas fa-briefcase"
                                :message="$this->hasSeatFilters() ? 'Tidak ada jabatan yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter.' : 'Unit ini belum memiliki formasi jabatan. Gunakan Tambah Jabatan untuk memulai formasi unit ini.'" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-admin.table.pagination :paginator="$pagedSeats" noun="jabatan" :per-page-options="$perPageOptions" />
        </div>

        <!-- Perubahan terakhir -->
        <div class="bg-white rounded-2xl border border-surface-200/80 p-4 sm:p-5 shadow-xs">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-sm font-bold text-surface-900">Perubahan Terakhir pada Unit Ini</h2>
                <a href="{{ route('admin.master.riwayat.index', ['search' => $unit->nama]) }}" class="text-[11px] font-semibold text-primary-700 hover:text-primary-900 hover:underline inline-flex items-center gap-1">
                    <span>Lihat semua riwayat</span><i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
            <ul class="divide-y divide-surface-100">
                @forelse ($this->recentChanges as $log)
                    <li wire:key="log-{{ $log->id }}" class="py-2.5 text-xs">
                        <p class="font-bold text-surface-800">{{ \App\Models\MasterAuditLog::actionLabel($log->action) }} — {{ $log->entity_label }}</p>
                        <p class="text-surface-500">{{ $log->user_name }} · {{ $log->created_at->diffForHumans() }}@if ($log->reason) · {{ $log->reason }}@endif</p>
                    </li>
                @empty
                    <li class="py-3 text-xs text-surface-500">Belum ada perubahan tercatat.</li>
                @endforelse
            </ul>
        </div>
    @else
        @php
            $stats = $this->overviewStats;
            $unitModels = $this->units->keyBy('id');
        @endphp
        @php $unregistered = $this->unregisteredEmployeeUnits @endphp
        @if ($unregistered->isNotEmpty())
            <div role="status" class="flex items-start gap-2.5 p-4 bg-warning-50 text-warning-800 border border-warning-200 rounded-xl shadow-xs text-sm">
                <i class="fas fa-triangle-exclamation mt-0.5" aria-hidden="true"></i>
                <div class="space-y-1">
                    <p class="font-semibold">{{ number_format($unregistered->sum('total')) }} pegawai tercatat pada {{ $unregistered->count() }} unit kerja yang belum terdaftar di Master Unit, sehingga tidak terhitung pada kolom Terisi.</p>
                    <p class="text-xs">
                        {{ $unregistered->take(3)->map(fn ($row) => $row->unit_kerja.' ('.$row->total.')')->join(', ') }}{{ $unregistered->count() > 3 ? ', dan '.($unregistered->count() - 3).' unit lainnya' : '' }}.
                        Samakan penulisan nama unit pada data pegawai dengan Master Unit, atau daftarkan unitnya.
                    </p>
                </div>
            </div>
        @endif

        <!-- Ringkasan lintas unit -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">
            <x-admin.kpi-card title="Unit Kerja" :value="$stats['units']" subtitle="Unit terdaftar" icon="fas fa-building" theme="teal" />
            <x-admin.kpi-card title="Formasi Jabatan" :value="$stats['seats']" subtitle="Jabatan pada seluruh unit" icon="fas fa-briefcase" theme="primary" />
            <x-admin.kpi-card title="Total Kuota" :value="$stats['kuota']" subtitle="Kuota formasi" icon="fas fa-table-cells" theme="info" />
            <x-admin.kpi-card title="Terisi" :value="$stats['filled']" :badge="$stats['kuota'] > 0 ? round($stats['filled'] / $stats['kuota'] * 100).'%' : null" :subtitle="$stats['over'].' formasi melebihi kuota'" icon="fas fa-user-check" theme="primary" />
        </div>

        <!-- Daftar unit: pencarian, filter, dan tabel dalam satu kartu (pola yang sama dengan modul Pegawai) -->
        <div class="bg-white rounded-2xl border border-surface-200 shadow-xs overflow-hidden">
            <div wire:loading.delay wire:target="unitQuery,wilayahFilter,statusFilter,sortBy" class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>
            <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ $this->filteredOverview->count() }} unit ditemukan</div>

            <div class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200">
                <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2.5">
                    <x-admin.search-input model="unitQuery" shortcut="/" placeholder="Cari nama unit kerja..." containerClass="relative flex-1 min-w-[260px]" />
                    <div class="flex flex-wrap items-center gap-2">
                        <x-admin.filter-select id="formasi-filter-wilayah" model="wilayahFilter" label="Wilayah" all-label="Semua Wilayah" icon="fas fa-location-dot" :options="array_combine($wilayahList, $wilayahList)" min-width="min-w-[200px]" />
                        <x-admin.filter-select id="formasi-filter-status" model="statusFilter" label="Status" all-label="Semua Status" icon="fas fa-toggle-on" :options="['active' => 'Aktif', 'inactive' => 'Nonaktif']" />
                        @if ($this->hasUnitFilters())
                            <button type="button" wire:click="resetUnitFilters" class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-danger-50 hover:text-danger-700 text-surface-600 border border-surface-300 hover:border-danger-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap" title="Reset semua filter">
                                <i class="fas fa-rotate-left text-[11px]" aria-hidden="true"></i><span class="hidden sm:inline">Reset</span>
                            </button>
                        @endif
                    </div>
                    <x-admin.button variant="secondary" size="sm" icon="fas fa-file-excel" wire:click="exportFormasi" target="exportFormasi" class="lg:ml-auto">Ekspor</x-admin.button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-surface-50 border-b border-surface-200 text-[11px] font-bold text-surface-700 uppercase tracking-wider">
                            <x-admin.table.th sortable field="nama" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Unit Kerja</x-admin.table.th>
                            <x-admin.table.th sortable field="wilayah" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Wilayah</x-admin.table.th>
                            <x-admin.table.th sortable field="seats" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Jabatan</x-admin.table.th>
                            <x-admin.table.th sortable field="kuota" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Kuota</x-admin.table.th>
                            <x-admin.table.th sortable field="filled" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Terisi</x-admin.table.th>
                            <x-admin.table.th class="py-3.5 px-5 text-right">Aksi</x-admin.table.th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-200 text-surface-800">
                        @forelse ($this->pagedOverview as $row)
                            <tr class="hover:bg-surface-50/75 transition" wire:key="overview-{{ $row->id }}">
                                <td class="py-3.5 px-5 font-semibold text-surface-900">
                                    <a href="{{ route('admin.master.formasi.show', $row->id) }}" wire:navigate class="hover:text-primary-700 transition">{{ $row->nama }}</a>
                                    @unless ($row->is_active)
                                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-md text-[10px] font-semibold bg-surface-100 text-surface-600">Nonaktif</span>
                                    @endunless
                                </td>
                                <td class="py-3.5 px-5 text-surface-600">{{ $row->wilayah }}</td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $row->seats }}</td>
                                <td class="py-3.5 px-5 tabular-nums">{{ $row->kuota }}</td>
                                <td class="py-3.5 px-5 tabular-nums">
                                    {{ $row->filled }}
                                    @if ($row->over > 0)
                                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-danger-50 text-danger-700 border border-danger-200">{{ $row->over }} melebihi</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-5 text-right">
                                    @php $unitModel = $unitModels[$row->id]; @endphp
                                    <x-admin.row-menu :label="'unit '.$row->nama">
                                        <x-admin.master.menu-item icon="fas fa-sitemap" :href="route('admin.master.formasi.show', $row->id)">Kelola Formasi</x-admin.master.menu-item>
                                        <x-admin.master.menu-item icon="fas fa-pencil-alt" :action="$dialogs->editUnit($unitModel)">Ubah Unit</x-admin.master.menu-item>
                                        <x-admin.master.menu-item :icon="$row->is_active ? 'fas fa-ban' : 'fas fa-circle-check'" :action="$dialogs->toggleUnit($unitModel)">{{ $row->is_active ? 'Nonaktifkan Unit' : 'Aktifkan Unit' }}</x-admin.master.menu-item>
                                        <x-admin.master.menu-item icon="fas fa-trash-can" tone="danger" separated :action="$dialogs->deleteUnit($unitModel)">Hapus Unit</x-admin.master.menu-item>
                                    </x-admin.row-menu>
                                </td>
                            </tr>
                        @empty
                            <x-admin.table.empty-state :colspan="6" icon="fas fa-building"
                                :message="$this->hasUnitFilters() ? 'Tidak ada unit yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter.' : 'Belum ada unit kerja. Gunakan Tambah Unit untuk memulai.'" />
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-admin.table.pagination :paginator="$this->pagedOverview" noun="unit kerja" :per-page-options="$perPageOptions" />
        </div>

    @endif
    </div>

    <!-- Konfirmasi perubahan + panel dampak -->
    @if ($pendingOperation)
        @php $impact = $this->impact @endphp
        <div x-data="{ open: true }" @keydown.escape.window="$wire.cancel()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="formasi-confirm-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="formasi-confirm-title" class="text-sm font-extrabold text-surface-900">{{ $this->operationTitle() }}</h3>
                    <p class="text-[11px] text-surface-500">Tinjau dampak perubahan sebelum diterapkan.</p>
                </div>

                <div class="p-6 space-y-4">
                    @if ($impact)
                        @if ($impact->blockers)
                            <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold space-y-1">
                                @foreach ($impact->blockers as $blocker)
                                    <p>{{ $blocker }}</p>
                                @endforeach
                            </div>
                        @endif
                        @if ($impact->warnings)
                            <div class="p-3 bg-warning-50 border border-warning-200 rounded-xl text-xs text-warning-800 font-semibold space-y-1">
                                @foreach ($impact->warnings as $warning)
                                    <p>{{ $warning }}</p>
                                @endforeach
                            </div>
                        @endif
                        @if (! $impact->blockers && ! $impact->warnings)
                            <p class="text-xs text-surface-600">Tidak ada dampak pada pegawai. Perubahan siap diterapkan.</p>
                        @endif
                        @if ($impact->affectedEmployees > 0)
                            <p class="text-[11px] text-surface-500">Pegawai terkait: {{ $impact->affectedEmployees }}</p>
                        @endif
                    @endif

                    @if ($this->requiresReason())
                        <div>
                            <label for="formasi-alasan" class="block text-xs font-bold text-surface-700 mb-1.5">Alasan Perubahan <span class="text-danger-700" aria-hidden="true">*</span></label>
                            <textarea id="formasi-alasan" wire:model="reason" rows="3" placeholder="Jelaskan alasan perubahan ini (minimal 10 karakter). Alasan dicatat pada riwayat perubahan master." class="{{ $fieldClass }}"></textarea>
                        </div>
                    @endif

                    @error('confirm')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>

                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="cancel" wire:loading.attr="disabled" wire:target="confirm">Batal</x-admin.button>
                    <x-admin.button wire:click="confirm" target="confirm" :disabled="$impact && $impact->blocked" icon="fas fa-check">Terapkan Perubahan</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Dialog Tambah Jabatan ke Formasi: satu formulir; jabatan yang sudah ada dikenali dari namanya, selain itu dibuat baru -->
    @if ($addingSeat && $this->unit)
        @php
            $catalog = $this->addablePositions->mapWithKeys(fn ($p) => [mb_strtolower($p->nama) => ['nama' => $p->nama, 'rm' => $p->rm_level]])->all();
            $rmOptions = $dialogs->rmOptions();
        @endphp
        <div x-data="{
                open: true,
                catalog: {{ \Illuminate\Support\Js::from($catalog) }},
                nama: '',
                rm: 'RM-3',
                kuota: 1,
                bidang: '',
                suggestOpen: false,
                activeIndex: -1,
                get suggestions() {
                    const term = this.nama.trim().toLowerCase();
                    if (term.length < 2) { return []; }
                    return Object.values(this.catalog)
                        .filter(item => item.nama.toLowerCase().includes(term))
                        .slice(0, 8);
                },
                pick(item) { this.nama = item.nama; this.suggestOpen = false; this.activeIndex = -1; },
                move(step) {
                    if (! this.suggestOpen) { this.suggestOpen = true; return; }
                    const last = this.suggestions.length - 1;
                    this.activeIndex = Math.max(0, Math.min(last, this.activeIndex + step));
                },
                get match() { return this.catalog[this.nama.trim().toLowerCase()] ?? null; },
                get effectiveRm() { return this.match ? (this.match.rm ?? '') : this.rm; },
                get ready() { return this.nama.trim() !== '' && this.bidang !== '' && (this.match || this.rm !== ''); }
            }"
            @keydown.escape.window="$wire.closeAddSeat()" @click.self="$wire.closeAddSeat()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="formasi-add-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="formasi-add-title" class="text-sm font-extrabold text-surface-900">Tambah Jabatan ke Formasi</h3>
                    <p class="text-[11px] text-surface-500">Unit: {{ $this->unit->nama }}. Jabatan mendapat kuota dan bidang untuk unit ini saja.</p>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label for="formasi-add-nama" class="block text-xs font-bold text-surface-700 mb-1.5">Nama Jabatan <span class="text-danger-700" aria-hidden="true">*</span></label>
                        <div class="relative" @click.outside="suggestOpen = false">
                            <input id="formasi-add-nama" type="text" x-model="nama" maxlength="255" autocomplete="off" placeholder="Ketik nama jabatan…" class="{{ $fieldClass }}"
                                role="combobox" aria-autocomplete="list" aria-controls="formasi-add-saran" x-bind:aria-expanded="(suggestOpen && suggestions.length > 0).toString()"
                                @input="suggestOpen = true; activeIndex = -1"
                                @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                                @keydown.enter="if (suggestOpen && activeIndex >= 0) { $event.preventDefault(); pick(suggestions[activeIndex]); }"
                                @keydown.escape.stop="if (suggestOpen) { suggestOpen = false; } else { $wire.closeAddSeat(); }">
                            <ul id="formasi-add-saran" role="listbox" x-show="suggestOpen && suggestions.length > 0" x-cloak
                                class="absolute z-50 mt-1.5 w-full max-h-56 overflow-y-auto bg-white rounded-xl border border-surface-200 shadow-xl ring-1 ring-black/5 py-1">
                                <template x-for="(item, index) in suggestions" :key="item.nama">
                                    <li role="option" :aria-selected="(index === activeIndex).toString()" @click="pick(item)" @mouseenter="activeIndex = index"
                                        class="px-3 py-2 text-xs cursor-pointer flex items-center justify-between gap-2 transition"
                                        :class="index === activeIndex ? 'bg-primary-50 text-primary-800' : 'text-surface-700 hover:bg-surface-50'">
                                        <span class="truncate" x-text="item.nama"></span>
                                        <span class="shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-md bg-surface-100 text-surface-600" x-text="item.rm ?? '-'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <p class="mt-1.5 text-[11px]" :class="match ? 'text-primary-700' : 'text-surface-500'" aria-live="polite"
                            x-text="nama.trim() === ''
                                ? 'Ketik nama jabatan. Bila mirip dengan jabatan yang sudah ada, saran akan muncul.'
                                : (match ? 'Jabatan sudah terdaftar di master (' + (match.rm ?? '-') + '); RM Band mengikuti jabatan tersebut.' : 'Nama belum terdaftar: jabatan baru akan dibuat pada master.')"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="formasi-add-rm" class="block text-xs font-bold text-surface-700 mb-1.5">RM Band</label>
                            <select id="formasi-add-rm" x-model="rm" x-bind:disabled="!!match" class="{{ $fieldClass }} disabled:opacity-60 disabled:cursor-not-allowed">
                                @foreach ($rmOptions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="formasi-add-bidang" class="block text-xs font-bold text-surface-700 mb-1.5">Bidang <span class="text-danger-700" aria-hidden="true">*</span></label>
                            <select id="formasi-add-bidang" x-model="bidang" class="{{ $fieldClass }}">
                                <option value="">— Pilih bidang —</option>
                                @foreach ($bidangList as $code => $label)
                                    <option value="{{ $code }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="w-1/2 pr-1.5">
                        <label for="formasi-add-kuota" class="block text-xs font-bold text-surface-700 mb-1.5">Kuota</label>
                        <input id="formasi-add-kuota" type="number" min="1" x-model.number="kuota" class="{{ $fieldClass }}">
                    </div>
                    @error('add')
                        <div role="alert" class="p-3 bg-danger-50 border border-danger-200 rounded-xl text-xs text-danger-700 font-semibold">{{ $message }}</div>
                    @enderror
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="closeAddSeat">Batal</x-admin.button>
                    <x-admin.button x-bind:disabled="!ready" @click="$wire.startAddJabatan(nama, effectiveRm, bidang, kuota)" icon="fas fa-eye">Lanjut</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Dialog Ubah Kuota & Bidang -->
    @if ($this->editSeat)
        @php $editing = $this->editSeat @endphp
        <div x-data="{ open: true, kuota: {{ $editing->kuota }}, bidang: '{{ $editing->bidang }}' }" @keydown.escape.window="$wire.closeEditSeat()"
            @click.self="$wire.closeEditSeat()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="formasi-edit-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="formasi-edit-title" class="text-sm font-extrabold text-surface-900">Ubah Kuota &amp; Bidang</h3>
                    <p class="text-[11px] text-surface-500">{{ $editing->jabatan }} — {{ $this->unit?->nama }}</p>
                </div>
                <div class="p-6 grid grid-cols-2 gap-3">
                    <div>
                        <label for="formasi-edit-bidang" class="block text-xs font-bold text-surface-700 mb-1.5">Bidang</label>
                        <select id="formasi-edit-bidang" x-model="bidang" class="{{ $fieldClass }}">
                            <option value="">—</option>
                            @foreach ($bidangList as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="formasi-edit-kuota" class="block text-xs font-bold text-surface-700 mb-1.5">Kuota</label>
                        <input id="formasi-edit-kuota" type="number" min="1" x-model.number="kuota" class="{{ $fieldClass }}">
                    </div>
                </div>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <x-admin.button variant="secondary" wire:click="closeEditSeat">Batal</x-admin.button>
                    <x-admin.button @click="$wire.startUpdateSeat({{ $editing->id }}, kuota, bidang)" icon="fas fa-eye">Tinjau Dampak</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    <!-- Dialog Pemegang Jabatan (drill-down kolom Terisi) -->
    @if ($this->occupantSeat)
        @php $occupantSeat = $this->occupantSeat @endphp
        <div x-data="{ open: true }" @keydown.escape.window="$wire.closeOccupants()" @click.self="$wire.closeOccupants()"
            class="fixed inset-0 z-50 overflow-y-auto bg-surface-900/60 backdrop-blur-xs flex items-center justify-center p-4"
            role="dialog" aria-modal="true" aria-labelledby="formasi-occupant-title" x-trap.noscroll="open" tabindex="-1">
            <div class="bg-white rounded-2xl border border-surface-200 shadow-2xl max-w-lg w-full overflow-hidden">
                <div class="px-6 py-4 border-b border-surface-100 bg-surface-50/50">
                    <h3 id="formasi-occupant-title" class="text-sm font-extrabold text-surface-900">Pemegang Jabatan</h3>
                    <p class="text-[11px] text-surface-500">{{ $occupantSeat->jabatan }} — {{ $occupantSeat->filled }} dari {{ $occupantSeat->kuota }} kuota terisi</p>
                </div>
                <ul class="max-h-80 overflow-y-auto divide-y divide-surface-100">
                    @foreach ($this->occupants as $occupant)
                        <li class="px-6 py-3 flex items-center justify-between gap-3 text-xs" wire:key="occupant-{{ $occupant->nik }}">
                            <a href="{{ route('admin.employees.show', $occupant->nik) }}" class="font-semibold text-surface-900 hover:text-primary-700 transition">{{ $occupant->nama }}</a>
                            <span class="text-surface-500">{{ $occupant->golongan ?: '-' }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="px-6 py-4 bg-surface-50/80 border-t border-surface-100 flex items-center justify-end">
                    <x-admin.button variant="secondary" wire:click="closeOccupants">Tutup</x-admin.button>
                </div>
            </div>
        </div>
    @endif

    <x-admin.master.action-dialog />
</div>
