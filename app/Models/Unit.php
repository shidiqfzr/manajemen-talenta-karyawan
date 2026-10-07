<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $fillable = [
        'nama',
        'kode',
        'wilayah',
        'is_active',
        'urutan',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'urutan' => 'integer',
    ];

    public const WILAYAH_LIST = [
        'Regional Office (Kantor Direksi Pontianak)',
        'Wilayah Kalimantan Barat',
        'Wilayah Kalimantan Selatan/Tengah',
        'Wilayah Kalimantan Timur',
    ];

    /**
     * Scope to only active units.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Relasi ke Employee via string nama unit kerja (non-breaking legacy compatibility).
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'unit_kerja', 'nama');
    }
}
