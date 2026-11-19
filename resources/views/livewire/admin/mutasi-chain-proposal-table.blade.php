{{-- Usulan Mutasi Berantai di atas lapisan tabel data bersama (pola sama dengan Riwayat & Persetujuan Impor). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
    $counts = $this->tabCounts;
    $requiresApproval = \App\Services\Mutasi\ChainProposalService::requiresApproval();
    $statusTone = [
        \App\Models\MutasiChainProposal::STATUS_DRAFT => 'bg-surface-100 text-surface-700 border-surface-300',
        \App\Models\MutasiChainProposal::STATUS_PENDING => 'bg-info-50 text-info-800 border-info-200',
        \App\Models\MutasiChainProposal::STATUS_REJECTED => 'bg-warning-50 text-warning-800 border-warning-300',
        \App\Models\MutasiChainProposal::STATUS_EXECUTED => 'bg-primary-50 text-primary-800 border-primary-300',
        \App\Models\MutasiChainProposal::STATUS_EXPIRED => 'bg-surface-100 text-surface-600 border-surface-300',
    ];
@endphp
<div>
    <x-admin.table.card :notice="$tableNotice">
        <nav class="flex flex-wrap items-center gap-1 px-4 pt-3 border-b border-surface-200" aria-label="Filter status usulan">
            @foreach ($this::TABS as $key => $label)
                @php $active = ($status === '' ? 'all' : $status) === $key; @endphp
                <button type="button" wire:key="tab-{{ $key }}" data-tab="{{ $key }}"
                    wire:click="$set('status', '{{ $key === 'all' ? '' : $key }}')"
                    @if ($active) aria-current="page" @endif
                    @if ($key === 'pending' && ! $requiresApproval) title="Persetujuan dua pihak belum diaktifkan, sehingga SK dapat langsung diterbitkan." @endif
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px rounded-t cursor-pointer transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $active ? 'border-primary-600 text-primary-800' : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300' }}">
                    {{ $label }}
                    <span class="ml-1 font-mono tabular-nums text-xs {{ $key === 'pending' && $counts[$key] > 0 ? 'text-warning-800' : 'text-surface-500' }}">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </nav>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            search-placeholder="Cari nomor SK atau penyusun..." />
        <x-admin.table.header title="Usulan Mutasi Berantai" :paginator="$rows" noun="Usulan" />

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
                    @forelse ($rows as $proposal)
                        @php
                            $canDecide = \App\Services\Mutasi\ChainProposalService::canDecide($proposal, auth()->user());
                            $isMine = (int) $proposal->created_by === (int) auth()->id();
                            $actionLabel = match (true) {
                                $canDecide => 'Tinjau & Putuskan',
                                $isMine && $proposal->isEditable() => 'Lanjutkan Penyusunan',
                                default => 'Lihat Usulan',
                            };
                            $actionIcon = $canDecide ? 'fas fa-clipboard-check' : ($isMine && $proposal->isEditable() ? 'fas fa-pen' : 'fas fa-eye');
                        @endphp
                        <tr wire:key="row-{{ $proposal->id }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 max-w-[320px]">
                                <a href="{{ route('admin.mutasi.chain', ['usulan' => $proposal->id]) }}" class="block font-semibold text-surface-900 truncate hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded">
                                    {{ $proposal->nomor_sk ?: 'Nomor SK belum diisi' }}
                                </a>
                                <span class="block text-xs text-surface-500 truncate">Usulan #{{ $proposal->id }}@if ($proposal->catatan) &bull; {{ $proposal->catatan }}@endif</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block text-surface-900">{{ $proposal->created_by_name ?? '-' }}</span>
                                <span class="block text-xs text-surface-500">Diperbarui {{ $proposal->updated_at->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono tabular-nums font-semibold text-surface-900">{{ $proposal->participant_count }}</span> orang
                                <span class="block text-xs text-surface-500">TMT {{ $proposal->tmt_awal?->format('d/m/Y') ?? '-' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold border {{ $statusTone[$proposal->status] ?? $statusTone['draft'] }}">{{ $proposal->statusLabel() }}</span>
                                @if ($proposal->isPending() && $proposal->expires_at)
                                    <span class="block mt-1 text-xs {{ $proposal->expires_at->isPast() ? 'text-danger-700 font-semibold' : 'text-surface-500' }}">Batas {{ $proposal->expires_at->format('d/m/Y') }}</span>
                                @elseif ($proposal->decided_by_name && $proposal->decided_at)
                                    <span class="block mt-1 text-xs text-surface-500">{{ $proposal->status === 'rejected' ? 'Dikembalikan' : 'Disetujui' }} {{ $proposal->decided_by_name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-admin.row-menu :label="'usulan #'.$proposal->id">
                                    <x-admin.master.menu-item :href="route('admin.mutasi.chain', ['usulan' => $proposal->id])" :icon="$actionIcon">
                                        {{ $actionLabel }}
                                    </x-admin.master.menu-item>
                                </x-admin.row-menu>
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada usulan yang cocok dengan filter ini. Ubah filter atau kata kunci pencarian.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-diagram-project" message="Belum ada usulan mutasi berantai. Susun usulan baru dari tombol Mutasi Berantai." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="usulan" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
