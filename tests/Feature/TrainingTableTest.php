<?php

namespace Tests\Feature;

use App\Livewire\Tables\TrainingTable;
use App\Models\Employee;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

use function Livewire\invade;

/**
 * SUT: App\Livewire\Tables\TrainingTable (employee profile, training history).
 */
class TrainingTableTest extends TestCase
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

        // Seven trainings for the employee: Pelatihan 01 is the oldest, Pelatihan 07 the newest.
        for ($i = 1; $i <= 7; $i++) {
            $this->makeTraining([
                'judul' => sprintf('Pelatihan %02d', $i),
                'tanggal_mulai' => sprintf('2024-%02d-01', $i),
                'tanggal_akhir' => sprintf('2024-%02d-05', $i),
            ])->employees()->attach($this->employee->nik);
        }

        $this->makeTraining(['judul' => 'Pelatihan Pegawai Lain'])->employees()->attach($this->other->nik);
    }

    /** @param array<string, mixed> $attributes */
    private function makeTraining(array $attributes): Training
    {
        return Training::create($attributes + [
            'jenis' => 'Teknis',
            'metode' => 'Luring',
            'tanggal_mulai' => '2023-01-01',
            'tanggal_akhir' => '2023-01-02',
            'biaya_tiket_peserta' => 0,
            'biaya_pelatihan' => 0,
            'penyelenggara' => 'Lembaga Umum',
            'nomor_surat' => 'NS/001',
            'tanggal_surat' => '2023-01-01',
            'keterangan' => 'Keterangan rahasia',
            'bidang_pelatihan' => 'Tanaman',
            'jam_belajar_per_hari' => 8,
        ]);
    }

    private function table()
    {
        return Livewire::test(TrainingTable::class, ['employeeId' => $this->employee->nik]);
    }

    public function test_lists_only_this_employees_records(): void
    {
        $component = $this->table()->set('perPage', 10)
            ->assertSee('Pelatihan 01')
            ->assertSee('Pelatihan 07')
            ->assertDontSee('Pelatihan Pegawai Lain');

        $this->assertSame(7, $component->instance()->rows->total());
    }

    public function test_default_sort_and_five_per_page(): void
    {
        $component = $this->table()
            ->assertSet('sortField', 'tanggal_mulai')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', 5)
            ->assertSeeInOrder(['Pelatihan 07', 'Pelatihan 06', 'Pelatihan 05', 'Pelatihan 04', 'Pelatihan 03'])
            ->assertDontSee('Pelatihan 02')
            ->assertSeeHtml('aria-label="Halaman pelatihan"');

        $this->assertCount(5, $component->instance()->rows->items());
        $this->assertSame([5, 10, 25, 50], invade($component->instance())->perPageOptions());
    }

    public function test_search_matches_declared_columns(): void
    {
        Training::where('judul', 'Pelatihan 02')->update(['penyelenggara' => 'Balai Sawit Medan']);
        Training::where('judul', 'Pelatihan 03')->update(['jenis' => 'Manajerial']);
        Training::where('judul', 'Pelatihan 04')->update(['metode' => 'Daring']);
        Training::where('judul', 'Pelatihan 05')->update(['keterangan' => 'Kata Tersembunyi']);

        $component = $this->table();

        $cases = ['Pelatihan 01' => 'Pelatihan 01', 'Balai Sawit' => 'Pelatihan 02', 'Manajerial' => 'Pelatihan 03', 'Daring' => 'Pelatihan 04'];

        foreach ($cases as $term => $expected) {
            $component->set('search', $term);
            $titles = collect($component->instance()->rows->items())->pluck('judul')->all();
            $this->assertSame([$expected], $titles, "Search for [{$term}]");
        }

        // Undeclared columns are not searched, and other employees' trainings never leak in.
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
            ->assertSet('sortField', 'tanggal_mulai')
            ->assertSeeInOrder(['Pelatihan 07', 'Pelatihan 06', 'Pelatihan 05']);
    }

    public function test_page_name_is_isolated(): void
    {
        $component = $this->table()
            ->call('gotoPage', 2, 'pelatihan_page')
            ->assertSet('paginators.pelatihan_page', 2)
            ->assertSee('Pelatihan 02')
            ->assertDontSee('Pelatihan 07')
            ->assertSeeHtml("gotoPage(1, 'pelatihan_page')")
            ->assertSeeHtml('id="per-page-pelatihan_page"');

        $this->assertSame('pelatihan_page', $component->instance()->rows->getPageName());
        $this->assertSame(2, $component->instance()->rows->currentPage());
    }

    public function test_row_action_still_works(): void
    {
        $training = Training::where('judul', 'Pelatihan 07')->firstOrFail();

        $this->table()
            ->call('detachTraining', $training->id)
            ->assertDontSee('Pelatihan 07');

        $this->assertFalse($training->employees()->where('employee_nik', $this->employee->nik)->exists());
        $this->assertNotNull(Training::find($training->id));

        // Livewire drops flash data at the end of an update request that does not redirect, so the
        // flashed text is checked by invoking the action directly on a mounted instance.
        $second = Training::where('judul', 'Pelatihan 06')->firstOrFail();
        $this->table()->instance()->detachTraining($second->id);
        $this->assertSame('Pelatihan berhasil dihapus dari karyawan.', session('message'));
    }

    public function test_employee_id_cannot_be_changed_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        $this->table()->set('employeeId', $this->other->nik);
    }
}
