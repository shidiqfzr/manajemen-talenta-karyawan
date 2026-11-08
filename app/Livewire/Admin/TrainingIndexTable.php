<?php

namespace App\Livewire\Admin;

use App\Exports\TrainingsExport;
use App\Livewire\Concerns\WithDataTable;
use App\Models\EmployeeStatistic;
use App\Models\Training;
use App\Models\User;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Manajemen Pelatihan list (admin trainings index) on the shared data table layer (WithDataTable).
 * The summary cards come from the same filtered query as the rows, so they follow search and filters.
 * Row actions (show, edit, delete) stay plain links and a CSRF-protected DELETE form handled by TrainingController.
 */
class TrainingIndexTable extends Component
{
    use WithDataTable;

    /** Kept as `start_date` / `end_date` in the URL so existing bookmarks keep working. */
    #[Url(as: 'start_date', except: '')]
    public string $startDate = '';

    #[Url(as: 'end_date', except: '')]
    public string $endDate = '';

    #[Url(except: '')]
    public string $jenis = '';

    #[Url(except: '')]
    public string $metode = '';

    /**
     * Admin-only screen: route middleware is not re-applied to Livewire update requests, so refuse
     * non-admins on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function jenisOptions(): array
    {
        return $this->distinctOptions('jenis');
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function metodeOptions(): array
    {
        return $this->distinctOptions('metode');
    }

    /**
     * Totals over every row matching the current search and filters (not just the visible page).
     * `karyawan` is the employee headcount over the selected period and is only known when both dates are set.
     *
     * @return array{man_hours: float, tiket: float, biaya: float, karyawan: ?int}
     */
    public function summary(): array
    {
        $query = (clone $this->filteredQuery())->reorder();

        $karyawan = null;

        if ($this->startDate !== '' && $this->endDate !== '') {
            $statistics = EmployeeStatistic::query()
                ->whereBetween('periode', [
                    Carbon::parse($this->startDate)->startOfMonth()->toDateString(),
                    Carbon::parse($this->endDate)->endOfMonth()->toDateString(),
                ])
                ->get();

            if ($statistics->count()) {
                $karyawan = (int) $statistics->sum('total_karyawan');
            }
        }

        return [
            'man_hours' => (float) (clone $query)->sum('jumlah_man_hours'),
            'tiket' => (float) (clone $query)->sum('biaya_tiket_peserta'),
            'biaya' => (float) (clone $query)->sum('biaya_pelatihan'),
            'karyawan' => $karyawan,
        ];
    }

    protected function baseQuery(): Builder
    {
        return Training::query()->withCount('employees');
    }

    protected function columns(): array
    {
        return [
            Column::make('judul', 'Judul')->sortable()->searchable(),
            Column::make('jenis', 'Jenis'),
            Column::make('metode', 'Metode'),
            Column::make('bidang', 'Bidang')->searchable(
                fn (Builder $q, string $term) => QueryPipeline::whereLikeAny($q, ['bidang_pelatihan'], $term)
            ),
            Column::make('tanggal_mulai', 'Tanggal Mulai')->sortable(),
            Column::make('jumlah_man_hours', 'Man Hours')->sortable()->mono(),
            // employees_count is a withCount alias, not a column of the trainings table.
            Column::make('employees_count', 'Peserta')->sortable(
                fn (Builder $q, string $direction) => $q->orderBy('employees_count', $direction)
            )->mono(),
            Column::make('aksi', 'Aksi')->align('center'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('jenis', 'Jenis', $this->jenisOptions)
                ->icon('fas fa-tag')
                ->allLabel('Semua Jenis')
                ->apply(fn (Builder $query, string $value) => $query->where('jenis', $value)),
            Filter::select('metode', 'Metode', $this->metodeOptions)
                ->icon('fas fa-chalkboard-teacher')
                ->allLabel('Semua Metode')
                ->apply(fn (Builder $query, string $value) => $query->where('metode', $value)),
            // tanggal_mulai is a DATE column: compare by day so the boundary days are included on every database.
            Filter::dateFrom('startDate', 'Dari Tanggal', 'tanggal_mulai')
                ->apply(fn (Builder $query, string $value) => $query->whereDate('tanggal_mulai', '>=', $value)),
            Filter::dateTo('endDate', 'Sampai Tanggal', 'tanggal_mulai')
                ->apply(fn (Builder $query, string $value) => $query->whereDate('tanggal_mulai', '<=', $value)),
        ];
    }

    protected function defaultSort(): array
    {
        return ['tanggal_mulai', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Daftar Pelatihan';
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 20, 50, 100];
    }

    protected function defaultPerPage(): int
    {
        return 20;
    }

    protected function exportName(): ?string
    {
        return 'data_pelatihan';
    }

    /**
     * Keeps the business workbook (all training columns) instead of the generic table export;
     * the already authorised and limited query makes the file follow search and filters.
     */
    protected function buildExport(Builder $query, ?User $user): object
    {
        return new TrainingsExport(['start_date' => $this->startDate, 'end_date' => $this->endDate], $query);
    }

    public function render(): View
    {
        return view('livewire.admin.training-index-table');
    }

    /** @return array<string, string> */
    private function distinctOptions(string $column): array
    {
        return Training::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn ($value) => [(string) $value => (string) $value])
            ->all();
    }
}
