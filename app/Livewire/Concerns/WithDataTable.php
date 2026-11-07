<?php

namespace App\Livewire\Concerns;

use App\Exports\DataTableExport;
use App\Models\MasterAuditLog;
use App\Models\User;
use App\Support\DataTable\BulkAction;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use App\Support\DataTable\TableState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Shared data table behaviour for Livewire list components.
 *
 * All table state (search, sort, filters, page size) is client-controlled and
 * therefore re-validated on mount and on every update; the query pipeline
 * whitelists it once more before it reaches SQL.
 *
 * Filter values live on the module's own public properties (typically
 * `#[Url(except: '')] public string $bidang = ''`), named after Filter::$property.
 */
trait WithDataTable
{
    use WithPagination;

    private const SEARCH_MAX_LENGTH = 100;

    private const SELECTION_MAX = 1000;

    private const NO_PERMISSION_MESSAGE = 'Anda tidak memiliki akses untuk tindakan ini.';

    public string $search = '';

    public string $sortField = '';

    public string $sortDirection = '';

    public int $perPage = 0;

    /** @var array<int, string> Selected row keys (bulk actions). */
    public array $selected = [];

    public bool $selectAllMatching = false;

    /** @var ?array{type: 'success'|'error'|'warning', message: string} */
    public ?array $tableNotice = null;

    abstract protected function baseQuery(): Builder;

    /** @return array<int, Column> */
    abstract protected function columns(): array;

    /** @return array{0: string, 1: string} [field, direction] */
    abstract protected function defaultSort(): array;

    abstract protected function tableLabel(): string;

