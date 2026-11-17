<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\StructureVersionsPage;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Layar "Versi Struktur" (DOMAIN.md §3.7): baca-saja; daftar versi struktur per SK, lalu struktur dan pemangku jabatan
 * pada versi atau tanggal yang dipilih.
 */
class StructureVersionsPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StructureDecree $first;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 08:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->first = $this->decree('DPSB/SKPTS/352/IX/2026', '2026-09-01', ['units' => [
            'Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'Regional Office', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Bagian' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                'Asisten Pemetaan' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 2],
            ]],
            'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'urutan' => 2, 'is_active' => true, 'seats' => [
                'Manajer' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
        ]]);

        Employee::factory()->create(['nik' => '12345678', 'nama' => 'Dewi Lestari']);
        JobHistory::withTrashed()->where('employee_nik', '12345678')->forceDelete();
        JobHistory::create(['employee_nik' => '12345678', 'jabatan' => 'Kepala Bagian', 'unit_kerja' => 'Bagian Tanaman', 'tmt_awal' => '2026-09-01', 'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI]);
    }

    private function decree(string $number, string $effective, array $snapshot): StructureDecree
    {
        return StructureDecree::create([
            'sk_number' => $number,
            'title' => 'Perubahan Struktur Organisasi Region Office',
            'sk_date' => $effective,
            'effective_date' => $effective,
            'status' => StructureDecree::STATUS_APPLIED,
            'source_path' => 'decrees/'.md5($number).'.xlsx',
            'source_sha256' => str_repeat('a', 64),
            'applied_at' => now(),
            'snapshot' => $snapshot,
        ]);
    }

    /** SK kedua (1 Nov 2026) yang menggantikan struktur dengan satu unit baru. */
    private function secondVersion(): StructureDecree
    {
        return $this->decree('DPSB/SKPTS/400/XI/2026', '2026-11-01', ['units' => [
            'Bagian Baru' => ['kode' => 'Bagian', 'wilayah' => 'Regional Office', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Baru' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
        ]]);
    }

    public function test_page_is_for_admins_only_including_livewire_requests(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.sk.versions'))->assertForbidden();
        Livewire::actingAs($user)->test(StructureVersionsPage::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.sk.versions'))->assertOk();
    }

    public function test_route_does_not_collide_with_the_decree_detail_route(): void
    {
        $this->actingAs($this->admin)->get('/admin/master/sk/versi')->assertOk()->assertSee('Versi Struktur');
    }

    public function test_page_explains_its_purpose_up_front(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSee('Setiap SK yang diterapkan menambah satu versi struktur')
            ->assertSee('menelusuri struktur dan pemangku jabatan di masa lalu');
    }

    public function test_a_single_version_is_listed_as_current_with_a_hint_about_the_next_one(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertSee('Berlaku sekarang')
            ->assertSee('Baru ada satu versi')
            ->assertSee('Catat SK dari Holding');
    }

    public function test_versions_are_listed_newest_first_with_periods_and_totals(): void
    {
        $this->secondVersion();

        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSeeInOrder(['DPSB/SKPTS/400/XI/2026', 'Berlaku sekarang', 'DPSB/SKPTS/352/IX/2026'])
            ->assertSee('01 Sep 2026 – 31 Okt 2026')
            ->assertSee('01 Nov 2026 – sekarang')
            ->assertDontSee('Baru ada satu versi');
    }

    public function test_selecting_a_past_version_opens_its_first_day_and_its_structure(): void
    {
        $this->secondVersion();

        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSet('date', '2026-11-17')
            ->assertSee('Bagian Baru')
            ->assertDontSee('Bagian Tanaman')
            ->call('selectVersion', $this->first->id)
            ->assertSet('date', '2026-09-01')
            ->assertSee('Bagian Tanaman')
            ->assertDontSee('Bagian Baru');
    }

    public function test_selecting_the_current_version_opens_today_and_unknown_versions_are_not_found(): void
    {
        $second = $this->secondVersion();

        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->set('date', '2026-09-01')
            ->call('selectVersion', $second->id)
            ->assertSet('date', '2026-11-17')
            ->call('selectVersion', 999999)
            ->assertNotFound();
    }

    public function test_summary_tiles_show_units_jabatan_total_quota_and_filled(): void
    {
        $component = Livewire::actingAs($this->admin)->test(StructureVersionsPage::class);

        $component->assertSeeInOrder(['Unit', '2', 'Jabatan', '3', 'Total Kuota', '4', 'Terisi', '1']);
        $this->assertSame(['units' => 2, 'seats' => 3, 'kuota' => 4, 'filled' => 1], $component->instance()->report['totals']);
    }

    public function test_table_is_per_unit_with_seats_and_holders_inside_each_unit(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSeeInOrder(['Bagian Tanaman', 'Kebun Tajati'])
            ->assertSee('Kepala Bagian')
            ->assertSee('Asisten Pemetaan')
            ->assertSee('Manajer')
            ->assertSee('Dewi Lestari');
    }

    public function test_holder_nik_is_masked_everywhere_on_the_page(): void
    {
        $html = Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)->html();

        $this->assertStringContainsString('*****678', $html);
        $this->assertStringNotContainsString('12345678', $html);
    }

    public function test_a_date_before_the_first_decree_says_so_but_keeps_the_version_list(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->set('date', '2026-08-31')
            ->assertSee('Struktur belum tersedia untuk tanggal ini')
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertDontSee('Kepala Bagian');
    }

    public function test_without_any_applied_decree_the_page_explains_there_is_no_version_yet(): void
    {
        StructureDecree::query()->delete();

        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSee('Belum ada SK yang diterapkan')
            ->assertDontSee('Berlaku sekarang');
    }

    public function test_people_who_were_not_yet_in_office_are_hidden_on_earlier_dates(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->set('date', '2026-09-01')
            ->assertSee('Dewi Lestari')
            ->set('date', '2026-08-31')
            ->assertDontSee('Dewi Lestari');
    }

    public function test_a_structure_without_career_history_says_so_instead_of_looking_broken(): void
    {
        JobHistory::query()->forceDelete();

        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertSee('Belum ada riwayat jabatan karyawan pada tanggal ini');
    }

    public function test_invalid_dates_fall_back_to_today(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->set('date', 'bukan-tanggal')
            ->assertSet('date', '2026-11-17')
            ->set('date', '2026-02-31')
            ->assertSet('date', '2026-11-17');
    }

    public function test_search_matches_unit_wilayah_or_jabatan_and_keeps_whole_units(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->set('search', 'Tajati')
            ->assertSee('Kebun Tajati')
            ->assertDontSee('Bagian Tanaman')
            ->set('search', 'pemetaan')
            ->assertSee('Bagian Tanaman')
            ->assertDontSee('Kebun Tajati')
            ->set('search', 'Kalimantan Timur')
            ->assertSee('Kebun Tajati')
            ->assertDontSee('Bagian Tanaman');
    }

    public function test_pagination_follows_the_shared_per_page_options(): void
    {
        $component = Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)->set('perPage', 999);

        $this->assertContains($component->get('perPage'), config('hris.pagination.options'));
    }

    public function test_comparison_with_the_current_structure_is_summarised_on_demand(): void
    {
        // Struktur sekarang (basis data uji) kosong, sedangkan versi SK memiliki 3 jabatan.
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->assertDontSee('Selisih dengan struktur sekarang')
            ->set('compare', true)
            ->assertSee('Selisih dengan struktur sekarang');
    }

    public function test_export_downloads_a_workbook_and_is_audited_without_personal_data(): void
    {
        Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)
            ->call('export')
            ->assertFileDownloaded();

        $log = MasterAuditLog::where('action', 'structure_as_of.export')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('DPSB/SKPTS/352/IX/2026', $log->new_values['sk_number']);
        $this->assertSame('2026-11-17', $log->new_values['date']);
        $this->assertSame(3, $log->new_values['rows']);
        $this->assertStringNotContainsString('12345678', json_encode($log->new_values));
    }

    public function test_page_never_exposes_internal_document_names(): void
    {
        $html = Livewire::actingAs($this->admin)->test(StructureVersionsPage::class)->html();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $internal) {
            $this->assertStringNotContainsString($internal, $html);
        }
    }

    public function test_sk_detail_links_to_the_structure_after_that_decree(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.sk.show', $this->first))
            ->assertOk()
            ->assertSee('Lihat struktur setelah SK ini')
            ->assertSee(route('admin.master.sk.versions', ['date' => '2026-09-01']), false);
    }
}
