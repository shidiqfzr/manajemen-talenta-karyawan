<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeImportLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'ip_address',
        'user_agent',
        'file_name',
        'file_size',
        'total_rows',
        'created_count',
        'updated_count',
        'unchanged_count',
        'skipped_count',
        'has_errors',
        'status',
        'token',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_rows' => 'integer',
        'created_count' => 'integer',
        'updated_count' => 'integer',
        'unchanged_count' => 'integer',
        'skipped_count' => 'integer',
        'has_errors' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Relasi ke administrator / pengguna yang mengeksekusi impor.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
