<?php

namespace App\Support\DataTable;

/**
 * Snapshot of the table's interactive state passed to the query pipeline.
 */
final readonly class TableState
{
    /** @param  array<string, mixed>  $filterValues  [property => value] */
    public function __construct(
        public string $search,
        public array $filterValues,
        public string $sortField,
        public string $sortDirection,
    ) {}
}
