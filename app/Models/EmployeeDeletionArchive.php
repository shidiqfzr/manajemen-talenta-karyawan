<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Arsip karyawan yang dihapus permanen (SECURITY.md §5): data induk, riwayat penempatan, pelaksana, dan alasan.
 * Append-only: baris tidak dapat diubah atau dihapus melalui aplikasi. Skor evaluasi 9-box tidak diarsipkan
 * (hanya jumlahnya) demi minimisasi data pribadi (UU PDP).
 */
class EmployeeDeletionArchive extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'employee_deletion_archives';

    protected $guarded = [];

    protected $casts = [
        'employee_data' => 'array',
        'job_histories' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Arsip penghapusan karyawan bersifat append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('Arsip penghapusan karyawan bersifat append-only.');
        });
    }
}
