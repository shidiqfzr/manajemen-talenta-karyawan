<?php

namespace Tests\Feature;

use App\Livewire\Tables\JobVacancyTable;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\JobVacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Tables\JobVacancyTable (collection-backed list on the shared table components).
 */
class JobVacancyTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // Critical (> 90 days), moderate (30-90 days) and recent (< 30 days) vacancies in two units.
        $this->makeVacancy('Jabatan Kritis Alfa', 'Unit Alfa', now()->subDays(200));
        $this->makeVacancy('Jabatan Sedang Beta', 'Unit Beta', now()->subDays(60));
        $this->makeVacancy('Jabatan Baru Alfa', 'Unit Alfa', now()->subDays(5));
    }

    /** A formasi seat (master formasi) in the given unit; the unit is created on first use. */
    private function makeSeat(string $jabatan, string $unit, int $kuota = 1): Position
    {
        $unitModel = Unit::firstOrCreate(['nama' => $unit], ['kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
        $position = Position::create(['nama' => $jabatan, 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);

        DB::table('position_unit')->insert([
            'unit_id' => $unitModel->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => 'TAN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $position;
    }

    private function makeVacancy(string $jabatan, string $unit, $vacantSince): void
    {
        $this->makeSeat($jabatan, $unit);
        $employee = Employee::factory()->create(['jabatan' => 'Jabatan Aktif '.$jabatan, 'unit_kerja' => $unit]);

        JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan' => $jabatan,
            'unit_kerja' => $unit,
            'tmt_awal' => now()->subYears(3)->toDateString(),
            'tmt_akhir' => $vacantSince->toDateString(),
        ]);
    }

    public function test_renders_shared_toolbar_and_header(): void
    {
        $html = Livewire::test(JobVacancyTable::class)->html();

        $this->assertStringContainsString('Filter Lanjutan', $html);
        $this->assertStringContainsString('Daftar Formasi Kosong', $html);
        $this->assertStringContainsString('3 Formasi', preg_replace('/\s+/', ' ', $html));
        $this->assertStringContainsString('id="per-page-page"', $html);
        $this->assertStringContainsString('Opsi Pengisian', $html);
        $this->assertStringNotContainsString('Ekspor Excel', $html);
    }

    public function test_unit_filter_and_chip(): void
    {
        $component = Livewire::test(JobVacancyTable::class)->set('selectedUnit', 'Unit Beta');

        $component->assertSee('Jabatan Sedang Beta')
            ->assertDontSee('Jabatan Kritis Alfa')
            ->assertSeeHtml('aria-label="Hapus filter Unit Kerja"')
            ->assertSeeHtml('data-filter-count="1"');
    }

    public function test_kpi_card_sets_severity_chip_only(): void
    {
        $component = Livewire::test(JobVacancyTable::class)->call('$set', 'severityFilter', 'red');

        $component->assertSee('Jabatan Kritis Alfa')
            ->assertDontSee('Jabatan Sedang Beta')
            ->assertSeeHtml('aria-label="Hapus filter Urgensi"')
            ->assertDontSeeHtml('data-filter-count');
        $this->assertStringContainsString('Kritis (&gt; 90 hari)', $component->html());
    }

    public function test_unknown_severity_is_ignored(): void
    {
        $component = Livewire::test(JobVacancyTable::class)
            ->set('severityFilter', 'purple')
            ->assertSet('severityFilter', '')
            ->set('selectedUnit', "Unit'; DROP TABLE x;--")
            ->assertSet('selectedUnit', '')
            ->assertSee('Jabatan Kritis Alfa')
            ->assertSee('Jabatan Sedang Beta');

        $component->set('search', '  '.str_repeat('a', 150).'  ')->assertSet('search', str_repeat('a', 100));
    }

    public function test_reset_clears_search_unit_and_severity(): void
    {
        Livewire::test(JobVacancyTable::class)
            ->set('search', 'Alfa')
            ->set('selectedUnit', 'Unit Alfa')
            ->set('severityFilter', 'green')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('selectedUnit', '')
            ->assertSet('severityFilter', '')
            ->assertSee('Jabatan Sedang Beta');
    }

    public function test_legacy_query_keys_are_applied(): void
    {
        Livewire::withQueryParams(['selectedUnit' => 'Unit Beta', 'severityFilter' => 'yellow'])
            ->test(JobVacancyTable::class)
            ->assertSet('selectedUnit', 'Unit Beta')
            ->assertSet('severityFilter', 'yellow')
            ->assertSee('Jabatan Sedang Beta');

        Livewire::withQueryParams(['unit' => 'Unit Alfa', 'severity' => 'red', 'search' => 'Kritis'])
            ->test(JobVacancyTable::class)
            ->assertSet('selectedUnit', 'Unit Alfa')
            ->assertSet('severityFilter', 'red')
            ->assertSet('search', 'Kritis')
            ->assertSee('Jabatan Kritis Alfa');

        Livewire::withQueryParams(['severity' => 'bogus', 'unit' => 'Nowhere'])
            ->test(JobVacancyTable::class)
            ->assertSet('severityFilter', '')
            ->assertSet('selectedUnit', '');
    }

    public function test_per_page_is_limited_to_the_offered_sizes(): void
    {
        Livewire::test(JobVacancyTable::class)
            ->set('perPage', 9999)
            ->assertSet('perPage', 25)
            ->set('perPage', 50)
            ->assertSet('perPage', 50);
    }

    public function test_formasi_that_was_never_filled_is_listed_and_filterable(): void
    {
        $this->makeSeat('Jabatan Belum Pernah Terisi', 'Unit Gamma');

        $component = Livewire::test(JobVacancyTable::class)
            ->assertSee('Jabatan Belum Pernah Terisi')
            ->assertSee('Belum pernah terisi');
        $this->assertStringContainsString('4 Formasi', preg_replace('/\s+/', ' ', $component->html()));

        $component->set('severityFilter', 'unknown')
            ->assertSet('severityFilter', 'unknown')
            ->assertSee('Jabatan Belum Pernah Terisi')
            ->assertDontSee('Jabatan Kritis Alfa')
            ->assertSeeHtml('aria-label="Hapus filter Urgensi"');
    }

    public function test_formasi_with_an_active_holder_shows_its_status_and_offers_no_mutasi_internal(): void
    {
        $acting = $this->makeSeat('Jabatan Dijabat Plt', 'Unit Gamma');
        Employee::factory()->create(['jabatan' => $acting->nama, 'unit_kerja' => 'Unit Gamma', 'status_penugasan' => 'Plt']);
        $partial = $this->makeSeat('Jabatan Dua Kursi', 'Unit Gamma', 2);
        Employee::factory()->create(['jabatan' => $partial->nama, 'unit_kerja' => 'Unit Gamma']);

        $html = Livewire::test(JobVacancyTable::class)
            ->assertSee('Jabatan Dijabat Plt')
            ->assertSee('Diisi Pj/Plt')
            ->assertSee('Sebagian kosong')
            ->html();

        // Placement is offered where Mutasi accepts it, i.e. where a seat is free: the three empty formasi from setUp
        // and the two-seat formasi with one holder. The Plt-held single seat keeps its holder, so it has no button.
        $this->assertSame(4, substr_count($html, 'Mutasi Internal</span>'));
        $this->assertSame(1, substr_count($html, 'Ada pemegang aktif'));
        $this->assertStringContainsString('Rekrut Baru', $html);
    }

    public function test_search_finds_the_last_occupant_by_name(): void
    {
        $this->makeSeat('Jabatan Cari Nama', 'Unit Gamma');
        $former = Employee::factory()->create(['nama' => 'Nama Unik Pencarian', 'jabatan' => 'Jabatan Lama', 'unit_kerja' => 'Unit Gamma']);
        JobHistory::create(['employee_nik' => $former->nik, 'jabatan' => 'Jabatan Cari Nama', 'unit_kerja' => 'Unit Gamma', 'tmt_awal' => now()->subYears(2)->toDateString(), 'tmt_akhir' => now()->subDays(10)->toDateString()]);

        Livewire::test(JobVacancyTable::class)
            ->set('search', 'Unik Pencarian')
            ->assertSee('Jabatan Cari Nama')
            ->assertDontSee('Jabatan Kritis Alfa');
    }

    public function test_a_never_filled_formasi_is_not_called_new_and_the_chip_labels_match_the_strip(): void
    {
        $this->makeSeat('Jabatan Belum Pernah Terisi', 'Unit Gamma');

        $html = Livewire::test(JobVacancyTable::class)->assertDontSee('Formasi Baru')->html();
        $this->assertStringContainsString('Belum Pernah Terisi', $html);

        $yellow = Livewire::test(JobVacancyTable::class)->set('severityFilter', 'yellow')->html();
        $this->assertStringContainsString('Perhatian (30 - 90 hari)', $yellow);
        $this->assertStringNotContainsString('Sedang (30', $yellow);
    }

    public function test_filled_formasi_and_positions_outside_the_master_are_not_listed(): void
    {
        $full = $this->makeSeat('Jabatan Sudah Penuh', 'Unit Alfa');
        Employee::factory()->create(['jabatan' => $full->nama, 'unit_kerja' => 'Unit Alfa']);

        // A departure from a position that is not a formasi seat is no vacancy.
        $former = Employee::factory()->create(['jabatan' => 'Jabatan Lain', 'unit_kerja' => 'Unit Alfa']);
        JobHistory::create(['employee_nik' => $former->nik, 'jabatan' => 'Jabatan Di Luar Master', 'unit_kerja' => 'Unit Alfa', 'tmt_awal' => now()->subYears(2)->toDateString(), 'tmt_akhir' => now()->subDays(20)->toDateString()]);

        Livewire::test(JobVacancyTable::class)
            ->assertDontSee('Jabatan Sudah Penuh')
            ->assertDontSee('Jabatan Di Luar Master')
            ->assertSee('Jabatan Kritis Alfa');
    }

    public function test_one_render_loads_the_formasi_data_once_even_with_filters(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(JobVacancyTable::class)->set('search', 'Alfa')->set('selectedUnit', 'Unit Alfa');

        $seatLoads = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'from "position_unit"') || str_contains($q['query'], 'from `position_unit`'))->count();
        DB::disableQueryLog();

        // Initial render + the two updates = 3 requests; each may load the seats once, never twice.
        $this->assertSame(3, $seatLoads);
    }

    public function test_the_monitor_uses_the_official_formasi_kosong_wording(): void
    {
        $acting = $this->makeSeat('Jabatan Dijabat Plt', 'Unit Gamma');
        Employee::factory()->create(['jabatan' => $acting->nama, 'unit_kerja' => 'Unit Gamma', 'status_penugasan' => 'Plt']);

        $html = strtolower(Livewire::test(JobVacancyTable::class)->html());
        $empty = strtolower(Livewire::test(JobVacancyTable::class)->set('search', 'tidak-ada-hasil')->html());

        $this->assertStringNotContainsString('lowong', $html);
        $this->assertStringNotContainsString('lowong', $empty);
        $this->assertStringContainsString('jabatan kosong', $html);
    }

    public function test_the_monitor_count_equals_the_formasi_kosong_count_of_sebaran_formasi(): void
    {
        $this->makeSeat('Jabatan Belum Pernah Terisi', 'Unit Gamma');

        $html = preg_replace('/\s+/', ' ', Livewire::test(JobVacancyTable::class)->html());

        $this->assertStringContainsString(app(JobVacancyService::class)->getFormasiStatistics()['vacant_formasi'].' Formasi', $html);
    }

    public function test_rows_are_compact_indonesian_and_use_the_shared_table_patterns(): void
    {
        // The app locale defaults to 'en'; the dates and ages on this screen must stay Indonesian regardless.
        app()->setLocale('en');
        $this->makeSeat('Jabatan Belum Pernah Terisi', 'Unit Gamma');

        $html = Livewire::test(JobVacancyTable::class)->html();
        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        // Age is shown once, as a labelled badge with the start date, never as English relative text.
        $this->assertStringContainsString('Kritis · 200 Hari', $text);
        $this->assertStringContainsString('Perhatian · 60 Hari', $text);
        $this->assertStringContainsString('Baru · 5 Hari', $text);
        $this->assertStringContainsString('Sejak '.now()->subDays(200)->locale('id')->translatedFormat('d M Y'), $text);
        $this->assertDoesNotMatchRegularExpression('/\b(ago|weeks?|months?|days?)\b/i', $text);
        // Same value on every row is not a column; the never-filled state is stated once per row.
        $this->assertStringNotContainsString('Formasi Operasional', $text);
        $this->assertSame(1, substr_count($text, 'Belum pernah terisi') - substr_count($html, 'class="sr-only">Belum pernah terisi'));
        // The formasi shows its RM Band (the structural anchor); Level and the last holder's golongan are not badges.
        $this->assertStringContainsString('RM-3', $text);
        $this->assertStringNotContainsString('Gol:', $text);
        $this->assertStringNotContainsString('Level:', $text);
        // One labelled "Isi Jabatan" menu per row with both ways to fill it (no icon-only kebab), and a loading skeleton that shares the rows' wire:target.
        $this->assertStringContainsString('aria-haspopup="menu"', $html);
        $this->assertStringContainsString('Isi Jabatan', $text);
        $this->assertStringContainsString('Mutasi Internal', $text);
        $this->assertStringContainsString('Rekrut Karyawan Baru', $text);
        $this->assertStringNotContainsString('fa-ellipsis-v', $html);
        $this->assertStringContainsString('aria-hidden="true" wire:loading.delay.class.remove="hidden"', $html);
        $this->assertStringContainsString('aria-busy', $html);
    }

    public function test_columns_sort_ascending_then_descending_and_empty_values_stay_last(): void
    {
        $this->makeSeat('Jabatan Belum Pernah Terisi', 'Unit Gamma');
        // Row order as rendered: the formasi names sorted by where they first appear in the table HTML.
        $order = function ($component): array {
            $html = $component->html();
            $names = ['Jabatan Baru Alfa', 'Jabatan Sedang Beta', 'Jabatan Kritis Alfa', 'Jabatan Belum Pernah Terisi'];
            usort($names, fn ($a, $b) => strpos($html, $a) <=> strpos($html, $b));

            return $names;
        };

        $component = Livewire::test(JobVacancyTable::class)->call('sortBy', 'lama_kosong');
        $component->assertSet('sortField', 'lama_kosong')->assertSet('sortDirection', 'asc')->assertSeeHtml('aria-sort="ascending"');
        // Fewest days first; the never-filled formasi has no age and stays last.
        $this->assertSame(['Jabatan Baru Alfa', 'Jabatan Sedang Beta', 'Jabatan Kritis Alfa', 'Jabatan Belum Pernah Terisi'], $order($component));

        $component->call('sortBy', 'lama_kosong')->assertSet('sortDirection', 'desc');
        $this->assertSame(['Jabatan Kritis Alfa', 'Jabatan Sedang Beta', 'Jabatan Baru Alfa', 'Jabatan Belum Pernah Terisi'], $order($component));

        $component->call('sortBy', 'jabatan')->assertSet('sortDirection', 'asc');
        $this->assertSame('Jabatan Baru Alfa', $order($component)[0]);

        // A tampered column or direction falls back to the default order.
        $component->call('sortBy', 'password')->assertSet('sortField', 'jabatan')
            ->set('sortField', 'nik; drop')->assertSet('sortField', '')
            ->set('sortDirection', 'sideways')->assertSet('sortDirection', 'asc');
    }

    public function test_partial_formasi_shows_one_status_badge_with_the_count(): void
    {
        $partial = $this->makeSeat('Jabatan Dua Kursi', 'Unit Gamma', 2);
        Employee::factory()->create(['jabatan' => $partial->nama, 'unit_kerja' => 'Unit Gamma']);

        $html = Livewire::test(JobVacancyTable::class)->html();

        $this->assertStringContainsString('Sebagian kosong · 1 dari 2', preg_replace('/\s+/', ' ', strip_tags($html)));
        $this->assertStringContainsString('title="1 dari 2 kosong"', $html);
    }

    public function test_reserved_vacancy_shows_the_reserved_employee_name(): void
    {
        $successor = Employee::factory()->create(['nama' => 'Sari Pengganti']);

        JobHistory::create([
            'employee_nik' => $successor->nik,
            'jabatan' => 'Jabatan Kritis Alfa',
            'unit_kerja' => 'Unit Alfa',
            'tmt_awal' => now()->addMonth()->toDateString(),
            'is_applied' => false,
        ]);

        Livewire::test(JobVacancyTable::class)->assertSeeHtml('Dipesan oleh Sari Pengganti');
    }
}
