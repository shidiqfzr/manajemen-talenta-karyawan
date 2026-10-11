<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Position extends Model
{
    use HasFactory;

    public const CACHE_KEY = 'master_positions_all';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Retrieve all positions keyed by nama with automatic caching.
     */
    public static function getAllCached(): Collection
    {
        return Cache::remember(self::CACHE_KEY, 86400, function () {
            return static::all()->keyBy('nama');
        });
    }

    /**
     * Ambil nama-nama posisi overflow (non-diperban) dari cache.
     */
    public static function getOverflowPositionNames(): array
    {
        return static::getAllCached()
            ->where('is_overflow', true)
            ->where('is_diperban', false)
            ->pluck('nama')
            ->toArray();
    }

    protected $table = 'positions';

    protected $fillable = [
        'nama',
        'bidang',
        'level',
        'rm_level',
        'is_active',
        'is_overflow',
        'is_diperban',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_overflow' => 'boolean',
        'is_diperban' => 'boolean',
    ];

    public const BIDANG_LIST = [
        'TAN' => 'TAN (Tanaman)',
        'TEK' => 'TEK (Teknik & Pengolahan)',
        'KEU' => 'KEU (Keuangan & Akuntansi)',
        'UMU' => 'UMU (Umum & SDM)',
    ];

    public const LEVEL_LIST = [
        'Karpim' => 'Karyawan Pimpinan (Karpim)',
        'Karpel' => 'Karyawan Pelaksana (Karpel)',
    ];

    public const RM_LEVEL_LIST = [
        'RM-1' => 'RM-1 (Pimpinan / Senior Management)',
        'RM-2' => 'RM-2 (Madya / Middle Management)',
        'RM-3' => 'RM-3 (Pratama / First Line Management)',
        'RM-4' => 'RM-4 (Pelaksana / Operational Staff)',
    ];

    /**
     * Scope to only active positions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Relasi ke Employee via string nama jabatan (non-breaking legacy compatibility).
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'jabatan', 'nama');
    }

    /**
     * Relasi ke Unit via pivot table position_unit.
     */
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'position_unit')
            ->withPivot('kuota')
            ->withTimestamps();
    }

    /**
     * Check if the position title is meant for operational units (Kebun/Pabrik).
     */
    public function isOperationalTitle(): bool
    {
        $lower = strtolower($this->nama);

        return str_contains($lower, 'manajer') || str_contains($lower, 'asisten');
    }

    /**
     * Check if the position title is meant for office units (Kandir/Bagian).
     */
    public function isKantorTitle(): bool
    {
        $lower = strtolower($this->nama);

        return str_contains($lower, 'kepala bagian') ||
               str_contains($lower, 'kepala sub bagian') ||
               $lower === 'asisten diperbantukan';
    }
}
