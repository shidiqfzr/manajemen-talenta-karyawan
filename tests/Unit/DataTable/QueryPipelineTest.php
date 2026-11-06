<?php

namespace Tests\Unit\DataTable;

use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use App\Support\DataTable\TableState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\DataTable\FixtureRow;
use Tests\TestCase;

class QueryPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FixtureRow::createTable();

        FixtureRow::create(['nama' => 'Budi', 'kode' => 'SK_01', 'bidang' => 'KEU', 'nilai' => 3]);
        FixtureRow::create(['nama' => 'Ani', 'kode' => 'SKX01', 'bidang' => 'TAN', 'nilai' => 1]);
        FixtureRow::create(['nama' => 'Citra', 'kode' => 'ZZZ', 'bidang' => 'KEU', 'nilai' => 2]);
    }

    private function columns(): array
    {
        return [
            Column::make('nama', 'Nama')->sortable()->searchable(),
            Column::make('kode', 'Kode'),
            Column::make('bidang', 'Bidang')->sortable(),
        ];
    }

    private function names(string $search = '', array $filters = [], string $sort = 'nama', string $dir = 'asc', ?array $columns = null, array $filterDefs = []): array
    {
        $state = new TableState($search, $filters, $sort, $dir);

        return QueryPipeline::apply(FixtureRow::query(), $columns ?? $this->columns(), $filterDefs, $state, ['nama', 'asc'])
            ->pluck('nama')->all();
    }

    public function test_search_matches_only_searchable_columns(): void
    {
        $this->assertSame([], $this->names('ZZZ'));
        $this->assertSame(['Citra'], $this->names('Cit'));
    }

    public function test_search_escapes_like_wildcards(): void
    {
        FixtureRow::query()->delete();
        FixtureRow::create(['nama' => 'SK_01', 'bidang' => 'KEU', 'nilai' => 1]);
        FixtureRow::create(['nama' => 'SKX01', 'bidang' => 'KEU', 'nilai' => 1]);
        FixtureRow::create(['nama' => 'Nilai 100%', 'bidang' => 'KEU', 'nilai' => 1]);
        FixtureRow::create(['nama' => 'Nilai 1000', 'bidang' => 'KEU', 'nilai' => 1]);

        $this->assertSame(['SK_01'], $this->names('SK_01'));
        $this->assertSame(['Nilai 100%'], $this->names('100%'));
        $this->assertSame('a!!b!%c!_d', QueryPipeline::escapeLike('a!b%c_d'));
    }

    public function test_search_uses_custom_closure(): void
    {
        $columns = [
            Column::make('nama', 'Nama')->sortable()
                ->searchable(fn (Builder $q, string $term) => $q->where('kode', $term)),
        ];

        $this->assertSame(['Ani'], $this->names('SKX01', columns: $columns));
    }

    public function test_non_sortable_field_falls_back_to_default_sort(): void
    {
        $this->assertSame(['Citra', 'Budi', 'Ani'], $this->names(sort: 'kode', dir: 'desc'));
    }

    public function test_invalid_direction_falls_back_to_default_direction(): void
    {
        $this->assertSame(['Ani', 'Budi', 'Citra'], $this->names(sort: 'nama', dir: 'sideways; drop table'));
    }

    public function test_primary_key_tiebreak_is_appended(): void
    {
        $ids = QueryPipeline::apply(
            FixtureRow::query(),
            $this->columns(),
            [],
            new TableState('', [], 'bidang', 'asc'),
            ['nama', 'asc'],
        )->pluck('id')->all();

        $this->assertSame([1, 3, 2], $ids);
    }

    public function test_filters_apply_only_allowed_values(): void
    {
        $defs = [Filter::select('bidang', 'Bidang', ['KEU' => 'Keuangan', 'TAN' => 'Tanaman'])];

        $this->assertSame(['Ani', 'Budi', 'Citra'], $this->names(filters: ['bidang' => 'XYZ'], filterDefs: $defs));
        $this->assertSame(['Budi', 'Citra'], $this->names(filters: ['bidang' => 'KEU'], filterDefs: $defs));
    }
}
