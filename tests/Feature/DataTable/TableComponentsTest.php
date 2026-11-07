<?php

namespace Tests\Feature\DataTable;

use App\Livewire\Tables\TrainingTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Fixtures\DataTable\FixtureRow;
use Tests\Fixtures\DataTable\FixtureTable;
use Tests\Fixtures\DataTable\FixtureTableBooleanFilter;
use Tests\Fixtures\DataTable\FixtureTableControlless;
use Tests\Fixtures\DataTable\FixtureTableDateFilter;
use Tests\Fixtures\DataTable\FixtureTableNoExport;
use Tests\Fixtures\DataTable\FixtureTableWidth;
use Tests\Fixtures\DataTable\FixtureToolbarOnly;
use Tests\TestCase;

/**
 * SUT: the shared x-admin.table.* Blade components, rendered through the FixtureTable reference view.
 */
class TableComponentsTest extends TestCase
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
                'aktif' => $i <= 20,
                'nilai' => $i,
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_header_marks_active_sort_with_aria_sort(): void
    {
        Livewire::test(FixtureTable::class)
            ->assertSeeHtmlInOrder(['aria-sort="ascending"', "wire:click=\"sortBy('nama')\""])
            ->call('sortBy', 'nama')
            ->assertSeeHtmlInOrder(['aria-sort="descending"', "wire:click=\"sortBy('nama')\""])
            ->call('sortBy', 'nilai')
            ->assertSeeHtmlInOrder(["wire:click=\"sortBy('nama')\"", 'aria-sort="ascending"', "wire:click=\"sortBy('nilai')\""]);
    }

    public function test_header_applies_column_width_classes(): void
    {
        Livewire::test(FixtureTableWidth::class)->assertSeeHtml('w-[30%]');
        Livewire::test(FixtureTable::class)->assertDontSeeHtml('w-[30%]');
    }

    public function test_filter_select_can_show_a_visible_label(): void
    {
        $html = Blade::render('<x-admin.filter-select id="unit" model="unitFilter" label="Unit Kerja" all-label="Semua Unit Kerja" :options="[\'a\' => \'Kebun A\']" :show-label="true" />');

        $this->assertStringContainsString('<label for="unit" class="mb-1 block text-xs font-medium text-surface-600">Unit Kerja</label>', $html);
        $this->assertStringNotContainsString('sr-only', $html);
        $this->assertStringContainsString('<select id="unit" wire:model.live="unitFilter"', $html);
        $this->assertStringContainsString('<option value="">Semua Unit Kerja</option>', $html);
    }

    public function test_filter_select_default_output_is_unchanged(): void
    {
        $html = Blade::render('<x-admin.filter-select id="unit" model="unitFilter" label="Unit Kerja" all-label="Semua Unit Kerja" :options="[\'a\' => \'Kebun A\']" />');

        $this->assertStringContainsString('<label for="unit" class="sr-only">Unit Kerja</label>', $html);
        $this->assertStringContainsString('class="relative flex-1 sm:flex-initial min-w-[150px]"', $html);
        $this->assertStringNotContainsString('mb-1 block', $html);
    }

    public function test_toolbar_renders_labelled_date_inputs(): void
    {
        Livewire::test(FixtureTableDateFilter::class)
            ->assertSeeHtml('type="date"')
            ->assertSeeHtml('wire:model.live="dari"')
            ->assertSeeHtml('wire:model.live="sampai"')
            ->assertSeeHtml('-filter-dari"')
            ->assertSeeHtml('-filter-sampai"')
            ->assertSeeHtmlInOrder(['Dari Tanggal</label>', 'wire:model.live="dari"', 'aria-hidden="true">–</span>', 'Sampai Tanggal</label>', 'wire:model.live="sampai"']);
    }

    public function test_toolbar_without_filters_shows_only_search(): void
    {
        Livewire::test(TrainingTable::class, ['employeeId' => '12345678'])
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->assertDontSeeHtml('aria-controls=')
            ->assertDontSeeHtml('-filter-panel')
            ->assertDontSeeHtml('data-filter-count');
    }

    public function test_filter_button_counts_active_filters(): void
    {
        Livewire::test(FixtureTableDateFilter::class)
            ->assertSeeHtml('aria-controls=')
            ->assertDontSeeHtml('data-filter-count')
            ->set('search', 'Budi')
            ->set('bidang', 'KEU')
            ->set('dari', '2026-01-01')
            ->assertSeeHtml('data-filter-count="2"')
            ->assertDontSeeHtml('data-filter-count="3"');
    }

    public function test_filter_panel_is_accessible(): void
    {
        $html = Livewire::test(FixtureTableDateFilter::class)->html();

        $this->assertMatchesRegularExpression('/<button type="button"[^>]*aria-controls="([^"]+)"/', $html);
        preg_match('/aria-controls="([^"]+)"/', $html, $panel);
        $this->assertStringContainsString('id="'.$panel[1].'"', $html);
        $this->assertStringContainsString(':aria-expanded="open"', $html);
        $this->assertStringContainsString('x-ref="filterToggle"', $html);
        // Esc closes an open panel and puts keyboard focus back on the Filter button (it must not strand focus on a hidden field).
        $this->assertStringContainsString('@keydown.escape="if (open) { open = false; $nextTick(() => $refs.filterToggle?.focus()) }"', $html);

        preg_match_all('/<label for="([^"]+)"/', $html, $labels);
        $this->assertNotEmpty($labels[1]);
        foreach (array_unique($labels[1]) as $for) {
            $this->assertStringContainsString('id="'.$for.'"', $html, "label for={$for} has no control");
        }
    }

    public function test_filter_panel_shows_visible_labels(): void
    {
        $html = Livewire::test(FixtureTableDateFilter::class)->html();

        // Employees-directory style: small bold label with an icon, visible above the field.
        foreach (['Bidang', 'Dari Tanggal', 'Sampai Tanggal'] as $label) {
            $this->assertMatchesRegularExpression(
                '/<label for="[^"]+" class="block text-\[11px\] font-semibold text-surface-700 mb-1">\s*<i class="[^"]*" aria-hidden="true"><\/i>\s*'.preg_quote($label, '/').'\s*<\/label>/',
                $html,
                "label {$label} must be visible with an icon"
            );
            $this->assertStringNotContainsString('class="sr-only">'.$label.'</label>', $html);
        }
    }

    public function test_controlless_filter_shows_as_a_chip_but_has_no_field(): void
    {
        $html = Livewire::test(FixtureTableControlless::class)->assertDontSeeHtml('aria-controls=')->set('bidang', 'KEU')->html();

        $this->assertStringContainsString('Filter Aktif:', $html);
        $this->assertStringContainsString('Bidang: KEU', $this->plainText($html));
        $this->assertStringContainsString('aria-label="Hapus filter Bidang"', $html);
        $this->assertStringContainsString('Reset Filter', $html);
        $this->assertStringNotContainsString('wire:model.live="bidang"', $html);
        $this->assertStringNotContainsString('data-filter-count', $html);
        $this->assertStringNotContainsString('Hapus Filter Tambahan', $html);
    }

    /** Visible text of the rendered component with whitespace collapsed, for asserting on summary copy. */
    private function plainText(string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    /** Text of the permanent live region (`<p role="status">`) of the rendered component, whitespace collapsed. */
    private function statusText(string $html): string
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $nodes = (new \DOMXPath($dom))->query('//p[@role="status"]');

        return $nodes->length === 1 ? trim(preg_replace('/\s+/', ' ', $nodes->item(0)->textContent)) : 'REGIONS:'.$nodes->length;
    }

    public function test_header_shows_title_count_range_and_export(): void
    {
        $html = Livewire::test(FixtureTable::class)->html();
        $text = $this->plainText($html);

        $this->assertStringContainsString('Tabel Uji', $text);
        $this->assertSame('30 Data | Menampilkan 1 – 25 dari 30', $this->statusText($html));
        $this->assertMatchesRegularExpression('/Ekspor Excel\s*<span[^>]*>30<\/span>/', $html);

        // Accessible name: the visual badge is hidden from assistive tech and a spoken phrase replaces it,
        // so the name reads "Ekspor Excel untuk 30 data" instead of "Ekspor Excel30".
        $this->assertMatchesRegularExpression('/Ekspor Excel <span[^>]*aria-hidden="true"[^>]*>30<\/span><span class="sr-only">untuk 30 data<\/span>/', $html);
    }

    public function test_header_without_rows_shows_zero_and_no_range(): void
    {
        $html = Livewire::test(FixtureToolbarOnly::class, ['total' => 0, 'export' => true])->html();

        $this->assertSame('0 Catatan', $this->statusText($html));
        $this->assertStringNotContainsString('Menampilkan', $html);
        $this->assertMatchesRegularExpression('/Ekspor Excel\s*<span[^>]*>0<\/span>/', $html);
    }

    public function test_header_without_title_or_export(): void
    {
        $html = Livewire::test(FixtureToolbarOnly::class, ['total' => 3])->html();

        $this->assertSame('3 Catatan | Menampilkan 1 – 3 dari 3', $this->statusText($html));
        $this->assertStringNotContainsString('Ekspor Excel', $html);
        $this->assertStringNotContainsString('text-sm font-bold text-surface-900', $html, 'no title element without a title');
    }

    public function test_header_announces_the_count_in_one_live_region(): void
    {
        $html = Livewire::test(FixtureTable::class)->html();

        $this->assertSame(1, substr_count($html, 'role="status"'));
        $this->assertStringContainsString('aria-live="polite" aria-atomic="true"', $html);
        $this->assertStringStartsWith('30 Data', $this->statusText($html));
    }

    public function test_header_formats_thousands_with_a_dot(): void
    {
        $html = Livewire::test(FixtureToolbarOnly::class, ['total' => 1240])->html();

        $this->assertSame('1.240 Catatan | Menampilkan 1 – 25 dari 1.240', $this->statusText($html));
    }

    public function test_toolbar_row_has_filter_lanjutan_and_reset_on_the_right(): void
    {
        $idle = Livewire::test(FixtureTable::class)->html();
        $this->assertStringContainsString('Filter Lanjutan', $this->plainText($idle));
        $this->assertStringNotContainsString('Reset Filter', $idle, 'Reset only shows when something is active');

        $active = Livewire::test(FixtureTable::class)->set('bidang', 'KEU')->html();
        $search = strpos($active, '-search"');
        $filter = strpos($active, 'Filter Lanjutan');
        $reset = strpos($active, 'Reset Filter');
        $export = strpos($active, 'Ekspor Excel');
        $this->assertTrue($search < $filter && $filter < $reset, 'order: search, Filter Lanjutan, Reset Filter');
        $this->assertGreaterThan($reset, $export, 'export lives in the table header below the toolbar, not in the toolbar row');
        $this->assertStringContainsString('wire:click="resetFilters"', $active);
    }

    public function test_filter_fields_have_icon_labels(): void
    {
        $html = Livewire::test(FixtureTable::class)->html();

        $this->assertMatchesRegularExpression(
            '/<label for="[^"]+" class="block text-\[11px\] font-semibold text-surface-700 mb-1">\s*<i class="fas fa-filter text-surface-400 text-\[10px\] mr-1" aria-hidden="true"><\/i>\s*Bidang\s*<\/label>/',
            $html
        );
    }

    public function test_filter_field_can_be_cleared_on_its_own(): void
    {
        $idle = Livewire::test(FixtureTable::class)->html();
        $this->assertStringNotContainsString('aria-label="Hapus pilihan Bidang"', $idle, 'no clear button on an empty field');

        $active = Livewire::test(FixtureTable::class)->set('bidang', 'KEU')->html();
        $this->assertMatchesRegularExpression('/<button type="button" wire:click="\$set\(\'bidang\', \'\'\)" aria-label="Hapus pilihan Bidang"/', $active);

        // DESIGN §10: the touch target is at least 32px wide (w-8) and spans the field height; the select reserves room for it.
        preg_match('/<button type="button" wire:click="\$set\(\'bidang\', \'\'\)" aria-label="Hapus pilihan Bidang" class="([^"]*)"/', $active, $button);
        $this->assertStringContainsString('w-8', $button[1]);
        $this->assertStringContainsString('inset-y-0', $button[1]);
        $this->assertStringContainsString('justify-center', $button[1]);
        preg_match('/<select id="[^"]*-filter-bidang" wire:model.live="bidang" class="([^"]*)"/', $active, $select);
        $this->assertStringContainsString('pr-14', $select[1]);
        $this->assertStringNotContainsString('pr-12', $select[1]);
    }

    public function test_active_filter_row_has_no_partial_clear_link_and_buttons_share_one_size(): void
    {
        $panel = Livewire::test(FixtureTable::class)->set('bidang', 'KEU')->html();
        $this->assertStringNotContainsString('Hapus Filter Tambahan', $panel, 'Reset Filter already clears everything');
        $this->assertStringNotContainsString('resetPanelFilters', $panel);

        // Filter Lanjutan and Reset Filter share one width and height; the search field is compact, not full-row.
        preg_match_all('/class="([^"]*)"[^>]*>\s*<i class="fas (?:fa-sliders|fa-rotate-left)/', $panel, $buttons);
        $this->assertCount(2, $buttons[1]);
        foreach ($buttons[1] as $class) {
            $this->assertStringContainsString('h-10', $class);
            $this->assertStringContainsString('sm:w-40', $class);
        }
        $this->assertStringNotContainsString('sm:flex-1', $panel);
    }

    public function test_toolbar_lists_active_filters_as_removable_chips(): void
    {
        $component = Livewire::test(FixtureTableDateFilter::class)
            ->set('search', 'Budi')
            ->set('bidang', 'KEU')
            ->set('dari', '2026-01-01')
            ->assertSee('Filter Aktif:')
            ->assertSeeHtml('aria-label="Hapus filter Pencarian"')
            ->assertSeeHtml("wire:click=\"\$set('search', '')\"")
            ->assertSeeHtml('aria-label="Hapus filter Bidang"')
            ->assertSeeHtml("wire:click=\"\$set('bidang', '')\"")
            ->assertSeeHtml('aria-label="Hapus filter Dari Tanggal"')
            ->assertSeeHtml("wire:click=\"\$set('dari', '')\"");

        // Chip copy mirrors the Employees directory: label, then the value in bold.
        $html = $component->html();
        $text = $this->plainText($html);
        $this->assertStringContainsString('Pencarian: "Budi"', $text);
        $this->assertStringContainsString('Bidang: KEU', $text);
        $this->assertStringContainsString('Dari Tanggal: 2026-01-01', $text);
        $this->assertStringNotContainsString('Sampai Tanggal: ', $text);
        $this->assertStringContainsString('<strong>Budi</strong>', $html);
    }

    public function test_boolean_filter_appears_as_a_chip_when_active(): void
    {
        Livewire::test(FixtureTableBooleanFilter::class)
            ->set('aktif', '1')
            ->assertSee('Filter Aktif:')
            ->assertSeeHtml('aria-label="Hapus filter Hanya Aktif"')
            ->assertSeeHtml("wire:click=\"\$set('aktif', '')\"");
    }

    public function test_chip_row_is_hidden_without_active_filters(): void
    {
        Livewire::test(FixtureTableDateFilter::class)
            ->assertDontSee('Filter Aktif:')
            ->assertDontSeeHtml('aria-label="Hapus filter');
    }

    public function test_chip_values_are_escaped(): void
    {
        Livewire::test(FixtureTableDateFilter::class)
            ->set('search', '<script>alert(1)</script>')
            ->assertSeeHtml('<strong>&lt;script&gt;alert(1)&lt;/script&gt;</strong>')
            ->assertDontSeeHtml('<script>alert(1)</script>');
    }

    public function test_sortable_header_uses_livewire_sort(): void
    {
        Livewire::test(FixtureTable::class)
            ->assertSeeHtml("wire:click=\"sortBy('nama')\"")
            ->assertSeeHtml("wire:click=\"sortBy('nilai')\"")
            ->assertDontSeeHtml("sortBy('kode')")
            ->assertSeeHtml('scope="col"')
            ->assertSeeHtml('font-mono tabular-nums');
    }

    public function test_search_input_is_debounced_and_capped(): void
    {
        Livewire::test(FixtureTable::class)
            ->assertSeeHtml('wire:model.live.debounce.300ms="search"')
            ->assertSeeHtml('maxlength="100"');
    }

    public function test_export_button_only_when_enabled(): void
    {
        Livewire::test(FixtureTable::class)
            ->assertSee('Ekspor Excel')
            ->assertSeeHtml('wire:click="export"');

        Livewire::test(FixtureTableNoExport::class)
            ->assertDontSee('Ekspor Excel')
            ->assertDontSeeHtml('wire:click="export"');
    }

    public function test_select_filter_renders_as_filter_select(): void
    {
        $component = Livewire::test(FixtureTable::class);
        $id = $component->id();

        $component
            ->assertSeeHtml('id="'.$id.'-filter-bidang"')
            ->assertSeeHtml('wire:model.live="bidang"')
            ->assertSee('Semua Bidang')
            ->assertDontSee('Reset Filter')
            ->set('bidang', 'KEU')
            ->assertSee('Reset Filter')
            ->assertSeeHtml('wire:click="resetFilters"');
    }

    public function test_boolean_filter_renders_as_pressable_chip(): void
    {
        Livewire::test(FixtureTableBooleanFilter::class)
            ->assertSee('Hanya Aktif')
            ->assertSeeHtml('aria-pressed="false"')
            ->assertSeeHtml("wire:click=\"\$set('aktif', '1')\"")
            ->set('aktif', '1')
            ->assertSeeHtml('aria-pressed="true"')
            ->assertSeeHtml("wire:click=\"\$set('aktif', '')\"")
            ->assertSee('Pegawai 20')
            ->assertDontSee('Pegawai 21');
    }

    public function test_bulk_bar_shows_count_and_select_all_link(): void
    {
        Livewire::test(FixtureTable::class)
            ->assertDontSee('terpilih')
            ->call('toggleSelectPage')
            ->assertSee('25 terpilih')
            ->assertSee('Pilih semua 30 data yang sesuai')
            ->assertSeeHtml('wire:click="selectAllMatchingRows"')
            ->assertSee('Batalkan pilihan')
            ->assertSeeHtml('wire:click="clearSelection"')
            ->assertSeeHtml('aria-live="polite"')
            ->call('selectAllMatchingRows')
            ->assertSee('30 terpilih')
            ->assertSee('Semua 30 data yang sesuai terpilih.')
            ->assertDontSee('Pilih semua 30 data yang sesuai');
    }

    public function test_bulk_bar_offers_select_all_only_when_the_whole_page_is_selected(): void
    {
        $key = (string) FixtureRow::where('nama', 'Pegawai 01')->value('id');

        Livewire::test(FixtureTable::class)
            ->set('selected', [$key])
            ->assertSee('1 terpilih')
            ->assertDontSee('Pilih semua 30 data yang sesuai');
    }

    public function test_bulk_actions_render_one_button_each(): void
    {
        Livewire::test(FixtureTable::class)
            ->call('toggleSelectPage')
            ->assertSeeHtml("wire:click=\"runBulkAction('nonaktifkan')\"")
            ->assertSee('Nonaktifkan')
            ->assertSee('Gagal')
            ->assertSee('Diam');
    }

    public function test_danger_bulk_action_asks_for_confirmation(): void
    {
        Livewire::test(FixtureTableBooleanFilter::class)
            ->call('toggleSelectPage')
            ->assertSeeHtml('wire:confirm="Hapus 25 data terpilih secara permanen?"');
    }

    public function test_header_checkbox_reflects_page_selection(): void
    {
        $key = (string) FixtureRow::where('nama', 'Pegawai 01')->value('id');

        Livewire::test(FixtureTable::class)
            ->assertSeeHtml('aria-label="Pilih semua di halaman ini"')
            ->assertSeeHtml('wire:click="toggleSelectPage"')
            ->assertSeeHtml('x-bind:indeterminate="false"')
            ->set('selected', [$key])
            ->assertSeeHtml('x-bind:indeterminate="true"')
            ->call('toggleSelectPage')
            ->assertSeeHtml('x-bind:indeterminate="false"');
    }

    public function test_row_checkbox_is_a_real_labelled_checkbox_and_marks_selected_row(): void
    {
        $key = (string) FixtureRow::where('nama', 'Pegawai 01')->value('id');

        Livewire::test(FixtureTable::class)
            ->assertSeeHtml('wire:model.live="selected" value="'.$key.'" aria-label="Pilih Pegawai 01"')
            ->assertDontSeeHtml('border-l-primary-600')
            ->set('selected', [$key])
            ->assertSeeHtml('border-l-primary-600');
    }

    public function test_tbody_and_skeleton_share_loading_targets(): void
    {
        $targets = 'search, sortBy, perPage, gotoPage, nextPage, previousPage, resetFilters, bidang';

        Livewire::test(FixtureTable::class)
            ->assertSeeHtml('wire:loading.delay.remove wire:target="'.$targets.'"')
            ->assertSeeHtml('wire:loading.delay.class.remove="hidden" wire:target="'.$targets.'"')
            ->assertSeeHtml('motion-safe:animate-pulse');
    }

    public function test_empty_table_without_filters_says_no_data_yet(): void
    {
        FixtureRow::query()->delete();

        Livewire::test(FixtureTable::class)
            ->assertSee('Belum ada data.')
            ->assertDontSee('Reset filter');
    }

    public function test_filtered_empty_state_offers_reset(): void
    {
        Livewire::test(FixtureTable::class)
            ->set('search', 'zzz')
            ->assertSee('Tidak ada data yang sesuai dengan pencarian atau filter.')
            ->assertSee('Reset filter')
            ->call('resetFilters')
            ->assertSee('Pegawai 01');
    }

    public function test_notice_roles(): void
    {
        Livewire::test(FixtureTable::class)
            ->assertDontSeeHtml('role="alert"')
            ->assertDontSeeHtml('<div role="status"')
            ->set('tableNotice', ['type' => 'error', 'message' => 'Tindakan gagal.'])
            ->assertSeeHtml('role="alert"')
            ->assertDontSeeHtml('<div role="status"')
            ->assertSee('Tindakan gagal.')
            ->assertSeeHtml('aria-label="Tutup pemberitahuan"')
            ->assertSeeHtml("wire:click=\"\$set('tableNotice', null)\"")
            ->set('tableNotice', ['type' => 'warning', 'message' => 'Pilih minimal satu data.'])
            ->assertSeeHtml('role="alert"')
            ->assertSeeHtml('bg-warning-50')
            ->set('tableNotice', ['type' => 'success', 'message' => '2 data berhasil diproses.'])
            ->assertSeeHtml('<div role="status"')
            ->assertDontSeeHtml('role="alert"')
            ->set('tableNotice', null)
            ->assertDontSee('2 data berhasil diproses.');
    }

    public function test_tampered_notice_is_escaped_and_falls_back_to_error(): void
    {
        Livewire::test(FixtureTable::class)
            ->set('tableNotice', ['type' => 'evil" onclick="x', 'message' => '<script>alert(1)</script>'])
            ->assertSeeHtml('role="alert"')
            ->assertSeeHtml('bg-danger-50')
            ->assertDontSeeHtml('<script>alert(1)</script>')
            ->assertDontSeeHtml('onclick="x')
            ->assertSee('<script>alert(1)</script>')
            ->set('tableNotice', ['type' => 'success', 'message' => ['not', 'a', 'string']])
            ->assertDontSeeHtml('<div role="status"')
            ->assertOk();
    }

    public function test_no_internal_document_names_rendered(): void
    {
        $component = Livewire::test(FixtureTableBooleanFilter::class)
            ->set('search', 'zzz')
            ->set('tableNotice', ['type' => 'warning', 'message' => 'Pilih minimal satu data.']);

        $html = $component->html();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }

        $html = Livewire::test(FixtureTable::class)->call('toggleSelectPage')->html();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }

    public function test_pagination_ids_and_page_buttons_follow_the_page_name(): void
    {
        $named = new LengthAwarePaginator(range(1, 10), 30, 10, 2, ['pageName' => 'riwayat_page']);

        $this->blade('<x-admin.table.pagination :paginator="$p" noun="riwayat" />', ['p' => $named])
            ->assertSee('for="per-page-riwayat_page"', false)
            ->assertSee('id="per-page-riwayat_page"', false)
            ->assertSee("gotoPage(3, 'riwayat_page')", false)
            ->assertSee("previousPage('riwayat_page')", false)
            ->assertSee("nextPage('riwayat_page')", false)
            ->assertDontSee('per-page-select', false);

        // Existing callers on the default page name keep working.
        $default = new LengthAwarePaginator(range(1, 10), 30, 10, 1);

        $this->blade('<x-admin.table.pagination :paginator="$p" />', ['p' => $default])
            ->assertSee('for="per-page-page"', false)
            ->assertSee('id="per-page-page"', false)
            ->assertSee("gotoPage(2, 'page')", false);
    }
}
