<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Golongan dan jalur masuk ikut dibekukan pada personil snapshot periode MPP, sehingga rincian periode
     * tertutup tidak berubah oleh promosi atau koreksi data sesudahnya. Nama, tanggal lahir, dan data pribadi
     * lain tetap tidak disimpan. Baris lama dibiarkan kosong; layar membaca golongan terkini sebagai cadangan.
     */
    public function up(): void
    {
        Schema::table('mpp_period_members', function (Blueprint $table) {
            $table->string('golongan')->nullable()->after('unit_kerja');
            $table->string('jalur_masuk', 50)->nullable()->after('golongan');
        });
    }

    public function down(): void
    {
        Schema::table('mpp_period_members', function (Blueprint $table) {
            $table->dropColumn(['golongan', 'jalur_masuk']);
        });
    }
};
