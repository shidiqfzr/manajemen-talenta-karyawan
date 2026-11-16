<?php

namespace App\Services\Mutasi;

use App\Exceptions\MutasiRuleViolationException;
use App\Models\MutasiChainProposal;
use App\Models\MutasiChainProposalEvent;
use App\Models\User;
use App\Services\MutasiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lifecycle of a Usulan Mutasi Berantai (DOMAIN.md §5.2.6): saved drafts and the four-eyes approval policy.
 *
 *  - Only the admin's inputs are stored (INPUT_FIELDS); occupancy, tenure and promotion analysis are always
 *    recomputed against current data, so an old draft can never carry a stale verdict into an SK.
 *  - What is approved is what was submitted: the inputs are fingerprinted (SHA-256) on submission and the
 *    fingerprint must still match when the checker approves.
 *  - The checker must be a different user from the maker. Execution itself reuses
 *    MutasiService::processChainMutasi, which re-validates the chain inside its own transaction.
 *
 * Every message is business language, safe to show to the administrator (MutasiRuleViolationException).
 */
class ChainProposalService
{
    /** Per-participant inputs that are persisted; everything else on a node is derived. */
    public const INPUT_FIELDS = [
        'employee_nik', 'jenis_mutasi', 'target_unit_kerja', 'target_jabatan', 'status_jabatan',
        'tmt_akhir_estimasi', 'new_golongan', 'new_job_grade', 'catatan',
        'alasan_dispensasi_direksi', 'tanggal_dispensasi_direksi',
    ];

    /** Minimum length of a rejection reason. */
    public const MIN_REASON_LENGTH = 10;

    private const DISK = 'local';

    private const FILE_DIR = 'dokumen_sk/usulan';

    private const EXPIRED_MESSAGE = 'Usulan ini sudah melewati batas waktu persetujuan dan kedaluwarsa. Penyusun perlu membuat usulan baru.';

    public function __construct(private readonly MutasiService $mutasi) {}

    public static function requiresApproval(): bool
    {
        return (bool) config('hris.mutasi_chain.require_approval', false);
    }

    /**
     * Keep only the persisted inputs of each node, as strings, in slot order.
     *
     * @return list<array<string, string>>
     */
    public static function extractInputs(array $nodes): array
    {
        return array_values(array_map(function (array $node) {
            $input = [];
            foreach (self::INPUT_FIELDS as $field) {
                $input[$field] = trim((string) ($node[$field] ?? ''));
            }

            return $input;
        }, $nodes));
    }

