{{-- Daftar SK struktur organisasi di atas lapisan tabel data bersama (referensi: livewire/admin/employees/import-batch-table.blade.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
    $counts = $this->tabCounts;
@endphp
<div>
    <x-admin.table.card :notice="$tableNotice">
        <nav class="flex flex-wrap items-center gap-1 px-4 pt-3 border-b border-surface-200" aria-label="Filter status SK">
            @foreach ($this::TABS as $key => $label)
                @php $active = ($status === '' ? 'all' : $status) === $key; @endphp
                <button type="button" wire:key="tab-{{ $key }}" data-tab="{{ $key }}"
                    wire:click="$set('status', '{{ $key === 'all' ? '' : $key }}')"
                    @if ($active) aria-current="page" @endif
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px rounded-t cursor-pointer transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $active ? 'border-primary-600 text-primary-800' : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300' }}">
                    {{ $label }}
                    <span class="ml-1 font-mono tabular-nums text-xs {{ $key === 'open' && $counts[$key] > 0 ? 'text-warning-800' : 'text-surface-500' }}">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </nav>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            search-placeholder="Cari nomor SK atau perihal..." />
        <x-admin.table.header title="Surat Keputusan Struktur" :paginator="$rows" noun="SK" />

        {{-- relative: header sr-only bersifat absolute; tanpa penampung berposisi ia lolos dari overflow dan melebarkan halaman di ponsel. --}}
        <div class="relative overflow-x-auto">
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
                    @forelse ($rows as $decree)
                        <tr wire:key="row-{{ $decree->id }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 max-w-[260px]">
                                <a href="{{ route('admin.master.sk.show', $decree) }}" class="block font-semibold text-surface-900 truncate hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded" title="{{ $decree->sk_number }}">{{ $decree->sk_number }}</a>
                                <span class="block text-xs text-surface-500">SK #{{ $decree->id }}</span>
                            </td>
                            <td class="px-4 py-3 text-surface-700 max-w-[320px]">
                                <span class="block truncate" title="{{ $decree->title }}">{{ $decree->title }}</span>
                            </td>
                            <td class="px-4 py-3 text-surface-600 whitespace-nowrap tabular-nums">{{ $decree->sk_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 text-surface-600 whitespace-nowrap tabular-nums">{{ $decree->effective_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3">
                                <x-admin.decree-status :status="$decree->status" :effective-date="$decree->effective_date" />
                                @if ($decree->decider && in_array($decree->status, ['approved', 'applied', 'rejected', 'cancelled'], true))
                                    <span class="block mt-1 text-xs text-surface-500">{{ $decree->status === 'rejected' ? 'Ditolak' : ($decree->status === 'cancelled' ? 'Dibatalkan' : 'Disetujui') }} {{ $decree->decider->name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-admin.button href="{{ route('admin.master.sk.show', $decree) }}" variant="secondary" size="sm" :icon="$decree->status === 'submitted' ? 'fas fa-clipboard-check' : 'fas fa-eye'" wire:navigate>{{ $decree->status === 'submitted' ? 'Tinjau SK' : 'Lihat Detail' }}</x-admin.button>
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada SK yang cocok dengan filter ini. Ubah filter atau kata kunci pencarian.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-file-signature" message="Belum ada SK struktur organisasi. Mulai dengan Catat SK dari Holding." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="SK" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
