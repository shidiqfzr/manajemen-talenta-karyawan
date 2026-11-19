<?php

namespace Tests\Fixtures\DataTable;

use App\Livewire\Admin\Employees\EmployeeTable;

/**
 * EmployeeTable variant with a tiny export row limit and bulk delete limit (2 rows each).
 */
class EmployeeTableSmallLimit extends EmployeeTable
{
    protected function exportLimit(): int
    {
        return 2;
    }

    protected function bulkDeleteLimit(): int
    {
        return 2;
    }
}
