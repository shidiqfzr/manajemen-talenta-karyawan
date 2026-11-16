<?php

namespace Tests\Feature;

use App\Exceptions\MutasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MutasiChainProposal;
use App\Models\MutasiChainProposalEvent;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Mutasi\ChainProposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Usulan Mutasi Berantai lifecycle (DOMAIN.md §5.2.6): drafts, four-eyes approval, fingerprint, expiry, permissions.
 */
class ChainProposalServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;

    private User $checker;

    private ChainProposalService $service;

    /** @var array<string, Employee> */
    private array $emp = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->maker = User::factory()->create(['role' => 'admin', 'name' => 'Admin Penyusun']);
        $this->checker = User::factory()->create(['role' => 'admin', 'name' => 'Admin Penyetuju']);
        $this->service = app(ChainProposalService::class);

        foreach (['Meliau', 'Belian', 'Emas'] as $name) {
            Unit::factory()->create(['nama' => "Kebun {$name}"]);
            Position::factory()->create(['nama' => "Asisten {$name}", 'rm_level' => 'RM-3']);
        }
        $this->emp['A'] = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Meliau', 'unit_kerja' => 'Kebun Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $this->emp['B'] = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Belian', 'unit_kerja' => 'Kebun Belian', 'tmt_unit_kerja' => now()->subYears(3)]);
        $this->emp['C'] = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Emas', 'unit_kerja' => 'Kebun Emas', 'tmt_unit_kerja' => now()->subYears(3)]);
    }

    /** Closed rotation A → Belian, B → Emas, C → Meliau. */
    private function cycleNodes(): array
    {
        $node = fn (Employee $e, string $kebun) => [
            'employee_nik' => $e->nik,
            'jenis_mutasi' => 'MUTASI_UNIT',
            'target_unit_kerja' => "Kebun {$kebun}",
            'target_jabatan' => "Asisten {$kebun}",
            'status_jabatan' => 'Definitif',
            // Derived fields must never be persisted
            'occupant_nama' => 'TIDAK DISIMPAN',
            'tenure_analysis' => ['is_compliant' => true],
        ];

        return [$node($this->emp['A'], 'Belian'), $node($this->emp['B'], 'Emas'), $node($this->emp['C'], 'Meliau')];
    }

    private function header(): array
    {
        return [
            'nomor_sk' => 'SK/DIR/KOLEKTIF/2026/077',
            'tanggal_sk' => now()->toDateString(),
            'tmt_awal' => now()->toDateString(),
            'catatan' => 'Rotasi asisten wilayah Kalbar',
        ];
    }

    private function submittedProposal(): MutasiChainProposal
    {
        config(['hris.mutasi_chain.require_approval' => true]);
        $proposal = $this->service->saveDraft(null, $this->header(), $this->cycleNodes(), $this->maker);
        $this->service->submit($proposal, $this->maker);

        return $proposal->refresh();
    }

    public function test_draft_keeps_only_inputs_and_encrypts_them_at_rest(): void
    {
        $proposal = $this->service->saveDraft(null, $this->header(), $this->cycleNodes(), $this->maker);

        $this->assertSame(MutasiChainProposal::STATUS_DRAFT, $proposal->status);
        $this->assertSame(3, $proposal->participant_count);
        $this->assertSame($this->maker->id, $proposal->created_by);
        $this->assertSame(ChainProposalService::INPUT_FIELDS, array_keys($proposal->payload[0]));
        $this->assertArrayNotHasKey('occupant_nama', $proposal->payload[0]);

        $raw = DB::table('mutasi_chain_proposals')->value('payload');
        $this->assertStringNotContainsString($this->emp['A']->nik, $raw, 'NIK must not be stored in plain text (UU PDP).');
        $this->assertSame([MutasiChainProposalEvent::ACTION_SAVED], $proposal->events()->pluck('action')->all());
    }

    public function test_only_the_maker_may_change_a_draft(): void
    {
        $proposal = $this->service->saveDraft(null, $this->header(), $this->cycleNodes(), $this->maker);

        $this->expectException(MutasiRuleViolationException::class);
        $this->expectExceptionMessage('Hanya admin penyusun');
        $this->service->saveDraft($proposal, $this->header(), $this->cycleNodes(), $this->checker);
    }

    public function test_submit_is_refused_when_the_approval_policy_is_off(): void
    {
        config(['hris.mutasi_chain.require_approval' => false]);
        $proposal = $this->service->saveDraft(null, $this->header(), $this->cycleNodes(), $this->maker);

        $this->expectException(MutasiRuleViolationException::class);
        $this->service->submit($proposal, $this->maker);
    }

    public function test_submit_freezes_and_fingerprints_the_proposal(): void
    {
        $proposal = $this->submittedProposal();

        $this->assertSame(MutasiChainProposal::STATUS_PENDING, $proposal->status);
        $this->assertSame(ChainProposalService::digest(ChainProposalService::headerOf($proposal), $proposal->payload), $proposal->payload_digest);
        $this->assertSame($this->maker->id, $proposal->submitted_by);
        $this->assertTrue($proposal->expires_at->isFuture());

        $this->expectException(MutasiRuleViolationException::class);
        $this->service->saveDraft($proposal, $this->header(), $this->cycleNodes(), $this->maker);
    }

    public function test_maker_cannot_approve_their_own_proposal(): void
    {
        $proposal = $this->submittedProposal();

        try {
            $this->service->approve($proposal, $this->maker, $proposal->payload);
            $this->fail('The maker must not approve their own proposal.');
        } catch (MutasiRuleViolationException $e) {
            $this->assertStringContainsString('admin lain', $e->getMessage());
        }

        $this->assertSame('Asisten Meliau', $this->emp['A']->refresh()->jabatan);
        $this->assertSame(MutasiChainProposal::STATUS_PENDING, $proposal->refresh()->status);
    }

    public function test_another_admin_approves_and_the_sk_is_issued_atomically(): void
    {
        $proposal = $this->submittedProposal();

        $result = $this->service->approve($proposal, $this->checker, $proposal->payload);

        $this->assertSame('Asisten Belian', $this->emp['A']->refresh()->jabatan);
        $this->assertSame('Asisten Emas', $this->emp['B']->refresh()->jabatan);
        $this->assertSame('Asisten Meliau', $this->emp['C']->refresh()->jabatan);

        $proposal->refresh();
        $this->assertSame(MutasiChainProposal::STATUS_EXECUTED, $proposal->status);
        $this->assertSame($result['batch_id'], $proposal->batch_id);
        $this->assertSame($this->checker->id, $proposal->decided_by);

        // Audit trail: every placement shares the batch and records the maker who prepared it
        $histories = JobHistory::where('batch_id', $result['batch_id'])->get();
        $this->assertCount(3, $histories);
        $this->assertTrue($histories->every(fn (JobHistory $h) => (int) $h->created_by === $this->maker->id));
        $this->assertSame(
            [MutasiChainProposalEvent::ACTION_SAVED, MutasiChainProposalEvent::ACTION_SUBMITTED, MutasiChainProposalEvent::ACTION_EXECUTED],
            $proposal->events()->pluck('action')->all()
        );
    }

    public function test_approval_is_refused_when_the_inputs_differ_from_what_was_submitted(): void
    {
        $proposal = $this->submittedProposal();
        $tampered = $proposal->payload;
        $tampered[0]['target_jabatan'] = 'Asisten Emas';

        $this->expectException(MutasiRuleViolationException::class);
        $this->expectExceptionMessage('berbeda dari yang diajukan');
        $this->service->approve($proposal, $this->checker, $tampered);
    }

    public function test_approval_is_refused_when_the_stored_payload_was_changed_after_submission(): void
    {
        $proposal = $this->submittedProposal();
        $changed = $proposal->payload;
        $changed[2]['target_jabatan'] = 'Asisten Belian';
        $proposal->forceFill(['payload' => $changed])->saveQuietly();

        $this->expectException(MutasiRuleViolationException::class);
        $this->service->approve($proposal->refresh(), $this->checker, $changed);
    }

    public function test_reject_requires_a_reason_and_returns_the_proposal_for_revision(): void
    {
        $proposal = $this->submittedProposal();

        try {
            $this->service->reject($proposal, $this->checker, 'kurang');
            $this->fail('A short reason must be refused.');
        } catch (MutasiRuleViolationException $e) {
            $this->assertStringContainsString('minimal', $e->getMessage());
        }

        $this->service->reject($proposal, $this->checker, 'Nomor SK belum sesuai nota dinas Direksi.');
        $proposal->refresh();
        $this->assertSame(MutasiChainProposal::STATUS_REJECTED, $proposal->status);
        $this->assertSame('Nomor SK belum sesuai nota dinas Direksi.', $proposal->decision_reason);

        // The maker revises: the proposal becomes a draft again and can be resubmitted
        $revised = $this->service->saveDraft($proposal, ['nomor_sk' => 'SK/DIR/KOLEKTIF/2026/078'] + $this->header(), $this->cycleNodes(), $this->maker);
        $this->assertSame(MutasiChainProposal::STATUS_DRAFT, $revised->status);
        $this->service->submit($revised, $this->maker);
        $this->assertSame(MutasiChainProposal::STATUS_PENDING, $revised->refresh()->status);
        $this->assertNull($revised->decision_reason);
    }

    public function test_only_the_submitter_may_withdraw(): void
    {
        $proposal = $this->submittedProposal();

        try {
            $this->service->withdraw($proposal, $this->checker);
            $this->fail('Only the submitter may withdraw.');
        } catch (MutasiRuleViolationException) {
            // expected
        }

        $this->service->withdraw($proposal, $this->maker);
        $this->assertSame(MutasiChainProposal::STATUS_DRAFT, $proposal->refresh()->status);
        $this->assertNull($proposal->payload_digest);
    }

    public function test_stale_proposals_expire_and_can_no_longer_be_approved(): void
    {
        $proposal = $this->submittedProposal();
        $proposal->forceFill(['expires_at' => now()->subMinute()])->save();

        try {
            $this->service->approve($proposal, $this->checker, $proposal->payload);
            $this->fail('An expired proposal must not be approved.');
        } catch (MutasiRuleViolationException $e) {
            $this->assertStringContainsString('kedaluwarsa', $e->getMessage());
        }
        $this->assertSame(MutasiChainProposal::STATUS_EXPIRED, $proposal->refresh()->status);

        $other = $this->submittedProposal();
        $other->forceFill(['expires_at' => now()->subDay()])->save();
        $this->assertSame(1, $this->service->expireStale());
        $this->assertSame(MutasiChainProposal::STATUS_EXPIRED, $other->refresh()->status);
    }

    public function test_only_never_submitted_drafts_can_be_discarded(): void
    {
        $draft = $this->service->saveDraft(null, $this->header(), $this->cycleNodes(), $this->maker);
        $this->service->discard($draft, $this->maker);
        $this->assertDatabaseMissing('mutasi_chain_proposals', ['id' => $draft->id]);

        $proposal = $this->submittedProposal();
        $this->service->withdraw($proposal, $this->maker);

        $this->expectException(MutasiRuleViolationException::class);
        $this->expectExceptionMessage('jejak audit');
        $this->service->discard($proposal->refresh(), $this->maker);
    }

    public function test_direct_execution_closes_the_draft_and_is_refused_under_the_approval_policy(): void
    {
        config(['hris.mutasi_chain.require_approval' => false]);
        $draft = $this->service->saveDraft(null, $this->header(), $this->cycleNodes(), $this->maker);

        $result = $this->service->executeDirect($draft, $this->maker, $this->header() + ['created_by' => $this->maker->id], $this->cycleNodes());

        $draft->refresh();
        $this->assertSame(MutasiChainProposal::STATUS_EXECUTED, $draft->status);
        $this->assertSame($result['batch_id'], $draft->batch_id);

        config(['hris.mutasi_chain.require_approval' => true]);
        $this->expectException(MutasiRuleViolationException::class);
        $this->service->executeDirect(null, $this->maker, $this->header(), $this->cycleNodes());
    }

    public function test_decision_rights_follow_the_configured_role_permissions(): void
    {
        // A future role that may prepare but not approve
        config(['hris.permissions' => ['mutasi.prepare' => ['admin', 'hr_staff']] + config('hris.permissions')]);
        $staff = User::factory()->create(['role' => 'hr_staff']);
        $proposal = $this->submittedProposal();

        $this->assertFalse(ChainProposalService::canDecide($proposal, $staff));
        $this->assertTrue(ChainProposalService::canDecide($proposal, $this->checker));
        $this->assertFalse(ChainProposalService::canDecide($proposal, $this->maker));

        $this->expectException(MutasiRuleViolationException::class);
        $this->expectExceptionMessage('tidak berwenang');
        $this->service->reject($proposal, $staff, 'Alasan pengembalian yang cukup panjang.');
    }
}
