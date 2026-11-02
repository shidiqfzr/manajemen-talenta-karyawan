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
        Schema::table('job_histories', function (Blueprint $table) {
            $table->boolean('is_applied')->default(true)->after('chain_order')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropIndex(['is_applied']);
            $table->dropColumn('is_applied');
        });
    }
};
