<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormasiSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_position_unit_stores_bidang_on_the_seat_row(): void
    {
        $this->assertTrue(Schema::hasColumn('position_unit', 'bidang'));
    }
}
