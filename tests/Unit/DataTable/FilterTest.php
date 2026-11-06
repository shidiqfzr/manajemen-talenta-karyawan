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

    public function test_date_filters_allow_only_real_calendar_dates(): void
    {
        foreach ([Filter::dateFrom('from', 'Dari Tanggal', 'created_at'), Filter::dateTo('to', 'Sampai Tanggal', 'created_at')] as $filter) {
            $this->assertTrue($filter->allows('2026-11-07'));
            $this->assertTrue($filter->allows('2024-02-29'));
            $this->assertTrue($filter->allows(''));
            $this->assertTrue($filter->allows(null));
            $this->assertFalse($filter->allows('2026-02-30'));
            $this->assertFalse($filter->allows('2026-13-01'));
            $this->assertFalse($filter->allows('31/01/2026'));
            $this->assertFalse($filter->allows('bukan-tanggal'));
            $this->assertFalse($filter->allows(['2026-11-07']));
        }
    }

    public function test_date_filters_bound_both_days_inclusively(): void
    {
        $from = Employee::query();
        Filter::dateFrom('from', 'Dari Tanggal', 'created_at')->applyTo($from, '2026-11-01');

        $this->assertStringContainsString('"created_at" >= ?', $from->toSql());
        $this->assertSame(['2026-11-01 00:00:00'], $from->getBindings());

        $to = Employee::query();
        Filter::dateTo('to', 'Sampai Tanggal', 'created_at')->applyTo($to, '2026-11-30');

        $this->assertStringContainsString('"created_at" <= ?', $to->toSql());
        $this->assertSame(['2026-11-30 23:59:59'], $to->getBindings());
    }

    public function test_date_filters_keep_custom_apply(): void
    {
        $query = Employee::query();
        Filter::dateFrom('from', 'Dari Tanggal', 'created_at')
            ->apply(fn ($q, $v) => $q->where('tmt_bekerja', '>=', $v))
            ->applyTo($query, '2026-11-01');

        $this->assertStringContainsString('"tmt_bekerja" >= ?', $query->toSql());
        $this->assertStringNotContainsString('"created_at"', $query->toSql());
        $this->assertSame(['2026-11-01'], $query->getBindings());
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
