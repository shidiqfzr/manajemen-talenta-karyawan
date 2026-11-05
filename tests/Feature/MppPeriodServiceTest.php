<?php

namespace Tests\Feature;

use App\Exceptions\MppPeriodRuleViolationException;
use App\Models\MasterAuditLog;
use App\Models\MppPeriod;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use App\Services\Mpp\MppPeriodService;
use App\Services\Mpp\MppSupplyPlanService;
use Carbon\Carbon;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesMppEmployees;
use Tests\TestCase;

class MppPeriodServiceTest extends TestCase
{
    use CreatesMppEmployees;
    use RefreshDatabase;

    private User $admin;

    private MppPeriodService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->service = app(MppPeriodService::class);
        $this->seed(PositionUnitSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail('Pelanggaran aturan periode seharusnya ditolak.');
        } catch (MppPeriodRuleViolationException $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_capture_freezes_matrix_and_members(): void
    {
        $emp = $this->makeAsistenKeuangan('90000001');
        $matrix = app(ManPowerPlanningService::class)->getMppMatrix(2026, 10);

        $period = $this->service->capture(2026, 10, $this->admin);

        $this->assertSame('draft', $period->status);
        $this->assertSame(1, $period->revision);
        $this->assertSame($this->admin->id, $period->captured_by);
        $this->assertEquals($matrix, $period->snapshot);
        $this->assertSame(
            count(app(ManPowerPlanningService::class)->getPositionMembers(2026, 10)),
            $period->members()->count()
        );
        $this->assertGreaterThan(0, $period->members()->where('employee_nik', '90000001')->count());

        $emp->update(['jabatan' => 'Jabatan Lain']);
        DB::table('position_unit')->where('kuota', '>', 0)->limit(1)->increment('kuota', 2);
        $plan = app(MppSupplyPlanService::class);
        $expected = $plan->matrixFor(2026);
        $cells = $expected;
        $cells['rbb']['RM-3']['TAN'] = 9;
        $plan->save(2026, $cells, $expected, 'Penyesuaian hasil rapat SDM', $this->admin);

        $this->assertEquals($matrix, $this->service->find(2026, 10)->snapshot);
    }

    public function test_recapture_replaces_members(): void
    {
        $this->makeAsistenKeuangan('90000001');
        $first = $this->service->capture(2026, 10, $this->admin);
        $this->makeAsistenKeuangan('90000002');

        $again = $this->service->capture(2026, 10, $this->admin);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, $again->revision);
        $this->assertSame(1, MppPeriod::count());
        $live = app(ManPowerPlanningService::class)->getPositionMembers(2026, 10);
        $this->assertSame(count($live), $again->members()->count());
        $this->assertSame(
            count($live),
            $again->members()->get()->unique(fn ($m) => $m->scope.$m->rm_level.$m->bidang.$m->employee_nik)->count()
        );
    }

    public function test_scheduler_capture_is_audited_as_system(): void
    {
        $period = $this->service->capture(2026, 10, null);

        $this->assertNull($period->captured_by);
        $log = MasterAuditLog::where('action', 'mpp_period.captured')->sole();
        $this->assertNull($log->user_id);
        $this->assertSame('Sistem', $log->user_name);
        $this->assertSame('Periode MPP Okt 2026', $log->entity_label);
        $this->assertSame('mpp_period', $log->entity_type);
    }

    public function test_capture_is_refused_for_closed_current_and_future_months(): void
    {
        $this->assertRefused('Periode berjalan atau mendatang belum dapat direkam atau ditutup.', fn () => $this->service->capture(2026, 11, $this->admin));
        $this->assertRefused('Periode berjalan atau mendatang belum dapat direkam atau ditutup.', fn () => $this->service->capture(2026, 12, $this->admin));
        $this->assertRefused('Periode berjalan atau mendatang belum dapat direkam atau ditutup.', fn () => $this->service->close(2026, 11, null, $this->admin));
        $this->assertSame(0, MppPeriod::count());

        $this->service->capture(2026, 10, $this->admin);
        $this->service->close(2026, 10, null, $this->admin);
        $snapshot = $this->service->find(2026, 10)->snapshot;

        $this->makeAsistenKeuangan('90000003');
        $this->assertRefused('Periode Okt 2026 sudah ditutup. Buka kembali terlebih dulu.', fn () => $this->service->capture(2026, 10, $this->admin));
        $this->assertEquals($snapshot, $this->service->find(2026, 10)->snapshot);
    }

