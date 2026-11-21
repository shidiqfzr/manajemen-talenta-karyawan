<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Services\EvaluationService;
use App\Services\KepegawaianPolicy;
use Carbon\Carbon;
use Database\Seeders\DemoEmployeeSeeder;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * SUT: DemoEmployeeSeeder. Synthetic employees for local development only (SECURITY.md §3): never production, never on
 * a database that already holds employees, and every derived value comes from the domain code, not from the seeder.
 */
class DemoEmployeeSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-21');
        $this->seed(PositionUnitSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array{employees: int, histories: int, evaluations: int, audits: int}
     */
    private function counts(): array
    {
        return [
            'employees' => Employee::count(),
            'histories' => JobHistory::count(),
            'evaluations' => Evaluation::count(),
            'audits' => MasterAuditLog::count(),
        ];
    }

    public function test_seeds_sixty_recognisable_synthetic_employees(): void
    {
        (new DemoEmployeeSeeder)->run();

        $this->assertSame(DemoEmployeeSeeder::DEMO_COUNT, Employee::count());

        foreach (Employee::all() as $employee) {
            $this->assertMatchesRegularExpression('/^99\d{6}$/', (string) $employee->nik);
            $this->assertStringStartsWith('Karyawan Contoh ', $employee->nama);
            // The same golongan rule the Excel import enforces (EmployeeImportRowValidator).
            $this->assertMatchesRegularExpression('/^(I|II|III|IV)[A-D](\/[0-9]{2})?$/i', (string) $employee->golongan);
        }
    }

    public function test_pool_positions_and_zero_quota_seats_are_never_filled(): void
    {
        // Give every unit one Advisor-style and one Diperbantukan-style pool position WITH a quota. They sort first, so an
        // unfiltered seeder would pick them before any real seat.
        $pool = [
            Position::create(['nama' => 'AAA Advisor Uji', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true, 'is_overflow' => true, 'is_diperban' => false]),
            Position::create(['nama' => 'AAB Diperbantukan Uji', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true, 'is_overflow' => false, 'is_diperban' => true]),
        ];
        foreach (Unit::active()->get() as $each) {
            foreach ($pool as $position) {
                $each->positions()->attach($position->id, ['kuota' => 3, 'bidang' => 'UMU']);
            }
        }

        // Close every seat of the first unit that would otherwise receive a Karpim demo holder.
        $unit = Unit::active()->orderBy('urutan')->orderBy('nama')->get()->first(
            fn (Unit $candidate): bool => $candidate->positions()
                ->where('positions.level', 'Karpim')->where('positions.is_active', true)
                ->where('positions.is_overflow', false)->where('positions.is_diperban', false)
                ->wherePivot('kuota', '>=', 1)->exists()
        );
        $this->assertNotNull($unit, 'precondition: a unit with an eligible Karpim seat exists');
        DB::table('position_unit')->where('unit_id', $unit->id)->update(['kuota' => 0]);

        (new DemoEmployeeSeeder)->run();

        $this->assertSame(DemoEmployeeSeeder::DEMO_COUNT, Employee::count());
        $this->assertSame(0, Employee::where('unit_kerja', $unit->nama)->count());

        $poolNames = Position::where(fn ($query) => $query->where('is_overflow', true)->orWhere('is_diperban', true))->pluck('nama');
        $this->assertContains('AAA Advisor Uji', $poolNames);
        $this->assertSame(0, Employee::whereIn('jabatan', $poolNames)->count());

        $holders = Employee::query()->selectRaw('unit_kerja, jabatan, count(*) as total')->groupBy('unit_kerja', 'jabatan')->get();
        foreach ($holders as $row) {
            $quota = (int) DB::table('position_unit')
                ->join('units', 'units.id', '=', 'position_unit.unit_id')
                ->join('positions', 'positions.id', '=', 'position_unit.position_id')
                ->where('units.nama', $row->unit_kerja)->where('positions.nama', $row->jabatan)
                ->value('position_unit.kuota');
            $this->assertLessThanOrEqual($quota, (int) $row->total, "{$row->jabatan} at {$row->unit_kerja} exceeds its quota");
        }
    }

    public function test_every_demo_employee_gets_observer_derived_fields(): void
    {
        (new DemoEmployeeSeeder)->run();

        foreach (Employee::with('jobHistories')->get() as $employee) {
            $this->assertNotEmpty($employee->rm_level);
            $this->assertSame(
                KepegawaianPolicy::tanggalPensiun($employee->tanggal_lahir->toDateString()),
                $employee->tanggal_pensiun->toDateString(),
            );
            $this->assertSame(
                KepegawaianPolicy::tanggalMbt($employee->tanggal_pensiun->toDateString()),
                $employee->tanggal_mbt->toDateString(),
            );

            $masterRm = Position::where('nama', $employee->jabatan)->value('rm_level');
            if (filled($masterRm)) {
                $this->assertSame($masterRm, $employee->rm_level);
            }

            $active = $employee->jobHistories->whereNull('tmt_akhir');
            $this->assertCount(1, $active);
            $this->assertSame(JobHistory::SUMBER_DATA_AWAL, $active->first()->sumber);
            $this->assertNull($active->first()->nomor_sk);
        }
    }

    public function test_rm_level_is_still_derived_when_the_master_position_has_no_band(): void
    {
        Position::query()->update(['rm_level' => null]);
        Cache::flush();

        (new DemoEmployeeSeeder)->run();

        // Without a master band the domain code falls back to golongan and job title, so any RM-1 to RM-7 is a valid
        // answer for a Karpim position (a title can map to RM-4). Someone on a Karpel position must still land in a
        // Karpel band (Position::KARPEL_RM_LEVELS), whatever golongan the demo data carries.
        $karpelPositions = Position::where('level', 'Karpel')->pluck('nama');

        foreach (Employee::all() as $employee) {
            $this->assertMatchesRegularExpression('/^RM-[1-7]$/', (string) $employee->rm_level);

            if ($karpelPositions->contains($employee->jabatan)) {
                $this->assertContains($employee->rm_level, Position::KARPEL_RM_LEVELS, "{$employee->nama} sits on a Karpel position but got {$employee->rm_level}");
            }
        }
    }

    public function test_retirement_screens_have_data(): void
    {
        (new DemoEmployeeSeeder)->run();

        $this->assertGreaterThanOrEqual(5, Employee::menjelangMbt()->count());
        $this->assertGreaterThanOrEqual(3, Employee::mbt()->count());

        foreach (Employee::all() as $employee) {
            $this->assertTrue($employee->tanggal_pensiun->greaterThan(today()), "{$employee->nama} is already past retirement");
        }
    }

    public function test_both_strata_and_acting_assignments_exist(): void
    {
        (new DemoEmployeeSeeder)->run();

        $this->assertGreaterThan(0, Employee::where('level', 'Karpim')->count());
        $this->assertGreaterThan(0, Employee::where('level', 'Karpel')->count());
        $this->assertSame(DemoEmployeeSeeder::ACTING_COUNT, Employee::where('status_penugasan', Employee::STATUS_PJS)->count());
    }

    public function test_every_demo_status_is_one_the_application_accepts(): void
    {
        (new DemoEmployeeSeeder)->run();

        // The admin form and the Excel import validate status_penugasan against this list; a demo employee outside it
        // could not be edited without the status silently changing.
        foreach (Employee::query()->distinct()->pluck('status_penugasan') as $status) {
            $this->assertContains($status, Employee::ASSIGNMENT_STATUSES);
        }
    }

    public function test_karpim_holders_spread_across_the_three_rm_bands(): void
    {
        (new DemoEmployeeSeeder)->run();

        $bands = Employee::where('level', 'Karpim')->selectRaw('rm_level, count(*) as total')->groupBy('rm_level')->pluck('total', 'rm_level');

        foreach (DemoEmployeeSeeder::KARPIM_BAND_TARGETS as $band => $target) {
            $this->assertGreaterThanOrEqual($target, (int) ($bands[$band] ?? 0), "{$band} has too few demo holders");
        }

        $this->assertGreaterThanOrEqual(20, (int) ($bands['RM-3'] ?? 0));
        $this->assertSame(DemoEmployeeSeeder::KARPIM_COUNT, (int) $bands->sum());
    }

    public function test_every_nine_box_cell_has_someone(): void
    {
        (new DemoEmployeeSeeder)->run();

        $this->assertSame(DemoEmployeeSeeder::EVALUATED_COUNT, Evaluation::count());

        foreach (array_keys(Evaluation::BOX_LABELS) as $number) {
            $this->assertGreaterThanOrEqual(
                4,
                Evaluation::where('kategori_9box', Evaluation::boxValue($number))->count(),
                'Box '.$number.' has too few demo evaluations',
            );
        }
    }

    public function test_refuses_in_production_and_writes_nothing(): void
    {
        $this->app['env'] = 'production';
        $before = $this->counts();

        try {
            (new DemoEmployeeSeeder)->run();
            $this->fail('The seeder must refuse to run in production.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('production', $exception->getMessage());
        }

        $this->assertSame($before, $this->counts());
    }

    public function test_refuses_when_employees_already_exist(): void
    {
        Employee::factory()->create();
        $before = $this->counts();

        try {
            (new DemoEmployeeSeeder)->run();
            $this->fail('The seeder must refuse when the employees table is not empty.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not empty', $exception->getMessage());
        }

        $this->assertSame($before, $this->counts());
    }

    public function test_refuses_without_master_data(): void
    {
        Unit::query()->update(['is_active' => false]);
        $before = $this->counts();

        try {
            (new DemoEmployeeSeeder)->run();
            $this->fail('The seeder must refuse when there is no master data to attach employees to.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('composer setup', $exception->getMessage());
        }

        $this->assertSame($before, $this->counts());
    }

    public function test_same_data_on_every_run(): void
    {
        $snapshot = function (): array {
            DB::beginTransaction();
            (new DemoEmployeeSeeder)->run();
            $data = Employee::orderBy('nik')->get()->mapWithKeys(fn (Employee $employee): array => [
                (string) $employee->nik => [$employee->jabatan, $employee->unit_kerja, $employee->tanggal_lahir->toDateString()],
            ])->all();
            DB::rollBack();

            return $data;
        };

        $first = $snapshot();
        $second = $snapshot();

        $this->assertCount(DemoEmployeeSeeder::DEMO_COUNT, $first);
        $this->assertSame($first, $second);
    }

    public function test_failure_mid_run_leaves_nothing_behind(): void
    {
        $this->app->singleton(EvaluationService::class, fn () => new class extends EvaluationService
        {
            private int $calls = 0;

            public function processStore(array $data): Evaluation
            {
                if (++$this->calls === 10) {
                    throw new RuntimeException('simulated failure on the tenth evaluation');
                }

                return parent::processStore($data);
            }
        });
        $before = $this->counts();

        try {
            (new DemoEmployeeSeeder)->run();
            $this->fail('The simulated failure should have stopped the run.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('simulated failure', $exception->getMessage());
        }

        $this->assertSame($before, $this->counts());
    }
}
