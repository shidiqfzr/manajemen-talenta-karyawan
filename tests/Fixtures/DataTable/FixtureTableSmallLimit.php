<?php

namespace Tests\Fixtures\DataTable;

/**
 * FixtureTable variant with a tiny export row limit.
 */
class FixtureTableSmallLimit extends FixtureTable
{
    protected function exportLimit(): int
    {
        return 10;
    }
}
