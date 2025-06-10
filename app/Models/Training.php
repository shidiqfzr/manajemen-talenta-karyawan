<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Training extends Model
{
    protected $fillable = [
        'judul',
        'jenis',
        'metode',
        'tanggal_mulai',
        'tanggal_akhir',
        'biaya_tiket_peserta',
        'biaya_pelatihan',
        'penyelenggara',
        'nomor_surat',
        'tanggal_surat',
        'keterangan',
        'bidang_pelatihan',
        'jam_belajar_per_hari',
        'jumlah_man_hours',
    ];

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'employee_training', 'training_id', 'employee_nik')
            ->withPivot(['sertifikat'])
            ->withTimestamps();
    }
}
