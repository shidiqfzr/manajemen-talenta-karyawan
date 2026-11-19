<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\MutasiChainProposal;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\HrisPermission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Usulan Mutasi Berantai (Hub Mutasi, tab "Usulan Berantai") on the shared data table layer (WithDataTable).
 * A preparer sees their own proposals; an approver also sees every submitted proposal (DOMAIN.md §5.2.6).
 * Rows link to the chain screen, which holds every action (edit, submit, withdraw, approve, return).
 */
class MutasiChainProposalTable extends Component
{
    use WithDataTable;

    /** Tab key => label. `all` means no status filter. */
    public const TABS = [
        'all' => 'Semua',
        'pending' => 'Menunggu Persetujuan',
        'draft' => 'Draf & Revisi',
        'closed' => 'Selesai',
    ];

    /** Tab key => proposal statuses it covers. */
    public const TAB_STATUSES = [
        'pending' => [MutasiChainProposal::STATUS_PENDING],
        'draft' => [MutasiChainProposal::STATUS_DRAFT, MutasiChainProposal::STATUS_REJECTED],
        'closed' => [MutasiChainProposal::STATUS_EXECUTED, MutasiChainProposal::STATUS_EXPIRED],
    ];

    /** '' = all; otherwise a key of TAB_STATUSES. */
    public string $status = '';

    /** Route middleware is not re-applied to Livewire update requests: check the permission every request. */
    public function boot(): void
    {
        abort_unless(Gate::any([HrisPermission::MUTASI_PREPARE, HrisPermission::MUTASI_APPROVE]), 403, 'Unauthorized.');
    }

    /**
     * Rows per status tab, over every visible proposal regardless of search.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        $byStatus = $this->baseQuery()->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = ['all' => (int) $byStatus->sum()];

        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = (int) $byStatus->only($statuses)->sum();
        }

        return $counts;
    }

    protected function baseQuery(): Builder
    {
        $user = auth()->user();
        $query = MutasiChainProposal::query();

        // Drafts stay private to their maker; submitted proposals are visible to approvers
        return $user->can(HrisPermission::MUTASI_APPROVE)
            ? $query->where(fn (Builder $q) => $q->where('created_by', $user->id)->orWhereNotNull('submitted_at'))
            : $query->where('created_by', $user->id);
    }

    protected function columns(): array
    {
        return [
            Column::make('nomor_sk', 'Usulan')->searchable(),
            Column::make('created_by_name', 'Penyusun')->searchable(),
            Column::make('participant_count', 'Karyawan')->sortable(),
            Column::make('status', 'Status'),
            Column::make('aksi', 'Aksi')->align('right'),
        ];
    }

    protected function filters(): array
    {
        $options = self::TABS;
        unset($options['all']);

        // The status tabs are the control for this filter; the toolbar only shows it as a removable chip.
        return [
            Filter::select('status', 'Status', $options)
                ->icon('fas fa-flag')
                ->withoutControl()
                ->apply(fn (Builder $query, string $value) => $query->whereIn('status', self::TAB_STATUSES[$value])),
        ];
    }

    protected function defaultSort(): array
    {
        return ['updated_at', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Usulan Mutasi Berantai';
    }

    /** The hub hosts several tables: keep this one out of the shared URL. */
    protected function syncsUrl(): bool
    {
        return false;
    }

    protected function paginationName(): string
    {
        return 'usulanPage';
    }

    public function render(): View
    {
        return view('livewire.admin.mutasi-chain-proposal-table');
    }
}
