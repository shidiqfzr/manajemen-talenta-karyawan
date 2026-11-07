<?php

namespace Tests\Fixtures\DataTable;

/**
 * FixtureTableCustomExport variant with a tiny export row limit.
 */
class FixtureTableCustomExportSmallLimit extends FixtureTableCustomExport
{
    protected function exportLimit(): int
    {
        return 10;
    }
}
