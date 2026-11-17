<?php

use App\Exceptions\MppPeriodRuleViolationException;
use App\Models\MppPeriod;
use App\Services\EmployeeImportGovernanceService;
use App\Services\EmployeeImportService;
use App\Services\ManPowerPlanningService;
use App\Services\Mpp\MppPeriodService;
use App\Services\Mutasi\ChainProposalService;
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
})->purpose('Membersihkan berkas sementara impor karyawan yang terbengkalai (UU PDP Compliance)');

Artisan::command('hris:purge-import-governance', function (EmployeeImportGovernanceService $governance) {
    $result = $governance->purgeExpired();
    $this->info("Pengajuan impor kedaluwarsa: {$result['expired']}; rekaman pembatalan dihapus: {$result['snapshots']}.");
})->purpose('Menandai pengajuan impor kedaluwarsa dan menghapus rekaman data sebelum impor yang melewati masa pembatalan (UU PDP Compliance)');

Artisan::command('hris:mpp-capture-period {--year= : Tahun periode (default: bulan sebelumnya)} {--month= : Bulan periode 1-12 (default: bulan sebelumnya)}', function (MppPeriodService $periods, ManPowerPlanningService $planning) {
    $default = now()->startOfMonth()->subMonth();
    $year = $this->option('year') !== null ? (int) $this->option('year') : $default->year;
    $month = $this->option('month') !== null ? (int) $this->option('month') : $default->month;

    if ($month < 1 || $month > 12) {
        $this->error('Bulan harus antara 1 dan 12.');

        return 1;
    }

    $label = $planning->monthLabel($month, $year);

    try {
        $existing = $periods->find($year, $month);

        if ($existing?->status === MppPeriod::STATUS_CLOSED) {
            $this->info("Periode MPP {$label} sudah ditutup; dilewati.");

            return 0;
        }

        // Salinan yang dibekukan hari ini (mis. sebelum SK struktur diterapkan) tidak ditimpa dengan struktur baru.
        if ($existing?->captured_at?->isToday()) {
            $this->info("Periode MPP {$label} sudah direkam hari ini; dilewati.");

            return 0;
        }

        $periods->capture($year, $month, null);
    } catch (MppPeriodRuleViolationException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info("Draf periode MPP {$label} direkam.");

    return 0;
})->purpose('Merekam draf periode MPP bulan sebelumnya (dijadwalkan tanggal 1 setiap bulan)');

Artisan::command('hris:expire-mutasi-proposals', function (ChainProposalService $proposals) {
    $expired = $proposals->expireStale();
    $this->info("Usulan mutasi berantai kedaluwarsa: {$expired}.");
})->purpose('Menandai usulan mutasi berantai yang melewati batas waktu persetujuan sebagai kedaluwarsa');

Schedule::command('hris:purge-import-files')->hourly();
Schedule::command('hris:purge-import-governance')->daily();
// SK struktur diterapkan lebih dulu agar mutasi terjadwal pada hari yang sama menuju struktur yang sudah baru.
Schedule::command('hr:apply-structure-decrees')->dailyAt('00:00');
Schedule::command('hr:apply-effective-mutations')->dailyAt('00:01');
Schedule::command('hr:process-retirements')->dailyAt('00:05');
Schedule::command('hris:mpp-capture-period')->monthlyOn(1, '01:00');
Schedule::command('hris:expire-mutasi-proposals')->daily();
