<?php

namespace Tests\Unit;

use App\Exports\FailedEmployeesExport;
use App\Exports\MutasiHistoryExport;
use App\Support\SpreadsheetCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SUT: App\Support\SpreadsheetCell, the one guard against formula injection (CWE-1236) in every Excel export
 * (SECURITY.md, OWASP). Exporters delegate to it, so a hardening change is made once.
 */
class SpreadsheetCellTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function formulaPayloads(): array
    {
        return [
            'equals' => ['=SUM(A1:A10)'],
            'plus' => ['+12345'],
            'minus' => ['-100'],
            'at' => ['@malicious'],
            'tab' => ["\tTabbed"],
            'carriage return' => ["\rReturn"],
        ];
    }

    #[DataProvider('formulaPayloads')]
    public function test_a_formula_prefix_is_neutralised_with_a_leading_quote(string $payload): void
    {
        $this->assertSame("'".$payload, SpreadsheetCell::neutralise($payload));
    }

    public function test_ordinary_text_empty_values_and_null_are_left_alone(): void
    {
        $this->assertSame('Normal Text', SpreadsheetCell::neutralise('Normal Text'));
        $this->assertSame('1 = 1', SpreadsheetCell::neutralise('1 = 1'));
        $this->assertSame('', SpreadsheetCell::neutralise(''));
        $this->assertSame('', SpreadsheetCell::neutralise(null));
        $this->assertSame('42', SpreadsheetCell::neutralise(42));
    }

    #[DataProvider('formulaPayloads')]
    public function test_the_exporters_use_the_same_guard(string $payload): void
    {
        $expected = SpreadsheetCell::neutralise($payload);

        $this->assertSame($expected, FailedEmployeesExport::sanitizeCellValue($payload));
        $this->assertSame($expected, MutasiHistoryExport::sanitizeCellValue($payload));
    }
}
