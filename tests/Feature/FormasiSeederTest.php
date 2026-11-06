<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Models\UnitAlias;
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
        $this->assertSame(486, DB::table('position_unit')->count());
        $this->assertSame(612, (int) DB::table('position_unit')->sum('kuota'));
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

        $this->assertSame(['RM-1' => 46, 'RM-2' => 72, 'RM-3' => 333, 'RM-5' => 161], $perRm);
        $this->assertSame(['KEU' => 87, 'TAN' => 253, 'TEK' => 97, 'UMU' => 175], $perBidang);
    }

    public function test_karpel_seats_keep_their_rm_5_band(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame(0, Position::whereNotIn('rm_level', Position::KARPIM_RM_LEVELS)->where('level', 'Karpim')->where('is_overflow', false)->count());
        $this->assertSame(['RM-5'], Position::where('level', 'Karpel')->distinct()->pluck('rm_level')->all());
        $this->assertSame(90, DB::table('position_unit')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('positions.level', 'Karpel')
            ->count());
    }

    public function test_seat_repeated_across_sub_bagian_sums_its_kuota(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $seat = DB::table('position_unit')
            ->where('unit_id', Unit::where('nama', 'Distrik Petani Mitra')->value('id'))
            ->where('position_id', Position::where('nama', 'Asisten Tata Usaha')->value('id'))
            ->first();

        $this->assertSame(2, (int) $seat->kuota);
        $this->assertSame('KEU', $seat->bidang);
    }

    public function test_seeding_twice_does_not_change_totals(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame(45, Unit::count());
        $this->assertSame(486, DB::table('position_unit')->count());
        $this->assertSame(612, (int) DB::table('position_unit')->sum('kuota'));
    }

    public function test_seeded_units_use_the_known_wilayah_labels(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame([], Unit::whereNotIn('wilayah', Unit::WILAYAH_LIST)->pluck('nama')->all());
    }

    public function test_grouped_units_fallback_matches_seeded_units(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $expected = Unit::orderBy('urutan')->get()
            ->groupBy('wilayah')
            ->map(fn ($units) => $units->pluck('nama')->all())
            ->all();

        $this->assertSame($expected, Employee::GROUPED_UNITS);
    }

    public function test_reseeding_restores_unit_and_position_attributes(): void
    {
        $this->seed(PositionUnitSeeder::class);
        Unit::where('nama', 'Kebun Tajati')->update(['wilayah' => 'Salah', 'urutan' => 999]);
        Position::where('nama', 'Manajer')->update(['rm_level' => 'RM-3']);

        $this->seed(PositionUnitSeeder::class);

        $tajati = Unit::where('nama', 'Kebun Tajati')->firstOrFail();
        $this->assertSame('Wilayah Kalimantan Timur', $tajati->wilayah);
        $this->assertNotSame(999, $tajati->urutan);
        $this->assertSame('RM-1', Position::where('nama', 'Manajer')->value('rm_level'));
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

    public function test_seeding_registers_the_legacy_unit_aliases_from_the_baseline(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame(3, UnitAlias::count());
        $this->assertSame(
            Unit::where('nama', 'PMO Batu Bara & Bisnis Lainnya')->value('id'),
            UnitAlias::where('alias_key', UnitAlias::keyFor('PROJECT MANAGEMENT OFFICE (PMO) BATU BARA DAN BISNIS LAINNYA'))->value('unit_id')
        );
    }
}
