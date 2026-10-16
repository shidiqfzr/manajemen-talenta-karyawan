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
            $table->foreignId('created_by')->nullable()->after('employee_nik')->constrained('users')->nullOnDelete();
            $table->string('file_sk')->nullable()->after('nomor_sk');
            $table->boolean('is_dispensasi')->default(false)->after('catatan');
            $table->text('alasan_dispensasi')->nullable()->after('is_dispensasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['created_by', 'file_sk', 'is_dispensasi', 'alasan_dispensasi']);
        });
    }
};
