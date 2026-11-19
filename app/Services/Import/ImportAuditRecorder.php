<?php

namespace App\Services\Import;

use App\Models\EmployeeImportLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Jejak audit Impor Data Karyawan: catatan komit persisten (`employee_import_logs`) dan log unduhan berkas yang memuat data
 * pribadi. Ringkasan yang disimpan permanen tidak memuat NIK atau nama (UU PDP No. 27/2022): hanya hitungan dan pesan galat
 * per nomor baris, dengan NIK pada pesan disamarkan.
 */
class ImportAuditRecorder
{
    /**
     * Mencatat komit. Impor yang disetujui memperbarui catatan pengajuan (pengaju tetap tercatat sebagai pengaju).
     *
     * @param  array<string, mixed>  $summary
     */
    public function record(array $summary, ?User $user, ?string $ip, ?string $userAgent, string $fileName, int $fileSize, ?string $token, string $fileHash, ?EmployeeImportLog $approvedLog = null): EmployeeImportLog
    {
        $result = [
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_sha256' => $fileHash,
            'stored_path' => null,
            'total_rows' => $summary['total_processed'] ?? 0,
            'created_count' => $summary['created'] ?? 0,
            'updated_count' => $summary['updated'] ?? 0,
            'unchanged_count' => $summary['unchanged'] ?? 0,
            'skipped_count' => $summary['skipped'] ?? 0,
            'has_errors' => $summary['has_errors'] ?? false,
            'status' => EmployeeImportLog::STATUS_COMPLETED,
            'committed_at' => now(),
            'token' => $token,
            'metadata' => [
                'file_sha256' => $fileHash,
                'summary' => $this->redactSummary($summary),
            ],
        ];

        if ($approvedLog) {
            $approvedLog->update($result);

            return $approvedLog;
        }

        return EmployeeImportLog::create($result + [
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System Administrator',
            'user_email' => $user?->email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Ringkasan untuk jejak audit persisten tanpa data pribadi (NIK, nama, baris contoh): hanya hitungan dan pesan galat per
     * nomor baris. Pesan galat dapat memuat NIK (mis. duplikat): disamarkan sebelum disimpan permanen.
     *
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public function redactSummary(array $summary): array
    {
        unset($summary['sample_rows']);
        $mask = fn (string $text): string => preg_replace_callback('/\b[0-9]{8}\b/', fn (array $m) => substr($m[0], 0, 2).'****'.substr($m[0], -2), $text) ?? $text;
        $summary['errors'] = array_map(
            fn (array $error) => ['row' => $error['row'] ?? null, 'errors' => array_map(fn ($message) => $mask((string) $message), $error['errors'] ?? [])],
            $summary['errors'] ?? []
        );

        return $summary;
    }

    /** Jejak audit unduhan berkas yang memuat data pribadi (laporan impor atau baris gagal), tanpa NIK atau nama. */
    public function downloaded(string $kind, string $token, ?User $user, ?string $ip, int $recordCount): void
    {
        Log::info('HRIS Unduh Berkas Impor Karyawan', [
            'kind' => $kind,
            'token' => $token,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $ip,
            'record_count' => $recordCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
