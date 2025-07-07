<?php

namespace App\Services;

use App\Models\Training;
use Carbon\Carbon;

class TrainingService
{
    public function recalculateManHours(Training $training): void
    {
        $jumlahPeserta = $training->employees()->count();
        $jumlahHari = Carbon::parse($training->tanggal_mulai)->diffInDays(Carbon::parse($training->tanggal_akhir)) + 1;
        $jamPerHari = $training->jam_belajar_per_hari;

        $training->jumlah_man_hours = $jumlahHari * $jamPerHari * $jumlahPeserta;
        $training->save();
    }
}
