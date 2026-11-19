<?php

namespace App\Console\Commands;

use App\Services\PensiunService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRetirementsCommand extends Command
{
    protected $signature = 'hr:process-retirements
                            {--date= : Tanggal acuan (YYYY-MM-DD, default hari ini)}
                            {--dry-run : Hanya tampilkan karyawan yang akan diberhentikan, tanpa mengubah data}';

    protected $description = 'Memberhentikan karyawan yang telah mencapai Batas Usia Pensiun dan mengosongkan kursi formasinya';

    public function handle(PensiunService $pensiun): int
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $pensiun->processDue($date, $dryRun);
        } catch (\Throwable $e) {
            $this->error("Gagal memproses pensiun: {$e->getMessage()}");

            return self::FAILURE;
        }

        if ($result['count'] === 0) {
            $this->line('Tidak ada karyawan yang jatuh tempo pensiun.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[Simulasi] ' : '')."Karyawan pensiun per {$date->toDateString()}: {$result['count']}");
        $this->table(
            ['NIK', 'Nama Karyawan', 'Jabatan Terakhir', 'Unit Kerja', 'Tanggal Berhenti'],
            array_map(fn (array $i): array => [$i['nik'], $i['nama'], $i['jabatan'], $i['unit_kerja'], $i['tanggal_berhenti']], $result['items'])
        );

        return self::SUCCESS;
    }
}
