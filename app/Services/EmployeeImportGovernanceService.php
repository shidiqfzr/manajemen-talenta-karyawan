<?php

namespace App\Services;

use App\Exceptions\ImportAbortedException;
use App\Models\Employee;
use App\Models\EmployeeImportChange;
use App\Models\EmployeeImportLog;
use App\Models\JobHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Tata kelola impor data karyawan di atas EmployeeImportService:
 *  - kebijakan empat mata (maker-checker): pengaju mengajukan hasil pratinjau, admin LAIN menyetujui/menolak;
 *  - pembatalan (undo) impor yang sudah tersimpan, atomik dan hanya bila tidak ada perubahan susulan;
 *  - pembersihan pengajuan kedaluwarsa dan rekaman data sebelum impor (UU PDP No. 27/2022).
 *
 * Seluruh pesan galat aman ditampilkan kepada admin (ImportAbortedException).
 */
class EmployeeImportGovernanceService
{
    private const DISK = 'local';

    private const APPROVAL_DIR = 'import_approvals';

    private const REVIEW_CACHE_MINUTES = 10;

    /** Kata yang harus diketik untuk membatalkan impor besar. */
    public const CONFIRMATION_WORD = 'BATALKAN';

    public function __construct(private EmployeeImportService $imports) {}

    public static function requiresApproval(): bool
    {
        return (bool) config('hris.import.require_approval', false);
    }

    /* ------------------------------------------------------------------ pengajuan (maker) */

    /**
     * Mengajukan hasil pratinjau untuk persetujuan. Berkas dipindahkan ke penyimpanan pengajuan di disk privat
     * dan tidak ada data karyawan yang berubah sampai admin lain menyetujui.
     *
     * @param  array<string, mixed>  $cached  Sesi pratinjau (berkas, hash, ringkasan)
     */
    public function submit(array $cached, string $token, User $maker, ?string $ip, ?string $userAgent): array
    {
        if (EmployeeImportLog::where('token', $token)->exists()) {
            throw new ImportAbortedException('Berkas ini sudah diajukan untuk persetujuan.');
        }

        $disk = Storage::disk(self::DISK);
        $source = $cached['file_path'];
        $hash = (string) hash_file('sha256', $disk->path($source));
        if (! is_string($cached['file_hash'] ?? null) || ! hash_equals($cached['file_hash'], $hash)) {
            throw new ImportAbortedException('Berkas impor berubah sejak pratinjau. Silakan unggah ulang berkas dan periksa kembali pratinjaunya.');
        }

        $target = self::APPROVAL_DIR.'/'.$token.'.xlsx';
        if (! $disk->copy($source, $target)) {
            throw new ImportAbortedException('Pengajuan gagal disimpan. Silakan coba lagi atau hubungi administrator sistem.');
        }

        $summary = $cached['summary'] ?? [];

        try {
            $log = EmployeeImportLog::create([
                'user_id' => $maker->id,
                'user_name' => $maker->name,
                'user_email' => $maker->email,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'file_name' => $cached['original_name'] ?? 'unknown.xlsx',
                'file_size' => (int) ($cached['file_size'] ?? 0),
                'file_sha256' => $hash,
                'stored_path' => $target,
                'total_rows' => $summary['total_processed'] ?? 0,
                'created_count' => $summary['created'] ?? 0,
                'updated_count' => $summary['updated'] ?? 0,
                'unchanged_count' => $summary['unchanged'] ?? 0,
                'skipped_count' => $summary['skipped'] ?? 0,
                'has_errors' => $summary['has_errors'] ?? false,
                'status' => EmployeeImportLog::STATUS_PENDING,
                'submitted_at' => now(),
                'token' => $token,
                'metadata' => ['file_sha256' => $hash, 'warning_count' => $summary['warning_count'] ?? 0],
            ]);
        } catch (\Throwable $e) {
            $disk->delete($target);

            throw $e;
        }

        // Berkas sementara pratinjau diganti berkas pengajuan; sesi pratinjau selesai.
        $disk->delete($source);
        Cache::forget("import_preview_{$token}");

        Log::info('HRIS Impor Data Karyawan diajukan untuk persetujuan', [
            'batch_id' => $log->id, 'maker_id' => $maker->id, 'ip_address' => $ip, 'file_sha256' => $hash,
        ]);

        return [
            'pending_approval' => true,
            'batch_id' => $log->id,
            'is_async' => false,
            'summary' => $summary,
        ];
    }

