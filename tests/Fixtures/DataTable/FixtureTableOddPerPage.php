<?php

namespace Tests\Fixtures\DataTable;

/**
 * FixtureTable variant whose default page size (25) is not one of its page size options.
 */
class FixtureTableOddPerPage extends FixtureTable
{
    protected function perPageOptions(): array
    {
        return [10, 50];
    }
}
