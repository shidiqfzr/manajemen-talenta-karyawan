<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeDeletionArchive;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\EmployeeDeletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * EmployeeDeletionService: archive-before-delete, atomicity and the append-only archive (SECURITY.md §5).
 */
class EmployeeDeletionServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EmployeeDeletionService
    {
        return app(EmployeeDeletionService::class);
    }

    public function test_archive_keeps_master_data_placement_history_actor_and_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin SDM']);
        $employee = Employee::factory()->create(['nik' => '22220001', 'nama' => 'KARYAWAN ARSIP', 'jabatan' => 'Asisten Afdeling I', 'unit_kerja' => 'Kebun Arsip']);
        JobHistory::create(['employee_nik' => $employee->nik, 'jabatan' => 'Mandor I', 'unit_kerja' => 'Kebun Lama', 'tmt_awal' => '2019-01-01']);

        $historyCount = $employee->jobHistories()->count();

        $deleted = $this->service()->deleteMany(Employee::whereKey($employee->nik), $admin, '  Karyawan keluar sesuai SK Direksi  ');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('employees', ['nik' => '22220001']);
        $this->assertSame(0, JobHistory::where('employee_nik', '22220001')->count());

        $archive = EmployeeDeletionArchive::sole();
        $this->assertSame('22220001', $archive->employee_nik);
        $this->assertSame('KARYAWAN ARSIP', $archive->employee_nama);
        $this->assertSame('Kebun Arsip', $archive->unit_kerja);
        $this->assertSame('22220001', $archive->employee_data['nik']);
        $this->assertCount($historyCount, $archive->job_histories);
        $this->assertContains('Mandor I', array_column($archive->job_histories, 'jabatan'));
        $this->assertSame($admin->id, $archive->deleted_by);
        $this->assertSame('Admin SDM', $archive->deleted_by_name);
        $this->assertSame('Karyawan keluar sesuai SK Direksi', $archive->reason);
    }

    public function test_one_batch_id_per_run(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Employee::factory()->create(['nik' => '22220002']);
        Employee::factory()->create(['nik' => '22220003']);

        $this->service()->deleteMany(Employee::query(), $admin, 'Pembersihan data ganda');

        $this->assertSame(1, EmployeeDeletionArchive::distinct()->count('batch_id'));
        $this->assertSame(2, EmployeeDeletionArchive::count());
    }

    public function test_reason_is_required(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Employee::factory()->create(['nik' => '22220004']);

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service()->deleteMany(Employee::query(), $admin, '   singkat ');
        } finally {
            $this->assertDatabaseHas('employees', ['nik' => '22220004']);
            $this->assertSame(0, EmployeeDeletionArchive::count());
        }
    }

    public function test_failure_rolls_back_rows_and_archive(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Employee::factory()->create(['nik' => '22220005']);
        Employee::factory()->create(['nik' => '22220006']);

        $deletions = 0;
        Employee::deleting(function () use (&$deletions) {
            if (++$deletions === 2) {
                throw new RuntimeException('Simulated deletion failure.');
            }
        });

        try {
            $this->service()->deleteMany(Employee::query(), $admin, 'Pembersihan data ganda');
            $this->fail('The simulated failure must propagate.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(2, Employee::count());
        $this->assertSame(0, EmployeeDeletionArchive::count());
    }

    public function test_archive_is_append_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Employee::factory()->create(['nik' => '22220007']);
        $this->service()->deleteMany(Employee::query(), $admin, 'Pembersihan data ganda');

        $archive = EmployeeDeletionArchive::sole();

        try {
            $archive->update(['reason' => 'diubah']);
            $this->fail('Updating the archive must be refused.');
        } catch (LogicException) {
            // expected
        }

        $this->expectException(LogicException::class);
        $archive->delete();
    }
}
