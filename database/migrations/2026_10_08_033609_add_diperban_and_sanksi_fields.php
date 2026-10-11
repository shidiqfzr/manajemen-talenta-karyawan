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
        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('is_diperban')->default(false)->after('is_overflow')
                ->comment('Penanda untuk jabatan demosi/sanksi indisipliner (Diperbantukan)');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->date('tanggal_berakhir_sanksi')->nullable()->after('tanggal_berakhir_kontrak')
                ->comment('Berisi tanggal kedaluwarsa sanksi jika karyawan sedang menjabat formasi Diperban');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('tanggal_berakhir_sanksi');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('is_diperban');
        });
    }
};
