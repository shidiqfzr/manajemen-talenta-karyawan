<?php

namespace Tests\Fixtures\DataTable;

/**
 * FixtureTable variant whose page size options are all below the default page size (25).
 */
class FixtureTableSmallPerPage extends FixtureTable
{
    protected function perPageOptions(): array
    {
        return [5, 10];
    }
}
