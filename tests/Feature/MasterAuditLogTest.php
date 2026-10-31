<?php

namespace Tests\Feature;

use App\Models\MasterAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class MasterAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function log(): MasterAuditLog
    {
        return MasterAuditLog::record(
            $this->admin,
            'seat.added',
            'seat',
            7,
            'Kebun Tajati — Manajer',
            null,
            ['kuota' => 1],
            null
        );
    }

    public function test_record_stores_the_audit_row_with_a_user_snapshot(): void
    {
        $log = $this->log()->fresh();

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($this->admin->name, $log->user_name);
        $this->assertSame('seat.added', $log->action);
        $this->assertSame('seat', $log->entity_type);
        $this->assertSame(7, $log->entity_id);
        $this->assertSame('Kebun Tajati — Manajer', $log->entity_label);
        $this->assertSame(['kuota' => 1], $log->new_values);
        $this->assertNull($log->old_values);
    }

    public function test_audit_rows_cannot_be_updated(): void
    {
        $log = $this->log();

        $this->expectException(LogicException::class);
        $log->update(['reason' => 'x']);
    }

    public function test_audit_rows_cannot_be_deleted(): void
    {
        $log = $this->log();

        try {
            $log->delete();
            $this->fail('Menghapus riwayat seharusnya ditolak.');
        } catch (LogicException) {
            $this->assertDatabaseHas('master_audit_logs', ['id' => $log->id]);
        }
    }

    public function test_deleting_the_user_keeps_the_row_with_its_name_snapshot(): void
    {
        $log = $this->log();
        $name = $this->admin->name;

        $this->admin->delete();

        $log = $log->fresh();
        $this->assertNull($log->user_id);
        $this->assertSame($name, $log->user_name);
    }
}
