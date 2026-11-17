<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\StructureAsOfPage;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\MasterAuditLog;
use App\Models\StructureDecree;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Layar "Struktur per Tanggal" (DOMAIN.md §3.7): baca-saja, versi struktur menurut tanggal, pemangku dari riwayat jabatan.
 */
class StructureAsOfPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 08:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);

        StructureDecree::create([
            'sk_number' => 'DPSB/SKPTS/352/IX/2026',
            'title' => 'Perubahan Struktur Organisasi Region Office',
            'sk_date' => '2026-09-01',
            'effective_date' => '2026-09-01',
            'status' => StructureDecree::STATUS_APPLIED,
            'source_path' => 'decrees/352.xlsx',
            'source_sha256' => str_repeat('a', 64),
            'applied_at' => now(),
            'snapshot' => ['units' => [
                'Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'Regional Office', 'urutan' => 1, 'is_active' => true, 'seats' => [
                    'Kepala Bagian' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                    'Asisten Pemetaan' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 2],
                ]],
                'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'urutan' => 2, 'is_active' => true, 'seats' => [
                    'Manajer' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                ]],
            ]],
        ]);

        Employee::factory()->create(['nik' => '12345678', 'nama' => 'Dewi Lestari']);
        JobHistory::withTrashed()->where('employee_nik', '12345678')->forceDelete();
        JobHistory::create(['employee_nik' => '12345678', 'jabatan' => 'Kepala Bagian', 'unit_kerja' => 'Bagian Tanaman', 'tmt_awal' => '2026-09-01', 'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI]);
    }

    public function test_page_is_for_admins_only_including_livewire_requests(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.sk.struktur'))->assertForbidden();
        Livewire::actingAs($user)->test(StructureAsOfPage::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.sk.struktur'))->assertOk();
    }

    public function test_route_does_not_collide_with_the_decree_detail_route(): void
    {
        $this->actingAs($this->admin)->get('/admin/master/sk/struktur')->assertOk()->assertSee('Struktur per Tanggal');
    }

    public function test_defaults_to_today_and_shows_the_decree_in_force_with_its_seats_and_holders(): void
    {
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->assertSet('date', '2026-11-17')
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertSee('Kepala Bagian')
            ->assertSee('Asisten Pemetaan')
            ->assertSee('Manajer')
            ->assertSee('Dewi Lestari');
    }

    public function test_holder_nik_is_masked_everywhere_on_the_page(): void
    {
        $html = Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)->html();

        $this->assertStringContainsString('*****678', $html);
        $this->assertStringNotContainsString('12345678', $html);
    }

    public function test_a_date_before_the_first_decree_says_the_structure_is_not_available(): void
    {
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->set('date', '2026-08-31')
            ->assertSee('Struktur belum tersedia untuk tanggal ini')
            ->assertDontSee('Kepala Bagian');
    }

    public function test_a_before_date_hides_people_who_were_not_yet_in_office(): void
    {
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->set('date', '2026-09-01')
            ->assertSee('Dewi Lestari')
            ->set('date', '2026-08-31')
            ->assertDontSee('Dewi Lestari');
    }

    public function test_invalid_or_future_garbage_dates_fall_back_to_today(): void
    {
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->set('date', 'bukan-tanggal')
            ->assertSet('date', '2026-11-17')
            ->set('date', '2026-02-31')
            ->assertSet('date', '2026-11-17');
    }

    public function test_search_filters_rows_by_unit_or_jabatan(): void
    {
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->set('search', 'Tajati')
            ->assertSee('Manajer')
            ->assertDontSee('Asisten Pemetaan')
            ->set('search', 'pemetaan')
            ->assertSee('Asisten Pemetaan')
            ->assertDontSee('Manajer');
    }

    public function test_pagination_follows_the_shared_per_page_options(): void
    {
        $component = Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)->set('perPage', 999);

        $this->assertContains($component->get('perPage'), config('hris.pagination.options'));
    }

    public function test_comparison_with_the_current_structure_is_summarised_on_demand(): void
    {
        // Struktur sekarang (basis data uji) kosong, sedangkan versi SK memiliki 3 jabatan: semuanya "tidak ada lagi".
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->assertDontSee('Selisih dengan struktur sekarang')
            ->set('compare', true)
            ->assertSee('Selisih dengan struktur sekarang');
    }

    public function test_export_downloads_a_workbook_and_is_audited_without_personal_data(): void
    {
        Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)
            ->call('export')
            ->assertFileDownloaded();

        $log = MasterAuditLog::where('action', 'structure_as_of.export')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('DPSB/SKPTS/352/IX/2026', $log->new_values['sk_number']);
        $this->assertSame('2026-11-17', $log->new_values['date']);
        $this->assertStringNotContainsString('12345678', json_encode($log->new_values));
    }

    public function test_page_never_exposes_internal_document_names(): void
    {
        $html = Livewire::actingAs($this->admin)->test(StructureAsOfPage::class)->html();

        foreach (['AGENTS.md', 'DOMAIN.md', 'DESIGN.md', 'ENGINEERING.md', 'SECURITY.md', 'SSOT'] as $internal) {
            $this->assertStringNotContainsString($internal, $html);
        }
    }
}
