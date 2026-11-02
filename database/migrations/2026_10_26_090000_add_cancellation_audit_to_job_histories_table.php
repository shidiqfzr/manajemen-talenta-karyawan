<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Audit Trail (SECURITY.md §5): pembatalan mutasi terjadwal tidak lagi menghapus
     * baris job_histories secara permanen, melainkan soft delete beserta pelaku & alasan.
     * tanggal_berakhir_sanksi disimpan agar sanksi terjadwal tidak hilang saat diaktivasi.
     */
    public function up(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->date('tanggal_berakhir_sanksi')->nullable()->after('tmt_akhir_estimasi');
            $table->foreignId('cancelled_by')->nullable()->after('alasan_dispensasi')
                ->constrained('users')->nullOnDelete();
            $table->string('alasan_pembatalan', 255)->nullable()->after('cancelled_by');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['tanggal_berakhir_sanksi', 'alasan_pembatalan', 'deleted_at']);
        });
    }
};
