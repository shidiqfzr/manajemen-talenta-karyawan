<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit entry of a chain mutation proposal (who did what, when, and why).
 */
class MutasiChainProposalEvent extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_SAVED = 'saved';

    public const ACTION_SUBMITTED = 'submitted';

    public const ACTION_WITHDRAWN = 'withdrawn';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_EXPIRED = 'expired';

    public const ACTION_EXECUTED = 'executed';

    /** Action => label shown to admins. */
    public const ACTION_LABELS = [
        self::ACTION_SAVED => 'Draf disimpan',
        self::ACTION_SUBMITTED => 'Diajukan untuk persetujuan',
        self::ACTION_WITHDRAWN => 'Usulan ditarik',
        self::ACTION_REJECTED => 'Dikembalikan untuk revisi',
        self::ACTION_EXPIRED => 'Kedaluwarsa',
        self::ACTION_EXECUTED => 'SK diterbitkan',
    ];

    protected $fillable = ['proposal_id', 'action', 'user_id', 'user_name', 'reason'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(MutasiChainProposal::class, 'proposal_id');
    }

    public function actionLabel(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
