<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation;
use App\Models\Training;
use App\Models\JobHistory;

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

    protected $casts = [
        'tanggal_dalam_jabatan' => 'date',
        'tmt_unit_kerja'        => 'date',
        'tanggal_lahir'         => 'date',
        'tmt_bekerja'           => 'date',
        'tanggal_diangkat_staf' => 'date',
        'tanggal_mbt'           => 'date',
        'tanggal_pensiun'       => 'date',
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

    public function jobHistories()
    {
        return $this->hasMany(JobHistory::class, 'employee_nik', 'nik')
            ->orderByDesc('tmt_awal');
    }

    public function currentJob()
    {
        return $this->hasOne(JobHistory::class, 'employee_nik', 'nik')
            ->whereNull('tmt_akhir')
            ->latestOfMany('tmt_awal');
    }
}
