<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'periode',
        'jumlah_karpim',
        'jumlah_karpel',
        'total_karyawan',
    ];
}
