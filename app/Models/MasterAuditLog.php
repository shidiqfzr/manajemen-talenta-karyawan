<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Riwayat perubahan master unit, jabatan, dan formasi jabatan (SECURITY.md §5).
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

    public const ACTION_LABELS = [
        'seat.added' => 'Formasi jabatan ditambahkan',
        'seat.updated' => 'Formasi jabatan diubah',
        'seat.removed' => 'Formasi jabatan dihapus',
        'unit.created' => 'Unit dibuat',
        'unit.updated' => 'Unit diubah',
        'unit.renamed' => 'Nama unit diubah',
        'unit.activated' => 'Unit diaktifkan',
        'unit.deactivated' => 'Unit dinonaktifkan',
        'unit.deleted' => 'Unit dihapus',
        'position.created' => 'Jabatan dibuat',
        'position.updated' => 'Jabatan diubah',
        'position.renamed' => 'Nama jabatan diubah',
        'position.activated' => 'Jabatan diaktifkan',
        'position.deactivated' => 'Jabatan dinonaktifkan',
        'position.deleted' => 'Jabatan dihapus',
        'mpp_supply.created' => 'Rencana pasokan MPP dibuat',
        'mpp_supply.updated' => 'Rencana pasokan MPP diubah',
        'mpp_period.captured' => 'Draf periode MPP direkam',
        'mpp_period.closed' => 'Periode MPP ditutup',
        'mpp_period.reopened' => 'Periode MPP dibuka kembali',
    ];

    public const ENTITY_LABELS = [
        'unit' => 'Unit Kerja',
        'position' => 'Jabatan',
        'seat' => 'Formasi Jabatan',
        'mpp_supply' => 'Rencana Pasokan MPP',
        'mpp_period' => 'Periode MPP',
    ];

    /** Istilah bisnis untuk kolom yang tercatat pada nilai lama/baru. */
    public const FIELD_LABELS = [
        'nama' => 'Nama',
        'kode' => 'Kode',
        'wilayah' => 'Wilayah',
        'urutan' => 'Urutan',
        'is_active' => 'Status',
        'level' => 'Strata',
        'rm_level' => 'RM Band',
        'bidang' => 'Bidang',
        'kuota' => 'Kuota',
        'filled' => 'Terisi',
        'jumlah' => 'Jumlah',
        'status' => 'Status Periode',
        'revision' => 'Revisi',
    ];

    public static function actionLabel(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    /**
     * Ringkasan perubahan dengan istilah bisnis, mis. "Kuota: 1 → 3".
     *
     * @return list<string>
     */
    public function changeSummary(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));

        return array_map(function (string $key) use ($old, $new): string {
            $show = fn (mixed $value): string => match (true) {
                $value === null => '-',
                $key === 'is_active' => $value ? 'Aktif' : 'Nonaktif',
                is_bool($value) => $value ? 'ya' : 'tidak',
                default => (string) $value,
            };

            return (self::FIELD_LABELS[$key] ?? $key).': '.$show($old[$key] ?? null).' → '.$show($new[$key] ?? null);
        }, $keys);
    }

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
        ?User $user,
        string $action,
        string $entityType,
        ?int $entityId,
        string $entityLabel,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'Sistem',
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
