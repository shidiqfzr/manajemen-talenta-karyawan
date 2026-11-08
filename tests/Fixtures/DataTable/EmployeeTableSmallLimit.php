<?php

namespace Tests\Fixtures\DataTable;

use App\Livewire\Admin\Employees\EmployeeTable;

/**
 * EmployeeTable variant with a tiny export row limit.
 */
class EmployeeTableSmallLimit extends EmployeeTable
{
    protected function exportLimit(): int
    {
        return 2;
    }
}