    /**
     * Fingerprint of the SK header and participant inputs that an approval is given for.
     *
     * @param  array<string, mixed>  $header  nomor_sk, tanggal_sk, tmt_awal, catatan, file_sk
     */
    public static function digest(array $header, array $inputs): string
    {
        $canonical = [
            'nomor_sk' => trim((string) ($header['nomor_sk'] ?? '')),
            'tanggal_sk' => (string) ($header['tanggal_sk'] ?? ''),
            'tmt_awal' => (string) ($header['tmt_awal'] ?? ''),
            'catatan' => trim((string) ($header['catatan'] ?? '')),
            'file_sk' => (string) ($header['file_sk'] ?? ''),
            'nodes' => self::extractInputs($inputs),
        ];

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * SK header of a stored proposal, in the shape used by digest() and processChainMutasi().
     *
     * @return array{nomor_sk: string, tanggal_sk: string, tmt_awal: string, catatan: string, file_sk: ?string}
     */
    public static function headerOf(MutasiChainProposal $proposal): array
    {
        return [
            'nomor_sk' => (string) $proposal->nomor_sk,
            'tanggal_sk' => (string) $proposal->tanggal_sk?->toDateString(),
            'tmt_awal' => (string) $proposal->tmt_awal?->toDateString(),
            'catatan' => (string) $proposal->catatan,
            'file_sk' => $proposal->file_sk,
        ];
    }

    /**
     * Create or update a draft. A rejected proposal becomes a draft again once revised.
     * A newly uploaded SK file replaces the previous one (the old file is deleted).
     *
     * @param  array<string, mixed>  $header  nomor_sk, tanggal_sk, tmt_awal, catatan, and optionally file_sk (new path)
     */
    public function saveDraft(?MutasiChainProposal $proposal, array $header, array $nodes, User $user): MutasiChainProposal
    {
        $previousFile = $proposal?->file_sk;
        $newFile = $header['file_sk'] ?? null;

        try {
            $saved = $this->persistDraft($proposal, $header, $nodes, $user, $newFile);
        } catch (Throwable $e) {
            // The upload was stored before this call; a draft that was not saved must not keep it (UU PDP retention).
            $this->deleteReplacedUpload($newFile, $previousFile);
            throw $e;
        }

        if ($newFile && $previousFile && $previousFile !== $newFile) {
            $this->deleteFile($previousFile);
        }

        return $saved;
    }

    private function persistDraft(?MutasiChainProposal $proposal, array $header, array $nodes, User $user, ?string $newFile): MutasiChainProposal
    {
        if ($proposal) {
            $this->assertEditableBy($proposal, $user);
        }

        $inputs = self::extractInputs($nodes);

        return DB::transaction(function () use ($proposal, $header, $inputs, $user, $newFile) {
            $proposal ??= new MutasiChainProposal([
                'created_by' => $user->id,
                'created_by_name' => $user->name,
            ]);

            $proposal->fill([
                'status' => MutasiChainProposal::STATUS_DRAFT,
                'nomor_sk' => self::nullIfBlank($header['nomor_sk'] ?? null),
                'tanggal_sk' => self::nullIfBlank($header['tanggal_sk'] ?? null),
                'tmt_awal' => self::nullIfBlank($header['tmt_awal'] ?? null),
                'catatan' => self::nullIfBlank($header['catatan'] ?? null),
                'payload' => $inputs,
                'participant_count' => self::countParticipants($inputs),
                'payload_digest' => null,
            ]);
            if ($newFile) {
                $proposal->file_sk = $newFile;
            }
            $proposal->save();

            $this->log($proposal, MutasiChainProposalEvent::ACTION_SAVED, $user);

            return $proposal;
        });
    }

    /** Store an uploaded SK file for a proposal on the private disk (SECURITY.md §4.4). */
    public function storeFile(UploadedFile $upload): string
    {
        return $upload->store(self::FILE_DIR, self::DISK);
    }

    /**
     * Submit a draft for approval. The chain must already pass review (the caller runs the same checks as
     * direct execution); from here on the inputs are frozen and fingerprinted.
     */
    public function submit(MutasiChainProposal $proposal, User $user): void
    {
        if (! self::requiresApproval()) {
            throw new MutasiRuleViolationException('Persetujuan berjenjang tidak aktif. SK dapat langsung diterbitkan oleh admin yang berwenang.');
        }

        DB::transaction(function () use ($proposal, $user) {
            $proposal = $this->lock($proposal);
            $this->assertEditableBy($proposal, $user);

            $proposal->update([
                'status' => MutasiChainProposal::STATUS_PENDING,
                'payload_digest' => self::digest(self::headerOf($proposal), $proposal->payload),
                'submitted_by' => $user->id,
                'submitted_by_name' => $user->name,
                'submitted_at' => now(),
                'expires_at' => now()->addDays(max(1, (int) config('hris.mutasi_chain.approval_ttl_days', 7))),
                'decided_by' => null,
                'decided_by_name' => null,
                'decided_at' => null,
                'decision_reason' => null,
            ]);

            $this->log($proposal, MutasiChainProposalEvent::ACTION_SUBMITTED, $user);
        });

        Log::info('HRIS Usulan Mutasi Berantai diajukan', ['proposal_id' => $proposal->id, 'maker_id' => $user->id]);
    }

    /** The maker pulls a pending proposal back to draft to change it. */
    public function withdraw(MutasiChainProposal $proposal, User $user): void
    {
        DB::transaction(function () use ($proposal, $user) {
            $proposal = $this->lock($proposal);
            $this->assertPending($proposal);

            if ((int) $proposal->submitted_by !== (int) $user->id) {
                throw new MutasiRuleViolationException('Hanya admin pengaju yang dapat menarik usulan ini.');
            }

            $proposal->update([
                'status' => MutasiChainProposal::STATUS_DRAFT,
                'payload_digest' => null,
                'expires_at' => null,
            ]);

            $this->log($proposal, MutasiChainProposalEvent::ACTION_WITHDRAWN, $user);
        });
    }

    /** The checker returns the proposal to the maker with a reason. */
    public function reject(MutasiChainProposal $proposal, User $user, string $reason): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new MutasiRuleViolationException('Alasan pengembalian wajib diisi (minimal '.self::MIN_REASON_LENGTH.' karakter).');
        }

        $this->expireIfDue($proposal);

        DB::transaction(function () use ($proposal, $user, $reason) {
            $proposal = $this->lock($proposal);
            $this->assertPending($proposal);
            $this->assertChecker($proposal, $user);

            $proposal->update([
                'status' => MutasiChainProposal::STATUS_REJECTED,
                'payload_digest' => null,
                'expires_at' => null,
                'decided_by' => $user->id,
                'decided_by_name' => $user->name,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);

            $this->log($proposal, MutasiChainProposalEvent::ACTION_REJECTED, $user, $reason);
        });

        Log::info('HRIS Usulan Mutasi Berantai dikembalikan', ['proposal_id' => $proposal->id, 'checker_id' => $user->id]);
    }

