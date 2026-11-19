<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\PoolPositions;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SUT: App\Livewire\Admin\Master\PoolPositions (tab "Jabatan Penampungan": Advisor and Diperbantukan, outside the formasi).
 */
class PoolPositionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unit $kalbar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->kalbar = Unit::create(['nama' => 'Group Manager Kalbar', 'kode' => 'Group', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => true, 'urutan' => 1]);
    }

    private function pool(string $nama = 'Advisor RM-2', bool $diperbantukan = false, string $rm = 'RM-2'): Position
    {
        return Position::create(['nama' => $nama, 'level' => 'Karpim', 'rm_level' => $rm, 'bidang' => 'UMU', 'is_active' => true, 'is_overflow' => true, 'is_diperban' => $diperbantukan]);
    }

    private function open(Position $position, ?Unit $unit = null): void
    {
        DB::table('position_unit')->insert(['unit_id' => ($unit ?? $this->kalbar)->id, 'position_id' => $position->id, 'kuota' => 0, 'bidang' => 'UMU', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function holders(Position $position, int $count, ?Unit $unit = null): void
    {
        Employee::factory()->count($count)->create(['unit_kerja' => ($unit ?? $this->kalbar)->nama, 'jabatan' => $position->nama]);
    }

    private function screen()
    {
        return Livewire::actingAs($this->admin)->test(PoolPositions::class);
    }

    public function test_only_admins_can_open_the_tab(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.penampungan.index'))->assertForbidden();
        Livewire::actingAs($user)->test(PoolPositions::class)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.master.penampungan.index'))->assertOk()->assertSee('Jabatan Penampungan');
    }

    public function test_catalog_lists_pool_jabatan_with_unit_and_employee_counts(): void
    {
        $advisor = $this->pool('Advisor RM-2');
        $diperbantukan = $this->pool('Manajer Diperbantukan', true, 'RM-1');
        Position::create(['nama' => 'Manajer Biasa', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]);
        $this->open($advisor);
        $this->holders($advisor, 2);

        $component = $this->screen()
            ->assertSee('Advisor RM-2')
            ->assertSee('Manajer Diperbantukan')
            ->assertDontSee('Manajer Biasa');

        $this->assertSame(['jabatan' => 2, 'units' => 1, 'holders' => 2], $component->instance()->stats);
        $this->assertNotNull($diperbantukan);
    }

    public function test_create_flow_creates_the_jabatan_audits_it_and_closes_the_dialog(): void
    {
        $this->screen()
            ->call('openCreate')
            ->assertSet('creating', true)
            ->set('newNama', 'Asisten Diperbantukan')
            ->set('newJenis', 'diperbantukan')
            ->set('newRm', 'RM-3')
            ->call('saveCreate')
            ->assertHasNoErrors()
            ->assertSet('creating', false)
            ->assertSee('Asisten Diperbantukan');

        $position = Position::where('nama', 'Asisten Diperbantukan')->firstOrFail();
        $this->assertTrue($position->is_overflow && $position->is_diperban);
        $this->assertSame('RM-3', $position->rm_level);
        $this->assertSame(1, MasterAuditLog::where('action', 'position.created')->where('entity_label', 'Asisten Diperbantukan')->count());
    }

    public function test_create_flow_shows_the_rule_message_and_creates_nothing_for_a_duplicate(): void
    {
        $this->pool('Advisor RM-2');

        $this->screen()
            ->call('openCreate')
            ->set('newNama', 'Advisor RM-2')
            ->set('newJenis', 'advisor')
            ->set('newRm', 'RM-2')
            ->call('saveCreate')
            ->assertHasErrors('create')
            ->assertSet('creating', true)
            ->assertSee('sudah digunakan');

        $this->assertSame(1, Position::count());
    }

    public function test_open_flow_attaches_the_jabatan_to_the_chosen_unit_only_once(): void
    {
        $advisor = $this->pool('Advisor RM-2');

        $component = $this->screen()
            ->call('startOpen', $advisor->id)
            ->assertSet('openingPositionId', $advisor->id)
            ->set('openUnitId', $this->kalbar->id)
            ->call('saveOpen')
            ->assertHasNoErrors()
            ->assertSet('openingPositionId', null);

        $this->assertSame(1, DB::table('position_unit')->where('unit_id', $this->kalbar->id)->where('position_id', $advisor->id)->where('kuota', 0)->count());

        $component->call('startOpen', $advisor->id)->set('openUnitId', $this->kalbar->id)->call('saveOpen')->assertHasErrors('open');
        $this->assertSame(1, DB::table('position_unit')->count());
    }

    public function test_open_flow_refuses_an_ordinary_position(): void
    {
        $ordinary = Position::create(['nama' => 'Manajer Biasa', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]);

        $this->screen()->call('startOpen', $ordinary->id)->assertSet('openingPositionId', null);
    }

    public function test_close_flow_is_blocked_while_employees_hold_the_jabatan_and_needs_a_reason(): void
    {
        $advisor = $this->pool('Advisor RM-2');
        $this->open($advisor);
        $this->holders($advisor, 1);

        $component = $this->screen()
            ->call('startClose', $advisor->id, $this->kalbar->id)
            ->set('closeReason', 'Alasan yang cukup panjang')
            ->call('saveClose')
            ->assertHasErrors('close')
            ->assertSee('1 karyawan');
        $this->assertSame(1, DB::table('position_unit')->count());

        Employee::where('jabatan', $advisor->nama)->delete();
        $component->set('closeReason', 'pendek')->call('saveClose')->assertHasErrors('close');
        $component->set('closeReason', 'Tidak lagi dipakai pada unit ini')->call('saveClose')->assertHasNoErrors()->assertSet('closePositionId', null);

        $this->assertSame(0, DB::table('position_unit')->count());
        $this->assertSame(1, MasterAuditLog::where('action', 'pool.closed')->count());
    }

    public function test_rename_flow_corrects_a_typo_and_needs_a_reason(): void
    {
        $position = $this->pool('Asisten Diperbantukn', true, 'RM-3');

        $component = $this->screen()
            ->call('startRename', $position->id)
            ->assertSet('renamePositionId', $position->id)
            ->assertSet('renameNama', 'Asisten Diperbantukn')
            ->set('renameNama', 'Asisten Diperbantukan')
            ->set('renameReason', 'pendek')
            ->call('saveRename')
            ->assertHasErrors('rename');
        $this->assertSame('Asisten Diperbantukn', $position->refresh()->nama);

        $component->set('renameReason', 'Memperbaiki salah ketik nama')->call('saveRename')->assertHasNoErrors()->assertSet('renamePositionId', null)->assertSee('Asisten Diperbantukan');
        $this->assertSame('Asisten Diperbantukan', $position->refresh()->nama);
        $this->assertSame(1, MasterAuditLog::where('action', 'position.renamed')->count());
    }

    public function test_delete_flow_removes_an_unused_jabatan_and_is_blocked_while_it_is_opened(): void
    {
        $unused = $this->pool('Advisor Keliru RM-4', false, 'RM-4');
        $opened = $this->pool('Advisor RM-2');
        $this->open($opened);

        $this->screen()
            ->call('startDelete', $opened->id)
            ->set('deleteReason', 'Alasan yang cukup panjang')
            ->call('saveDelete')
            ->assertHasErrors('delete')
            ->assertSee('tab Jabatan Penampungan');
        $this->assertNotNull(Position::find($opened->id));

        $this->screen()
            ->call('startDelete', $unused->id)
            ->set('deleteReason', 'Dibuat keliru, belum pernah dipakai')
            ->call('saveDelete')
            ->assertHasNoErrors()
            ->assertSet('deletePositionId', null)
            ->assertDontSeeHtml('wire:key="pool-'.$unused->id.'"');
        $this->assertNull(Position::find($unused->id));
    }

    public function test_dialogs_ignore_ordinary_positions_and_units_that_cannot_open_the_jabatan(): void
    {
        $ordinary = Position::create(['nama' => 'Manajer Biasa', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true]);
        DB::table('position_unit')->insert(['unit_id' => $this->kalbar->id, 'position_id' => $ordinary->id, 'kuota' => 1, 'bidang' => 'TAN', 'created_at' => now(), 'updated_at' => now()]);
        $advisor = $this->pool('Advisor RM-2');
        $inactive = Unit::create(['nama' => 'Unit Tidak Aktif', 'kode' => 'Group', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => false, 'urutan' => 9]);

        $this->screen()
            ->call('startRename', $ordinary->id)->assertSet('renamePositionId', null)
            ->call('startDelete', $ordinary->id)->assertSet('deletePositionId', null)
            ->call('startClose', $ordinary->id, $this->kalbar->id)->assertSet('closePositionId', null)
            ->call('startOpen', $advisor->id, $inactive->id)->assertSet('openingPositionId', $advisor->id)->assertSet('openUnitId', null);
    }

    public function test_an_action_keeps_the_page_the_admin_is_on(): void
    {
        foreach (range(1, 12) as $n) {
            $this->pool(sprintf('Advisor Uji %02d', $n));
        }

        $this->screen()
            ->set('perPage', 10)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->call('openCreate')
            ->set('newNama', 'Advisor Baru')
            ->assertSet('paginators.page', 2);
    }

    public function test_one_request_loads_the_pool_data_once(): void
    {
        $this->pool('Advisor RM-2');
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->screen();

        $loads = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'count(*) as total'))->count();
        DB::disableQueryLog();

        $this->assertSame(1, $loads);
    }

    public function test_per_unit_view_shows_opened_jabatan_and_flags_holders_without_an_opened_unit(): void
    {
        $advisor = $this->pool('Advisor RM-2');
        $other = Unit::create(['nama' => 'Group Manager Kalselteng', 'kode' => 'Group', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'is_active' => true, 'urutan' => 2]);
        $this->open($advisor);
        $this->holders($advisor, 2);
        // Placed before the jabatan was opened in this unit (for example imported earlier): must be visible, not hidden.
        $this->holders($advisor, 1, $other);

        $html = preg_replace('/\s+/', ' ', $this->screen()->call('setView', 'unit')->assertSet('view', 'unit')->html());

        $this->assertStringContainsString('Group Manager Kalbar', $html);
        $this->assertStringContainsString('Group Manager Kalselteng', $html);
        $this->assertStringContainsString('Belum dibuka di unit', $html);
    }

    public function test_search_filters_both_views(): void
    {
        $advisor = $this->pool('Advisor RM-2');
        $this->pool('Manajer Diperbantukan', true, 'RM-1');
        $this->open($advisor);

        $this->screen()->set('search', 'Advisor')->assertSee('Advisor RM-2')->assertDontSee('Manajer Diperbantukan');
        $this->screen()->call('setView', 'unit')->set('search', 'Kalbar')->assertSee('Advisor RM-2');
        $this->screen()->call('setView', 'unit')->set('search', 'tidak-ada')->assertDontSee('Advisor RM-2');
    }

    public function test_unknown_view_and_oversized_search_are_discarded(): void
    {
        $this->screen()
            ->set('view', 'grid')->assertSet('view', 'jabatan')
            ->set('search', '  '.str_repeat('a', 150).'  ')->assertSet('search', str_repeat('a', 100));
    }

    public function test_the_screen_uses_business_wording_only(): void
    {
        $advisor = $this->pool('Advisor RM-2');
        $this->open($advisor);
        $this->holders($advisor, 1);

        $perJabatan = strtolower($this->screen()->html());
        $perUnit = strtolower($this->screen()->call('setView', 'unit')->html());

        foreach ([$perJabatan, $perUnit] as $html) {
            $this->assertStringNotContainsString('kursi', $html);
            $this->assertStringNotContainsString('seat', $html);
            $this->assertStringNotContainsString('domain.md', $html);
            $this->assertStringNotContainsString('pegawai', $html);
        }
    }
}
