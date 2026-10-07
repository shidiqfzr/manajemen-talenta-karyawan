<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'job_grader') && ! Schema::hasColumn('employees', 'job_grade')) {
                $table->renameColumn('job_grader', 'job_grade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'job_grade') && ! Schema::hasColumn('employees', 'job_grader')) {
                $table->renameColumn('job_grade', 'job_grader');
            }
        });
    }
};
