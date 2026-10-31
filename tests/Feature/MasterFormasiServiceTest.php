<?php

namespace Tests\Feature;

use App\Exceptions\FormasiRuleViolationException;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
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
