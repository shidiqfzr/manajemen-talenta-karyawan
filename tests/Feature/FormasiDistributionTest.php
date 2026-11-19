<?php

namespace Tests\Feature;

use App\Exports\FormasiExport;
use App\Livewire\Admin\Master\FormasiDistribution;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\Master\FormasiDistribution (tab "Sebaran Formasi", data from JobVacancyService).
 */
class FormasiDistributionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unit $alfa;

    private Unit $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->alfa = Unit::create(['nama' => 'Unit Alfa Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
        $this->beta = Unit::create(['nama' => 'Unit Beta Uji', 'kode' => 'PKS', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => true, 'urutan' => 2]);

        // Family "Asisten Afdeling": V is filled in Alfa, X (quota 2) is empty in Beta.
        $filled = $this->seat($this->alfa, 'Asisten Afdeling V', 1);
        Employee::factory()->create(['unit_kerja' => $this->alfa->nama, 'jabatan' => $filled->nama]);
        $this->seat($this->beta, 'Asisten Afdeling X', 2);
        $this->seat($this->alfa, 'Kepala Keuangan Uji', 1, 'RM-2', 'KEU');
        $acting = $this->seat($this->beta, 'Manajer Plt Beta', 1);
        Employee::factory()->create(['unit_kerja' => $this->beta->nama, 'jabatan' => $acting->nama, 'status_penugasan' => 'Plt']);
    }

    private function seat(Unit $unit, string $jabatan, int $kuota, string $rm = 'RM-3', string $bidang = 'TAN'): Position
    {
        $position = Position::create(['nama' => $jabatan, 'level' => 'Karpim', 'rm_level' => $rm, 'is_active' => true]);

        DB::table('position_unit')->insert([
            'unit_id' => $unit->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => $bidang,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $position;
    }

    private function table()
    {
        return Livewire::actingAs($this->admin)->test(FormasiDistribution::class);
    }

    public function test_only_admins_can_open_the_tab(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.sebaran.index'))->assertForbidden();
        Livewire::actingAs($user)->test(FormasiDistribution::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.sebaran.index'))->assertOk()->assertSee('Sebaran Formasi');
    }

    public function test_default_view_groups_per_family_and_shows_filled_and_vacant(): void
    {
        $this->table()
            ->assertSet('view', 'jabatan')
            ->assertSee('Asisten Afdeling')
            ->assertSee('2 unit')
            ->assertSee('Kepala Keuangan Uji')
            ->assertDontSee('Asisten Afdeling X');
    }

    public function test_the_coverage_note_follows_the_master_formasi_and_names_the_pool_exclusion(): void
    {
        $html = preg_replace('/\s+/', ' ', $this->table()->html());

        $this->assertStringContainsString('Cakupan mengikuti Master Formasi', $html);
        $this->assertStringContainsString('Advisor dan Diperbantukan', $html);
        $this->assertStringNotContainsString('pada unit Regional Office', $html, 'The note must not hardcode today\'s coverage.');
    }

    public function test_kpi_cards_summarise_the_whole_formasi(): void
    {
        $html = preg_replace('/\s+/', ' ', $this->table()->html());

        $this->assertStringContainsString('Total kuota 5', $html);
        $this->assertStringContainsString('4 kuota kosong', $html);
    }

    public function test_expanding_a_family_lists_each_unit_with_status_and_unit_link(): void
    {
        $this->table()
            ->call('toggleFamily', 'Asisten Afdeling')
            ->assertSet('expanded', 'Asisten Afdeling')
            ->assertSee('Unit Alfa Uji')
            ->assertSee('Unit Beta Uji')
            ->assertSee('Asisten Afdeling X')
            ->assertSee('Penuh')
            ->assertSee('Kosong')
            ->assertSeeHtml(route('admin.master.formasi.show', $this->alfa))
            ->assertSeeHtml('aria-expanded="true"')
            ->call('toggleFamily', 'Asisten Afdeling')
            ->assertSet('expanded', null);
    }

    public function test_expanded_family_clears_when_it_is_no_longer_listed(): void
    {
        $this->table()
            ->call('toggleFamily', 'Asisten Afdeling')
            ->set('search', 'Keuangan')
            ->assertSet('expanded', null)
            ->call('toggleFamily', 'Tidak Ada')
            ->assertSet('expanded', null);
    }

    public function test_unit_view_lists_rows_and_only_vacant_unreserved_rows_get_the_mutasi_link(): void
    {
        $html = $this->table()->call('setView', 'unit')->assertSet('view', 'unit')->assertSee('Asisten Afdeling V')->html();

        // Only formasi without any holder offer the link: Asisten Afdeling X and Kepala Keuangan Uji.
        // The filled V has none, and the Plt-held Manajer Plt Beta cannot be placed into (Mutasi rejects it).
        $this->assertSame(2, substr_count($html, 'Isi lewat Mutasi'));
        $this->assertStringContainsString('Ada pemegang aktif', $html);
        $this->assertStringContainsString(e(route('admin.mutasi.create', ['jabatan' => 'Asisten Afdeling X', 'unit' => 'Unit Beta Uji'])), $html);
    }

    public function test_a_partly_filled_formasi_with_a_free_seat_offers_the_mutasi_link(): void
    {
        $partial = $this->seat($this->alfa, 'Kepala Seksi Dua Kursi', 2);
        Employee::factory()->create(['unit_kerja' => $this->alfa->nama, 'jabatan' => $partial->nama]);
        // Two seats, one definitive holder and one Plt: no seat is free, the acting holder keeps it.
        $full = $this->seat($this->alfa, 'Kepala Seksi Plt Dua Kursi', 2);
        Employee::factory()->create(['unit_kerja' => $this->alfa->nama, 'jabatan' => $full->nama]);
        Employee::factory()->create(['unit_kerja' => $this->alfa->nama, 'jabatan' => $full->nama, 'status_penugasan' => 'Plt']);

        $html = $this->table()->call('setView', 'unit')->assertSee('Kepala Seksi Dua Kursi')->html();

        // Free seat: Asisten Afdeling X, Kepala Keuangan Uji and the partly filled Kepala Seksi Dua Kursi. Not Plt-held ones.
        $this->assertSame(3, substr_count($html, 'Isi lewat Mutasi'));
        $this->assertSame(2, substr_count($html, 'Ada pemegang aktif'));
    }

    public function test_status_kosong_hides_filled_formasi_in_both_views(): void
    {
        $this->table()->set('statusFilter', 'kosong')
            ->call('toggleFamily', 'Asisten Afdeling')
            ->assertSee('Asisten Afdeling X')
            ->assertDontSee('Asisten Afdeling V');

        $this->table()->set('statusFilter', 'kosong')->call('setView', 'unit')
            ->assertSee('Asisten Afdeling X')
            ->assertDontSee('Asisten Afdeling V');
    }

    public function test_kpi_card_toggles_the_kosong_filter(): void
    {
        $this->table()
            ->call('toggleKosongFilter')->assertSet('statusFilter', 'kosong')
            ->call('toggleKosongFilter')->assertSet('statusFilter', '');
    }

    public function test_unit_wilayah_rm_bidang_and_status_filters_narrow_the_list(): void
    {
        $this->table()->set('unitFilter', 'Unit Beta Uji')->assertSee('Manajer Plt Beta')->assertDontSee('Kepala Keuangan Uji');
        $this->table()->set('wilayahFilter', 'Wilayah Kalimantan Barat')->assertSee('Kepala Keuangan Uji')->assertDontSee('Manajer Plt Beta');
        $this->table()->set('rmFilter', 'RM-2')->assertSee('Kepala Keuangan Uji')->assertDontSee('Manajer Plt Beta');
        $this->table()->set('bidangFilter', 'KEU')->assertSee('Kepala Keuangan Uji')->assertDontSee('Manajer Plt Beta');
        $this->table()->set('statusFilter', 'acting')->assertSee('Manajer Plt Beta')->assertDontSee('Kepala Keuangan Uji')
            ->assertSeeHtml('aria-label="Hapus filter Status Formasi"');
    }

    public function test_search_is_trimmed_and_capped_and_finds_the_family(): void
    {
        $this->table()->set('search', 'afdeling')->assertSee('Asisten Afdeling')->assertDontSee('Kepala Keuangan Uji');
        $this->table()->set('search', '  '.str_repeat('a', 150).'  ')->assertSet('search', str_repeat('a', 100));
    }

    public function test_unknown_filter_values_views_and_page_sizes_are_discarded(): void
    {
        $this->table()
            ->set('statusFilter', 'bogus')->assertSet('statusFilter', '')
            ->set('unitFilter', "Unit'; DROP TABLE units;--")->assertSet('unitFilter', '')
            ->set('wilayahFilter', 'Mars')->assertSet('wilayahFilter', '')
            ->set('rmFilter', 'RM-99')->assertSet('rmFilter', '')
            ->set('severityFilter', 'purple')->assertSet('severityFilter', '')
            ->set('view', 'grid')->assertSet('view', 'jabatan')
            ->set('perPage', 9999)->assertSet('perPage', 25)
            ->assertSee('Asisten Afdeling');

        $this->assertDatabaseCount('units', 2);
    }

    public function test_sorting_accepts_only_whitelisted_columns_per_view(): void
    {
        $this->table()
            ->call('sortBy', 'kuota')->assertSet('sortField', 'kuota')->assertSet('sortDirection', 'asc')
            ->call('sortBy', 'kuota')->assertSet('sortDirection', 'desc')
            ->call('sortBy', 'nik')->assertSet('sortField', 'kuota')
            ->call('sortBy', 'unit_kerja')->assertSet('sortField', 'kuota')
            ->call('setView', 'unit')
            ->call('sortBy', 'unit_kerja')->assertSet('sortField', 'unit_kerja');
    }

    public function test_sorting_changes_the_row_order(): void
    {
        $component = $this->table()->call('sortBy', 'family');
        $html = $component->html();
        $this->assertLessThan(strpos($html, 'Kepala Keuangan Uji'), strpos($html, 'Asisten Afdeling'));

        $html = $component->call('sortBy', 'family')->html(); // descending
        $this->assertGreaterThan(strpos($html, 'Kepala Keuangan Uji'), strpos($html, 'Asisten Afdeling'));
    }

    public function test_unit_filter_with_an_ampersand_round_trips_through_the_url(): void
    {
        $unit = Unit::create(['nama' => 'Bagian Akuntansi & Keuangan', 'kode' => 'Kantor', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => true, 'urutan' => 3]);
        $this->seat($unit, 'Manajer Akuntansi Uji', 1);

        Livewire::actingAs($this->admin)
            ->withQueryParams(['unit' => 'Bagian Akuntansi & Keuangan', 'tampilan' => 'unit'])
            ->test(FormasiDistribution::class)
            ->assertSet('unitFilter', 'Bagian Akuntansi & Keuangan')
            ->assertSet('view', 'unit')
            ->assertSee('Manajer Akuntansi Uji')
            ->assertDontSee('Kepala Keuangan Uji');
    }

    public function test_reset_clears_filters_and_keeps_the_view(): void
    {
        $this->table()
            ->call('setView', 'unit')
            ->set('search', 'Alfa')
            ->set('unitFilter', 'Unit Alfa Uji')
            ->set('statusFilter', 'empty')
            ->call('resetFilters')
            ->assertSet('search', '')
            ->assertSet('unitFilter', '')
            ->assertSet('statusFilter', '')
            ->assertSet('view', 'unit')
            ->assertSee('Manajer Plt Beta');
    }

    public function test_empty_states(): void
    {
        $this->table()->set('search', 'tidak-ada-jabatan-ini')->assertSee('Tidak ada formasi yang sesuai dengan filter saat ini');

        DB::table('position_unit')->delete();
        $this->table()->assertSee('Belum ada formasi jabatan pada unit aktif.');
    }

    public function test_reserved_formasi_shows_the_schedule_instead_of_a_second_placement(): void
    {
        $successor = Employee::factory()->create(['nama' => 'Sari Pengganti', 'unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create([
            'employee_nik' => $successor->nik,
            'jabatan' => 'Kepala Keuangan Uji',
            'unit_kerja' => $this->alfa->nama,
            'tmt_awal' => now()->addMonth()->toDateString(),
            'is_applied' => false,
        ]);

        $html = $this->table()->call('setView', 'unit')->assertSee('Terjadwal (SK)')->assertSeeHtml('Dipesan oleh Sari Pengganti')->html();

        $this->assertSame(1, substr_count($html, 'Isi lewat Mutasi'));
    }

    public function test_legacy_lowongan_url_without_a_status_keeps_showing_only_vacant_formasi(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/master/lowongan?unit=Unit+Beta+Uji&durasi=unknown')
            ->assertStatus(301)
            ->assertRedirect(route('admin.master.sebaran.index', ['unit' => 'Unit Beta Uji', 'status' => 'kosong', 'durasi' => 'unknown']));
    }

    public function test_a_page_beyond_the_last_page_is_stored_as_the_last_page(): void
    {
        foreach (range(1, 12) as $n) {
            $this->seat($this->alfa, sprintf('Kepala Seksi Uji %02d', $n), 1);
        }

        Livewire::actingAs($this->admin)
            ->withQueryParams(['page' => 50, 'per_page' => 10])
            ->test(FormasiDistribution::class)
            ->assertSet('paginators.page', 2)
            ->call('previousPage')
            ->assertSet('paginators.page', 1);
    }

    public function test_view_toggle_is_a_keyboard_navigable_radiogroup(): void
    {
        $html = $this->table()->html();

        $this->assertMatchesRegularExpression('/role="radio" aria-checked="true" tabindex="0"/', $html);
        $this->assertMatchesRegularExpression('/role="radio" aria-checked="false" tabindex="-1"/', $html);
        $this->assertStringContainsString('@keydown.arrow-right.prevent', $html);
        $this->assertStringContainsString('@keydown.arrow-left.prevent', $html);
    }

    public function test_legacy_lowongan_url_redirects_to_sebaran_and_keeps_the_filters(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/master/lowongan?unit=Unit+Alfa+Uji&status=empty&search=Kepala')
            ->assertStatus(301)
            ->assertRedirect(route('admin.master.sebaran.index', ['unit' => 'Unit Alfa Uji', 'status' => 'empty', 'search' => 'Kepala']));
    }

    public function test_export_follows_the_view_and_never_contains_employee_names(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $successor = Employee::factory()->create(['nama' => 'Sari Pengganti', 'unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create(['employee_nik' => $successor->nik, 'jabatan' => 'Asisten Afdeling X', 'unit_kerja' => $this->beta->nama, 'tmt_awal' => now()->addMonth()->toDateString(), 'is_applied' => false]);

        $this->table()->call('export');

        Excel::assertDownloaded('/^Sebaran_Formasi_Jabatan_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/', function (FormasiExport $export) {
            $this->assertSame(['NO', 'JABATAN', 'RM BAND', 'JUMLAH UNIT', 'KUOTA', 'TERISI', 'KOSONG', 'KETERISIAN (%)'], $export->headings());
            $this->assertContains('Asisten Afdeling', $export->collection()->pluck(1)->all());
            $this->assertStringContainsString($this->admin->name, $export->properties()['description']);

            return true;
        });

        $this->table()->call('setView', 'unit')->set('statusFilter', 'kosong')->call('export');

        Excel::assertDownloaded('/^Sebaran_Formasi_Unit_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/', function (FormasiExport $export) {
            $this->assertSame(['NO', 'UNIT KERJA', 'WILAYAH', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'KOSONG', 'STATUS FORMASI', 'KOSONG SEJAK'], $export->headings());
            $this->assertCount(3, $export->collection());
            $this->assertStringNotContainsString('Sari', json_encode($export->collection()));

            return true;
        });

        $user = User::factory()->create(['role' => 'user']);
        Livewire::actingAs($user)->test(FormasiDistribution::class)->assertForbidden();
    }

    public function test_unit_export_marks_filled_formasi_without_a_vacancy_date(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $this->table()->call('setView', 'unit')->call('export');

        Excel::assertDownloaded('/^Sebaran_Formasi_Unit_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/', function (FormasiExport $export) {
            $filled = $export->collection()->firstWhere(3, 'Asisten Afdeling V');

            $this->assertSame('Penuh', $filled[9]);
            $this->assertSame('-', $filled[10]);

            return true;
        });
    }

    public function test_the_screen_never_says_lowong(): void
    {
        $perJabatan = strtolower($this->table()->call('toggleFamily', 'Asisten Afdeling')->html());
        $perUnit = strtolower($this->table()->call('setView', 'unit')->html());

        $this->assertStringNotContainsString('lowong', $perJabatan);
        $this->assertStringNotContainsString('lowong', $perUnit);
    }
}