    /**
     * Approve and issue the SK. $nodes must be rebuilt by the caller from this proposal's stored inputs
     * (so derived fields such as the dispensation flags are recomputed on the server); they must match the
     * submitted fingerprint exactly.
     *
     * @return array<string, mixed> result of MutasiService::processChainMutasi
     */
    public function approve(MutasiChainProposal $proposal, User $user, array $nodes): array
    {
        $this->expireIfDue($proposal);

        $result = DB::transaction(function () use ($proposal, $user, $nodes) {
            $proposal = $this->lock($proposal);
            $this->assertPending($proposal);
            $this->assertChecker($proposal, $user);

            $header = self::headerOf($proposal);
            $stored = self::digest($header, $proposal->payload);
            if (! hash_equals((string) $proposal->payload_digest, $stored)
                || ! hash_equals($stored, self::digest($header, $nodes))) {
                throw new MutasiRuleViolationException('Isi usulan berbeda dari yang diajukan. Muat ulang halaman dan tinjau kembali usulan ini.');
            }

            $result = $this->mutasi->processChainMutasi($header + [
                // The maker prepared the SK; the approver is recorded on the proposal, linked by batch_id
                'created_by' => $proposal->submitted_by ?? $user->id,
            ], $nodes);

            $this->markExecuted($proposal, $user, $result['batch_id']);

            return $result;
        });

        Log::info('HRIS Usulan Mutasi Berantai disetujui', [
            'proposal_id' => $proposal->id, 'checker_id' => $user->id, 'batch_id' => $result['batch_id'],
        ]);

        return $result;
    }

