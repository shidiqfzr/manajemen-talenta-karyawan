<?php

namespace App\Console\Commands;

use App\Services\MutasiService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ApplyEffectiveMutationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:apply-effective-mutations 
                            {--date= : Tanggal target aktivasi efektif (format YYYY-MM-DD, default hari ini)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aktivasi otomatis mutasi pegawai terjadwal yang telah mencapai tanggal TMT efektif';

    /**
     * Execute the console command.
     */
    public function handle(MutasiService $mutasiService): int
    {
        $dateOption = $this->option('date');
        $targetDate = $dateOption ? Carbon::parse($dateOption)->toDateString() : Carbon::today()->toDateString();

        $this->info("Menjalankan aktivasi mutasi terjadwal per TMT: {$targetDate}...");

        try {
            $result = $mutasiService->applyEffectiveMutations($targetDate);

            if ($result['activated_count'] === 0) {
                $this->line('Tidak ada mutasi terjadwal yang jatuh tempo untuk diaktifkan.');

                return self::SUCCESS;
            }

            $this->info("Berhasil mengaktifkan {$result['activated_count']} mutasi pegawai:");

            $tableRows = array_map(function ($item) {
                return [
                    $item['employee_nik'],
                    $item['nama'],
                    $item['jabatan'],
                    $item['unit_kerja'],
                    $item['tmt_awal'],
                    $item['jenis_mutasi'],
                ];
            }, $result['items']);

            $this->table(
                ['NIK', 'Nama Pegawai', 'Jabatan Baru', 'Unit Kerja Baru', 'TMT Efektif', 'Jenis Mutasi'],
                $tableRows
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Gagal mengeksekusi aktivasi mutasi: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
