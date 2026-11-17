<?php

namespace Tests\Feature;

use App\Exceptions\StructureDecreeException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\StructureDecree;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\User;
use App\Services\Formasi\MasterFormasiService;
use App\Services\ManPowerPlanningService;
use App\Services\Mpp\MppPeriodService;
use App\Services\Structure\StructureDecreeService;
use App\Services\Structure\StructureReader;
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
        // Jam tetap: tanggal berlaku "masa lalu" dan "masa depan" pada pengujian tidak bergantung pada hari menjalankan.
        $this->travelTo('2026-11-17 08:00:00');
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

    private function submittedDecree(?User $by = null): StructureDecree
    {
        // Tanggal berlaku di masa depan: persetujuan tidak langsung menerapkan SK (Terjadwal).
        $decree = $this->service->createDraft($this->data(['effective_date' => now()->addMonth()->toDateString()]), $this->changedWorkbook(), null, $this->admin);

        return $this->service->submit($decree, $by ?? $this->admin);
    }

    public function test_submit_is_blocked_while_blockers_exist(): void
    {
        $decree = $this->service->createDraft($this->data(), $this->upload([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 2],
        ]), null, $this->admin);

        $this->assertDecreeError(fn () => $this->service->submit($decree, $this->admin), 'Tidak ada perubahan');
        $this->assertSame(StructureDecree::STATUS_DRAFT, $decree->fresh()->status);
    }

    public function test_submit_freezes_the_review_digest_and_records_the_submitter(): void
    {
        $decree = $this->submittedDecree();

        $this->assertSame(StructureDecree::STATUS_SUBMITTED, $decree->status);
        $this->assertSame($this->service->preview($decree)->digest(), $decree->review_digest);
        $this->assertSame($this->admin->id, $decree->submitted_by);
        $this->assertNotNull($decree->submitted_at);
    }

    public function test_approval_required_blocks_the_submitter_from_approving(): void
    {
        config(['hris.structure.require_approval' => true]);
        $decree = $this->submittedDecree();
        $checker = User::factory()->create(['role' => 'admin']);

        $this->assertDecreeError(fn () => $this->service->approve($decree, $this->admin), 'empat mata');

        $approved = $this->service->approve($decree->fresh(), $checker);
        $this->assertSame(StructureDecree::STATUS_APPROVED, $approved->status);
        $this->assertSame($checker->id, $approved->decided_by);
    }

    public function test_approval_not_required_lets_the_submitter_approve(): void
    {
        config(['hris.structure.require_approval' => false]);

        $approved = $this->service->approve($this->submittedDecree(), $this->admin);

        $this->assertSame(StructureDecree::STATUS_APPROVED, $approved->status);
    }

    public function test_reject_and_cancel_need_a_reason_of_ten_characters_and_free_the_open_slot(): void
    {
        $decree = $this->submittedDecree();

        $this->assertDecreeError(fn () => $this->service->reject($decree, $this->admin, 'pendek'), '10 karakter');
        $rejected = $this->service->reject($decree, $this->admin, 'Lampiran belum sesuai SK asli.');
        $this->assertSame(StructureDecree::STATUS_REJECTED, $rejected->status);
        $this->assertSame('Lampiran belum sesuai SK asli.', $rejected->decision_reason);

        $next = $this->service->createDraft($this->data(['sk_number' => 'DPSB/SKPTS/353/X/2026']), $this->changedWorkbook(), null, $this->admin);
        $this->assertDecreeError(fn () => $this->service->cancel($next, $this->admin, 'pendek'), '10 karakter');
        $cancelled = $this->service->cancel($next, $this->admin, 'Salah unggah lampiran.');
        $this->assertSame(StructureDecree::STATUS_CANCELLED, $cancelled->status);

        $this->service->createDraft($this->data(['sk_number' => 'DPSB/SKPTS/354/X/2026']), $this->changedWorkbook(), null, $this->admin);
        $this->assertSame(1, StructureDecree::whereIn('status', StructureDecree::OPEN_STATUSES)->count());
    }

    public function test_a_decree_goes_stale_when_the_structure_changes_after_submission(): void
    {
        $decree = $this->submittedDecree();
        DB::table('position_unit')->where('unit_id', Unit::where('nama', 'Bagian Tanaman')->value('id'))
            ->where('position_id', Position::where('nama', 'Asisten Pemetaan')->value('id'))->update(['kuota' => 5]);

        $this->assertTrue($this->service->isStale($decree));
        $this->assertDecreeError(fn () => $this->service->approve($decree, $this->admin), 'tinjau ulang');
        $this->assertSame(StructureDecree::STATUS_STALE, $decree->fresh()->status);

        // Tinjau ulang: kembali ke draf, ajukan lagi, lalu disetujui.
        $draft = $this->service->updateReview($decree->fresh(), [], [], [], [], $this->admin);
        $this->assertSame(StructureDecree::STATUS_DRAFT, $draft->status);
        $approved = $this->service->approve($this->service->submit($draft, $this->admin), $this->admin);
        $this->assertSame(StructureDecree::STATUS_APPROVED, $approved->status);
    }

    public function test_each_transition_writes_a_master_audit_entry_with_the_decree_id(): void
    {
        $decree = $this->service->approve($this->submittedDecree(), $this->admin);

        $actions = MasterAuditLog::where('decree_id', $decree->id)->orderBy('id')->pluck('action')->all();

        $this->assertSame(['decree.submitted', 'decree.approved'], $actions);
    }

    public function test_a_user_without_the_permission_cannot_transition(): void
    {
        $decree = $this->submittedDecree();
        $user = User::factory()->create(['role' => 'user']);

        $this->assertDecreeError(fn () => $this->service->approve($decree, $user), 'tidak berwenang');
        $this->assertDecreeError(fn () => $this->service->reject($decree, $user, 'Alasan yang cukup panjang'), 'tidak berwenang');
        $this->assertDecreeError(fn () => $this->service->cancel($decree, $user, 'Alasan yang cukup panjang'), 'tidak berwenang');
    }

    // ───────────────────────── Apply ─────────────────────────

    /**
     * Draft, review choices, submit and approve in one go; a decree whose effective date has arrived is applied by approve.
     *
     * @param  array{mapping?: array, closed?: array, new_units?: array, positions?: array, effective?: ?string}  $choices
     */
    private function approvedDecree(array $rows, array $choices = [], ?User $approver = null): StructureDecree
    {
        $decree = $this->service->createDraft($this->data(['effective_date' => $choices['effective'] ?? '2026-09-01']), $this->upload($rows), null, $this->admin);
        $decree = $this->service->updateReview($decree, $choices['mapping'] ?? [], $choices['closed'] ?? [], $choices['new_units'] ?? [], $choices['positions'] ?? [], $this->admin);

        return $this->service->approve($this->service->submit($decree, $this->admin), $approver ?? $this->admin);
    }

    public function test_apply_reproduces_the_workbook_structure_and_leaves_uncovered_units_alone(): void
    {
        $lain = Unit::create(['nama' => 'Bagian Lain', 'kode' => 'Bagian', 'wilayah' => self::RO, 'is_active' => true, 'urutan' => 3]);
        $this->seat($lain, 'Kepala Lain', 'RM-1', 'UMU', 1);
        $before = app(StructureReader::class)->current()['units']['Bagian Lain'];

        $rows = [
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'KEU', 3],
            ['Bagian Tanaman', 'Krani Pemetaan', 'RM 5', 'TAN', 8],
            ['Kebun Tajati Baru', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Manajer', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Asisten Kebun', 'RM 3', 'TAN', 1],
            ['Kebun Kemitraan', 'Manajer Kemitraan', 'RM 1', 'TAN', 1],
        ];
        $decree = $this->approvedDecree($rows, [
            'mapping' => ['Kebun Tajati' => 'Kebun Tajati Baru'],
            'new_units' => ['Kebun Kemitraan' => ['kode' => 'Kebun', 'wilayah' => self::RO, 'urutan' => 10]],
        ]);

        $this->assertSame(StructureDecree::STATUS_APPLIED, $decree->status);
        $current = app(StructureReader::class)->current()['units'];
        $this->assertSame(['Kepala Bagian', 'Asisten Pemetaan', 'Krani Pemetaan'], array_keys($current['Bagian Tanaman']['seats']));
        $this->assertEquals(['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'KEU', 'kuota' => 3], $current['Bagian Tanaman']['seats']['Asisten Pemetaan']);
        $this->assertEquals(['rm_level' => 'RM-5', 'level' => 'Karpel', 'bidang' => 'TAN', 'kuota' => 8], $current['Bagian Tanaman']['seats']['Krani Pemetaan']);
        $this->assertArrayNotHasKey('Kebun Tajati', $current);
        $this->assertEqualsCanonicalizing(['Kepala Bagian', 'Manajer', 'Asisten Kebun'], array_keys($current['Kebun Tajati Baru']['seats']));
        $this->assertSame('Regional Office (Kantor Direksi Pontianak)', $current['Kebun Kemitraan']['wilayah']);
        $this->assertSame($before, $current['Bagian Lain']);
    }

    public function test_apply_records_aliases_and_audit_entries_linked_to_the_decree(): void
    {
        $decree = $this->approvedDecree([
            ['Kebun Tajati Baru', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Manajer', 'RM 1', 'TAN', 1],
        ], ['mapping' => ['Kebun Tajati' => 'Kebun Tajati Baru']]);

        $unitId = Unit::where('nama', 'Kebun Tajati Baru')->value('id');
        $this->assertSame($unitId, UnitAlias::where('alias_key', UnitAlias::keyFor('Kebun Tajati'))->value('unit_id'));
        $logs = MasterAuditLog::where('decree_id', $decree->id)->get();
        $this->assertContains('unit.renamed', $logs->pluck('action')->all());
        $this->assertSame('Penerapan SK DPSB/SKPTS/352/IX/2026', $logs->firstWhere('action', 'unit.renamed')->reason);
    }

    public function test_apply_stores_a_snapshot_and_sets_applied_at(): void
    {
        $decree = $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 4]]);

        $this->assertNotNull($decree->applied_at);
        $this->assertSame(4, $decree->snapshot['units']['Bagian Tanaman']['seats']['Kepala Bagian']['kuota']);
    }

    public function test_seat_removed_with_holders_gets_quota_zero_and_the_holders_stay_on_the_worklist(): void
    {
        Employee::factory()->count(2)->create(['unit_kerja' => 'Bagian Tanaman', 'jabatan' => 'Asisten Pemetaan']);

        $decree = $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1]]);

        $seats = app(StructureReader::class)->current()['units']['Bagian Tanaman']['seats'];
        $this->assertSame(0, $seats['Asisten Pemetaan']['kuota']);
        $this->assertSame(2, Employee::where('unit_kerja', 'Bagian Tanaman')->where('jabatan', 'Asisten Pemetaan')->count());
        $worklist = $this->service->worklist($decree);
        $this->assertCount(2, $worklist);
        $this->assertSame(['Asisten Pemetaan'], $worklist->pluck('jabatan')->unique()->values()->all());
    }

    public function test_seat_without_holders_is_removed_and_a_closed_unit_with_employees_stays_active_and_is_reported(): void
    {
        Unit::create(['nama' => 'Kebun Sepi', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => true, 'urutan' => 5]);
        Employee::factory()->create(['unit_kerja' => 'Kebun Tajati', 'jabatan' => 'Manajer']);

        $decree = $this->approvedDecree(
            [['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1]],
            ['closed' => ['Kebun Tajati', 'Kebun Sepi']],
        );

        $this->assertDatabaseMissing('position_unit', ['position_id' => Position::where('nama', 'Asisten Pemetaan')->value('id')]);
        $this->assertTrue((bool) Unit::where('nama', 'Kebun Tajati')->value('is_active'));
        $this->assertFalse((bool) Unit::where('nama', 'Kebun Sepi')->value('is_active'));
        $this->assertStringContainsString('Kebun Tajati', implode(' ', $decree->unit_mapping['apply_warnings']));
    }

    public function test_apply_creates_a_new_norm_seat_with_quota_zero(): void
    {
        $this->approvedDecree([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 2],
            ['Bagian Tanaman', 'Supir Pool', 'RM 5', 'UMU', null],
        ]);

        $seat = app(StructureReader::class)->current()['units']['Bagian Tanaman']['seats']['Supir Pool'];
        $this->assertSame(0, $seat['kuota']);
        $this->assertSame('Karpel', $seat['level']);
    }

    public function test_apply_reactivates_an_inactive_unit_listed_in_the_workbook(): void
    {
        $tidur = Unit::create(['nama' => 'Kebun Tidur', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => false, 'urutan' => 6]);
        $this->seat($tidur, 'Manajer', 'RM-1', 'TAN', 1);

        $this->approvedDecree([['Kebun Tidur', 'Manajer', 'RM 1', 'TAN', 2]]);

        $this->assertTrue((bool) $tidur->fresh()->is_active);
        $this->assertSame(2, (int) DB::table('position_unit')->where('unit_id', $tidur->id)->value('kuota'));
    }

    public function test_apply_leaves_jabatan_penampungan_rows_untouched(): void
    {
        $advisor = Position::create(['nama' => 'Advisor RM-2', 'level' => 'Karpim', 'rm_level' => 'RM-2', 'is_active' => true, 'is_overflow' => true]);
        DB::table('position_unit')->insert(['unit_id' => Unit::where('nama', 'Bagian Tanaman')->value('id'), 'position_id' => $advisor->id, 'kuota' => 0, 'bidang' => 'UMU', 'created_at' => now(), 'updated_at' => now()]);

        $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1]]);

        $this->assertDatabaseHas('position_unit', ['position_id' => $advisor->id, 'kuota' => 0]);
    }

    public function test_position_rename_keeps_holders_on_their_seat_and_syncs_their_jabatan(): void
    {
        $positionId = Position::where('nama', 'Asisten Pemetaan')->value('id');
        $holder = Employee::factory()->create(['unit_kerja' => 'Bagian Tanaman', 'jabatan' => 'Asisten Pemetaan']);

        $this->approvedDecree([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan & Survei', 'RM 3', 'TAN', 2],
        ], ['positions' => ['Asisten Pemetaan' => 'Asisten Pemetaan & Survei']]);

        $this->assertSame('Asisten Pemetaan & Survei', Position::find($positionId)->nama);
        $this->assertSame('Asisten Pemetaan & Survei', $holder->fresh()->jabatan);
        $this->assertSame(2, (int) DB::table('position_unit')->where('position_id', $positionId)->value('kuota'));
    }

    public function test_an_rm_change_across_the_karpim_karpel_boundary_updates_level_and_band(): void
    {
        $this->approvedDecree([
            ['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 5', 'TAN', 2],
        ]);

        $position = Position::where('nama', 'Asisten Pemetaan')->first();
        $this->assertSame(['Karpel', 'RM-5'], [$position->level, $position->rm_level]);
    }

    public function test_future_effective_date_is_not_applied_by_approve_and_stays_scheduled(): void
    {
        $future = now()->addMonth()->toDateString();

        $decree = $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 7]], ['effective' => $future]);

        $this->assertSame(StructureDecree::STATUS_APPROVED, $decree->status);
        $this->assertSame(1, (int) DB::table('position_unit')->where('position_id', Position::where('nama', 'Kepala Bagian')->value('id'))->where('unit_id', Unit::where('nama', 'Bagian Tanaman')->value('id'))->value('kuota'));
        $this->assertDecreeError(fn () => $this->service->apply($decree), 'belum berlaku');
    }

    public function test_scheduled_apply_acts_as_the_approving_admin(): void
    {
        $approver = User::factory()->create(['role' => 'admin', 'name' => 'Penyetuju Utama']);
        $future = now()->addMonth();
        $decree = $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 7]], ['effective' => $future->toDateString()], $approver);

        $this->travelTo($future->copy()->addDay());
        $applied = $this->service->apply($decree);

        $this->assertSame(StructureDecree::STATUS_APPLIED, $applied->status);
        $this->assertSame($approver->id, MasterAuditLog::where('decree_id', $decree->id)->where('action', 'seat.updated')->value('user_id'));
    }

    public function test_a_decree_that_went_stale_after_approval_is_not_applied(): void
    {
        $future = now()->addMonth();
        $decree = $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 7]], ['effective' => $future->toDateString()]);
        DB::table('position_unit')->where('unit_id', Unit::where('nama', 'Bagian Tanaman')->value('id'))->where('position_id', Position::where('nama', 'Kepala Bagian')->value('id'))->update(['kuota' => 3]);

        $this->travelTo($future->copy()->addDay());
        $result = $this->service->apply($decree);

        $this->assertSame(StructureDecree::STATUS_STALE, $result->status);
        $this->assertSame(3, (int) DB::table('position_unit')->where('unit_id', Unit::where('nama', 'Bagian Tanaman')->value('id'))->where('position_id', Position::where('nama', 'Kepala Bagian')->value('id'))->value('kuota'));
    }

    public function test_failure_rolls_everything_back_marks_the_decree_failed_and_keeps_the_slot_until_retried(): void
    {
        $this->app->bind(MasterFormasiService::class, fn () => new class extends MasterFormasiService
        {
            public function addSeat(Unit $unit, Position $position, int $kuota, ?string $bidang, User $user, bool $allowZero = false): int
            {
                throw new \RuntimeException('boom');
            }
        });

        $decree = $this->approvedDecree([
            ['Kebun Tajati Baru', 'Kepala Bagian', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Manajer', 'RM 1', 'TAN', 1],
            ['Kebun Tajati Baru', 'Asisten Kebun', 'RM 3', 'TAN', 1],
        ], ['mapping' => ['Kebun Tajati' => 'Kebun Tajati Baru']]);

        $this->assertSame(StructureDecree::STATUS_FAILED, $decree->status);
        $this->assertStringContainsString('boom', $decree->failure_message);
        $this->assertTrue(Unit::where('nama', 'Kebun Tajati')->exists());
        $this->assertFalse(Unit::where('nama', 'Kebun Tajati Baru')->exists());
        $this->assertContains('decree.failed', MasterAuditLog::where('decree_id', $decree->id)->pluck('action')->all());
        $this->assertDecreeError(fn () => $this->service->createDraft($this->data(['sk_number' => 'DPSB/SKPTS/353/X/2026']), $this->changedWorkbook(), null, $this->admin), 'Masih terbuka');

        $this->app->bind(MasterFormasiService::class, fn () => new MasterFormasiService);
        $retried = $this->service->apply($decree->fresh());

        $this->assertSame(StructureDecree::STATUS_APPLIED, $retried->status);
        $this->assertTrue(Unit::where('nama', 'Kebun Tajati Baru')->exists());
    }

    public function test_apply_freezes_the_previous_mpp_month_before_changing_the_structure(): void
    {
        $service = app(MppPeriodService::class);
        $before = app(ManPowerPlanningService::class)->getMppMatrix(2026, 8)['stats']['total_formasi'];

        $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1], ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 12]]);

        $this->assertSame($before, $service->find(2026, 8)->snapshot['stats']['total_formasi']);

        // Perekaman bulanan pukul 01:00 tidak menimpa salinan yang dibekukan sebelum SK diterapkan.
        $this->artisan('hris:mpp-capture-period', ['--year' => 2026, '--month' => 8])->assertExitCode(0);
        $this->assertSame($before, $service->find(2026, 8)->snapshot['stats']['total_formasi']);
        $this->assertSame($before + 10, app(ManPowerPlanningService::class)->getMppMatrix(2026, 11)['stats']['total_formasi']);
    }

    public function test_a_closed_mpp_period_is_unchanged_by_apply(): void
    {
        $service = app(MppPeriodService::class);
        $service->capture(2026, 8, null);
        $service->close(2026, 8, null, $this->admin);
        $before = $service->find(2026, 8)->snapshot['stats']['total_formasi'];

        $this->approvedDecree([['Bagian Tanaman', 'Kepala Bagian', 'RM 1', 'TAN', 1], ['Bagian Tanaman', 'Asisten Pemetaan', 'RM 3', 'TAN', 12]]);

        $period = $service->find(2026, 8);
        $this->assertSame('closed', $period->status);
        $this->assertSame($before, $period->snapshot['stats']['total_formasi']);
    }

    public function test_a_rejected_or_cancelled_sk_number_can_be_used_again(): void
    {
        $decree = $this->submittedDecree();
        $this->service->reject($decree, $this->admin, 'Lampiran belum sesuai SK asli.');

        $again = $this->service->createDraft($this->data(), $this->changedWorkbook(), null, $this->admin);

        $this->assertSame($decree->sk_number, $again->sk_number);
        $this->assertSame(2, StructureDecree::where('sk_number', $decree->sk_number)->count());
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
