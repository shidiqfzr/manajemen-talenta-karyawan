<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
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

    public function test_tenure_compliance_check(): void
    {
        $empCompliant = Employee::factory()->create([
            'unit_kerja' => 'Unit A',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        $empNonCompliant = Employee::factory()->create([
            'unit_kerja' => 'Unit A',
            'tmt_unit_kerja' => now()->subMonths(10)->toDateString(),
        ]);

        // Same unit -> not applicable (always compliant)
        $sameUnit = $this->service->checkTenureCompliance($empNonCompliant, 'Unit A');
        $this->assertFalse($sameUnit['is_applicable']);
        $this->assertTrue($sameUnit['is_compliant']);

        // Different unit, >= 2 years -> compliant
        $compliantResult = $this->service->checkTenureCompliance($empCompliant, 'Unit B');
        $this->assertTrue($compliantResult['is_applicable']);
        $this->assertTrue($compliantResult['is_compliant']);

        // Different unit, < 2 years -> non-compliant
        $nonCompliantResult = $this->service->checkTenureCompliance($empNonCompliant, 'Unit B');
        $this->assertTrue($nonCompliantResult['is_applicable']);
        $this->assertFalse($nonCompliantResult['is_compliant']);
        $this->assertEquals(10, $nonCompliantResult['months']);
    }

    public function test_promotion_eligibility_check(): void
    {
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);

        // Emp with Gol IIIC (eligible for RM-2 promotion)
        $empEligible = Employee::factory()->create([
            'jabatan' => 'Asisten Tanaman',
            'golongan' => 'IIIC',
            'tanggal_dalam_jabatan' => now()->subYears(2)->toDateString(),
        ]);

        $resultEligible = $this->service->checkPromotionEligibility($empEligible, 'Askep Tanaman');
        $this->assertTrue($resultEligible['is_applicable']);

        // Emp with Gol IIIA and only 1 year tenure (not eligible for RM-2 without dispensation)
        $empIneligible = Employee::factory()->create([
            'jabatan' => 'Asisten Tanaman',
            'golongan' => 'IIIA',
            'tanggal_dalam_jabatan' => now()->subYear()->toDateString(),
        ]);

        $resultIneligible = $this->service->checkPromotionEligibility($empIneligible, 'Askep Tanaman');
        $this->assertTrue($resultIneligible['is_applicable']);
        $this->assertFalse($resultIneligible['is_eligible']);
    }

    public function test_process_mutasi_stores_enterprise_audit_and_dispensasi(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun A',
        ]);
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik', 'rm_level' => 'RM-3']);

        $result = $this->service->processMutasi(
            employeeNik: $emp->nik,
            targetJabatan: 'Asisten Teknik',
            targetUnitKerja: 'Kebun A',
            tmtAwal: now()->toDateString(),
            jenisMutasi: 'ROTASI',
            nomorSk: 'SK/TEST/001',
            tanggalSk: now()->toDateString(),
            catatan: 'Rotasi rotasi',
            tanggalBerakhirSanksi: null,
            statusJabatan: 'Definitif',
            tmtAkhirEstimasi: null,
            fileSk: 'dokumen_sk/sk_dummy.pdf',
            createdBy: $user->id,
            isDispensasi: true,
            alasanDispensasi: 'Dispensasi Khusus Direksi'
        );

        $this->assertEquals('transfer', $result['type']);

        $job = JobHistory::where('employee_nik', $emp->nik)->where('jabatan', 'Asisten Teknik')->first();
        $this->assertNotNull($job);
        $this->assertEquals($user->id, $job->created_by);
        $this->assertEquals('dokumen_sk/sk_dummy.pdf', $job->file_sk);
        $this->assertTrue($job->is_dispensasi);
        $this->assertEquals('Dispensasi Khusus Direksi', $job->alasan_dispensasi);
    }
}
