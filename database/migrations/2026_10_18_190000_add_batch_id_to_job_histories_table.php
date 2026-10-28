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
            $table->string('batch_id', 64)->nullable()->after('id')->index();
            $table->unsignedSmallInteger('chain_order')->nullable()->after('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropIndex(['batch_id']);
            $table->dropColumn(['batch_id', 'chain_order']);
        });
    }
};
