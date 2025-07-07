<?php

namespace Database\Seeders;

use App\Models\EmployeeStatistic;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            EmployeeSeeder::class,
            TrainingSeeder::class,
            EmployeeTrainingSeeder::class,
            EvaluationSeeder::class,
            EmployeeStatisticSeeder::class
        ]);
    }
}
