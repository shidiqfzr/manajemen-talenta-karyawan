<?php

namespace App\Livewire\Concerns;

use App\Support\DataTable\BulkAction;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use App\Support\DataTable\TableState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

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
    public function queryStringWithDataTable(): array
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

    public function tableState(): TableState
    {
        $filterValues = [];

        foreach ($this->filters() as $filter) {
            $filterValues[$filter->property] = $this->{$filter->property};
        }

        return new TableState($this->search, $filterValues, $this->sortField, $this->sortDirection);
    }

    /** The searched, filtered and sorted query, unpaginated. */
    public function filteredQuery(): Builder
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

    protected function clearSelection(): void
    {
        $this->selected = [];
        $this->selectAllMatching = false;
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
