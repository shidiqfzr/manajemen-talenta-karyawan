<?php

namespace App\Services;

use App\Exceptions\ImportAbortedException;
use App\Imports\EmployeesImport;
use App\Jobs\ProcessEmployeeImportCommitJob;
use App\Jobs\ProcessEmployeeImportPreviewJob;
use App\Models\Employee;
use App\Models\EmployeeImportLog;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\Import\ImportAuditRecorder;
use App\Services\Import\ImportSessionStore;
use Illuminate\Http\UploadedFile;
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

    public function __construct(
        private readonly ImportSessionStore $sessions,
        private readonly ImportAuditRecorder $audit,
    ) {}

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
        $this->sessions->claim($token, $user);

        // 3. Deteksi volume data
        $dataRows = $this->getSpreadsheetRowCount($fullPath);

        // 4. Hybrid Execution Routing
        if ($dataRows > self::ASYNC_THRESHOLD) {
            $this->sessions->putJob($token, [
                'status' => 'processing',
                'progress' => 15,
                'message' => 'Menganalisis '.number_format($dataRows).' baris data karyawan di antrean latar belakang...',
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'total_rows' => $dataRows,
                'token' => $token,
                'type' => 'preview',
            ]);

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
     * Eksekusi inti Dry-Run analisis pratinjau.
     */
    public function executeDryRun(string $fullPath, string $storedPath, string $originalName, int $fileSize, string $token): array
    {
        $import = new EmployeesImport(dryRun: true);
        $this->importSpreadsheet($import, $fullPath);

        $summary = $import->getImportSummary();

        // Cache metadata pratinjau untuk proses komit atau unduh baris gagal
        $this->sessions->putPreview($token, [
            'file_hash' => hash_file('sha256', $fullPath),
            'file_path' => $storedPath,
            'original_name' => $originalName,
            'file_size' => $fileSize,
            'summary' => $summary,
            'failed_rows' => $this->sessions->sealRows($import->failedRows),
        ]);

        // Simpan baris gagal di cache terpisah dengan retensi 2 jam untuk unduhan berkas koreksi
        $this->sessions->putFailedRows($token, $import->failedRows);

        // Update status job ke completed untuk sinkronisasi hybrid
        $this->sessions->putJob($token, [
            'status' => 'completed',
            'progress' => 100,
            'summary' => $summary,
            'token' => $token,
            'file_name' => $originalName,
            'file_size' => $fileSize,
            'type' => 'preview',
        ]);

        return $summary;
    }

    /**
     * Dijalankan oleh ProcessEmployeeImportPreviewJob pada worker antrean latar belakang.
     */
    public function processPreviewJob(string $token, string $storedPath, string $originalName, int $fileSize): array
    {
        try {
            $fullPath = Storage::disk(self::DISK)->path($storedPath);

            $this->sessions->putJob($token, [
                'status' => 'processing',
                'progress' => 40,
                'message' => 'Memvalidasi aturan bisnis kepegawaian...',
                'file_name' => $originalName,
                'file_size' => $fileSize,
                'token' => $token,
                'type' => 'preview',
            ]);

            return $this->executeDryRun($fullPath, $storedPath, $originalName, $fileSize, $token);
        } catch (\Throwable $e) {
            Log::error("Gagal menjalankan preview import background job [Token: {$token}]: ".$e->getMessage());

            $this->sessions->putJob($token, [
                'status' => 'failed',
                'error' => 'Gagal memproses pratinjau: '.$e->getMessage(),
                'token' => $token,
                'type' => 'preview',
            ]);

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
        $cached = $this->sessions->preview($token);
        if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
            throw new \RuntimeException('Sesi pratinjau impor telah kedaluwarsa atau berkas tidak ditemukan. Silakan unggah ulang berkas.');
        }

        // Satu token hanya boleh dikomit sekali: klik ganda atau permintaan paralel tidak boleh menulis dua kali.
        if (! $this->sessions->beginCommit($token)) {
            throw new \RuntimeException('Komit untuk berkas ini sudah dijalankan atau sedang diproses.');
        }

        try {
            return $this->dispatchOrRunCommit($cached, $token, $user, $ip, $userAgent);
        } catch (\Throwable $e) {
            $this->sessions->releaseCommit($token);

            throw $e;
        }
    }

    /**
     * Menyimpan pengajuan yang sudah disetujui admin lain (dipanggil EmployeeImportGovernanceService).
     */
    public function commitApproved(string $token, User $checker, ?string $ip = null, ?string $userAgent = null): array
    {
        $cached = $this->sessions->preview($token);
        if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
            throw new ImportAbortedException('Berkas pengajuan tidak ditemukan atau sesi peninjauan berakhir. Tinjau ulang pengajuan.');
        }

        if (! $this->sessions->beginCommit($token)) {
            throw new ImportAbortedException('Penyimpanan pengajuan ini sudah dijalankan atau sedang diproses.');
        }

        try {
            return $this->dispatchOrRunCommit($cached, $token, $checker, $ip, $userAgent, true);
        } catch (\Throwable $e) {
            $this->sessions->releaseCommit($token);

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
            $this->sessions->releaseCommit($token);

            return $result;
        }

        $totalProcessed = $cached['summary']['total_processed'] ?? 0;

        // Hybrid Execution untuk Commit volume besar
        if ($totalProcessed > self::ASYNC_THRESHOLD) {
            $this->sessions->putCommitJob($token, [
                'status' => 'processing',
                'progress' => 15,
                'message' => 'Menyimpan '.number_format($totalProcessed).' data karyawan ke database di antrean latar belakang...',
                'token' => $token,
                'type' => 'commit',
            ]);

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
                'message' => 'Penyimpanan data karyawan berukuran besar sedang dieksekusi di antrean latar belakang...',
            ];
        }

        $summary = $this->executeCommit($cached, $token, $user, $ip, $userAgent);
        $summary['is_async'] = false;

        return $summary;
    }

    /**
     * Eksekusi inti penulisan database untuk komit impor karyawan.
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

        Log::info('HRIS Import Data Karyawan dieksekusi (via Dry-Run Commit)', [
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

        $failedRows = ! empty($import->failedRows) ? $import->failedRows : $this->sessions->openRows($cached['failed_rows'] ?? []);

        // Pertahankan baris gagal di cache terpisah untuk unduhan Tahap 3 (retensi 2 jam)
        if (! empty($failedRows)) {
            $this->sessions->putFailedRows($token, $failedRows);
        }

        // Bersihkan berkas sementara & cache pratinjau
        Storage::disk(self::DISK)->delete($cached['file_path']);
        $this->sessions->forgetPreview($token);

        // Simpan data rekap hasil komit untuk unduhan laporan impor (.xlsx)
        $this->sessions->putReport($token, [
            'user_name' => $user?->name ?? 'Administrator HR',
            'user_email' => $user?->email ?? '-',
            'ip_address' => $ip ?? '-',
            'timestamp' => now()->translatedFormat('d F Y H:i:s'),
            'file_name' => $cached['original_name'],
            'file_size' => $cached['file_size'],
            'summary' => $summary,
            'rows' => $this->sessions->sealRows($import->validRows),
            'failed_rows' => $this->sessions->sealRows($failedRows),
        ]);

        // Update commit job status ke completed
        $this->sessions->putCommitJob($token, [
            'status' => 'completed',
            'progress' => 100,
            'summary' => $summary,
            'token' => $token,
            'type' => 'commit',
        ]);

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

                // Karyawan yang tanggal pensiunnya sudah tiba langsung diberhentikan, dalam transaksi yang sama.
                $retired = $this->retireDueImportedEmployees($import, $user);

                $summary = $import->getImportSummary();
                $summary['retired'] = $retired;
                $log = $this->audit->record($summary, $user, $ip, $userAgent, $fileName, $fileSize, $token, $fileHash, $approvedLog);

                // Rekaman perubahan per karyawan disimpan dalam transaksi yang sama agar impor dapat dibatalkan.
                foreach ($import->changes as $change) {
                    $log->changes()->create($change);
                }

                return [$import, $summary];
            });
        } catch (ImportAbortedException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Komit impor karyawan dibatalkan dan di-rollback: '.$e->getMessage(), ['exception' => $e::class]);

            throw new ImportAbortedException('Komit dibatalkan karena kendala sistem. Tidak ada data yang tersimpan, silakan coba lagi atau hubungi administrator sistem.', 0, $e);
        }
    }

    /** Satu kalimat hasil impor (disimpan, atau komit selesai) untuk pesan keberhasilan pada layar daftar karyawan. */
    public static function resultMessage(array $summary): string
    {
        return "{$summary['created']} data baru ditambahkan, {$summary['updated']} data diperbarui, ".($summary['unchanged'] ?? 0)." data tetap/identik, {$summary['skipped']} baris dilewati.".self::retiredNote($summary);
    }

    /**
     * Kalimat tambahan untuk pesan hasil komit bila ada karyawan yang langsung diberhentikan karena pensiun.
     */
    public static function retiredNote(array $summary): string
    {
        $retired = (int) ($summary['retired'] ?? 0);

        return $retired > 0
            ? " {$retired} karyawan telah mencapai Batas Usia Pensiun dan otomatis dipindahkan ke arsip Pensiun."
            : '';
    }

    /**
     * Memberhentikan karyawan hasil impor yang tanggal pensiun efektifnya sudah tiba (DOMAIN.md §5.1), tanpa menunggu
     * pekerjaan harian `hr:process-retirements`, agar daftar aktif, arsip pensiun, dan kursi formasi langsung konsisten.
     * Hanya karyawan yang diubah impor ini yang diproses; sisanya tetap urusan pekerjaan harian. Berjalan di dalam
     * transaksi komit: sidik jari pembatalan diperbarui dan riwayat jabatan yang ditutup direkam, sehingga pembatalan
     * impor tetap dapat mengembalikan karyawan beserta jabatannya.
     *
     * @return int jumlah karyawan yang diberhentikan
     */
    private function retireDueImportedEmployees(EmployeesImport $import, ?User $user): int
    {
        $niks = array_values(array_unique(array_column($import->changes, 'nik')));
        if ($niks === []) {
            return 0;
        }

        $openBefore = JobHistory::query()->active()->whereIn('employee_nik', $niks)->get()
            ->groupBy('employee_nik')
            ->map(fn ($histories) => $histories->map(fn (JobHistory $h) => [
                'id' => $h->id,
                'created' => false,
                'before' => ['tmt_akhir' => null, 'catatan' => $h->catatan],
            ])->values()->all());

        $result = app(PensiunService::class)->processDue(
            null, false, $niks, $user, 'Diproses otomatis setelah impor Excel (tanggal pensiun sudah tiba)'
        );
        $retired = array_column($result['items'], 'nik');
        if ($retired === []) {
            return 0;
        }

        foreach ($import->changes as $i => $change) {
            if (! in_array($change['nik'], $retired, true)) {
                continue;
            }

            $import->changes[$i]['jobs'] = array_merge($change['jobs'], $openBefore->get($change['nik'], []));
            $import->changes[$i]['after_hash'] = Employee::stateHash(Employee::findOrFail($change['nik']));
        }

        return count($retired);
    }

    /**
     * Membaca spreadsheet; berkas yang tidak dapat dibaca menjadi galat yang aman ditampilkan, bukan galat 500.
     */
    private function importSpreadsheet(EmployeesImport $import, string|UploadedFile $source): void
    {
        try {
            Excel::import($import, $source);
        } catch (SpreadsheetException|NoTypeDetectedException $e) {
            Log::warning('Berkas impor karyawan tidak dapat dibaca: '.$e->getMessage());

            throw new ImportAbortedException('Berkas tidak dapat dibaca sebagai spreadsheet Excel. Pastikan berkas berasal dari template impor karyawan dan tidak rusak.', 0, $e);
        }
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
            $cached = $this->sessions->preview($token);
            if (! $cached || ! Storage::disk(self::DISK)->exists($cached['file_path'])) {
                throw new \RuntimeException('Sesi pratinjau impor telah kedaluwarsa atau berkas tidak ditemukan.');
            }

            $this->sessions->putCommitJob($token, [
                'status' => 'processing',
                'progress' => 50,
                'message' => 'Sedang menulis data ke tabel master kepegawaian...',
                'token' => $token,
                'type' => 'commit',
            ]);

            return $this->executeCommit($cached, $token, $user, $ip, $userAgent);
        } catch (\Throwable $e) {
            Log::error("Gagal menjalankan commit import background job [Token: {$token}]: ".$e->getMessage());
            $this->sessions->releaseCommit($token);
            app(EmployeeImportGovernanceService::class)->releaseApproval($token);

            $this->sessions->putCommitJob($token, [
                'status' => 'failed',
                'error' => 'Gagal mengeksekusi komit data: '.$e->getMessage(),
                'token' => $token,
                'type' => 'commit',
            ]);

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

        Log::info('HRIS Import Data Karyawan dieksekusi', [
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
        if ($this->sessions->hasCommitJob($token)) {
            $cached = $this->sessions->preview($token);
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
        if ($this->sessions->hasJob($token)) {
            $jobData = $this->sessions->job($token);
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
