<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Training;
use App\Models\Employee;
use App\Services\TrainingService;

class EmployeeTrainingSeeder extends Seeder
{
    protected TrainingService $trainingService;

    public function __construct(TrainingService $trainingService)
    {
        $this->trainingService = $trainingService;
    }

    public function run(): void
    {
        $trainings = Training::all();
        $employees = Employee::all();

        foreach ($trainings as $training) {
            // Select random employees (min 2 or total available)
            $selectedEmployees = $employees->random(min(2, $employees->count()));

            // Attach participants
            $training->employees()->sync($selectedEmployees->pluck('nik')->toArray());

            // Recalculate man hours using the service
            $this->trainingService->recalculateManHours($training);
        }
    }
}
