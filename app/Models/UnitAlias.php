<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nama lama atau variasi penulisan sebuah unit kerja. Dipakai impor karyawan untuk memetakan
 * berkas lama ke nama unit master saat ini, sehingga tidak ada pemetaan nama yang tertanam di kode.
 */
class UnitAlias extends Model
{
    protected $fillable = ['unit_id', 'alias', 'alias_key', 'source'];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Bentuk pembanding nama unit: huruf kecil, spasi tunggal, dan "&" diperlakukan sama dengan "dan".
     */
    public static function keyFor(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', str_replace('&', ' dan ', trim($name))));
    }

    /**
     * Mencatat (atau memindahkan) alias ke unit. Satu alias hanya menunjuk satu unit.
     */
    public static function remember(Unit $unit, string $alias, string $source): self
    {
        return static::updateOrCreate(
            ['alias_key' => static::keyFor($alias)],
            ['unit_id' => $unit->id, 'alias' => trim($alias), 'source' => $source]
        );
    }

    /**
     * Peta alias ternormalisasi => nama unit aktif.
     *
     * @return array<string, string>
     */
    public static function activeMap(): array
    {
        return static::query()
            ->join('units', 'units.id', '=', 'unit_aliases.unit_id')
            ->where('units.is_active', true)
            ->pluck('units.nama', 'unit_aliases.alias_key')
            ->all();
    }
}
