<?php

namespace Tests\Feature;

use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Tests\TestCase;

class StructureDecreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_number_removes_spaces_around_slashes(): void
    {
        $this->assertSame('DPSB/SKPTS/352/IX/2026', StructureDecree::normalizeNumber(' DPSB/SKPTS/ 352 /IX/2026 '));
    }

    public function test_for_decree_links_master_audit_entries(): void
    {
        $decree = StructureDecree::create([
            'sk_number' => 'DPSB/SKPTS/352/IX/2026',
            'title' => 'Perubahan Struktur Organisasi Region Office',
            'sk_date' => '2026-09-01',
            'effective_date' => '2026-09-01',
            'status' => StructureDecree::STATUS_DRAFT,
            'source_path' => 'structure-decrees/1/source.xlsx',
            'source_sha256' => str_repeat('a', 64),
        ]);

        $inside = MasterAuditLog::forDecree($decree->id, fn () => MasterAuditLog::record(null, 'unit.updated', 'unit', 1, 'Unit A'));
        $outside = MasterAuditLog::record(null, 'unit.updated', 'unit', 1, 'Unit A');

        $this->assertSame($decree->id, $inside->decree_id);
        $this->assertNull($outside->decree_id);
    }

    public function test_for_decree_clears_the_ambient_id_even_when_the_callback_throws(): void
    {
        try {
            MasterAuditLog::forDecree(99, fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull(MasterAuditLog::record(null, 'unit.updated', 'unit', 1, 'Unit A')->decree_id);
    }

    public function test_struktur_permissions_belong_to_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);

        $this->assertTrue(Gate::forUser($admin)->allows('struktur.prepare'));
        $this->assertTrue(Gate::forUser($admin)->allows('struktur.approve'));
        $this->assertFalse(Gate::forUser($user)->allows('struktur.prepare'));
        $this->assertFalse(Gate::forUser($user)->allows('struktur.approve'));
    }

    public function test_open_statuses_keep_the_slot_until_the_decree_is_applied_rejected_or_cancelled(): void
    {
        $this->assertSame(
            [StructureDecree::STATUS_DRAFT, StructureDecree::STATUS_SUBMITTED, StructureDecree::STATUS_APPROVED, StructureDecree::STATUS_STALE, StructureDecree::STATUS_FAILED],
            StructureDecree::OPEN_STATUSES
        );
        $this->assertArrayHasKey(StructureDecree::STATUS_FAILED, StructureDecree::STATUS_LABELS);
    }
}
