<?php

namespace Tests\Unit;

use App\Services\EvaluationService;
use PHPUnit\Framework\TestCase;

/**
 * SUT: App\Services\EvaluationService (rumus nilai tertimbang dan bobot kriteria, DOMAIN.md §2.3).
 */
class EvaluationServiceTest extends TestCase
{
    public function test_weights_sum_to_one_hundred_percent(): void
    {
        $this->assertEqualsWithDelta(1.0, array_sum(EvaluationService::BOBOT), 0.00001);
    }

    public function test_weighted_score_uses_the_shared_weights(): void
    {
        $service = new EvaluationService;

        // 8*0.4 + 7*0.3 + 6*0.2 + 5*0.1
        $this->assertEqualsWithDelta(7.0, $service->calculateNilaiTertimbang(8, 7, 6, 5), 0.00001);
        $this->assertEqualsWithDelta(10.0, $service->calculateNilaiTertimbang(10, 10, 10, 10), 0.00001);
        $this->assertEqualsWithDelta(0.0, $service->calculateNilaiTertimbang(0, 0, 0, 0), 0.00001);
    }

    public function test_weight_is_exposed_as_a_whole_percentage_for_the_form(): void
    {
        $this->assertSame(40, EvaluationService::bobotPersen('nilai_kepemimpinan'));
        $this->assertSame(30, EvaluationService::bobotPersen('nilai_perilaku_budaya'));
        $this->assertSame(20, EvaluationService::bobotPersen('nilai_pengalaman_teknis'));
        $this->assertSame(10, EvaluationService::bobotPersen('nilai_kematangan_pribadi'));
        $this->assertSame(0, EvaluationService::bobotPersen('tidak_dikenal'));
    }
}
