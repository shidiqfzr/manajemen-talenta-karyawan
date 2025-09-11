<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;

class JobHistory extends Model
{
    use HasFactory;

    public const MUTASI_TYPES = [
        'PROMOSI',
        'ROTASI',
        'DEMOSI',
        'ALIH_TUGAS',
        'MUTASI_UNIT',
        'PENUGASAN',
        'LAINNYA',
    ];

    protected $fillable = [
        'employee_nik',      
        'jabatan',
        'unit_kerja',
        'level',
        'golongan',
        'tmt_awal',
        'tmt_akhir',
        'jenis_mutasi',      
        'nomor_sk',
        'tanggal_sk',
        'catatan',
    ];

    protected $casts = [
        'tmt_awal' => 'date',
        'tmt_akhir' => 'date',
        'tanggal_sk' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function getJenisMutasiLabelAttribute(): string
    {
        if (!$this->jenis_mutasi) return '—';

        return match ($this->jenis_mutasi) {
            'PROMOSI'     => 'Promosi',
            'ROTASI'      => 'Rotasi',
            'DEMOSI'      => 'Demosi',
            'ALIH_TUGAS'  => 'Alih Tugas',
            'MUTASI_UNIT' => 'Mutasi Unit',
            'PENUGASAN'   => 'Penugasan',
            default       => 'Lainnya',
        };
    }
}
