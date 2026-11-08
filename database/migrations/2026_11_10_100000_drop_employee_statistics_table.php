<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The Statistik Karyawan page and its model are gone; Manajemen Pelatihan now uses the live employee headcount.
     */
    public function up(): void
    {
        Schema::dropIfExists('employee_statistics');
    }

    /**
     * Reverse the migrations (restores the empty table structure only, not its rows).
     */
    public function down(): void
    {
        Schema::create('employee_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->integer('jumlah_karpim');
            $table->integer('jumlah_karpel');
            $table->integer('total_karyawan');
            $table->timestamps();
        });
    }
};
