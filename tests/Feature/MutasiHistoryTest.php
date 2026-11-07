<?php

namespace Tests\Feature;

use App\Exports\MutasiHistoryExport;
use App\Livewire\Admin\MutasiHistory;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

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
            ->assertSet('sumberFilter', 'resmi');

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
            ->call('toggleStatusFilter', 'applied')
            ->assertSet('statusFilter', 'applied')
            ->call('toggleStatusFilter', 'applied')
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
            ->call('exportAuditLog');

        Excel::assertDownloaded(
            '/^Audit_Log_Mutasi_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/',
            function (MutasiHistoryExport $export) {
                return $export->query()->where('nomor_sk', 'SK/DIR/EXCEL/001')->exists();
            }
        );
    }
}
