<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobHistory extends Model
{
    use HasFactory;

    public const MUTASI_TYPES = [
        'PROMOSI',
        'ROTASI',
        'DEMOSI',
        'MUTASI_UNIT',
        'PENUGASAN',
        'PENUGASAN_KHUSUS',
        'SANKSI_INDISIPLINER',
    ];

    public const STATUS_JABATAN = [
        'Definitif',
        'Pj',
        'Plt',
    ];

    protected $fillable = [
        'employee_nik',
        'created_by',
        'jabatan',
        'unit_kerja',
        'status_penugasan',
        'level',
        'golongan',
        'tmt_awal',
        'tmt_akhir',
        'tmt_akhir_estimasi',
        'jenis_mutasi',
        'nomor_sk',
        'tanggal_sk',
        'file_sk',
        'catatan',
        'is_dispensasi',
        'alasan_dispensasi',
    ];

    protected $casts = [
        'tmt_awal' => 'date',
        'tmt_akhir' => 'date',
        'tmt_akhir_estimasi' => 'date',
        'tanggal_sk' => 'date',
        'is_dispensasi' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_nik', 'nik');
    }

    public function getJenisMutasiLabelAttribute(): string
    {
        if (! $this->jenis_mutasi) {
            return '—';
        }

        return match ($this->jenis_mutasi) {
            'PROMOSI' => 'Promosi',
            'ROTASI' => 'Rotasi',
            'DEMOSI' => 'Demosi',
            'MUTASI_UNIT' => 'Mutasi Unit',
            'PENUGASAN', 'PENUGASAN_SEMENTARA' => 'Penugasan',
            'PENUGASAN_KHUSUS' => 'Penugasan Khusus',
            'SANKSI_INDISIPLINER' => 'Sanksi Indisipliner',
            default => $this->jenis_mutasi,
        };
    }
}
