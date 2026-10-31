<?php

namespace Tests\Feature;

use App\Exceptions\FormasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Formasi\ImpactResult;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MasterFormasiServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private MasterFormasiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->service = new MasterFormasiService;
    }

    private function unit(string $nama = 'Kebun Uji', bool $active = true): Unit
    {
        return Unit::create(['nama' => $nama, 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => $active, 'urutan' => 1]);
    }

    private function position(string $nama = 'Manajer', string $level = 'Karpim', ?string $rm = 'RM-1', bool $active = true): Position
    {
        return Position::create(['nama' => $nama, 'level' => $level, 'rm_level' => $rm, 'is_active' => $active]);
    }

    private function seat(Unit $unit, Position $position, int $kuota = 2, ?string $bidang = 'TAN'): int
    {
        return DB::table('position_unit')->insertGetId([
            'unit_id' => $unit->id,
            'position_id' => $position->id,
            'kuota' => $kuota,
            'bidang' => $bidang,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function employees(Unit $unit, Position $position, int $count): void
    {
        Employee::factory()->count($count)->create(['unit_kerja' => $unit->nama, 'jabatan' => $position->nama]);
    }

    public function test_add_seat_creates_the_seat_and_one_audit_row(): void
    {
        $unit = $this->unit();
        $position = $this->position();

        $id = $this->service->addSeat($unit, $position, 2, 'TAN', $this->admin);

        $this->assertDatabaseHas('position_unit', ['id' => $id, 'unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => 2, 'bidang' => 'TAN']);
        $this->assertSame(1, MasterAuditLog::count());
        $log = MasterAuditLog::firstOrFail();
        $this->assertSame('seat.added', $log->action);
        $this->assertSame('seat', $log->entity_type);
        $this->assertSame($id, $log->entity_id);
        $this->assertSame('Kebun Uji — Manajer', $log->entity_label);
        $this->assertSame(['kuota' => 2, 'bidang' => 'TAN'], $log->new_values);
    }

    public function test_add_seat_requires_bidang_for_karpim_but_not_for_karpel(): void
    {
        $unit = $this->unit();

        try {
            $this->service->addSeat($unit, $this->position('Manajer', 'Karpim', 'RM-1'), 1, null, $this->admin);
            $this->fail('Bidang kursi Karpim seharusnya wajib.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('Bidang', $e->getMessage());
        }

        $id = $this->service->addSeat($unit, $this->position('Mandor Panen', 'Karpel', null), 3, null, $this->admin);
        $this->assertDatabaseHas('position_unit', ['id' => $id, 'bidang' => null]);
    }

    public function test_add_seat_rejects_invalid_bidang_and_non_positive_kuota(): void
    {
        $unit = $this->unit();
        $position = $this->position();

        foreach ([[1, 'XYZ'], [0, 'TAN']] as [$kuota, $bidang]) {
            try {
                $this->service->addSeat($unit, $position, $kuota, $bidang, $this->admin);
                $this->fail("Kuota {$kuota} / bidang {$bidang} seharusnya ditolak.");
            } catch (FormasiRuleViolationException) {
                $this->assertSame(0, DB::table('position_unit')->count());
            }
        }
    }

    public function test_add_seat_rejects_a_duplicate_pair_with_a_clear_message(): void
    {
        $unit = $this->unit();
        $position = $this->position();
        $this->service->addSeat($unit, $position, 1, 'TAN', $this->admin);

        try {
            $this->service->addSeat($unit, $position, 1, 'TAN', $this->admin);
            $this->fail('Kursi ganda seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('sudah memiliki', $e->getMessage());
        }

        $this->assertSame(1, DB::table('position_unit')->count());
    }

    public function test_add_seat_rejects_inactive_unit_or_jabatan(): void
    {
        $this->expectException(FormasiRuleViolationException::class);
        $this->service->addSeat($this->unit('Kebun Nonaktif', false), $this->position(), 1, 'TAN', $this->admin);
    }

    public function test_add_seat_rejects_inactive_jabatan(): void
    {
        $this->expectException(FormasiRuleViolationException::class);
        $this->service->addSeat($this->unit(), $this->position('Manajer Lama', 'Karpim', 'RM-1', false), 1, 'TAN', $this->admin);
    }

    public function test_update_seat_below_filled_is_allowed_and_flagged(): void
    {
        $unit = $this->unit();
        $position = $this->position('Asisten Afdeling');
        $id = $this->seat($unit, $position, 3);
        $this->employees($unit, $position, 3);

        $preview = $this->service->previewImpact('seat.update', ['seat_id' => $id, 'kuota' => 1]);
        $this->assertFalse($preview->blocked);
        $this->assertStringContainsString('melebihi kuota', implode(' ', $preview->warnings));

        $this->service->updateSeat($id, 1, 'TAN', $this->admin);

        $this->assertDatabaseHas('position_unit', ['id' => $id, 'kuota' => 1]);
        $this->assertSame('seat.updated', MasterAuditLog::firstOrFail()->action);
    }

    public function test_update_seat_with_no_change_writes_no_audit_row(): void
    {
        $id = $this->seat($this->unit(), $this->position(), 2, 'TAN');

        $this->service->updateSeat($id, 2, 'TAN', $this->admin);

        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_remove_seat_with_employees_is_blocked_with_a_count(): void
    {
        $unit = $this->unit();
        $position = $this->position('Asisten Afdeling');
        $id = $this->seat($unit, $position);
        $this->employees($unit, $position, 2);

        try {
            $this->service->removeSeat($id, null, $this->admin);
            $this->fail('Menghapus kursi berpenghuni seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('2', $e->getMessage());
            $this->assertStringContainsString('Mutasi', $e->getMessage());
        }

        $this->assertDatabaseHas('position_unit', ['id' => $id]);
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_remove_empty_seat_requires_a_reason_and_writes_an_audit_row(): void
    {
        $id = $this->seat($this->unit(), $this->position());

        try {
            $this->service->removeSeat($id, null, $this->admin);
            $this->fail('Alasan seharusnya wajib.');
        } catch (FormasiRuleViolationException) {
            $this->assertDatabaseHas('position_unit', ['id' => $id]);
        }

        $this->service->removeSeat($id, 'Kursi dihapus karena restrukturisasi', $this->admin);

        $this->assertDatabaseMissing('position_unit', ['id' => $id]);
        $log = MasterAuditLog::firstOrFail();
        $this->assertSame('seat.removed', $log->action);
        $this->assertSame('Kursi dihapus karena restrukturisasi', $log->reason);
    }

    public function test_whitespace_only_reason_counts_as_missing(): void
    {
        $id = $this->seat($this->unit(), $this->position());

        $this->expectException(FormasiRuleViolationException::class);
        $this->service->removeSeat($id, '          ', $this->admin);
    }

    private function history(string $unitNama, string $jabatan): void
    {
        $employee = Employee::factory()->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan' => $jabatan,
            'unit_kerja' => $unitNama,
            'tmt_awal' => '2020-01-01',
        ]);
    }

    public function test_create_unit_writes_audit_and_validates_wilayah(): void
    {
        $unit = $this->service->createUnit(['nama' => 'Kebun Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'urutan' => 5], $this->admin);

        $this->assertTrue($unit->is_active);
        $this->assertSame('unit.created', MasterAuditLog::firstOrFail()->action);

        try {
            $this->service->createUnit(['nama' => 'Kebun Salah', 'kode' => 'Kebun', 'wilayah' => 'Antah Berantah', 'urutan' => 6], $this->admin);
            $this->fail('Wilayah tidak dikenal seharusnya ditolak.');
        } catch (FormasiRuleViolationException) {
            $this->assertDatabaseMissing('units', ['nama' => 'Kebun Salah']);
        }

        $this->expectException(FormasiRuleViolationException::class);
        $this->service->createUnit(['nama' => 'Kebun Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'urutan' => 7], $this->admin);
    }

    public function test_rename_unit_updates_employees_text_but_not_job_histories(): void
    {
        $unit = $this->unit('Kebun Lama');
        $position = $this->position();
        $this->employees($unit, $position, 2);
        $this->history('Kebun Lama', 'Manajer');

        $this->service->updateUnit($unit, ['nama' => 'Kebun Nama Baru'], 'Penyesuaian nama unit resmi', $this->admin);

        $this->assertSame(2, Employee::where('unit_kerja', 'Kebun Nama Baru')->count());
        $this->assertSame(0, Employee::where('unit_kerja', 'Kebun Lama')->count());
        $this->assertDatabaseHas('job_histories', ['unit_kerja' => 'Kebun Lama']);
        $log = MasterAuditLog::firstOrFail();
        $this->assertSame('unit.renamed', $log->action);
        $this->assertSame('Penyesuaian nama unit resmi', $log->reason);
        $this->assertSame(['nama' => 'Kebun Lama'], $log->old_values);
        $this->assertSame(['nama' => 'Kebun Nama Baru'], $log->new_values);
    }

    public function test_rename_unit_to_an_existing_name_is_refused(): void
    {
        $unit = $this->unit('Kebun A');
        $this->unit('Kebun B');

        try {
            $this->service->updateUnit($unit, ['nama' => 'Kebun B'], 'Penyesuaian nama unit resmi', $this->admin);
            $this->fail('Nama unit ganda seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('sudah digunakan', $e->getMessage());
        }

        $this->assertSame('Kebun A', $unit->fresh()->nama);
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_rename_unit_requires_a_reason(): void
    {
        $unit = $this->unit('Kebun A');

        $this->expectException(FormasiRuleViolationException::class);
        $this->service->updateUnit($unit, ['nama' => 'Kebun Z'], null, $this->admin);
    }

    public function test_update_unit_without_changes_writes_no_audit_row(): void
    {
        $unit = $this->unit('Kebun A');

        $this->service->updateUnit($unit, ['nama' => 'Kebun A', 'kode' => 'Kebun', 'urutan' => 1], null, $this->admin);

        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_deactivate_unit_with_employees_is_blocked_with_a_count(): void
    {
        $unit = $this->unit();
        $this->employees($unit, $this->position(), 3);

        try {
            $this->service->setUnitActive($unit, false, 'Unit digabung ke unit lain', $this->admin);
            $this->fail('Menonaktifkan unit berpenghuni seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('3', $e->getMessage());
            $this->assertStringContainsString('Mutasi', $e->getMessage());
        }

        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_deactivate_empty_unit_requires_a_reason_and_activation_does_not(): void
    {
        $unit = $this->unit();

        try {
            $this->service->setUnitActive($unit, false, null, $this->admin);
            $this->fail('Alasan seharusnya wajib.');
        } catch (FormasiRuleViolationException) {
            $this->assertTrue($unit->fresh()->is_active);
        }

        $this->service->setUnitActive($unit, false, 'Unit digabung ke unit lain', $this->admin);
        $this->assertFalse($unit->fresh()->is_active);

        $this->service->setUnitActive($unit->fresh(), true, null, $this->admin);
        $this->assertTrue($unit->fresh()->is_active);

        $this->assertSame(['unit.deactivated', 'unit.activated'], MasterAuditLog::orderBy('id')->pluck('action')->all());
    }

    public function test_delete_unit_is_blocked_by_seats_employees_or_history(): void
    {
        $withSeat = $this->unit('Kebun Berkursi');
        $this->seat($withSeat, $this->position('Manajer Seat'));

        $withEmployee = $this->unit('Kebun Berpegawai');
        $this->employees($withEmployee, $this->position('Manajer Pegawai'), 1);

        $withHistory = $this->unit('Kebun Berriwayat');
        $this->history('Kebun Berriwayat', 'Manajer');

        foreach ([$withSeat, $withEmployee, $withHistory] as $unit) {
            try {
                $this->service->deleteUnit($unit, 'Unit dihapus dari master', $this->admin);
                $this->fail("Unit {$unit->nama} seharusnya tidak dapat dihapus.");
            } catch (FormasiRuleViolationException) {
                $this->assertDatabaseHas('units', ['id' => $unit->id]);
            }
        }

        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_delete_unused_unit_succeeds_and_audits(): void
    {
        $unit = $this->unit('Kebun Kosong');

        $this->service->deleteUnit($unit, 'Unit dihapus dari master', $this->admin);

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertSame('unit.deleted', MasterAuditLog::firstOrFail()->action);
    }

    public function test_create_position_validates_and_audits(): void
    {
        $position = $this->service->createPosition(['nama' => 'Asisten Baru', 'bidang' => 'TAN', 'level' => 'Karpim', 'rm_level' => 'RM-3'], $this->admin);

        $this->assertTrue($position->is_active);
        $this->assertSame('RM-3', $position->rm_level);
        $this->assertSame('position.created', MasterAuditLog::firstOrFail()->action);

        $karpel = $this->service->createPosition(['nama' => 'Mandor Baru', 'level' => 'Karpel', 'rm_level' => 'RM-3'], $this->admin);
        $this->assertNull($karpel->rm_level);

        try {
            $this->service->createPosition(['nama' => 'Jabatan Salah', 'level' => 'Pimpinan', 'rm_level' => 'RM-1'], $this->admin);
            $this->fail('Level tidak dikenal seharusnya ditolak.');
        } catch (FormasiRuleViolationException) {
            $this->assertDatabaseMissing('positions', ['nama' => 'Jabatan Salah']);
        }

        $this->expectException(FormasiRuleViolationException::class);
        $this->service->createPosition(['nama' => 'Asisten Baru', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'bidang' => 'TAN'], $this->admin);
    }

    public function test_rename_position_updates_employees_text_but_not_job_histories(): void
    {
        $unit = $this->unit();
        $position = $this->position('Asisten Lama');
        $this->employees($unit, $position, 2);
        $this->history('Kebun Uji', 'Asisten Lama');

        $this->service->updatePosition($position, ['nama' => 'Asisten Nama Baru'], 'Penyesuaian nomenklatur jabatan', $this->admin);

        $this->assertSame(2, Employee::where('jabatan', 'Asisten Nama Baru')->count());
        $this->assertSame(0, Employee::where('jabatan', 'Asisten Lama')->count());
        $this->assertDatabaseHas('job_histories', ['jabatan' => 'Asisten Lama']);
        $this->assertSame('position.renamed', MasterAuditLog::firstOrFail()->action);
    }

    public function test_rename_position_to_an_existing_name_is_refused(): void
    {
        $position = $this->position('Asisten A');
        $this->position('Asisten B');

        try {
            $this->service->updatePosition($position, ['nama' => 'Asisten B'], 'Penyesuaian nomenklatur jabatan', $this->admin);
            $this->fail('Nama jabatan ganda seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('sudah digunakan', $e->getMessage());
        }

        $this->assertSame('Asisten A', $position->fresh()->nama);
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_change_rm_requires_a_reason_and_warns_with_the_unit_count(): void
    {
        $position = $this->position('Asisten Afdeling', 'Karpim', 'RM-3');
        foreach (['Kebun A', 'Kebun B', 'Kebun C'] as $nama) {
            $this->seat($this->unit($nama), $position);
        }

        $preview = $this->service->previewImpact('position.rm_change', ['position_id' => $position->id, 'rm_level' => 'RM-2']);
        $this->assertFalse($preview->blocked);
        $this->assertStringContainsString('3 unit', implode(' ', $preview->warnings));

        try {
            $this->service->updatePosition($position, ['rm_level' => 'RM-2'], null, $this->admin);
            $this->fail('Alasan seharusnya wajib untuk perubahan RM.');
        } catch (FormasiRuleViolationException) {
            $this->assertSame('RM-3', $position->fresh()->rm_level);
        }

        $this->service->updatePosition($position, ['rm_level' => 'RM-2'], 'Penyesuaian eselon sesuai pedoman', $this->admin);
        $this->assertSame('RM-2', $position->fresh()->rm_level);
        $this->assertSame('position.updated', MasterAuditLog::firstOrFail()->action);
    }

    public function test_update_position_without_changes_writes_no_audit_row(): void
    {
        $position = $this->position('Asisten A', 'Karpim', 'RM-3');

        $this->service->updatePosition($position, ['nama' => 'Asisten A', 'level' => 'Karpim', 'rm_level' => 'RM-3'], null, $this->admin);

        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_deactivate_position_held_by_employees_is_blocked(): void
    {
        $position = $this->position('Asisten Afdeling');
        $this->employees($this->unit(), $position, 2);

        try {
            $this->service->setPositionActive($position, false, 'Jabatan tidak dipakai lagi', $this->admin);
            $this->fail('Menonaktifkan jabatan yang dipegang pegawai seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('2', $e->getMessage());
            $this->assertStringContainsString('Mutasi', $e->getMessage());
        }

        $this->assertTrue($position->fresh()->is_active);
    }

    public function test_deactivate_empty_position_requires_a_reason_and_audits(): void
    {
        $position = $this->position('Asisten Kosong');

        try {
            $this->service->setPositionActive($position, false, null, $this->admin);
            $this->fail('Alasan seharusnya wajib.');
        } catch (FormasiRuleViolationException) {
            $this->assertTrue($position->fresh()->is_active);
        }

        $this->service->setPositionActive($position, false, 'Jabatan tidak dipakai lagi', $this->admin);
        $this->assertFalse($position->fresh()->is_active);
        $this->assertSame('position.deactivated', MasterAuditLog::firstOrFail()->action);
    }

    public function test_delete_position_is_blocked_by_seats_employees_or_history(): void
    {
        $withSeat = $this->position('Jabatan Berkursi');
        $this->seat($this->unit('Kebun A'), $withSeat);

        $withEmployee = $this->position('Jabatan Berpegawai');
        $this->employees($this->unit('Kebun B'), $withEmployee, 1);

        $withHistory = $this->position('Jabatan Berriwayat');
        $this->history('Kebun C', 'Jabatan Berriwayat');

        foreach ([$withSeat, $withEmployee, $withHistory] as $position) {
            try {
                $this->service->deletePosition($position, 'Jabatan dihapus dari master', $this->admin);
                $this->fail("Jabatan {$position->nama} seharusnya tidak dapat dihapus.");
            } catch (FormasiRuleViolationException) {
                $this->assertDatabaseHas('positions', ['id' => $position->id]);
            }
        }

        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_delete_unused_position_succeeds_and_audits(): void
    {
        $position = $this->position('Jabatan Kosong');

        $this->service->deletePosition($position, 'Jabatan dihapus dari master', $this->admin);

        $this->assertDatabaseMissing('positions', ['id' => $position->id]);
        $this->assertSame('position.deleted', MasterAuditLog::firstOrFail()->action);
    }

    private function pending(string $unitNama, string $jabatan): JobHistory
    {
        $employee = Employee::factory()->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);

        return JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan' => $jabatan,
            'unit_kerja' => $unitNama,
            'tmt_awal' => '2030-01-01',
            'is_applied' => false,
        ]);
    }

    public function test_rename_unit_also_updates_pending_scheduled_mutations(): void
    {
        $unit = $this->unit('Kebun Lama');
        $this->history('Kebun Lama', 'Manajer');
        $pending = $this->pending('Kebun Lama', 'Manajer');

        $this->service->updateUnit($unit, ['nama' => 'Kebun Nama Baru'], 'Penyesuaian nama unit resmi', $this->admin);

        $this->assertSame('Kebun Nama Baru', $pending->fresh()->unit_kerja);
        $this->assertDatabaseHas('job_histories', ['unit_kerja' => 'Kebun Lama', 'is_applied' => true]);
    }

    public function test_rename_position_also_updates_pending_scheduled_mutations(): void
    {
        $position = $this->position('Asisten Lama');
        $this->history('Kebun Uji', 'Asisten Lama');
        $pending = $this->pending('Kebun Uji', 'Asisten Lama');

        $this->service->updatePosition($position, ['nama' => 'Asisten Nama Baru'], 'Penyesuaian nomenklatur jabatan', $this->admin);

        $this->assertSame('Asisten Nama Baru', $pending->fresh()->jabatan);
        $this->assertDatabaseHas('job_histories', ['jabatan' => 'Asisten Lama', 'is_applied' => true]);
    }

    public function test_deactivating_a_unit_is_blocked_by_a_pending_scheduled_mutation(): void
    {
        $unit = $this->unit('Kebun Terjadwal');
        $this->pending('Kebun Terjadwal', 'Manajer');

        try {
            $this->service->setUnitActive($unit, false, 'Unit digabung ke unit lain', $this->admin);
            $this->fail('Mutasi terjadwal seharusnya memblokir penonaktifan.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('mutasi terjadwal', $e->getMessage());
        }

        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_deactivating_a_position_is_blocked_by_a_pending_scheduled_mutation(): void
    {
        $position = $this->position('Asisten Terjadwal');
        $this->pending('Kebun Uji', 'Asisten Terjadwal');

        $this->expectException(FormasiRuleViolationException::class);
        $this->expectExceptionMessage('mutasi terjadwal');
        $this->service->setPositionActive($position, false, 'Jabatan tidak dipakai lagi', $this->admin);
    }

    public function test_removing_a_seat_is_blocked_by_a_pending_scheduled_mutation(): void
    {
        $unit = $this->unit();
        $position = $this->position('Asisten Terjadwal');
        $seatId = $this->seat($unit, $position);
        $this->pending('Kebun Uji', 'Asisten Terjadwal');

        try {
            $this->service->removeSeat($seatId, 'Kursi dihapus karena restrukturisasi', $this->admin);
            $this->fail('Mutasi terjadwal seharusnya memblokir penghapusan kursi.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('mutasi terjadwal', $e->getMessage());
        }

        $this->assertDatabaseHas('position_unit', ['id' => $seatId]);
    }

    public function test_concurrent_duplicate_seat_insert_gives_a_rule_message(): void
    {
        $unit = $this->unit();
        $position = $this->position();
        $this->seat($unit, $position);

        // Pratinjau lolos (balapan: kursi dibuat permintaan lain setelah pemeriksaan), insert menabrak indeks unik.
        $racing = new class extends MasterFormasiService
        {
            public function previewImpact(string $operation, array $params): ImpactResult
            {
                return ImpactResult::ok();
            }
        };

        try {
            $racing->addSeat($unit, $position, 1, 'TAN', $this->admin);
            $this->fail('Kursi ganda seharusnya ditolak.');
        } catch (FormasiRuleViolationException $e) {
            $this->assertStringContainsString('sudah memiliki', $e->getMessage());
        }

        $this->assertSame(1, DB::table('position_unit')->count());
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_kuota_above_the_maximum_is_refused(): void
    {
        $this->expectException(FormasiRuleViolationException::class);
        $this->expectExceptionMessage('maksimal');
        $this->service->addSeat($this->unit(), $this->position(), 1001, 'TAN', $this->admin);
    }

    public function test_audit_failure_rolls_back_the_change(): void
    {
        $unit = $this->unit();
        $position = $this->position();
        Schema::drop('master_audit_logs');

        try {
            $this->service->addSeat($unit, $position, 1, 'TAN', $this->admin);
            $this->fail('Kegagalan pencatatan riwayat seharusnya membatalkan perubahan.');
        } catch (\Throwable $e) {
            $this->assertNotInstanceOf(FormasiRuleViolationException::class, $e);
        }

        $this->assertSame(0, DB::table('position_unit')->count());
    }
}
