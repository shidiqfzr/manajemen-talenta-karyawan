<?php

namespace Tests\Fixtures\DataTable;

/**
 * FixtureTable variant with export switched off.
 */
class FixtureTableNoExport extends FixtureTable
{
    protected function exportName(): ?string
    {
        return null;
    }
}
