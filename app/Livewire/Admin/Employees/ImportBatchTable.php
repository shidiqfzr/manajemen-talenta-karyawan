<?php

namespace App\Livewire\Admin\Employees;

use App\Livewire\Concerns\WithDataTable;
use App\Models\EmployeeImportLog;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Riwayat & Persetujuan Impor on the shared data table layer (WithDataTable).
 * The status tabs above the table drive the `status` filter, so the toolbar only shows it as a removable chip.
 * Row actions stay plain links; approval, rejection and rollback are handled by EmployeeImportBatchController.
 */
class ImportBatchTable extends Component
{
    use WithDataTable;

    /** Tab key => label. `all` means no status filter. */
    public const TABS = [
        'all' => 'Semua',
        'pending' => 'Perlu Tindakan',
        'saved' => 'Tersimpan',
        'closed' => 'Ditutup',
    ];

    /** Tab key => import statuses it covers. */
    public const TAB_STATUSES = [
        'pending' => [EmployeeImportLog::STATUS_PENDING, EmployeeImportLog::STATUS_APPROVED],
        'saved' => [EmployeeImportLog::STATUS_COMPLETED],
        'closed' => [EmployeeImportLog::STATUS_REJECTED, EmployeeImportLog::STATUS_EXPIRED, EmployeeImportLog::STATUS_ROLLED_BACK],
    ];

    /** '' = all; otherwise a key of TAB_STATUSES. */
    #[Url(except: '')]
    public string $status = '';

    /**
     * Admin-only screen: route middleware is not re-applied to Livewire update requests, so refuse
     * non-admins on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /**
     * Rows per status tab, over every import regardless of search.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function tabCounts(): array
    {
        $byStatus = EmployeeImportLog::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $counts = ['all' => (int) $byStatus->sum()];

        foreach (self::TAB_STATUSES as $key => $statuses) {
            $counts[$key] = (int) $byStatus->only($statuses)->sum();
        }

        return $counts;
    }

    protected function baseQuery(): Builder
    {
        return EmployeeImportLog::query()->withCount('changes');
    }

    protected function columns(): array
    {
        return [
            Column::make('file_name', 'Berkas')->searchable(),
            Column::make('user_name', 'Pengaju')->searchable(),
            Column::make('hasil', 'Hasil'),
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
        return ['id', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Riwayat & Persetujuan Impor';
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 15, 25, 50, 100];
    }

    protected function defaultPerPage(): int
    {
        return 15;
    }

    public function render(): View
    {
        return view('livewire.admin.employees.import-batch-table');
    }
}
