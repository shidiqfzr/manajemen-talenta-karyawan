<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu personil di balik sel personil matriks pada periode MPP. employee_nik berupa nilai biasa tanpa
 * foreign key: snapshot harus tetap utuh walaupun data pegawai berubah kemudian.
 */
class MppPeriodMember extends Model
{
    public $timestamps = false;

    protected $table = 'mpp_period_members';

    protected $fillable = [
        'mpp_period_id',
        'scope',
        'rm_level',
        'bidang',
        'employee_nik',
        'jabatan',
        'unit_kerja',
        'golongan',
        'jalur_masuk',
        'tanggal_penempatan',
        'tanggal_mbt',
    ];

    protected $casts = [
        'tanggal_penempatan' => 'date',
        'tanggal_mbt' => 'date',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(MppPeriod::class, 'mpp_period_id');
    }
}
