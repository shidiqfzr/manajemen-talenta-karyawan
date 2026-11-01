@php
    $selectClass = 'w-full pl-7.5 pr-7 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition appearance-none cursor-pointer shadow-2xs';
    $dateClass = 'w-full px-3 py-2 bg-white border border-surface-300 rounded-xl text-xs text-surface-800 focus:outline-none focus:ring-2 focus:ring-primary-100 focus:border-primary-600 transition shadow-2xs';
    $adminName = $userId !== '' ? $this->admins->firstWhere('id', (int) $userId)?->name : null;
@endphp
<div class="bg-white rounded-2xl border border-surface-200 shadow-xs overflow-hidden">
    <!-- Bilah pemuatan -->
    <div wire:loading.delay class="w-full h-1 bg-gradient-to-r from-primary-600 via-primary-400 to-primary-600 motion-safe:animate-pulse"></div>

    {{-- Wilayah live permanen: hasil filter diumumkan walau penomoran halaman tidak tampil. --}}
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ number_format($logs->total()) }} catatan ditemukan</div>

    <!-- Toolbar: pencarian + filter -->
    <div class="p-3 sm:p-4 bg-surface-50/70 border-b border-surface-200 space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center gap-2.5">
            <x-admin.search-input model="search" shortcut="/" placeholder="Cari unit, jabatan, admin, atau alasan..." containerClass="relative flex-1 min-w-[260px]" />

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 sm:flex-initial min-w-[170px]">
                    <label for="riwayat-entitas" class="sr-only">Jenis Data</label>
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500"><i class="fas fa-tag text-[11px]" aria-hidden="true"></i></div>
                    <select id="riwayat-entitas" wire:model.live="entityType" class="{{ $selectClass }}">
                        <option value="">Semua Jenis Data</option>
                        @foreach ($entityLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500"><i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i></div>
                </div>

                <div class="relative flex-1 sm:flex-initial min-w-[170px]">
                    <label for="riwayat-admin" class="sr-only">Admin</label>
                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none text-surface-500"><i class="fas fa-user text-[11px]" aria-hidden="true"></i></div>
                    <select id="riwayat-admin" wire:model.live="userId" class="{{ $selectClass }}">
                        <option value="">Semua Admin</option>
                        @foreach ($this->admins as $admin)
                            <option value="{{ $admin->id }}">{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-surface-500"><i class="fas fa-chevron-down text-[10px]" aria-hidden="true"></i></div>
                </div>

                <div class="flex items-center gap-1.5">
                    <label for="riwayat-dari" class="sr-only">Dari Tanggal</label>
                    <input id="riwayat-dari" type="date" wire:model.live="from" class="{{ $dateClass }}">
                    <span class="text-xs text-surface-500" aria-hidden="true">–</span>
                    <label for="riwayat-sampai" class="sr-only">Sampai Tanggal</label>
                    <input id="riwayat-sampai" type="date" wire:model.live="to" class="{{ $dateClass }}">
                </div>

                @if ($this->hasFilters())
                    <button type="button" wire:click="resetFilters"
                        class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-danger-50 hover:text-danger-700 text-surface-600 border border-surface-300 hover:border-danger-200 rounded-xl text-xs font-semibold transition cursor-pointer shadow-2xs whitespace-nowrap"
                        title="Reset semua filter">
                        <i class="fas fa-rotate-left text-[11px]" aria-hidden="true"></i>
                        <span class="hidden sm:inline">Reset</span>
                    </button>
                @endif
            </div>
        </div>

        @if ($this->hasFilters())
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-medium text-surface-500">Filter Aktif:</span>
                @if ($search !== '')
                    <button type="button" wire:click="$set('search', '')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-primary-50 text-primary-800 border border-primary-200 hover:bg-primary-100 transition cursor-pointer">
                        <span>Pencarian: <strong>{{ $search }}</strong></span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endif
                @if ($entityType !== '' && isset($entityLabels[$entityType]))
                    <button type="button" wire:click="$set('entityType', '')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface-100 text-surface-800 border border-surface-300 hover:bg-surface-200 transition cursor-pointer">
                        <span>Jenis: <strong>{{ $entityLabels[$entityType] }}</strong></span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endif
                @if ($adminName)
                    <button type="button" wire:click="$set('userId', '')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface-100 text-surface-800 border border-surface-300 hover:bg-surface-200 transition cursor-pointer">
                        <span>Admin: <strong>{{ $adminName }}</strong></span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endif
                @if ($from !== '')
                    <button type="button" wire:click="$set('from', '')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface-100 text-surface-800 border border-surface-300 hover:bg-surface-200 transition cursor-pointer">
                        <span>Dari: <strong>{{ $from }}</strong></span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endif
                @if ($to !== '')
                    <button type="button" wire:click="$set('to', '')" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface-100 text-surface-800 border border-surface-300 hover:bg-surface-200 transition cursor-pointer">
                        <span>Sampai: <strong>{{ $to }}</strong></span><i class="fas fa-times text-[10px]" aria-hidden="true"></i>
                    </button>
                @endif
            </div>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-surface-50/90 border-b border-surface-200 text-[11px] font-bold text-surface-700 uppercase tracking-wider">
                    <x-admin.table.th sortable field="created_at" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Waktu</x-admin.table.th>
                    <x-admin.table.th sortable field="user_name" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Admin</x-admin.table.th>
                    <x-admin.table.th sortable field="action" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Aksi</x-admin.table.th>
                    <x-admin.table.th sortable field="entity_label" :sort-field="$sortField" :sort-direction="$sortDirection" class="py-3.5 px-5">Data</x-admin.table.th>
                    <x-admin.table.th class="py-3.5 px-5">Perubahan</x-admin.table.th>
                    <x-admin.table.th class="py-3.5 px-5">Alasan</x-admin.table.th>
                </tr>
            </thead>
            <tbody class="divide-y divide-surface-200 text-surface-800 align-top">
                @forelse ($logs as $log)
                    <tr class="hover:bg-surface-50/75 transition" wire:key="log-{{ $log->id }}">
                        <td class="py-3.5 px-5 text-surface-600 whitespace-nowrap tabular-nums">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                        <td class="py-3.5 px-5 text-surface-800">{{ $log->user_name }}</td>
                        <td class="py-3.5 px-5 font-bold text-surface-800">{{ \App\Models\MasterAuditLog::actionLabel($log->action) }}</td>
                        <td class="py-3.5 px-5 text-surface-600">
                            <span class="block text-[10px] font-bold uppercase text-surface-500">{{ $entityLabels[$log->entity_type] ?? $log->entity_type }}</span>
                            {{ $log->entity_label }}
                        </td>
                        <td class="py-3.5 px-5 text-surface-600">
                            @forelse ($log->changeSummary() as $line)
                                <div>{{ $line }}</div>
                            @empty
                                -
                            @endforelse
                        </td>
                        <td class="py-3.5 px-5 text-surface-600">{{ $log->reason ?: '-' }}</td>
                    </tr>
                @empty
                    <x-admin.table.empty-state :colspan="6" icon="fas fa-clock-rotate-left"
                        :message="$this->hasFilters() ? 'Tidak ada catatan yang sesuai dengan filter saat ini. Ubah kata kunci atau reset filter.' : 'Belum ada perubahan master yang tercatat.'" />
                @endforelse
            </tbody>
        </table>
    </div>

    <x-admin.table.pagination :paginator="$logs" noun="catatan" :per-page-options="\App\Livewire\Admin\Master\AuditHistory::PER_PAGE_OPTIONS" />
</div>
