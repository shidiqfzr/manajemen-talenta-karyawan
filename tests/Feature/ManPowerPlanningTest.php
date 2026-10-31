<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManPowerPlanning;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use Carbon\Carbon;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ManPowerPlanningTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    public function test_authenticated_admin_can_access_mutasi_mpp_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.mutasi.index', ['tab' => 'mpp']));

        $response->assertStatus(200);
        $response->assertSee('Perencanaan Formasi (MPP)');
        $response->assertSee('Matriks Formasi &amp; Kebutuhan Tenaga Kerja (MPP)', false);
    }

    public function test_authenticated_admin_can_access_mutasi_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.mutasi.create'));

        $response->assertStatus(200);
        $response->assertSee('Buat Mutasi &amp; Penempatan Baru', false);
        $response->assertSee('Kembali ke Hub Mutasi');
    }

    public function test_livewire_mpp_component_renders_baseline_matrix_and_kpis(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSet('sourceMode', 'baseline')
            ->assertSee('Standar Formasi Baku')
            ->assertSee('444')
            ->assertSee('344')
            ->assertSee('-14')
            ->assertSee('RM-1')
            ->assertSee('RM-2')
            ->assertSee('RM-3')
            ->assertSee('JUMLAH');
    }

    public function test_mpp_component_can_toggle_live_database_mode(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('setSourceMode', 'live')
            ->assertSet('sourceMode', 'live')
            ->assertSee('Sync Karyawan Database');
    }

    public function test_mpp_component_can_filter_by_field(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('setFilterBidang', 'KEU')
            ->assertSet('filterBidang', 'KEU');
    }

    public function test_mpp_export_csv_streams_file(): void
    {
        $component = Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('exportCsv');

        $component->assertStatus(200);
        $component->assertFileDownloaded();
    }

    public function test_mpp_service_returns_accurate_matrix_dimensions(): void
    {
        // Standar Formasi kini berasal dari kursi master formasi, sehingga master harus dimuat.
        $this->seed(PositionUnitSeeder::class);

        $service = new ManPowerPlanningService;
        $baseline = $service->getMppMatrix('baseline', 2026);

        $this->assertEquals(444, $baseline['stats']['total_formasi']);
        $this->assertEquals(344, $baseline['stats']['total_realisasi']);
        $this->assertEquals(77.5, $baseline['stats']['fill_rate']);
        $this->assertEquals(38, $baseline['stats']['total_pensiun']);
        $this->assertEquals(-14, $baseline['stats']['total_kebutuhan']);
        $this->assertCount(12, $baseline['matrix']); // 12 indicator rows
    }

    public function test_mpp_service_excludes_overflow_positions_from_realisasi(): void
    {
        // Set up live database scenario
        Position::create(['nama' => 'Manajer Regulatif', 'rm_level' => 'RM-1', 'is_overflow' => false, 'is_active' => true]);
        Position::create(['nama' => 'Advisor', 'rm_level' => 'RM-1', 'is_overflow' => true, 'is_active' => true]);

        // Employee 1: Regular position
        Employee::create([
            'nik' => '99991111',
            'nama' => 'Reguler',
            'jabatan' => 'Manajer Regulatif',
            'unit_kerja' => 'Kebun A',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tmt_bekerja' => '2010-01-01',
            'status_penugasan' => 'Definitif',
        ]);

        // Employee 2: Overflow position
        Employee::create([
            'nik' => '99992222',
            'nama' => 'Overflow',
            'jabatan' => 'Advisor',
            'unit_kerja' => 'Kantor Direksi',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tmt_bekerja' => '2010-01-01',
            'status_penugasan' => 'Definitif',
        ]);

        $service = new ManPowerPlanningService;
        $liveStats = $service->getLiveDatabaseCounts(2026);

        // Assert that the overflow employee is NOT counted in realisasi but counted in their respective overflow bins
        $this->assertEquals(1, array_sum($liveStats['realisasi']['RM-1']));
        $this->assertEquals(1, array_sum($liveStats['advisor']['RM-1']));
        $this->assertEquals(0, array_sum($liveStats['diperban']['RM-1']));
    }

    public function test_baseline_standar_formasi_is_derived_from_seats(): void
    {
        $this->seed(PositionUnitSeeder::class);

        // Menambah kuota satu kursi Manajer (RM-1/TAN) harus langsung tampak pada matriks.
        DB::table('position_unit')
            ->where('unit_id', Unit::where('nama', 'Kebun Tajati')->value('id'))
            ->where('position_id', Position::where('nama', 'Manajer')->value('id'))
            ->increment('kuota', 3);

        $this->assertSame([
            'RM-1' => ['KEU' => 2, 'TAN' => 30, 'TEK' => 11, 'UMU' => 5],
            'RM-2' => ['KEU' => 5, 'TAN' => 38, 'TEK' => 12, 'UMU' => 16],
            'RM-3' => ['KEU' => 63, 'TAN' => 146, 'TEK' => 61, 'UMU' => 58],
        ], (new ManPowerPlanningService)->getBaselineData()['standar_formasi']);
    }

    public function test_determine_bidang_prefers_the_seat(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $employee = new Employee([
            'jabatan' => 'Asisten Penggajian',
            'unit_kerja' => 'Bagian Sumber Daya Manusia dan Sistem Manajemen',
        ]);

        $this->assertSame('KEU', (new ManPowerPlanningService)->determineBidang($employee));
    }

    public function test_determine_bidang_falls_back_to_heuristic_without_a_seat(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $service = new ManPowerPlanningService;

        $pool = new Employee(['jabatan' => 'Advisor RM-1', 'unit_kerja' => 'Kebun Tajati']);
        $karpel = new Employee(['jabatan' => 'Operator Boiler', 'unit_kerja' => 'PKS Kembayan']);

        $this->assertSame('TAN', $service->determineBidang($pool));
        $this->assertSame('TEK', $service->determineBidang($karpel));
    }

    public function test_mpp_component_can_change_planning_year(): void
    {
        $yearT = Carbon::now()->year;
        $yearT1 = $yearT + 1;

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSet('planningYear', 2026)
            ->assertSee("Pensiun sd Des {$yearT}")
            ->assertSee("Kebutuhan {$yearT}")
            ->call('setPlanningYear', 2028)
            ->assertSet('planningYear', 2028)
            ->assertSee("Pensiun sd Des {$yearT}")
            ->assertSee("Pensiun sd Des {$yearT1}")
            ->assertSee("Kebutuhan {$yearT}")
            ->assertSee("Kebutuhan {$yearT1}")
            ->assertSee("BUP periode {$yearT} – {$yearT1}")
            ->assertSee("Kebutuhan Bersih {$yearT}");
    }
}
