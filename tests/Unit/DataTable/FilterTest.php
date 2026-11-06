<?php

namespace Tests\Unit\DataTable;

use App\Models\Employee;
use App\Support\DataTable\Filter;
use Tests\TestCase;

class FilterTest extends TestCase
{
    public function test_select_allows_only_option_keys(): void
    {
        $filter = Filter::select('bidang', 'Bidang', ['KEU' => 'Keuangan', 'TAN' => 'Tanaman']);

        $this->assertTrue($filter->allows('KEU'));
        $this->assertFalse($filter->allows('XYZ'));
        $this->assertTrue($filter->allows(''));
        $this->assertTrue($filter->allows(null));
        $this->assertFalse($filter->allows(['KEU']));
    }

    public function test_boolean_allows_only_one(): void
    {
        $filter = Filter::boolean('aktif', 'Aktif');

        $this->assertTrue($filter->allows('1'));
        $this->assertFalse($filter->allows('0'));
        $this->assertTrue($filter->allows(''));
    }

    public function test_apply_to_defaults_to_where_equals(): void
    {
        $query = Employee::query();
        Filter::select('bidang', 'Bidang', ['KEU' => 'Keuangan'])->applyTo($query, 'KEU');

        $this->assertStringContainsString('"bidang" = ?', $query->toSql());
        $this->assertSame(['KEU'], $query->getBindings());
    }

    public function test_apply_to_uses_custom_closure(): void
    {
        $query = Employee::query();
        Filter::select('bidang', 'Bidang', ['KEU' => 'Keuangan'])
            ->apply(fn ($q, $v) => $q->where('kode', 'like', $v.'%'))
            ->applyTo($query, 'KEU');

        $this->assertStringContainsString('"kode" like ?', $query->toSql());
        $this->assertStringNotContainsString('"bidang" = ?', $query->toSql());
    }
}
