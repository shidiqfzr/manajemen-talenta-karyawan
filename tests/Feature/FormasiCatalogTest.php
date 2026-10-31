<?php

namespace Tests\Feature;

use App\Services\Formasi\FormasiCatalog;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormasiCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_standar_formasi_equals_corporate_mpp_matrix(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $this->assertSame([
            'RM-1' => ['KEU' => 2, 'TAN' => 27, 'TEK' => 11, 'UMU' => 5],
            'RM-2' => ['KEU' => 5, 'TAN' => 38, 'TEK' => 12, 'UMU' => 16],
            'RM-3' => ['KEU' => 63, 'TAN' => 146, 'TEK' => 61, 'UMU' => 58],
        ], app(FormasiCatalog::class)->standarFormasi());
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
