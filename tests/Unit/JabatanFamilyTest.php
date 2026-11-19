<?php

namespace Tests\Unit;

use App\Services\Formasi\JabatanFamily;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * SUT: App\Services\Formasi\JabatanFamily (groups numbered formasi names for the Sebaran Formasi view).
 */
class JabatanFamilyTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            'roman numeral' => ['Asisten Afdeling I', 'Asisten Afdeling'],
            'long numeral' => ['Asisten Afdeling XIII', 'Asisten Afdeling'],
            'numeral with letter suffix' => ['Asisten Afdeling IIA', 'Asisten Afdeling'],
            'pair with dan' => ['Asisten Afdeling I dan II', 'Asisten Afdeling'],
            'pair with ampersand' => ['Asisten Afdeling VII & VIII', 'Asisten Afdeling'],
            'no numeral' => ['Asisten Afdeling', 'Asisten Afdeling'],
            'place name' => ['Asisten Afdeling Tanjung Santan', 'Asisten Afdeling Tanjung Santan'],
            'acronym IT' => ['Asisten Teknik IT', 'Asisten Teknik IT'],
            'acronym QC' => ['Asisten QC', 'Asisten QC'],
            'acronym SDM' => ['Kepala Bagian SDM', 'Kepala Bagian SDM'],
            'single word' => ['Manajer', 'Manajer'],
            'extra whitespace' => ['  Asisten   Afdeling  V ', 'Asisten Afdeling'],
            'lone numeral is kept' => ['I', 'I'],
            'empty stays empty-safe' => ['', ''],
        ];
    }

    #[DataProvider('names')]
    public function test_family_of(string $jabatan, string $family): void
    {
        $this->assertSame($family, JabatanFamily::of($jabatan));
    }

    public function test_family_is_idempotent(): void
    {
        $once = JabatanFamily::of('Asisten Afdeling VI dan VII');

        $this->assertSame($once, JabatanFamily::of($once));
    }
}
