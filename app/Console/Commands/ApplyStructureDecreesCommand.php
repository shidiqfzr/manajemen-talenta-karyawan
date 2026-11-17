<?php

namespace App\Console\Commands;

use App\Exceptions\StructureDecreeException;
use App\Models\StructureDecree;
use App\Services\Structure\StructureDecreeService;
use Illuminate\Console\Command;

class ApplyStructureDecreesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hr:apply-structure-decrees';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menerapkan SK struktur organisasi yang sudah disetujui dan tanggal berlakunya telah tiba (DOMAIN.md §3.7)';

    public function handle(StructureDecreeService $decrees): int
    {
        $due = StructureDecree::query()
            ->where('status', StructureDecree::STATUS_APPROVED)
            ->whereDate('effective_date', '<=', now()->toDateString())
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        if ($due->isEmpty()) {
            $this->line('Tidak ada SK struktur yang jatuh tempo untuk diterapkan.');

            return self::SUCCESS;
        }

        $counts = [StructureDecree::STATUS_APPLIED => 0, StructureDecree::STATUS_STALE => 0, StructureDecree::STATUS_FAILED => 0];

        foreach ($due as $decree) {
            try {
                $status = $decrees->apply($decree)->status;
            } catch (StructureDecreeException $e) {
                $this->error("SK {$decree->sk_number}: {$e->getMessage()}");
                $status = StructureDecree::STATUS_FAILED;
            }

            $counts[$status] = ($counts[$status] ?? 0) + 1;

            if ($status === StructureDecree::STATUS_FAILED) {
                $this->error("SK {$decree->sk_number} gagal diterapkan: ".($decree->fresh()->failure_message ?? 'lihat log aplikasi'));
            }
        }

        $this->info(sprintf(
            'Diterapkan: %d; Perlu tinjau ulang: %d; Gagal: %d.',
            $counts[StructureDecree::STATUS_APPLIED],
            $counts[StructureDecree::STATUS_STALE],
            $counts[StructureDecree::STATUS_FAILED],
        ));

        return $counts[StructureDecree::STATUS_FAILED] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
