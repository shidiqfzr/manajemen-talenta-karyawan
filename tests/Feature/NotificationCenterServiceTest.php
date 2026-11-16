<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeImportLog;
use App\Models\MutasiChainProposal;
use App\Models\User;
use App\Services\NotificationCenterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUT: App\Services\NotificationCenterService (butir pemberitahuan dihitung dari data, bukan angka tetap).
 */
class NotificationCenterServiceTest extends TestCase
{
    use RefreshDatabase;

    private function keys(User $user): array
    {
        return array_column(app(NotificationCenterService::class)->itemsFor($user), 'key');
    }

    public function test_it_is_empty_when_nothing_needs_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertSame([], app(NotificationCenterService::class)->itemsFor($admin));
        $this->assertSame(0, app(NotificationCenterService::class)->totalFor($admin));
    }

    public function test_pending_imports_are_counted_and_other_statuses_are_ignored(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        EmployeeImportLog::create(['file_name' => 'a.xlsx', 'user_name' => 'X', 'status' => EmployeeImportLog::STATUS_PENDING, 'total_rows' => 1]);
        EmployeeImportLog::create(['file_name' => 'b.xlsx', 'user_name' => 'X', 'status' => EmployeeImportLog::STATUS_PENDING, 'total_rows' => 1]);
        EmployeeImportLog::create(['file_name' => 'c.xlsx', 'user_name' => 'X', 'status' => EmployeeImportLog::STATUS_COMPLETED, 'total_rows' => 1]);

        $item = app(NotificationCenterService::class)->itemsFor($admin)[0];

        $this->assertSame('import', $item['key']);
        $this->assertSame(2, $item['count']);
        $this->assertStringContainsString('status=pending', $item['url']);
    }

    public function test_pending_proposals_are_shown_only_to_users_who_may_approve_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'user']);
        MutasiChainProposal::unguard();
        MutasiChainProposal::create(['status' => MutasiChainProposal::STATUS_PENDING, 'created_by' => $admin->id, 'payload' => [], 'participant_count' => 2]);
        MutasiChainProposal::reguard();

        $this->assertContains('usulan', $this->keys($admin));
        $this->assertNotContains('usulan', $this->keys($staff));
    }

    public function test_only_employees_entering_mbt_within_the_warning_window_are_counted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Employee::factory()->create(['tanggal_mbt' => today()->addMonths(2)]);
        Employee::factory()->create(['tanggal_mbt' => today()->addMonths(12)]);
        Employee::factory()->create(['tanggal_mbt' => today()->subMonth()]);

        $mbt = collect(app(NotificationCenterService::class)->itemsFor($admin))->firstWhere('key', 'mbt');

        $this->assertSame(1, $mbt['count']);
    }
}
