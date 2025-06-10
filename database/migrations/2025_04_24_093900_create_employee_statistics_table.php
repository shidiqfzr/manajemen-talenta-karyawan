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
        Schema::create('employee_statistics', function (Blueprint $table) {
            $table->id();
            $table->date('periode');
            $table->integer('jumlah_karpim');
            $table->integer('jumlah_karpel');
            $table->integer('total_karyawan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_statistics');
    }
};
