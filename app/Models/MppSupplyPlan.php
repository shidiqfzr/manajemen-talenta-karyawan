<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sel rencana pasokan talenta MPP: tahun x sumber x RM Band x bidang (DOMAIN.md §8).
 * Hanya diubah melalui MppSupplyPlanService agar setiap perubahan tercatat.
 */
class MppSupplyPlan extends Model
{
    protected $table = 'mpp_supply_plans';

    /** Sumber pasokan (kunci => istilah bisnis). Pasokan = ckp + talent_scouting + rbb. */
    public const SOURCES = [
        'ckp' => 'CKP Internal',
        'talent_scouting' => 'Talent Scouting',
        'rbb' => 'RBB',
        'prioritas' => 'Jabatan Prioritas Diisi',
    ];

    /** Sumber yang dihitung sebagai pasokan pada rumus Kebutuhan. */
    public const SUPPLY_SOURCES = ['ckp', 'talent_scouting', 'rbb'];

    public const LEVELS = ['RM-1', 'RM-2', 'RM-3'];

    /**
     * RM Band yang dipasok jalur rekrutmen (CKP, Talent Scouting, RBB): seluruhnya pintu masuk staf RM-3
     * (DOMAIN.md §8). RM-1 dan RM-2 diisi lewat promosi, bukan rekrutmen.
     */
    public const SUPPLY_LEVELS = ['RM-3'];

    public const BIDANG = ['KEU', 'TAN', 'TEK', 'UMU'];

    public const MAX_JUMLAH = 999;

    /** Apakah sumber boleh direncanakan pada RM Band ini (Jabatan Prioritas Diisi berlaku untuk semua band). */
    public static function allowsCell(string $source, string $rm): bool
    {
        return ! in_array($source, self::SUPPLY_SOURCES, true) || in_array($rm, self::SUPPLY_LEVELS, true);
    }

    protected $fillable = [
        'year',
        'source',
        'rm_level',
        'bidang',
        'jumlah',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'jumlah' => 'integer',
    ];

    /** Admin yang terakhir mengubah sel ini. */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
