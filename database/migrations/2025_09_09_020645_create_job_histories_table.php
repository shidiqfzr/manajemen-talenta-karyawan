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
        Schema::create('job_histories', function (Blueprint $table) {
            $table->id();
            $table->string('employee_nik');
            $table->foreign('employee_nik')->references('nik')->on('employees')->onDelete('cascade');
            $table->string('jabatan');                
            $table->string('unit_kerja');            
            $table->string('level')->nullable();
            $table->string('golongan')->nullable();
            $table->date('tmt_awal');                 
            $table->date('tmt_akhir')->nullable();    
            $table->string('jenis_mutasi')->nullable();  
            $table->string('nomor_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_histories');
    }
};
