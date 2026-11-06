<?php

namespace App\Services;

use App\Exceptions\ImportAbortedException;
use App\Imports\EmployeesImport;
use App\Jobs\ProcessEmployeeImportCommitJob;
use App\Jobs\ProcessEmployeeImportPreviewJob;
use App\Models\EmployeeImportLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeeImportService
{
    private const DISK = 'local'; // Maps to storage/app/private per filesystems.php & SECURITY.md 4.4

    private const CACHE_TTL_MINUTES = 60;

    /**
     * Ambang batas jumlah baris data untuk beralih otomatis ke Background Queue Job (Hybrid Execution).
     */
    public const ASYNC_THRESHOLD = 500;

    /**
     * Membaca jumlah baris data spreadsheet secara cepat (tanpa memuat seluruh sel ke RAM).
     */
    public function getSpreadsheetRowCount(string $fullPath): int
    {
        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            $info = $reader->listWorksheetInfo($fullPath);
            $totalRows = $info[0]['totalRows'] ?? 0;

            return max(0, $totalRows - 1); // Kurangi baris 1 (Header)
        } catch (\Throwable $e) {
            Log::warning('Gagal membaca metadata jumlah baris spreadsheet: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * Memproses berkas Excel dalam mode Dry-Run (pratinjau) tanpa modifikasi database.
     * Menggunakan strategi Hybrid Execution: sinkron untuk berkas <= 500 baris,
     * dan otomatis beralih ke Background Queue Job untuk berkas > 500 baris.
     */
    public function preview(UploadedFile $file, ?User $user = null): array
    {
        // 1. Pembersihan berkas sementara yang kedaluwarsa (> 2 jam)
        $this->purgeExpiredFiles(2);

        // 2. Simpan berkas sementara di disk privat
        $token = (string) Str::uuid();
        $storedPath = $file->storeAs('imports', "import_{$token}.xlsx", self::DISK);
        $fullPath = Storage::disk(self::DISK)->path($storedPath);
        Cache::put("import_owner_{$token}", $user?->id ?? 0, now()->addHours(2));

        // 3. Deteksi volume data
        $dataRows = $this->getSpreadsheetRowCount($fullPath);

        // 4. Hybrid Execution Routing
        if ($dataRows > self::ASYNC_THRESHOLD) {
            Cache::put("import_job_{$token}", [
                'status' => 'processing',
                'progress' => 15,
                'message' => 'Menganalisis '.number_format($dataRows).' baris data pegawai di antrean latar belakang...',
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'total_rows' => $dataRows,
                'token' => $token,
                'type' => 'preview',
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));

            ProcessEmployeeImportPreviewJob::dispatch(
                $token,
                $storedPath,
                $file->getClientOriginalName(),
                $file->getSize()
            );

            return [
                'token' => $token,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'is_async' => true,
                'status' => 'processing',
                'total_rows' => $dataRows,
                'message' => 'Berkas berukuran besar ('.number_format($dataRows).' baris) sedang dianalisis di antrean latar belakang...',
            ];
        }

        // Eksekusi Sinkron untuk volume data wajar (<= 500 baris)
        try {
            $summary = $this->executeDryRun($fullPath, $storedPath, $file->getClientOriginalName(), $file->getSize(), $token);
        } catch (ImportAbortedException $e) {
            Storage::disk(self::DISK)->delete($storedPath);

            throw $e;
        }

        return [
            'token' => $token,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'summary' => $summary,
            'is_async' => false,
            'status' => 'completed',
        ];
    }

    /**
     * Sesi impor hanya boleh diakses admin yang mengunggah berkasnya. Token tanpa pemilik
     * (kedaluwarsa) dianggap boleh diteruskan agar pesan "sesi berakhir" tetap tampil.
     */
    public function isTokenOwnedBy(string $token, ?User $user): bool
    {
        $owner = Cache::get("import_owner_{$token}");

        return $owner === null || $owner === ($user?->id ?? 0);
    }

    /**
     * Eksekusi inti Dry-Run analisis pratinjau.
     */
    public function executeDryRun(string $fullPath, string $storedPath, string $originalName, int $fileSize, string $token): array
    {
        $import = new EmployeesImport(dryRun: true);
        $this->importSpreadsheet($import, $fullPath);

        $summary = $import->getImportSummary();

        // Cache metadata pratinjau untuk proses komit atau unduh baris gagal
        Cache::put("import_preview_{$token}", [
            'file_hash' => hash_file('sha256', $fullPath),
            'file_path' => $storedPath,
            'original_name' => $originalName,
            'file_size' => $fileSize,
            'summary' => $summary,
            'failed_rows' => $this->sealRows($import->failedRows),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        // Simpan baris gagal di cache terpisah dengan retensi 2 jam untuk unduhan berkas koreksi
        Cache::put("import_failed_rows_{$token}", $this->sealRows($import->failedRows), now()->addHours(2));

        // Update status job ke completed untuk sinkronisasi hybrid
        Cache::put("import_job_{$token}", [
            'status' => 'completed',
            'progress' => 100,
            'summary' => $summary,
            'token' => $token,
            'file_name' => $originalName,
            'file_size' => $fileSize,
            'type' => 'preview',
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $summary;
    }

    /**
     * Dijalankan oleh ProcessEmployeeImportPreviewJob pada worker antrean latar belakang.
     */
    public function processPreviewJob(string $token, string $storedPath, string $originalName, int $fileSize): array
    {
        try {
            $fullPath = Storage::disk(self::DISK)->path($storedPath);

            Cache::put("import_job_{$token}", [
                'status' => 'processing',
                'progress' => 40,
                'message' => 'Memvalidasi aturan bisnis kepegawaian...',
                'file_name' => $originalName,
                'file_size' => $fileSize,
                'token' => $token,
                'type' => 'preview',
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $this->executeDryRun($fullPath, $storedPath, $originalName, $fileSize, $token);
        } catch (\Throwable $e) {
            Log::error("Gagal menjalankan preview import background job [Token: {$token}]: ".$e->getMessage());

            Cache::put("import_job_{$token}", [
                'status' => 'failed',
                'error' => 'Gagal memproses pratinjau: '.$e->getMessage(),
                'token' => $token,
                'type' => 'preview',
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));

            throw $e;
        }
    }

    /**
     * Mengeksekusi penulisan data yang valid dari sesi pratinjau ke database secara parsial (terisolasi).
     */
    public function commit(
        string $token,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        $cached = Cache::get("import_preview_{$token}");
        if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
            throw new \RuntimeException('Sesi pratinjau impor telah kedaluwarsa atau berkas tidak ditemukan. Silakan unggah ulang berkas.');
        }

        // Satu token hanya boleh dikomit sekali: klik ganda atau permintaan paralel tidak boleh menulis dua kali.
        if (! Cache::add($this->commitGuardKey($token), true, now()->addMinutes(self::CACHE_TTL_MINUTES))) {
            throw new \RuntimeException('Komit untuk berkas ini sudah dijalankan atau sedang diproses.');
        }

        try {
            return $this->dispatchOrRunCommit($cached, $token, $user, $ip, $userAgent);
        } catch (\Throwable $e) {
            Cache::forget($this->commitGuardKey($token));

            throw $e;
        }
    }

    private function commitGuardKey(string $token): string
    {
        return "import_commit_started_{$token}";
    }

    /**
     * Menyimpan pengajuan yang sudah disetujui admin lain (dipanggil EmployeeImportGovernanceService).
     */
    public function commitApproved(string $token, User $checker, ?string $ip = null, ?string $userAgent = null): array
    {
        $cached = Cache::get("import_preview_{$token}");
        if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
            throw new ImportAbortedException('Berkas pengajuan tidak ditemukan atau sesi peninjauan berakhir. Tinjau ulang pengajuan.');
        }

        if (! Cache::add($this->commitGuardKey($token), true, now()->addMinutes(self::CACHE_TTL_MINUTES))) {
            throw new ImportAbortedException('Penyimpanan pengajuan ini sudah dijalankan atau sedang diproses.');
        }

        try {
            return $this->dispatchOrRunCommit($cached, $token, $checker, $ip, $userAgent, true);
        } catch (\Throwable $e) {
            Cache::forget($this->commitGuardKey($token));

            throw $e;
        }
    }

    private function dispatchOrRunCommit(array $cached, string $token, ?User $user, ?string $ip, ?string $userAgent, bool $approved = false): array
    {
        // Kebijakan empat mata: impor hanya diajukan, belum disimpan. Penyetuju memakai token yang sama.
        if (! $approved && EmployeeImportGovernanceService::requiresApproval()) {
            if ($user === null) {
                throw new ImportAbortedException('Pengajuan impor memerlukan pengguna yang masuk.');
            }

            $result = app(EmployeeImportGovernanceService::class)->submit($cached, $token, $user, $ip, $userAgent);
            Cache::forget($this->commitGuardKey($token));

            return $result;
        }

        $totalProcessed = $cached['summary']['total_processed'] ?? 0;

        // Hybrid Execution untuk Commit volume besar
        if ($totalProcessed > self::ASYNC_THRESHOLD) {
            Cache::put("import_commit_job_{$token}", [
                'status' => 'processing',
                'progress' => 15,
                'message' => 'Menyimpan '.number_format($totalProcessed).' data pegawai ke database di antrean latar belakang...',
                'token' => $token,
                'type' => 'commit',
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));

            ProcessEmployeeImportCommitJob::dispatch(
                $token,
                $user?->id,
                $ip,
                $userAgent
            );

            return [
                'is_async' => true,
                'status' => 'processing',
                'token' => $token,
                'message' => 'Penyimpanan data pegawai berukuran besar sedang dieksekusi di antrean latar belakang...',
            ];
        }

        $summary = $this->executeCommit($cached, $token, $user, $ip, $userAgent);
        $summary['is_async'] = false;

        return $summary;
    }

    /**
     * Eksekusi inti penulisan database untuk komit impor pegawai.
     */
    public function executeCommit(
        array $cached,
        string $token,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        // Penyimpanan nyata hanya sah untuk impor yang sudah disetujui bila kebijakan empat mata aktif.
        $approvedLog = EmployeeImportLog::where('token', $token)->where('status', EmployeeImportLog::STATUS_APPROVED)->first();
        if ($approvedLog === null && EmployeeImportGovernanceService::requiresApproval()) {
            throw new ImportAbortedException('Impor ini memerlukan persetujuan admin lain sebelum dapat disimpan.');
        }

        $fullPath = Storage::disk(self::DISK)->path($cached['file_path']);

        // Berkas yang ditulis harus identik dengan berkas yang sudah dipratinjau dan disetujui admin.
        $fileHash = (string) hash_file('sha256', $fullPath);
        if (! is_string($cached['file_hash'] ?? null) || ! hash_equals($cached['file_hash'], $fileHash)) {
            throw new \RuntimeException('Berkas impor berubah sejak pratinjau. Silakan unggah ulang berkas dan periksa kembali pratinjaunya.');
        }

        [$import, $summary] = $this->commitAtomically(
            $fullPath, $user, $ip, $userAgent, $cached['original_name'] ?? 'unknown.xlsx', (int) ($cached['file_size'] ?? 0), $token, $fileHash, $approvedLog
        );

        Log::info('HRIS Import Data Pegawai dieksekusi (via Dry-Run Commit)', [
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'file_name' => $cached['original_name'],
            'file_size' => $cached['file_size'],
            'created_count' => $summary['created'],
            'updated_count' => $summary['updated'],
            'unchanged_count' => $summary['unchanged'] ?? 0,
            'skipped_count' => $summary['skipped'],
            'has_errors' => $summary['has_errors'],
            'timestamp' => now()->toIso8601String(),
        ]);

        $failedRows = ! empty($import->failedRows) ? $import->failedRows : $this->openRows($cached['failed_rows'] ?? []);

        // Pertahankan baris gagal di cache terpisah untuk unduhan Tahap 3 (retensi 2 jam)
        if (! empty($failedRows)) {
            Cache::put("import_failed_rows_{$token}", $this->sealRows($failedRows), now()->addHours(2));
        }

        // Bersihkan berkas sementara & cache pratinjau
        Storage::disk(self::DISK)->delete($cached['file_path']);
        Cache::forget("import_preview_{$token}");

        // Simpan data rekap hasil komit untuk unduhan laporan impor (.xlsx)
        Cache::put("import_report_{$token}", [
            'user_name' => $user?->name ?? 'Administrator HR',
            'user_email' => $user?->email ?? '-',
            'ip_address' => $ip ?? '-',
            'timestamp' => now()->translatedFormat('d F Y H:i:s'),
            'file_name' => $cached['original_name'],
            'file_size' => $cached['file_size'],
            'summary' => $summary,
            'rows' => $this->sealRows($import->validRows),
            'failed_rows' => $this->sealRows($failedRows),
        ], now()->addHours(2));

        // Update commit job status ke completed
        Cache::put("import_commit_job_{$token}", [
            'status' => 'completed',
            'progress' => 100,
            'summary' => $summary,
            'token' => $token,
            'type' => 'commit',
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $summary;
    }

    /**
     * Menulis seluruh baris valid beserta jejak auditnya dalam SATU transaksi. Bila terjadi kendala sistem pada
     * salah satu baris, atau jejak audit gagal tercatat, seluruh komit dibatalkan dan tidak ada data yang tersimpan.
     * Baris yang melanggar aturan data tetap dilewati dan dilaporkan, sama seperti pada pratinjau.
     *
     * @return array{0: EmployeesImport, 1: array}
     */
    private function commitAtomically(
        string|UploadedFile $source,
        ?User $user,
        ?string $ip,
        ?string $userAgent,
        string $fileName,
        int $fileSize,
        ?string $token,
        string $fileHash,
        ?EmployeeImportLog $approvedLog = null
    ): array {
        try {
            return DB::transaction(function () use ($source, $user, $ip, $userAgent, $fileName, $fileSize, $token, $fileHash, $approvedLog): array {
                // Pada impor yang disetujui, catatan riwayat menyebut pengaju dan penyetuju.
                $actorName = $approvedLog ? "{$approvedLog->user_name} (disetujui {$user?->name})" : $user?->name;
                $import = new EmployeesImport(dryRun: false, actorName: $actorName);
                $this->importSpreadsheet($import, $source);

                if ($import->processingFailures > 0) {
                    throw new ImportAbortedException("Komit dibatalkan: {$import->processingFailures} baris gagal diproses akibat kendala sistem. Tidak ada data yang tersimpan, silakan coba lagi atau hubungi administrator sistem.");
                }

                $summary = $import->getImportSummary();
                $log = $this->recordAuditLog($summary, $user, $ip, $userAgent, $fileName, $fileSize, $token, $fileHash, $approvedLog);

                // Rekaman perubahan per pegawai disimpan dalam transaksi yang sama agar impor dapat dibatalkan.
                foreach ($import->changes as $change) {
                    $log->changes()->create($change);
                }

                return [$import, $summary];
            });
        } catch (ImportAbortedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Komit impor pegawai dibatalkan dan di-rollback: '.$e->getMessage(), ['exception' => $e::class]);

            throw new ImportAbortedException('Komit dibatalkan karena kendala sistem. Tidak ada data yang tersimpan, silakan coba lagi atau hubungi administrator sistem.', 0, $e);
        }
    }

    /**
     * Membaca spreadsheet; berkas yang tidak dapat dibaca menjadi galat yang aman ditampilkan, bukan galat 500.
     */
    private function importSpreadsheet(EmployeesImport $import, string|UploadedFile $source): void
    {
        try {
            Excel::import($import, $source);
        } catch (SpreadsheetException|NoTypeDetectedException $e) {
            Log::warning('Berkas impor pegawai tidak dapat dibaca: '.$e->getMessage());

            throw new ImportAbortedException('Berkas tidak dapat dibaca sebagai spreadsheet Excel. Pastikan berkas berasal dari template impor pegawai dan tidak rusak.', 0, $e);
        }
    }

    /**
     * Audit Trail Pencatatan Impor Massal Pegawai (UU PDP No. 27/2022 & Tata Kelola Keamanan BUMN).
     * Dipanggil di dalam transaksi komit; kegagalan mencatat membatalkan komit.
     */
    private function recordAuditLog(array $summary, ?User $user, ?string $ip, ?string $userAgent, string $fileName, int $fileSize, ?string $token, string $fileHash, ?EmployeeImportLog $approvedLog = null): EmployeeImportLog
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

        // Impor yang disetujui memperbarui catatan pengajuan (pengaju tetap tercatat sebagai pengaju).
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
     * Ringkasan untuk jejak audit persisten tanpa data pribadi (NIK, nama, baris contoh): hanya
     * hitungan dan pesan galat per nomor baris (UU PDP No. 27/2022).
     */
    private function redactSummary(array $summary): array
    {
        unset($summary['sample_rows']);
        // Pesan galat dapat memuat NIK (mis. duplikat): disamarkan sebelum disimpan permanen.
        $mask = fn (string $text): string => preg_replace_callback('/\b[0-9]{8}\b/', fn (array $m) => substr($m[0], 0, 2).'****'.substr($m[0], -2), $text) ?? $text;
        $summary['errors'] = array_map(
            fn (array $error) => ['row' => $error['row'] ?? null, 'errors' => array_map(fn ($message) => $mask((string) $message), $error['errors'] ?? [])],
            $summary['errors'] ?? []
        );

        return $summary;
    }

    /**
     * Dijalankan oleh ProcessEmployeeImportCommitJob pada worker antrean latar belakang.
     */
    public function processCommitJob(
        string $token,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        try {
            $cached = Cache::get("import_preview_{$token}");
            if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
                throw new \RuntimeException('Sesi pratinjau impor telah kedaluwarsa atau berkas tidak ditemukan.');
            }

            Cache::put("import_commit_job_{$token}", [
                'status' => 'processing',
                'progress' => 50,
                'message' => 'Sedang menulis data ke tabel master kepegawaian...',
                'token' => $token,
                'type' => 'commit',
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));

            return $this->executeCommit($cached, $token, $user, $ip, $userAgent);
        } catch (\Throwable $e) {
            Log::error("Gagal menjalankan commit import background job [Token: {$token}]: ".$e->getMessage());
            Cache::forget($this->commitGuardKey($token));
            app(EmployeeImportGovernanceService::class)->releaseApproval($token);

            Cache::put("import_commit_job_{$token}", [
                'status' => 'failed',
                'error' => 'Gagal mengeksekusi komit data: '.$e->getMessage(),
                'token' => $token,
                'type' => 'commit',
            ], now()->addMinutes(self::CACHE_TTL_MINUTES));

            throw $e;
        }
    }

    /**
     * Impor langsung terstandarisasi untuk konsolidasi rute legacy (DRY Principle).
     */
    public function directImport(
        UploadedFile $file,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        if (EmployeeImportGovernanceService::requiresApproval()) {
            throw new ImportAbortedException('Impor langsung dinonaktifkan karena kebijakan persetujuan empat mata aktif. Gunakan alur pratinjau lalu ajukan persetujuan.');
        }

        [, $summary] = $this->commitAtomically(
            $file, $user, $ip, $userAgent, $file->getClientOriginalName(), (int) $file->getSize(), null,
            (string) hash_file('sha256', (string) $file->getRealPath())
        );

        Log::info('HRIS Import Data Pegawai dieksekusi', [
            'channel' => 'direct_import',
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'created_count' => $summary['created'],
            'updated_count' => $summary['updated'],
            'unchanged_count' => $summary['unchanged'] ?? 0,
            'skipped_count' => $summary['skipped'],
            'has_errors' => $summary['has_errors'],
            'timestamp' => now()->toIso8601String(),
        ]);

        return $summary;
    }

    /**
     * Memeriksa status antrean kerja impor (pratinjau atau komit).
     */
    public function getJobStatus(string $token): ?array
    {
        return Cache::get("import_commit_job_{$token}")
            ?? Cache::get("import_job_{$token}");
    }

    /**
     * Mengambil daftar baris data yang gagal divalidasi dari sesi pratinjau maupun pasca-komit.
     */
    public function getFailedRows(string $token): ?array
    {
        $sealed = Cache::get("import_failed_rows_{$token}")
            ?? Cache::get("import_report_{$token}")['failed_rows']
            ?? Cache::get("import_preview_{$token}")['failed_rows']
            ?? null;

        return $sealed === null ? null : $this->openRows($sealed);
    }

    /**
     * Mengambil data rekapitulasi komit impor pegawai untuk diunduh sebagai laporan Excel.
     */
    public function getImportReport(string $token): ?array
    {
        $report = Cache::get("import_report_{$token}");

        if ($report === null) {
            return null;
        }

        $report['rows'] = $this->openRows($report['rows'] ?? []);
        $report['failed_rows'] = $this->openRows($report['failed_rows'] ?? []);

        return $report;
    }

    /**
     * Jejak audit unduhan berkas yang memuat data pribadi (laporan impor / baris gagal), tanpa NIK atau nama.
     */
    public function recordDownloadAudit(string $kind, string $token, ?User $user, ?string $ip, int $recordCount): void
    {
        Log::info('HRIS Unduh Berkas Impor Pegawai', [
            'kind' => $kind,
            'token' => $token,
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'ip_address' => $ip,
            'record_count' => $recordCount,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Baris impor memuat data pribadi: di cache disimpan terenkripsi (UU PDP No. 27/2022).
     */
    private function sealRows(array $rows): string
    {
        return Crypt::encrypt($rows);
    }

    /**
     * Membuka baris dari cache; nilai larik polos (cache lama) diteruskan apa adanya.
     */
    private function openRows(mixed $rows): array
    {
        return is_string($rows) ? Crypt::decrypt($rows) : (array) $rows;
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

    /**
     * Mengeksekusi auto-fallback ke sinkron jika worker antrean latar belakang tidak merespons (SLA Fail-Safe).
     */
    public function fallbackToSync(
        string $token,
        ?User $user = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): array {
        // 1. Cek jika token berada dalam sesi antrean commit
        if (Cache::has("import_commit_job_{$token}")) {
            $cached = Cache::get("import_preview_{$token}");
            if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
                throw new \RuntimeException('Sesi komit impor telah kedaluwarsa atau berkas tidak ditemukan.');
            }

            Log::info("Hybrid Fail-Safe: Mengalihkan komit ke pemrosesan sinkron [Token: {$token}]");

            $summary = $this->executeCommit($cached, $token, $user, $ip, $userAgent);
            $summary['is_async'] = false;
            $summary['fallback_executed'] = true;

            return $summary;
        }

        // 2. Cek jika token berada dalam sesi antrean preview
        if (Cache::has("import_job_{$token}")) {
            $jobData = Cache::get("import_job_{$token}");
            $storedPath = "imports/import_{$token}.xlsx";

            if (! Storage::disk(self::DISK)->exists($storedPath)) {
                throw new \RuntimeException('Berkas pratinjau impor tidak ditemukan.');
            }

            $fullPath = Storage::disk(self::DISK)->path($storedPath);
            $fileName = $jobData['file_name'] ?? "import_{$token}.xlsx";
            $fileSize = $jobData['file_size'] ?? Storage::disk(self::DISK)->size($storedPath);

            Log::info("Hybrid Fail-Safe: Mengalihkan pratinjau ke pemrosesan sinkron [Token: {$token}]");

            $summary = $this->executeDryRun($fullPath, $storedPath, $fileName, $fileSize, $token);

            return [
                'token' => $token,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'summary' => $summary,
                'is_async' => false,
                'fallback_executed' => true,
                'status' => 'completed',
            ];
        }

        throw new \RuntimeException('Sesi antrean tidak ditemukan untuk dieksekusi secara sinkron.');
    }
}