    /* ------------------------------------------------------------------ peninjauan & keputusan (checker) */

    /**
     * Pratinjau terkini atas berkas pengajuan terhadap Master Formasi saat ini.
     *
     * @return array{summary: array, digest: string}
     */
    public function review(EmployeeImportLog $log, bool $fresh = false): array
    {
        $this->assertStatus($log, [EmployeeImportLog::STATUS_PENDING]);

        $cacheKey = "import_review_{$log->token}";
        if (! $fresh && is_string($cached = Cache::get($cacheKey))) {
            return Crypt::decrypt($cached);
        }

        $summary = $this->dryRunStoredFile($log);
        $review = ['summary' => $summary, 'digest' => $this->digest($summary, (string) $log->file_sha256)];
        Cache::put($cacheKey, Crypt::encrypt($review), now()->addMinutes(self::REVIEW_CACHE_MINUTES));

        return $review;
    }

    /**
     * Menyetujui dan menyimpan. Penyetuju harus admin lain (bukan pengaju), dan hasil pratinjau saat ini harus
     * sama dengan yang ditinjaunya ($reviewedDigest), sehingga yang disetujui adalah yang tersimpan.
     *
     * @return array<string, mixed> Ringkasan komit (is_async bila diproses antrean)
     */
    public function approve(EmployeeImportLog $log, User $checker, string $reviewedDigest, ?string $ip, ?string $userAgent): array
    {
        // Kedaluwarsa dicatat di luar transaksi persetujuan agar tidak ikut dibatalkan oleh galat yang menyusul.
        $current = EmployeeImportLog::findOrFail($log->id);
        if ($current->status === EmployeeImportLog::STATUS_PENDING && $this->isPastApprovalDeadline($current)) {
            $this->expire($current);

            throw new ImportAbortedException('Pengajuan impor sudah kedaluwarsa. Minta pengaju mengunggah ulang berkas.');
        }

        $log = DB::transaction(function () use ($log, $checker, $ip): EmployeeImportLog {
            $locked = EmployeeImportLog::lockForUpdate()->findOrFail($log->id);
            $this->assertStatus($locked, [EmployeeImportLog::STATUS_PENDING]);

            if ($locked->user_id === $checker->id) {
                throw new ImportAbortedException('Pengajuan impor tidak dapat disetujui oleh pengajunya sendiri. Persetujuan harus dilakukan admin lain (prinsip empat mata).');
            }

            $locked->update([
                'status' => EmployeeImportLog::STATUS_APPROVED,
                'checker_id' => $checker->id,
                'checker_name' => $checker->name,
                'decided_at' => now(),
                'decided_ip' => $ip,
            ]);

            return $locked;
        });

        try {
            $summary = $this->dryRunStoredFile($log);
            if (! hash_equals($reviewedDigest, $this->digest($summary, (string) $log->file_sha256))) {
                Cache::forget("import_review_{$log->token}");

                throw new ImportAbortedException('Hasil pratinjau berubah sejak Anda meninjau (data master atau berkas berbeda). Tinjau ulang sebelum menyetujui.');
            }

            return $this->imports->commitApproved($log->token, $checker, $ip, $userAgent);
        } catch (\Throwable $e) {
            $this->releaseApproval($log->token);

            if ($e instanceof ImportAbortedException) {
                throw $e;
            }

            Log::error('Persetujuan impor karyawan gagal dieksekusi: '.$e->getMessage(), ['batch_id' => $log->id, 'exception' => $e::class]);

            throw new ImportAbortedException('Persetujuan gagal diproses karena kendala sistem. Tidak ada data yang tersimpan, silakan coba lagi atau hubungi administrator sistem.', 0, $e);
        }
    }

