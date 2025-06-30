<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama');
            $table->string('jabatan');
            $table->string('level');
            $table->string('unit_kerja');
            $table->string('golongan_2024')->nullable();
            $table->date('tanggal_dalam_jabatan')->nullable();
            $table->date('tmt_unit_kerja')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->date('tmt_bekerja')->nullable();
            $table->date('tanggal_diangkat_staf')->nullable();
            $table->text('susunan_keluarga')->nullable();
            $table->integer('job_grader')->nullable();
            $table->integer('person_grade')->nullable();
            $table->date('tanggal_mbt')->nullable();
            $table->date('tanggal_pensiun')->nullable();
            $table->string('agama')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('sekolah')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
