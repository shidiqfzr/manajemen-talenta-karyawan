<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManPowerPlanning;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use Livewire\Livewire;
use Tests\TestCase;

class ManPowerPlanningTest extends TestCase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::first() ?? User::factory()->create();
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
        $service = new ManPowerPlanningService;
        $baseline = $service->getMppMatrix('baseline', 2026);

        $this->assertEquals(444, $baseline['stats']['total_formasi']);
        $this->assertEquals(344, $baseline['stats']['total_realisasi']);
        $this->assertEquals(77.5, $baseline['stats']['fill_rate']);
        $this->assertEquals(38, $baseline['stats']['total_pensiun']);
        $this->assertEquals(-14, $baseline['stats']['total_kebutuhan']);
        $this->assertCount(12, $baseline['matrix']); // 12 indicator rows
    }

    public function test_mpp_component_can_change_planning_year(): void
    {
        $yearT = \Carbon\Carbon::now()->year;
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