    /**
     * Menolak pengajuan (atau menarik kembali oleh pengaju). Alasan wajib dan tercatat.
     */
    public function reject(EmployeeImportLog $log, User $actor, string $reason, ?string $ip): void
    {
        $reason = $this->requireReason($reason);

        DB::transaction(function () use ($log, $actor, $reason, $ip): void {
            $locked = EmployeeImportLog::lockForUpdate()->findOrFail($log->id);
            $this->assertStatus($locked, [EmployeeImportLog::STATUS_PENDING]);

            $this->deleteStoredFile($locked);
            $locked->update([
                'status' => EmployeeImportLog::STATUS_REJECTED,
                'checker_id' => $actor->id,
                'checker_name' => $actor->name,
                'decided_at' => now(),
                'decided_ip' => $ip,
                'decision_note' => $reason,
                'stored_path' => null,
            ]);
        });

        Cache::forget("import_review_{$log->token}");
        Log::info('HRIS Pengajuan impor karyawan ditolak', ['batch_id' => $log->id, 'actor_id' => $actor->id]);
    }

    /**
     * Mengembalikan status persetujuan ke menunggu bila eksekusi penyimpanan gagal (agar dapat diulang).
     */
    public function releaseApproval(string $token): void
    {
        EmployeeImportLog::where('token', $token)->where('status', EmployeeImportLog::STATUS_APPROVED)->update([
            'status' => EmployeeImportLog::STATUS_PENDING,
            'checker_id' => null,
            'checker_name' => null,
            'decided_at' => null,
            'decided_ip' => null,
        ]);
    }

    /* ------------------------------------------------------------------ pembatalan (undo) */

    /**
     * Membatalkan impor yang sudah tersimpan: karyawan baru dihapus, karyawan yang diperbarui dikembalikan ke kondisi
     * sebelum impor, tugas rangkap hasil impor dibatalkan (soft delete beserta pelaku dan alasan, riwayat tidak
     * dihapus). Atomik: bila satu karyawan sudah berubah setelah impor, tidak ada data yang diubah.
     *
     * @return array{deleted: int, restored: int}
     */
    public function rollback(EmployeeImportLog $log, User $actor, string $reason, ?string $ip, ?string $confirmation = null): array
    {
        $reason = $this->requireReason($reason);

        if ($this->requiresTypedConfirmation($log) && strcasecmp(trim((string) $confirmation), self::CONFIRMATION_WORD) !== 0) {
            throw new ImportAbortedException('Pembatalan ini menyentuh '.($log->created_count + $log->updated_count).' karyawan. Ketik kata '.self::CONFIRMATION_WORD.' untuk mengonfirmasi.');
        }

        try {
            $result = DB::transaction(function () use ($log, $actor, $reason, $ip): array {
                $locked = EmployeeImportLog::lockForUpdate()->findOrFail($log->id);
                $this->assertStatus($locked, [EmployeeImportLog::STATUS_COMPLETED]);

                if (! $locked->isWithinUndoWindow()) {
                    throw new ImportAbortedException('Masa pembatalan impor ini sudah berakhir ('.config('hris.import.undo_window_days').' hari sejak disimpan).');
                }

                $changes = $locked->changes()->orderBy('id')->get();
                if ($changes->isEmpty()) {
                    throw new ImportAbortedException('Tidak ada rekaman perubahan yang dapat dibatalkan untuk impor ini.');
                }

                $conflicts = $this->findConflicts($changes);
                if ($conflicts !== []) {
                    throw new ImportAbortedException(sprintf(
                        'Pembatalan dihentikan: %d karyawan sudah berubah atau memiliki data turunan setelah impor (%s). Tidak ada data yang diubah.',
                        count($conflicts),
                        implode(', ', array_slice($conflicts, 0, 5)).(count($conflicts) > 5 ? ', ...' : '')
                    ));
                }

                $deleted = 0;
                $restored = 0;
                foreach ($changes as $change) {
                    $employee = Employee::findOrFail($change->nik);

                    if ($change->action === 'CREATE') {
                        $employee->delete();
                        $deleted++;

                        continue;
                    }

                    $this->revertJobs($change->jobs ?? [], $locked, $actor);
                    $employee->historyNote = "Pembatalan impor Excel #{$locked->id} oleh {$actor->name}: {$reason}";
                    $employee->forceFill($change->before ?? [])->save();
                    $restored++;
                }

                $locked->changes()->delete();
                $locked->update([
                    'status' => EmployeeImportLog::STATUS_ROLLED_BACK,
                    'rolled_back_at' => now(),
                    'rolled_back_by' => $actor->id,
                    'rolled_back_by_name' => $actor->name,
                    'rollback_reason' => $reason,
                    'metadata' => array_merge($locked->metadata ?? [], ['rollback' => ['deleted' => $deleted, 'restored' => $restored, 'ip_address' => $ip]]),
                ]);

                return ['deleted' => $deleted, 'restored' => $restored];
            });
        } catch (ImportAbortedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Pembatalan impor karyawan gagal dan di-rollback: '.$e->getMessage(), ['batch_id' => $log->id, 'exception' => $e::class]);

            throw new ImportAbortedException('Pembatalan gagal diproses karena kendala sistem. Tidak ada data yang diubah, silakan coba lagi atau hubungi administrator sistem.', 0, $e);
        }

        Log::info('HRIS Impor data karyawan dibatalkan', ['batch_id' => $log->id, 'actor_id' => $actor->id, 'ip_address' => $ip] + $result);

        return $result;
    }

