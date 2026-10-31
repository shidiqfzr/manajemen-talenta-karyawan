<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_history_page_is_admin_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.riwayat.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'))->assertOk()->assertSee('Riwayat Perubahan Master');
    }

    public function test_history_lists_newest_first_and_paginates_by_25(): void
    {
        foreach (range(1, 30) as $i) {
            MasterAuditLog::record($this->admin, 'seat.added', 'seat', $i, "Unit Uji — Jabatan {$i}", null, ['kuota' => 1]);
        }

        $first = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'));
        $first->assertSee('Jabatan 30')->assertSee('Jabatan 6')->assertDontSee('Jabatan 5');

        $second = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['page' => 2]));
        $second->assertSee('Jabatan 5')->assertDontSee('Jabatan 30');
    }

    public function test_history_filters_by_entity_type_user_and_date_range(): void
    {
        $other = User::factory()->create(['role' => 'admin', 'name' => 'Admin Lain']);
        MasterAuditLog::record($this->admin, 'seat.added', 'seat', 1, 'Unit A — Seat Satu');
        MasterAuditLog::record($this->admin, 'unit.created', 'unit', 2, 'Unit Baru Dua');
        MasterAuditLog::record($other, 'position.created', 'position', 3, 'Jabatan Tiga');

        $old = MasterAuditLog::record($this->admin, 'unit.deleted', 'unit', 4, 'Unit Lama Empat');
        DB::table('master_audit_logs')->where('id', $old->id)->update(['created_at' => '2020-01-15 10:00:00']);

        $byType = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['entity_type' => 'seat']));
        $byType->assertSee('Seat Satu')->assertDontSee('Unit Baru Dua')->assertDontSee('Jabatan Tiga');

        $byUser = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['user_id' => $other->id]));
        $byUser->assertSee('Jabatan Tiga')->assertDontSee('Seat Satu');

        $byDate = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index', ['from' => '2020-01-01', 'to' => '2020-01-31']));
        $byDate->assertSee('Unit Lama Empat')->assertDontSee('Seat Satu');
    }

    public function test_history_shows_who_what_and_reason_but_no_employee_data(): void
    {
        $employee = Employee::factory()->create(['nama' => 'Pegawai Rahasia Uji']);
        MasterAuditLog::record(
            $this->admin, 'seat.removed', 'seat', 9, 'Kebun Uji — Manajer',
            ['kuota' => 1, 'bidang' => 'TAN', 'filled' => 0], null, 'Kursi dihapus karena restrukturisasi'
        );

        $response = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'));

        $response->assertSee($this->admin->name)
            ->assertSee('Kursi dihapus')
            ->assertSee('Kebun Uji — Manajer')
            ->assertSee('Kursi dihapus karena restrukturisasi')
            ->assertDontSee($employee->nama)
            ->assertDontSee($employee->nik);
    }

    public function test_history_page_renders_old_and_new_values_in_readable_form(): void
    {
        MasterAuditLog::record($this->admin, 'seat.updated', 'seat', 5, 'Kebun Uji — Asisten', ['kuota' => 1, 'bidang' => null], ['kuota' => 3, 'bidang' => 'TAN']);
        MasterAuditLog::record($this->admin, 'unit.deactivated', 'unit', 6, 'Kebun Mati', ['is_active' => true], ['is_active' => false]);

        $response = $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'));

        $response->assertSee('kuota: 1 → 3')->assertSee('bidang: - → TAN')->assertSee('is_active: ya → tidak');
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
