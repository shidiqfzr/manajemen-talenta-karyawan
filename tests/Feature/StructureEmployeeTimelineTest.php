<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\StructureEmployeeTimeline;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\StructureDecree;
use App\Models\User;
use App\Services\Structure\EmployeePositionTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tab "Riwayat Jabatan Karyawan" pada Versi Struktur: cari karyawan, lalu lihat penempatannya terhadap versi struktur.
 * NIK tidak pernah tampil atau tersimpan utuh pada halaman; pilihan diwakili token terenkripsi.
 */
class StructureEmployeeTimelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-17 08:00:00');
        $this->admin = User::factory()->create(['role' => 'admin']);

        StructureDecree::create([
            'sk_number' => 'DPSB/SKPTS/352/IX/2026', 'title' => 'Perubahan Struktur', 'sk_date' => '2026-09-01', 'effective_date' => '2026-09-01',
            'status' => StructureDecree::STATUS_APPLIED, 'source_path' => 'decrees/352.xlsx', 'source_sha256' => str_repeat('a', 64), 'applied_at' => now(),
            'snapshot' => ['units' => ['Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'W', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Bagian' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]]]],
        ]);

        Employee::factory()->create(['nik' => '12345678', 'nama' => 'Dewi Lestari', 'jabatan' => 'Kepala Bagian', 'unit_kerja' => 'Bagian Tanaman']);
        JobHistory::withTrashed()->where('employee_nik', '12345678')->forceDelete();
        JobHistory::create(['employee_nik' => '12345678', 'jabatan' => 'Staf Lama', 'unit_kerja' => 'Bagian Lama', 'tmt_awal' => '2026-01-01', 'tmt_akhir' => '2026-08-31', 'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI, 'nomor_sk' => 'SK/MUTASI/1', 'jenis_mutasi' => 'ROTASI']);
        JobHistory::create(['employee_nik' => '12345678', 'jabatan' => 'Kepala Bagian', 'unit_kerja' => 'Bagian Tanaman', 'tmt_awal' => '2026-09-01', 'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI, 'status_penugasan' => 'Plt']);
    }

    private function token(): string
    {
        return app(EmployeePositionTimelineService::class)->search('Dewi')[0]['token'];
    }

    public function test_component_is_for_admins_only(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($user)->test(StructureEmployeeTimeline::class)->assertForbidden();
    }

    public function test_it_asks_for_at_least_three_characters_before_searching(): void
    {
        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->assertSee('Cari karyawan')
            ->assertSee('minimal 3 karakter')
            ->set('term', 'de')
            ->assertSee('minimal 3 karakter')
            ->assertDontSee('Dewi Lestari');
    }

    public function test_search_lists_matches_with_masked_nik_and_current_position(): void
    {
        $html = Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->set('term', 'lestari')
            ->assertSee('Dewi Lestari')
            ->assertSee('Kepala Bagian')
            ->html();

        $this->assertStringContainsString('*****678', $html);
        $this->assertStringNotContainsString('12345678', $html);
    }

    public function test_search_without_a_match_says_so(): void
    {
        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->set('term', 'tidak ada orang ini')
            ->assertSee('Tidak ada karyawan yang cocok');
    }

    public function test_search_input_is_debounced_like_every_other_search(): void
    {
        $html = Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)->html();

        $this->assertStringContainsString('debounce.300ms', $html);
    }

    public function test_selecting_an_employee_shows_the_timeline_against_the_structure_versions(): void
    {
        $component = Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->set('term', 'dewi')
            ->call('select', $this->token())
            ->assertSee('Dewi Lestari')
            ->assertSeeInOrder(['Kepala Bagian', 'Staf Lama'])
            ->assertSee('Plt')
            ->assertSee('DPSB/SKPTS/352/IX/2026')
            ->assertSee('SK/MUTASI/1')
            ->assertSee('Sebelum SK struktur pertama')
            ->assertSee('sekarang');

        $html = $component->html();
        $this->assertStringNotContainsString('12345678', $html, 'NIK tidak boleh muncul pada HTML maupun snapshot Livewire');
    }

    public function test_a_placement_outside_the_structure_version_in_force_is_flagged(): void
    {
        JobHistory::create(['employee_nik' => '12345678', 'jabatan' => 'Jabatan Penampungan', 'unit_kerja' => 'Bagian Tanaman', 'tmt_awal' => '2026-10-01', 'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI]);

        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->call('select', $this->token())
            ->assertSee('Tidak tercantum pada struktur SK');
    }

    public function test_a_tampered_token_shows_not_found_and_no_timeline(): void
    {
        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->call('select', 'bukan-token')
            ->assertSee('Karyawan tidak ditemukan')
            ->assertDontSee('Staf Lama');
    }

    public function test_clearing_returns_to_the_search(): void
    {
        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->call('select', $this->token())
            ->assertSee('Staf Lama')
            ->call('clear')
            ->assertSet('selected', '')
            ->assertDontSee('Staf Lama')
            ->assertSee('Cari karyawan');
    }

    public function test_an_employee_without_history_says_so(): void
    {
        JobHistory::query()->forceDelete();

        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->call('select', $this->token())
            ->assertSee('Belum ada riwayat jabatan');
    }

    public function test_the_search_term_is_capped(): void
    {
        Livewire::actingAs($this->admin)->test(StructureEmployeeTimeline::class)
            ->set('term', str_repeat('a', 300))
            ->assertSet('term', str_repeat('a', 100));
    }
}
