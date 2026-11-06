<?php

namespace App\Livewire\Concerns;

use App\Models\MasterAuditLog;
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

    /** @return array<int, BulkAction> */
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

    /** Comma-joined filter property names, e.g. for `wire:target`. */
    public function filterProperties(): string
    {
        return implode(',', array_map(fn (Filter $filter) => $filter->property, $this->filters()));
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

    #[Computed]
    public function matchingCount(): int
    {
        return $this->filteredQuery()->reorder()->count();
    }

    public function selectedCount(): int
    {
        return $this->selectAllMatching ? $this->matchingCount : count($this->selected);
    }

    /** Selects the keys on the current page, or clears them when the whole page is already selected. */
    public function toggleSelectPage(): void
    {
        $pageKeys = $this->rows->getCollection()
            ->map(fn ($row) => (string) $row->getAttribute($this->rowKey()))
            ->all();

        $this->selectAllMatching = false;
        $this->selected = $pageKeys !== [] && array_diff($pageKeys, $this->selected) === [] ? [] : $pageKeys;
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
            $this->tableNotice = ['type' => 'error', 'message' => 'Anda tidak memiliki akses untuk tindakan ini.'];

            return;
        }

        if ($this->selectedCount() === 0) {
            $this->tableNotice = ['type' => 'warning', 'message' => 'Pilih minimal satu data.'];

            return;
        }

        $targets = $this->bulkTargets();

        try {
            $count = DB::transaction(fn () => $this->{$action->handlerMethod()}($targets));
        } catch (Throwable $e) {
            report($e);
            $this->tableNotice = ['type' => 'error', 'message' => 'Tindakan gagal, tidak ada data yang berubah.'];

            return;
        }

        $this->auditTable('data_table.bulk', [
            'aksi' => $action->name,
            'jumlah_baris' => $count,
            'filter' => $this->activeFilterValues(),
            'pencarian' => $this->search !== '',
        ]);

        $this->tableNotice = ['type' => 'success', 'message' => $action->successText($count)];
        $this->clearSelection();
        unset($this->rows, $this->matchingCount);
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

    private function normaliseSearch(string $search): string
    {
        return mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH);
    }

    private function normalisePerPage(): void
    {
        if (! in_array($this->perPage, $this->perPageOptions(), true)) {
            $this->perPage = $this->defaultPerPage();
        }
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
