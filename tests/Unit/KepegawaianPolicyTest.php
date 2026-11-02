<?php

namespace Tests\Unit;

use App\Services\KepegawaianPolicy;
use PHPUnit\Framework\TestCase;

class KepegawaianPolicyTest extends TestCase
{
    public function test_retirement_date_is_the_start_of_the_birth_month_at_age_56(): void
    {
        $this->assertSame('2041-05-01', KepegawaianPolicy::tanggalPensiun('1985-05-15'));
        $this->assertNull(KepegawaianPolicy::tanggalPensiun(null));
        $this->assertNull(KepegawaianPolicy::tanggalPensiun('bukan tanggal'));
    }

    public function test_mbt_is_one_year_before_retirement(): void
    {
        $this->assertSame('2040-05-01', KepegawaianPolicy::tanggalMbt('2041-05-01'));
        $this->assertNull(KepegawaianPolicy::tanggalMbt(null));
    }

    public function test_grade_ranges_per_strata(): void
    {
        $this->assertSame([1, 10], KepegawaianPolicy::gradeRange('Karpel'));
        $this->assertSame([11, 16], KepegawaianPolicy::gradeRange('Karpim'));
        $this->assertNull(KepegawaianPolicy::gradeRange('Lainnya'));
    }
}
