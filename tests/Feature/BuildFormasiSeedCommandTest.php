<?php

namespace Tests\Feature;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class BuildFormasiSeedCommandTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function tempPath(string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'formasi').$suffix;
        $this->tempFiles[] = $path;

        return $path;
    }

    /** @param list<array<int, string>> $rows */
    private function workbook(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray(
            [['Bagian/Unit Kerja', 'Nama Jabatan', 'Level RM', 'Bidang'], ...$rows],
            null,
            'A1'
        );

        $path = $this->tempPath('.xlsx');
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }

    private function metadata(array $units): string
    {
        $path = $this->tempPath('.json');
        file_put_contents($path, json_encode($units));

        return $path;
    }

    public function test_builds_json_in_seeder_shape(): void
    {
        $excel = $this->workbook([
            ['KEBUN TAJATI', 'MANAJER', 'RM-1', 'TAN'],
            ['KEBUN TAJATI', 'ASISTEN AFDELING I', 'RM-3', 'TAN'],
            ['KEBUN TAJATI', 'ASISTEN AFDELING I', 'RM-3', 'TAN'],
            ['BAGIAN TANAMAN', 'KEPALA BAGIAN', 'RM-1', 'TAN'],
        ]);
        $metadata = $this->metadata([
            'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Kalimantan Timur', 'urutan' => 39],
            'Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'Regional Office (Pontianak)', 'urutan' => 3],
        ]);
        $out = $this->tempPath('.json');

        $this->artisan('formasi:build-seed', ['file' => $excel, '--out' => $out, '--metadata' => $metadata])
            ->assertExitCode(0);

        $this->assertEquals([
            [
                'nama' => 'Kebun Tajati',
                'kode' => 'Kebun',
                'wilayah' => 'Kalimantan Timur',
                'is_active' => true,
                'urutan' => 39,
                'positions' => [
                    ['nama' => 'Manajer', 'bidang' => 'TAN', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'kuota' => 1],
                    ['nama' => 'Asisten Afdeling I', 'bidang' => 'TAN', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'kuota' => 2],
                ],
            ],
            [
                'nama' => 'Bagian Tanaman',
                'kode' => 'Bagian',
                'wilayah' => 'Regional Office (Pontianak)',
                'is_active' => true,
                'urutan' => 3,
                'positions' => [
                    ['nama' => 'Kepala Bagian', 'bidang' => 'TAN', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'kuota' => 1],
                ],
            ],
        ], json_decode(file_get_contents($out), true));
    }

    public function test_fails_and_lists_units_missing_from_metadata(): void
    {
        $excel = $this->workbook([
            ['KEBUN KUMAI', 'MANAJER', 'RM-1', 'TAN'],
        ]);
        $metadata = $this->metadata([]);
        $out = $this->tempPath('.json');

        $this->artisan('formasi:build-seed', ['file' => $excel, '--out' => $out, '--metadata' => $metadata])
            ->expectsOutputToContain('Kebun Kumai')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($out);
    }

    public function test_fails_when_parser_reports_errors(): void
    {
        $excel = $this->workbook([
            ['KEBUN TAJATI', 'MANAJER', 'RM-1', 'TAN'],
            ['KEBUN TAJATI', 'ASISTEN AFDELING I', 'RM-9', 'TAN'],
        ]);
        $metadata = $this->metadata([
            'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Kalimantan Timur', 'urutan' => 39],
        ]);
        $out = $this->tempPath('.json');

        $this->artisan('formasi:build-seed', ['file' => $excel, '--out' => $out, '--metadata' => $metadata])
            ->expectsOutputToContain('Baris 3')
            ->assertExitCode(1);

        $this->assertFileDoesNotExist($out);
    }
}
