<?php

namespace Tests\Feature;

use App\Exports\EmployeesExport;
use App\Livewire\Admin\Employees\EmployeeTable;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\User;
use App\Services\EmployeeExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tests\Fixtures\DataTable\EmployeeTableSmallLimit;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\Employees\EmployeeTable (Employees directory list on the shared data table layer).
 */
class EmployeeTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    /**
     * 25 employees in `Kebun Legacy` whose jabatan sorts with their number (PGW-A01 .. PGW-A25),
     * plus one employee in another unit.
     */
    private function seedLegacyUnit(): void
    {
        foreach (range(1, 25) as $i) {
            $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Employee::factory()->create([
                'nama' => 'PGW-A'.$n,
                'jabatan' => 'Jabatan '.$n,
                'unit_kerja' => 'Kebun Legacy',
            ]);
        }

        Employee::factory()->create(['nama' => 'PGW-LAIN', 'jabatan' => 'Jabatan 99', 'unit_kerja' => 'Kebun Lain']);
    }

    public function test_legacy_query_string_is_applied(): void
    {
        $this->seedLegacyUnit();

        // An old bookmark of the controller-rendered directory opens with the same filter, sort and page.
        $response = $this->get('/admin/employees?unit_kerja=Kebun%20Legacy&sort=jabatan&direction=desc&page=2')->assertOk();
        $content = $response->getContent();

        // jabatan desc: page 1 holds 25..06 (20 rows), page 2 holds 05..01.
        foreach (['PGW-A05', 'PGW-A04', 'PGW-A03', 'PGW-A02', 'PGW-A01'] as $name) {
            $response->assertSee($name);
        }
        $response->assertDontSee('PGW-A06')->assertDontSee('PGW-A25')->assertDontSee('PGW-LAIN');
        $this->assertTrue(strpos($content, 'PGW-A05') < strpos($content, 'PGW-A01'), 'rows follow jabatan desc');

        Livewire::withQueryParams(['unit_kerja' => 'Kebun Legacy', 'sort' => 'jabatan', 'direction' => 'desc', 'page' => 2])
            ->test(EmployeeTable::class)
            ->assertSet('unitKerja', 'Kebun Legacy')
            ->assertSet('sortField', 'jabatan')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('paginators.page', 2)
            ->assertSee('PGW-A05')->assertDontSee('PGW-A06');
    }

    public function test_unknown_filter_values_are_ignored(): void
    {
        Employee::factory()->create(['nama' => 'PEGAWAI SATU', 'unit_kerja' => 'Kebun Satu']);
        Employee::factory()->create(['nama' => 'PEGAWAI DUA', 'unit_kerja' => 'Kebun Dua']);

        $tampered = [
            'status_khusus' => 'drop',
            'status_penugasan' => "x' OR 1=1 --",
            'level' => 'Bogus',
            'rm_level' => 'RM-9',
            'unit_kerja' => 'Kebun Tidak Ada',
            'jabatan' => 'Jabatan Tidak Ada',
            'golongan' => 'ZZ/99',
            'tahun_masuk' => 'abcd',
            'bulan_masuk' => '13',
        ];

        DB::enableQueryLog();

        Livewire::withQueryParams($tampered)
            ->test(EmployeeTable::class)
            ->assertSet('statusKhusus', '')
            ->assertSet('statusPenugasan', '')
            ->assertSet('level', '')
            ->assertSet('rmLevel', '')
            ->assertSet('unitKerja', '')
            ->assertSet('jabatan', '')
            ->assertSet('golongan', '')
            ->assertSet('tahunMasuk', '')
            ->assertSet('bulanMasuk', '')
            ->assertSee('PEGAWAI SATU')
            ->assertSee('PEGAWAI DUA')
            ->set('statusKhusus', 'drop')
            ->assertSet('statusKhusus', '')
            ->assertSee('PEGAWAI SATU');

        $bindings = collect(DB::getQueryLog())->pluck('bindings')->flatten()->map(fn ($b) => (string) $b);

        foreach ($tampered as $value) {
            $this->assertFalse($bindings->contains($value), "tampered value [{$value}] reached SQL");
        }
    }

    public function test_tampered_sort_field_is_ignored(): void
    {
        Employee::factory()->create(['nama' => 'BUDI']);
        Employee::factory()->create(['nama' => 'ANDI']);

        $component = Livewire::withQueryParams(['sort' => 'tanggal_lahir; drop table employees', 'direction' => 'sideways'])
            ->test(EmployeeTable::class)
            ->assertSet('sortField', 'nama')
            ->assertSet('sortDirection', 'asc')
            ->assertSeeInOrder(['ANDI', 'BUDI']);

        // Only the five whitelisted columns are sortable.
        $component->call('sortBy', 'tanggal_lahir')->assertSet('sortField', 'nama');

        foreach (['jabatan', 'rm_level', 'level', 'golongan', 'nama'] as $field) {
            $component->call('sortBy', $field)->assertSet('sortField', $field);
        }
    }

    public function test_level_is_a_chip_not_a_panel_field(): void
    {
        Employee::factory()->create(['nama' => 'PIMPINAN SATU', 'level' => 'Karpim']);
        Employee::factory()->create(['nama' => 'PELAKSANA SATU', 'level' => 'Karpel']);

        $html = Livewire::test(EmployeeTable::class)
            ->set('level', 'Karpim')
            ->assertSee('PIMPINAN SATU')
            ->assertDontSee('PELAKSANA SATU')
            ->html();

        $this->assertStringContainsString('aria-label="Hapus filter Strata"', $html);
        $this->assertStringNotContainsString('wire:model.live="level"', $html);
        $this->assertStringContainsString('Filter Lanjutan', $html);

        // The KPI cards link to ?level=..., which drives the component on page load.
        $this->get(route('admin.employees.index', ['level' => 'Karpel']))
            ->assertOk()
            ->assertSee('PELAKSANA SATU')
            ->assertDontSee('PIMPINAN SATU');
    }

    public function test_header_title_and_count(): void
    {
        Employee::factory()->count(3)->create();

        $html = Livewire::test(EmployeeTable::class)->html();
        preg_match('/<p role="status"[^>]*>(.*?)<\/p>/s', $html, $region);

        $this->assertStringContainsString('Direktori Pegawai', $html);
        $this->assertStringStartsWith('3 Pegawai', trim(preg_replace('/\s+/', ' ', strip_tags($region[1]))));
        // The selection count is announced by the page, not by a second count region inside the table.
        $this->assertSame(1, substr_count($html, 'role="status"'), 'one count live region');
    }

    public function test_zero_results_show_reset(): void
    {
        Employee::factory()->create(['nama' => 'PEGAWAI ADA']);

        Livewire::test(EmployeeTable::class)
            ->set('search', 'tidak-ada-yang-cocok')
            ->assertDontSee('PEGAWAI ADA')
            ->assertSee('Tidak ada pegawai yang sesuai dengan pencarian atau filter.')
            ->assertSee('wire:click="resetFilters"', false)
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSee('PEGAWAI ADA');
    }

    public function test_empty_directory_offers_first_employee_link(): void
    {
        Livewire::test(EmployeeTable::class)
            ->assertSee('Belum ada data karyawan terdaftar di sistem.')
            ->assertSee('Tambah Karyawan Pertama')
            ->assertSee(route('admin.employees.create'), false);
    }

    public function test_pages_hold_twenty_rows(): void
    {
        Employee::factory()->count(21)->create();

        $component = Livewire::test(EmployeeTable::class)->assertSet('perPage', 20);

        $this->assertCount(20, $component->instance()->rows->items());
        $this->assertSame(21, $component->instance()->rows->total());
    }

    public function test_search_uses_the_omnisearch_scope(): void
    {
        Employee::factory()->create(['nik' => '99887766', 'nama' => 'BAMBANG HERMANTO', 'jabatan' => 'Kepala Tata Usaha', 'unit_kerja' => 'PKS Rimba Belian']);
        Employee::factory()->create(['nama' => 'CITRA LESTARI', 'jabatan' => 'Mandor Panen', 'unit_kerja' => 'Kebun Lain']);

        $component = Livewire::test(EmployeeTable::class);

        foreach (['99887766', 'HERMANTO', 'Tata Usaha', 'Rimba Belian'] as $term) {
            $component->set('search', $term)->assertSee('BAMBANG')->assertDontSee('CITRA LESTARI');
        }

        // Matches are highlighted on Livewire updates too, not only on the first page load.
        $component->set('search', 'HERMANTO')->assertSee('<mark', false);
    }

    public function test_filters_apply_like_the_old_controller(): void
    {
        $sanksi = Employee::factory()->create(['nama' => 'PEGAWAI SANKSI', 'tanggal_berakhir_sanksi' => now()->addDays(10)->toDateString(), 'tmt_bekerja' => '2015-03-10']);
        $rangkap = Employee::factory()->create(['nama' => 'PEGAWAI RANGKAP', 'tmt_bekerja' => '2018-07-01']);
        $rangkap->jobHistories()->create([
            'jabatan' => 'Plt. Manajer',
            'unit_kerja' => 'Kantor Regional',
            'status_penugasan' => 'Plt',
            'tmt_awal' => now()->subMonth(),
            'status_riwayat' => 'Aktif',
        ]);

        Livewire::test(EmployeeTable::class)
            ->set('statusKhusus', 'sanksi')->assertSee('PEGAWAI SANKSI')->assertDontSee('PEGAWAI RANGKAP')
            ->set('statusKhusus', 'rangkap')->assertSee('PEGAWAI RANGKAP')->assertDontSee('PEGAWAI SANKSI')
            ->set('statusKhusus', '')
            ->set('statusPenugasan', 'sementara')->assertSee('PEGAWAI RANGKAP')->assertDontSee('PEGAWAI SANKSI')
            ->set('statusPenugasan', 'definitif')->assertSee('PEGAWAI SANKSI')->assertDontSee('PEGAWAI RANGKAP')
            ->set('statusPenugasan', 'Plt')->assertSee('PEGAWAI RANGKAP')->assertDontSee('PEGAWAI SANKSI')
            ->set('statusPenugasan', '')
            ->set('tahunMasuk', '2015')->assertSee('PEGAWAI SANKSI')->assertDontSee('PEGAWAI RANGKAP')
            ->set('tahunMasuk', '')
            ->set('bulanMasuk', '07')->assertSee('PEGAWAI RANGKAP')->assertDontSee('PEGAWAI SANKSI');

        // Unpadded month values from older links keep working.
        Livewire::withQueryParams(['bulan_masuk' => '3'])
            ->test(EmployeeTable::class)
            ->assertSet('bulanMasuk', '03')
            ->assertSee('PEGAWAI SANKSI')->assertDontSee('PEGAWAI RANGKAP');
    }

    public function test_row_actions_and_engine_selection_controls_are_rendered(): void
    {
        $employee = Employee::factory()->create(['nik' => '12345678', 'nama' => 'PEGAWAI AKSI']);

        $html = Livewire::test(EmployeeTable::class)->html();

        $this->assertStringContainsString(route('admin.employees.show', $employee->nik), $html);
        $this->assertStringContainsString(route('admin.employees.edit', $employee->nik), $html);
        $this->assertStringContainsString(route('admin.mutasi.create', ['nik' => $employee->nik]), $html);
        $this->assertStringContainsString(route('admin.employees.destroy', $employee->nik), $html);

        // Selection and export run through the shared engine, not through page-level forms.
        $this->assertStringContainsString('aria-label="Pilih PEGAWAI AKSI"', $html);
        $this->assertStringContainsString('wire:model.live="selected" value="12345678"', $html);
        $this->assertStringContainsString('aria-label="Pilih semua di halaman ini"', $html);
        $this->assertStringContainsString('wire:click="toggleSelectPage"', $html);
        $this->assertStringContainsString('wire:click="export"', $html);
        $this->assertStringNotContainsString(route('admin.employees.export'), $html);
        $this->assertStringNotContainsString(route('admin.employees.bulkDestroy'), $html);
        $this->assertStringNotContainsString('selectedNiks', $html);

        // The page no longer carries the Alpine selection state, the floating bar or the bulk-delete form.
        $page = $this->get(route('admin.employees.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('selectedNiks', $page);
        $this->assertStringNotContainsString(route('admin.employees.bulkDestroy'), $page);
    }

    /**
     * Three employees with stored photos: two in `Kebun Hapus`, one in `Kebun Aman`.
     *
     * @return array{0: Employee, 1: Employee, 2: Employee}
     */
    private function seedBulkTargets(): array
    {
        Storage::fake('public');

        foreach (['hapus-1', 'hapus-2', 'aman'] as $file) {
            Storage::disk('public')->put("photos/{$file}.jpg", 'foto');
        }

        return [
            Employee::factory()->create(['nik' => '11110001', 'nama' => 'PEGAWAI HAPUS SATU', 'unit_kerja' => 'Kebun Hapus', 'foto' => 'photos/hapus-1.jpg']),
            Employee::factory()->create(['nik' => '11110002', 'nama' => 'PEGAWAI HAPUS DUA', 'unit_kerja' => 'Kebun Hapus', 'foto' => 'photos/hapus-2.jpg']),
            Employee::factory()->create(['nik' => '11110003', 'nama' => 'PEGAWAI AMAN', 'unit_kerja' => 'Kebun Aman', 'foto' => 'photos/aman.jpg']),
        ];
    }

    public function test_bulk_delete_is_limited_to_matching_selection(): void
    {
        [$first, $second, $outside] = $this->seedBulkTargets();

        $component = Livewire::test(EmployeeTable::class)
            ->set('unitKerja', 'Kebun Hapus')
            ->call('toggleSelectPage')
            ->assertSet('selected', ['11110002', '11110001'])
            ->call('selectAllMatchingRows')
            ->assertSet('selectAllMatching', true);

        // The engine's bulk bar offers the delete with a confirmation naming the count.
        $html = $component->html();
        $this->assertStringContainsString('wire:click="runBulkAction(\'destroy\')"', $html);
        $this->assertStringContainsString('Hapus 2 data pegawai terpilih beserta foto, riwayat penempatan, evaluasi, dan pelatihannya? Tindakan ini tidak dapat dibatalkan.', $html);

        $component->call('runBulkAction', 'destroy')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '2 data pegawai terpilih berhasil dihapus.'])
            ->assertSet('selected', [])
            ->assertSet('selectAllMatching', false);

        $this->assertDatabaseMissing('employees', ['nik' => $first->nik]);
        $this->assertDatabaseMissing('employees', ['nik' => $second->nik]);
        $this->assertDatabaseHas('employees', ['nik' => $outside->nik]);

        Storage::disk('public')->assertMissing('photos/hapus-1.jpg');
        Storage::disk('public')->assertMissing('photos/hapus-2.jpg');
        Storage::disk('public')->assertExists('photos/aman.jpg');

        $log = MasterAuditLog::where('action', 'data_table.bulk')->sole();
        $this->assertSame('destroy', $log->new_values['aksi']);
        $this->assertSame(2, $log->new_values['jumlah_baris']);
        $this->assertSame(['unitKerja' => 'Kebun Hapus'], $log->new_values['filter']);
    }

    public function test_tampered_selection_cannot_widen_bulk_delete(): void
    {
        [$first, $second, $outside] = $this->seedBulkTargets();

        // A NIK outside the current filter result (and one that does not exist) is sent with the selection.
        Livewire::test(EmployeeTable::class)
            ->set('unitKerja', 'Kebun Hapus')
            ->set('selected', [$first->nik, $outside->nik, '99999999'])
            ->call('runBulkAction', 'destroy')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '1 data pegawai terpilih berhasil dihapus.']);

        $this->assertDatabaseMissing('employees', ['nik' => $first->nik]);
        $this->assertDatabaseHas('employees', ['nik' => $second->nik]);
        $this->assertDatabaseHas('employees', ['nik' => $outside->nik]);
        Storage::disk('public')->assertExists('photos/aman.jpg');
        Storage::disk('public')->assertExists('photos/hapus-2.jpg');
    }

    public function test_bulk_delete_requires_permission(): void
    {
        [$first, $second, $outside] = $this->seedBulkTargets();

        Gate::define('delete-employee', fn () => false);

        Livewire::test(EmployeeTable::class)
            ->set('selected', [$first->nik, $second->nik])
            ->call('runBulkAction', 'destroy')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Anda tidak memiliki akses untuk tindakan ini.']);

        $this->assertSame(3, Employee::count());
        Storage::disk('public')->assertExists('photos/hapus-1.jpg');
        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.bulk')->count());

        // The handler is not client-callable, and non-admins never reach the component.
        $this->assertFalse((new \ReflectionMethod(EmployeeTable::class, 'bulkDestroy'))->isPublic());

        $this->actingAs(User::factory()->create(['role' => 'user']));
        Livewire::test(EmployeeTable::class)->assertForbidden();
        $this->assertSame(3, Employee::count());
    }

    public function test_bulk_delete_confirmation_names_count_and_consequences(): void
    {
        $this->seedBulkTargets();

        $action = collect((fn () => $this->bulkActions())->call(new EmployeeTable))->firstWhere('name', 'destroy');
        $this->assertStringContainsString(':count', $action->confirmText());
        $this->assertStringContainsString('riwayat penempatan', $action->confirmText());

        $html = Livewire::test(EmployeeTable::class)
            ->set('unitKerja', 'Kebun Hapus')
            ->call('toggleSelectPage')
            ->html();

        $this->assertStringContainsString('Hapus 2 data pegawai terpilih beserta foto, riwayat penempatan, evaluasi, dan pelatihannya? Tindakan ini tidak dapat dibatalkan.', $html);
    }

    public function test_bulk_delete_over_the_limit_deletes_nothing(): void
    {
        $this->seedBulkTargets();

        // Limit 2 (fixture): three matching rows selected through "select all matching".
        Livewire::test(EmployeeTableSmallLimit::class)
            ->call('selectAllMatchingRows')
            ->call('runBulkAction', 'destroy')
            ->assertSet('tableNotice', ['type' => 'warning', 'message' => 'Hapus massal dibatasi 2 data per proses. Persempit filter atau pilih lebih sedikit data.'])
            ->assertSet('selectAllMatching', true);

        $this->assertSame(3, Employee::count());
        Storage::disk('public')->assertExists('photos/hapus-1.jpg');
        Storage::disk('public')->assertExists('photos/aman.jpg');
        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.bulk')->count());

        // The production limit is 100 rows per run.
        $this->assertSame(100, EmployeeTable::BULK_DELETE_LIMIT);
    }

    public function test_bulk_delete_at_the_limit_deletes(): void
    {
        [$first, $second, $outside] = $this->seedBulkTargets();

        Livewire::test(EmployeeTableSmallLimit::class)
            ->set('unitKerja', 'Kebun Hapus')
            ->call('selectAllMatchingRows')
            ->call('runBulkAction', 'destroy')
            ->assertSet('tableNotice', ['type' => 'success', 'message' => '2 data pegawai terpilih berhasil dihapus.']);

        $this->assertDatabaseMissing('employees', ['nik' => $first->nik]);
        $this->assertDatabaseMissing('employees', ['nik' => $second->nik]);
        $this->assertDatabaseHas('employees', ['nik' => $outside->nik]);
    }

    public function test_bulk_delete_rolls_back_on_failure(): void
    {
        [$first, $second] = $this->seedBulkTargets();

        // The second deletion fails after the first one already ran.
        $deletions = 0;
        Employee::deleting(function () use (&$deletions) {
            if (++$deletions === 2) {
                throw new RuntimeException('Simulated deletion failure.');
            }
        });

        Livewire::test(EmployeeTable::class)
            ->set('unitKerja', 'Kebun Hapus')
            ->call('selectAllMatchingRows')
            ->call('runBulkAction', 'destroy')
            ->assertSet('tableNotice', ['type' => 'error', 'message' => 'Tindakan gagal, tidak ada data yang berubah.']);

        $this->assertSame(2, $deletions);
        $this->assertDatabaseHas('employees', ['nik' => $first->nik]);
        $this->assertDatabaseHas('employees', ['nik' => $second->nik]);
        Storage::disk('public')->assertExists('photos/hapus-1.jpg');
        Storage::disk('public')->assertExists('photos/hapus-2.jpg');
        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.bulk')->count());
    }

    public function test_export_uses_current_filters_and_is_audited(): void
    {
        Excel::fake();
        $this->travelTo('2026-11-08 09:30:00');

        $budi = Employee::factory()->create(['nik' => '22220001', 'nama' => 'BUDI EKSPOR', 'jabatan' => 'Asisten A', 'unit_kerja' => 'Kebun Ekspor']);
        $andi = Employee::factory()->create(['nik' => '22220002', 'nama' => 'ANDI EKSPOR', 'jabatan' => 'Asisten B', 'unit_kerja' => 'Kebun Ekspor']);
        Employee::factory()->create(['nik' => '22220003', 'nama' => 'CITRA EKSPOR', 'unit_kerja' => 'Kebun Lain']);
        Employee::factory()->create(['nik' => '22220004', 'nama' => 'DEWI LAINNYA', 'jabatan' => 'Mandor Panen', 'unit_kerja' => 'Kebun Ekspor']);

        $component = Livewire::test(EmployeeTable::class)
            ->set('unitKerja', 'Kebun Ekspor')
            ->set('search', 'Asisten')
            ->call('sortBy', 'jabatan')
            ->call('sortBy', 'jabatan')
            ->call('export');

        // Old route file name pattern and the business workbook (26 columns, document metadata naming the user).
        $legacy = new EmployeesExport(['unit_kerja' => 'Kebun Ekspor', 'search' => 'Asisten'], $this->admin);
        Excel::assertDownloaded('data_karyawan_ptpn4_reg5_20261108_093000.xlsx', function (EmployeesExport $export) use ($legacy, $andi, $budi) {
            $niks = $export->query()->pluck('nik')->all();

            return $niks === [$andi->nik, $budi->nik] // jabatan desc
                && $export->query()->count() === $legacy->query()->count()
                && $export->headings() === $legacy->headings()
                && count($export->headings()) === 26
                && $export->properties() === $legacy->properties()
                && str_contains($export->properties()['creator'], $this->admin->name);
        });

        $log = MasterAuditLog::where('action', 'data_table.export')->sole();
        $this->assertSame(2, $log->new_values['jumlah_baris']);
        $this->assertSame(['unitKerja' => 'Kebun Ekspor'], $log->new_values['filter']);
        $this->assertTrue($log->new_values['pencarian']);
        $this->assertStringNotContainsString('Asisten', json_encode($log->new_values), 'the raw search term is not audited');

        // A selection narrows the export to the selected rows within the filter result.
        $this->travelTo('2026-11-08 09:31:00');
        $component->set('selected', [$budi->nik, '22220003'])->call('export');
        Excel::assertDownloaded('data_karyawan_ptpn4_reg5_20261108_093100.xlsx',
            fn (EmployeesExport $export) => $export->query()->pluck('nik')->all() === [$budi->nik]);
        $this->assertSame(1, MasterAuditLog::where('action', 'data_table.export')->latest('id')->first()->new_values['jumlah_baris']);
    }

    public function test_export_over_limit_shows_warning(): void
    {
        Excel::fake();
        $this->travelTo('2026-11-08 09:30:00');

        Employee::factory()->create(['nama' => 'PEGAWAI SATU', 'unit_kerja' => 'Kebun Batas']);
        Employee::factory()->create(['nama' => 'PEGAWAI DUA', 'unit_kerja' => 'Kebun Batas']);
        Employee::factory()->create(['nama' => 'PEGAWAI TIGA', 'unit_kerja' => 'Kebun Lain']);

        // Same component with an export row limit of 2.
        $component = Livewire::test(EmployeeTableSmallLimit::class)
            ->call('export')
            ->assertSet('tableNotice', ['type' => 'warning', 'message' => 'Data terlalu besar untuk diekspor. Persempit filter terlebih dahulu.'])
            ->assertNoFileDownloaded();

        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.export')->count());

        // Narrowing the filter brings the export within the limit.
        $component->set('unitKerja', 'Kebun Batas')->call('export');
        Excel::assertDownloaded('data_karyawan_ptpn4_reg5_20261108_093000.xlsx', fn (EmployeesExport $export) => $export->query()->count() === 2);
        $this->assertSame(1, MasterAuditLog::where('action', 'data_table.export')->count());
    }

    /** KPI metrics as the controller passes them to the component. */
    private function kpi(): array
    {
        return [
            'total' => Employee::count(),
            'units' => 1,
            'karpim' => Employee::where('level', Employee::LEVEL_KARPIM)->count(),
            'karpel' => Employee::where('level', Employee::LEVEL_KARPEL)->count(),
            'rangkap' => 0,
            'sanksi' => 0,
            'overflow' => 0,
            'expired' => 0,
        ];
    }

    public function test_kpi_cards_drive_the_live_table_state(): void
    {
        Employee::factory()->create(['nama' => 'PIMPINAN KPI', 'level' => Employee::LEVEL_KARPIM]);
        Employee::factory()->create(['nama' => 'PELAKSANA KPI', 'level' => Employee::LEVEL_KARPEL]);

        $component = Livewire::test(EmployeeTable::class, ['kpi' => $this->kpi()])
            ->set('search', 'KPI');

        // Cards act on the component (no page reload) and keep a plain link fallback carrying the live state.
        $html = $component->html();
        $this->assertStringContainsString('wire:click.prevent="$set(\'level\', \''.Employee::LEVEL_KARPEL.'\')"', $html);
        $this->assertStringContainsString('wire:click.prevent="$set(\'level\', \''.Employee::LEVEL_KARPIM.'\')"', $html);
        $this->assertStringNotContainsString('level=Pelaksana', $html);
        $this->assertStringContainsString('search=KPI', $html, 'card links carry the search typed in the table');

        // Clicking the Karyawan Pelaksana card filters on Karpel: list, highlight and export agree.
        $component->set('level', Employee::LEVEL_KARPEL)
            ->assertSee('PELAKSANA KPI')->assertDontSee('PIMPINAN KPI')
            ->assertSet('search', 'KPI');
        $html = $component->html();
        $this->assertSame(1, substr_count($html, 'aria-current="true"'), 'one active card');
        $activeCard = substr($html, strpos($html, 'aria-current="true"'));
        $activeCard = substr($activeCard, 0, strpos($activeCard, '</a>'));
        $this->assertStringContainsString('Karyawan Pelaksana', $activeCard);
        $this->assertStringContainsString('wire:click.prevent="$set(\'level\', \'\')"', $html, 'the active card toggles the filter off');
        Excel::fake();
        $this->freezeTime();
        $component->call('export');
        Excel::assertDownloaded(
            app(EmployeeExportService::class)->generateFilename(),
            fn (EmployeesExport $export) => $export->query()->pluck('nama')->all() === ['PELAKSANA KPI']
        );

        // "Total Pegawai" clears the card-driven filters but keeps the search.
        $component->call('clearKpiFilters')
            ->assertSet('level', '')->assertSet('statusKhusus', '')->assertSet('statusPenugasan', '')
            ->assertSet('search', 'KPI')
            ->assertSee('PIMPINAN KPI')->assertSee('PELAKSANA KPI');
    }

    public function test_governance_strip_follows_chip_changes(): void
    {
        Employee::factory()->create(['nama' => 'PEGAWAI STRIP']);

        $component = Livewire::test(EmployeeTable::class, ['kpi' => $this->kpi()])
            ->set('statusKhusus', 'sanksi');

        $html = $component->html();
        $this->assertStringContainsString('aria-pressed="true"', $html);
        $this->assertStringContainsString('Reset Status Khusus', $html);
        $this->assertStringContainsString('wire:click.prevent="$set(\'statusKhusus\', \'\')"', $html);

        // Removing the Tata Kelola chip inside the table un-highlights the strip and hides its reset link.
        $html = $component->set('statusKhusus', '')->html();
        $this->assertStringNotContainsString('aria-pressed="true"', $html);
        $this->assertStringNotContainsString('Reset Status Khusus', $html);
        $this->assertStringContainsString('wire:click.prevent="$set(\'statusKhusus\', \'rangkap\')"', $html);
        $this->assertStringNotContainsString('status_khusus=sanksi', $html, 'a removed filter does not come back through a card link');

        // Values sent by a tampered card click are validated like any filter value.
        $component->call('$set', 'statusKhusus', 'drop')->assertSet('statusKhusus', '')
            ->call('$set', 'level', 'Pelaksana')->assertSet('level', '');
    }

    public function test_kpi_card_counts_match_the_filtered_rows(): void
    {
        Employee::factory()->count(2)->create(['level' => Employee::LEVEL_KARPIM]);
        Employee::factory()->count(3)->create(['level' => Employee::LEVEL_KARPEL]);

        $page = $this->get(route('admin.employees.index'))->assertOk();
        $page->assertViewHas('kpi', fn (array $kpi) => $kpi['karpim'] === 2 && $kpi['karpel'] === 3 && $kpi['total'] === 5);

        $this->assertSame(2, Livewire::withQueryParams(['level' => Employee::LEVEL_KARPIM])->test(EmployeeTable::class)->instance()->rows->total());
        $this->assertSame(3, Livewire::withQueryParams(['level' => Employee::LEVEL_KARPEL])->test(EmployeeTable::class)->instance()->rows->total());
    }

    public function test_non_admin_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        Livewire::test(EmployeeTable::class)->assertForbidden();
    }
}