    /** @return array<int, Filter> */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Bulk handlers (`bulk<Name>`) must stay `protected`: a public method would be
     * client-callable and skip the permission check in runBulkAction().
     *
     * @return array<int, BulkAction>
     */
    protected function bulkActions(): array
    {
        return [];
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 25, 50, 100];
    }

    protected function defaultPerPage(): int
    {
        return 25;
    }

    protected function exportName(): ?string
    {
        return null;
    }

    protected function exportAbility(): string
    {
        return 'export-data';
    }

    protected function exportLimit(): int
    {
        return 10000;
    }

    protected function syncsUrl(): bool
    {
        return true;
    }

    protected function paginationName(): string
    {
        return 'page';
    }

    protected function rowKey(): string
    {
        return $this->baseQuery()->getModel()->getKeyName();
    }

    /** Livewire trait hook: runs after URL values have been applied to the properties. */
    public function mountWithDataTable(): void
    {
        $this->search = $this->normaliseSearch($this->search);
        $this->normalisePerPage();
        $this->normaliseSort();

        foreach ($this->filters() as $filter) {
            $this->normaliseFilter($filter);
        }
    }

    /** Livewire trait hook: receives the full path of the updated property. */
    public function updatedWithDataTable(string $property): void
    {
        if ($property === 'search') {
            $this->search = $this->normaliseSearch($this->search);
            $this->resetPage($this->paginationName());
            $this->clearSelection();

            return;
        }

        if ($property === 'perPage') {
            $this->normalisePerPage();
            $this->resetPage($this->paginationName());

            return;
        }

        if ($property === 'sortField' || $property === 'sortDirection') {
            $this->normaliseSort();
            $this->resetPage($this->paginationName());
            $this->clearSelection();

            return;
        }

        // The user ticked or unticked a row: "select all matching" no longer describes the selection.
        if ($property === 'selected' || str_starts_with($property, 'selected.')) {
            $this->selected = $this->normaliseSelection($this->selected);
            $this->selectAllMatching = false;

            return;
        }

        foreach ($this->filters() as $filter) {
            if ($filter->property === $property) {
                $this->normaliseFilter($filter);
                $this->resetPage($this->paginationName());
                $this->clearSelection();

                return;
            }
        }
    }

    /**
     * Livewire legacy query string hook, collected per trait as `queryString<TraitName>()`.
     * Values equal to the post-mount defaults are omitted from the URL by Livewire.
     *
     * @return array<string, array<string, string>>
     */
    protected function queryStringWithDataTable(): array
    {
        if (! $this->syncsUrl()) {
            return [];
        }

        return [
            'search' => ['except' => ''],
            'sortField' => ['as' => 'sort'],
            'sortDirection' => ['as' => 'direction'],
            'perPage' => ['as' => 'per_page'],
        ];
    }

    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, $this->sortableColumns())) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage($this->paginationName());
        $this->clearSelection();
    }

    public function resetFilters(): void
    {
        $this->search = '';

        foreach ($this->filters() as $filter) {
            $this->{$filter->property} = '';
        }

        $this->resetPage($this->paginationName());
        $this->clearSelection();
    }

    /**
     * "Hapus Filter Tambahan": clears only the filters that have a field in the panel. The search term and filters
     * driven from outside the toolbar (e.g. KPI cards) stay; resetFilters() clears everything.
     */
    public function resetPanelFilters(): void
    {
        foreach ($this->filters() as $filter) {
            if ($filter->hasControl()) {
                $this->{$filter->property} = '';
            }
        }

        $this->resetPage($this->paginationName());
        $this->clearSelection();
    }

    /** Comma-joined filter property names, e.g. for `wire:target`. */
    public function filterProperties(): string
    {
        return implode(',', array_map(fn (Filter $filter) => $filter->property, $this->filters()));
    }

    /**
     * Comma-joined `wire:target` list for the loading skeleton and aria-busy: the shared table actions,
     * then the module's own row actions, then the filter properties.
     */
    protected function tableLoadingTargets(string ...$actions): string
    {
        return collect(['search', 'sortBy', 'perPage', 'gotoPage', 'nextPage', 'previousPage', 'resetFilters', ...$actions, $this->filterProperties()])
            ->filter()
            ->implode(', ');
    }

    /** @return list<string> Keys of the rows on the current page, in the form the selection uses. */
    protected function tablePageKeys(): array
    {
        return $this->rows->getCollection()
            ->map(fn ($row) => (string) $row->getAttribute($this->rowKey()))
            ->all();
    }

    /** Visible columns, plus the selection column when the table has bulk actions. */
    protected function tableColumnCount(): int
    {
        $visible = count(array_filter($this->columns(), fn (Column $column) => ! $column->isExportOnly()));

        return $visible + ($this->bulkActions() !== [] ? 1 : 0);
    }

    protected function tableState(): TableState
    {
        $filterValues = [];

        foreach ($this->filters() as $filter) {
            $filterValues[$filter->property] = $this->{$filter->property};
        }

        return new TableState($this->search, $filterValues, $this->sortField, $this->sortDirection);
    }

    /** The searched, filtered and sorted query, unpaginated. */
    protected function filteredQuery(): Builder
    {
        return QueryPipeline::apply(
            $this->baseQuery(),
            $this->columns(),
            $this->filters(),
            $this->tableState(),
            $this->defaultSort(),
        );
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        return $this->filteredQuery()->paginate($this->perPage, pageName: $this->paginationName());
    }

    public function hasActiveFilters(): bool
    {
        if ($this->search !== '') {
            return true;
        }

        foreach ($this->filters() as $filter) {
            $value = $this->{$filter->property};

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Query whose count is the "dari Y" total of the summary bar. Defaults to the base query; override it when the
     * total must follow a scope that one of the filters changes (e.g. a source selector), so Y never contradicts
     * the number shown elsewhere on the screen.
     */
    protected function unfilteredQuery(): Builder
    {
        return $this->baseQuery();
    }

    /**
     * Rows the module offers before any search or filter, for "12 dari 339 ..." summaries.
     * A computed property, so it is cached per request and cannot be called from the browser.
     */
    #[Computed]
    public function unfilteredCount(): int
    {
        return $this->unfilteredQuery()->reorder()->count();
    }

    #[Computed]
    public function matchingCount(): int
    {
        return $this->filteredQuery()->reorder()->count();
    }

    public function selectedCount(): int
    {
        return $this->selectAllMatching ? $this->matchingCount : count($this->selected);
    }

    /**
     * Adds the current page's keys to the selection, or removes only the current page's keys when the
     * whole page is already selected. Selections made on other pages are kept.
     */
    public function toggleSelectPage(): void
    {
        $pageKeys = $this->tablePageKeys();

        $this->selectAllMatching = false;
        $this->selected = $this->normaliseSelection(
            $pageKeys !== [] && array_diff($pageKeys, $this->selected) === []
                ? array_diff($this->selected, $pageKeys)
                : [...$this->selected, ...$pageKeys]
        );
    }

    public function selectAllMatchingRows(): void
    {
        $this->selectAllMatching = true;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAllMatching = false;
    }

    public function runBulkAction(string $name): void
    {
        $action = null;

        foreach ($this->bulkActions() as $candidate) {
            if ($candidate->name === $name) {
                $action = $candidate;
            }
        }

        if ($action === null) {
            abort(403);
        }

        if ($action->requiredAbility !== null && Gate::denies($action->requiredAbility)) {
            $this->tableNotice = ['type' => 'error', 'message' => self::NO_PERMISSION_MESSAGE];

            return;
        }

        if ($this->selectedCount() === 0) {
            $this->tableNotice = ['type' => 'warning', 'message' => 'Pilih minimal satu data.'];

            return;
        }

        $targets = $this->bulkTargets();

        // The audit entry is written in the same transaction: no audit entry, no change.
        try {
            $count = DB::transaction(function () use ($action, $targets): int {
                $count = (int) $this->{$action->handlerMethod()}($targets);

                $this->auditTable('data_table.bulk', [
                    'aksi' => $action->name,
                    'jumlah_baris' => $count,
                    'filter' => $this->activeFilterValues(),
                    'pencarian' => $this->search !== '',
                ]);

                return $count;
            });
        } catch (Throwable $e) {
            report($e);
            $this->tableNotice = ['type' => 'error', 'message' => 'Tindakan gagal, tidak ada data yang berubah.'];

            return;
        }

        $this->tableNotice = ['type' => 'success', 'message' => $action->successText($count)];
        $this->clearSelection();
        unset($this->rows, $this->matchingCount);
    }

    /** Downloads the searched, filtered (and optionally narrowed by selection) rows as an Excel file. */
    public function export(): ?BinaryFileResponse
    {
        $name = $this->exportName();

        if ($name === null) {
            abort(404);
        }

        if (Gate::denies($this->exportAbility())) {
            $this->tableNotice = ['type' => 'error', 'message' => self::NO_PERMISSION_MESSAGE];

            return null;
        }

        $query = $this->filteredQuery();

        // The client-writable selection can only narrow the export, never widen it.
        if ($this->selected !== [] && ! $this->selectAllMatching) {
            $query->whereIn($query->getModel()->qualifyColumn($this->rowKey()), $this->selected);
        }

        $count = (clone $query)->reorder()->count();

        if ($count > $this->exportLimit()) {
            $this->tableNotice = ['type' => 'warning', 'message' => 'Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu.'];

            return null;
        }

        $this->auditTable('data_table.export', [
            'jumlah_baris' => $count,
            'filter' => $this->activeFilterValues(),
            'pencarian' => $this->search !== '',
        ]);

        return Excel::download($this->buildExport($query, auth()->user()), $this->exportFileName());
    }

    /**
     * Name of the downloaded file. A module may override it; the permission check, row limit
     * and audit entry in export() apply regardless.
     */
    protected function exportFileName(): string
    {
        return $this->exportName().'_'.now()->format('Y-m-d_His').'.xlsx';
    }

    /**
     * The workbook for the already authorised, limited and audited query. A module may return its
     * own export object (for example a compliance format) instead of the generic one.
     */
    protected function buildExport(Builder $query, ?User $user): object
    {
        return new DataTableExport($query, $this->columns(), $this->tableLabel(), $user);
    }

    /**
     * Rows a bulk action may touch. Always derived from the current search and filters;
     * the client-writable selection can only narrow it, never widen it.
     */
    protected function bulkTargets(): Builder
    {
        // Sorting is irrelevant for writes and not portable inside UPDATE/DELETE statements.
        $targets = $this->filteredQuery()->reorder();

        if (! $this->selectAllMatching) {
            $targets->whereIn($targets->getModel()->qualifyColumn($this->rowKey()), $this->selected);
        }

        return $targets;
    }

    /** @return array<string, string> [filter property => value] for filters that are set and allowed. */
    protected function activeFilterValues(): array
    {
        $values = [];

        foreach ($this->filters() as $filter) {
            $value = $this->{$filter->property};

            if ($value !== null && $value !== '' && $filter->allows($value)) {
                $values[$filter->property] = $value;
            }
        }

        return $values;
    }

    /** @param array<string, mixed> $detail Never include the raw search term (it may contain personal data). */
    protected function auditTable(string $action, array $detail): void
    {
        MasterAuditLog::record(auth()->user(), $action, 'data_table', null, $this->tableLabel(), null, $detail, null);
    }

    /** @return array<string, Column> */
    private function sortableColumns(): array
    {
        $sortable = [];

        foreach ($this->columns() as $column) {
            if ($column->isSortable()) {
                $sortable[$column->key] = $column;
            }
        }

        return $sortable;
    }

    /**
     * @param  array<mixed>  $selection
     * @return array<int, string>
     */
    private function normaliseSelection(array $selection): array
    {
        $keys = [];

        foreach ($selection as $key) {
            if (is_int($key) || is_string($key)) {
                $key = (string) $key;

                if ($key !== '') {
                    $keys[] = $key;
                }
            }
        }

        return array_slice(array_values(array_unique($keys)), 0, self::SELECTION_MAX);
    }

    private function normaliseSearch(string $search): string
    {
        return mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH);
    }

    private function normalisePerPage(): void
    {
        if (! in_array($this->perPage, $this->perPageOptions(), true)) {
            $this->perPage = $this->resolvedDefaultPerPage();
        }
    }

    /**
     * defaultPerPage() when it is one of perPageOptions(); otherwise the first option of at least 25,
     * else the first option.
     */
    private function resolvedDefaultPerPage(): int
    {
        $options = array_values($this->perPageOptions());
        $default = $this->defaultPerPage();

        if ($options === [] || in_array($default, $options, true)) {
            return $default;
        }

        foreach ($options as $option) {
            if ($option >= 25) {
                return $option;
            }
        }

        return $options[0];
    }

    private function normaliseSort(): void
    {
        [$defaultField, $defaultDirection] = $this->defaultSort();

        if (! array_key_exists($this->sortField, $this->sortableColumns())) {
            $this->sortField = $defaultField;
        }

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = $defaultDirection;
        }
    }

    private function normaliseFilter(Filter $filter): void
    {
        if (! $filter->allows($this->{$filter->property})) {
            $this->{$filter->property} = '';
        }
    }
}
