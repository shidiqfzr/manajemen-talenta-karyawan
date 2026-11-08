<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Arsip penghapusan karyawan (SECURITY.md §5): menyimpan data induk dan riwayat penempatan
     * karyawan yang dihapus permanen, agar jejak audit BPK/Inspektorat tidak hilang bersama cascade.
     * nik sengaja tanpa foreign key karena barisnya harus bertahan setelah karyawan dihapus.
     */
    public function up(): void
    {
        Schema::create('employee_deletion_archives', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->comment('Satu nilai per proses hapus (satu atau massal)');
            $table->string('employee_nik', 8);
            $table->string('employee_nama');
            $table->string('jabatan')->nullable();
            $table->string('unit_kerja')->nullable();
            $table->json('employee_data')->comment('Snapshot kolom data induk karyawan');
            $table->json('job_histories')->comment('Seluruh riwayat penempatan saat dihapus');
            $table->unsignedInteger('evaluations_count')->default(0);
            $table->unsignedInteger('trainings_count')->default(0);
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('deleted_by_name')->comment('Snapshot nama admin pelaksana');
            $table->text('reason');
            $table->timestamp('created_at')->useCurrent();

            $table->index('batch_id');
            $table->index('employee_nik');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_deletion_archives');
    }
};
