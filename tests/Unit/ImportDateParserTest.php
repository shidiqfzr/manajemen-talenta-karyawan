<?php

namespace Tests\Unit;

use App\Services\Import\ImportDateParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SUT: App\Services\Import\ImportDateParser, normalisasi tanggal impor Data Karyawan ke Y-m-d.
 */
class ImportDateParserTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function dates(): array
    {
        return [
            'serial Excel' => ['44197', '2021-01-01'],
            'dd/mm/yyyy' => ['15/07/2026', '2026-07-15'],
            'yyyy-mm-dd' => ['2026-07-15', '2026-07-15'],
            'teks umum' => ['15 July 2026', '2026-07-15'],
            'kosong' => ['', null],
            'null' => [null, null],
            'sampah' => ['bukan tanggal', null],
        ];
    }

    #[DataProvider('dates')]
    public function test_it_normalizes_every_supported_format_to_ymd(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, (new ImportDateParser)->normalize($input));
    }

    public function test_the_date_field_list_covers_the_tanggal_columns_of_the_import_row(): void
    {
        foreach (['tanggal_lahir', 'tmt_bekerja', 'tanggal_diangkat_staf', 'tanggal_dalam_jabatan', 'tmt_unit_kerja', 'tanggal_mbt', 'tanggal_pensiun'] as $field) {
            $this->assertContains($field, ImportDateParser::FIELDS);
        }
    }
}
