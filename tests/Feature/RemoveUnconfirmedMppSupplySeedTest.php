<?php

namespace Tests\Feature;

use App\Models\MppSupplyPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\StoresMppSupplyPlan;
use Tests\TestCase;

class RemoveUnconfirmedMppSupplySeedTest extends TestCase
{
    use RefreshDatabase;
    use StoresMppSupplyPlan;

    private function runCleanup(): void
    {
        (require database_path('migrations/2026_11_06_120000_remove_unconfirmed_mpp_supply_seed.php'))->up();
    }

    public function test_a_fresh_install_has_no_supply_plan(): void
    {
        // Rencana pasokan hanya berasal dari admin melalui editor, bukan dari angka bawaan sistem.
        $this->assertSame(0, MppSupplyPlan::count());
    }

    public function test_an_unedited_seeded_plan_is_removed(): void
    {
        $this->storeMppSupplyPlan(2026);

        $this->runCleanup();

        $this->assertSame(0, MppSupplyPlan::where('year', 2026)->count());
    }

    public function test_a_plan_an_admin_has_edited_is_kept(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->storeMppSupplyPlan(2026);
        MppSupplyPlan::where(['year' => 2026, 'source' => 'rbb', 'rm_level' => 'RM-3', 'bidang' => 'KEU'])
            ->update(['jumlah' => 5, 'updated_by' => $admin->id]);
        $this->storeMppSupplyPlan(2027, updatedBy: $admin->id);

        $this->runCleanup();

        $this->assertSame(48, MppSupplyPlan::where('year', 2026)->count());
        $this->assertSame(48, MppSupplyPlan::where('year', 2027)->count());
    }
}
