<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * MBT moves from 1 year to 6 months before the pension date (DOMAIN.md Bab 5.1).
     *
     * Only stored MBT dates that still follow the old rule (exactly pension - 1 year) are recalculated; a date an
     * admin entered by hand is left alone. Closed MPP periods keep their recorded snapshot (`mpp_period_members`).
     */
    public function up(): void
    {
        $this->shift(fn (Carbon $pensiun) => $pensiun->copy()->subYear(), fn (Carbon $pensiun) => $pensiun->copy()->subMonthsNoOverflow(6));
    }

    /**
     * Restore the old 1-year rule for the dates the new 6-month rule produced.
     */
    public function down(): void
    {
        $this->shift(fn (Carbon $pensiun) => $pensiun->copy()->subMonthsNoOverflow(6), fn (Carbon $pensiun) => $pensiun->copy()->subYear());
    }

    /**
     * @param  callable(Carbon): Carbon  $from  The MBT date the current rule would have produced.
     * @param  callable(Carbon): Carbon  $to  The MBT date the target rule produces.
     */
    private function shift(callable $from, callable $to): void
    {
        DB::transaction(function () use ($from, $to) {
            DB::table('employees')
                ->whereNotNull('tanggal_pensiun')
                ->whereNotNull('tanggal_mbt')
                ->orderBy('nik')
                ->chunk(500, function ($rows) use ($from, $to) {
                    foreach ($rows as $row) {
                        $pensiun = Carbon::parse($row->tanggal_pensiun);

                        if (Carbon::parse($row->tanggal_mbt)->toDateString() === $from($pensiun)->toDateString()) {
                            DB::table('employees')->where('nik', $row->nik)->update(['tanggal_mbt' => $to($pensiun)->toDateString()]);
                        }
                    }
                });
        });
    }
};
