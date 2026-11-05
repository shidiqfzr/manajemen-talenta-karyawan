<?php

namespace Tests\Concerns;

use App\Models\MppSupplyPlan;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan rencana pasokan MPP langsung ke tabel (tanpa riwayat), untuk pengujian yang membutuhkan
 * rencana yang sudah ada. Nilai bawaan: angka contoh RM-3 yang dipakai sejak awal pengujian MPP.
 */
trait StoresMppSupplyPlan
{
    /** @param  array<string, array<string, array<string, int>>>|null  $values  sumber => RM => bidang => jumlah */
    protected function storeMppSupplyPlan(int $year, ?array $values = null, ?int $updatedBy = null): void
    {
        $values ??= [
            'ckp' => ['RM-3' => ['KEU' => 2, 'TAN' => 7, 'TEK' => 2, 'UMU' => 1]],
            'talent_scouting' => ['RM-3' => ['KEU' => 0, 'TAN' => 4, 'TEK' => 2, 'UMU' => 0]],
            'rbb' => ['RM-3' => ['KEU' => 8, 'TAN' => 6, 'TEK' => 1, 'UMU' => 6]],
            'prioritas' => [
                'RM-2' => ['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 1],
                'RM-3' => ['KEU' => 1, 'TAN' => 4, 'TEK' => 0, 'UMU' => 8],
            ],
        ];

        $rows = [];
        foreach (array_keys(MppSupplyPlan::SOURCES) as $source) {
            foreach (MppSupplyPlan::LEVELS as $rm) {
                foreach (MppSupplyPlan::BIDANG as $bidang) {
                    $rows[] = [
                        'year' => $year, 'source' => $source, 'rm_level' => $rm, 'bidang' => $bidang,
                        'jumlah' => $values[$source][$rm][$bidang] ?? 0, 'updated_by' => $updatedBy,
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }

        DB::table('mpp_supply_plans')->insert($rows);
    }
}
