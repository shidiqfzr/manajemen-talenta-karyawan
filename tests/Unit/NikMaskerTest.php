<?php

namespace Tests\Unit;

use App\Support\NikMasker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Satu aturan penyamaran NIK untuk seluruh layar (UU PDP; SECURITY.md): hanya tiga digit terakhir yang terlihat.
 */
class NikMaskerTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function cases(): array
    {
        return [
            'nik delapan digit' => ['12345678', '*****678'],
            'empat digit' => ['1234', '*234'],
            'nik pendek' => ['12', '**'],
            'tepat tiga digit disamarkan penuh' => ['123', '***'],
            'kosong' => ['', ''],
        ];
    }

    #[DataProvider('cases')]
    public function test_it_keeps_only_the_last_three_characters(string $nik, string $expected): void
    {
        $this->assertSame($expected, NikMasker::mask($nik));
    }

    public function test_the_masked_value_never_contains_the_hidden_digits(): void
    {
        $this->assertStringNotContainsString('12345', NikMasker::mask('12345678'));
    }
}
