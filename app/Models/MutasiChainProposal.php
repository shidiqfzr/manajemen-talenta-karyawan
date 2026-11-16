<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Usulan Mutasi Berantai: a saved chain draft and its approval lifecycle (DOMAIN.md §5.2).
 *
 * Status flow: draft → pending_approval → executed, or pending_approval → rejected | expired, and
 * pending_approval → draft when the maker withdraws. A rejected proposal can be revised (back to draft).
 * Without the approval policy a draft goes straight to executed.
 */
class MutasiChainProposal extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_EXECUTED = 'executed';

    /** Statuses whose inputs the maker may still change. */
    public const EDITABLE_STATUSES = [self::STATUS_DRAFT, self::STATUS_REJECTED];

    /** Status => label shown to admins. */
    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_PENDING => 'Menunggu Persetujuan',
        self::STATUS_REJECTED => 'Dikembalikan untuk Revisi',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_EXECUTED => 'SK Diterbitkan',
    ];

    protected $fillable = [
        'status', 'nomor_sk', 'tanggal_sk', 'tmt_awal', 'catatan', 'file_sk', 'payload', 'participant_count',
        'payload_digest', 'created_by', 'created_by_name', 'submitted_by', 'submitted_by_name', 'submitted_at',
        'expires_at', 'decided_by', 'decided_by_name', 'decided_at', 'decision_reason', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            // Participant NIKs and dispensation references are personal data: encrypted at rest (SECURITY.md §2)
            'payload' => 'encrypted:array',
            'tanggal_sk' => 'date',
            'tmt_awal' => 'date',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'decided_at' => 'datetime',
            'participant_count' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(MutasiChainProposalEvent::class, 'proposal_id')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
