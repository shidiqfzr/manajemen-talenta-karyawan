<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rekaman perubahan per pegawai agar impor dapat dibatalkan (undo). NIK, kondisi sebelum impor, dan
     * rincian tugas rangkap berisi data pribadi sehingga disimpan terenkripsi (UU PDP No. 27/2022) dan
     * dihapus setelah masa pembatalan berakhir.
     */
    public function up(): void
    {
        Schema::create('employee_import_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_import_log_id')->constrained('employee_import_logs')->cascadeOnDelete();
            $table->string('action', 10)->comment('CREATE | UPDATE');
            $table->text('nik')->comment('Terenkripsi');
            $table->longText('before')->nullable()->comment('Terenkripsi: atribut sebelum impor (null untuk CREATE)');
            $table->longText('jobs')->nullable()->comment('Terenkripsi: tugas rangkap yang dibuat/diubah impor');
            $table->string('after_hash', 64)->comment('SHA-256 atribut pegawai tepat setelah impor');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_import_changes');
    }
};
