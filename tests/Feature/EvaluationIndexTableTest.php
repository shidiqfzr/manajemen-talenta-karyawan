<?php

namespace Tests\Feature;

use App\Livewire\Admin\EvaluationIndexTable;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\EvaluationIndexTable (Manajemen Penilaian list on the shared table engine).
 */
class EvaluationIndexTableTest extends TestCase
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
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $employee
     */
    private function makeEvaluation(array $attributes = [], array $employee = []): Evaluation
    {
        $employee = Employee::factory()->create($employee);

        return Evaluation::create($attributes + [
            'employee_nik' => $employee->nik,
            'nilai_tertimbang' => 80,
            'hasil_skor_asesmen' => 75,
            'kategori_asesmen' => 'Baik',
            'kategori_9box' => 'Solid Performer',
            'bidang_tugas' => 'Bidang Umum',
            'tanggal_pelaksanaan_asesmen' => '2024-05-10',
        ]);
    }

    private function table()
    {
        return Livewire::test(EvaluationIndexTable::class);
    }

    public function test_index_page_mounts_the_table(): void
    {
        $this->makeEvaluation();

        $this->get(route('admin.evaluations.index'))
            ->assertOk()
            ->assertSee('Manajemen Penilaian')
            ->assertSee('Tambah Penilaian')
            ->assertSeeLivewire(EvaluationIndexTable::class);
    }

    public function test_lists_evaluations_newest_first_20_per_page(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->makeEvaluation(['bidang_tugas' => sprintf('Bidang %02d', $i), 'tanggal_pelaksanaan_asesmen' => sprintf('2024-01-%02d', $i)]);
        }

        $component = $this->table()
            ->assertSet('sortField', 'tanggal_pelaksanaan_asesmen')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', 20);

        $items = collect($component->instance()->rows->items());
        $this->assertCount(20, $items);
        $this->assertSame('Bidang 25', $items->first()->bidang_tugas);
        $this->assertSame('Bidang 06', $items->last()->bidang_tugas);
        $this->assertSame(25, $component->instance()->rows->total());
    }

    public function test_header_shows_title_and_count(): void
    {
        foreach (range(1, 3) as $ignored) {
            $this->makeEvaluation();
        }

        $html = $this->table()->html();
        preg_match('/<p role="status"[^>]*>(.*?)<\/p>/s', $html, $region);

        $this->assertStringContainsString('Daftar Penilaian', $html);
        $this->assertStringStartsWith('3 Penilaian', trim(preg_replace('/\s+/', ' ', strip_tags($region[1]))));
        $this->assertStringNotContainsString('Ekspor Excel', $html);
    }

    public function test_search_matches_nik_name_and_unit(): void
    {
        $target = $this->makeEvaluation([], ['nik' => '77001122', 'nama' => 'Zainal Pencari', 'jabatan' => 'Manajer Contoh', 'unit_kerja' => 'Unit Spesifik Satu']);
        $this->makeEvaluation([], ['nama' => 'Orang Lain', 'jabatan' => 'Asisten Biasa', 'unit_kerja' => 'Kebun Biasa']);

        $component = $this->table();

        foreach (['77001122', 'Zainal Pencari', 'Manajer Contoh', 'Unit Spesifik'] as $term) {
            $component->set('search', $term);
            $this->assertSame([$target->id], collect($component->instance()->rows->items())->pluck('id')->all(), "Search for [{$term}]");
        }

        $component->set('search', 'tidak ada yang cocok');
        $this->assertSame(0, $component->instance()->rows->total());
    }

    public function test_kategori_filter_and_chip(): void
    {
        $this->makeEvaluation(['kategori_9box' => 'Future Star', 'bidang_tugas' => 'Bidang Bintang']);
        $this->makeEvaluation(['kategori_9box' => 'Solid Performer', 'bidang_tugas' => 'Bidang Solid']);

        $component = $this->table()
            ->set('kategori', 'Future Star')
            ->assertSee('Semua Kategori')
            ->assertSee('Kategori 9-Box');

        $this->assertSame(['Bidang Bintang'], collect($component->instance()->rows->items())->pluck('bidang_tugas')->all());

        $component->set('kategori', 'Tidak Dikenal')->assertSet('kategori', '');
        $this->assertSame(2, $component->instance()->rows->total());
    }

    public function test_date_range_filter(): void
    {
        $this->makeEvaluation(['tanggal_pelaksanaan_asesmen' => '2023-03-01', 'bidang_tugas' => 'Bidang Lama']);
        $this->makeEvaluation(['tanggal_pelaksanaan_asesmen' => '2024-06-15', 'bidang_tugas' => 'Bidang Tengah']);
        $this->makeEvaluation(['tanggal_pelaksanaan_asesmen' => '2025-09-30', 'bidang_tugas' => 'Bidang Baru']);

        $component = $this->table()->set('from', '2024-01-01')->set('to', '2024-12-31');

        $this->assertSame(['Bidang Tengah'], collect($component->instance()->rows->items())->pluck('bidang_tugas')->all());
    }

    public function test_tampered_sort_field_is_ignored(): void
    {
        $this->makeEvaluation();

        $this->table()
            ->set('sortField', 'employee_nik; drop')
            ->assertSet('sortField', 'tanggal_pelaksanaan_asesmen')
            ->set('sortDirection', 'sideways')
            ->assertSet('sortDirection', 'desc');
    }

    public function test_zero_results_show_reset(): void
    {
        $this->table()->assertSee('Belum ada data penilaian.');

        $this->makeEvaluation();

        $this->table()
            ->set('search', 'tidak ada yang cocok')
            ->assertSee('Tidak ada penilaian yang sesuai dengan pencarian atau filter.')
            ->assertSee('Reset Filter');
    }

    public function test_delete_action_still_works(): void
    {
        $evaluation = $this->makeEvaluation();

        $this->table()->assertSeeHtml(route('admin.evaluations.destroy', $evaluation->id));

        $this->delete(route('admin.evaluations.destroy', $evaluation->id))
            ->assertRedirect(route('admin.evaluations.index'));

        $this->assertDatabaseMissing('evaluations', ['id' => $evaluation->id]);
    }

    public function test_no_internal_document_names_rendered(): void
    {
        $this->makeEvaluation();

        $html = $this->get(route('admin.evaluations.index'))->getContent();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }

    public function test_non_admin_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        Livewire::test(EvaluationIndexTable::class)->assertForbidden();
    }

    public function test_evaluations_index_view_has_no_utf8_bom(): void
    {
        $head = substr(file_get_contents(resource_path('views/admin/evaluations/index.blade.php')), 0, 3);

        $this->assertNotSame('efbbbf', bin2hex($head));
    }
}
