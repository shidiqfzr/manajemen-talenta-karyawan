<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penutupan periode bulanan MPP: satu baris per bulan berisi salinan matriks (snapshot), ditambah daftar
     * personil di balik tiap sel personil. employee_nik sengaja tanpa foreign key agar snapshot tetap utuh
     * walaupun data pegawai berubah atau dihapus.
     */
    public function up(): void
    {
        Schema::create('mpp_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 10)->default('draft')->comment('draft, closed');
            $table->unsignedSmallInteger('revision')->default(1);
            $table->json('snapshot');
            $table->timestamp('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('close_note')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        Schema::create('mpp_period_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mpp_period_id')->constrained('mpp_periods')->cascadeOnDelete();
            $table->string('scope', 30);
            $table->string('rm_level', 5);
            $table->string('bidang', 3);
            $table->string('employee_nik', 8);
            $table->string('jabatan')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->date('tanggal_penempatan')->nullable();
            $table->date('tanggal_mbt')->nullable();

            $table->index(['mpp_period_id', 'scope', 'rm_level', 'bidang'], 'mpp_period_members_cell_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpp_period_members');
        Schema::dropIfExists('mpp_periods');
    }
};
