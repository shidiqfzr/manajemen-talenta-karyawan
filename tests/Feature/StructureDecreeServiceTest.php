<?php

namespace Tests\Feature;

use App\Exceptions\StructureDecreeException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\StructureDecree;
use App\Models\Unit;
use App\Models\User;
use App\Services\Structure\StructureDecreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StructureDecreeServiceTest extends TestCase
{
    use RefreshDatabase;

    private const HEADERS = ['No', 'Unit/Bagian', 'Sub Bagian', 'Jabatan', 'Strata RM', 'Bidang', 'Kuota', 'Keterangan'];

    private const RO = 'Regional Office (Kantor Direksi Pontianak)';

    private StructureDecreeService $service;

    private User $admin;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->service = app(StructureDecreeService::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->baseStructure();
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function seat(Unit $unit, string $nama, string $rm, string $bidang, int $kuota): void
    {
        $position = Position::firstOrCreate(['nama' => $nama], ['level' => in_array($rm, ['RM-1', 'RM-2', 'RM-3'], true) ? 'Karpim' : 'Karpel', 'rm_level' => $rm, 'is_active' => true]);
        DB::table('position_unit')->insert(['unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => $bidang, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function baseStructure(): void
    {
        $tanaman = Unit::create(['nama' => 'Bagian Tanaman', 'kode' => 'Bagian', 'wilayah' => self::RO, 'is_active' => true, 'urutan' => 1]);
        $tajati = Unit::create(['nama' => 'Kebun Tajati', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => true, 'urutan' => 2]);
        $this->seat($tanaman, 'Kepala Bagian', 'RM-1', 'TAN', 1);
        $this->seat($tanaman, 'Asisten Pemetaan', 'RM-3', 'TAN', 2);
        $this->seat($tajati, 'Kepala Bagian', 'RM-1', 'TAN', 1);
        $this->seat($tajati, 'Manajer', 'RM-1', 'TAN', 1);
    }

    /** Workbook rows [unit, jabatan, rm, bidang, kuota] in the 8-column format. */
    private function upload(array $rows, ?array $headers = null): UploadedFile
    {
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray(
            [$headers ?? self::HEADERS, ...array_map(fn (array $r, int $i) => [$i + 1, $r[0], '-', $r[1], $r[2], $r[3], $r[4], null], $rows, array_keys($rows))],
            null,
            'A1'
        );
        $path = tempnam(sys_get_temp_dir(), 'sk').'.xlsx';
        (new Xlsx($sheet))->save($path);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, 'lampiran.xlsx', null, null, true);
    }

    /** Workbook equal to the base structure, with Asisten Pemetaan changed to a quota of 3. */
    private function changedWorkbook(): UploadedFile
    {
        return $this->upload([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 3],
        ]);
    }

    private function data(array $override = []): array
    {
        return $override + [
            'sk_number' => ' DPSB/SKPTS/ 352 /IX/2026 ',
            'title' => 'Perubahan Struktur Organisasi Region Office PT Perkebunan Nusantara IV',
            'sk_date' => '2026-09-01',
        ];
    }

    private function decreeRow(array $override = []): StructureDecree
    {
        return StructureDecree::create($override + [
            'sk_number' => 'DPSB/SKPTS/100/I/2026',
            'title' => 'SK Lama',
            'sk_date' => '2026-01-01',
            'effective_date' => '2026-01-01',
            'status' => StructureDecree::STATUS_APPLIED,
            'source_path' => 'structure-decrees/0/source.xlsx',
            'source_sha256' => str_repeat('b', 64),
        ]);
    }

    private function assertDecreeError(callable $callback, string $contains): void
    {
        try {
            $callback();
        } catch (StructureDecreeException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());

            return;
        }

        $this->fail("Expected StructureDecreeException containing '{$contains}'.");
    }

    public function test_create_draft_stores_files_hash_and_diff(): void
    {
        $file = $this->changedWorkbook();

        $decree = $this->service->createDraft($this->data(), $file, null, $this->admin);

        $this->assertSame(StructureDecree::STATUS_DRAFT, $decree->status);
        $this->assertSame('DPSB/SKPTS/352/IX/2026', $decree->sk_number);
        $this->assertSame('2026-09-01', $decree->effective_date->toDateString());
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $decree->source_sha256);
        Storage::disk('local')->assertExists($decree->source_path);
        $this->assertSame(['seat.update'], array_column($decree->diff['changes'], 'type'));
        $this->assertNotNull($decree->target);
    }

    public function test_duplicate_sk_number_is_rejected_even_with_different_spacing(): void
    {
        $this->decreeRow(['sk_number' => 'DPSB/SKPTS/352/IX/2026']);

        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(), $this->changedWorkbook(), null, $this->admin), 'sudah terdaftar');
    }

    public function test_second_open_decree_is_rejected(): void
    {
        $this->decreeRow(['status' => StructureDecree::STATUS_DRAFT]);

        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(), $this->changedWorkbook(), null, $this->admin), 'Masih terbuka');
    }

    public function test_effective_date_before_the_latest_applied_decree_is_rejected(): void
    {
        $this->decreeRow(['effective_date' => '2026-09-01']);

        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(['sk_number' => 'DPSB/SKPTS/353/X/2026', 'effective_date' => '2026-08-01']), $this->changedWorkbook(), null, $this->admin), 'tanggal berlaku');
    }

    public function test_wrong_headers_or_non_xlsx_are_rejected_with_a_message_and_store_nothing(): void
    {
        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(), $this->upload([['Bagian Tanaman', 'X', 'RM 1', 'TAN', 1]], ['A', 'B']), null, $this->admin), 'Judul kolom');
        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(), UploadedFile::fake()->create('lampiran.pdf', 10, 'application/pdf'), null, $this->admin), '.xlsx');

        $this->assertSame(0, StructureDecree::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_user_without_the_permission_cannot_create_a_draft(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(), $this->changedWorkbook(), null, $user), 'tidak berwenang');
    }

    public function test_identical_workbook_blocks_submission_with_a_no_changes_message(): void
    {
        $decree = $this->service->createDraft($this->data(), $this->upload([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 2],
        ]), null, $this->admin);

        $this->assertTrue($this->service->preview($decree)->isEmpty());
        $this->assertStringContainsString('Tidak ada perubahan', $this->service->blockers($decree)[0]);
    }

    public function test_update_review_applies_mapping_and_closing_to_the_diff(): void
    {
        $decree = $this->service->createDraft($this->data(), $this->upload([
            ['Kebun Tajati Baru', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Manajer', 'RM 1', 'TAN', 1],
        ]), null, $this->admin);
        $this->assertContains('unit.add', array_column($decree->diff['changes'], 'type'));

        $decree = $this->service->updateReview($decree, ['Kebun Tajati' => 'Kebun Tajati Baru'], [], [], [], $this->admin);

        $this->assertSame(['unit.rename'], array_column($decree->diff['changes'], 'type'));

        $decree = $this->service->updateReview($decree, [], ['Kebun Tajati'], [], [], $this->admin);

        $this->assertEqualsCanonicalizing(['unit.add', 'seat.add', 'seat.add', 'unit.close'], array_column($decree->diff['changes'], 'type'));
    }

    public function test_new_units_need_kode_and_a_known_wilayah_before_submission(): void
    {
        $decree = $this->service->createDraft($this->data(), $this->upload([['Kebun Baru', 'Manajer', 'RM 1', 'TAN', 1]]), null, $this->admin);

        $this->assertStringContainsString('Kebun Baru', implode(' ', $this->service->blockers($decree)));

        $decree = $this->service->updateReview($decree, [], [], ['Kebun Baru' => ['kode' => 'Kebun', 'wilayah' => 'Mars', 'urutan' => 9]], [], $this->admin);
        $this->assertStringContainsString('wilayah', implode(' ', $this->service->blockers($decree)));

        $decree = $this->service->updateReview($decree, [], [], ['Kebun Baru' => ['kode' => 'Kebun', 'wilayah' => self::RO, 'urutan' => 9]], [], $this->admin);
        $this->assertSame([], $this->service->blockers($decree));
    }

    public function test_position_rename_is_blocked_when_the_position_is_used_outside_the_workbook(): void
    {
        $decree = $this->service->createDraft($this->data(), $this->upload([
            ['Bagian Tanaman', 'Kepala Bagian Tanaman', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 2],
        ]), null, $this->admin);

        $decree = $this->service->updateReview($decree, [], [], [], ['Kepala Bagian' => 'Kepala Bagian Tanaman'], $this->admin);

        $this->assertStringContainsString('Kebun Tajati', implode(' ', $this->service->blockers($decree)));
    }

    public function test_impact_lists_holders_over_quota_seats_and_scheduled_mutations_for_a_removed_seat(): void
    {
        Employee::factory()->count(2)->create(['unit_kerja' => 'Bagian Tanaman', 'jabatan' => 'Asisten Pemetaan']);
        $other = Employee::factory()->create(['unit_kerja' => 'Kebun Tajati', 'jabatan' => 'Manajer']);
        JobHistory::create(['employee_nik' => $other->nik, 'jabatan' => 'Asisten Pemetaan', 'unit_kerja' => 'Bagian Tanaman', 'tmt_awal' => '2026-12-01', 'is_applied' => false]);
        $decree = $this->service->createDraft($this->data(), $this->upload([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1]]), null, $this->admin);

        $impact = $this->service->impact($decree);

        $this->assertSame(2, $impact['affected_employees']);
        $this->assertSame(1, $impact['over_quota_seats']);
        $this->assertCount(1, $impact['scheduled_mutations']);
        $this->assertStringContainsString('Asisten Pemetaan', $impact['scheduled_mutations'][0]);
    }
}
