<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Siklus hidup impor: pending_approval -> approved -> completed -> rolled_back (atau rejected / expired).
     * user_id adalah pengaju (maker); checker_id adalah penyetuju/penolak (checker).
     */
    public function up(): void
    {
        Schema::table('employee_import_logs', function (Blueprint $table) {
            $table->foreignId('checker_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->string('checker_name')->nullable()->after('user_email');
            $table->string('file_sha256', 64)->nullable()->after('file_size');
            $table->string('stored_path')->nullable()->after('file_sha256')->comment('Berkas pengajuan di disk privat selama menunggu keputusan');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('decided_at')->nullable();
            $table->string('decided_ip', 45)->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->foreignId('rolled_back_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rolled_back_by_name')->nullable();
            $table->text('rollback_reason')->nullable();
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_import_logs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropConstrainedForeignId('checker_id');
            $table->dropConstrainedForeignId('rolled_back_by');
            $table->dropColumn([
                'checker_name', 'file_sha256', 'stored_path', 'submitted_at', 'decided_at', 'decided_ip',
                'decision_note', 'committed_at', 'rolled_back_at', 'rolled_back_by_name', 'rollback_reason',
            ]);
        });
    }
};
