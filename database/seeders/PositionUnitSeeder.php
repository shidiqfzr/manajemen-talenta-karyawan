<?php

namespace Database\Seeders;

use App\Models\Position;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('data/master_formasi.json');

        if (! file_exists($jsonPath)) {
            $this->command->error('File data master formasi tidak ditemukan di database/data/master_formasi.json');

            return;
        }

        $this->command->info('Membaca file JSON master formasi...');
        $data = json_decode(file_get_contents($jsonPath), true);

        if (! is_array($data)) {
            $this->command->error('Format JSON tidak valid.');

            return;
        }

        $positionUnitMap = [];
        $insertedCount = 0;

        $this->command->info('Memproses baris formasi Karpim...');

        foreach ($data as $unitData) {
            // Buat atau update Unit
            $unit = Unit::firstOrCreate(
                ['nama' => $unitData['nama']],
                [
                    'kode' => $unitData['kode'],
                    'wilayah' => $unitData['wilayah'],
                    'is_active' => $unitData['is_active'],
                    'urutan' => $unitData['urutan'],
                ]
            );

            if (! empty($unitData['positions'])) {
                foreach ($unitData['positions'] as $posData) {
                    $isOverflow = str_contains($posData['nama'], 'Advisor') || str_contains($posData['nama'], 'Diperbantukan');
                    $isDiperban = str_contains($posData['nama'], 'Diperbantukan');
                    
                    $position = Position::firstOrCreate(
                        ['nama' => $posData['nama']],
                        [
                            'bidang' => $posData['bidang'],
                            'level' => $posData['level'],
                            'rm_level' => $posData['rm_level'],
                            'is_active' => true,
                            'is_overflow' => $isOverflow,
                            'is_diperban' => $isDiperban,
                        ]
                    );

                    $key = $position->id.'_'.$unit->id;
                    if (! isset($positionUnitMap[$key])) {
                        $positionUnitMap[$key] = [
                            'position_id' => $position->id,
                            'unit_id' => $unit->id,
                            'kuota' => 0,
                        ];
                    }
                    $positionUnitMap[$key]['kuota'] += $posData['kuota'];
                    $insertedCount += $posData['kuota'];
                }
            }
        }

        if (empty($positionUnitMap)) {
            $this->command->info('Tidak ada formasi Karpim yang ditemukan.');

            return;
        }

        $values = array_values($positionUnitMap);

        DB::table('position_unit')->upsert(
            $values,
            ['position_id', 'unit_id'],
            ['kuota']
        );

        $this->command->info("Berhasil! {$insertedCount} formasi Karpim dipetakan ke dalam ".count($values).' kombinasi unik Jabatan-Unit Kerja murni.');

        // Explicitly seed Generic Pool Positions (Advisor & Diperbantukan) since they are missing from master JSON
        $this->command->info('Memastikan jabatan Pool (Advisor & Diperbantukan) tersedia...');
        $rms = ['RM-1', 'RM-2', 'RM-3', 'RM-4'];
        foreach ($rms as $rm) {
            Position::firstOrCreate(
                ['nama' => "Advisor {$rm}"],
                [
                    'bidang' => 'UMU',
                    'level' => 'Karpim',
                    'rm_level' => $rm,
                    'is_active' => true,
                    'is_overflow' => true,
                    'is_diperban' => false,
                ]
            );
            Position::firstOrCreate(
                ['nama' => "Karyawan Diperbantukan {$rm}"],
                [
                    'bidang' => 'UMU',
                    'level' => 'Karpim',
                    'rm_level' => $rm,
                    'is_active' => true,
                    'is_overflow' => true,
                    'is_diperban' => true,
                ]
            );
        }
    }
}
