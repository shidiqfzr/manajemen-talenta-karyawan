<?php

namespace Tests\Feature\DataTable;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\Fixtures\DataTable\FixtureRow;
use Tests\Fixtures\DataTable\FixtureTable;
use Tests\Fixtures\DataTable\FixtureTableBooleanFilter;
use Tests\Fixtures\DataTable\FixtureTableDateFilter;
use Tests\Fixtures\DataTable\FixtureTableNoExport;
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

    public function test_toolbar_renders_labelled_date_inputs(): void
    {
        Livewire::test(FixtureTableDateFilter::class)
            ->assertSeeHtml('type="date"')
            ->assertSeeHtml('wire:model.live="dari"')
            ->assertSeeHtml('wire:model.live="sampai"')
            ->assertSeeHtml('-filter-dari"')
            ->assertSeeHtml('-filter-sampai"')
            ->assertSeeHtmlInOrder(['sr-only', 'Dari Tanggal', 'wire:model.live="dari"', 'aria-hidden="true">–</span>', 'sr-only', 'Sampai Tanggal', 'wire:model.live="sampai"']);
    }

    public function test_toolbar_lists_active_filters_as_removable_chips(): void
    {
        Livewire::test(FixtureTableDateFilter::class)
            ->set('search', 'Budi')
            ->set('bidang', 'KEU')
            ->set('dari', '2026-01-01')
            ->assertSee('Filter Aktif:')
            ->assertSee('Pencarian: Budi')
            ->assertSeeHtml('aria-label="Hapus filter Pencarian"')
            ->assertSeeHtml("wire:click=\"\$set('search', '')\"")
            ->assertSee('Bidang: KEU')
            ->assertSeeHtml('aria-label="Hapus filter Bidang"')
            ->assertSeeHtml("wire:click=\"\$set('bidang', '')\"")
            ->assertSee('Dari Tanggal: 2026-01-01')
            ->assertSeeHtml('aria-label="Hapus filter Dari Tanggal"')
            ->assertSeeHtml("wire:click=\"\$set('dari', '')\"")
            ->assertDontSee('Sampai Tanggal: ');
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
            ->assertSeeHtml('Pencarian: &lt;script&gt;alert(1)&lt;/script&gt;')
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
            ->assertDontSee('Reset filter')
            ->set('bidang', 'KEU')
            ->assertSee('Reset filter')
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
            ->assertDontSeeHtml('role="status"')
            ->set('tableNotice', ['type' => 'error', 'message' => 'Tindakan gagal.'])
            ->assertSeeHtml('role="alert"')
            ->assertDontSeeHtml('role="status"')
            ->assertSee('Tindakan gagal.')
            ->assertSeeHtml('aria-label="Tutup pemberitahuan"')
            ->assertSeeHtml("wire:click=\"\$set('tableNotice', null)\"")
            ->set('tableNotice', ['type' => 'warning', 'message' => 'Pilih minimal satu data.'])
            ->assertSeeHtml('role="alert"')
            ->assertSeeHtml('bg-warning-50')
            ->set('tableNotice', ['type' => 'success', 'message' => '2 data berhasil diproses.'])
            ->assertSeeHtml('role="status"')
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
            ->assertDontSeeHtml('role="status"')
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
