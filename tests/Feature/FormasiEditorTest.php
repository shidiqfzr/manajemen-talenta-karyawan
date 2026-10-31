<?php

namespace Tests\Feature;

use App\Livewire\Admin\Master\FormasiEditor;
use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\Formasi\MasterFormasiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FormasiEditorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->unit = Unit::create(['nama' => 'Kebun Editor Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
    }

    private function position(string $nama, string $level = 'Karpim', ?string $rm = 'RM-3', bool $active = true): Position
    {
        return Position::create(['nama' => $nama, 'level' => $level, 'rm_level' => $rm, 'is_active' => $active]);
    }

    private function seat(Position $position, int $kuota = 2, string $bidang = 'TAN'): int
    {
        return DB::table('position_unit')->insertGetId([
            'unit_id' => $this->unit->id,
            'position_id' => $position->id,
            'kuota' => $kuota,
            'bidang' => $bidang,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function editor()
    {
        return Livewire::actingAs($this->admin)->test(FormasiEditor::class)->call('selectUnit', $this->unit->id);
    }

    public function test_non_admin_cannot_open_the_formasi_page_or_the_component(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('admin.master.formasi.index'))->assertForbidden();
        Livewire::actingAs($user)->test(FormasiEditor::class)->assertForbidden();
    }

    public function test_page_renders_for_admin_and_lists_the_seats_of_the_selected_unit(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.formasi.index'))->assertOk()->assertSee('Formasi');

        $this->seat($this->position('Asisten Afdeling Uji', 'Karpim', 'RM-3'), 2, 'TAN');

        $this->editor()
            ->assertSee('Asisten Afdeling Uji')
            ->assertSee('RM-3')
            ->assertSee('TAN');
    }

    public function test_over_quota_seat_shows_the_badge(): void
    {
        $position = $this->position('Asisten Penuh');
        $this->seat($position, 1);
        Employee::factory()->count(2)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama]);

        $this->editor()->assertSee('Melebihi kuota');
    }

    public function test_add_seat_flow_previews_then_confirms_and_audits(): void
    {
        $position = $this->position('Asisten Baru Uji');

        $this->editor()
            ->call('startAddSeat', $position->id, 2, 'TEK')
            ->assertSet('pendingOperation', 'seat.add')
            ->assertSee('Tambah Kursi')
            ->call('confirm')
            ->assertSet('pendingOperation', null);

        $this->assertDatabaseHas('position_unit', ['unit_id' => $this->unit->id, 'position_id' => $position->id, 'kuota' => 2, 'bidang' => 'TEK']);
        $this->assertSame(['seat.added'], MasterAuditLog::pluck('action')->all());
    }

    public function test_remove_seat_with_employees_shows_blockers_and_does_not_remove(): void
    {
        $position = $this->position('Asisten Berpenghuni');
        $seatId = $this->seat($position);
        Employee::factory()->count(2)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama]);

        $this->editor()
            ->call('startRemoveSeat', $seatId)
            ->assertSee('2 pegawai')
            ->assertSee('Mutasi')
            ->set('reason', 'Kursi dihapus karena restrukturisasi')
            ->call('confirm')
            ->assertHasErrors('confirm');

        $this->assertDatabaseHas('position_unit', ['id' => $seatId]);
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_remove_seat_requires_a_reason_and_shows_a_message_when_missing(): void
    {
        $seatId = $this->seat($this->position('Asisten Kosong'));

        $this->editor()
            ->call('startRemoveSeat', $seatId)
            ->call('confirm')
            ->assertHasErrors('confirm')
            ->assertSee('Alasan perubahan wajib diisi');

        $this->assertDatabaseHas('position_unit', ['id' => $seatId]);

        $this->editor()
            ->call('startRemoveSeat', $seatId)
            ->set('reason', 'Kursi dihapus karena restrukturisasi')
            ->call('confirm')
            ->assertHasNoErrors()
            ->assertSet('pendingOperation', null);

        $this->assertDatabaseMissing('position_unit', ['id' => $seatId]);
    }

    public function test_editing_kuota_below_filled_shows_the_over_quota_warning(): void
    {
        $position = $this->position('Asisten Afdeling Tiga');
        $seatId = $this->seat($position, 3);
        Employee::factory()->count(3)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama]);

        $this->editor()
            ->call('startUpdateSeat', $seatId, 1, 'TAN')
            ->assertSee('melebihi kuota')
            ->call('confirm')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('position_unit', ['id' => $seatId, 'kuota' => 1]);
    }

    public function test_add_dropdown_only_offers_active_karpim_jabatan_not_yet_in_the_unit(): void
    {
        $this->seat($this->position('Sudah Berkursi'));
        $this->position('Calon Kursi');
        $this->position('Mandor Karpel', 'Karpel', null);
        $this->position('Jabatan Nonaktif', 'Karpim', 'RM-3', false);

        $names = $this->editor()->instance()->addablePositions->pluck('nama')->all();

        $this->assertSame(['Calon Kursi'], $names);
    }

    public function test_cleared_kuota_input_gives_a_rule_message_not_a_server_error(): void
    {
        $position = $this->position('Asisten Kuota Kosong');

        $this->editor()
            ->call('startAddSeat', $position->id, '', 'TAN')
            ->call('confirm')
            ->assertHasErrors('confirm')
            ->assertSee('Kuota harus berupa bilangan bulat minimal 1');

        $this->assertDatabaseMissing('position_unit', ['position_id' => $position->id]);
    }

    public function test_a_seat_of_another_unit_cannot_be_started_from_this_unit(): void
    {
        $other = Unit::create(['nama' => 'Kebun Lain', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 2]);
        $position = $this->position('Asisten Milik Unit Lain');
        $foreignSeat = DB::table('position_unit')->insertGetId([
            'unit_id' => $other->id, 'position_id' => $position->id, 'kuota' => 1, 'bidang' => 'TAN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->editor()->call('startRemoveSeat', $foreignSeat)->assertSet('pendingOperation', null);
        $this->editor()->call('startUpdateSeat', $foreignSeat, 5, 'TAN')->assertSet('pendingOperation', null);
    }

    public function test_recent_changes_for_the_selected_unit_are_listed(): void
    {
        $position = $this->position('Asisten Riwayat');
        app(MasterFormasiService::class)->addSeat($this->unit, $position, 1, 'TAN', $this->admin);

        $this->editor()->assertSee('Kebun Editor Uji — Asisten Riwayat');
    }
}
