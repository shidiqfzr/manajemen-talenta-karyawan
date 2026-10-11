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
            $table->string('status_penugasan')->default('Definitif')->after('unit_kerja');
        });

        Schema::table('job_histories', function (Blueprint $table) {
            $table->string('status_penugasan')->default('Definitif')->after('unit_kerja');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('status_penugasan');
        });

        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropColumn('status_penugasan');
        });
    }
};
