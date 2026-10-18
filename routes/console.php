<?php

use App\Services\EmployeeImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('hris:purge-import-files {--hours=2 : Batas jam kedaluwarsa berkas}', function (EmployeeImportService $importService) {
    $hours = (int) $this->option('hours');
    $deleted = $importService->purgeExpiredFiles($hours);
    $this->info("Berhasil membersihkan {$deleted} berkas spreadsheet impor sementara yang kedaluwarsa (> {$hours} jam).");
})->purpose('Membersihkan berkas sementara impor pegawai yang terbengkalai (UU PDP Compliance)');

Schedule::command('hris:purge-import-files')->hourly();
