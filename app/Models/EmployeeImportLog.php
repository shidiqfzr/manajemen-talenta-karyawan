<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class EmployeeImportLog extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_ROLLED_BACK = 'rolled_back';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Menunggu Persetujuan',
        self::STATUS_APPROVED => 'Disetujui (Diproses)',
        self::STATUS_COMPLETED => 'Tersimpan',
        self::STATUS_REJECTED => 'Ditolak',
        self::STATUS_EXPIRED => 'Kedaluwarsa',
        self::STATUS_ROLLED_BACK => 'Dibatalkan',
    ];

    protected $fillable = [
        'user_id',
        'checker_id',
        'user_name',
        'user_email',
        'checker_name',
        'ip_address',
        'user_agent',
        'file_name',
        'file_size',
        'file_sha256',
        'stored_path',
        'total_rows',
        'created_count',
        'updated_count',
        'unchanged_count',
        'skipped_count',
        'has_errors',
        'status',
        'submitted_at',
        'decided_at',
        'decided_ip',
        'decision_note',
        'committed_at',
        'rolled_back_at',
        'rolled_back_by',
        'rolled_back_by_name',
        'rollback_reason',
        'token',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'unchanged_count' => 'integer',
        'skipped_count' => 'integer',
        'has_errors' => 'boolean',
        'metadata' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'committed_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    /**
     * Relasi ke administrator / pengguna yang mengeksekusi impor (pengaju).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(EmployeeImportChange::class);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Batas waktu pembatalan: masa pembatalan dihitung sejak impor disimpan.
     */
    public function undoDeadline(?int $windowDays = null): ?Carbon
    {
        $savedAt = $this->committed_at ?? ($this->status === self::STATUS_COMPLETED ? $this->created_at : null);

        return $savedAt?->copy()->addDays($windowDays ?? (int) config('hris.import.undo_window_days', 7));
    }

    /**
     * Apakah impor ini masih dalam masa pembatalan (belum memeriksa keberadaan rekaman perubahan).
     */
    public function isWithinUndoWindow(): bool
    {
        $deadline = $this->undoDeadline();

        return $this->status === self::STATUS_COMPLETED && $deadline !== null && now()->lt($deadline);
    }
}
