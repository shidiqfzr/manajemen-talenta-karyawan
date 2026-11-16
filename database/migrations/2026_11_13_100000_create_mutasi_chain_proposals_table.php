<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Usulan Mutasi Berantai (SK Kolektif): saved drafts and the maker-checker approval trail (DOMAIN.md §5.2).
 * `payload` holds only the admin's inputs per participant, encrypted at rest (UU PDP No. 27/2022);
 * analysis results are always recomputed, never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutasi_chain_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->index();
            $table->string('nomor_sk', 100)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->date('tmt_awal')->nullable();
            $table->text('catatan')->nullable();
            $table->string('file_sk')->nullable();
            $table->longText('payload');
            $table->unsignedSmallInteger('participant_count')->default(0);
            $table->char('payload_digest', 64)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitted_by_name')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_by_name')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_reason')->nullable();
            $table->uuid('batch_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('mutasi_chain_proposal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('mutasi_chain_proposals')->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutasi_chain_proposal_events');
        Schema::dropIfExists('mutasi_chain_proposals');
    }
};
