<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekaman perubahan satu pegawai oleh satu impor, dipakai untuk membatalkan impor. Berisi data pribadi
 * sehingga seluruh kolom sensitifnya terenkripsi.
 */
class EmployeeImportChange extends Model
{
    protected $fillable = ['employee_import_log_id', 'action', 'nik', 'before', 'jobs', 'after_hash'];

    protected $casts = [
        'nik' => 'encrypted',
        'before' => 'encrypted:array',
        'jobs' => 'encrypted:array',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmployeeImportLog::class, 'employee_import_log_id');
    }
}
