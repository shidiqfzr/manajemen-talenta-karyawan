<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Audit Trail (SECURITY.md §5): kualifikasi promosi bersifat peringatan (bukan persetujuan),
     * sehingga kondisi yang belum terpenuhi saat SK diproses dicatat sebagai snapshot pada riwayat jabatan.
     */
    public function up(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->string('catatan_kepatuhan', 500)->nullable()->after('alasan_dispensasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropColumn('catatan_kepatuhan');
        });
    }
};
