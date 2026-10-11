<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\MutasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MutasiServiceTest extends TestCase
{
    use RefreshDatabase;

    private MutasiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MutasiService::class);
    }

    public function test_clean_transfer_to_vacant_position()
    {
        // Setup
        $unit = Unit::factory()->create(['nama' => 'Unit A']);
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Unit A',
        ]);

        // Act
        $result = $this->service->processMutasi(
            $emp->nik,
            'Asisten Teknik',
            'Unit A',
            now()->toDateString(),
            'ROTASI'
        );

        // Assert
        $this->assertEquals('transfer', $result['type']);
        $this->assertEquals('Asisten Teknik', $result['to_jabatan']);

        // Check Employee
        $emp->refresh();
        $this->assertEquals('Asisten Teknik', $emp->jabatan);

        // Check Job Histories
        $oldJob = JobHistory::where('employee_nik', $emp->nik)->where('jabatan', 'Asisten Tanaman')->first();
        $this->assertNotNull($oldJob->tmt_akhir);

        $newJob = JobHistory::where('employee_nik', $emp->nik)->where('jabatan', 'Asisten Teknik')->first();
        $this->assertNull($newJob->tmt_akhir);
        $this->assertEquals('ROTASI', $newJob->jenis_mutasi);
    }

    public function test_simultaneous_swap_when_target_occupied()
    {
        // Setup
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Unit A']);
        $empB = Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Unit A']);

        // Act
        $result = $this->service->processMutasi(
            $empA->nik,
            'Posisi B',
            'Unit A',
            now()->toDateString(),
            'ROTASI'
        );

        // Assert
        $this->assertEquals('swap', $result['type']);
        $this->assertEquals($empB->nama, $result['displaced']);

        $empA->refresh();
        $empB->refresh();

        $this->assertEquals('Posisi B', $empA->jabatan);
        $this->assertEquals('Posisi A', $empB->jabatan);

        // Check histories for both
        $this->assertEquals(2, JobHistory::where('tmt_akhir', null)->count());
        $this->assertTrue(JobHistory::where('employee_nik', $empA->nik)->where('jabatan', 'Posisi B')->whereNull('tmt_akhir')->exists());
        $this->assertTrue(JobHistory::where('employee_nik', $empB->nik)->where('jabatan', 'Posisi A')->whereNull('tmt_akhir')->exists());
    }

    public function test_multi_occupancy_pool_advisor_does_not_trigger_swap()
    {
        // Setup
        Position::factory()->create(['nama' => 'Advisor', 'is_overflow' => true]);

        // Someone is already an Advisor
        $empB = Employee::factory()->create(['jabatan' => 'Advisor', 'unit_kerja' => 'Unit A']);
        $empA = Employee::factory()->create(['jabatan' => 'Manajer', 'unit_kerja' => 'Unit A']);

        // Act
        $result = $this->service->processMutasi(
            $empA->nik,
            'Advisor',
            'Unit A',
            now()->toDateString(),
            'MASA_PERSIAPAN_PENSIUN'
        );

        // Assert
        $this->assertEquals('transfer', $result['type']); // Should NOT be a swap!

        $empA->refresh();
        $empB->refresh();

        $this->assertEquals('Advisor', $empA->jabatan);
        $this->assertEquals('Advisor', $empB->jabatan); // Emp B is STILL an Advisor!
    }

    public function test_same_position_guard_throws_exception()
    {
        // Setup
        $emp = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Unit A']);

        // Assert Exception
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $this->service->processMutasi(
            $emp->nik,
            'Posisi A',
            'Unit A',
            now()->toDateString(),
            'ROTASI',
        );
    }

    public function test_position_caching_and_invalidation(): void
    {
        Position::factory()->create(['nama' => 'Jabatan Alpha', 'rm_level' => 'RM-3']);

        $cached = Position::getAllCached();
        $this->assertTrue($cached->has('Jabatan Alpha'));

        // Create new position and check invalidation
        Position::factory()->create(['nama' => 'Jabatan Beta', 'rm_level' => 'RM-3']);
        $newCached = Position::getAllCached();
        $this->assertTrue($newCached->has('Jabatan Beta'));
    }
}
