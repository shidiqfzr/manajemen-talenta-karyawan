<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\EmployeeStatistic;
use Carbon\Carbon;

class EmployeeStatisticSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statistics = [
            [
                'periode' => Carbon::create(2025, 1, 1),
                'jumlah_karpim' => 10,
                'jumlah_karpel' => 25,
            ],
            [
                'periode' => Carbon::create(2025, 2, 1),
                'jumlah_karpim' => 12,
                'jumlah_karpel' => 28,
            ],
            [
                'periode' => Carbon::create(2025, 3, 1),
                'jumlah_karpim' => 11,
                'jumlah_karpel' => 27,
            ],
        ];

        foreach ($statistics as $statistic) {
            $statistic['total_karyawan'] = $statistic['jumlah_karpim'] + $statistic['jumlah_karpel'];
            EmployeeStatistic::create($statistic);
        }
    }
}
