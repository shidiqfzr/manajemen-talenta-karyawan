<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menandai asal setiap baris riwayat jabatan (DOMAIN.md §2.2). Baris data awal hasil impor/input pegawai
 * dan pembaruan data kepegawaian bukan SK mutasi resmi, sehingga dibedakan dari mutasi pada layar
 * Riwayat & Audit Log. Tidak ada baris yang dihapus (SECURITY.md §5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->string('sumber', 20)->default('mutasi')->after('jenis_mutasi')->index();
        });

        // Baris data awal lama memakai nomor SK rekaan "SK.DIR/REG5/REK-AWAL/{tahun}"; itu bukan SK Direksi.
        DB::table('job_histories')->where('nomor_sk', 'like', 'SK.DIR/REG5/REK-AWAL/%')
            ->update(['sumber' => 'data_awal', 'nomor_sk' => null, 'tanggal_sk' => null]);

        DB::table('job_histories')->where('catatan', 'like', 'Tugas Rangkap via Excel Import:%')
            ->update(['sumber' => 'impor']);

        // Baris hasil pembaruan data pegawai: tanpa jenis mutasi, SK, maupun gerbong.
        DB::table('job_histories')->where('sumber', 'mutasi')
            ->whereNull('jenis_mutasi')->whereNull('nomor_sk')->whereNull('batch_id')
            ->update(['sumber' => 'pembaruan_data']);
    }

    public function down(): void
    {
        Schema::table('job_histories', function (Blueprint $table) {
            $table->dropIndex(['sumber']);
            $table->dropColumn('sumber');
        });
    }
};
