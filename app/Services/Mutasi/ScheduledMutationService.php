<?php

namespace App\Services\Mutasi;

use App\Exceptions\MutasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lifecycle of future-dated mutations (is_applied = false): activation on TMT, on-demand
 * activation of one SK, and audit-preserving cancellation.
 */
class ScheduledMutationService
{
    public function __construct(
        private readonly FormationService $formations,
        private readonly PlacementWriter $placement,
    ) {}

    /**
     * Activate scheduled mutations whose TMT has arrived (due on or before $targetDate).
     * Atomically transitions employee master profiles and closes previous job histories.
     *
     * @return array Summary of activated mutations
     */
    public function applyEffectiveMutations(?string $targetDate = null): array
    {
        $date = $targetDate ?? Carbon::today()->toDateString();

        return DB::transaction(function () use ($date) {
            $dueJobs = JobHistory::with('employee')
                ->where('is_applied', false)
                ->whereDate('tmt_awal', '<=', $date)
                ->orderBy('tmt_awal')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            return $this->activateScheduledJobs($dueJobs);
        });
    }

    /**
     * Activate one specific scheduled mutation on demand (ahead of or on its TMT).
     * Batch members (swap pair / collective SK) are always activated together so the
     * formation never ends up half-applied. Unrelated scheduled mutations are untouched.
     * Idempotent: an already applied mutation yields activated_count = 0.
     */
    public function applyScheduledMutation(int $jobHistoryId): array
    {
        return DB::transaction(function () use ($jobHistoryId) {
            $target = JobHistory::with('employee')->lockForUpdate()->findOrFail($jobHistoryId);

            if ($target->is_applied) {
                return ['activated_count' => 0, 'items' => []];
            }

            $jobs = $target->batch_id
                ? JobHistory::with('employee')
                    ->where('batch_id', $target->batch_id)
                    ->where('is_applied', false)
                    ->orderBy('chain_order')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                : collect([$target]);

            return $this->activateScheduledJobs($jobs);
        });
    }

    /**
     * Transition staged job histories into effect: close previous definitive jobs,
     * update the employee master record, and flag the rows as applied.
     * Must be called inside a DB transaction.
     */
    private function activateScheduledJobs(Collection $jobs): array
    {
        $activated = [];

        foreach ($jobs as $job) {
            $emp = Employee::with('currentJob')
                ->where('nik', $job->employee_nik)
                ->lockForUpdate()
                ->first();

            if (! $emp) {
                continue;
            }

            $tmtAkhir = Carbon::parse($job->tmt_awal)->subDay()->toDateString();

            // 1. Close previous definitive job history if new one is Definitif
            if ($job->status_penugasan === 'Definitif' && $emp->currentJob) {
                $emp->currentJob->update(['tmt_akhir' => $tmtAkhir]);
            }

            // 2. Update employee master record (RM & Karpim/Karpel derived from the staged position)
            if ($job->status_penugasan === 'Definitif') {
                $targetRm = $this->formations->resolvePositionRm($job->jabatan);

                $this->placement->apply($emp, $job->jabatan, $job->unit_kerja, $job->tmt_awal->toDateString(), [
                    'rm_level' => $targetRm,
                    'level' => $this->formations->deriveLevelForRm($targetRm, $emp->level),
                    'golongan' => $job->golongan ?: $emp->golongan,
                    'job_grade' => $job->job_grade ?: $emp->job_grade,
                    'status_penugasan' => $job->status_penugasan,
                    'tanggal_berakhir_sanksi' => $job->tanggal_berakhir_sanksi,
                ]);
            }

            // 3. Mark job history as applied
            $job->update(['is_applied' => true]);

            Log::info("Auto-Runner: Mutasi NIK {$emp->nik} ({$emp->nama}) berhasil diaktifkan per TMT {$job->tmt_awal->toDateString()} ke jabatan {$job->jabatan} ({$job->unit_kerja}).");

            $activated[] = [
                'id' => $job->id,
                'batch_id' => $job->batch_id,
                'employee_nik' => $emp->nik,
                'nama' => $emp->nama,
                'jabatan' => $job->jabatan,
                'unit_kerja' => $job->unit_kerja,
                'tmt_awal' => $job->tmt_awal->toDateString(),
                'jenis_mutasi' => $job->jenis_mutasi,
            ];
        }

        return [
            'activated_count' => count($activated),
            'items' => $activated,
        ];
    }

    /**
     * Safely cancel a scheduled mutation that has not yet been applied.
     * If the mutation belongs to a batch (swap pair / collective chain), cancels the entire batch.
     * Rows are soft-deleted with canceller & reason so the audit trail is preserved (SECURITY.md §5).
     *
     * @throws MutasiRuleViolationException if mutation is already applied
     */
    public function cancelScheduledMutation(int|string $identifier, ?string $reason = null, ?int $cancelledBy = null): array
    {
        $cancelledBy ??= auth()->id();

        return DB::transaction(function () use ($identifier, $reason, $cancelledBy) {
            if (is_numeric($identifier)) {
                $target = JobHistory::findOrFail((int) $identifier);
                if ($target->is_applied) {
                    throw new MutasiRuleViolationException('Mutasi yang sudah diterapkan (aktif) tidak dapat dibatalkan melalui fitur ini.');
                }
                if ($target->batch_id) {
                    $jobsToCancel = JobHistory::where('batch_id', $target->batch_id)
                        ->where('is_applied', false)
                        ->get();
                } else {
                    $jobsToCancel = collect([$target]);
                }
            } else {
                $jobsToCancel = JobHistory::where('batch_id', (string) $identifier)
                    ->where('is_applied', false)
                    ->get();
            }

            if ($jobsToCancel->isEmpty()) {
                throw new MutasiRuleViolationException('Tidak ditemukan data mutasi terjadwal yang belum diterapkan dengan identifikasi tersebut.');
            }

            $count = $jobsToCancel->count();
            $first = $jobsToCancel->first();
            $batchId = $first->batch_id;
            $nomorSk = $first->nomor_sk;

            foreach ($jobsToCancel as $job) {
                $job->update([
                    'cancelled_by' => $cancelledBy,
                    'alasan_pembatalan' => $reason,
                ]);
                $job->delete();
            }

            Log::info("Pembatalan mutasi terjadwal: {$count} riwayat (SK: ".($nomorSk ?? '—').') dibatalkan oleh user #'.($cancelledBy ?? 'system').'.', [
                'job_history_ids' => $jobsToCancel->pluck('id')->all(),
                'batch_id' => $batchId,
                'reason' => $reason,
            ]);

            return [
                'cancelled_count' => $count,
                'batch_id' => $batchId,
                'nomor_sk' => $nomorSk,
                'reason' => $reason,
            ];
        });
    }
}
