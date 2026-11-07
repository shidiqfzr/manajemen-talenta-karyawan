<?php

namespace App\Support\DataTable;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies search, filters and sorting to a base query.
 *
 * The table state originates from the client and is never trusted: only
 * whitelisted columns, filter options and directions reach the query.
 */
final class QueryPipeline
{
    /**
     * @param  array<int, Column>  $columns
     * @param  array<int, Filter>  $filters
     * @param  array{0: string, 1: string}  $defaultSort  [field, direction]
     */
    public static function apply(Builder $base, array $columns, array $filters, TableState $state, array $defaultSort): Builder
    {
        self::applySearch($base, $columns, $state->search);
        self::applyFilters($base, $filters, $state->filterValues);
        self::applySort($base, $columns, $state, $defaultSort);

        return $base;
    }

    /** Escape LIKE wildcards using `!` as the escape character. */
    public static function escapeLike(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /** @param  array<int, Column>  $columns */
    private static function applySearch(Builder $base, array $columns, string $term): void
    {
        if ($term === '') {
            return;
        }

        $searchable = array_filter($columns, fn (Column $column) => $column->isSearchable());

        if ($searchable === []) {
            return;
        }

        $base->where(function (Builder $q) use ($searchable, $term) {
            foreach ($searchable as $column) {
                if ($custom = $column->searchUsing()) {
                    $q->orWhere(fn (Builder $inner) => $custom($inner, $term));

                    continue;
                }

                $q->orWhere(fn (Builder $inner) => self::whereLikeAny($inner, [$column->key], $term));
            }
        });
    }

    /**
     * Add one grouped OR of `column LIKE %term% ESCAPE '!'` over the given columns.
     *
     * Columns are qualified with the model table and wrapped by the query grammar;
     * the term is escaped with escapeLike() and always bound as a parameter. All
     * conditions live in a single where() group so an OR never leaks outside it.
     *
     * @param  array<int, string>  $columns
     */
    public static function whereLikeAny(Builder $query, array $columns, string $term): void
    {
        $pattern = '%'.self::escapeLike($term).'%';

        $query->where(function (Builder $group) use ($columns, $pattern) {
            $grammar = $group->getQuery()->getGrammar();

            foreach ($columns as $column) {
                $group->orWhereRaw($grammar->wrap($group->qualifyColumn($column))." LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }

    /**
     * @param  array<int, Filter>  $filters
     * @param  array<string, mixed>  $values
     */
    private static function applyFilters(Builder $base, array $filters, array $values): void
    {
        foreach ($filters as $filter) {
            $value = $values[$filter->property] ?? null;

            if (! is_string($value) || $value === '' || ! $filter->allows($value)) {
                continue;
            }

            $filter->applyTo($base, $value);
        }
    }

    /**
     * @param  array<int, Column>  $columns
     * @param  array{0: string, 1: string}  $defaultSort
     */
    private static function applySort(Builder $base, array $columns, TableState $state, array $defaultSort): void
    {
        $sortable = [];
        foreach ($columns as $column) {
            if ($column->isSortable()) {
                $sortable[$column->key] = $column;
            }
        }

        $field = array_key_exists($state->sortField, $sortable) ? $state->sortField : $defaultSort[0];
        $direction = in_array($state->sortDirection, ['asc', 'desc'], true) ? $state->sortDirection : $defaultSort[1];

        $custom = isset($sortable[$field]) ? $sortable[$field]->sortUsing() : null;

        if ($custom) {
            $custom($base, $direction);
        } else {
            $base->orderBy($base->getModel()->qualifyColumn($field), $direction);
        }

        $base->orderBy($base->getModel()->getQualifiedKeyName(), $direction);
    }
}
