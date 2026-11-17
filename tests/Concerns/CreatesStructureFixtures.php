<?php

namespace Tests\Concerns;

use App\Models\Position;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Struktur kecil dan lampiran SK (.xlsx format 8 kolom) untuk pengujian SK struktur organisasi.
 */
trait CreatesStructureFixtures
{
    private const STRUCTURE_HEADERS = ['No', 'Unit/Bagian', 'Sub Bagian', 'Jabatan', 'Strata RM', 'Bidang', 'Kuota', 'Keterangan'];

    private const REGIONAL_OFFICE = 'Regional Office (Kantor Direksi Pontianak)';

    /** @var list<string> */
    private array $workbookFiles = [];

    private function cleanUpWorkbooks(): void
    {
        foreach ($this->workbookFiles as $file) {
            @unlink($file);
        }
    }

    private function seatRow(Unit $unit, string $nama, string $rm, string $bidang, int $kuota): void
    {
        $position = Position::firstOrCreate(['nama' => $nama], ['level' => in_array($rm, ['RM-1', 'RM-2', 'RM-3'], true) ? 'Karpim' : 'Karpel', 'rm_level' => $rm, 'is_active' => true]);
        DB::table('position_unit')->insert(['unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => $bidang, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Bagian Tanaman (Kepala Bagian 1, Asisten Pemetaan 2) dan Kebun Tajati (Kepala Bagian 1, Manajer 1). */
    private function baseStructure(): void
    {
        $tanaman = Unit::create(['nama' => 'Bagian Tanaman', 'kode' => 'Bagian', 'wilayah' => self::REGIONAL_OFFICE, 'is_active' => true, 'urutan' => 1]);
        $tajati = Unit::create(['nama' => 'Kebun Tajati', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => true, 'urutan' => 2]);
        $this->seatRow($tanaman, 'Kepala Bagian', 'RM-1', 'TAN', 1);
        $this->seatRow($tanaman, 'Asisten Pemetaan', 'RM-3', 'TAN', 2);
        $this->seatRow($tajati, 'Kepala Bagian', 'RM-1', 'TAN', 1);
        $this->seatRow($tajati, 'Manajer', 'RM-1', 'TAN', 1);
    }

    /**
     * Lampiran SK dengan baris [unit, jabatan, strata RM, bidang, kuota].
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: int|string|null}>  $rows
     * @param  list<string>|null  $headers
     */
    private function uploadWorkbook(array $rows, ?array $headers = null): UploadedFile
    {
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray(
            [$headers ?? self::STRUCTURE_HEADERS, ...array_map(fn (array $r, int $i) => [$i + 1, $r[0], '-', $r[1], $r[2], $r[3], $r[4], null], $rows, array_keys($rows))],
            null,
            'A1'
        );
        $path = tempnam(sys_get_temp_dir(), 'sk').'.xlsx';
        (new Xlsx($sheet))->save($path);
        $this->workbookFiles[] = $path;

        return new UploadedFile($path, 'lampiran.xlsx', null, null, true);
    }
}
