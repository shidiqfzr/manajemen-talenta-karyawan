<?php

namespace App\Jobs;

use App\Services\EmployeeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEmployeeImportPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $token,
        public string $storedPath,
        public string $originalName,
        public int $fileSize
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmployeeImportService $importService): void
    {
        $importService->processPreviewJob(
            $this->token,
            $this->storedPath,
            $this->originalName,
            $this->fileSize
        );
    }
}
