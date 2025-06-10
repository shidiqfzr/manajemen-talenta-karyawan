<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'nilai_kepemimpinan',
        'nilai_perilaku_budaya',
        'nilai_pengalaman_teknis',
        'nilai_kematangan_pribadi',
        'nilai_tertimbang',
        'skor_smkbk_9box',
        'skor_cli_9box',
        'kategori_9box',
        'bidang_tugas',
        'lembaga_asesmen',
        'tanggal_pelaksanaan_asesmen',
        'hasil_skor_asesmen',
        'kategori_asesmen',
        'keterangan_asesmen',
        'expired_asesmen',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_diangkat_staf' => 'date',
        'tanggal_pelaksanaan_asesmen' => 'date',
        'expired_asesmen' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'nik', 'nik');
    }
}
