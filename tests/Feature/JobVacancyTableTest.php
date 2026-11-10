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
            ->assertSet('perPage', 10)
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
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

        // Only the three formasi without any holder (from setUp) can be placed into from here.
        $this->assertSame(3, substr_count($html, 'Mutasi Internal</span>'));
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
