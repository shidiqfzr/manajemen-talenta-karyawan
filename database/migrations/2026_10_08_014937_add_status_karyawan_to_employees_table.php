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
            $table->string('status_karyawan')->default('Tetap (PKWTT)')->after('status_penugasan')->comment('Tetap (PKWTT), Kontrak (PKWT), Calon Pegawai');
            $table->date('tanggal_berakhir_kontrak')->nullable()->after('status_karyawan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['status_karyawan', 'tanggal_berakhir_kontrak']);
        });
    }
};
