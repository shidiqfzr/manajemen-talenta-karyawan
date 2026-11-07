<?php

namespace Tests\Feature;

use App\Exports\MutasiHistoryExport;
use App\Livewire\Admin\MutasiHistory;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Mutasi\MutasiHistoryReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

use function Livewire\invade;

class MutasiHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_new_employee_seeds_data_awal_history_without_a_fabricated_sk_number(): void
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten Afdeling', 'unit_kerja' => 'Kebun Danau Salak']);

        $row = JobHistory::where('employee_nik', $emp->nik)->sole();
        $this->assertSame(JobHistory::SUMBER_DATA_AWAL, $row->sumber);
        $this->assertNull($row->nomor_sk);
        $this->assertNull($row->tanggal_sk);
        $this->assertTrue($row->isRecordOnly());

        $emp->update(['jabatan' => 'Manajer Kebun']);
        $update = JobHistory::where('employee_nik', $emp->nik)->whereNull('tmt_akhir')->sole();
        $this->assertSame(JobHistory::SUMBER_PEMBARUAN_DATA, $update->sumber);
        $this->assertNull($update->nomor_sk);
    }

    public function test_history_screen_shows_official_mutations_by_default_and_filters_by_source(): void
    {
        Employee::factory()->create(['nama' => 'Siti Data Awal', 'jabatan' => 'Asisten Afdeling', 'unit_kerja' => 'Kebun Danau Salak']);
        $official = Employee::factory()->create(['nama' => 'Rudi Mutasi Resmi', 'jabatan' => 'Asisten Afdeling', 'unit_kerja' => 'Kebun Danau Salak']);
        JobHistory::create([
            'employee_nik' => $official->nik, 'jabatan' => 'Manajer Kebun', 'unit_kerja' => 'Kebun Danau Salak',
            'tmt_awal' => now()->subMonth()->toDateString(), 'jenis_mutasi' => 'PROMOSI', 'nomor_sk' => 'SK/001/2026',
            'status_penugasan' => 'Definitif', 'is_applied' => true,
        ]);

        $this->assertSame(JobHistory::SUMBER_MUTASI, JobHistory::where('nomor_sk', 'SK/001/2026')->value('sumber'));

        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSee('Rudi Mutasi Resmi')
            ->assertDontSee('Siti Data Awal')
            ->set('sumberFilter', 'semua')
            ->assertSee('Siti Data Awal')
            ->assertSee('Data Awal')
            ->set('sumberFilter', JobHistory::SUMBER_DATA_AWAL)
            ->assertSee('Siti Data Awal')
            ->assertDontSee('SK/001/2026')
            ->set('sumberFilter', 'nilai-asing')
            ->assertSee('Rudi Mutasi Resmi')
            ->assertDontSee('Siti Data Awal')
            ->call('resetFilters')
            ->assertSet('sumberFilter', '');

        // Tidak ada baris yang hilang: dua baris data awal pegawai dan satu mutasi resmi tetap tersimpan.
        $this->assertSame(3, JobHistory::count());
        $this->assertSame(2, JobHistory::where('sumber', JobHistory::SUMBER_DATA_AWAL)->count());
    }

    public function test_export_follows_the_source_filter(): void
    {
        Employee::factory()->create(['nama' => 'Siti Data Awal', 'jabatan' => 'Asisten', 'unit_kerja' => 'Kebun Danau Salak']);

        $resmi = (new MutasiHistoryExport(['sumber' => 'resmi']))->query()->count();
        $semua = (new MutasiHistoryExport(['sumber' => 'semua']))->query()->count();

        $this->assertSame(0, $resmi);
        $this->assertSame(1, $semua);
    }

    public function test_export_uses_a_given_query_instead_of_rebuilding_filters(): void
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten', 'unit_kerja' => 'Kebun Danau Salak']);
        foreach (['SK/101/2026', 'SK/102/2026'] as $sk) {
            JobHistory::create([
                'employee_nik' => $emp->nik, 'jabatan' => 'Manajer Kebun', 'unit_kerja' => 'Kebun Danau Salak',
                'tmt_awal' => now()->subMonth()->toDateString(), 'jenis_mutasi' => 'PROMOSI', 'nomor_sk' => $sk,
                'status_penugasan' => 'Definitif', 'is_applied' => true,
            ]);
        }

        $given = JobHistory::query()->where('nomor_sk', 'SK/101/2026');
        $export = new MutasiHistoryExport(['sumber' => 'resmi'], null, $given);

        $this->assertSame(2, (new MutasiHistoryExport(['sumber' => 'resmi']))->query()->count());
        $this->assertSame(1, $export->query()->count());
    }

    public function test_export_without_a_query_still_builds_from_filters(): void
    {
        Employee::factory()->create(['jabatan' => 'Asisten', 'unit_kerja' => 'Kebun Danau Salak']);

        $this->assertSame(1, (new MutasiHistoryExport(['sumber' => 'semua'], null, null))->query()->count());
        $this->assertSame(0, (new MutasiHistoryExport(['sumber' => 'resmi'], null, null))->query()->count());
    }

    public function test_export_labels_the_source_of_every_row_and_states_its_scope(): void
    {
        $emp = Employee::factory()->create(['nama' => 'Siti Data Awal', 'jabatan' => 'Asisten', 'unit_kerja' => 'Kebun Danau Salak']);
        JobHistory::create([
            'employee_nik' => $emp->nik, 'jabatan' => 'Manajer Kebun', 'unit_kerja' => 'Kebun Danau Salak',
            'tmt_awal' => now()->subMonth()->toDateString(), 'jenis_mutasi' => 'PROMOSI', 'nomor_sk' => 'SK/002/2026',
            'status_penugasan' => 'Definitif', 'is_applied' => true,
        ]);

        $export = new MutasiHistoryExport(['sumber' => 'semua', 'sortBy' => 'id', 'sortDirection' => 'asc']);
        $headings = $export->headings();
        $col = array_search('SUMBER CATATAN', $headings, true);
        $this->assertSame(22, count($headings));
        $this->assertSame('JENIS MUTASI', $headings[$col - 1]);

        $rows = $export->query()->get()->map(fn ($r) => $export->map($r))->all();
        $this->assertSame('Data Awal', $rows[0][$col]);
        $this->assertSame('Bukan SK Mutasi (Catatan Data Kepegawaian)', $rows[0][array_search('STATUS REGULASI', $headings, true)]);
        $this->assertSame('Mutasi Resmi', $rows[1][$col]);
        $this->assertSame('Sesuai Standar Regulasi', $rows[1][array_search('STATUS REGULASI', $headings, true)]);
        $this->assertSame('SK/002/2026', $rows[1][array_search('NOMOR SK', $headings, true)]);

        $this->assertStringContainsString('Semua Catatan', $export->properties()['subject']);
        $this->assertStringContainsString('Mutasi Resmi saja', (new MutasiHistoryExport(['sumber' => 'resmi']))->properties()['subject']);
        $this->assertSame('O', array_keys($export->columnFormats())[1]);
    }

    public function test_can_render_mutasi_history_with_kpi_metrics(): void
    {
        $unitA = Unit::factory()->create(['nama' => 'Kebun Danau Salak']);
        $unitB = Unit::factory()->create(['nama' => 'Kantor Direksi']);

        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Danau Salak',
        ]);

        // 1 Active JobHistory
        JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Danau Salak',
            'tmt_awal' => now()->subYear()->toDateString(),
            'tmt_akhir' => null,
            'jenis_mutasi' => 'ROTASI',
            'status_penugasan' => 'Definitif',
            'is_applied' => true,
        ]);

        // 1 Scheduled Future JobHistory
        JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kantor Direksi',
            'tmt_awal' => now()->addDays(10)->toDateString(),
            'tmt_akhir' => null,
            'jenis_mutasi' => 'MUTASI_UNIT',
            'status_penugasan' => 'Definitif',
            'is_applied' => false,
            'nomor_sk' => 'SK/DIR/2026/099',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->assertSee('Total Riwayat Mutasi')
            ->assertSee('Aktif Menjabat')
            ->assertSee('Terjadwal (Staging)')
            ->assertSee('Budi Santoso')
            ->assertSee('Staging (Belum Aktif)')
            ->assertSee('SK/DIR/2026/099')
            ->assertSee('Aktivasi')
            ->assertSee('Batal');
    }

    public function test_result_count_is_announced_through_a_permanent_live_region(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->assertSeeHtml('role="status" aria-live="polite" aria-atomic="true">0 riwayat ditemukan');
    }

    public function test_can_filter_and_sort_mutasi_history(): void
    {
        $unitA = Unit::factory()->create(['nama' => 'Kebun A']);
        $unitB = Unit::factory()->create(['nama' => 'Kebun B']);

        $empA = Employee::factory()->create(['nama' => 'Ahmad Dahlan', 'unit_kerja' => 'Kebun A']);
        $empB = Employee::factory()->create(['nama' => 'Zainal Abidin', 'unit_kerja' => 'Kebun B']);

        JobHistory::create([
            'employee_nik' => $empA->nik,
            'jabatan' => 'Mandor Panen',
            'unit_kerja' => 'Kebun A',
            'tmt_awal' => '2025-01-01',
            'jenis_mutasi' => 'ROTASI',
            'status_penugasan' => 'Definitif',
            'is_applied' => true,
        ]);

        JobHistory::create([
            'employee_nik' => $empB->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun B',
            'tmt_awal' => '2026-06-01',
            'jenis_mutasi' => 'PROMOSI',
            'status_penugasan' => 'Definitif',
            'is_applied' => false,
        ]);

        // Filter by Scheduled
        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->set('statusFilter', 'scheduled')
            ->assertSee('Zainal Abidin')
            ->assertDontSee('Ahmad Dahlan')
            // Test Sorting
            ->call('sortBy', 'tmt_awal')
            ->assertSet('sortField', 'tmt_awal')
            ->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'tmt_awal')
            ->assertSet('sortDirection', 'desc')
            // Test Reset
            ->call('resetFilters')
            ->assertSet('statusFilter', '')
            ->assertSee('Ahmad Dahlan');
    }

    public function test_can_apply_due_scheduled_mutation(): void
    {
        $unit = Unit::factory()->create(['nama' => 'Kebun A']);
        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Krani Afdeling',
            'unit_kerja' => 'Kebun A',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        $dueJob = JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
            'tmt_awal' => now()->subDay()->toDateString(), // Due yesterday
            'jenis_mutasi' => 'PROMOSI',
            'status_penugasan' => 'Definitif',
            'is_applied' => false,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->assertSee('Aktivasi TMT Jatuh Tempo')
            ->call('applyNow', $dueJob->id)
            ->assertSee('Berhasil!');

        $dueJob->refresh();
        $this->assertTrue($dueJob->is_applied);

        $emp->refresh();
        $this->assertEquals('Asisten Afdeling', $emp->jabatan);
    }

    public function test_can_cancel_scheduled_mutation(): void
    {
        $unit = Unit::factory()->create(['nama' => 'Kebun A']);
        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
        ]);

        $futureJob = JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
            'tmt_awal' => now()->addDays(20)->toDateString(),
            'jenis_mutasi' => 'ROTASI',
            'status_penugasan' => 'Definitif',
            'is_applied' => false,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->call('cancelScheduled', $futureJob->id)
            ->assertSee('Berhasil!');

        $this->assertSoftDeleted('job_histories', [
            'id' => $futureJob->id,
            'cancelled_by' => $this->adminUser->id,
        ]);
    }

    public function test_apply_now_activates_only_selected_mutation(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'Pegawai Dipilih', 'jabatan' => 'Asisten Gudang', 'unit_kerja' => 'Kebun A']);
        $empB = Employee::factory()->create(['nama' => 'Pegawai Lain', 'jabatan' => 'Asisten Gudang', 'unit_kerja' => 'Kebun B']);

        $selected = JobHistory::create([
            'employee_nik' => $empA->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
            'tmt_awal' => now()->addDays(10)->toDateString(),
            'jenis_mutasi' => 'ROTASI',
            'status_penugasan' => 'Definitif',
            'is_applied' => false,
        ]);

        // Unrelated mutation scheduled EARLIER than the selected one — must stay untouched
        $unrelated = JobHistory::create([
            'employee_nik' => $empB->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun B',
            'tmt_awal' => now()->addDays(5)->toDateString(),
            'jenis_mutasi' => 'ROTASI',
            'status_penugasan' => 'Definitif',
            'is_applied' => false,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->call('applyNow', $selected->id);

        $this->assertTrue($selected->refresh()->is_applied);
        $this->assertFalse($unrelated->refresh()->is_applied);
        $this->assertEquals('Asisten Gudang', $empB->refresh()->jabatan);
    }

    public function test_can_toggle_kpi_filter_and_view_detail_modal(): void
    {
        $unit = Unit::factory()->create(['nama' => 'Kebun A']);
        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'nama' => 'Rahmat Hidayat',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
        ]);

        $job = JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
            'tmt_awal' => '2026-03-01',
            'jenis_mutasi' => 'PROMOSI',
            'status_penugasan' => 'Definitif',
            'nomor_sk' => 'SK/DIR/TEST/999',
            'catatan' => 'Promosi prestasi gemilang',
            'is_applied' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            // Test KPI Toggle Filter
            ->call('toggleStatusFilter', 'active')
            ->assertSet('statusFilter', 'active')
            ->call('toggleStatusFilter', 'active')
            ->assertSet('statusFilter', '')
            // Test Open Detail Modal
            ->call('showDetail', $job->id)
            ->assertSet('selectedJobId', $job->id)
            ->assertSee('Detail Riwayat & Jejak Audit Mutasi')
            ->assertSee('SK/DIR/TEST/999')
            ->assertSee('Promosi prestasi gemilang')
            // Test Close Detail Modal
            ->call('closeDetailModal')
            ->assertSet('selectedJobId', null)
            ->assertDontSee('Detail Riwayat & Jejak Audit Mutasi');
    }

    public function test_by_status_scope_uses_the_domain_definitions(): void
    {
        $emp = Employee::factory()->create();
        // The factory seeds a data_awal row for the employee; keep it out of the assertions.
        $seeded = JobHistory::where('employee_nik', $emp->nik)->pluck('id')->all();

        $make = fn (bool $applied, string $tmt) => JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Kepala Bagian',
            'unit_kerja' => 'Kantor Direksi',
            'tmt_awal' => $tmt,
            'jenis_mutasi' => 'PROMOSI',
            'status_penugasan' => 'Definitif',
            'is_applied' => $applied,
        ])->id;

        $applied = $make(true, now()->subMonth()->toDateString());
        $finished = $make(true, now()->subYear()->toDateString());
        JobHistory::whereKey($finished)->update(['tmt_akhir' => now()->subMonths(2)->toDateString()]);
        $future = $make(false, now()->addMonth()->toDateString());
        $yesterday = $make(false, now()->subDay()->toDateString());
        $today = $make(false, now()->toDateString());
        $tomorrow = $make(false, now()->addDay()->toDateString());

        $ids = fn (?string $status) => JobHistory::byStatus($status)->whereKeyNot($seeded)->pluck('id')->sort()->values()->all();
        $sorted = fn (array $list) => collect($list)->sort()->values()->all();

        // Applied covers finished rows too; active is applied and still open (tmt_akhir NULL).
        $this->assertSame($sorted([$applied, $finished]), $ids('applied'));
        $this->assertSame([$applied], $ids('active'));
        $this->assertSame($sorted([$future, $yesterday, $today, $tomorrow]), $ids('scheduled'));
        // Due means scheduled with a TMT up to and including today; tomorrow is not due yet.
        $this->assertSame($sorted([$yesterday, $today]), $ids('due'));

        $all = $sorted([$applied, $finished, $future, $yesterday, $today, $tomorrow]);
        $this->assertSame($all, $ids('zzz'));
        $this->assertSame($all, $ids(null));

        // The reader shares the same definition of "due".
        $kpis = app(MutasiHistoryReader::class)->getMutasiHistoryKpis(null);
        $this->assertSame(2, $kpis['due']);
    }

    public function test_can_export_audit_log_excel(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $emp = Employee::factory()->create([
            'nama' => 'Bambang Soediro',
            'jabatan' => 'Kepala Bagian',
            'unit_kerja' => 'Kantor Direksi',
        ]);

        JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Kepala Bagian',
            'unit_kerja' => 'Kantor Direksi',
            'tmt_awal' => '2026-01-01',
            'jenis_mutasi' => 'PROMOSI',
            'status_penugasan' => 'Definitif',
            'nomor_sk' => 'SK/DIR/EXCEL/001',
            'is_applied' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiHistory::class)
            ->call('export');

        Excel::assertDownloaded(
            '/^Audit_Log_Mutasi_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/',
            function (MutasiHistoryExport $export) {
                return $export->query()->where('nomor_sk', 'SK/DIR/EXCEL/001')->exists();
            }
        );
    }

    /** Creates an official (sumber mutasi) history row for the employee. */
    private function official(Employee $employee, array $attributes = []): JobHistory
    {
        return JobHistory::create(array_merge([
            'employee_nik' => $employee->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun A',
            'tmt_awal' => now()->subMonth()->toDateString(),
            'jenis_mutasi' => 'ROTASI',
            'status_penugasan' => 'Definitif',
            'is_applied' => true,
        ], $attributes));
    }

    public function test_history_keeps_filter_urls_and_ignores_old_sort_keys(): void
    {
        $older = $this->official(Employee::factory()->create(['nama' => 'Pegawai Jatuh Tempo Lama']), [
            'jabatan' => 'Asisten Teknik', 'tmt_awal' => now()->subDays(10)->toDateString(), 'is_applied' => false,
        ]);
        $newer = $this->official(Employee::factory()->create(['nama' => 'Pegawai Jatuh Tempo Baru']), [
            'jabatan' => 'Zeta Asisten', 'tmt_awal' => now()->subDays(2)->toDateString(), 'is_applied' => false,
        ]);
        $this->official(Employee::factory()->create(['nama' => 'Pegawai Unit Lain']), [
            'unit_kerja' => 'Kebun B', 'tmt_awal' => now()->subDay()->toDateString(), 'is_applied' => false,
        ]);
        $this->official(Employee::factory()->create(['nama' => 'Pegawai Belum Tempo']), [
            'tmt_awal' => now()->addDays(10)->toDateString(), 'is_applied' => false,
        ]);
        Employee::factory()->create(['nama' => 'Pegawai Data Awal', 'unit_kerja' => 'Kebun A']);

        // The old sortField/sortDirection keys would order by jabatan asc (older first); they are ignored.
        $this->actingAs($this->adminUser)->get(route('admin.mutasi.index', ['tab' => 'history',
            'sumberFilter' => 'resmi',
            'statusFilter' => 'due',
            'unitFilter' => 'Kebun A',
            'sortField' => 'jabatan',
            'sortDirection' => 'asc',
        ]))
            ->assertOk()
            ->assertSeeInOrder(['Pegawai Jatuh Tempo Baru', 'Pegawai Jatuh Tempo Lama'])
            ->assertDontSee('Pegawai Unit Lain')
            ->assertDontSee('Pegawai Belum Tempo')
            ->assertDontSee('Pegawai Data Awal');

        $component = Livewire::withQueryParams([
            'sumberFilter' => 'resmi', 'statusFilter' => 'due', 'unitFilter' => 'Kebun A',
            'sortField' => 'jabatan', 'sortDirection' => 'asc',
        ])->actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSet('sumberFilter', '')
            ->assertSet('statusFilter', 'due')
            ->assertSet('unitFilter', 'Kebun A')
            ->assertSet('sortField', 'tmt_awal')
            ->assertSet('sortDirection', 'desc');
        $this->assertSame([$newer->id, $older->id], $component->instance()->rows->pluck('id')->all());

        $this->actingAs($this->adminUser)->get(route('admin.mutasi.index', ['tab' => 'history', 'sumberFilter' => 'semua', 'unitFilter' => 'Kebun A']))
            ->assertOk()
            ->assertSee('Pegawai Data Awal')
            ->assertSee('Pegawai Belum Tempo');
    }

    public function test_history_ignores_tampered_url_state(): void
    {
        $this->official(Employee::factory()->create(['nama' => 'Pegawai Resmi Aman']));
        Employee::factory()->create(['nama' => 'Pegawai Data Awal Saja']);

        $params = [
            'sumberFilter' => 'zzz',
            'statusFilter' => 'zzz',
            'unitFilter' => 'Unit Yang Tidak Dikenal',
            'mutasiFilter' => 'BUKAN_JENIS',
            'per_page' => 999,
            'sort' => 'password',
            'direction' => 'sideways',
        ];

        $this->actingAs($this->adminUser)->get(route('admin.mutasi.index', ['tab' => 'history', ...$params]))
            ->assertOk()
            ->assertSee('Pegawai Resmi Aman')
            ->assertDontSee('Pegawai Data Awal Saja')
            ->assertDontSee('Filter Aktif:');

        Livewire::withQueryParams($params)->actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSet('sumberFilter', '')
            ->assertSet('statusFilter', '')
            ->assertSet('unitFilter', '')
            ->assertSet('mutasiFilter', '')
            ->assertSet('perPage', 15)
            ->assertSet('sortField', 'tmt_awal')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_search_matches_name_nik_jabatan_unit_literally(): void
    {
        $byName = $this->official(Employee::factory()->create(['nik' => '11110001', 'nama' => 'Sulastri Wardhani']));
        $byNik = $this->official(Employee::factory()->create(['nik' => '98765432', 'nama' => 'Pegawai Kedua']));
        $percent = $this->official(Employee::factory()->create(['nik' => '11110003', 'nama' => 'Pegawai Ketiga']), ['jabatan' => 'Asisten 100%']);
        $plain = $this->official(Employee::factory()->create(['nik' => '11110004', 'nama' => 'Pegawai Keempat']), ['jabatan' => 'Asisten 1000']);
        $unit = $this->official(Employee::factory()->create(['nik' => '11110005', 'nama' => 'Pegawai Kelima']), ['unit_kerja' => 'Kebun Ujung_Batu']);
        $this->official(Employee::factory()->create(['nik' => '11110006', 'nama' => 'Pegawai Keenam']), ['unit_kerja' => 'Kebun UjungXBatu']);

        $component = Livewire::actingAs($this->adminUser)->test(MutasiHistory::class);
        $ids = fn (string $term) => $component->set('search', $term)->instance()->rows->pluck('id')->sort()->values()->all();

        $this->assertSame([$byName->id], $ids('Sulastri'));
        $this->assertSame([$byNik->id], $ids('98765432'));
        $this->assertSame([$percent->id], $ids('100%'));
        $this->assertSame([$plain->id], $ids('Asisten 1000'));
        $this->assertSame([$unit->id], $ids('Ujung_Batu'));
    }

    public function test_karyawan_header_sorts_by_nik(): void
    {
        $high = $this->official(Employee::factory()->create(['nik' => '20000002']), ['tmt_awal' => now()->subYear()->toDateString()]);
        $low = $this->official(Employee::factory()->create(['nik' => '10000001']), ['tmt_awal' => now()->subDay()->toDateString()]);

        $component = Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSeeHtml('wire:click="sortBy(\'employee_nik\')"')
            ->call('sortBy', 'employee_nik')
            ->assertSet('sortField', 'employee_nik')
            ->assertSet('sortDirection', 'asc');
        $this->assertSame([$low->id, $high->id], $component->instance()->rows->pluck('id')->all());

        $component->call('sortBy', 'employee_nik')->assertSet('sortDirection', 'desc');
        $this->assertSame([$high->id, $low->id], $component->instance()->rows->pluck('id')->all());
    }

    public function test_status_and_source_filters_show_as_chips(): void
    {
        $this->official(Employee::factory()->create(), ['is_applied' => false, 'tmt_awal' => now()->subDay()->toDateString()]);

        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertDontSee('Filter Aktif:')
            ->set('statusFilter', 'due')
            ->set('sumberFilter', 'semua')
            ->assertSee('Filter Aktif:')
            ->assertSee('Status: Siap Diaktifkan')
            ->assertSee('Sumber Catatan: Semua Catatan')
            ->assertSeeHtml("wire:click=\"\$set('statusFilter', '')\"")
            ->assertSeeHtml("wire:click=\"\$set('sumberFilter', '')\"")
            // The old inline status pill is gone; the chip row replaces it.
            ->assertDontSee('Lepas filter status');
    }

    public function test_export_matches_the_screen_and_is_audited(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $hit = $this->official(Employee::factory()->create(['nama' => 'Hartono Cocok']), [
            'is_applied' => false, 'tmt_awal' => now()->subDay()->toDateString(),
        ]);
        $hitToo = $this->official(Employee::factory()->create(['nama' => 'Hartono Juga']), [
            'is_applied' => false, 'tmt_awal' => now()->subDays(3)->toDateString(),
        ]);
        // Excluded by unit, by status and by source respectively.
        $this->official(Employee::factory()->create(['nama' => 'Hartono Unit Lain']), [
            'unit_kerja' => 'Kebun B', 'is_applied' => false, 'tmt_awal' => now()->subDay()->toDateString(),
        ]);
        $this->official(Employee::factory()->create(['nama' => 'Hartono Aktif']));
        $this->official(Employee::factory()->create(['nama' => 'Hartono Impor']), [
            'sumber' => JobHistory::SUMBER_IMPOR, 'is_applied' => false, 'tmt_awal' => now()->subDay()->toDateString(),
        ]);
        // Not matched by the search term.
        $this->official(Employee::factory()->create(['nama' => 'Budi Lain']), [
            'is_applied' => false, 'tmt_awal' => now()->subDay()->toDateString(),
        ]);

        $component = Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->set('search', 'Hartono')
            ->set('unitFilter', 'Kebun A')
            ->set('statusFilter', 'due')
            ->set('sumberFilter', JobHistory::SUMBER_MUTASI);

        $screenIds = $component->instance()->rows->pluck('id')->all();
        $this->assertSame([$hit->id, $hitToo->id], $screenIds);

        $component->call('export');

        Excel::assertDownloaded(
            '/^Audit_Log_Mutasi_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/',
            fn (MutasiHistoryExport $export) => $export->query()->pluck('id')->all() === $screenIds
        );

        $audit = MasterAuditLog::where('action', 'data_table.export')->sole();
        $this->assertSame('Riwayat Mutasi', $audit->entity_label);
        $this->assertSame(2, $audit->new_values['jumlah_baris']);
        $this->assertTrue($audit->new_values['pencarian']);
        $this->assertStringNotContainsString('Hartono', json_encode($audit->new_values));
    }

    public function test_export_requires_the_export_permission(): void
    {
        Excel::fake();
        $this->official(Employee::factory()->create());
        // Only admins can mount the screen, so deny the export gate itself to isolate it.
        Gate::define('export-data', fn () => false);

        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->call('export')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Anda tidak memiliki akses untuk tindakan ini.'])
            ->assertSee('Anda tidak memiliki akses untuk tindakan ini.')
            ->assertNoFileDownloaded();

        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.export')->count());
    }

    public function test_non_admin_cannot_mount_the_component(): void
    {
        $staff = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($staff)->test(MutasiHistory::class)->assertForbidden();
    }

    public function test_aktif_menjabat_card_lists_exactly_the_rows_it_counts(): void
    {
        $open = $this->official(Employee::factory()->create(['nama' => 'Aktif Satu']));
        $openToo = $this->official(Employee::factory()->create(['nama' => 'Aktif Dua']), ['tmt_awal' => now()->subMonths(2)->toDateString()]);
        $this->official(Employee::factory()->create(['nama' => 'Sudah Selesai']), [
            'tmt_awal' => now()->subYears(2)->toDateString(), 'tmt_akhir' => now()->subYear()->toDateString(),
        ]);
        $this->official(Employee::factory()->create(['nama' => 'Masih Terjadwal']), [
            'is_applied' => false, 'tmt_awal' => now()->addMonth()->toDateString(),
        ]);

        $component = Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSeeHtml("wire:click=\"toggleStatusFilter('active')\"")
            ->call('toggleStatusFilter', 'active')
            ->assertSet('statusFilter', 'active')
            ->assertSee('Status: Aktif Menjabat')
            ->assertDontSee('Sudah Selesai')
            ->assertDontSee('Masih Terjadwal');

        $rows = $component->instance()->rows;
        $this->assertSame([$open->id, $openToo->id], $rows->pluck('id')->all());
        $this->assertSame($rows->total(), $component->viewData('activeCount'));
    }

    public function test_applied_status_includes_finished_rows_under_an_honest_label(): void
    {
        $open = $this->official(Employee::factory()->create(['nama' => 'Aktif Satu']));
        $finished = $this->official(Employee::factory()->create(['nama' => 'Sudah Selesai']), [
            'tmt_awal' => now()->subYears(2)->toDateString(), 'tmt_akhir' => now()->subYear()->toDateString(),
        ]);
        $this->official(Employee::factory()->create(['nama' => 'Masih Terjadwal']), [
            'is_applied' => false, 'tmt_awal' => now()->addMonth()->toDateString(),
        ]);

        $component = Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->set('statusFilter', 'applied')
            ->assertSee('Status: Sudah Diterapkan')
            ->assertSee('Sudah Selesai')
            ->assertDontSee('Masih Terjadwal');

        $this->assertSame([$open->id, $finished->id], $component->instance()->rows->pluck('id')->all());
    }

    public function test_toggle_status_filter_ignores_unknown_values(): void
    {
        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->call('toggleStatusFilter', 'zzz')
            ->assertSet('statusFilter', '')
            ->call('toggleStatusFilter', 'due')
            ->call('toggleStatusFilter', 'zzz')
            ->assertSet('statusFilter', 'due')
            ->call('toggleStatusFilter', 'due')
            ->assertSet('statusFilter', '');
    }

    public function test_base_query_keeps_the_official_default_for_unknown_sources(): void
    {
        $official = $this->official(Employee::factory()->create());
        $this->official(Employee::factory()->create(), ['sumber' => JobHistory::SUMBER_IMPOR]);
        $officialIds = JobHistory::where('sumber', JobHistory::SUMBER_MUTASI)->pluck('id')->all();
        $this->assertSame([$official->id], $officialIds);

        foreach (['', 'zzz', JobHistory::SUMBER_MUTASI] as $value) {
            $component = new MutasiHistory;
            // Assigned directly: no mount or update hook normalises the value first.
            $component->sumberFilter = $value;

            $this->assertSame($officialIds, invade($component)->baseQuery()->pluck('id')->all(), "sumberFilter '{$value}'");
        }

        $component = new MutasiHistory;
        $component->sumberFilter = 'semua';
        $this->assertSame(JobHistory::count(), invade($component)->baseQuery()->count());
    }

    public function test_export_covers_all_sources_when_asked_and_states_the_scope(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $official = $this->official(Employee::factory()->create(['nama' => 'Promosi Resmi']), ['jenis_mutasi' => 'PROMOSI']);
        $imported = $this->official(Employee::factory()->create(['nama' => 'Promosi Impor']), [
            'jenis_mutasi' => 'PROMOSI', 'sumber' => JobHistory::SUMBER_IMPOR, 'tmt_awal' => now()->subMonths(2)->toDateString(),
        ]);
        // Excluded by the mutation type.
        $this->official(Employee::factory()->create(['nama' => 'Rotasi Resmi']));

        $component = Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->set('sumberFilter', 'semua')
            ->set('mutasiFilter', 'PROMOSI');

        $screenIds = $component->instance()->rows->pluck('id')->all();
        $this->assertSame([$official->id, $imported->id], $screenIds);

        $component->call('export');

        Excel::assertDownloaded(
            '/^Audit_Log_Mutasi_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/',
            fn (MutasiHistoryExport $export) => $export->query()->pluck('id')->all() === $screenIds
                && str_contains($export->properties()['subject'], 'Semua Catatan')
        );

        Excel::fake();
        Excel::matchByRegex();

        $component->set('sumberFilter', JobHistory::SUMBER_IMPOR)->call('export');

        Excel::assertDownloaded(
            '/^Audit_Log_Mutasi_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/',
            fn (MutasiHistoryExport $export) => $export->query()->pluck('id')->all() === [$imported->id]
                && str_contains($export->properties()['subject'], JobHistory::SUMBER_LABELS[JobHistory::SUMBER_IMPOR].' saja')
        );
    }

    public function test_row_actions_show_loading_state(): void
    {
        $this->official(Employee::factory()->create(), ['is_applied' => false, 'tmt_awal' => now()->addDays(5)->toDateString()]);

        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSeeHtml('applyNow, cancelScheduled, applyAllDue, toggleStatusFilter')
            ->assertSeeHtml('wire:loading.attr="aria-busy"')
            ->assertSeeHtml('wire:loading.delay.class.remove="hidden"')
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="applyNow"')
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="cancelScheduled"');
    }

    public function test_empty_state_offers_reset_when_filtered(): void
    {
        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->assertSee('Belum ada mutasi atau alih tugas yang tercatat dalam sistem.')
            ->assertDontSee('Tidak ada data yang sesuai dengan pencarian atau filter.');

        $this->official(Employee::factory()->create());

        Livewire::actingAs($this->adminUser)->test(MutasiHistory::class)
            ->set('search', 'tidak-ada-yang-cocok')
            ->assertDontSee('Belum ada mutasi atau alih tugas yang tercatat dalam sistem.')
            // The empty-state button comes after its message; the toolbar's own Reset button comes before it.
            ->assertSeeInOrder(['Tidak ada data yang sesuai dengan pencarian atau filter.', 'Reset filter']);
    }
}