    /**
     * Pemeriksaan kesiapan pembatalan tanpa mengubah data: berapa karyawan yang belum berubah sejak impor.
     *
     * @return array{total: int, conflicts: list<string>, ready: bool}
     */
    public function rollbackReadiness(EmployeeImportLog $log): array
    {
        $changes = $log->changes()->orderBy('id')->get();
        $conflicts = $this->findConflicts($changes);

        return ['total' => $changes->count(), 'conflicts' => $conflicts, 'ready' => $changes->isNotEmpty() && $conflicts === []];
    }

    /**
     * Pembatalan yang menyentuh banyak karyawan wajib dikonfirmasi dengan mengetik kata kunci.
     */
    public function requiresTypedConfirmation(EmployeeImportLog $log): bool
    {
        $threshold = (int) config('hris.import.typed_confirmation_threshold', 50);

        return $threshold > 0 && ($log->created_count + $log->updated_count) >= $threshold;
    }

    /**
     * Mengelompokkan baris yang dilewati menurut alasan umumnya (nilai spesifik baris, yaitu teks dalam tanda
     * kutip dan angka, diganti penanda) agar administrator melihat pola kesalahan, bukan ratusan pesan serupa.
     *
     * @param  list<array{row: int|string|null, errors: list<string>}>  $errors
     * @return list<array{reason: string, count: int, rows: list<int|string>, sample: string}>
     */
    public static function groupSkippedRows(array $errors): array
    {
        $groups = [];

        foreach ($errors as $error) {
            $message = (string) ($error['errors'][0] ?? 'Alasan tidak tercatat.');
            $generic = preg_replace(["/'[^']*'/u", '/\d+/'], ["'…'", '…'], $message) ?? $message;
            $reason = trim(strtok($generic, '.') ?: $generic);

            $groups[$reason] ??= ['reason' => $reason, 'count' => 0, 'rows' => [], 'sample' => $message];
            $groups[$reason]['count']++;
            $groups[$reason]['rows'][] = $error['row'] ?? '-';
        }

        usort($groups, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        return array_values($groups);
    }

    /* ------------------------------------------------------------------ pembersihan */

    /**
     * Pengajuan yang tidak diputuskan dalam batas waktu menjadi kedaluwarsa (berkas dihapus), dan rekaman data
     * sebelum impor yang melewati masa pembatalan dihapus.
     *
     * @return array{expired: int, snapshots: int}
     */
    public function purgeExpired(): array
    {
        $expired = 0;
        EmployeeImportLog::where('status', EmployeeImportLog::STATUS_PENDING)
            ->where('submitted_at', '<', now()->subDays((int) config('hris.import.approval_ttl_days', 7)))
            ->get()
            ->each(function (EmployeeImportLog $log) use (&$expired): void {
                $this->expire($log);
                $expired++;
            });

        $snapshots = EmployeeImportChange::where('created_at', '<', now()->subDays((int) config('hris.import.undo_window_days', 7)))->delete();

        return ['expired' => $expired, 'snapshots' => $snapshots];
    }

    /* ------------------------------------------------------------------ internal */

    /**
     * @param  Collection<int, EmployeeImportChange>  $changes
     * @return list<string> NIK tersamar karyawan yang berkonflik
     */
    private function findConflicts($changes): array
    {
        $conflicts = [];

        foreach ($changes as $change) {
            $employee = Employee::find($change->nik);

            $conflict = $employee === null || ! hash_equals($change->after_hash, Employee::stateHash($employee));

            if (! $conflict && $change->action === 'CREATE') {
                // Karyawan baru hanya boleh dihapus bila belum memiliki data turunan setelah impor.
                $conflict = $employee->evaluations()->exists()
                    || $employee->trainings()->exists()
                    || JobHistory::withTrashed()->where('employee_nik', $employee->nik)->where('created_at', '>', $change->created_at)->exists();
            }

            if ($conflict) {
                $conflicts[] = substr($change->nik, 0, 2).'****'.substr($change->nik, -2);
            }
        }

        return $conflicts;
    }

    /**
     * @param  list<array{id: int, created: bool, before: ?array}>  $jobs
     */
    private function revertJobs(array $jobs, EmployeeImportLog $log, User $actor): void
    {
        foreach ($jobs as $job) {
            $history = JobHistory::find($job['id'] ?? null);
            if (! $history) {
                continue;
            }

            if ($job['created'] ?? false) {
                // Dibatalkan, bukan dihapus permanen: riwayat tetap ada beserta pelaku dan alasan.
                $history->update(['cancelled_by' => $actor->id, 'alasan_pembatalan' => "Pembatalan impor Excel #{$log->id}"]);
                $history->delete();
            } elseif (is_array($job['before'] ?? null)) {
                $history->update($job['before']);
            }
        }
    }

    /**
     * Pratinjau (dry-run) atas berkas pengajuan; berkas harus masih identik dengan yang diajukan.
     */
    private function dryRunStoredFile(EmployeeImportLog $log): array
    {
        $disk = Storage::disk(self::DISK);
        if (! $log->stored_path || ! $disk->exists($log->stored_path)) {
            throw new ImportAbortedException('Berkas pengajuan tidak ditemukan. Minta pengaju mengunggah ulang berkas.');
        }

        $fullPath = $disk->path($log->stored_path);
        if (! hash_equals((string) $log->file_sha256, (string) hash_file('sha256', $fullPath))) {
            throw new ImportAbortedException('Berkas pengajuan berubah sejak diajukan dan tidak dapat diproses.');
        }

        return $this->imports->executeDryRun($fullPath, $log->stored_path, $log->file_name, $log->file_size, $log->token);
    }

    /**
     * Sidik jari hasil pratinjau yang ditinjau: jumlah per aksi, peringatan, dan berkas.
     */
    private function digest(array $summary, string $fileHash): string
    {
        return hash('sha256', json_encode([
            $summary['created'] ?? 0, $summary['updated'] ?? 0, $summary['unchanged'] ?? 0,
            $summary['skipped'] ?? 0, $summary['warning_count'] ?? 0, $fileHash,
        ]));
    }

    private function expire(EmployeeImportLog $log): void
    {
        $this->deleteStoredFile($log);
        $log->update([
            'status' => EmployeeImportLog::STATUS_EXPIRED,
            'decided_at' => now(),
            'decision_note' => 'Kedaluwarsa otomatis: tidak diputuskan dalam batas waktu persetujuan.',
            'stored_path' => null,
        ]);
        Cache::forget("import_review_{$log->token}");
    }

    private function isPastApprovalDeadline(EmployeeImportLog $log): bool
    {
        return $log->submitted_at !== null
            && $log->submitted_at->lt(now()->subDays((int) config('hris.import.approval_ttl_days', 7)));
    }

    private function deleteStoredFile(EmployeeImportLog $log): void
    {
        if ($log->stored_path) {
            Storage::disk(self::DISK)->delete($log->stored_path);
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertStatus(EmployeeImportLog $log, array $allowed): void
    {
        if (! in_array($log->status, $allowed, true)) {
            throw new ImportAbortedException('Tindakan tidak dapat dilakukan: status impor saat ini "'.$log->statusLabel().'".');
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10) {
            throw new ImportAbortedException('Alasan wajib diisi (minimal 10 karakter) agar tercatat pada jejak audit.');
        }

        return mb_substr($reason, 0, 500);
    }
}
