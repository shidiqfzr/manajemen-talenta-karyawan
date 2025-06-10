<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evaluation;
use App\Models\Employee;
use Illuminate\Support\Str;

class EvaluationSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::take(10)->get(); // Seed for first 10 employees

        foreach ($employees as $employee) {
            $nilai_kepemimpinan = rand(60, 100);
            $nilai_perilaku_budaya = rand(60, 100);
            $nilai_pengalaman_teknis = rand(60, 100);
            $nilai_kematangan_pribadi = rand(60, 100);

            $nilai_tertimbang = (
                $nilai_kepemimpinan * 0.4 +
                $nilai_perilaku_budaya * 0.3 +
                $nilai_pengalaman_teknis * 0.2 +
                $nilai_kematangan_pribadi * 0.1
            );

            Evaluation::create([
                'nik' => $employee->nik,
                'nilai_kepemimpinan' => $nilai_kepemimpinan,
                'nilai_perilaku_budaya' => $nilai_perilaku_budaya,
                'nilai_pengalaman_teknis' => $nilai_pengalaman_teknis,
                'nilai_kematangan_pribadi' => $nilai_kematangan_pribadi,
                'nilai_tertimbang' => $nilai_tertimbang,
                'skor_smkbk_9box' => rand(1, 9),
                'skor_cli_9box' => rand(1, 9),
                'kategori_9box' => collect(['High Potential', 'Core Performer', 'Low Performer'])->random(),
                'bidang_tugas' => 'Bidang ' . rand(1, 3),
                'lembaga_asesmen' => collect(['Lembaga A', 'Lembaga B', 'Lembaga C'])->random(),
                'tanggal_pelaksanaan_asesmen' => now()->subMonths(rand(1, 24)),
                'hasil_skor_asesmen' => rand(60, 100),
                'kategori_asesmen' => collect(['Baik', 'Cukup', 'Kurang'])->random(),
                'keterangan_asesmen' => Str::random(20),
                'expired_asesmen' => now()->addYears(2),
                'keterangan' => Str::random(20),
            ]);
        }
    }
}
