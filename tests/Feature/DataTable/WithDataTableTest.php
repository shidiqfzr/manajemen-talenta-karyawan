<?php

namespace Tests\Feature\DataTable;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\DataTable\FixtureRow;
use Tests\Fixtures\DataTable\FixtureTable;
use Tests\Fixtures\DataTable\FixtureTableNoUrl;
use Tests\TestCase;

class WithDataTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        FixtureRow::createTable();

        for ($i = 1; $i <= 30; $i++) {
            FixtureRow::create([
                'nama' => sprintf('Pegawai %02d', $i),
                'kode' => sprintf('K%02d', $i),
                'bidang' => $i % 2 === 0 ? 'KEU' : 'TAN',
                'nilai' => $i,
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_defaults_applied_on_mount(): void
    {
        $component = Livewire::test(FixtureTable::class)
            ->assertSet('sortField', 'nama')
            ->assertSet('sortDirection', 'asc')
            ->assertSet('perPage', 25)
            ->assertOk();

        $this->assertCount(25, $component->instance()->rows->items());
        $component->assertSee('Pegawai 01')->assertSee('Pegawai 25')->assertDontSee('Pegawai 26');
    }

    public function test_tampered_url_state_falls_back_to_defaults(): void
    {
        $component = Livewire::withQueryParams([
            'sort' => 'password',
            'direction' => 'sideways',
            'per_page' => 9999,
            'bidang' => 'XYZ',
        ])->test(FixtureTable::class)
            ->assertSet('sortField', 'nama')
            ->assertSet('sortDirection', 'asc')
            ->assertSet('perPage', 25)
            ->assertSet('bidang', '')
            ->assertOk();

        // Livewire honoured the trait hook: the state properties are URL-bound under their aliases.
        $url = $component->effects['url'] ?? [];
        $this->assertSame('sort', $url['sortField']['as'] ?? null);
        $this->assertSame('direction', $url['sortDirection']['as'] ?? null);
        $this->assertSame('per_page', $url['perPage']['as'] ?? null);
        $this->assertSame('', $url['search']['except'] ?? null);
        $this->assertArrayHasKey('bidang', $url);
    }

    public function test_valid_url_state_is_applied_and_search_is_normalised(): void
    {
        Livewire::withQueryParams([
            'search' => '  Pegawai 0  ',
            'sort' => 'nilai',
            'direction' => 'desc',
            'per_page' => 10,
            'bidang' => 'KEU',
        ])->test(FixtureTable::class)
            ->assertSet('search', 'Pegawai 0')
            ->assertSet('sortField', 'nilai')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', 10)
            ->assertSet('bidang', 'KEU')
            ->assertSeeInOrder(['Pegawai 08', 'Pegawai 06', 'Pegawai 04', 'Pegawai 02']);
    }

    public function test_sort_by_ignores_non_sortable_columns(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('sortBy', 'kode')
            ->assertSet('sortField', 'nama')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'password')
            ->assertSet('sortField', 'nama');
    }

    public function test_sort_by_toggles_direction_and_resets_page(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('selected', ['1', '2'])
            ->call('sortBy', 'nama')
            ->assertSet('sortField', 'nama')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('paginators.page', 1)
            ->assertSet('selected', [])
            ->call('sortBy', 'nama')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'nama')
            ->call('sortBy', 'nilai')
            ->assertSet('sortField', 'nilai')
            ->assertSet('sortDirection', 'asc');
    }

    public function test_search_is_trimmed_capped_and_resets_page(): void
    {
        $component = Livewire::test(FixtureTable::class)
            ->call('gotoPage', 2)
            ->set('selected', ['1'])
            ->set('selectAllMatching', true)
            ->set('search', '   '.str_repeat('a', 120).'   ')
            ->assertSet('paginators.page', 1)
            ->assertSet('selected', [])
            ->assertSet('selectAllMatching', false);

        $this->assertSame(str_repeat('a', 100), $component->get('search'));

        $component->set('search', '  Pegawai 07  ')
            ->assertSet('search', 'Pegawai 07')
            ->assertSee('Pegawai 07')
            ->assertDontSee('Pegawai 08');
    }

    public function test_invalid_filter_value_is_cleared(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('gotoPage', 2)
            ->set('bidang', 'XYZ')
            ->assertSet('bidang', '')
            ->assertSet('paginators.page', 1)
            ->set('bidang', 'KEU')
            ->assertSet('bidang', 'KEU')
            ->assertSee('Pegawai 02')
            ->assertDontSee('Pegawai 01');
    }

    public function test_per_page_is_clamped(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('gotoPage', 2)
            ->set('perPage', 9999)
            ->assertSet('perPage', 25)
            ->assertSet('paginators.page', 1)
            ->set('perPage', 10)
            ->assertSet('perPage', 10)
            ->assertSee('Pegawai 10')
            ->assertDontSee('Pegawai 11');
    }

    public function test_reset_filters_clears_search_and_filters_and_returns_to_page_one(): void
    {
        $component = Livewire::test(FixtureTable::class)
            ->set('search', 'Pegawai')
            ->set('bidang', 'KEU')
            ->call('gotoPage', 2);

        $this->assertTrue($component->instance()->hasActiveFilters());

        $component->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('bidang', '')
            ->assertSet('paginators.page', 1);

        $this->assertFalse($component->instance()->hasActiveFilters());
        $this->assertSame('bidang', $component->instance()->filterProperties());
    }

    public function test_url_sync_can_be_disabled(): void
    {
        $this->assertSame([], (new FixtureTableNoUrl)->queryStringWithDataTable());
        $this->assertNotSame([], (new FixtureTable)->queryStringWithDataTable());

        $component = Livewire::withQueryParams(['search' => 'Pegawai 07', 'sort' => 'nilai', 'bidang' => 'KEU'])
            ->test(FixtureTableNoUrl::class)
            ->assertSet('search', '')
            ->assertSet('sortField', 'nama')
            // The module's own #[Url] filter keeps working alongside.
            ->assertSet('bidang', 'KEU');

        $url = $component->effects['url'] ?? [];
        $this->assertArrayNotHasKey('search', $url);
        $this->assertArrayNotHasKey('sortField', $url);
        $this->assertArrayNotHasKey('sortDirection', $url);
        $this->assertArrayNotHasKey('perPage', $url);
        $this->assertArrayHasKey('bidang', $url);
    }
}
