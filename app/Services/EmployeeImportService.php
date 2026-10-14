<?php

namespace App\Services;

use App\Imports\EmployeesImport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeImportService
{
    private const DISK = 'local'; // Maps to storage/app/private per filesystems.php & SECURITY.md 4.4

    private const CACHE_TTL_MINUTES = 60;

    /**
     * Memproses berkas Excel dalam mode Dry-Run (pratinjau) tanpa modifikasi database.
     */
    public function preview(UploadedFile $file): array
    {
        // 1. Pembersihan berkas sementara yang kedaluwarsa (> 2 jam)
        $this->purgeExpiredFiles(2);

        // 2. Simpan berkas sementara di disk privat
        $token = (string) Str::uuid();
        $storedPath = $file->storeAs('imports', "import_{$token}.xlsx", self::DISK);
        $fullPath = Storage::disk(self::DISK)->path($storedPath);

        // 3. Jalankan eksekusi Dry-Run
        $import = new EmployeesImport(dryRun: true);
        Excel::import($import, $fullPath);

        $summary = $import->getImportSummary();

        // 4. Cache metadata pratinjau untuk proses komit atau unduh baris gagal
        Cache::put("import_preview_{$token}", [
            'file_path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'summary' => $summary,
            'failed_rows' => $import->failedRows,
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return [
            'token' => $token,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'summary' => $summary,
        ];
    }

    /**
     * Mengeksekusi penulisan data yang valid dari sesi pratinjau ke database.
     */
    public function commit(string $token, ?User $user = null, ?string $ip = null, ?string $userAgent = null): array
    {
        $cached = Cache::get("import_preview_{$token}");
        if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
            throw new \RuntimeException('Sesi pratinjau impor telah kedaluwarsa atau berkas tidak ditemukan. Silakan unggah ulang berkas.');
        }

        $fullPath = Storage::disk(self::DISK)->path($cached['file_path']);
        $import = new EmployeesImport(dryRun: false);
        Excel::import($import, $fullPath);

        $summary = $import->getImportSummary();

        // Audit Trail Pencatatan Impor Massal Pegawai (UU PDP No. 27/2022 & Tata Kelola Keamanan BUMN)
        Log::info('HRIS Import Data Pegawai dieksekusi (via Dry-Run Commit)', [
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'file_name' => $cached['original_name'],
            'file_size' => $cached['file_size'],
            'created_count' => $summary['created'],
            'updated_count' => $summary['updated'],
            'skipped_count' => $summary['skipped'],
            'has_errors' => $summary['has_errors'],
            'timestamp' => now()->toIso8601String(),
        ]);

        // Bersihkan berkas sementara & cache
        Storage::disk(self::DISK)->delete($cached['file_path']);
        Cache::forget("import_preview_{$token}");

        return $summary;
    }

    /**
     * Mengambil daftar baris data yang gagal divalidasi dari sesi pratinjau.
     */
    public function getFailedRows(string $token): ?array
    {
        $cached = Cache::get("import_preview_{$token}");

        return $cached['failed_rows'] ?? null;
    }

    /**
     * Membersihkan berkas spreadsheet sementara yang ditinggalkan tanpa komit.
     */
    public function purgeExpiredFiles(int $hours = 2): int
    {
        $disk = Storage::disk(self::DISK);
        $files = $disk->files('imports');
        $deleted = 0;
        $threshold = now()->subHours($hours)->getTimestamp();

        foreach ($files as $file) {
            if ($disk->lastModified($file) < $threshold) {
                $disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
