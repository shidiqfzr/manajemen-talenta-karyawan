<?php

namespace Tests\Unit;

use App\Services\Mutasi\DispensasiBasis;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DispensasiBasisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-09 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_complete_evidence_needs_a_reference_and_a_valid_past_or_today_date(): void
    {
        $this->assertTrue(DispensasiBasis::isComplete('ND-012/DIR/2026', '2026-11-09'));
        $this->assertTrue(DispensasiBasis::isComplete('  ND-012  ', '2026-01-02'));
    }

    #[DataProvider('incompleteEvidence')]
    public function test_incomplete_evidence_is_never_an_approval(?string $reference, ?string $date): void
    {
        $this->assertFalse(DispensasiBasis::isComplete($reference, $date));
        $this->assertSame('', DispensasiBasis::compose($reference, $date));
    }

    public static function incompleteEvidence(): array
    {
        return [
            'no reference' => [null, '2026-11-09'],
            'reference too short' => ['ND-1', '2026-11-09'],
            'whitespace only reference' => ['      ', '2026-11-09'],
            'no date' => ['ND-012/DIR/2026', null],
            'blank date' => ['ND-012/DIR/2026', ' '],
            'future date' => ['ND-012/DIR/2026', '2026-11-10'],
            'overflowing date' => ['ND-012/DIR/2026', '2026-02-31'],
            'wrong date format' => ['ND-012/DIR/2026', '09/11/2026'],
        ];
    }

    public function test_compose_writes_reference_and_indonesian_formatted_date_for_the_audit_trail(): void
    {
        $this->assertSame(
            'ND-012/DIR/2026 (disetujui 09/11/2026)',
            DispensasiBasis::compose(' ND-012/DIR/2026 ', '2026-11-09')
        );
    }

    public function test_reference_and_date_can_be_checked_independently(): void
    {
        $this->assertTrue(DispensasiBasis::hasReference('ND-12'));
        $this->assertFalse(DispensasiBasis::hasReference('ND-1'));
        $this->assertTrue(DispensasiBasis::hasValidDate('2026-11-09'));
        $this->assertFalse(DispensasiBasis::hasValidDate('2026-11-10'));
    }
}
