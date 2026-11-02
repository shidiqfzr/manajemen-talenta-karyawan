<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('position_unit', function (Blueprint $table) {
            $table->string('bidang', 3)->nullable()->after('kuota')
                ->comment('Bidang fungsional kursi (KEU/TAN/TEK/UMU); jabatan yang sama dapat berbeda bidang antar unit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('position_unit', function (Blueprint $table) {
            $table->dropColumn('bidang');
        });
    }
};
