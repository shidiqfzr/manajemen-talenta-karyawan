<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation;
use Carbon\Carbon;

class Employee extends Model
{
    protected $primaryKey = 'nik';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'nik',
        'nama',
        'jabatan',
        'level',
        'unit_kerja',
        'golongan',
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tempat_lahir',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'susunan_keluarga',
        'job_grader',
        'person_grade',
        'tanggal_mbt',
        'tanggal_pensiun',
        'agama',
        'pendidikan_terakhir',
        'sekolah',
        'foto',
    ];

    protected $dates = [
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'tanggal_mbt',
        'tanggal_pensiun',
    ];

    public function trainings()
    {
        return $this->belongsToMany(Training::class, 'employee_training', 'employee_nik', 'training_id')
            ->withPivot(['sertifikat'])
            ->withTimestamps();
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'nik', 'nik');
    }
}
