<?php

namespace Tests\Unit;

use App\Services\Formasi\FormasiExcelParser;
use App\Services\Formasi\FormasiNormalizer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

class FormasiExcelParserTest extends TestCase
{
    private const HEADERS = ['Bagian/Unit Kerja', 'Nama Jabatan', 'Level RM', 'Bidang'];

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /**
     * @param  list<array<int, ?string>>  $rows
     * @param  list<string>  $headers
     */
    private function workbook(array $rows, ?array $headers = null): string
    {
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([$headers ?? self::HEADERS, ...$rows], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'formasi').'.xlsx';
        (new Xlsx($sheet))->save($path);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_normalizer_formats_units_like_existing_master(): void
    {
        $this->assertSame('Bagian Sekretariat dan Hukum', FormasiNormalizer::unit('BAGIAN SEKRETARIAT & HUKUM'));
        $this->assertSame('Kebun-PKS Longkali', FormasiNormalizer::unit('KEBUN-PKS LONGKALI'));
        $this->assertSame('Group Manager Kaltim', FormasiNormalizer::unit('GROUP MANAJER KALTIM'));
        $this->assertSame('Bagian Akuntansi dan Keuangan', FormasiNormalizer::unit('BAGIAN AKUNTASI DAN KEUANGAN'));
        $this->assertSame('Kebun Kemitraan Area Kaltim-Sel', FormasiNormalizer::unit('KEBUN KEMITRAAN AREA KALTIM-SEL'));
    }

    public function test_normalizer_formats_jabatan(): void
    {
        $this->assertSame('Asisten Humas & Protokoler', FormasiNormalizer::jabatan('ASISTEN HUMAS & PROTOKOLER'));
        $this->assertSame('Asisten Afdeling VII dan VIII', FormasiNormalizer::jabatan('ASISTEN AFDELING VII DAN VIII'));
        $this->assertSame('Asisten TJSL', FormasiNormalizer::jabatan('ASISTEN TJSL'));
        $this->assertSame('Asisten Asesmen dan Rekrutmen', FormasiNormalizer::jabatan('ASISTEN ASESMEN DAN REKRUITMEN'));
        $this->assertSame('Asisten Tajati', FormasiNormalizer::jabatan('Asisten Tajati'));
        $this->assertSame('Asisten Afdeling IIA', FormasiNormalizer::jabatan('ASISTEN AFDELING IIA'));
    }

    public function test_normalizer_keeps_acronyms_and_roman_numerals_up_to_xiii_uppercase(): void
    {
        $this->assertSame('Asisten Afdeling XIII', FormasiNormalizer::jabatan('ASISTEN AFDELING XIII'));
        $this->assertSame('Asisten QC Pengolahan Kelapa Sawit', FormasiNormalizer::jabatan('ASISTEN QC PENGOLAHAN KELAPA SAWIT'));
        $this->assertSame('Kepala Sub Bagian QC & Administrasi Tanaman', FormasiNormalizer::jabatan('KEPALA SUB BAGIAN QC & ADMINISTRASI TANAMAN'));
        $this->assertSame('Asisten ESG dan Sustainability', FormasiNormalizer::jabatan('ASISTEN ESG DAN SUSTAINABILITY'));
        $this->assertSame('Kepala Sub Bagian Kesejahteraan dan HI', FormasiNormalizer::jabatan('KEPALA SUB BAGIAN KESEJAHTERAAN DAN HI'));
        $this->assertSame('Head of PMO Investasi Tanaman Wilayah Kalbar', FormasiNormalizer::jabatan('HEAD OF PMO INVESTASI TANAMAN WILAYAH KALBAR'));
        $this->assertSame('Key User Modul FI / CO / HCM / MM / PM / SD', FormasiNormalizer::jabatan('KEY USER MODUL FI / CO / HCM / MM / PM / SD'));
        $this->assertSame('Key User Modul PP, QM, dan Jembatan Timbang', FormasiNormalizer::jabatan('KEY USER MODUL PP, QM, DAN JEMBATAN TIMBANG'));
        $this->assertSame('Key User Modul FM,PS,BKM,SPB, dan D-Farm', FormasiNormalizer::jabatan('KEY USER MODUL FM,PS,BKM,SPB, DAN D-FARM'));
    }

    public function test_parser_counts_repeated_rows_as_kuota(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook([
            ['BAGIAN TANAMAN', 'ASISTEN PEMETAAN', 'RM-3', 'TAN'],
            ['BAGIAN TANAMAN', 'ASISTEN PEMETAAN', 'RM-3', 'TAN'],
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame([[
            'unit' => 'Bagian Tanaman',
            'jabatan' => 'Asisten Pemetaan',
            'rm_level' => 'RM-3',
            'bidang' => 'TAN',
            'kuota' => 2,
        ]], $result['seats']);
    }

    public function test_parser_keeps_same_jabatan_with_different_bidang_in_different_units(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook([
            ['KEBUN TAJATI', 'MANAJER', 'RM-1', 'TAN'],
            ['PKS KEMBAYAN', 'MANAJER', 'RM-1', 'TEK'],
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['seats']);
        $this->assertSame(['TAN', 'TEK'], array_column($result['seats'], 'bidang'));
    }

    public function test_parser_reports_conflicting_duplicate_in_same_unit(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook([
            ['BAGIAN TANAMAN', 'ASISTEN PEMETAAN', 'RM-3', 'TAN'],
            ['BAGIAN TANAMAN', 'ASISTEN PEMETAAN', 'RM-3', 'KEU'],
        ]));

        $this->assertCount(1, $result['errors']);
        $this->assertSame(3, $result['errors'][0]['row']);
        $this->assertSame([], $result['seats']);
    }

    public function test_parser_reports_jabatan_with_different_rm_across_units(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook([
            ['KEBUN TAJATI', 'ASISTEN TATA USAHA', 'RM-3', 'KEU'],
            ['KEBUN PAMUKAN', 'ASISTEN TATA USAHA', 'RM-2', 'KEU'],
        ]));

        $this->assertSame([3], array_column($result['errors'], 'row'));
        $this->assertStringContainsString('RM-3', $result['errors'][0]['message']);
        $this->assertCount(1, $result['seats']);
        $this->assertSame('Kebun Tajati', $result['seats'][0]['unit']);
    }

    public function test_parser_rejects_invalid_rm_and_bidang_with_row_numbers(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook([
            ['BAGIAN TANAMAN', 'ASISTEN A', 'RM-4', 'TAN'],
            ['BAGIAN TANAMAN', 'ASISTEN B', 'RM-3', 'XYZ'],
            ['BAGIAN TANAMAN', 'ASISTEN C', 'RM-3', 'TAN'],
        ]));

        $this->assertSame([2, 3], array_column($result['errors'], 'row'));
        $this->assertCount(1, $result['seats']);
        $this->assertSame('Asisten C', $result['seats'][0]['jabatan']);
    }

    public function test_parser_rejects_missing_or_renamed_headers(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook(
            [['BAGIAN TANAMAN', 'ASISTEN A', 'RM-3', 'TAN']],
            ['Unit', 'Nama Jabatan', 'Level RM', 'Bidang'],
        ));

        $this->assertSame([], $result['seats']);
        $this->assertCount(1, $result['errors']);
        $this->assertSame(1, $result['errors'][0]['row']);
    }

    public function test_parser_ignores_trailing_blank_rows_and_trims_cells(): void
    {
        $result = (new FormasiExcelParser)->parse($this->workbook([
            ['  BAGIAN TANAMAN ', ' ASISTEN PEMETAAN ', ' RM-3 ', ' TAN '],
            [null, null, null, null],
            ['', '  ', null, null],
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['seats']);
        $this->assertSame('Bagian Tanaman', $result['seats'][0]['unit']);
        $this->assertSame(1, $result['seats'][0]['kuota']);
    }
}
