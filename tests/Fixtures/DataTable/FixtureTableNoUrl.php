<?php

namespace Tests\Fixtures\DataTable;

/**
 * FixtureTable variant that keeps its table state out of the URL.
 */
class FixtureTableNoUrl extends FixtureTable
{
    protected function syncsUrl(): bool
    {
        return false;
    }
}
