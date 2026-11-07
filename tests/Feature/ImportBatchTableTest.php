<?php

namespace Tests\Feature;

use App\Livewire\Admin\Employees\ImportBatchTable;
use App\Models\EmployeeImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\Employees\ImportBatchTable (import history on the shared data table layer).
 */
class ImportBatchTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    private function log(string $file, string $status, string $submitter = 'Admin Pengaju'): EmployeeImportLog
    {
        return EmployeeImportLog::create([
            'file_name' => $file,
            'user_name' => $submitter,
            'status' => $status,
            'total_rows' => 3,
        ]);
    }

    private function seedLogs(): void
    {
        $this->log('menunggu.xlsx', EmployeeImportLog::STATUS_PENDING);
        $this->log('diproses.xlsx', EmployeeImportLog::STATUS_APPROVED);
        $this->log('tersimpan.xlsx', EmployeeImportLog::STATUS_COMPLETED);
        $this->log('ditolak.xlsx', EmployeeImportLog::STATUS_REJECTED);
        $this->log('dibatalkan.xlsx', EmployeeImportLog::STATUS_ROLLED_BACK);
    }

    public function test_status_tabs_filter_and_show_counts(): void
    {
        $this->seedLogs();

        $component = Livewire::test(ImportBatchTable::class);
        $html = $component->html();

        foreach (['all' => 5, 'pending' => 2, 'saved' => 1, 'closed' => 2] as $key => $count) {
            $this->assertMatchesRegularExpression('/data-tab="'.$key.'"[^>]*>.*?'.$count.'\s*<\/span>/s', $html);
        }

        $component->assertSee('menunggu.xlsx')->assertSee('tersimpan.xlsx')->assertSee('aria-current="page"', false);

        $component->call('$set', 'status', 'pending')
            ->assertSee('menunggu.xlsx')->assertSee('diproses.xlsx')
            ->assertDontSee('tersimpan.xlsx')->assertDontSee('ditolak.xlsx');

        $component->call('$set', 'status', 'saved')
            ->assertSee('tersimpan.xlsx')->assertDontSee('menunggu.xlsx');

        $component->call('$set', 'status', 'closed')
            ->assertSee('ditolak.xlsx')->assertSee('dibatalkan.xlsx')->assertDontSee('tersimpan.xlsx');
    }

    public function test_status_is_a_chip_not_a_panel_field(): void
    {
        $this->seedLogs();

        $html = Livewire::test(ImportBatchTable::class)->set('status', 'saved')->html();

        $this->assertStringContainsString('aria-label="Hapus filter Status"', $html);
        $this->assertStringNotContainsString('Filter Lanjutan', $html);
    }

    public function test_legacy_q_parameter_redirects_to_search(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employees.import.batches.index', ['q' => 'laporan', 'status' => 'saved']))
            ->assertRedirect(route('admin.employees.import.batches.index', ['status' => 'saved', 'search' => 'laporan']));

        $this->actingAs($this->admin)
            ->get(route('admin.employees.import.batches.index', ['q' => 'laporan']))
            ->assertRedirect(route('admin.employees.import.batches.index', ['search' => 'laporan']));
    }

    public function test_unknown_status_is_ignored(): void
    {
        $this->seedLogs();

        Livewire::withQueryParams(['status' => 'bogus'])
            ->test(ImportBatchTable::class)
            ->assertSet('status', '')
            ->assertSee('menunggu.xlsx')->assertSee('tersimpan.xlsx')->assertSee('ditolak.xlsx');
    }

    public function test_header_title_and_count(): void
    {
        $this->seedLogs();

        $html = Livewire::test(ImportBatchTable::class)->html();
        preg_match('/<p role="status"[^>]*>(.*?)<\/p>/s', $html, $region);

        $this->assertStringContainsString('Riwayat &amp; Persetujuan', $html);
        $this->assertStringStartsWith('5 Impor', trim(preg_replace('/\s+/', ' ', strip_tags($region[1]))));
        $this->assertStringNotContainsString('Ekspor Excel', $html);
    }

    public function test_search_file_or_submitter(): void
    {
        $this->log('laporan-januari.xlsx', EmployeeImportLog::STATUS_COMPLETED, 'Budi Santoso');
        $this->log('rekap-februari.xlsx', EmployeeImportLog::STATUS_COMPLETED, 'Citra Dewi');

        Livewire::test(ImportBatchTable::class)
            ->set('search', 'januari')
            ->assertSee('laporan-januari.xlsx')->assertDontSee('rekap-februari.xlsx')
            ->set('search', 'Citra')
            ->assertSee('rekap-februari.xlsx')->assertDontSee('laporan-januari.xlsx');
    }

    public function test_governance_notes_stay_on_the_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employees.import.batches.index'))
            ->assertOk()->assertSee('Persetujuan dua pihak (empat mata)')->assertSee('dapat dibatalkan dalam');
    }
}
