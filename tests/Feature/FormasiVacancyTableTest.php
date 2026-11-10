<?php

namespace Tests\Feature;

use App\Exports\FormasiExport;
use App\Livewire\Admin\Master\FormasiVacancyTable;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\Master\FormasiVacancyTable (tab "Formasi Lowong", data from JobVacancyService).
 */
class FormasiVacancyTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unit $alfa;

    private Unit $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->alfa = Unit::create(['nama' => 'Unit Alfa Lowong', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
        $this->beta = Unit::create(['nama' => 'Unit Beta Lowong', 'kode' => 'PKS', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 2]);

        $this->seat($this->alfa, 'Manajer Kosong Alfa', 1);
        $this->seat($this->beta, 'Manajer Kosong Beta', 2);
        $filled = $this->seat($this->alfa, 'Manajer Penuh Alfa', 1);
        Employee::factory()->create(['unit_kerja' => $this->alfa->nama, 'jabatan' => $filled->nama]);
        $acting = $this->seat($this->beta, 'Manajer Plt Beta', 1);
        Employee::factory()->create(['unit_kerja' => $this->beta->nama, 'jabatan' => $acting->nama, 'status_penugasan' => 'Plt']);
    }

    private function seat(Unit $unit, string $jabatan, int $kuota): Position
    {
        $position = Position::create(['nama' => $jabatan, 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);

        DB::table('position_unit')->insert([
            'unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => 'TAN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $position;
    }

    private function table()
    {
        return Livewire::actingAs($this->admin)->test(FormasiVacancyTable::class);
    }

    public function test_only_admins_can_open_the_tab(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.lowongan.index'))->assertForbidden();
        Livewire::actingAs($user)->test(FormasiVacancyTable::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.lowongan.index'))->assertOk()->assertSee('Formasi Lowong');
    }

    public function test_lists_vacant_formasi_across_units_and_hides_full_ones(): void
    {
        $this->table()
            ->assertSee('Manajer Kosong Alfa')
            ->assertSee('Manajer Kosong Beta')
            ->assertSee('Manajer Plt Beta')
            ->assertDontSee('Manajer Penuh Alfa')
            ->assertSee('Diisi Pj/Plt')
            ->assertSee('Belum pernah terisi');
    }

    public function test_kpi_cards_summarise_the_whole_formasi(): void
    {
        $html = preg_replace('/\s+/', ' ', $this->table()->html());

        $this->assertStringContainsString('Formasi Lowong', $html);
        $this->assertStringContainsString('dari 4 formasi jabatan', $html);
        $this->assertStringContainsString('dari 5 kuota formasi', $html);
    }

    public function test_unit_and_status_filters_narrow_the_list(): void
    {
        $this->table()->set('unitFilter', 'Unit Beta Lowong')
            ->assertSee('Manajer Kosong Beta')
            ->assertDontSee('Manajer Kosong Alfa');

        $this->table()->set('statusFilter', 'acting')
            ->assertSee('Manajer Plt Beta')
            ->assertDontSee('Manajer Kosong Alfa')
            ->assertSeeHtml('aria-label="Hapus filter Status Formasi"');
    }

    public function test_search_is_trimmed_and_capped(): void
    {
        $this->table()->set('search', 'Kosong Alfa')
            ->assertSee('Manajer Kosong Alfa')
            ->assertDontSee('Manajer Kosong Beta');

        $this->table()->set('search', '  '.str_repeat('a', 150).'  ')->assertSet('search', str_repeat('a', 100));
    }

    public function test_unknown_filter_values_and_page_sizes_are_discarded(): void
    {
        $this->table()
            ->set('statusFilter', 'full')
            ->assertSet('statusFilter', '')
            ->set('unitFilter', "Unit'; DROP TABLE units;--")
            ->assertSet('unitFilter', '')
            ->set('severityFilter', 'purple')
            ->assertSet('severityFilter', '')
            ->set('perPage', 9999)
            ->assertSet('perPage', 10)
            ->assertSee('Manajer Kosong Alfa');

        $this->assertDatabaseCount('units', 2);
    }

    public function test_reset_clears_every_filter(): void
    {
        $this->table()
            ->set('search', 'Alfa')
            ->set('unitFilter', 'Unit Alfa Lowong')
            ->set('statusFilter', 'empty')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('unitFilter', '')
            ->assertSet('statusFilter', '')
            ->assertSee('Manajer Kosong Beta');
    }

    public function test_empty_state_when_every_formasi_is_filled(): void
    {
        DB::table('position_unit')->update(['kuota' => 0]);

        $this->table()->assertSee('Seluruh formasi jabatan saat ini terisi pejabat definitif.');
    }

    public function test_reserved_formasi_offers_no_second_placement(): void
    {
        $successor = Employee::factory()->create(['nama' => 'Sari Pengganti', 'unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create([
            'employee_nik' => $successor->nik,
            'jabatan' => 'Manajer Kosong Alfa',
            'unit_kerja' => $this->alfa->nama,
            'tmt_awal' => now()->addMonth()->toDateString(),
            'is_applied' => false,
        ]);

        $this->table()->assertSee('Terjadwal (SK)')->assertSeeHtml('Dipesan oleh Sari Pengganti');
    }

    public function test_export_downloads_an_excel_without_employee_names(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $successor = Employee::factory()->create(['nama' => 'Sari Pengganti', 'unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create([
            'employee_nik' => $successor->nik,
            'jabatan' => 'Manajer Kosong Beta',
            'unit_kerja' => $this->beta->nama,
            'tmt_awal' => now()->addMonth()->toDateString(),
            'is_applied' => false,
        ]);

        $this->table()->set('unitFilter', 'Unit Beta Lowong')->call('exportVacancies');

        Excel::assertDownloaded('/^Formasi_Lowong_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/', function (FormasiExport $export) {
            $rows = $export->collection();

            $this->assertSame(['Manajer Kosong Beta', 'Manajer Plt Beta'], $rows->pluck(2)->sort()->values()->all());
            $this->assertSame('LOWONG', $export->headings()[7]);
            $this->assertStringNotContainsString('Sari', json_encode($rows));
            $this->assertStringContainsString($this->admin->name, $export->properties()['description']);

            return true;
        });

        $user = User::factory()->create(['role' => 'user']);
        Livewire::actingAs($user)->test(FormasiVacancyTable::class)->assertForbidden();
    }
}
