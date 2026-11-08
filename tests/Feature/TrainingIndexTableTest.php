<?php

namespace Tests\Feature;

use App\Exports\TrainingsExport;
use App\Livewire\Admin\TrainingIndexTable;
use App\Models\EmployeeStatistic;
use App\Models\MasterAuditLog;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\TrainingIndexTable (Manajemen Pelatihan list and its summary on the shared table engine).
 */
class TrainingIndexTableTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    /** @param array<string, mixed> $attributes */
    private function makeTraining(array $attributes = []): Training
    {
        return Training::create($attributes + [
            'judul' => 'Pelatihan Dasar',
            'jenis' => 'Teknis',
            'metode' => 'Tatap Muka',
            'tanggal_mulai' => '2026-02-10',
            'tanggal_akhir' => '2026-02-12',
            'biaya_tiket_peserta' => 1000,
            'biaya_pelatihan' => 5000,
            'penyelenggara' => 'Lembaga Uji',
            'nomor_surat' => 'SRT/001',
            'tanggal_surat' => '2026-01-30',
            'keterangan' => '-',
            'bidang_pelatihan' => 'TEK',
            'jam_belajar_per_hari' => 8,
            'jumlah_man_hours' => 24,
        ]);
    }

    private function table()
    {
        return Livewire::test(TrainingIndexTable::class);
    }

    /** @return array<string, mixed> */
    private function summary($component): array
    {
        return $component->instance()->summary();
    }

    /** @return array<int, string> */
    private function titles($component): array
    {
        return collect($component->instance()->rows->items())->pluck('judul')->all();
    }

    public function test_legacy_date_range_query_is_applied(): void
    {
        $this->makeTraining(['judul' => 'Di Dalam Rentang', 'tanggal_mulai' => '2026-01-01']);
        $this->makeTraining(['judul' => 'Batas Akhir', 'tanggal_mulai' => '2026-03-31']);
        $this->makeTraining(['judul' => 'Di Luar Rentang', 'tanggal_mulai' => '2026-06-01']);

        $this->get(route('admin.trainings.index', ['start_date' => '2026-01-01', 'end_date' => '2026-03-31']))
            ->assertOk()
            ->assertSee('Di Dalam Rentang')
            ->assertSee('Batas Akhir')
            ->assertDontSee('Di Luar Rentang')
            ->assertSee('01 Jan 2026')
            ->assertSee('31 Mar 2026');

        Livewire::withQueryParams(['start_date' => '2026-01-01', 'end_date' => '2026-03-31'])
            ->test(TrainingIndexTable::class)
            ->assertSet('startDate', '2026-01-01')
            ->assertSet('endDate', '2026-03-31');
    }

    public function test_index_page_mounts_the_table_without_old_form(): void
    {
        $this->get(route('admin.trainings.index'))
            ->assertOk()
            ->assertSee('Manajemen Pelatihan')
            ->assertSee('Tambah Pelatihan')
            ->assertSeeLivewire(TrainingIndexTable::class)
            ->assertDontSee('Filter & Export Data')
            ->assertDontSeeHtml('id="filterForm"');
    }

    public function test_summary_follows_search_and_date_filters(): void
    {
        $this->makeTraining(['judul' => 'Kepemimpinan A', 'tanggal_mulai' => '2026-01-10', 'jumlah_man_hours' => 10, 'biaya_tiket_peserta' => 100, 'biaya_pelatihan' => 1000]);
        $this->makeTraining(['judul' => 'Kepemimpinan B', 'tanggal_mulai' => '2026-02-10', 'jumlah_man_hours' => 20, 'biaya_tiket_peserta' => 200, 'biaya_pelatihan' => 2000]);
        $this->makeTraining(['judul' => 'Keselamatan', 'tanggal_mulai' => '2026-02-15', 'jumlah_man_hours' => 40, 'biaya_tiket_peserta' => 400, 'biaya_pelatihan' => 4000]);

        $component = $this->table();
        $this->assertEquals(['man_hours' => 70, 'tiket' => 700, 'biaya' => 7000, 'karyawan' => null], $this->summary($component));

        $component->set('search', 'Kepemimpinan');
        $this->assertEquals(['man_hours' => 30, 'tiket' => 300, 'biaya' => 3000, 'karyawan' => null], $this->summary($component));

        $component->set('startDate', '2026-02-01');
        $this->assertEquals(['man_hours' => 20, 'tiket' => 200, 'biaya' => 2000, 'karyawan' => null], $this->summary($component));
        $this->assertSame(1, $component->instance()->rows->total());

        $component->assertSee('Total Man Hours')->assertSee('Rp 2.000');
    }

    public function test_total_karyawan_only_with_both_dates(): void
    {
        $this->makeTraining(['tanggal_mulai' => '2026-01-10', 'jumlah_man_hours' => 100]);
        EmployeeStatistic::create(['periode' => '2026-01-01', 'jumlah_karpim' => 5, 'jumlah_karpel' => 45, 'total_karyawan' => 50]);
        EmployeeStatistic::create(['periode' => '2026-02-01', 'jumlah_karpim' => 5, 'jumlah_karpel' => 45, 'total_karyawan' => 50]);
        EmployeeStatistic::create(['periode' => '2026-06-01', 'jumlah_karpim' => 5, 'jumlah_karpel' => 45, 'total_karyawan' => 999]);

        $component = $this->table()->set('startDate', '2026-01-01');
        $this->assertNull($this->summary($component)['karyawan']);
        $component->assertSee('Data Belum Lengkap');

        $component->set('endDate', '2026-02-20');
        $this->assertSame(100, $this->summary($component)['karyawan']);
        $component->assertSee('Rata-rata JPL / Karyawan')->assertSee('1,00');

        $component->set('endDate', '')->assertSee('Data Belum Lengkap');
        $this->assertNull($this->summary($component)['karyawan']);
    }

    public function test_header_title_count_and_export(): void
    {
        foreach (range(1, 3) as $i) {
            $this->makeTraining(['judul' => "Pelatihan {$i}"]);
        }

        $html = $this->table()->html();
        preg_match('/<p role="status"[^>]*>(.*?)<\/p>/s', $html, $region);

        $this->assertStringContainsString('Daftar Pelatihan', $html);
        $this->assertStringStartsWith('3 Pelatihan', trim(preg_replace('/\s+/', ' ', strip_tags($region[1]))));
        $this->assertStringContainsString('Ekspor Excel', $html);
    }

    public function test_export_is_gated_and_audited(): void
    {
        $this->makeTraining(['judul' => 'Pelatihan Ekspor']);
        $this->makeTraining(['judul' => 'Lainnya']);

        $viewer = User::factory()->create(['role' => 'user']);
        // Non-admins are now refused outright by the component's boot() guard, before any export action.
        Livewire::actingAs($viewer)->test(TrainingIndexTable::class)->assertForbidden();
        $this->assertSame(0, MasterAuditLog::where('action', 'data_table.export')->count());

        Livewire::actingAs($this->admin)->test(TrainingIndexTable::class)
            ->set('search', 'Ekspor')
            ->call('export')
            ->assertFileDownloaded();

        $log = MasterAuditLog::where('action', 'data_table.export')->sole();
        $this->assertSame(1, $log->new_values['jumlah_baris']);
        $this->assertTrue($log->new_values['pencarian']);
    }

    public function test_export_keeps_business_format_and_honors_search(): void
    {
        $this->makeTraining(['judul' => 'Pelatihan Ekspor']);
        $this->makeTraining(['judul' => 'Lainnya']);

        $instance = $this->table()->set('search', 'Ekspor')->instance();
        $query = (new ReflectionMethod($instance, 'filteredQuery'))->invoke($instance);
        $export = (new ReflectionMethod($instance, 'buildExport'))->invoke($instance, $query, $this->admin);

        $this->assertInstanceOf(TrainingsExport::class, $export);
        $this->assertCount(15, $export->headings());
        $this->assertSame(['Pelatihan Ekspor'], $export->collection()->pluck('judul')->all());

        $this->assertMatchesRegularExpression('/^data_pelatihan_.*\.xlsx$/', (new ReflectionMethod($instance, 'exportFileName'))->invoke($instance));
    }

    public function test_search_and_sort(): void
    {
        $this->makeTraining(['judul' => 'Alpha Kelas', 'bidang_pelatihan' => 'KEU', 'jumlah_man_hours' => 5]);
        $this->makeTraining(['judul' => 'Beta Kelas', 'bidang_pelatihan' => 'TAN', 'jumlah_man_hours' => 50]);

        $component = $this->table()
            ->assertSet('sortField', 'tanggal_mulai')
            ->assertSet('sortDirection', 'desc')
            ->assertSet('perPage', 20);

        $component->set('search', 'TAN');
        $this->assertSame(['Beta Kelas'], $this->titles($component));

        $component->set('search', 'Alpha');
        $this->assertSame(['Alpha Kelas'], $this->titles($component));

        $component->set('search', '')->call('sortBy', 'jumlah_man_hours')->call('sortBy', 'jumlah_man_hours');
        $this->assertSame(['Beta Kelas', 'Alpha Kelas'], $this->titles($component));

        $component->call('sortBy', 'employees_count')->assertSet('sortField', 'employees_count');
        $this->assertSame(2, $component->instance()->rows->total());
    }

    public function test_jenis_and_metode_filters(): void
    {
        $this->makeTraining(['judul' => 'Teknis Satu', 'jenis' => 'Teknis', 'metode' => 'Daring']);
        $this->makeTraining(['judul' => 'Manajerial Satu', 'jenis' => 'Manajerial', 'metode' => 'Tatap Muka']);

        $component = $this->table()->set('jenis', 'Manajerial');
        $this->assertSame(['Manajerial Satu'], $this->titles($component));

        $component->set('jenis', '')->set('metode', 'Daring');
        $this->assertSame(['Teknis Satu'], $this->titles($component));
    }

    public function test_zero_results_show_reset(): void
    {
        $this->table()->assertSee('Belum ada data pelatihan.');

        $this->makeTraining();

        $this->table()
            ->set('search', 'tidak ada yang cocok')
            ->assertSee('Tidak ada pelatihan yang sesuai dengan pencarian atau filter.')
            ->assertSee('Reset Filter');
    }

    public function test_row_actions_and_delete_still_work(): void
    {
        $training = $this->makeTraining();

        $this->table()
            ->assertSeeHtml(route('admin.trainings.show', $training->id))
            ->assertSeeHtml(route('admin.trainings.edit', $training->id))
            ->assertSeeHtml(route('admin.trainings.destroy', $training->id));

        $this->delete(route('admin.trainings.destroy', $training->id))
            ->assertRedirect(route('admin.trainings.index'));

        $this->assertDatabaseMissing('trainings', ['id' => $training->id]);
    }

    public function test_no_internal_document_names_rendered(): void
    {
        $this->makeTraining();

        $html = $this->get(route('admin.trainings.index'))->getContent();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $name) {
            $this->assertStringNotContainsString($name, $html);
        }
    }

    public function test_non_admin_is_refused(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']));

        Livewire::test(TrainingIndexTable::class)->assertForbidden();
    }
}
