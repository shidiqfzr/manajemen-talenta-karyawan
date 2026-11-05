<?php

namespace Tests\Feature;

use App\Exceptions\MppPlanRuleViolationException;
use App\Models\MasterAuditLog;
use App\Models\MppSupplyPlan;
use App\Models\User;
use App\Services\Mpp\MppSupplyPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\StoresMppSupplyPlan;
use Tests\TestCase;

class MppSupplyPlanServiceTest extends TestCase
{
    use RefreshDatabase;
    use StoresMppSupplyPlan;

    protected function setUp(): void
    {
        parent::setUp();
        // Rencana 2026 yang sudah tersimpan; 2027 dst. belum direncanakan.
        $this->storeMppSupplyPlan(2026);
    }

    private function service(): MppSupplyPlanService
    {
        return app(MppSupplyPlanService::class);
    }

    public function test_unplanned_year_returns_a_zero_filled_matrix(): void
    {
        $matrix = $this->service()->matrixFor(2030);

        $this->assertSame(array_keys(MppSupplyPlan::SOURCES), array_keys($matrix));
        $this->assertSame(['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 0], $matrix['rbb']['RM-2']);
        $this->assertFalse($this->service()->hasPlan(2030));
    }

    public function test_stored_plan_is_read_back_per_cell(): void
    {
        $m = $this->service()->matrixFor(2026);

        $this->assertTrue($this->service()->hasPlan(2026));
        $this->assertSame(48, MppSupplyPlan::where('year', 2026)->count());
        $this->assertSame(['KEU' => 2, 'TAN' => 7, 'TEK' => 2, 'UMU' => 1], $m['ckp']['RM-3']);
        $this->assertSame(['KEU' => 0, 'TAN' => 4, 'TEK' => 2, 'UMU' => 0], $m['talent_scouting']['RM-3']);
        $this->assertSame(['KEU' => 8, 'TAN' => 6, 'TEK' => 1, 'UMU' => 6], $m['rbb']['RM-3']);
        $this->assertSame(['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 1], $m['prioritas']['RM-2']);
        $this->assertSame(['KEU' => 1, 'TAN' => 4, 'TEK' => 0, 'UMU' => 8], $m['prioritas']['RM-3']);
        $this->assertSame(0, array_sum($m['ckp']['RM-1']));
    }

    private const REASON = 'Penyesuaian hasil rapat SDM';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function auditCount(): int
    {
        return MasterAuditLog::where('entity_type', 'mpp_supply')->count();
    }

    public function test_save_updates_changed_cells_and_audits_each_one(): void
    {
        $expected = $this->service()->matrixFor(2026);
        $cells = $expected;
        $cells['ckp']['RM-3']['KEU'] = 5;
        $cells['rbb']['RM-3']['UMU'] = 3;

        $changed = $this->service()->save(2026, $cells, $expected, self::REASON, $this->admin());

        $this->assertSame(2, $changed);
        $stored = $this->service()->matrixFor(2026);
        $this->assertSame(5, $stored['ckp']['RM-3']['KEU']);
        $this->assertSame(3, $stored['rbb']['RM-3']['UMU']);
        $this->assertSame(2, $this->auditCount());

        $row = MasterAuditLog::where('entity_label', 'Rencana Pasokan 2026 — CKP Internal RM-3 KEU')->firstOrFail();
        $this->assertSame('mpp_supply.updated', $row->action);
        $this->assertSame(['jumlah' => 2], $row->old_values);
        $this->assertSame(['jumlah' => 5], $row->new_values);
        $this->assertSame(self::REASON, $row->reason);
    }

    public function test_first_save_of_a_year_creates_48_rows_and_audits_as_created(): void
    {
        $expected = $this->service()->matrixFor(2027);
        $cells = $expected;
        $cells['talent_scouting']['RM-3']['TAN'] = 4;

        $this->assertSame(1, $this->service()->save(2027, $cells, $expected, self::REASON, $this->admin()));

        $this->assertSame(48, MppSupplyPlan::where('year', 2027)->count());
        $this->assertSame(1, $this->auditCount());
        $this->assertSame('mpp_supply.created', MasterAuditLog::where('entity_type', 'mpp_supply')->value('action'));
    }

    public function test_recruitment_channels_supply_only_rm3(): void
    {
        $expected = $this->service()->matrixFor(2026);
        $cells = $expected;
        $cells['rbb']['RM-1']['TEK'] = 2;

        try {
            $this->service()->save(2026, $cells, $expected, self::REASON, $this->admin());
            $this->fail('Supply outside RM-3 should be refused.');
        } catch (MppPlanRuleViolationException $e) {
            $this->assertSame('RBB hanya memasok formasi RM-3. Kosongkan RBB RM-1 TEK.', $e->getMessage());
        }

        $this->assertFalse(MppSupplyPlan::allowsCell('ckp', 'RM-2'));
        $this->assertTrue(MppSupplyPlan::allowsCell('talent_scouting', 'RM-3'));
        $this->assertTrue(MppSupplyPlan::allowsCell('prioritas', 'RM-1'));
    }

    public function test_jabatan_prioritas_may_be_planned_for_every_band(): void
    {
        $expected = $this->service()->matrixFor(2026);
        $cells = $expected;
        $cells['prioritas']['RM-1']['KEU'] = 2;

        $this->assertSame(1, $this->service()->save(2026, $cells, $expected, self::REASON, $this->admin()));
    }

    public function test_unchanged_save_writes_no_audit(): void
    {
        $grid = $this->service()->matrixFor(2026);

        $this->assertSame(0, $this->service()->save(2026, $grid, $grid, self::REASON, $this->admin()));
        $this->assertSame(0, $this->auditCount());
    }

    public function test_save_refuses_a_stale_edit(): void
    {
        $expected = $this->service()->matrixFor(2026);
        $cells = $expected;
        $cells['ckp']['RM-3']['KEU'] = 9;

        MppSupplyPlan::where(['year' => 2026, 'source' => 'ckp', 'rm_level' => 'RM-3', 'bidang' => 'KEU'])->update(['jumlah' => 4]);

        try {
            $this->service()->save(2026, $cells, $expected, self::REASON, $this->admin());
            $this->fail('Stale edit should be refused.');
        } catch (MppPlanRuleViolationException $e) {
            $this->assertSame('Rencana pasokan 2026 telah diubah oleh pengguna lain. Muat ulang lalu ulangi perubahan.', $e->getMessage());
        }

        $this->assertSame(4, $this->service()->matrixFor(2026)['ckp']['RM-3']['KEU']);
        $this->assertSame(0, $this->auditCount());
    }

    public static function invalidSaves(): array
    {
        return [
            'year below range' => [fn (array $g) => [2023, $g, self::REASON]],
            'year above range' => [fn (array $g) => [2032, $g, self::REASON]],
            'negative cell' => [fn (array $g) => [2026, array_replace_recursive($g, ['ckp' => ['RM-3' => ['KEU' => -1]]]), self::REASON]],
            'cell above max' => [fn (array $g) => [2026, array_replace_recursive($g, ['ckp' => ['RM-3' => ['KEU' => 1000]]]), self::REASON]],
            'non numeric cell' => [fn (array $g) => [2026, array_replace_recursive($g, ['ckp' => ['RM-3' => ['KEU' => 'abc']]]), self::REASON]],
            'unknown source' => [fn (array $g) => [2026, $g + ['reguler' => $g['ckp']], self::REASON]],
            'recruitment supply outside RM-3' => [fn (array $g) => [2026, array_replace_recursive($g, ['ckp' => ['RM-2' => ['KEU' => 1]]]), self::REASON]],
            'missing bidang' => [function (array $g) {
                unset($g['rbb']['RM-2']['UMU']);

                return [2026, $g, self::REASON];
            }],
            'short reason' => [fn (array $g) => [2026, array_replace_recursive($g, ['ckp' => ['RM-3' => ['KEU' => 3]]]), 'singkat']],
            'missing reason' => [fn (array $g) => [2026, array_replace_recursive($g, ['ckp' => ['RM-3' => ['KEU' => 3]]]), null]],
        ];
    }

    #[DataProvider('invalidSaves')]
    public function test_save_rejects_invalid_input(\Closure $make): void
    {
        $grid = $this->service()->matrixFor(2026);
        [$year, $cells, $reason] = $make($grid);
        $rowsBefore = MppSupplyPlan::count();

        try {
            $this->service()->save($year, $cells, $grid, $reason, $this->admin());
            $this->fail('Invalid save should be refused.');
        } catch (MppPlanRuleViolationException) {
            // expected
        }

        $this->assertSame($rowsBefore, MppSupplyPlan::count());
        $this->assertSame($grid, $this->service()->matrixFor(2026));
        $this->assertSame(0, $this->auditCount());
    }
}
