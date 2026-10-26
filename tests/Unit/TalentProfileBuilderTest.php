<?php

namespace Tests\Unit;

use App\Services\Mutasi\TalentProfileBuilder;
use Tests\TestCase;

class TalentProfileBuilderTest extends TestCase
{
    public function test_format_months_renders_years_and_months(): void
    {
        $this->assertSame('1 th 7 bln', TalentProfileBuilder::formatMonths(19));
        $this->assertSame('0 th 0 bln', TalentProfileBuilder::formatMonths(0));
        $this->assertSame('2 th 0 bln', TalentProfileBuilder::formatMonths(24));
    }
}
