<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surat Keputusan (SK) struktur organisasi beserta siklus persetujuannya (DOMAIN.md §3.7).
 */
class StructureDecree extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    /** Disetujui; tampil sebagai "Terjadwal" selama tanggal berlaku belum tiba. */
    public const STATUS_APPROVED = 'approved';

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /** Struktur berubah setelah SK diajukan; tidak ada yang diterapkan sampai ditinjau ulang. */
    public const STATUS_STALE = 'stale';

    public const STATUS_FAILED = 'failed';

    /**
     * Status yang masih menahan satu-satunya slot SK terbuka. SK Gagal tetap terbuka: dapat diterapkan ulang setelah
     * penyebabnya diperbaiki, atau dibatalkan.
     */
    public const OPEN_STATUSES = [self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED, self::STATUS_STALE, self::STATUS_FAILED];

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Draf',
        self::STATUS_SUBMITTED => 'Diajukan',
        self::STATUS_APPROVED => 'Disetujui',
        self::STATUS_APPLIED => 'Diterapkan',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_STALE => 'Perlu Tinjau Ulang',
        self::STATUS_FAILED => 'Gagal',
    ];

    protected $fillable = [
        'sk_number',
        'title',
        'revokes',
        'note',
        'sk_date',
        'effective_date',
        'status',
        'source_path',
        'source_sha256',
        'pdf_path',
        'target',
        'diff',
        'unit_mapping',
        'review_digest',
        'snapshot',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'decision_reason',
        'applied_at',
        'failure_message',
    ];

    protected $casts = [
        'sk_date' => 'date',
        'effective_date' => 'date',
        'target' => 'array',
        'diff' => 'array',
        'unit_mapping' => 'array',
        'snapshot' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    /** Nomor SK tanpa spasi di sekitar garis miring, mis. `DPSB/SKPTS/ 352 /IX/2026` menjadi `DPSB/SKPTS/352/IX/2026`. */
    public static function normalizeNumber(string $number): string
    {
        return trim((string) preg_replace('/\s*\/\s*/', '/', trim($number)));
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