    public function test_year_must_be_within_planning_range(): void
    {
        $this->assertRefused(
            'Tahun perencanaan harus antara '.ManPowerPlanningService::PLANNING_YEAR_MIN.' dan '.ManPowerPlanningService::PLANNING_YEAR_MAX.'.',
            fn () => $this->service->capture(ManPowerPlanningService::PLANNING_YEAR_MIN - 1, 1, $this->admin)
        );
    }

    public function test_close_and_reopen_lifecycle(): void
    {
        $this->service->capture(2026, 10, $this->admin);

        $closed = $this->service->close(2026, 10, 'Disetujui rapat SDM', $this->admin);
        $this->assertSame('closed', $closed->status);
        $this->assertSame($this->admin->id, $closed->closed_by);
        $this->assertNotNull($closed->closed_at);
        $this->assertSame('Disetujui rapat SDM', $closed->close_note);

        $reopened = $this->service->reopen(2026, 10, 'Koreksi data mutasi', $this->admin);
        $this->assertSame('draft', $reopened->status);
        $this->assertSame(2, $reopened->revision);
        $this->assertNull($reopened->closed_at);
        $this->assertNull($reopened->closed_by);
        $this->assertNull($reopened->close_note);

        $logs = MasterAuditLog::where('entity_type', 'mpp_period')->orderBy('id')->get();
        $this->assertSame(['mpp_period.captured', 'mpp_period.closed', 'mpp_period.reopened'], $logs->pluck('action')->all());
        $this->assertSame('Periode MPP Okt 2026', $logs[2]->entity_label);
        $this->assertSame('Koreksi data mutasi', $logs[2]->reason);
        $this->assertSame(['status' => 'closed', 'revision' => 1], $logs[2]->old_values);
        $this->assertSame(['status' => 'draft', 'revision' => 2], $logs[2]->new_values);
    }

    public function test_close_requires_a_draft(): void
    {
        $this->assertRefused('Periode Okt 2026 belum memiliki draf.', fn () => $this->service->close(2026, 10, null, $this->admin));

        $this->service->capture(2026, 10, $this->admin);
        $this->service->close(2026, 10, null, $this->admin);
        $this->assertRefused('Periode Okt 2026 sudah ditutup. Buka kembali terlebih dulu.', fn () => $this->service->close(2026, 10, null, $this->admin));
    }

    public function test_reopen_requires_a_closed_period(): void
    {
        $this->assertRefused('Periode Okt 2026 belum ditutup.', fn () => $this->service->reopen(2026, 10, 'Koreksi data mutasi', $this->admin));

        $this->service->capture(2026, 10, $this->admin);
        $this->assertRefused('Periode Okt 2026 belum ditutup.', fn () => $this->service->reopen(2026, 10, 'Koreksi data mutasi', $this->admin));
    }

    public function test_reopen_requires_a_reason(): void
    {
        $this->service->capture(2026, 10, $this->admin);
        $this->service->close(2026, 10, null, $this->admin);

        foreach ([null, '', 'singkat', '   singkat   '] as $reason) {
            $this->assertRefused('Alasan membuka kembali wajib diisi minimal 10 karakter.', fn () => $this->service->reopen(2026, 10, $reason, $this->admin));
        }

        $this->assertSame('closed', $this->service->find(2026, 10)->status);
        $this->assertSame(0, MasterAuditLog::where('action', 'mpp_period.reopened')->count());
    }

    public function test_members_for_reads_snapshot_and_master(): void
    {
        $emp = $this->makeAsistenKeuangan('90000001');
        $period = $this->service->capture(2026, 10, $this->admin);

        $emp->update(['jabatan' => 'Jabatan Lain', 'golongan' => 'IVA/00']);
        $rows = $this->service->membersFor($period, 'realisasi', 'RM-3', 'KEU');
        $row = collect($rows)->firstWhere('nik', '90000001');

        $this->assertNotNull($row);
        $this->assertSame('Asisten Keuangan Uji', $row['jabatan']);
        $this->assertSame('Bagian Keuangan Uji', $row['unit_kerja']);
        $this->assertSame('Asisten 90000001', $row['nama']);
        $this->assertSame('IVA/00', $row['golongan']);
        $this->assertSame($emp->id, $row['id']);
        $this->assertSame(
            ['id', 'nik', 'nama', 'jabatan', 'unit_kerja', 'golongan', 'tanggal_penempatan', 'tanggal_mbt', 'tanggal_pensiun', 'status_karyawan'],
            array_keys($row)
        );

        $emp->delete();
        $gone = collect($this->service->membersFor($period, 'realisasi', 'RM-3', 'KEU'))->firstWhere('nik', '90000001');
        $this->assertSame('tidak lagi terdaftar', $gone['nama']);
        $this->assertSame('—', $gone['golongan']);
        $this->assertSame('Asisten Keuangan Uji', $gone['jabatan']);
    }
}
