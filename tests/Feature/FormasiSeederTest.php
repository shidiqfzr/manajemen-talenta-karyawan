<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Unit;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormasiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_unit_stores_bidang_on_the_seat_row(): void
    {
        $this->assertTrue(Schema::hasColumn('position_unit', 'bidang'));
    }

    public function test_seeds_excel_totals(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame(45, Unit::count());
        $this->assertSame(392, DB::table('position_unit')->count());
        $this->assertSame(444, (int) DB::table('position_unit')->sum('kuota'));
    }

    public function test_manajer_coexists_in_three_bidang(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $manajer = Position::where('nama', 'Manajer')->firstOrFail();
        $bidang = DB::table('position_unit')->where('position_id', $manajer->id)->distinct()->pluck('bidang')->sort()->values()->all();

        $this->assertSame(['TAN', 'TEK', 'UMU'], $bidang);
    }

    public function test_rm_and_bidang_totals_match_excel(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $perRm = DB::table('position_unit')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->groupBy('positions.rm_level')
            ->selectRaw('positions.rm_level as rm, SUM(position_unit.kuota) as total')
            ->pluck('total', 'rm')
            ->map(fn ($v) => (int) $v)
            ->sortKeys()
            ->all();
        $perBidang = DB::table('position_unit')
            ->groupBy('bidang')
            ->selectRaw('bidang, SUM(kuota) as total')
            ->pluck('total', 'bidang')
            ->map(fn ($v) => (int) $v)
            ->sortKeys()
            ->all();

        $this->assertSame(['RM-1' => 45, 'RM-2' => 71, 'RM-3' => 328], $perRm);
        $this->assertSame(['KEU' => 70, 'TAN' => 211, 'TEK' => 84, 'UMU' => 79], $perBidang);
    }

    public function test_seeding_twice_does_not_change_totals(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame(45, Unit::count());
        $this->assertSame(392, DB::table('position_unit')->count());
        $this->assertSame(444, (int) DB::table('position_unit')->sum('kuota'));
    }

    public function test_pool_positions_exist_without_seats(): void
    {
        $this->seed(PositionUnitSeeder::class);

        foreach (['Advisor RM-1', 'Karyawan Diperbantukan RM-3'] as $nama) {
            $position = Position::where('nama', $nama)->first();

            $this->assertNotNull($position, "{$nama} seharusnya tersedia sebagai jabatan pool.");
            $this->assertSame(0, DB::table('position_unit')->where('position_id', $position->id)->count());
        }
    }
}
