<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rencana pasokan talenta MPP per tahun (DOMAIN.md §8): satu baris per sumber x RM Band x bidang.
     * Tabel dimulai kosong: rencana hanya diisi admin melalui editor sehingga setiap angka punya pengubah
     * dan alasan yang tercatat.
     */
    public function up(): void
    {
        Schema::create('mpp_supply_plans', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('source', 20)->comment('ckp, talent_scouting, rbb, prioritas');
            $table->string('rm_level', 5);
            $table->string('bidang', 3);
            $table->unsignedSmallInteger('jumlah')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'source', 'rm_level', 'bidang']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpp_supply_plans');
    }
};
