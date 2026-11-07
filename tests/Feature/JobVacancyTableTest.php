<?php

namespace Tests\Feature;

use App\Livewire\Tables\JobVacancyTable;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function makeVacancy(string $jabatan, string $unit, $vacantSince): void
    {
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
        $this->assertStringContainsString('Daftar Jabatan Lowong', $html);
        $this->assertStringContainsString('3 Lowongan', preg_replace('/\s+/', ' ', $html));
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
}
