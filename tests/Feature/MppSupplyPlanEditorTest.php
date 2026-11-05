<?php

namespace Tests\Feature;

use App\Livewire\Admin\MppSupplyPlanEditor;
use App\Models\MasterAuditLog;
use App\Models\MppSupplyPlan;
use App\Models\User;
use App\Services\Mpp\MppSupplyPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesMppEmployees;
use Tests\Concerns\StoresMppSupplyPlan;
use Tests\TestCase;

class MppSupplyPlanEditorTest extends TestCase
{
    use CreatesMppEmployees;
    use RefreshDatabase;
    use StoresMppSupplyPlan;

    private const REASON = 'Penyesuaian hasil rapat SDM';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'admin']);
        $this->storeMppSupplyPlan(2026);
    }

    private function editor(int $year = 2026)
    {
        return Livewire::actingAs($this->user)
            ->test(MppSupplyPlanEditor::class)
            ->dispatch('open-mpp-supply-plan-editor', year: $year);
    }

    public function test_editor_loads_the_plan_of_the_requested_year(): void
    {
        $this->editor()
            ->assertSet('open', true)
            ->assertSet('year', 2026)
            ->assertSet('cells.ckp.RM-3.TAN', 7)
            ->assertSee('Rencana Pasokan Talenta 2026');
    }

    public function test_editor_shows_what_is_already_realised_per_cell(): void
    {
        foreach (['88880061', '88880062', '88880063'] as $nik) {
            $this->makeAsistenKeuangan($nik, ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2026-03-01']);
        }

        Livewire::actingAs($this->user)
            ->test(MppSupplyPlanEditor::class)
            ->dispatch('open-mpp-supply-plan-editor', year: 2026, month: 11)
            ->assertSet('asOfMonth', 11)
            ->assertSee('Terealisasi s.d. Nov 2026')
            ->assertSeeHtml('data-realised="ckp-RM-3-KEU">3<')
            ->assertSeeHtml('data-realised="rbb-RM-3-KEU">0<')
            // Rencana CKP KEU 2 < terealisasi 3: ditandai melebihi rencana.
            ->assertSee('CKP Internal: terealisasi melebihi rencana');
    }

    public function test_editor_without_a_month_uses_the_default_position(): void
    {
        $this->editor(2025)->assertSet('asOfMonth', 12);
    }

    public function test_an_unplanned_year_opens_empty(): void
    {
        $this->editor(2027)
            ->assertSet('open', true)
            ->assertSet('cells.ckp.RM-3.TAN', 0)
            ->assertSet('cells.rbb.RM-3.KEU', 0);
    }

    public function test_recruitment_channels_are_editable_only_for_rm3(): void
    {
        $this->editor()
            ->assertSeeHtml('wire:model="cells.ckp.RM-3.KEU"')
            ->assertDontSeeHtml('wire:model="cells.ckp.RM-1.KEU"')
            ->assertDontSeeHtml('wire:model="cells.rbb.RM-2.TAN"')
            ->assertSeeHtml('wire:model="cells.prioritas.RM-1.KEU"')
            ->assertSee('Hanya formasi RM-3');
    }

    public function test_review_summarises_changes_before_saving(): void
    {
        $this->editor()
            ->set('cells.ckp.RM-3.TAN', 9)
            ->call('review')
            ->assertSet('confirming', true)
            ->assertSee('1 sel berubah')
            ->assertSee('CKP Internal: 12 → 14');
    }

    public function test_review_without_changes_says_so(): void
    {
        $this->editor()
            ->call('review')
            ->assertSet('confirming', false)
            ->assertSee('Tidak ada perubahan pada rencana pasokan.');
    }

    public function test_save_with_reason_persists_and_dispatches_the_event(): void
    {
        $this->editor()
            ->set('cells.ckp.RM-3.TAN', 9)
            ->call('review')
            ->set('reason', self::REASON)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('mpp-supply-plan-saved')
            ->assertSet('open', false);

        $this->assertSame(9, app(MppSupplyPlanService::class)->matrixFor(2026)['ckp']['RM-3']['TAN']);
    }

    public function test_invalid_input_keeps_values_and_writes_nothing(): void
    {
        foreach (['abc', '-1', '1000', ''] as $typed) {
            $this->editor()
                ->set('cells.ckp.RM-3.KEU', $typed)
                ->set('reason', self::REASON)
                ->call('save')
                ->assertSee('CKP Internal RM-3 KEU harus berupa bilangan bulat 0 sampai 999.')
                ->assertSet('cells.ckp.RM-3.KEU', $typed)
                ->assertSet('open', true);
        }

        $this->assertSame(0, MasterAuditLog::where('entity_type', 'mpp_supply')->count());
        $this->assertSame(2, app(MppSupplyPlanService::class)->matrixFor(2026)['ckp']['RM-3']['KEU']);
    }

    public function test_stale_edit_shows_the_message(): void
    {
        $component = $this->editor()->set('cells.ckp.RM-3.KEU', 5)->set('reason', self::REASON);

        MppSupplyPlan::where(['year' => 2026, 'source' => 'ckp', 'rm_level' => 'RM-3', 'bidang' => 'KEU'])->update(['jumlah' => 4]);

        $component->call('save')
            ->assertSee('Rencana pasokan 2026 telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan.')
            ->assertNotDispatched('mpp-supply-plan-saved');
    }

    public function test_save_that_normalises_to_no_change_reports_it_and_dispatches_nothing(): void
    {
        $this->editor()
            ->set('cells.ckp.RM-3.TAN', '07')
            ->set('reason', self::REASON)
            ->call('save')
            ->assertSee('Tidak ada perubahan pada rencana pasokan.')
            ->assertNotDispatched('mpp-supply-plan-saved')
            ->assertSet('open', true);
    }

    public function test_non_admin_cannot_open_or_save_the_editor(): void
    {
        $staff = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($staff)
            ->test(MppSupplyPlanEditor::class)
            ->dispatch('open-mpp-supply-plan-editor', year: 2026)
            ->assertForbidden();

        Livewire::actingAs($staff)
            ->test(MppSupplyPlanEditor::class)
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, MasterAuditLog::where('entity_type', 'mpp_supply')->count());
    }

    public function test_editor_ignores_out_of_range_years(): void
    {
        $this->editor(1999)->assertSet('open', false);
    }
}
