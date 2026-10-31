<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Riwayat perubahan master unit, jabatan, dan kursi formasi (SECURITY.md §5).
 * Append-only: baris tidak dapat diubah atau dihapus melalui aplikasi.
 */
class MasterAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'master_audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'entity_type',
        'entity_id',
        'entity_label',
        'old_values',
        'new_values',
        'reason',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Riwayat perubahan master bersifat append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Riwayat perubahan master bersifat append-only.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        User $user,
        string $action,
        string $entityType,
        ?int $entityId,
        string $entityLabel,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'entity_label' => $entityLabel,
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason,
        ]);
    }
}
