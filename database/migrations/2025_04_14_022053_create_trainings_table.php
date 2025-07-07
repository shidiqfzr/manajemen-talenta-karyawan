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
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->string('judul');
            $table->string('jenis');
            $table->string('metode');
            $table->date('tanggal_mulai');
            $table->date('tanggal_akhir');
            $table->integer('biaya_tiket_peserta');
            $table->integer('biaya_pelatihan');
            $table->string('penyelenggara');
            $table->string('nomor_surat');
            $table->date('tanggal_surat');
            $table->text('keterangan');
            $table->string('bidang_pelatihan');
            $table->integer('jam_belajar_per_hari');
            $table->integer('jumlah_man_hours')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
