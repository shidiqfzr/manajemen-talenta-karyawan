<?php

namespace Tests\Feature\DataTable;

use App\Exports\DataTableExport;
use App\Livewire\Tables\EvaluationTable;
use App\Livewire\Tables\JobHistoryTable;
use App\Livewire\Tables\TrainingTable;
use App\Models\MasterAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportComputed\CannotCallComputedDirectlyException;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\Fixtures\DataTable\FixtureCustomExport;
use Tests\Fixtures\DataTable\FixtureRow;
use Tests\Fixtures\DataTable\FixtureTable;
use Tests\Fixtures\DataTable\FixtureTableCustomExport;
use Tests\Fixtures\DataTable\FixtureTableCustomExportSmallLimit;
use Tests\Fixtures\DataTable\FixtureTableNoExport;
use Tests\Fixtures\DataTable\FixtureTableNoUrl;
use Tests\Fixtures\DataTable\FixtureTableOddPerPage;
use Tests\Fixtures\DataTable\FixtureTableScoped;
use Tests\Fixtures\DataTable\FixtureTableSmallLimit;
use Tests\Fixtures\DataTable\FixtureTableSmallPerPage;
use Tests\TestCase;

use function Livewire\invade;

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

    public function test_default_per_page_outside_the_options_falls_back_to_an_allowed_size(): void
    {
        // Default 25 is not offered: the first option of at least 25 wins.
        Livewire::test(FixtureTableOddPerPage::class)
            ->assertSet('perPage', 50)
            ->set('perPage', 9999)
            ->assertSet('perPage', 50);

        // No option reaches 25: the first option wins.
        Livewire::withQueryParams(['per_page' => 7])
            ->test(FixtureTableSmallPerPage::class)
            ->assertSet('perPage', 5);

        // A default that is one of the options is kept as is (the three employee profile tables).
        $this->assertSame(25, invade(new FixtureTable)->resolvedDefaultPerPage());

        foreach ([TrainingTable::class, EvaluationTable::class, JobHistoryTable::class] as $table) {
            $this->assertSame(5, invade(new $table)->resolvedDefaultPerPage(), $table);
        }
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
        $this->assertSame([], invade(new FixtureTableNoUrl)->queryStringWithDataTable());
        $this->assertNotSame([], invade(new FixtureTable)->queryStringWithDataTable());

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

    private function defineManageFixture(): void
    {
        Gate::define('manage-fixture', fn (User $u) => $u->role === 'admin');
    }

    private function idOf(string $nama): string
    {
        return (string) FixtureRow::where('nama', $nama)->value('id');
    }

    public function test_toggle_select_page_selects_and_clears_current_page_keys(): void
    {
        $component = Livewire::test(FixtureTable::class)->set('perPage', 10)->call('toggleSelectPage');

        $this->assertCount(10, $component->get('selected'));
        $this->assertContains($this->idOf('Pegawai 01'), $component->get('selected'));
        $this->assertNotContains($this->idOf('Pegawai 11'), $component->get('selected'));

        $component->call('toggleSelectPage')->assertSet('selected', []);
    }

    public function test_toggle_select_page_keeps_selections_from_other_pages(): void
    {
        $pageOne = array_map(fn (int $i) => $this->idOf(sprintf('Pegawai %02d', $i)), range(1, 10));
        $pageTwo = array_map(fn (int $i) => $this->idOf(sprintf('Pegawai %02d', $i)), range(11, 20));

        $component = Livewire::test(FixtureTable::class)
            ->set('perPage', 10)
            ->call('toggleSelectPage')
            ->call('gotoPage', 2)
            ->call('toggleSelectPage');

        $this->assertEqualsCanonicalizing([...$pageOne, ...$pageTwo], $component->get('selected'));

        // Clearing the second page leaves the first page's ticks alone.
        $component->call('toggleSelectPage');

        $this->assertEqualsCanonicalizing($pageOne, $component->get('selected'));
        $component->assertSet('selectAllMatching', false);
    }

    public function test_selection_survives_page_change_and_clears_on_search_filter_or_sort(): void
    {
        Livewire::test(FixtureTable::class)
            ->set('selected', ['1', '2'])
            ->call('gotoPage', 2)
            ->assertSet('selected', ['1', '2'])
            ->set('search', 'Pegawai')
            ->assertSet('selected', [])
            ->set('selected', ['1'])
            ->set('selectAllMatching', true)
            ->set('bidang', 'KEU')
            ->assertSet('selected', [])
            ->assertSet('selectAllMatching', false)
            ->set('selected', ['1'])
            ->call('sortBy', 'nilai')
            ->assertSet('selected', []);
    }

    public function test_unticking_a_row_cancels_select_all_matching(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('selectAllMatchingRows')
            ->assertSet('selectAllMatching', true)
            ->set('selected', ['1'])
            ->assertSet('selectAllMatching', false)
            ->call('selectAllMatchingRows')
            ->call('toggleSelectPage')
            ->assertSet('selectAllMatching', false);
    }

    public function test_direct_sort_set_resets_page_and_clears_selection(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('gotoPage', 2)
            ->set('selected', ['1'])
            ->set('sortField', 'nilai')
            ->assertSet('paginators.page', 1)
            ->assertSet('selected', []);
    }

    public function test_unknown_bulk_action_is_forbidden(): void
    {
        Livewire::test(FixtureTable::class)->call('runBulkAction', 'hapus')->assertForbidden();
    }

    public function test_bulk_action_without_permission_shows_notice_and_changes_nothing(): void
    {
        $this->defineManageFixture();
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        Livewire::test(FixtureTable::class)
            ->set('selected', [$this->idOf('Pegawai 01')])
            ->call('runBulkAction', 'nonaktifkan')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Anda tidak memiliki akses untuk tindakan ini.']);

        $this->assertSame(30, FixtureRow::where('aktif', true)->count());
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_bulk_action_with_empty_selection_warns(): void
    {
        $this->defineManageFixture();

        Livewire::test(FixtureTable::class)
            ->call('runBulkAction', 'nonaktifkan')
            ->assertSet('tableNotice', ['type' => 'warning', 'message' => 'Pilih minimal satu data.']);

        $this->assertSame(30, FixtureRow::where('aktif', true)->count());
    }

    public function test_bulk_action_on_selected_rows_updates_only_those_and_audits(): void
    {
        $this->defineManageFixture();
        $ids = [$this->idOf('Pegawai 01'), $this->idOf('Pegawai 02')];

        Livewire::test(FixtureTable::class)
            ->set('search', 'Pegawai')
            ->set('selected', $ids)
            ->call('runBulkAction', 'nonaktifkan')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '2 data berhasil diproses.'])
            ->assertSet('selected', []);

        $this->assertSame(28, FixtureRow::where('aktif', true)->count());
        $this->assertFalse((bool) FixtureRow::find($ids[0])->aktif);

        $log = MasterAuditLog::where('action', 'data_table.bulk')->sole();
        $this->assertSame('data_table', $log->entity_type);
        $this->assertSame('Tabel Uji', $log->entity_label);
        $this->assertSame(
            ['aksi' => 'nonaktifkan', 'jumlah_baris' => 2, 'filter' => [], 'pencarian' => true],
            $log->new_values,
        );
        $this->assertStringNotContainsString('Pegawai', json_encode($log->new_values));
    }

    public function test_bulk_action_ignores_keys_outside_current_filter(): void
    {
        $this->defineManageFixture();
        $keu = $this->idOf('Pegawai 02');
        $tan = $this->idOf('Pegawai 01');

        Livewire::test(FixtureTable::class)
            ->set('bidang', 'KEU')
            ->set('selected', [$keu, $tan])
            ->call('runBulkAction', 'nonaktifkan');

        $this->assertFalse((bool) FixtureRow::find($keu)->aktif);
        $this->assertTrue((bool) FixtureRow::find($tan)->aktif);
        $this->assertSame(
            ['bidang' => 'KEU'],
            MasterAuditLog::where('action', 'data_table.bulk')->sole()->new_values['filter'],
        );
    }

    public function test_select_all_matching_targets_every_filtered_row(): void
    {
        $this->defineManageFixture();
        FixtureRow::query()->where('nilai', '>', 20)->update(['bidang' => 'KEU']);
        $this->assertSame(20, FixtureRow::where('bidang', 'KEU')->count());

        $component = Livewire::test(FixtureTable::class)
            ->set('bidang', 'KEU')
            ->call('selectAllMatchingRows');

        $this->assertSame(20, $component->instance()->selectedCount());

        $component->call('runBulkAction', 'nonaktifkan')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '20 data berhasil diproses.'])
            ->assertSet('selectAllMatching', false);

        $this->assertSame(20, FixtureRow::where('aktif', false)->count());
        $this->assertSame(0, FixtureRow::where('aktif', false)->where('bidang', '!=', 'KEU')->count());
    }

    public function test_failed_bulk_handler_rolls_back(): void
    {
        Livewire::test(FixtureTable::class)
            ->set('selected', [$this->idOf('Pegawai 01'), $this->idOf('Pegawai 02')])
            ->call('runBulkAction', 'gagal')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Tindakan gagal, tidak ada data yang berubah.']);

        $this->assertSame(30, FixtureRow::where('aktif', true)->count());
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_failed_audit_write_rolls_back_the_bulk_change(): void
    {
        $this->defineManageFixture();
        MasterAuditLog::creating(function (): never {
            throw new RuntimeException('Simulated audit failure.');
        });

        Livewire::test(FixtureTable::class)
            ->set('selected', [$this->idOf('Pegawai 01'), $this->idOf('Pegawai 02')])
            ->call('runBulkAction', 'nonaktifkan')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Tindakan gagal, tidak ada data yang berubah.']);

        // No audit entry, no change.
        $this->assertSame(30, FixtureRow::where('aktif', true)->count());
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_forged_selection_is_normalised_and_bulk_action_still_works(): void
    {
        $this->defineManageFixture();
        $id = $this->idOf('Pegawai 02');

        Livewire::test(FixtureTable::class)
            ->set('selected', [['1'], $id, null, $id, ''])
            ->assertSet('selected', [$id])
            ->call('runBulkAction', 'nonaktifkan')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '1 data berhasil diproses.']);
    }

    public function test_selection_is_capped_at_1000_entries(): void
    {
        $forged = array_map('strval', range(1, 1500));

        $component = Livewire::test(FixtureTable::class)->set('selected', $forged);

        $this->assertCount(1000, $component->get('selected'));
    }

    public function test_bulk_handler_returning_nothing_counts_zero(): void
    {
        Livewire::test(FixtureTable::class)
            ->set('selected', [$this->idOf('Pegawai 01')])
            ->call('runBulkAction', 'diam')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '0 data berhasil diproses.']);
    }

    public function test_export_downloads_the_filtered_rows(): void
    {
        Excel::fake();
        $this->travelTo('2026-11-06 10:00:00');
        FixtureRow::query()->where('nilai', '>', 20)->update(['bidang' => 'KEU']);

        Livewire::test(FixtureTable::class)->set('bidang', 'KEU')->call('export');

        Excel::assertDownloaded('tabel-uji_2026-11-06_100000.xlsx', fn (DataTableExport $e) => $e->query()->count() === 20);

        $log = MasterAuditLog::where('action', 'data_table.export')->sole();
        $this->assertSame(
            ['jumlah_baris' => 20, 'filter' => ['bidang' => 'KEU'], 'pencarian' => false],
            $log->new_values,
        );
    }

    public function test_export_uses_the_module_file_name_and_export_class(): void
    {
        Excel::fake();
        FixtureRow::query()->where('nilai', '>', 20)->update(['bidang' => 'KEU']);

        Livewire::test(FixtureTableCustomExport::class)->set('bidang', 'KEU')->call('export');

        Excel::assertDownloaded('berkas-khusus.xlsx', fn ($e) => $e instanceof FixtureCustomExport && $e->query()->count() === 20);
        $this->assertSame(1, MasterAuditLog::where('action', 'data_table.export')->count());

        // Permission and limit checks still run before the module's workbook is built.
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        Livewire::test(FixtureTableCustomExport::class)
            ->call('export')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Anda tidak memiliki akses untuk tindakan ini.']);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(FixtureTableCustomExportSmallLimit::class)
            ->call('export')
            ->assertSet('tableNotice', ['type' => 'warning', 'message' => 'Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu.']);

        $this->assertSame(1, MasterAuditLog::where('action', 'data_table.export')->count());
    }

    public function test_export_respects_selection(): void
    {
        Excel::fake();
        $this->travelTo('2026-11-06 10:00:00');
        $ids = [$this->idOf('Pegawai 01'), $this->idOf('Pegawai 02')];

        Livewire::test(FixtureTable::class)->set('selected', $ids)->call('export');

        Excel::assertDownloaded('tabel-uji_2026-11-06_100000.xlsx', fn (DataTableExport $e) => $e->query()->count() === 2);
    }

    public function test_export_selection_cannot_widen_the_filter(): void
    {
        Excel::fake();
        $this->travelTo('2026-11-06 10:00:00');
        $keu = $this->idOf('Pegawai 02');
        $tan = $this->idOf('Pegawai 01');

        Livewire::test(FixtureTable::class)->set('bidang', 'KEU')->set('selected', [$keu, $tan])->call('export');

        Excel::assertDownloaded('tabel-uji_2026-11-06_100000.xlsx', fn (DataTableExport $e) => $e->query()->count() === 1);
    }

    public function test_export_requires_permission(): void
    {
        Excel::fake();
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        Livewire::test(FixtureTable::class)
            ->call('export')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Anda tidak memiliki akses untuk tindakan ini.']);

        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.export')->count());
    }

    public function test_export_over_limit_is_refused(): void
    {
        Excel::fake();

        Livewire::test(FixtureTableSmallLimit::class)
            ->call('export')
            ->assertSet('tableNotice', ['type' => 'warning', 'message' => 'Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu.']);

        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.export')->count());
    }

    public function test_export_audit_excludes_search_term(): void
    {
        Excel::fake();
        FixtureRow::create(['nama' => 'Budi', 'kode' => 'B1', 'bidang' => 'KEU', 'nilai' => 99]);

        Livewire::test(FixtureTable::class)->set('search', 'Budi')->call('export');

        $detail = MasterAuditLog::where('action', 'data_table.export')->sole()->new_values;
        $this->assertTrue($detail['pencarian']);
        $this->assertSame(1, $detail['jumlah_baris']);
        $this->assertNotContains('Budi', $detail);
        $this->assertStringNotContainsString('Budi', json_encode($detail));
    }

    public function test_export_disabled_returns_not_found(): void
    {
        Excel::fake();

        Livewire::test(FixtureTableNoExport::class)->call('export')->assertNotFound();
    }

    public function test_unfiltered_count_ignores_search_and_filters(): void
    {
        $component = Livewire::test(FixtureTable::class)->set('bidang', 'KEU')->set('search', 'Pegawai 0');

        $this->assertSame(30, $component->instance()->unfilteredCount);
        $this->assertLessThan(30, $component->instance()->rows->total());
        $this->assertSame($component->instance()->rows->total(), $component->instance()->matchingCount);
    }

    public function test_unfiltered_count_respects_the_module_base_query(): void
    {
        $component = Livewire::test(FixtureTableScoped::class)->set('search', 'Pegawai 0');

        // 15 of the 30 rows are KEU: the scoped base query is the "total", not the whole table.
        $this->assertSame(15, $component->instance()->unfilteredCount);
        $this->assertLessThan(15, $component->instance()->rows->total());
    }

    public function test_unfiltered_count_cannot_be_called_from_the_browser(): void
    {
        $this->expectException(CannotCallComputedDirectlyException::class);

        Livewire::test(FixtureTable::class)->call('unfilteredCount');
    }

    public function test_table_loading_targets_include_actions_and_filters(): void
    {
        $table = invade(Livewire::test(FixtureTable::class)->instance());

        $this->assertSame(
            'search, sortBy, perPage, gotoPage, nextPage, previousPage, resetFilters, hapus, bidang',
            $table->tableLoadingTargets('hapus')
        );
        $this->assertSame(
            'search, sortBy, perPage, gotoPage, nextPage, previousPage, resetFilters, bidang',
            $table->tableLoadingTargets()
        );
    }

    public function test_table_page_keys_use_the_row_key(): void
    {
        $table = invade(Livewire::test(FixtureTable::class)->instance());

        $expected = FixtureRow::query()->orderBy('nama')->limit(25)->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->assertSame($expected, $table->tablePageKeys());
    }

    public function test_table_column_count_adds_the_selection_column(): void
    {
        $withBulk = invade(Livewire::test(FixtureTable::class)->instance());
        $withoutBulk = invade(Livewire::test(TrainingTable::class, ['employeeId' => '12345678'])->instance());

        $this->assertNotSame([], $withBulk->bulkActions());
        $this->assertSame([], $withoutBulk->bulkActions());

        $visible = fn ($table) => count(array_filter($table->columns(), fn ($column) => ! $column->isExportOnly()));

        $this->assertSame($visible($withBulk) + 1, $withBulk->tableColumnCount());
        $this->assertSame($visible($withoutBulk), $withoutBulk->tableColumnCount());
    }
}
