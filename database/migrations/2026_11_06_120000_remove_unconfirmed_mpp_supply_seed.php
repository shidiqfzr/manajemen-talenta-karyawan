<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Versi awal tabel rencana pasokan MPP mengisi rencana 2026 dengan angka acuan korporat lama yang tidak
     * pernah dikonfirmasi admin. Hapus rencana itu bila belum pernah disunting (tidak ada sel dengan
     * updated_by), agar layar meminta HR mengisi rencana yang sah. Rencana yang sudah disunting admin dibiarkan.
     */
    public function up(): void
    {
        $edited = DB::table('mpp_supply_plans')->where('year', 2026)->whereNotNull('updated_by')->exists();

        if (! $edited) {
            DB::table('mpp_supply_plans')->where('year', 2026)->delete();
        }
    }

    /**
     * Angka acuan lama sengaja tidak dipulihkan: rencana pasokan hanya berasal dari admin.
     */
    public function down(): void
    {
        //
    }
};
