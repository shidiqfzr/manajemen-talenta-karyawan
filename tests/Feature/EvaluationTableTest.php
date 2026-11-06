<?php

namespace Tests\Feature;

use App\Livewire\Tables\EvaluationTable;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

use function Livewire\invade;

/**
 * SUT: App\Livewire\Tables\EvaluationTable (employee profile, evaluation history).
 */
class EvaluationTableTest extends TestCase
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

        // Seven evaluations: "Bidang 01" is the oldest assessment, "Bidang 07" the newest.
        for ($i = 1; $i <= 7; $i++) {
            $this->makeEvaluation($this->employee, [
                'bidang_tugas' => sprintf('Bidang %02d', $i),
                'tanggal_pelaksanaan_asesmen' => sprintf('2024-%02d-10', $i),
                'nilai_tertimbang' => 70 + $i,
            ]);
        }

        $this->makeEvaluation($this->other, ['bidang_tugas' => 'Bidang Pegawai Lain', 'tanggal_pelaksanaan_asesmen' => '2024-12-01']);
    }

    /** @param array<string, mixed> $attributes */
    private function makeEvaluation(Employee $employee, array $attributes): Evaluation
    {
        return Evaluation::create($attributes + [
            'employee_nik' => $employee->nik,
            'lembaga_asesmen' => 'Lembaga Umum',
            'kategori_asesmen' => 'Reguler',
            'kategori_9box' => 'Solid Performer',
            'skor_smkbk_9box' => 2,
            'skor_cli_9box' => 2,
            'keterangan' => 'Keterangan rahasia',
        ]);
    }

    private function table()
    {
        return Livewire::test(EvaluationTable::class, ['employeeId' => $this->employee->nik]);
    }

    public function test_lists_only_this_employees_records(): void
    {
        $component = $this->table()->set('perPage', 10)
            ->assertSee('Bidang 01')
            ->assertSee('Bidang 07')
            ->assertDontSee('Bidang Pegawai Lain');

        $this->assertSame(7, $component->instance()->rows->total());
    }

    public function test_default_sort_and_five_per_page(): void
    {
        $component = $this->table()
            ->assertSet('sortField', 'tanggal_pelaksanaan_asesmen')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', 5)
            ->assertSeeInOrder(['Bidang 07', 'Bidang 06', 'Bidang 05', 'Bidang 04', 'Bidang 03'])
            ->assertDontSee('Bidang 02')
            ->assertSeeHtml('aria-label="Halaman evaluasi"')
            // Sortable headers: Periode Asesmen, Nilai Tertimbang, Bidang Tugas only.
            ->assertSeeHtml("sortBy('tanggal_pelaksanaan_asesmen')")
            ->assertSeeHtml("sortBy('nilai_tertimbang')")
            ->assertSeeHtml("sortBy('bidang_tugas')")
            ->assertDontSeeHtml("sortBy('kategori_9box')")
            ->assertDontSeeHtml("sortBy('lembaga_asesmen')")
            // A cancelled delete confirm must stop the wire:click listener (Livewire ignores preventDefault).
            ->assertSeeHtml('event.stopImmediatePropagation()')
            ->assertDontSeeHtml('return confirm(');

        $this->assertCount(5, $component->instance()->rows->items());
        $this->assertSame([5, 10, 25, 50], invade($component->instance())->perPageOptions());
    }

    public function test_search_matches_declared_columns(): void
    {
        Evaluation::where('bidang_tugas', 'Bidang 02')->update(['lembaga_asesmen' => 'PPM Manajemen']);
        Evaluation::where('bidang_tugas', 'Bidang 03')->update(['kategori_9box' => 'Future Star']);
        Evaluation::where('bidang_tugas', 'Bidang 04')->update(['kategori_asesmen' => 'Promosi Khusus']);
        Evaluation::where('bidang_tugas', 'Bidang 05')->update(['keterangan' => 'Kata Tersembunyi']);

        $component = $this->table();

        $cases = ['Bidang 01' => 'Bidang 01', 'PPM Manajemen' => 'Bidang 02', 'Future Star' => 'Bidang 03', 'Promosi Khusus' => 'Bidang 04'];

        foreach ($cases as $term => $expected) {
            $component->set('search', $term);
            $fields = collect($component->instance()->rows->items())->pluck('bidang_tugas')->all();
            $this->assertSame([$expected], $fields, "Search for [{$term}]");
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
            ->assertSet('sortField', 'tanggal_pelaksanaan_asesmen')
            ->assertSeeInOrder(['Bidang 07', 'Bidang 06', 'Bidang 05']);
    }

    public function test_page_name_is_isolated(): void
    {
        $component = $this->table()
            ->call('gotoPage', 2, 'evaluasi_page')
            ->assertSet('paginators.evaluasi_page', 2)
            ->assertSee('Bidang 02')
            ->assertDontSee('Bidang 07')
            ->assertSeeHtml("gotoPage(1, 'evaluasi_page')")
            ->assertSeeHtml('id="per-page-evaluasi_page"');

        $this->assertSame('evaluasi_page', $component->instance()->rows->getPageName());
        $this->assertSame(2, $component->instance()->rows->currentPage());
    }

    public function test_row_action_still_works(): void
    {
        $evaluation = Evaluation::where('bidang_tugas', 'Bidang 07')->firstOrFail();
        $foreign = Evaluation::where('employee_nik', $this->other->nik)->firstOrFail();

        $this->table()
            ->call('deleteRow', $evaluation->id)
            ->assertDontSee('Bidang 07');

        $this->assertNull(Evaluation::find($evaluation->id));

        // Another employee's evaluation cannot be deleted through this table.
        $this->table()->call('deleteRow', $foreign->id)->assertStatus(404);
        $this->assertNotNull(Evaluation::find($foreign->id));

        // Livewire drops flash data at the end of an update request that does not redirect, so the
        // flashed text is checked by invoking the action directly on a mounted instance.
        $second = Evaluation::where('bidang_tugas', 'Bidang 06')->firstOrFail();
        $this->table()->instance()->deleteRow($second->id);
        $this->assertSame('Data evaluasi berhasil dihapus.', session('message'));
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
}
