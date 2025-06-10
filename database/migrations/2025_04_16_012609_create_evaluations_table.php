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
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id(); //PK
            $table->string('nik'); // Foreign key to employees.nik
            $table->foreign('nik')->references('nik')->on('employees')->onDelete('cascade'); // relasi ke tabel employees

            // Nilai per kriteria
            $table->decimal('nilai_kepemimpinan', 5, 2)->nullable(); // 40%
            $table->decimal('nilai_perilaku_budaya', 5, 2)->nullable(); // 30%
            $table->decimal('nilai_pengalaman_teknis', 5, 2)->nullable(); // 20%
            $table->decimal('nilai_kematangan_pribadi', 5, 2)->nullable(); // 10%
            
            $table->decimal('nilai_tertimbang', 5, 2)->nullable();

            // 9 Box
            $table->decimal('skor_smkbk_9box', 5, 2)->nullable();
            $table->decimal('skor_cli_9box', 5, 2)->nullable();
            $table->string('kategori_9box')->nullable();

            // Bidang tugas
            $table->string('bidang_tugas')->nullable();

            // Asesmen
            $table->string('lembaga_asesmen')->nullable();
            $table->date('tanggal_pelaksanaan_asesmen')->nullable();
            $table->decimal('hasil_skor_asesmen', 5, 2)->nullable();
            $table->string('kategori_asesmen')->nullable();
            $table->string('keterangan_asesmen')->nullable();
            $table->date('expired_asesmen')->nullable();

            // Tambahan keterangan umum
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
