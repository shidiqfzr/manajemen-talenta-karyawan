<?php

namespace Tests\Feature;

use App\Exceptions\MutasiIntegrityException;
use App\Exceptions\MutasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Data\MutasiRequest;
use App\Services\MutasiService;
use Carbon\Carbon;
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
        $result = $this->service->processMutasi(new MutasiRequest(
            $emp->nik,
            'Asisten Teknik',
            'Unit A',
            now()->toDateString(),
            'ROTASI'
        ));

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
        $result = $this->service->processMutasi(new MutasiRequest(
            $empA->nik,
            'Posisi B',
            'Unit A',
            now()->toDateString(),
            'ROTASI'
        ));

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
        $result = $this->service->processMutasi(new MutasiRequest(
            $empA->nik,
            'Advisor',
            'Unit A',
            now()->toDateString(),
            'PENUGASAN_KHUSUS'
        ));

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
        $this->service->processMutasi(new MutasiRequest(
            $emp->nik,
            'Posisi A',
            'Unit A',
            now()->toDateString(),
            'ROTASI',
        ));
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

        // Emp RM-2 targeting RM-1: Gol IIID and tenure >= 3 years is eligible without requiring holding assessment
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Manajer Kebun', 'rm_level' => 'RM-1']);

        $empRm2Eligible = Employee::factory()->create([
            'jabatan' => 'Askep Tanaman',
            'rm_level' => 'RM-2',
            'golongan' => 'IIID/08',
            'tanggal_dalam_jabatan' => now()->subYears(3)->subMonths(2)->toDateString(),
        ]);

        $resultRm1 = $this->service->checkPromotionEligibility($empRm2Eligible, 'Manajer Kebun');
        $this->assertTrue($resultRm1['is_applicable']);
        $this->assertTrue($resultRm1['is_eligible']);
        $this->assertCount(2, $resultRm1['criteria']); // Exactly 2 criteria (Golongan and Tenure)
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

        $result = $this->service->processMutasi(new MutasiRequest(
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
        ));

        $this->assertEquals('transfer', $result['type']);

        $job = JobHistory::where('employee_nik', $emp->nik)->where('jabatan', 'Asisten Teknik')->first();
        $this->assertNotNull($job);
        $this->assertEquals($user->id, $job->created_by);
        $this->assertEquals('dokumen_sk/sk_dummy.pdf', $job->file_sk);
        $this->assertTrue($job->is_dispensasi);
        $this->assertEquals('Dispensasi Khusus Direksi', $job->alasan_dispensasi);
    }

    public function test_validate_chain_topology_detects_closed_cyclic_chain_of_4_employees(): void
    {
        $u1 = Unit::factory()->create(['nama' => 'Kebun Inti Gunung Meliau']);
        $u2 = Unit::factory()->create(['nama' => 'PKS Gunung Meliau']);
        $u3 = Unit::factory()->create(['nama' => 'Kebun Rimba Belian']);
        $u4 = Unit::factory()->create(['nama' => 'PKS Rimba Belian']);

        Position::factory()->create(['nama' => 'Asisten Tanaman Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Tanaman Belian', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik Belian', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Tanaman Meliau', 'unit_kerja' => 'Kebun Inti Gunung Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Teknik Meliau', 'unit_kerja' => 'PKS Gunung Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empC = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Tanaman Belian', 'unit_kerja' => 'Kebun Rimba Belian', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empD = Employee::factory()->create(['nama' => 'KARYAWAN D', 'jabatan' => 'Asisten Teknik Belian', 'unit_kerja' => 'PKS Rimba Belian', 'tmt_unit_kerja' => now()->subYears(3)]);

        $nodes = [
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Asisten Teknik Meliau', 'target_unit_kerja' => 'PKS Gunung Meliau', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empB->nik, 'target_jabatan' => 'Asisten Tanaman Belian', 'target_unit_kerja' => 'Kebun Rimba Belian', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empC->nik, 'target_jabatan' => 'Asisten Teknik Belian', 'target_unit_kerja' => 'PKS Rimba Belian', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empD->nik, 'target_jabatan' => 'Asisten Tanaman Meliau', 'target_unit_kerja' => 'Kebun Inti Gunung Meliau', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
        ];

        $analysis = $this->service->validateChainTopology($nodes);

        $this->assertTrue($analysis['is_valid']);
        $this->assertEquals('cyclic', $analysis['topology']);
        $this->assertCount(0, $analysis['closed_vacancies']);
        $this->assertCount(0, $analysis['unfilled_vacancies']);
    }

    public function test_validate_chain_topology_detects_multi_cyclic_subgraphs_with_individual_clusters(): void
    {
        // Cluster 1 (4 employees in cyclic rotation)
        Unit::factory()->create(['nama' => 'Kebun Inti Gunung Meliau']);
        Unit::factory()->create(['nama' => 'PKS Gunung Meliau']);
        Unit::factory()->create(['nama' => 'Kebun Rimba Belian']);
        Unit::factory()->create(['nama' => 'PKS Rimba Belian']);

        Position::factory()->create(['nama' => 'Asisten Tanaman Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Tanaman Belian', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik Belian', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Tanaman Meliau', 'unit_kerja' => 'Kebun Inti Gunung Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Teknik Meliau', 'unit_kerja' => 'PKS Gunung Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empC = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Tanaman Belian', 'unit_kerja' => 'Kebun Rimba Belian', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empD = Employee::factory()->create(['nama' => 'KARYAWAN D', 'jabatan' => 'Asisten Teknik Belian', 'unit_kerja' => 'PKS Rimba Belian', 'tmt_unit_kerja' => now()->subYears(3)]);

        // Cluster 2 (2 employees in mutual swap / cyclic)
        Unit::factory()->create(['nama' => 'Kantor Direksi Pontianak']);
        Position::factory()->create(['nama' => 'Kepala Bagian SDM', 'rm_level' => 'RM-1']);
        Position::factory()->create(['nama' => 'Kasubag SDM', 'rm_level' => 'RM-2']);

        $empE = Employee::factory()->create(['nama' => 'KARYAWAN E', 'jabatan' => 'Kasubag SDM', 'unit_kerja' => 'Kantor Direksi Pontianak', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empF = Employee::factory()->create(['nama' => 'KARYAWAN F', 'jabatan' => 'Kepala Bagian SDM', 'unit_kerja' => 'Kantor Direksi Pontianak', 'tmt_unit_kerja' => now()->subYears(3)]);

        // 6 Nodes combined in 1 collective transaction
        $nodes = [
            // Cycle 1
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Asisten Teknik Meliau', 'target_unit_kerja' => 'PKS Gunung Meliau', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empB->nik, 'target_jabatan' => 'Asisten Tanaman Belian', 'target_unit_kerja' => 'Kebun Rimba Belian', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empC->nik, 'target_jabatan' => 'Asisten Teknik Belian', 'target_unit_kerja' => 'PKS Rimba Belian', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empD->nik, 'target_jabatan' => 'Asisten Tanaman Meliau', 'target_unit_kerja' => 'Kebun Inti Gunung Meliau', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            // Cycle 2 (Independent swap)
            ['employee_nik' => $empE->nik, 'target_jabatan' => 'Kepala Bagian SDM', 'target_unit_kerja' => 'Kantor Direksi Pontianak', 'jenis_mutasi' => 'PROMOSI', 'is_dispensasi' => true, 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empF->nik, 'target_jabatan' => 'Kasubag SDM', 'target_unit_kerja' => 'Kantor Direksi Pontianak', 'jenis_mutasi' => 'DEMOSI', 'is_dispensasi' => true, 'status_jabatan' => 'Definitif'],
        ];

        $analysis = $this->service->validateChainTopology($nodes);

        $this->assertTrue($analysis['is_valid']);
        $this->assertEquals('multi_cyclic', $analysis['topology']);
        $this->assertCount(2, $analysis['clusters']);
        $this->assertCount(0, $analysis['closed_vacancies']);
        $this->assertCount(0, $analysis['unfilled_vacancies']);

        // Assert Cluster 1
        $cluster1 = $analysis['clusters'][0];
        $this->assertEquals('cyclic', $cluster1['type']);
        $this->assertCount(4, $cluster1['nodes']);
        $this->assertNotNull($cluster1['loopback']);
        $this->assertEquals($empD->nik, $cluster1['loopback']['from_nik']);
        $this->assertEquals($empA->nik, $cluster1['loopback']['to_nik']);

        // Assert Cluster 2
        $cluster2 = $analysis['clusters'][1];
        $this->assertEquals('cyclic', $cluster2['type']);
        $this->assertCount(2, $cluster2['nodes']);
        $this->assertNotNull($cluster2['loopback']);
        $this->assertEquals($empF->nik, $cluster2['loopback']['from_nik']);
        $this->assertEquals($empE->nik, $cluster2['loopback']['to_nik']);
    }

    public function test_process_chain_mutasi_executes_4_employees_closed_cyclic_atomically(): void
    {
        $u1 = Unit::factory()->create(['nama' => 'Kebun Inti Gunung Meliau']);
        $u2 = Unit::factory()->create(['nama' => 'PKS Gunung Meliau']);
        $u3 = Unit::factory()->create(['nama' => 'Kebun Rimba Belian']);
        $u4 = Unit::factory()->create(['nama' => 'PKS Rimba Belian']);

        Position::factory()->create(['nama' => 'Asisten Tanaman Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Tanaman Belian', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Teknik Belian', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Tanaman Meliau', 'unit_kerja' => 'Kebun Inti Gunung Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Teknik Meliau', 'unit_kerja' => 'PKS Gunung Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empC = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Tanaman Belian', 'unit_kerja' => 'Kebun Rimba Belian', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empD = Employee::factory()->create(['nama' => 'KARYAWAN D', 'jabatan' => 'Asisten Teknik Belian', 'unit_kerja' => 'PKS Rimba Belian', 'tmt_unit_kerja' => now()->subYears(3)]);

        $header = [
            'nomor_sk' => 'DIR/SK/MUTASI/001',
            'tanggal_sk' => now()->toDateString(),
            'tmt_awal' => now()->toDateString(),
            'catatan' => 'Mutasi Kolektif 4 Asisten Regional V',
        ];

        $nodes = [
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Asisten Teknik Meliau', 'target_unit_kerja' => 'PKS Gunung Meliau', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empB->nik, 'target_jabatan' => 'Asisten Tanaman Belian', 'target_unit_kerja' => 'Kebun Rimba Belian', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empC->nik, 'target_jabatan' => 'Asisten Teknik Belian', 'target_unit_kerja' => 'PKS Rimba Belian', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empD->nik, 'target_jabatan' => 'Asisten Tanaman Meliau', 'target_unit_kerja' => 'Kebun Inti Gunung Meliau', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
        ];

        $result = $this->service->processChainMutasi($header, $nodes);

        $this->assertEquals('chain', $result['type']);
        $this->assertEquals('cyclic', $result['topology']);
        $this->assertEquals(4, $result['total_transfers']);
        $this->assertNotEmpty($result['batch_id']);

        // Check each employee was moved correctly
        $empA->refresh();
        $this->assertEquals('Asisten Teknik Meliau', $empA->jabatan);
        $this->assertEquals('PKS Gunung Meliau', $empA->unit_kerja);

        $empB->refresh();
        $this->assertEquals('Asisten Tanaman Belian', $empB->jabatan);
        $this->assertEquals('Kebun Rimba Belian', $empB->unit_kerja);

        $empC->refresh();
        $this->assertEquals('Asisten Teknik Belian', $empC->jabatan);
        $this->assertEquals('PKS Rimba Belian', $empC->unit_kerja);

        $empD->refresh();
        $this->assertEquals('Asisten Tanaman Meliau', $empD->jabatan);
        $this->assertEquals('Kebun Inti Gunung Meliau', $empD->unit_kerja);

        // Check JobHistories batch_id
        $histories = JobHistory::where('batch_id', $result['batch_id'])->orderBy('chain_order')->get();
        $this->assertCount(4, $histories);
        $this->assertEquals($empA->nik, $histories[0]->employee_nik);
        $this->assertEquals($empB->nik, $histories[1]->employee_nik);
        $this->assertEquals($empC->nik, $histories[2]->employee_nik);
        $this->assertEquals($empD->nik, $histories[3]->employee_nik);
    }

    public function test_validate_chain_topology_detects_open_domino_chain(): void
    {
        $u1 = Unit::factory()->create(['nama' => 'Kebun A']);
        $u2 = Unit::factory()->create(['nama' => 'Kebun B']);

        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 2', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'PEGAWAI A', 'jabatan' => 'Asisten Afdeling 1', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['nama' => 'PEGAWAI B', 'jabatan' => 'Asisten Afdeling 2', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);

        // Target 'Askep Tanaman' in 'Kebun B' is currently VACANT!
        $nodes = [
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Askep Tanaman', 'target_unit_kerja' => 'Kebun B', 'jenis_mutasi' => 'PROMOSI', 'is_dispensasi' => true, 'status_jabatan' => 'Definitif'],
            ['employee_nik' => $empB->nik, 'target_jabatan' => 'Asisten Afdeling 1', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'],
        ];

        $analysis = $this->service->validateChainTopology($nodes);

        $this->assertTrue($analysis['is_valid']);
        $this->assertEquals('domino', $analysis['topology']);
        $this->assertCount(1, $analysis['closed_vacancies']);
        $this->assertCount(1, $analysis['unfilled_vacancies']);
        $this->assertEquals('Askep Tanaman', $analysis['closed_vacancies'][0]['jabatan']);
        $this->assertEquals('Asisten Afdeling 2', $analysis['unfilled_vacancies'][0]['jabatan']);
    }

    public function test_validate_chain_topology_fails_on_outsider_occupant(): void
    {
        $u1 = Unit::factory()->create(['nama' => 'Kebun A']);

        Position::factory()->create(['nama' => 'Jabatan X', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Jabatan Y', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'PEGAWAI A', 'jabatan' => 'Jabatan X', 'unit_kerja' => 'Kebun A']);
        $empOutsider = Employee::factory()->create(['nama' => 'PEGAWAI LUAR', 'jabatan' => 'Jabatan Y', 'unit_kerja' => 'Kebun A']);

        // empA tries to target Jabatan Y which is occupied by empOutsider, but empOutsider is not in chain
        $nodes = [
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Jabatan Y', 'target_unit_kerja' => 'Kebun A'],
            // Need min 2 nodes
            ['employee_nik' => '99999999', 'target_jabatan' => 'Jabatan X', 'target_unit_kerja' => 'Kebun A'],
        ];

        $analysis = $this->service->validateChainTopology($nodes);

        $this->assertFalse($analysis['is_valid']);
        $this->assertNotEmpty($analysis['errors']);
    }

    public function test_future_dated_mutasi_creates_scheduled_job_history_without_updating_employee_immediately(): void
    {
        $unit = Unit::factory()->create(['nama' => 'Kebun Asal']);
        $unitTarget = Unit::factory()->create(['nama' => 'Kebun Tujuan']);
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Asal',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Asal',
            'level' => 'Karpim',
            'golongan' => 'IIIB/01',
            'tmt_awal' => now()->subYears(3)->toDateString(),
            'status_penugasan' => 'Definitif',
            'jenis_mutasi' => 'MUTASI_UNIT',
            'is_applied' => true,
        ]);

        $futureTmt = now()->addDays(14)->toDateString();

        $result = $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik,
            targetJabatan: 'Asisten Tanaman',
            targetUnitKerja: 'Kebun Tujuan',
            tmtAwal: $futureTmt,
            jenisMutasi: 'MUTASI_UNIT'
        ));

        $this->assertTrue($result['is_scheduled']);
        $this->assertEquals($futureTmt, $result['tmt_awal']);

        // Check Employee master record: MUST NOT CHANGE YET!
        $emp->refresh();
        $this->assertEquals('Kebun Asal', $emp->unit_kerja);
        $this->assertEquals('Asisten Tanaman', $emp->jabatan);

        // Check JobHistory: is_applied must be FALSE
        $scheduledJob = JobHistory::where('employee_nik', $emp->nik)
            ->where('unit_kerja', 'Kebun Tujuan')
            ->first();

        $this->assertNotNull($scheduledJob);
        $this->assertFalse($scheduledJob->is_applied);
        $this->assertEquals($futureTmt, $scheduledJob->tmt_awal->toDateString());

        // Employee currentJob relation must still return the active current job, not the scheduled one
        $this->assertNotNull($emp->currentJob);
        $this->assertEquals('Kebun Asal', $emp->currentJob->unit_kerja);
        $this->assertTrue($emp->hasScheduledMutation());
    }

    public function test_future_dated_mutasi_reserves_target_position_and_prevents_duplicate_targeting(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);

        $emp1 = Employee::factory()->create([
            'jabatan' => 'Askep Tanaman',
            'unit_kerja' => 'Kebun A',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        $emp2 = Employee::factory()->create([
            'jabatan' => 'Askep Tanaman',
            'unit_kerja' => 'Kebun A',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        $futureTmt = now()->addMonth()->startOfMonth()->toDateString();

        // Emp 1 reserves Askep Tanaman at Kebun B for next month
        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp1->nik,
            targetJabatan: 'Askep Tanaman',
            targetUnitKerja: 'Kebun B',
            tmtAwal: $futureTmt,
            jenisMutasi: 'MUTASI_UNIT'
        ));

        // Analyze target position
        $analysis = $this->service->analyzeTarget('Askep Tanaman', 'Kebun B');
        $this->assertTrue($analysis['is_reserved']);
        $this->assertEquals($emp1->nik, $analysis['reserved_job']->employee_nik);

        // Emp 2 tries to target the same reserved position -> MUST FAIL with InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('telah dipesan (Reserved)');

        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp2->nik,
            targetJabatan: 'Askep Tanaman',
            targetUnitKerja: 'Kebun B',
            tmtAwal: $futureTmt,
            jenisMutasi: 'MUTASI_UNIT'
        ));
    }

    public function test_apply_effective_mutations_activates_due_mutations_and_updates_employee(): void
    {
        Unit::factory()->create(['nama' => 'Unit X']);
        Unit::factory()->create(['nama' => 'Unit Y']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 2', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Afdeling 1',
            'unit_kerja' => 'Unit X',
        ]);

        // Manually create a scheduled mutation whose TMT is today
        $dueTmt = now()->toDateString();
        $job = JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Afdeling 2',
            'unit_kerja' => 'Unit Y',
            'level' => 'Karpim',
            'golongan' => 'IIIC',
            'tmt_awal' => $dueTmt,
            'status_penugasan' => 'Definitif',
            'jenis_mutasi' => 'ROTASI',
            'is_applied' => false,
        ]);

        // Act: Apply due mutations
        $result = $this->service->applyEffectiveMutations($dueTmt);

        $this->assertEquals(1, $result['activated_count']);

        // Assert: JobHistory is now applied
        $job->refresh();
        $this->assertTrue($job->is_applied);

        // Assert: Employee record is now updated
        $emp->refresh();
        $this->assertEquals('Asisten Afdeling 2', $emp->jabatan);
        $this->assertEquals('Unit Y', $emp->unit_kerja);
        $this->assertEquals($dueTmt, $emp->tanggal_dalam_jabatan->toDateString());
    }

    public function test_cancel_scheduled_mutation_soft_deletes_with_audit_and_releases_reservation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Afdeling 1',
            'unit_kerja' => 'Kebun A',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        $futureTmt = now()->addDays(20)->toDateString();

        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik,
            targetJabatan: 'Asisten Afdeling 1',
            targetUnitKerja: 'Kebun B',
            tmtAwal: $futureTmt,
            jenisMutasi: 'MUTASI_UNIT'
        ));

        $scheduledJob = JobHistory::where('employee_nik', $emp->nik)->where('is_applied', false)->first();
        $this->assertNotNull($scheduledJob);

        // Act: Cancel scheduled mutation
        $cancelResult = $this->service->cancelScheduledMutation($scheduledJob->id, 'Dibatalkan Direksi');

        $this->assertEquals(1, $cancelResult['cancelled_count']);

        // Assert: JobHistory is retained for audit (soft delete) with canceller & reason
        $this->assertSoftDeleted('job_histories', [
            'id' => $scheduledJob->id,
            'cancelled_by' => $user->id,
            'alasan_pembatalan' => 'Dibatalkan Direksi',
        ]);

        // Assert: Position is no longer reserved
        $analysis = $this->service->analyzeTarget('Asisten Afdeling 1', 'Kebun B');
        $this->assertFalse($analysis['is_reserved']);
        $this->assertTrue($analysis['vacant']);
    }

    public function test_apply_effective_mutations_command_executes_successfully(): void
    {
        $this->artisan('hr:apply-effective-mutations')
            ->assertExitCode(0);
    }

    public function test_scheduled_promotion_persists_and_restores_job_grade_when_applied(): void
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'golongan' => 'IIIA/00',
            'job_grade' => '11',
            'rm_level' => 'RM-3',
            'level' => 'Karpim',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Askep Tanaman B', 'rm_level' => 'RM-2']);
        Unit::factory()->create(['nama' => 'Kebun Sawit B']);

        $futureTmt = now()->addDays(5)->toDateString();

        // Process scheduled promotion with new golongan & job grade
        $result = $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik,
            targetJabatan: 'Askep Tanaman B',
            targetUnitKerja: 'Kebun Sawit B',
            tmtAwal: $futureTmt,
            jenisMutasi: 'PROMOSI',
            statusJabatan: 'Definitif',
            isDispensasi: true,
            alasanDispensasi: 'Promosi: Keputusan Direksi No. 12/BOD/2026',
            newGolongan: 'IIIC/00',
            newJobGrade: '13'
        ));

        $this->assertTrue($result['is_scheduled']);

        // Check JobHistory: is_applied must be FALSE, but job_grade must be 13
        $scheduledJob = JobHistory::where('employee_nik', $emp->nik)->where('is_applied', false)->first();
        $this->assertNotNull($scheduledJob);
        $this->assertEquals(13, $scheduledJob->job_grade);
        $this->assertEquals('IIIC/00', $scheduledJob->golongan);

        // Employee profile is still at the old position and grade
        $emp->refresh();
        $this->assertEquals('Asisten Kebun A', $emp->jabatan);
        $this->assertEquals('11', $emp->job_grade);
        $this->assertEquals('IIIA/00', $emp->golongan);

        // Act: Run applyEffectiveMutations for the future date
        $activationResult = $this->service->applyEffectiveMutations($futureTmt);

        $this->assertEquals(1, $activationResult['activated_count']);

        // Assert: Employee profile is now updated to the new position, golongan, AND job_grade 13
        $emp->refresh();
        $this->assertEquals('Askep Tanaman B', $emp->jabatan);
        $this->assertEquals('Kebun Sawit B', $emp->unit_kerja);
        $this->assertEquals('RM-2', $emp->rm_level);
        $this->assertEquals('IIIC/00', $emp->golongan);
        $this->assertEquals('13', $emp->job_grade);

        $scheduledJob->refresh();
        $this->assertTrue($scheduledJob->is_applied);
    }

    public function test_temporary_assignment_into_occupied_position_is_rejected_without_side_effects(): void
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Unit A']);
        $empB = Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Unit A']);

        try {
            $this->service->processMutasi(new MutasiRequest(
                employeeNik: $empA->nik,
                targetJabatan: 'Posisi B',
                targetUnitKerja: 'Unit A',
                tmtAwal: now()->toDateString(),
                jenisMutasi: 'ROTASI',
                statusJabatan: 'Plt'
            ));
            $this->fail('Plt into an occupied position must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('formasi kosong', $e->getMessage());
        }

        // Neither employee moved — no double occupancy of Posisi A
        $this->assertEquals('Posisi A', $empA->refresh()->jabatan);
        $this->assertEquals('Posisi B', $empB->refresh()->jabatan);
    }

    public function test_unmastered_target_title_stores_canonical_rm_and_karpim_level(): void
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Mandor Panen',
            'unit_kerja' => 'Kebun A',
            'level' => 'Karpel',
            'rm_level' => 'RM-4',
        ]);

        // 'Asisten Gudang' is NOT in master positions -> resolved by title matching (RM-3)
        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik,
            targetJabatan: 'Asisten Gudang',
            targetUnitKerja: 'Kebun A',
            tmtAwal: now()->toDateString(),
            jenisMutasi: 'PROMOSI'
        ));

        $emp->refresh();
        $this->assertEquals('RM-3', $emp->rm_level);
        $this->assertEquals('Karpim', $emp->level);
    }

    public function test_definitive_placement_to_higher_rm_requires_promosi_type(): void
    {
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Manajer Kebun', 'rm_level' => 'RM-1']);

        $emp = Employee::factory()->create(['jabatan' => 'Asisten Tanaman', 'unit_kerja' => 'Kebun A']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('wajib diproses sebagai Promosi');

        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik,
            targetJabatan: 'Manajer Kebun',
            targetUnitKerja: 'Kebun A',
            tmtAwal: now()->toDateString(),
            jenisMutasi: 'PENUGASAN',
            statusJabatan: 'Definitif'
        ));
    }

    public function test_service_enforces_tenure_dispensasi_and_known_mutation_types(): void
    {
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun A',
            'tmt_unit_kerja' => now()->subMonths(6)->toDateString(),
        ]);

        // Unknown mutation type
        try {
            $this->service->processMutasi(new MutasiRequest($emp->nik, 'Asisten Tanaman', 'Kebun B', now()->toDateString(), 'MASA_PERSIAPAN_PENSIUN'));
            $this->fail('Unknown mutation type must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('tidak dikenal', $e->getMessage());
        }

        // Tenure < 24 months without dispensasi
        try {
            $this->service->processMutasi(new MutasiRequest($emp->nik, 'Asisten Tanaman', 'Kebun B', now()->toDateString(), 'MUTASI_UNIT'));
            $this->fail('Tenure below minimum without dispensasi must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('masa dinas minimal', $e->getMessage());
        }

        // Dispensasi flag without a reason is not sufficient
        try {
            $this->service->processMutasi(new MutasiRequest(
                employeeNik: $emp->nik, targetJabatan: 'Asisten Tanaman', targetUnitKerja: 'Kebun B',
                tmtAwal: now()->toDateString(), jenisMutasi: 'MUTASI_UNIT', isDispensasi: true, alasanDispensasi: ' '
            ));
            $this->fail('Dispensasi without reason must be rejected.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('masa dinas minimal', $e->getMessage());
        }

        $this->assertEquals('Kebun A', $emp->refresh()->unit_kerja);

        // With dispensasi + reason it succeeds
        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik, targetJabatan: 'Asisten Tanaman', targetUnitKerja: 'Kebun B',
            tmtAwal: now()->toDateString(), jenisMutasi: 'MUTASI_UNIT', isDispensasi: true,
            alasanDispensasi: 'Masa Dinas: Nota Dinas Direksi No. 7/ND/2026'
        ));

        $this->assertEquals('Kebun B', $emp->refresh()->unit_kerja);
    }

    public function test_scheduled_swap_is_batched_and_activated_or_cancelled_as_a_pair(): void
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Unit A']);
        $empB = Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Unit A']);
        $empC = Employee::factory()->create(['jabatan' => 'Posisi C', 'unit_kerja' => 'Unit A']);
        $empD = Employee::factory()->create(['jabatan' => 'Posisi D', 'unit_kerja' => 'Unit A']);

        $futureTmt = now()->addDays(7)->toDateString();

        $this->service->processMutasi(new MutasiRequest($empA->nik, 'Posisi B', 'Unit A', $futureTmt, 'ROTASI'));
        $this->service->processMutasi(new MutasiRequest($empC->nik, 'Posisi D', 'Unit A', $futureTmt, 'ROTASI'));

        $legA = JobHistory::where('employee_nik', $empA->nik)->where('is_applied', false)->first();
        $legB = JobHistory::where('employee_nik', $empB->nik)->where('is_applied', false)->first();
        $this->assertNotNull($legA->batch_id);
        $this->assertEquals($legA->batch_id, $legB->batch_id);

        // Activating one leg activates its partner, but not the unrelated C<->D swap
        $result = $this->service->applyScheduledMutation($legA->id);

        $this->assertEquals(2, $result['activated_count']);
        $this->assertEquals('Posisi B', $empA->refresh()->jabatan);
        $this->assertEquals('Posisi A', $empB->refresh()->jabatan);
        $this->assertEquals('Posisi C', $empC->refresh()->jabatan);

        // Cancelling one leg of C<->D cancels both legs
        $legC = JobHistory::where('employee_nik', $empC->nik)->where('is_applied', false)->first();
        $cancel = $this->service->cancelScheduledMutation($legC->id, 'Revisi SK');

        $this->assertEquals(2, $cancel['cancelled_count']);
        $this->assertEquals(0, JobHistory::where('is_applied', false)->count());
    }

    public function test_scheduled_sanction_end_date_is_applied_on_activation(): void
    {
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Diperbantukan', 'rm_level' => 'RM-3', 'is_diperban' => true]);

        $emp = Employee::factory()->create(['jabatan' => 'Asisten Tanaman', 'unit_kerja' => 'Kebun A']);
        $futureTmt = now()->addDays(3)->toDateString();
        $sanksiEnd = now()->addYear()->toDateString();

        $this->service->processMutasi(new MutasiRequest(
            employeeNik: $emp->nik,
            targetJabatan: 'Asisten Diperbantukan',
            targetUnitKerja: 'Kebun A',
            tmtAwal: $futureTmt,
            jenisMutasi: 'SANKSI_INDISIPLINER',
            tanggalBerakhirSanksi: $sanksiEnd
        ));

        $this->service->applyEffectiveMutations($futureTmt);

        $emp->refresh();
        $this->assertEquals('Asisten Diperbantukan', $emp->jabatan);
        $this->assertEquals($sanksiEnd, Carbon::parse($emp->tanggal_berakhir_sanksi)->toDateString());
    }

    // -------------------------------------------------------------------------
    // Chain governance, node preparation & flow ordering
    // -------------------------------------------------------------------------

    public function test_chain_rejects_unknown_mutation_type_and_retired_participant(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Position::factory()->create(['nama' => 'Jabatan X', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Jabatan Y', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['jabatan' => 'Jabatan X', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['jabatan' => 'Jabatan Y', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);

        $unknown = $this->service->validateChainTopology([
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Jabatan Y', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'MASA_PERSIAPAN_PENSIUN'],
            ['employee_nik' => $empB->nik, 'target_jabatan' => 'Jabatan X', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'ROTASI'],
        ]);
        $this->assertFalse($unknown['is_valid']);
        $this->assertStringContainsString('tidak dikenal', implode(' ', $unknown['errors']));

        $retired = Employee::factory()->create([
            'jabatan' => 'Jabatan Y', 'unit_kerja' => 'Kebun A', 'tanggal_pensiun' => now()->subMonth()->toDateString(),
        ]);
        $withRetired = $this->service->validateChainTopology([
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Jabatan X', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'ROTASI'],
            ['employee_nik' => $retired->nik, 'target_jabatan' => 'Jabatan Y', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'ROTASI'],
        ]);
        $this->assertFalse($withRetired['is_valid']);
        $this->assertStringContainsString('Batas Usia Pensiun', implode(' ', $withRetired['errors']));
    }

    public function test_chain_blocks_the_seat_of_a_participant_on_temporary_assignment(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Position::factory()->create(['nama' => 'Jabatan X', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Jabatan Y', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['jabatan' => 'Jabatan X', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['jabatan' => 'Jabatan Y', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);

        // B only acts as Plt, so B keeps the definitive seat Jabatan Y that A wants
        $analysis = $this->service->validateChainTopology([
            ['employee_nik' => $empA->nik, 'target_jabatan' => 'Jabatan Y', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'ROTASI'],
            ['employee_nik' => $empB->nik, 'target_jabatan' => 'Jabatan X', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'PENUGASAN', 'status_jabatan' => 'Plt'],
        ]);

        $this->assertFalse($analysis['is_valid']);
        $this->assertStringContainsString('penugasan sementara (Pj/Plt)', implode(' ', $analysis['errors']));
    }

    public function test_chain_requires_dispensasi_for_short_unit_tenure_and_persists_folded_reason(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Position::factory()->create(['nama' => 'Jabatan X', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Jabatan Y', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Jabatan Z', 'rm_level' => 'RM-3']);

        // A has only 6 months in Kebun A; Jabatan Z at Kebun B is vacant, B takes over A's seat
        $empA = Employee::factory()->create(['jabatan' => 'Jabatan X', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subMonths(6)]);
        $empB = Employee::factory()->create(['jabatan' => 'Jabatan Y', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);

        $nodeA = ['employee_nik' => $empA->nik, 'target_jabatan' => 'Jabatan Z', 'target_unit_kerja' => 'Kebun B', 'jenis_mutasi' => 'MUTASI_UNIT'];
        $nodeB = ['employee_nik' => $empB->nik, 'target_jabatan' => 'Jabatan X', 'target_unit_kerja' => 'Kebun A', 'jenis_mutasi' => 'ROTASI'];

        $blocked = $this->service->validateChainTopology([$nodeA, $nodeB]);
        $this->assertFalse($blocked['is_valid']);
        $this->assertStringContainsString('masa dinas minimal', implode(' ', $blocked['errors']));

        // The form only sends the per-criterion fields; the service folds them into flag + reason
        $nodeA += ['dispensasi_tenure' => true, 'alasan_dispensasi_tenure' => 'Nota Dinas Direksi No. 9'];

        $this->service->processChainMutasi(
            ['nomor_sk' => 'SK/KOLEKTIF/09', 'tanggal_sk' => now()->toDateString(), 'tmt_awal' => now()->toDateString(), 'created_by' => null],
            [$nodeA, $nodeB]
        );

        $historyA = JobHistory::where('employee_nik', $empA->nik)->where('jabatan', 'Jabatan Z')->first();
        $this->assertTrue($historyA->is_dispensasi);
        $this->assertSame('[Dispensasi Masa Dinas]: Nota Dinas Direksi No. 9', $historyA->alasan_dispensasi);
        $this->assertSame(
            $historyA->batch_id,
            JobHistory::where('employee_nik', $empB->nik)->where('jabatan', 'Jabatan X')->value('batch_id')
        );
        $this->assertSame('Jabatan Z', $empA->refresh()->jabatan);
    }

    public function test_prepare_chain_nodes_drops_incomplete_rows_and_folds_dispensasi(): void
    {
        $prepared = $this->service->prepareChainNodes([
            ['employee_nik' => '11111111', 'target_jabatan' => 'A', 'target_unit_kerja' => 'U1', 'dispensasi_promosi' => true, 'alasan_dispensasi_promosi' => 'Rekomendasi BOD'],
            ['employee_nik' => '', 'target_jabatan' => 'B', 'target_unit_kerja' => 'U2'],
            ['employee_nik' => '33333333', 'target_jabatan' => 'C', 'target_unit_kerja' => 'U3', 'is_dispensasi' => true, 'alasan_dispensasi' => 'Keputusan Direksi'],
            ['employee_nik' => '44444444', 'target_jabatan' => 'D', 'target_unit_kerja' => 'U4'],
        ]);

        $this->assertCount(3, $prepared);
        $this->assertSame([0, 1, 2], array_keys($prepared));

        $this->assertTrue($prepared[0]['is_dispensasi']);
        $this->assertSame('[Dispensasi Promosi]: Rekomendasi BOD', $prepared[0]['alasan_dispensasi']);
        $this->assertTrue($prepared[1]['is_dispensasi']);
        $this->assertSame('Keputusan Direksi', $prepared[1]['alasan_dispensasi']);
        $this->assertFalse($prepared[2]['is_dispensasi']);
        $this->assertNull($prepared[2]['alasan_dispensasi']);
    }

    public function test_order_chain_nodes_by_flow_starts_at_the_head_of_the_domino(): void
    {
        // C's seat is taken by B, B's seat by A. Nobody takes A's seat, so A is the head.
        $ordered = $this->service->orderChainNodesByFlow([
            0 => ['employee_nik' => 'C', 'occupant_nik' => null],
            1 => ['employee_nik' => 'B', 'occupant_nik' => 'C'],
            2 => ['employee_nik' => 'A', 'occupant_nik' => 'B'],
        ]);

        $this->assertSame(['A', 'B', 'C'], array_column($ordered, 'employee_nik'));
        $this->assertSame([3, 2, 1], array_column($ordered, 'slot_number'));
        $this->assertSame([2, 1, 0], array_column($ordered, 'original_slot_index'));
        $this->assertSame([], $this->service->orderChainNodesByFlow([]));
    }

    // -------------------------------------------------------------------------
    // Business exceptions
    // -------------------------------------------------------------------------

    public function test_rule_violations_use_a_dedicated_business_exception(): void
    {
        $emp = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Unit A']);

        try {
            $this->service->processMutasi(new MutasiRequest($emp->nik, 'Posisi A', 'Unit A', now()->toDateString(), 'ROTASI'));
            $this->fail('Same position must be rejected.');
        } catch (MutasiRuleViolationException $e) {
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    public function test_swap_with_an_occupant_without_active_job_raises_integrity_exception(): void
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Unit A']);
        $empB = Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Unit A']);
        JobHistory::where('employee_nik', $empB->nik)->delete();

        $this->expectException(MutasiIntegrityException::class);

        $this->service->processMutasi(new MutasiRequest($empA->nik, 'Posisi B', 'Unit A', now()->toDateString(), 'ROTASI'));
    }

    public function test_rm_5_to_karpim_promotion_follows_the_ckp_path(): void
    {
        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create(['jabatan' => 'Krani Pemetaan', 'level' => 'Karpel', 'rm_level' => 'RM-5']);

        $result = $this->service->checkPromotionEligibility($emp, 'Asisten Tanaman');

        $this->assertTrue($result['is_applicable']);
        $this->assertSame('Jalur Karir CKP (Calon Karyawan Pimpinan)', $result['criteria'][0]['label']);
    }
}
