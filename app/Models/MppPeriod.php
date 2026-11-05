<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode bulanan MPP: salinan matriks pada posisi akhir bulan. Draf dapat direkam ulang, periode tertutup
 * menjadi acuan. Hanya diubah melalui MppPeriodService agar setiap perubahan tercatat.
 */
class MppPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'mpp_periods';

    protected $fillable = [
        'year',
        'month',
        'status',
        'revision',
        'snapshot',
        'captured_at',
        'captured_by',
        'closed_at',
        'closed_by',
        'close_note',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'revision' => 'integer',
        'snapshot' => 'array',
        'captured_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /** Personil di balik tiap sel personil pada saat perekaman. */
    public function members(): HasMany
    {
        return $this->hasMany(MppPeriodMember::class);
    }

    /** Admin yang menutup periode (null bila belum ditutup). */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** Admin yang terakhir merekam draf (null bila direkam oleh penjadwal). */
    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
