<?php

namespace Database\Seeders;

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
            PositionUnitSeeder::class, // <-- Added this
            EmployeeSeeder::class,
            TrainingSeeder::class,
            EmployeeTrainingSeeder::class,
            EvaluationSeeder::class,
            EmployeeStatisticSeeder::class,
            RealisticMutationSeeder::class,
        ]);
    }
}
