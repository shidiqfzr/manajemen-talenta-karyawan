<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Formasi\FormasiExcelParser;
use App\Services\Structure\StructureDecreeService;
use App\Services\Structure\StructureTemplateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesStructureFixtures;
use Tests\TestCase;

/**
 * SUT: App\Services\Structure\StructureTemplateBuilder (template lampiran SK berisi struktur saat ini).
 */
class StructureTemplateBuilderTest extends TestCase
{
    use CreatesStructureFixtures;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->travelTo('2026-11-17 08:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->baseStructure();
    }

    protected function tearDown(): void
    {
        $this->cleanUpWorkbooks();
        parent::tearDown();
    }

    private function save(Spreadsheet $book): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        (new Xlsx($book))->save($path);
        $this->workbookFiles[] = $path;

        return new UploadedFile($path, 'lampiran.xlsx', null, null, true);
    }

    private function draftFrom(UploadedFile $file): array
    {
        return app(StructureDecreeService::class)->createDraft(
            ['sk_number' => 'DPSB/SKPTS/352/IX/2026', 'title' => 'Perubahan Struktur', 'sk_date' => '2026-09-01'],
            $file,
            null,
            $this->admin,
        )->diff['changes'];
    }

    public function test_template_lists_the_current_structure_in_the_upload_format(): void
    {
        $file = $this->save(app(StructureTemplateBuilder::class)->build());

        $result = app(FormasiExcelParser::class)->parse($file->getRealPath());

        $this->assertSame([], $result['errors']);
        $this->assertSame([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM-1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM-3', 'TAN', 2],
            ['Kebun Tajati', 'Kepala Bagian', 'RM-1', 'TAN', 1],
            ['Kebun Tajati', 'Manajer', 'RM-1', 'TAN', 1],
        ], array_map(fn (array $s) => [$s['unit'], $s['jabatan'], $s['rm_level'], $s['bidang'], $s['kuota']], $result['seats']));
    }

    public function test_template_excludes_jabatan_penampungan_and_inactive_units_but_keeps_norm_seats(): void
    {
        $tanaman = Unit::where('nama', 'Bagian Tanaman')->first();
        $advisor = Position::create(['nama' => 'Advisor RM-2', 'level' => 'Karpim', 'rm_level' => 'RM-2', 'is_active' => true, 'is_overflow' => true]);
        DB::table('position_unit')->insert(['unit_id' => $tanaman->id, 'position_id' => $advisor->id, 'kuota' => 0, 'bidang' => 'UMU', 'created_at' => now(), 'updated_at' => now()]);
        $this->seatRow($tanaman, 'Supir Pool', 'RM-5', 'UMU', 0);
        $tidur = Unit::create(['nama' => 'Kebun Tidur', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => false, 'urutan' => 9]);
        $this->seatRow($tidur, 'Manajer Tidur', 'RM-1', 'TAN', 1);

        $result = app(FormasiExcelParser::class)->parse($this->save(app(StructureTemplateBuilder::class)->build())->getRealPath());

        $jabatan = array_column($result['seats'], 'jabatan');
        $this->assertNotContains('Advisor RM-2', $jabatan);
        $this->assertNotContains('Manajer Tidur', $jabatan);
        $this->assertContains('Supir Pool', $jabatan);
        $this->assertSame(0, collect($result['seats'])->firstWhere('jabatan', 'Supir Pool')['kuota']);
    }

    public function test_uploading_the_unedited_template_gives_an_empty_diff(): void
    {
        $this->seatRow(Unit::where('nama', 'Bagian Tanaman')->first(), 'Krani Pemetaan', 'RM-5', 'TAN', 8);

        $changes = $this->draftFrom($this->save(app(StructureTemplateBuilder::class)->build()));

        $this->assertSame([], $changes);
    }

    public function test_editing_template_rows_gives_the_matching_changes(): void
    {
        $book = app(StructureTemplateBuilder::class)->build();
        $sheet = $book->getSheet(0);
        $sheet->setCellValue('G3', 5);                       // Asisten Pemetaan: kuota 2 -> 5
        $sheet->removeRow(5);                                 // Kebun Tajati / Manajer dihapus
        $sheet->fromArray([[5, 'Kebun Tajati', '-', 'Asisten Kebun', 'RM 3', 'TAN', 1, null]], null, 'A5');

        $types = array_column($this->draftFrom($this->save($book)), 'type');

        $this->assertEqualsCanonicalizing(['seat.update', 'seat.remove', 'seat.add'], $types);
    }

    public function test_names_that_look_like_formulas_stay_text(): void
    {
        $this->seatRow(Unit::where('nama', 'Bagian Tanaman')->first(), '=HYPERLINK("x")', 'RM-3', 'TAN', 1);

        $sheet = app(StructureTemplateBuilder::class)->build()->getSheet(0);
        $cell = collect(range(2, 8))->map(fn ($row) => $sheet->getCell("D{$row}"))->first(fn ($c) => str_contains((string) $c->getValue(), 'HYPERLINK'));

        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
        $this->assertSame('=HYPERLINK("x")', $cell->getValue());
    }

    public function test_the_second_sheet_explains_how_to_prepare_the_attachment(): void
    {
        $book = app(StructureTemplateBuilder::class)->build();

        $this->assertSame('Struktur Organisasi', $book->getSheet(0)->getTitle());
        $this->assertSame('Petunjuk', $book->getSheet(1)->getTitle());
        $text = collect($book->getSheet(1)->toArray())->flatten()->filter()->implode(' ');
        foreach (['Kuota', 'baris', 'SK'] as $word) {
            $this->assertStringContainsString($word, $text);
        }
    }
}
