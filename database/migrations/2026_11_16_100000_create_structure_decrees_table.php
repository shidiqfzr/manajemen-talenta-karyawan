<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Surat Keputusan (SK) struktur organisasi: satu baris per SK beserta selisih, hasil baca lampiran,
     * dan snapshot struktur hasil penerapan (DOMAIN.md §3.7).
     */
    public function up(): void
    {
        Schema::create('structure_decrees', function (Blueprint $table) {
            $table->id();
            // Tidak unik di basis data: nomor SK yang ditolak atau dibatalkan boleh dipakai lagi; keunikan di antara SK
            // lain dijaga oleh StructureDecreeService.
            $table->string('sk_number')->index()->comment('Nomor SK ternormalisasi tanpa spasi di sekitar garis miring');
            $table->string('title', 500)->comment('Baris "Tentang" pada SK');
            $table->text('revokes')->nullable()->comment('SK yang dicabut atau digantikan');
            $table->text('note')->nullable();
            $table->date('sk_date');
            $table->date('effective_date');
            $table->string('status', 20)->index();
            $table->string('source_path');
            $table->string('source_sha256', 64);
            $table->string('pdf_path')->nullable();
            $table->json('target')->nullable()->comment('Struktur hasil baca lampiran');
            $table->json('diff')->nullable();
            $table->json('unit_mapping')->nullable()->comment('mapping, closed, new_units, positions');
            $table->string('review_digest', 64)->nullable();
            $table->json('snapshot')->nullable()->comment('Struktur hasil setelah diterapkan');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamps();
        });

        Schema::table('master_audit_logs', function (Blueprint $table) {
            $table->foreignId('decree_id')->nullable()->after('reason')->constrained('structure_decrees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('master_audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decree_id');
        });

        Schema::dropIfExists('structure_decrees');
    }
};
