<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmployeeImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessEmployeeImportCommitJob implements ShouldQueue
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
        public ?int $userId = null,
        public ?string $ip = null,
        public ?string $userAgent = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmployeeImportService $importService): void
    {
        $user = $this->userId ? User::find($this->userId) : null;

        $importService->processCommitJob(
            $this->token,
            $user,
            $this->ip,
            $this->userAgent
        );
    }
}
