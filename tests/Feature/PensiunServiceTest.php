<?php

namespace Tests\Feature;

use App\Livewire\Admin\Employees\EmployeeTable;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Mutasi\FormationService;
use App\Services\Mutasi\MutasiGovernanceGuard;
use App\Services\Mutasi\MutasiLookup;
use App\Services\PensiunService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Services\PensiunService (pemberhentian karyawan yang melewati Batas Usia Pensiun, DOMAIN.md §5.1).
 * Karyawan pensiun dilepas dari jabatan: riwayat ditutup, kursi kosong, daftar aktif tidak memuatnya.
 */
class PensiunServiceTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-11');
        $this->unit = Unit::create(['nama' => 'Kebun Pensiun Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
        $position = Position::create(['nama' => 'Manajer Uji', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]);
        DB::table('position_unit')->insert([
            'unit_id' => $this->unit->id, 'position_id' => $position->id, 'kuota' => 1, 'bidang' => 'TAN',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function holder(string $tanggalLahir): Employee
    {
        $employee = Employee::factory()->create([
            'unit_kerja' => $this->unit->nama, 'jabatan' => 'Manajer Uji', 'status_penugasan' => 'Definitif',
            'tanggal_lahir' => $tanggalLahir, 'tanggal_pensiun' => null, 'tanggal_mbt' => null,
            'tanggal_dalam_jabatan' => '2020-01-01',
        ]);

        return $employee->fresh();
    }

    private function service(): PensiunService
    {
        return app(PensiunService::class);
    }

    public function test_only_employees_past_the_retirement_date_are_due(): void
    {
        $retired = $this->holder('1970-08-15');  // pensiun 2026-08-01
        $mbt = $this->holder('1970-12-10');      // pensiun 2026-12-01, masih dalam MBT
        $young = $this->holder('1990-01-01');

        $due = $this->service()->dueEmployees()->pluck('nik')->all();

        $this->assertSame([$retired->nik], $due);
        $this->assertNotContains($mbt->nik, $due);
        $this->assertNotContains($young->nik, $due);
    }

    public function test_separation_closes_history_frees_the_seat_and_keeps_the_record(): void
    {
        $retired = $this->holder('1970-08-15');
        $formations = app(FormationService::class);
        $this->assertSame('occupied', $formations->analyzeTarget('Manajer Uji', $this->unit->nama)['type']);

        $result = $this->service()->processDue();

        $this->assertSame(1, $result['count']);
        $retired->refresh();
        $this->assertSame('2026-08-01', $retired->tanggal_berhenti->toDateString());
        $this->assertSame(Employee::ALASAN_PENSIUN, $retired->alasan_berhenti);
        $this->assertSame('Manajer Uji', $retired->jabatan, 'Jabatan terakhir tetap menjadi arsip.');

        $history = JobHistory::where('employee_nik', $retired->nik)->first();
        $this->assertSame('2026-08-01', $history->tmt_akhir->toDateString());
        $this->assertStringContainsString('Berakhir karena Pensiun', (string) $history->catatan);

        $this->assertSame('vacant', $formations->analyzeTarget('Manajer Uji', $this->unit->nama)['type']);
        $this->assertFalse(Employee::aktif()->whereKey($retired->nik)->exists());
        $this->assertTrue(Employee::berhenti()->whereKey($retired->nik)->exists());
    }

    public function test_scheduled_mutations_of_a_retiree_are_cancelled_with_a_reason(): void
    {
        $retired = $this->holder('1970-08-15');
        $scheduled = JobHistory::create([
            'employee_nik' => $retired->nik, 'jabatan' => 'Manajer Uji', 'unit_kerja' => 'Unit Lain',
            'tmt_awal' => '2026-12-15', 'is_applied' => false,
        ]);

        $this->service()->processDue();

        $this->assertSoftDeleted('job_histories', ['id' => $scheduled->id]);
        $this->assertStringStartsWith(PensiunService::ALASAN_PEMBATALAN, JobHistory::withTrashed()->find($scheduled->id)->alasan_pembatalan);
    }

    public function test_processing_is_idempotent_and_dry_run_changes_nothing(): void
    {
        $retired = $this->holder('1970-08-15');

        $dry = $this->service()->processDue(null, true);
        $this->assertSame(1, $dry['count']);
        $this->assertTrue($dry['dry_run']);
        $this->assertNull($retired->fresh()->tanggal_berhenti);

        $this->assertSame(1, $this->service()->processDue()['count']);
        $this->assertSame(0, $this->service()->processDue()['count']);
    }

    public function test_the_scheduled_command_runs_the_separation(): void
    {
        $retired = $this->holder('1970-08-15');

        $this->artisan('hr:process-retirements')->assertSuccessful();

        $this->assertNotNull($retired->fresh()->tanggal_berhenti);
    }

    public function test_the_employee_list_shows_active_employees_by_default_and_the_archive_on_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $retired = $this->holder('1970-08-15');
        $active = $this->holder('1990-01-01');
        $this->service()->processDue();

        Livewire::actingAs($admin)->test(EmployeeTable::class)
            ->assertSee($active->nama)
            ->assertDontSee($retired->nama);

        Livewire::actingAs($admin)->test(EmployeeTable::class)
            ->set('statusKepegawaian', 'pensiun')
            ->assertSee($retired->nama)
            ->assertDontSee($active->nama);
    }

    public function test_separation_writes_a_structured_audit_entry(): void
    {
        $retired = $this->holder('1970-08-15');

        $this->service()->processDue();

        $log = MasterAuditLog::where('action', 'employee.separated')->firstOrFail();
        $this->assertSame('employee', $log->entity_type);
        $this->assertStringContainsString($retired->nik, $log->entity_label);
        $this->assertSame('Pensiun', $log->new_values['alasan']);
        $this->assertSame('Manajer Uji', $log->old_values['jabatan']);
        $this->assertSame('Sistem', $log->user_name);
    }

    public function test_an_approved_extension_beyond_bup_keeps_the_employee_active(): void
    {
        $extended = $this->holder('1970-08-15');
        $extended->update(['tanggal_pensiun' => '2027-08-01', 'tanggal_mbt' => '2027-02-01']);

        $this->assertSame([], $this->service()->dueEmployees()->pluck('nik')->all());
        $this->assertNull($extended->fresh()->tanggal_berhenti);
    }

    public function test_sk_can_follow_an_automatic_separation_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $retired = $this->holder('1970-08-15');
        $this->service()->processDue();

        $this->service()->catatSk($retired->fresh(), 'SK-001/PTPN4/2026', Carbon::parse('2026-07-20'), $admin);

        $this->assertSame('SK-001/PTPN4/2026', $retired->fresh()->nomor_sk_berhenti);
        $this->assertSame($admin->id, MasterAuditLog::where('action', 'employee.separation_sk_recorded')->firstOrFail()->user_id);
    }

    public function test_sk_cannot_be_recorded_for_an_active_employee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->catatSk($this->holder('1990-01-01'), 'SK-X', Carbon::today());
    }

    public function test_admin_can_separate_an_employee_for_another_reason_with_sk(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->holder('1990-01-01');

        $this->actingAs($admin)->post(route('admin.employees.separate', $employee), [
            'alasan' => 'Mengundurkan Diri',
            'tanggal_berhenti' => '2026-11-01',
            'nomor_sk' => 'SK-77/2026',
            'tanggal_sk' => '2026-10-20',
        ])->assertRedirect(route('admin.employees.show', $employee));

        $employee->refresh();
        $this->assertSame('Mengundurkan Diri', $employee->alasan_berhenti);
        $this->assertSame('SK-77/2026', $employee->nomor_sk_berhenti);
        $this->assertSame($admin->id, MasterAuditLog::where('action', 'employee.separated')->firstOrFail()->user_id);
    }

    public function test_separation_refuses_a_future_effective_date_so_nothing_changes_early(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->holder('1990-01-01');
        $jobsBefore = $employee->activeJobs()->count();

        $this->actingAs($admin)->post(route('admin.employees.separate', $employee), [
            'alasan' => 'Mengundurkan Diri',
            'tanggal_berhenti' => '2026-12-01',
        ])->assertSessionHasErrors('tanggal_berhenti');

        $employee->refresh();
        $this->assertNull($employee->tanggal_berhenti);
        $this->assertSame($jobsBefore, $employee->activeJobs()->count());
        $this->assertSame(0, MasterAuditLog::where('action', 'employee.separated')->count());

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->separate($employee, 'Mengundurkan Diri', Carbon::parse('2026-12-01'));
    }

    public function test_separation_dated_today_or_earlier_is_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->holder('1990-01-01');

        $this->actingAs($admin)->post(route('admin.employees.separate', $employee), [
            'alasan' => 'Mengundurkan Diri',
            'tanggal_berhenti' => '2026-11-11',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-11', $employee->fresh()->tanggal_berhenti->toDateString());
    }

    public function test_separation_rejects_an_unknown_reason_and_non_admins(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);
        $employee = $this->holder('1990-01-01');
        $payload = ['alasan' => 'Cuti Panjang', 'tanggal_berhenti' => '2026-11-01'];

        $this->actingAs($admin)->post(route('admin.employees.separate', $employee), $payload)->assertSessionHasErrors('alasan');
        $this->actingAs($staff)->post(route('admin.employees.separate', $employee), ['alasan' => 'Pensiun Dini'] + $payload)->assertForbidden();
        $this->assertNull($employee->fresh()->tanggal_berhenti);
    }

    public function test_a_separated_employee_cannot_reopen_history_or_be_mutated(): void
    {
        $retired = $this->holder('1970-08-15');
        $this->service()->processDue();
        $histories = JobHistory::where('employee_nik', $retired->nik)->count();

        $retired->fresh()->update(['jabatan' => 'Jabatan Baru Sesudah Pensiun']);

        $this->assertSame($histories, JobHistory::where('employee_nik', $retired->nik)->count());
        $this->assertTrue(app(MutasiGovernanceGuard::class)->isRetired($retired->fresh()));
        $this->assertNotContains($retired->nik, app(MutasiLookup::class)->getSelectableEmployees()->pluck('nik')->all());
    }

    public function test_an_employee_due_for_retirement_is_not_offered_for_mutasi_before_the_separation_runs(): void
    {
        // Lahir November 1970 => tanggal pensiun 1 November 2026, sudah tiba pada 11 November tetapi belum diproses.
        $due = $this->holder('1970-11-05');
        $active = $this->holder('1990-01-01');

        $this->assertTrue(app(MutasiGovernanceGuard::class)->isRetired($due));
        $this->assertFalse(app(MutasiGovernanceGuard::class)->isRetired($active));

        $selectable = app(MutasiLookup::class)->getSelectableEmployees()->pluck('nik')->all();
        $this->assertNotContains($due->nik, $selectable);
        $this->assertContains($active->nik, $selectable);
    }

    public function test_the_staff_directory_excludes_separated_employees(): void
    {
        $staff = User::factory()->create(['role' => 'user']);
        $retired = $this->holder('1970-08-15');
        $active = $this->holder('1990-01-01');
        $this->service()->processDue();

        $this->actingAs($staff)->get(route('user.employees.index'))
            ->assertOk()->assertSee($active->nama)->assertDontSee($retired->nama);
    }

    public function test_the_profile_page_offers_the_separation_dialog_with_its_impact(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->holder('1990-01-01');
        JobHistory::create(['employee_nik' => $employee->nik, 'jabatan' => 'Manajer Uji', 'unit_kerja' => 'Unit Lain', 'tmt_awal' => '2026-12-15', 'is_applied' => false]);

        $this->actingAs($admin)->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertSee('Berhentikan '.$employee->nama.'?')
            ->assertSee('Dampak pemberhentian')
            ->assertSee('1 mutasi terjadwal dibatalkan')
            ->assertSee('role="dialog"', false);
    }
}
