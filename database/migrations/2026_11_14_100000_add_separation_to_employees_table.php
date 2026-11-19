<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemberhentian karyawan (DOMAIN.md §5.1): karyawan yang telah melewati Batas Usia Pensiun dilepas dari
 * jabatannya lewat peristiwa pemberhentian yang tercatat, bukan dihapus. `tanggal_berhenti` menandai karyawan
 * tidak lagi menempati kursi formasi; jabatan dan unit terakhir tetap tersimpan sebagai arsip.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('tanggal_berhenti')->nullable()->index()->after('tanggal_pensiun');
            $table->string('alasan_berhenti', 30)->nullable()->after('tanggal_berhenti');
            // Dasar pemberhentian: SK Direksi (boleh menyusul untuk pensiun otomatis, lihat PensiunService::catatSk).
            $table->string('nomor_sk_berhenti', 100)->nullable()->after('alasan_berhenti');
            $table->date('tanggal_sk_berhenti')->nullable()->after('nomor_sk_berhenti');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['tanggal_berhenti']);
            $table->dropColumn(['tanggal_berhenti', 'alasan_berhenti', 'nomor_sk_berhenti', 'tanggal_sk_berhenti']);
        });
    }
};
