<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\StructureVersionsPage;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\CreatesStructureVersions;
use Tests\TestCase;

/**
 * Layar "Versi Struktur" (DOMAIN.md §3.7): baca-saja; satu kendali waktu (versi atau tanggal), tabel per jabatan atau per unit,
 * perbandingan antarversi, dan riwayat jabatan karyawan.
 */
class StructureVersionsPageTest extends TestCase
{
    use CreatesStructureVersions;
    use RefreshDatabase;

    private User $admin;

    private StructureDecree $first;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 08:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->first = $this->appliedDecree('DPSB/SKPTS/352/IX/2026', '2026-09-01', ['units' => [
            'Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'Regional Office', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Bagian' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                'Asisten Pemetaan' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TEK', 'kuota' => 2],
            ]],
            'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'urutan' => 2, 'is_active' => true, 'seats' => [
                'Manajer' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
        ]]);

        $this->employeeWithoutHistory('12345678', 'Dewi Lestari');
        $this->placement('12345678', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');
    }

    /** SK kedua (1 Nov 2026) yang menggantikan struktur dengan satu unit baru. */
    private function secondVersion(): StructureDecree
    {
        return $this->appliedDecree('DPSB/SKPTS/400/XI/2026', '2026-11-01', ['units' => [
            'Bagian Baru' => ['kode' => 'Bagian', 'wilayah' => 'Regional Office', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Baru' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
        ]]);
    }

    private function page(): Testable
    {
        return Livewire::actingAs($this->admin)->test(StructureVersionsPage::class);
    }

    public function test_page_is_for_admins_only_including_livewire_requests(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.versi.index'))->assertForbidden();
        Livewire::actingAs($user)->test(StructureVersionsPage::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.versi.index'))->assertOk();
    }

    public function test_page_is_its_own_master_data_tab_and_is_not_reached_through_a_buried_button(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.master.versi.index'))->assertOk()->getContent();
        preg_match('/<nav aria-label="Tampilan Formasi".*?<\/nav>/s', $html, $views);

        $this->assertSame(1, substr_count($views[0], 'aria-current="page"'));
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>.*?Versi Struktur/s', $views[0], 'Versi Struktur adalah salah satu tampilan Formasi');

        $sk = $this->actingAs($this->admin)->get(route('admin.master.sk.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('>Versi Struktur</x', $sk);
        $this->assertSame(1, preg_match('/aria-current="page"[^>]*>.*?Surat Keputusan/s', $sk), 'tab Surat Keputusan tetap aktif di daftar SK');
    }

    public function test_the_page_leads_with_data_not_with_explanations(): void
    {
        $html = $this->page()->html();

        $this->assertStringNotContainsString('Setiap SK yang diterapkan menambah satu versi struktur', $html, 'banner penjelasan panjang dihapus; tujuan ada pada subjudul halaman');
        $this->assertLessThan(strpos($html, 'Kepala Bagian'), strpos($html, 'Total Kuota'), 'ringkasan mendahului tabel');
    }

    public function test_one_time_control_lists_every_version_and_follows_the_date(): void
    {
        $second = $this->secondVersion();

        $this->page()
            ->assertSet('version', (string) $second->id)
            ->assertSee('DPSB/SKPTS/400/XI/2026')
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertSee('01 Nov 2026 – sekarang')
            ->assertSee('01 Sep 2026 – 31 Okt 2026')
            ->set('date', '2026-09-15')
            ->assertSet('version', (string) $this->first->id)
            ->set('date', '2026-08-31')
            ->assertSet('version', '');
    }

    public function test_choosing_a_version_opens_its_first_day_and_the_current_version_opens_today(): void
    {
        $second = $this->secondVersion();

        $this->page()
            ->set('version', (string) $this->first->id)
            ->assertSet('date', '2026-09-01')
            ->assertSee('Bagian Tanaman')
            ->assertDontSee('Bagian Baru')
            ->set('version', (string) $second->id)
            ->assertSet('date', '2026-11-17')
            ->assertSee('Bagian Baru');
    }

    public function test_an_unknown_version_is_ignored(): void
    {
        $this->page()
            ->set('version', '999999')
            ->assertSet('date', '2026-11-17')
            ->assertSet('version', (string) $this->first->id);
    }

    public function test_a_single_version_explains_where_the_next_one_comes_from_without_a_big_card(): void
    {
        $this->page()
            ->assertSee('Baru ada satu versi')
            ->assertSee('Catat SK dari Holding');
    }

    public function test_context_line_names_the_decree_in_force_with_a_link_to_it(): void
    {
        $html = $this->page()->html();

        $this->assertStringContainsString('mengacu pada', $html);
        $this->assertStringContainsString(route('admin.master.sk.show', $this->first), $html);
    }

    public function test_summary_tiles_show_units_jabatan_total_quota_and_filled(): void
    {
        $component = $this->page();

        $component->assertSeeInOrder(['Unit', '2', 'Jabatan', '3', 'Total Kuota', '4', 'Terisi', '1']);
        $this->assertSame(['units' => 2, 'seats' => 3, 'kuota' => 4, 'filled' => 1], $component->instance()->report['totals']);
    }

    public function test_default_table_is_flat_per_jabatan_so_a_jabatan_can_be_found_without_opening_units(): void
    {
        $this->page()
            ->assertSet('view', 'jabatan')
            ->assertSeeInOrder(['Kepala Bagian', 'Asisten Pemetaan', 'Manajer'])
            ->assertSee('Dewi Lestari')
            ->assertDontSee('Buka semua unit');
    }

    public function test_a_seat_with_more_holders_than_its_quota_is_flagged_in_both_views(): void
    {
        $this->employeeWithoutHistory('87654321', 'Budi Santoso');
        $this->placement('87654321', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');

        $this->page()->assertSee('melebihi kuota')->set('view', 'unit')->assertSee('melebihi kuota');
        $this->assertStringNotContainsString('melebihi kuota', Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)->set('search', 'Tajati')->html());
    }

    public function test_unit_view_groups_jabatan_inside_expandable_units_with_an_expand_all_control(): void
    {
        $this->page()
            ->set('view', 'unit')
            ->assertSeeInOrder(['Bagian Tanaman', 'Kebun Tajati'])
            ->assertSee('Buka semua unit')
            ->assertSee('Tutup semua unit')
            ->assertSee('Asisten Pemetaan');
    }

    public function test_unknown_view_falls_back_to_the_flat_view(): void
    {
        $this->page()->set('view', 'sembarang')->assertSet('view', 'jabatan');
    }

    public function test_search_matches_unit_wilayah_jabatan_or_holder_name_and_shows_only_matching_rows(): void
    {
        $this->page()
            ->set('search', 'Tajati')
            ->assertSee('Manajer')
            ->assertDontSee('Asisten Pemetaan')
            ->set('search', 'pemetaan')
            ->assertSee('Asisten Pemetaan')
            ->assertDontSee('Manajer')
            ->set('search', 'Kalimantan Timur')
            ->assertSee('Manajer')
            ->assertDontSee('Kepala Bagian')
            ->set('search', 'lestari')
            ->assertSee('Kepala Bagian')
            ->assertDontSee('Asisten Pemetaan');
    }

    public function test_filters_narrow_by_wilayah_rm_band_bidang_and_filled_state(): void
    {
        $this->page()
            ->set('rmFilter', 'RM-3')->assertSee('Asisten Pemetaan')->assertDontSee('Manajer')->set('rmFilter', '')
            ->set('bidangFilter', 'TEK')->assertSee('Asisten Pemetaan')->assertDontSee('Kepala Bagian')->set('bidangFilter', '')
            ->set('wilayahFilter', 'Wilayah Kalimantan Timur')->assertSee('Manajer')->assertDontSee('Kepala Bagian')->set('wilayahFilter', '')
            ->set('isiFilter', 'terisi')->assertSee('Kepala Bagian')->assertDontSee('Manajer')
            ->set('isiFilter', 'kosong')->assertSee('Manajer')->assertDontSee('Kepala Bagian');
    }

    public function test_unknown_filter_values_are_ignored_and_reset_clears_everything(): void
    {
        $this->page()
            ->set('rmFilter', 'RM-99')->assertSet('rmFilter', '')
            ->set('isiFilter', 'aneh')->assertSet('isiFilter', '')
            ->set('search', 'Tajati')->set('bidangFilter', 'TAN')
            ->call('resetFilters')
            ->assertSet('search', '')->assertSet('bidangFilter', '');
    }

    public function test_holder_nik_is_masked_everywhere_on_the_page(): void
    {
        $html = $this->page()->html();

        $this->assertStringContainsString('*****678', $html);
        $this->assertStringNotContainsString('12345678', $html);
    }

    public function test_a_date_before_the_first_decree_says_so_and_keeps_the_time_control(): void
    {
        $this->page()
            ->set('date', '2026-08-31')
            ->assertSee('Struktur belum tersedia untuk tanggal ini')
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertDontSee('Kepala Bagian');
    }

    public function test_without_any_applied_decree_the_page_explains_there_is_no_version_yet(): void
    {
        StructureDecree::query()->delete();

        $this->page()->assertSee('Belum ada SK yang diterapkan');
    }

    public function test_people_who_were_not_yet_in_office_are_hidden_on_earlier_dates(): void
    {
        $this->page()
            ->set('date', '2026-09-01')
            ->assertSee('Dewi Lestari')
            ->set('date', '2026-08-31')
            ->assertDontSee('Dewi Lestari');
    }

    public function test_a_structure_without_career_history_says_so_instead_of_looking_broken(): void
    {
        JobHistory::query()->forceDelete();

        $this->page()->assertSee('Belum ada riwayat jabatan karyawan pada tanggal ini');
    }

    public function test_employees_outside_the_structure_are_listed_with_masked_nik_and_never_hidden(): void
    {
        $this->employeeWithoutHistory('87654321', 'Budi Penampung');
        $this->placement('87654321', 'Bagian Tanaman', 'Jabatan Penampungan', '2026-09-01');

        $html = $this->page()
            ->assertSee('tidak dihitung pada kolom Terisi')
            ->assertSee('Lihat daftar')
            ->assertSee('Budi Penampung')
            ->assertSee('Jabatan Penampungan')
            ->assertSee('modul Mutasi')
            ->html();

        $this->assertStringContainsString('*****321', $html);
        $this->assertStringNotContainsString('87654321', $html);
    }

    public function test_concurrent_assignments_are_informational_not_a_mismatch_warning(): void
    {
        $this->employeeWithoutHistory('87654323', 'Dedi Rangkap');
        $this->placement('87654323', 'Bagian Tanaman', 'Asisten Lain', '2026-09-01', null, ['status_penugasan' => 'Plt']);

        $html = $this->page()
            ->assertSee('1 penugasan rangkap (Plt/Pj)')
            ->assertSee('bukan kesalahan data')
            ->assertSee('Dedi Rangkap')
            ->assertDontSee('tidak cocok dengan struktur SK')
            ->html();

        $this->assertStringNotContainsString('87654323', $html);
    }

    public function test_outside_holders_show_a_cause_and_a_correction_hint(): void
    {
        $this->employeeWithoutHistory('87654322', 'Citra Terpotong');
        $this->placement('87654322', 'Bagian Tanaman', 'Kepala', '2026-09-01');

        $this->page()
            ->assertSee('Penyebab')
            ->assertSee('Nama jabatan berbeda dari SK')
            ->assertSee('Kepala Bagian');
    }

    public function test_the_comparison_control_is_disabled_while_only_one_version_exists(): void
    {
        $html = $this->page()->html();
        $this->assertMatchesRegularExpression('/<select[^>]*id="compare"[^>]*disabled/', $html);

        $this->secondVersion();
        $html = $this->page()->html();
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*id="compare"[^>]*disabled/', $html);
    }

    public function test_invalid_dates_fall_back_to_today(): void
    {
        $this->page()
            ->set('date', 'bukan-tanggal')
            ->assertSet('date', '2026-11-17')
            ->set('date', '2026-02-31')
            ->assertSet('date', '2026-11-17');
    }

    public function test_pagination_follows_the_shared_per_page_options(): void
    {
        $component = $this->page()->set('perPage', 999);

        $this->assertContains($component->get('perPage'), config('hris.pagination.options'));
    }

    public function test_comparison_offers_the_current_structure_and_every_other_version(): void
    {
        $second = $this->secondVersion();

        $component = $this->page()
            ->assertDontSee('Selisih dengan')
            ->set('compare', 'current')
            ->assertSee('Selisih dengan struktur sekarang');

        $options = $component->instance()->compareOptions;
        $this->assertSame(['current', (string) $this->first->id], array_column($options, 'value'), 'versi yang sedang dilihat tidak ditawarkan sebagai pembanding');
        $this->assertSame($second->id, $component->instance()->report['decree']->id);
    }

    public function test_comparing_two_versions_lists_what_changed_between_them(): void
    {
        $second = $this->secondVersion();

        $this->page()
            ->set('version', (string) $this->first->id)
            ->set('compare', (string) $second->id)
            ->assertSee('Selisih dengan DPSB/SKPTS/400/XI/2026')
            ->assertSee('Bagian Baru');
    }

    public function test_an_invalid_comparison_choice_is_dropped(): void
    {
        $this->page()->set('compare', 'aneh')->assertSet('compare', '')->set('compare', (string) $this->first->id)->assertSet('compare', '', 'sebuah versi tidak dibandingkan dengan dirinya sendiri');
    }

    public function test_the_page_has_no_second_tab_row_and_points_to_the_employee_profile_for_placement_history(): void
    {
        $html = $this->page()->html();

        $this->assertStringNotContainsString('aria-label="Tampilan Versi Struktur"', $html, 'tidak ada baris tab ketiga di dalam halaman');
        $this->assertStringNotContainsString('Riwayat Jabatan Karyawan', $html);
        $this->assertStringContainsString('Riwayat Karir', $html, 'petunjuk ke profil karyawan');
        $this->assertStringContainsString(route('admin.employees.index'), $html);
    }

    public function test_export_downloads_a_workbook_and_is_audited_without_personal_data(): void
    {
        $this->page()->call('export')->assertFileDownloaded();

        $log = MasterAuditLog::where('action', 'structure_as_of.export')->latest('id')->first();
        $this->assertNotNull($log);
        // Kunci memakai istilah bisnis yang sama dengan catatan audit lain, agar Riwayat Perubahan menampilkannya berlabel.
        $this->assertSame('DPSB/SKPTS/352/IX/2026', $log->new_values['nomor_sk']);
        $this->assertSame('2026-11-17', $log->new_values['posisi_per']);
        $this->assertSame(3, $log->new_values['jumlah_baris']);
        $this->assertStringNotContainsString('12345678', json_encode($log->new_values));

        $this->assertSame('Versi struktur diunduh', MasterAuditLog::actionLabel('structure_as_of.export'));
        $this->assertSame('Versi Struktur', MasterAuditLog::ENTITY_LABELS['structure_as_of']);
        foreach (array_keys($log->new_values) as $key) {
            $this->assertArrayHasKey($key, MasterAuditLog::FIELD_LABELS, "kunci '{$key}' harus punya label bisnis");
        }

        $this->actingAs($this->admin)->get(route('admin.master.riwayat.index'))->assertOk()
            ->assertSee('Versi struktur diunduh')
            ->assertDontSee('structure_as_of.export');
    }

    public function test_page_never_exposes_internal_document_names(): void
    {
        $html = $this->page()->html();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $internal) {
            $this->assertStringNotContainsString($internal, $html);
        }
    }

    public function test_sk_detail_links_to_the_structure_after_that_decree(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.sk.show', $this->first))
            ->assertOk()
            ->assertSee('Lihat struktur setelah SK ini')
            ->assertSee(route('admin.master.versi.index', ['date' => '2026-09-01']), false);
    }
}
