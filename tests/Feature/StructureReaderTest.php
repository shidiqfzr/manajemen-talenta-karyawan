<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Unit;
use App\Services\Structure\StructureReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StructureReaderTest extends TestCase
{
    use RefreshDatabase;

    private function seat(Unit $unit, string $nama, string $rm, string $bidang, int $kuota, array $position = []): void
    {
        $level = in_array($rm, ['RM-1', 'RM-2', 'RM-3'], true) ? 'Karpim' : 'Karpel';
        $positionModel = Position::firstOrCreate(['nama' => $nama], $position + ['level' => $level, 'rm_level' => $rm, 'is_active' => true]);
        DB::table('position_unit')->insert(['unit_id' => $unit->id, 'position_id' => $positionModel->id, 'kuota' => $kuota, 'bidang' => $bidang, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_current_reads_units_and_seats_in_the_shared_shape(): void
    {
        $unit = Unit::create(['nama' => 'Bagian Tanaman', 'kode' => 'Bagian', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => true, 'urutan' => 3]);
        $this->seat($unit, 'Kepala Bagian Tanaman', 'RM-1', 'TAN', 1);

        $this->assertSame([
            'units' => [
                'Bagian Tanaman' => [
                    'kode' => 'Bagian',
                    'wilayah' => 'Regional Office (Kantor Direksi Pontianak)',
                    'urutan' => 3,
                    'is_active' => true,
                    'seats' => ['Kepala Bagian Tanaman' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1]],
                ],
            ],
        ], app(StructureReader::class)->current());
    }

    public function test_current_excludes_jabatan_penampungan_and_keeps_norm_seats_with_quota_zero(): void
    {
        $unit = Unit::create(['nama' => 'Bagian Sekretariat & Hukum', 'kode' => 'Bagian', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => true, 'urutan' => 7]);
        $this->seat($unit, 'Supir Pool', 'RM-5', 'UMU', 0);
        $this->seat($unit, 'Advisor RM-2', 'RM-2', 'UMU', 0, ['is_overflow' => true]);
        $this->seat($unit, 'Karyawan Diperbantukan RM-3', 'RM-3', 'UMU', 0, ['is_overflow' => true, 'is_diperban' => true]);

        $seats = app(StructureReader::class)->current()['units']['Bagian Sekretariat & Hukum']['seats'];

        $this->assertSame(['Supir Pool'], array_keys($seats));
        $this->assertSame(0, $seats['Supir Pool']['kuota']);
    }

    public function test_current_flags_inactive_units(): void
    {
        Unit::create(['nama' => 'Bagian Tidur', 'kode' => 'Bagian', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => false, 'urutan' => 9]);

        $this->assertFalse(app(StructureReader::class)->current()['units']['Bagian Tidur']['is_active']);
    }

    public function test_from_parsed_seats_builds_the_target_shape_with_level_from_rm(): void
    {
        $target = app(StructureReader::class)->fromParsedSeats([
            ['unit' => 'Bagian Tanaman', 'jabatan' => 'Kepala Bagian Tanaman', 'rm_level' => 'RM-1', 'bidang' => 'TAN', 'kuota' => 1],
            ['unit' => 'Bagian Tanaman', 'jabatan' => 'Krani Pemetaan', 'rm_level' => 'RM-5', 'bidang' => 'TAN', 'kuota' => 8],
        ]);

        $this->assertSame(['Bagian Tanaman'], array_keys($target['units']));
        $this->assertSame('Karpim', $target['units']['Bagian Tanaman']['seats']['Kepala Bagian Tanaman']['level']);
        $this->assertSame(['rm_level' => 'RM-5', 'level' => 'Karpel', 'bidang' => 'TAN', 'kuota' => 8], $target['units']['Bagian Tanaman']['seats']['Krani Pemetaan']);
    }
}
