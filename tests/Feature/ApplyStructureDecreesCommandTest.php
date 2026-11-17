<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\StructureDecree;
use App\Models\Unit;
use App\Models\User;
use App\Services\Structure\StructureDecreeService;
use App\Services\Structure\StructureReader;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApplyStructureDecreesCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 00:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);

        $unit = Unit::create(['nama' => 'Bagian Tanaman', 'kode' => 'Bagian', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => true, 'urutan' => 1]);
        $position = Position::create(['nama' => 'Kepala Bagian', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]);
        DB::table('position_unit')->insert(['unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => 1, 'bidang' => 'TAN', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function decree(string $status, string $effective, int $kuota = 4, string $number = 'DPSB/SKPTS/352/IX/2026'): StructureDecree
    {
        $target = app(StructureReader::class)->fromParsedSeats([
            ['unit' => 'Bagian Tanaman', 'jabatan' => 'Kepala Bagian', 'rm_level' => 'RM-1', 'bidang' => 'TAN', 'kuota' => $kuota],
        ]);
        $decree = StructureDecree::create([
            'sk_number' => $number,
            'title' => 'Perubahan Struktur Organisasi Region Office',
            'sk_date' => $effective,
            'effective_date' => $effective,
            'status' => $status,
            'source_path' => 'structure-decrees/0/source.xlsx',
            'source_sha256' => str_repeat('c', 64),
            'target' => $target,
            'unit_mapping' => ['mapping' => [], 'closed' => [], 'new_units' => [], 'positions' => []],
            'submitted_by' => $this->admin->id,
            'decided_by' => $this->admin->id,
        ]);
        $decree->update(['review_digest' => app(StructureDecreeService::class)->preview($decree)->digest()]);

        return $decree;
    }

    private function kuota(): int
    {
        return (int) DB::table('position_unit')->value('kuota');
    }

    public function test_it_applies_a_due_approved_decree(): void
    {
        $decree = $this->decree(StructureDecree::STATUS_APPROVED, '2026-11-16');

        $this->artisan('hr:apply-structure-decrees')
            ->expectsOutputToContain('Diterapkan: 1')
            ->assertExitCode(0);

        $this->assertSame(StructureDecree::STATUS_APPLIED, $decree->fresh()->status);
        $this->assertSame(4, $this->kuota());
    }

    public function test_it_skips_future_dated_and_unapproved_decrees(): void
    {
        $future = $this->decree(StructureDecree::STATUS_APPROVED, '2026-12-01');
        $submitted = $this->decree(StructureDecree::STATUS_SUBMITTED, '2026-11-16', 5, 'DPSB/SKPTS/353/XI/2026');

        $this->artisan('hr:apply-structure-decrees')
            ->expectsOutputToContain('Tidak ada SK struktur')
            ->assertExitCode(0);

        $this->assertSame(StructureDecree::STATUS_APPROVED, $future->fresh()->status);
        $this->assertSame(StructureDecree::STATUS_SUBMITTED, $submitted->fresh()->status);
        $this->assertSame(1, $this->kuota());
    }

    public function test_running_it_twice_changes_nothing_the_second_time(): void
    {
        $this->decree(StructureDecree::STATUS_APPROVED, '2026-11-16');

        $this->artisan('hr:apply-structure-decrees')->assertExitCode(0);
        $this->artisan('hr:apply-structure-decrees')->expectsOutputToContain('Tidak ada SK struktur')->assertExitCode(0);

        $this->assertSame(4, $this->kuota());
    }

    public function test_it_reports_a_decree_that_needs_review_and_applies_nothing(): void
    {
        $decree = $this->decree(StructureDecree::STATUS_APPROVED, '2026-11-16');
        DB::table('position_unit')->update(['kuota' => 2]);

        $this->artisan('hr:apply-structure-decrees')
            ->expectsOutputToContain('Perlu tinjau ulang: 1')
            ->assertExitCode(0);

        $this->assertSame(StructureDecree::STATUS_STALE, $decree->fresh()->status);
        $this->assertSame(2, $this->kuota());
    }

    public function test_it_is_scheduled_daily_at_midnight_before_the_effective_mutations(): void
    {
        $events = collect(app(Schedule::class)->events());
        $decrees = $events->first(fn ($e) => str_contains($e->command, 'hr:apply-structure-decrees'));
        $mutations = $events->first(fn ($e) => str_contains($e->command, 'hr:apply-effective-mutations'));

        $this->assertNotNull($decrees);
        $this->assertSame('0 0 * * *', $decrees->expression);
        $this->assertSame('1 0 * * *', $mutations->expression);
    }
}
