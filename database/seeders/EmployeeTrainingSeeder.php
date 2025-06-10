<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Training;
use App\Models\Employee;
use Carbon\Carbon;

class EmployeeTrainingSeeder extends Seeder
{
    public function run(): void
    {
        $trainings = Training::all();
        $employees = Employee::all();

        foreach ($trainings as $training) {
            $tanggalMulai = Carbon::parse($training->tanggal_mulai);
            $tanggalAkhir = Carbon::parse($training->tanggal_akhir);
            $jumlahHari = $tanggalMulai->diffInDays($tanggalAkhir) + 1;
        
            $jamBelajarPerHari = (int) $training->jam_belajar_per_hari;
        
            // Pick random employees to participate
            $selectedEmployees = $employees->random(min(2, $employees->count())); // use 2 or more for realism
        
            // Attach employees to training
            $training->employees()->syncWithoutDetaching($selectedEmployees->pluck('nik')->toArray());
        
            // Recalculate based on actual number of participants
            $manHours = $jumlahHari * $jamBelajarPerHari * $selectedEmployees->count();
        
            // Update the training's man hours
            $training->update([
                'jumlah_man_hours' => $manHours,
            ]);
        }        
    }
}
