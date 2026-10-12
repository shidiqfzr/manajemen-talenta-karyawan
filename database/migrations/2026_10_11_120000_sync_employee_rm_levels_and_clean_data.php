<?php

use App\Models\Employee;
use App\Services\ManPowerPlanningService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Data cleansing: normalize any invalid level string (such as 'test_dummy')
        Employee::where('level', 'test_dummy')->update(['level' => 'Karpim']);

        // 2. Sync rm_level for employees where it is missing
        $mppService = app(ManPowerPlanningService::class);
        $employees = Employee::whereNull('rm_level')->orWhere('rm_level', '')->get();

        foreach ($employees as $emp) {
            $determinedRm = $mppService->determineRmLevel($emp);
            $emp->updateQuietly([
                'rm_level' => $determinedRm,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructive reversal needed for data cleansing
    }
};
