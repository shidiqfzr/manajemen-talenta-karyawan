<?php

namespace Tests\Feature;

use App\Models\StructureDecree;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUT: DatabaseSeeder, which `migrate --seed` and `composer setup` run. The master structure is seeded once: running it
 * again must not overwrite quotas or rewrite the applied baseline SK on a database that has changed since
 * (DOMAIN.md §3.6 and §3.7, AGENTS.md rule 8).
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_fresh_database_gets_the_accounts_the_master_structure_and_the_baseline_sk(): void
    {
        $this->seed(DatabaseSeeder::class);

        $units = count(json_decode((string) file_get_contents(database_path('data/master_formasi.json')), true));

        $this->assertSame(3, User::count());
        $this->assertSame($units, Unit::count());
        $this->assertSame(1, StructureDecree::count());
    }

    public function test_running_it_again_leaves_the_structure_and_the_applied_decree_untouched(): void
    {
        Carbon::setTestNow('2026-11-21 08:00:00');
        $this->seed(DatabaseSeeder::class);

        // A developer changes a quota locally; the baseline SK was written at 08:00.
        $seat = DB::table('position_unit')->orderBy('id')->first();
        DB::table('position_unit')->where('id', $seat->id)->update(['kuota' => 9]);
        $counts = [Unit::count(), DB::table('positions')->count(), DB::table('position_unit')->count()];
        $decree = (array) DB::table('structure_decrees')->first();

        Carbon::setTestNow('2026-11-21 09:00:00');
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(9, (int) DB::table('position_unit')->where('id', $seat->id)->value('kuota'), 'the local quota change was overwritten');
        $this->assertSame($counts, [Unit::count(), DB::table('positions')->count(), DB::table('position_unit')->count()]);
        $this->assertSame($decree, (array) DB::table('structure_decrees')->first(), 'the baseline SK record was rewritten');
    }

    public function test_the_accounts_are_still_topped_up_on_a_re_run(): void
    {
        $this->seed(DatabaseSeeder::class);
        User::where('username', 'admin2')->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('admin', User::where('username', 'admin2')->value('role'));
        $this->assertSame(3, User::count());
    }
}
