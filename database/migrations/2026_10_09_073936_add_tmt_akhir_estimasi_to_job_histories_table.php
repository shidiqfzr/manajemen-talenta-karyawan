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
            $table->date('tmt_akhir_estimasi')->nullable()->after('tmt_akhir')->comment('Tanggal perkiraan selesai khusus untuk Pj/Plt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropColumn('tmt_akhir_estimasi');
        });
    }
};