    /**
     * Direct execution without the approval policy: issue the SK and, when it came from a saved draft,
     * close that draft as executed in the same transaction.
     *
     * @param  array<string, mixed>  $header  nomor_sk, tanggal_sk, tmt_awal, catatan, file_sk, created_by
     * @return array<string, mixed> result of MutasiService::processChainMutasi
     */
    public function executeDirect(?MutasiChainProposal $proposal, User $user, array $header, array $nodes): array
    {
        $previousFile = $proposal?->file_sk;
        $newFile = $header['file_sk'] ?? null;

        try {
            $result = $this->runDirect($proposal, $user, $header, $nodes);
        } catch (Throwable $e) {
            // The SK file was uploaded before the SK was issued; if issuing failed it has no owner and must not stay.
            $this->deleteReplacedUpload($newFile, $previousFile);
            throw $e;
        }

        // The draft's earlier upload was replaced by the one that is now on the issued SK.
        if ($newFile && $previousFile && $previousFile !== $newFile) {
            $this->deleteFile($previousFile);
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function runDirect(?MutasiChainProposal $proposal, User $user, array $header, array $nodes): array
    {
        if (self::requiresApproval()) {
            throw new MutasiRuleViolationException('Kebijakan persetujuan berjenjang aktif: ajukan usulan ini untuk disetujui admin lain.');
        }

        return DB::transaction(function () use ($proposal, $user, $header, $nodes) {
            if ($proposal) {
                $proposal = $this->lock($proposal);
                $this->assertEditableBy($proposal, $user);
            }

            $result = $this->mutasi->processChainMutasi($header, $nodes);

            if ($proposal) {
                $proposal->fill([
                    'nomor_sk' => self::nullIfBlank($header['nomor_sk'] ?? null),
                    'tanggal_sk' => self::nullIfBlank($header['tanggal_sk'] ?? null),
                    'tmt_awal' => self::nullIfBlank($header['tmt_awal'] ?? null),
                    'catatan' => self::nullIfBlank($header['catatan'] ?? null),
                    'file_sk' => $header['file_sk'] ?? $proposal->file_sk,
                    'payload' => self::extractInputs($nodes),
                ]);
                $this->markExecuted($proposal, $user, $result['batch_id']);
            }

            return $result;
        });
    }

    /** Delete a draft that was never submitted. Submitted proposals stay for the audit trail. */
    public function discard(MutasiChainProposal $proposal, User $user): void
    {
        $this->assertEditableBy($proposal, $user);
        if ($proposal->submitted_at !== null) {
            throw new MutasiRuleViolationException('Usulan yang pernah diajukan tidak dapat dihapus karena menjadi bagian dari jejak audit.');
        }

        $file = $proposal->file_sk;
        $proposal->delete();
        $this->deleteFile($file);
    }

    /** Mark every pending proposal past its deadline as expired. Returns how many. */
    public function expireStale(): int
    {
        $expired = 0;
        MutasiChainProposal::where('status', MutasiChainProposal::STATUS_PENDING)
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (MutasiChainProposal $proposal) use (&$expired): void {
                if ($this->expire($proposal)) {
                    $expired++;
                }
            });

        return $expired;
    }

    /** Can this user decide (approve / reject) this proposal? */
    public static function canDecide(MutasiChainProposal $proposal, ?User $user): bool
    {
        return $user !== null
            && $proposal->isPending()
            && $user->can('mutasi.approve')
            && (int) $proposal->submitted_by !== (int) $user->id;
    }

    // -------------------------------------------------------------------------

    private function markExecuted(MutasiChainProposal $proposal, User $user, string $batchId): void
    {
        $proposal->fill([
            'status' => MutasiChainProposal::STATUS_EXECUTED,
            'batch_id' => $batchId,
            'decided_by' => $user->id,
            'decided_by_name' => $user->name,
            'decided_at' => now(),
            'expires_at' => null,
            'participant_count' => self::countParticipants($proposal->payload ?? []),
        ])->save();

        $this->log($proposal, MutasiChainProposalEvent::ACTION_EXECUTED, $user);
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /** Delete an upload that did not become part of the proposal; the file the proposal already had is never touched. */
    private function deleteReplacedUpload(?string $uploaded, ?string $existing): void
    {
        if ($uploaded && $uploaded !== $existing) {
            $this->deleteFile($uploaded);
        }
    }

    /**
     * Participants are the slots that carry an employee; padded empty slots are not people.
     *
     * @param  list<array<string, string>>  $inputs
     */
    private static function countParticipants(array $inputs): int
    {
        return count(array_filter($inputs, fn (array $input) => trim((string) ($input['employee_nik'] ?? '')) !== ''));
    }

    /**
     * Expire a proposal only if it is still pending and overdue. The row is locked and re-read inside the
     * transaction, so a decision that landed after the caller selected the proposal is never overwritten.
     */
    private function expire(MutasiChainProposal $proposal): bool
    {
        return DB::transaction(function () use ($proposal): bool {
            $locked = $this->lock($proposal);
            if (! $this->isOverdue($locked)) {
                return false;
            }

            $locked->update(['status' => MutasiChainProposal::STATUS_EXPIRED, 'payload_digest' => null]);
            $this->log($locked, MutasiChainProposalEvent::ACTION_EXPIRED, null);

            return true;
        });
    }

    private function lock(MutasiChainProposal $proposal): MutasiChainProposal
    {
        return MutasiChainProposal::whereKey($proposal->getKey())->lockForUpdate()->firstOrFail();
    }

    private function assertEditableBy(MutasiChainProposal $proposal, User $user): void
    {
        if (! $proposal->isEditable()) {
            throw new MutasiRuleViolationException("Usulan berstatus \"{$proposal->statusLabel()}\" tidak dapat diubah.");
        }
        if ((int) $proposal->created_by !== (int) $user->id) {
            throw new MutasiRuleViolationException('Hanya admin penyusun yang dapat mengubah usulan ini.');
        }
    }

    /**
     * Mark a pending proposal past its deadline as expired and refuse the decision. Runs before the decision
     * transaction, because an exception inside it would roll the expiry back.
     */
    private function expireIfDue(MutasiChainProposal $proposal): void
    {
        if (! $this->isOverdue($proposal->fresh() ?? $proposal) || ! $this->expire($proposal)) {
            return;
        }

        throw new MutasiRuleViolationException(self::EXPIRED_MESSAGE);
    }

    private function isOverdue(MutasiChainProposal $proposal): bool
    {
        return $proposal->isPending() && $proposal->expires_at && $proposal->expires_at->isPast();
    }

    private function assertPending(MutasiChainProposal $proposal): void
    {
        if ($this->isOverdue($proposal)) {
            throw new MutasiRuleViolationException(self::EXPIRED_MESSAGE);
        }

        if (! $proposal->isPending()) {
            throw new MutasiRuleViolationException("Usulan berstatus \"{$proposal->statusLabel()}\", bukan menunggu persetujuan.");
        }
    }

    private function assertChecker(MutasiChainProposal $proposal, User $user): void
    {
        if (! $user->can('mutasi.approve')) {
            throw new MutasiRuleViolationException('Akun Anda tidak berwenang memutuskan usulan mutasi.');
        }
        if ((int) $proposal->submitted_by === (int) $user->id) {
            throw new MutasiRuleViolationException('Usulan harus diputuskan oleh admin lain, bukan admin pengaju.');
        }
    }

    private function log(MutasiChainProposal $proposal, string $action, ?User $user, ?string $reason = null): void
    {
        MutasiChainProposalEvent::create([
            'proposal_id' => $proposal->id,
            'action' => $action,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem',
            'reason' => $reason,
        ]);
    }

    private static function nullIfBlank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return blank($value) ? null : (string) $value;
    }
}
