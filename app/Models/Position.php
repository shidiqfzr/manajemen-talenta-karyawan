<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';

    protected $fillable = [
        'nama',
        'bidang',
        'level',
        'rm_level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}
