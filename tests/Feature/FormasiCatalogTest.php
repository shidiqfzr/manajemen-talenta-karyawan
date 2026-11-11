<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Unit;
use App\Services\Formasi\FormasiCatalog;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormasiCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_standar_formasi_equals_the_seeded_seats(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame([
            'RM-1' => ['KEU' => 2, 'TAN' => 27, 'TEK' => 11, 'UMU' => 6],
            'RM-2' => ['KEU' => 4, 'TAN' => 41, 'TEK' => 12, 'UMU' => 15],
            'RM-3' => ['KEU' => 62, 'TAN' => 148, 'TEK' => 60, 'UMU' => 63],
        ], app(FormasiCatalog::class)->standarFormasi());
    }

    public function test_a_pool_position_opened_on_a_unit_never_gives_a_seat_bidang(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Katalog Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
        $manager = Position::create(['nama' => 'Manajer Katalog', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]);
        $advisor = Position::create(['nama' => 'Advisor RM-3', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true, 'is_overflow' => true]);
        foreach ([[$manager, 1, 'TAN'], [$advisor, 0, 'UMU']] as [$position, $kuota, $bidang]) {
            DB::table('position_unit')->insert(['unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => $bidang, 'created_at' => now(), 'updated_at' => now()]);
        }

        $catalog = app(FormasiCatalog::class);

        $this->assertSame('TAN', $catalog->seatBidang('Kebun Katalog Uji', 'Manajer Katalog'));
        $this->assertNull($catalog->seatBidang('Kebun Katalog Uji', 'Advisor RM-3'), 'A pool Jabatan keeps the estimated Bidang, never the link marker.');
    }

    public function test_standar_formasi_is_zero_when_no_seats_are_seeded(): void
    {
        $this->assertSame([
            'RM-1' => ['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 0],
            'RM-2' => ['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 0],
            'RM-3' => ['KEU' => 0, 'TAN' => 0, 'TEK' => 0, 'UMU' => 0],
        ], app(FormasiCatalog::class)->standarFormasi());
    }

    public function test_seat_bidang_is_per_unit(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $catalog = app(FormasiCatalog::class);

        $this->assertSame('TAN', $catalog->seatBidang('Kebun Tajati', 'Manajer'));
        $this->assertSame('TEK', $catalog->seatBidang('PKS Kembayan', 'Manajer'));
        $this->assertNull($catalog->seatBidang('Kebun Tajati', 'Advisor RM-1'));
        $this->assertNull($catalog->seatBidang('Unit Tidak Ada', 'Manajer'));
    }
}
