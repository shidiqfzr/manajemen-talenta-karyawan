<?php

namespace Tests\Feature;

use App\Livewire\Tables\JobHistoryTable;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

use function Livewire\invade;

/**
 * SUT: App\Livewire\Tables\JobHistoryTable (employee profile, job history).
 */
class JobHistoryTableTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    private Employee $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->employee = Employee::factory()->create();
        $this->other = Employee::factory()->create();

        // The employee observer seeds an initial history row; start from a known set instead.
        JobHistory::query()->forceDelete();

        // Seven positions: "Jabatan 01" is the oldest, "Jabatan 07" the newest.
        for ($i = 1; $i <= 7; $i++) {
            $this->makeHistory($this->employee, [
                'jabatan' => sprintf('Jabatan %02d', $i),
                'tmt_awal' => sprintf('2020-%02d-01', $i),
            ]);
        }

        $this->makeHistory($this->other, ['jabatan' => 'Jabatan Pegawai Lain', 'tmt_awal' => '2021-01-01']);
    }

    /** @param array<string, mixed> $attributes */
    private function makeHistory(Employee $employee, array $attributes): JobHistory
    {
        return JobHistory::create($attributes + [
            'employee_nik' => $employee->nik,
            'unit_kerja' => 'Kebun Umum',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jenis_mutasi' => 'ROTASI',
            'nomor_sk' => 'SK/UMUM/001',
            'catatan' => 'Catatan rahasia',
        ]);
    }

    private function table()
    {
        return Livewire::test(JobHistoryTable::class, ['employeeId' => $this->employee->nik]);
    }

    public function test_summary_shows_the_row_count(): void
    {
        $total = $this->table()->instance()->rows->total();
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($this->table()->html())));
        $this->assertStringContainsString($total.' riwayat jabatan', $text);
        $this->assertStringNotContainsString('sesuai filter', $text);

        $filtered = trim(preg_replace('/\s+/', ' ', strip_tags($this->table()->set('search', 'Jabatan 01')->html())));
        $this->assertStringContainsString('1 dari '.$total.' riwayat jabatan sesuai filter', $filtered);
    }

    public function test_lists_only_this_employees_records(): void
    {
        $component = $this->table()->set('perPage', 10)
            ->assertSee('Jabatan 01')
            ->assertSee('Jabatan 07')
            ->assertDontSee('Jabatan Pegawai Lain');

        $this->assertSame(7, $component->instance()->rows->total());
    }

    public function test_default_sort_and_five_per_page(): void
    {
        $component = $this->table()
            ->assertSet('sortField', 'tmt_awal')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', 5)
            ->assertSeeInOrder(['Jabatan 07', 'Jabatan 06', 'Jabatan 05', 'Jabatan 04', 'Jabatan 03'])
            ->assertDontSee('Jabatan 02')
            ->assertSeeHtml('aria-label="Halaman riwayat jabatan"')
            // Sortable headers: Periode, Jabatan, Unit Kerja only.
            ->assertSeeHtml("sortBy('tmt_awal')")
            ->assertSeeHtml("sortBy('jabatan')")
            ->assertSeeHtml("sortBy('unit_kerja')")
            ->assertDontSeeHtml("sortBy('jenis_mutasi')")
            ->assertDontSeeHtml("sortBy('nomor_sk')");

        $this->assertCount(5, $component->instance()->rows->items());
        $this->assertSame([5, 10, 25, 50], invade($component->instance())->perPageOptions());
    }

    public function test_search_matches_declared_columns(): void
    {
        JobHistory::where('jabatan', 'Jabatan 02')->update(['unit_kerja' => 'Kebun Ngabang']);
        JobHistory::where('jabatan', 'Jabatan 03')->update(['jenis_mutasi' => 'PROMOSI']);
        JobHistory::where('jabatan', 'Jabatan 04')->update(['nomor_sk' => 'SK/DIR/777']);
        JobHistory::where('jabatan', 'Jabatan 05')->update(['catatan' => 'Kata Tersembunyi']);

        $component = $this->table();

        $cases = ['Jabatan 01' => 'Jabatan 01', 'Ngabang' => 'Jabatan 02', 'PROMOSI' => 'Jabatan 03', 'DIR/777' => 'Jabatan 04'];

        foreach ($cases as $term => $expected) {
            $component->set('search', $term);
            $titles = collect($component->instance()->rows->items())->pluck('jabatan')->all();
            $this->assertSame([$expected], $titles, "Search for [{$term}]");
        }

        $component->set('search', 'Kata Tersembunyi');
        $this->assertSame(0, $component->instance()->rows->total());
        $component->set('search', 'Pegawai Lain');
        $this->assertSame(0, $component->instance()->rows->total());
    }

    public function test_tampered_sort_field_is_ignored(): void
    {
        $this->table()
            ->set('sortField', 'employee_nik; drop')
            ->assertOk()
            ->assertSet('sortField', 'tmt_awal')
            ->assertSeeInOrder(['Jabatan 07', 'Jabatan 06', 'Jabatan 05']);
    }

    public function test_page_name_is_isolated(): void
    {
        $component = $this->table()
            ->call('gotoPage', 2, 'riwayat_jabatan_page')
            ->assertSet('paginators.riwayat_jabatan_page', 2)
            ->assertSee('Jabatan 02')
            ->assertDontSee('Jabatan 07')
            ->assertSeeHtml("gotoPage(1, 'riwayat_jabatan_page')")
            ->assertSeeHtml('id="per-page-riwayat_jabatan_page"');

        $this->assertSame('riwayat_jabatan_page', $component->instance()->rows->getPageName());
        $this->assertSame(2, $component->instance()->rows->currentPage());
    }

    public function test_row_action_still_works(): void
    {
        $history = JobHistory::where('jabatan', 'Jabatan 07')->firstOrFail();
        $foreign = JobHistory::where('employee_nik', $this->other->nik)->firstOrFail();

        $this->table()
            ->call('deleteRow', $history->id)
            ->assertDontSee('Jabatan 07');

        $this->assertNull(JobHistory::find($history->id));

        // Another employee's history cannot be deleted through this table.
        $this->table()->call('deleteRow', $foreign->id)->assertStatus(404);
        $this->assertNotNull(JobHistory::find($foreign->id));

        // Livewire drops flash data at the end of an update request that does not redirect, so the
        // flashed text is checked by invoking the action directly on a mounted instance.
        $second = JobHistory::where('jabatan', 'Jabatan 06')->firstOrFail();
        $this->table()->instance()->deleteRow($second->id);
        $this->assertSame('Riwayat jabatan berhasil dihapus.', session('message'));
    }

    public function test_employee_id_cannot_be_changed_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->table()->set('employeeId', $this->other->nik);
    }

    public function test_row_action_shows_loading_state(): void
    {
        $targets = 'search, sortBy, perPage, gotoPage, nextPage, previousPage, resetFilters, deleteRow';

        // While the row action runs the skeleton replaces the rows, the table is busy, and the button is disabled.
        $this->table()
            ->assertSeeHtml('wire:loading.delay.remove wire:target="'.$targets.'"')
            ->assertSeeHtml('wire:loading.delay.class.remove="hidden" wire:target="'.$targets.'"')
            ->assertSeeHtml('wire:loading.attr="aria-busy" wire:target="'.$targets.'"')
            ->assertSeeHtml('wire:loading.attr="disabled" wire:target="deleteRow"');
    }

    public function test_cancelling_the_delete_confirm_stops_the_livewire_call(): void
    {
        // Livewire ignores preventDefault, so a cancelled confirm must stop the wire:click listener itself.
        $this->table()
            ->assertSeeHtml("onclick=\"confirm('Hapus riwayat jabatan ini?') || event.stopImmediatePropagation()\"")
            ->assertDontSeeHtml('return confirm(');
    }
}
