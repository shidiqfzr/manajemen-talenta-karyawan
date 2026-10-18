<?php

use App\Models\Position;
use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pastikan posisi formasi terdaftar dengan RM-1 (Eselon Karpim Pimpinan)
        $position = Position::firstOrCreate(
            ['nama' => 'Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng'],
            [
                'bidang' => 'TAN',
                'level' => 'Karpim',
                'rm_level' => 'RM-1',
                'is_active' => true,
                'is_overflow' => false,
                'is_diperban' => false,
            ]
        );

        if ($position->rm_level !== 'RM-1') {
            $position->update(['rm_level' => 'RM-1']);
        }

        // 2. Hubungkan posisi ke Unit 'Project Management Office (PMO)'
        $unit = Unit::firstOrCreate(
            ['nama' => 'Project Management Office (PMO)'],
            [
                'kode' => 'Project',
                'wilayah' => 'Regional Office (Pontianak)',
                'is_active' => true,
                'urutan' => 9,
            ]
        );

        $existing = DB::table('position_unit')
            ->where('position_id', $position->id)
            ->where('unit_id', $unit->id)
            ->first();

        if (! $existing) {
            DB::table('position_unit')->insert([
                'position_id' => $position->id,
                'unit_id' => $unit->id,
                'kuota' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $position = Position::where('nama', 'Head of PMO Investasi Tanamanan Wilayah Kaltim & Kalsel-Teng')->first();
        $unit = Unit::where('nama', 'Project Management Office (PMO)')->first();

        if ($position && $unit) {
            DB::table('position_unit')
                ->where('position_id', $position->id)
                ->where('unit_id', $unit->id)
                ->delete();
        }
    }
};
