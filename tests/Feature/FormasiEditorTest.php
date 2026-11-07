<?php

namespace Tests\Feature;

use App\Exports\FormasiExport;
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
use Maatwebsite\Excel\Facades\Excel;
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
        return Livewire::actingAs($this->admin)->test(FormasiEditor::class, ['unitId' => $this->unit->id]);
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
            ->assertSee('Tambah Formasi Jabatan')
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

    public function test_suggestions_come_only_from_same_kind_units_and_skip_pool_inactive_and_seated_jabatan(): void
    {
        $sibling = Unit::create(['nama' => 'Kebun Saudara', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 2]);
        $bagian = Unit::create(['nama' => 'Bagian Lain', 'kode' => 'Bagian', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 3]);
        $attach = fn (Position $p, Unit $u) => DB::table('position_unit')->insert(['unit_id' => $u->id, 'position_id' => $p->id, 'kuota' => 1, 'bidang' => 'TAN', 'created_at' => now(), 'updated_at' => now()]);

        $this->seat($this->position('Sudah Berformasi'));
        $attach($this->position('Asisten Afdeling Contoh'), $sibling);
        $attach($this->position('Kepala Seksi Bagian'), $bagian);
        $attach($this->position('Mandor Karpel', 'Karpel', null), $sibling);
        $attach($this->position('Jabatan Nonaktif', 'Karpim', 'RM-3', false), $sibling);
        $attach(Position::create(['nama' => 'Advisor Pool', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true, 'is_overflow' => true]), $sibling);
        $this->position('Belum Dipakai Unit Mana Pun');

        // Unit uji berkode "Kebun": hanya jabatan unit Kebun lain yang disarankan; "Kepala Seksi Bagian" (unit Bagian) tidak.
        $this->assertSame(['Asisten Afdeling Contoh'], $this->editor()->instance()->addablePositions->pluck('nama')->all());
    }

    public function test_adding_by_name_reuses_an_existing_jabatan_or_creates_a_new_one(): void
    {
        $existing = $this->position('Asisten Sudah Ada', 'Karpim', 'RM-2');

        $editor = $this->editor()
            ->call('startAddJabatan', 'asisten sudah ada', 'RM-3', 'TAN', 2)
            ->assertSet('pendingOperation', 'seat.add')
            ->assertSet('pendingParams.position_id', $existing->id);
        $editor->call('confirm');
        $this->assertDatabaseHas('position_unit', ['unit_id' => $this->unit->id, 'position_id' => $existing->id, 'kuota' => 2]);
        $this->assertSame('RM-2', $existing->fresh()->rm_level);

        $this->editor()
            ->call('startAddJabatan', 'Asisten Benar Baru', 'RM-3', 'KEU', 1)
            ->assertSet('pendingParams.new_position.nama', 'Asisten Benar Baru')
            ->assertSee('Jabatan baru')
            ->call('confirm')
            ->assertSet('pendingOperation', null);

        $created = Position::where('nama', 'Asisten Benar Baru')->firstOrFail();
        $this->assertSame('RM-3', $created->rm_level);
        $this->assertSame('Karpim', $created->level);
        $this->assertDatabaseHas('position_unit', ['unit_id' => $this->unit->id, 'position_id' => $created->id, 'bidang' => 'KEU']);
        $this->assertDatabaseHas('master_audit_logs', ['action' => 'position.created', 'entity_label' => 'Asisten Benar Baru']);
        $this->assertDatabaseHas('master_audit_logs', ['action' => 'seat.added']);
    }

    public function test_adding_by_name_rejects_pool_duplicates_and_missing_rm_band(): void
    {
        Position::create(['nama' => 'Advisor RM-8', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true, 'is_overflow' => true]);
        $seated = $this->position('Sudah Di Unit');
        $this->seat($seated);

        $this->editor()
            ->call('startAddJabatan', 'Advisor RM-8', 'RM-3', 'UMU', 1)
            ->assertHasErrors('add')->assertSet('pendingOperation', null)
            ->call('startAddJabatan', 'Sudah Di Unit', 'RM-3', 'TAN', 1)
            ->assertHasErrors('add')
            ->call('startAddJabatan', 'Nama Tanpa RM', '', 'TAN', 1)
            ->assertHasErrors('add')
            ->call('startAddJabatan', '  ', 'RM-3', 'TAN', 1)
            ->assertHasErrors('add');

        $this->assertDatabaseMissing('positions', ['nama' => 'Nama Tanpa RM']);
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

    public function test_each_unit_has_its_own_page_and_the_legacy_query_string_redirects_to_it(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.formasi.show', $this->unit))
            ->assertOk()
            ->assertSee('Formasi Jabatan — Kebun Editor Uji')
            ->assertSee('Kembali ke Daftar Unit')
            ->assertSee(route('admin.master.formasi.index'), false);

        $this->actingAs($this->admin)->get(route('admin.master.formasi.index', ['unit' => $this->unit->id]))
            ->assertRedirect(route('admin.master.formasi.show', $this->unit));

        $this->actingAs($this->admin)->get(route('admin.master.formasi.index', ['unit' => 999999]))
            ->assertOk()
            ->assertSee('Cari nama unit kerja');

        $this->actingAs($this->admin)->get(route('admin.master.formasi.show', 999999))->assertNotFound();
    }

    public function test_overview_links_each_unit_to_its_own_page(): void
    {
        $this->actingAs($this->admin)->get(route('admin.master.formasi.index'))
            ->assertSee(route('admin.master.formasi.show', $this->unit), false);
    }

    public function test_add_seat_dialog_opens_closes_and_hands_over_to_the_impact_preview(): void
    {
        $position = $this->position('Asisten Dialog Kursi');

        $this->editor()
            ->assertSet('addingSeat', false)
            ->call('openAddSeat')
            ->assertSet('addingSeat', true)
            ->assertSee('Lanjut')
            ->call('closeAddSeat')
            ->assertSet('addingSeat', false)
            ->call('openAddSeat')
            ->call('startAddSeat', $position->id, 1, 'TAN')
            ->assertSet('addingSeat', false)
            ->assertSet('pendingOperation', 'seat.add');
    }

    public function test_unit_list_searches_filters_and_keeps_inactive_units_visible(): void
    {
        Unit::create(['nama' => 'Kebun Nonaktif Pilihan', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => false, 'urutan' => 9]);

        $list = Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertSee('Kebun Nonaktif Pilihan')
            ->assertSee('Kebun Editor Uji');

        $list->set('unitQuery', 'Nonaktif')->assertSee('Kebun Nonaktif Pilihan')->assertDontSee('Kebun Editor Uji');
        $list->call('resetUnitFilters')->set('statusFilter', 'inactive')->assertSee('Kebun Nonaktif Pilihan')->assertDontSee('Kebun Editor Uji');
        $list->call('resetUnitFilters')->set('wilayahFilter', 'Wilayah Kalimantan Barat')->assertSee('Kebun Editor Uji')->assertDontSee('Kebun Nonaktif Pilihan');
        $list->call('resetUnitFilters')->set('unitQuery', 'tidak ada')->assertSee('Tidak ada unit yang sesuai');

        $list->call('resetUnitFilters')->call('sortBy', 'nama')->call('sortBy', 'nama')->assertSet('sortDirection', 'desc');
        $this->assertSame('Kebun Nonaktif Pilihan', $list->instance()->filteredOverview->first()->nama);
    }

    public function test_seat_table_sorts_by_a_column_and_toggles_direction(): void
    {
        $this->seat($this->position('Asisten Alfa'), 1);
        $this->seat($this->position('Asisten Zulu'), 5);

        $editor = $this->editor()->call('sortBy', 'kuota');
        $this->assertSame(['Asisten Alfa', 'Asisten Zulu'], $editor->instance()->seats->pluck('jabatan')->all());

        $editor->call('sortBy', 'kuota')->assertSet('sortDirection', 'desc');
        $this->assertSame(['Asisten Zulu', 'Asisten Alfa'], $editor->instance()->seats->pluck('jabatan')->all());

        $editor->call('sortBy', 'bukan_kolom')->assertSet('sortField', 'kuota');
    }

    public function test_filled_count_opens_the_occupant_list_without_exposing_nik_text(): void
    {
        $position = $this->position('Asisten Pemegang');
        $seatId = $this->seat($position, 2);
        $employee = Employee::factory()->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama, 'nama' => 'Pegawai Pemegang Uji']);

        $this->editor()
            ->call('viewOccupants', $seatId)
            ->assertSee('Pemegang Jabatan')
            ->assertSee('Pegawai Pemegang Uji')
            ->assertDontSeeText($employee->nik)
            ->call('closeOccupants')
            ->assertDontSee('Pegawai Pemegang Uji');
    }

    public function test_occupant_list_opens_as_a_right_drawer_with_profile_link_and_a_blur_free_overlay(): void
    {
        $position = $this->position('Asisten Drawer', 'Karpim', 'RM-3');
        $seatId = $this->seat($position, 2, 'TAN');
        $employee = Employee::factory()->create([
            'unit_kerja' => $this->unit->nama,
            'jabatan' => $position->nama,
            'nama' => 'Pegawai Drawer Uji',
            'golongan' => 'IVB/06',
            'status_karyawan' => 'Tetap (PKWTT)',
            'tanggal_pensiun' => '2030-02-01',
            'tanggal_mbt' => '2029-02-01',
        ]);

        $html = $this->editor()->call('viewOccupants', $seatId)
            ->assertSee('Pemegang Jabatan')
            ->assertSee('1 Personil')
            ->assertSee('Bidang TAN')
            ->assertSee('IVB/06')
            ->assertSee('Tetap (PKWTT)')
            ->assertSee('01 Feb 2029')
            ->assertSee('01 Feb 2030')
            ->assertDontSee('Lihat Profil')
            ->assertSee('Tutup Panel')
            ->assertDontSeeText($employee->nik)
            ->html();

        $this->assertStringContainsString('inset-y-0 right-0', $html);
        $this->assertStringContainsString('fixed inset-0 bg-surface-950/30 transition-opacity', $html);
        // Nama pegawai adalah tautan profil (tab baru), bukan tombol terpisah.
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin.employees.show', $employee->nik), '/').'"[^>]*target="_blank"[^>]*>\s*Pegawai Drawer Uji/', $html);
    }

    public function test_occupant_drawer_warns_when_the_seat_holds_more_people_than_the_list_limit(): void
    {
        $position = $this->position('Mandor Banyak');
        $seatId = $this->seat($position, 300);
        Employee::factory()->count(FormasiEditor::OCCUPANT_LIMIT + 1)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama]);

        $this->editor()->call('viewOccupants', $seatId)
            ->assertSee('Menampilkan '.FormasiEditor::OCCUPANT_LIMIT.' dari '.(FormasiEditor::OCCUPANT_LIMIT + 1).' pegawai');
    }

    public function test_opening_the_occupant_list_is_recorded_on_the_audit_log_without_personal_data(): void
    {
        $position = $this->position('Asisten Audit', 'Karpim', 'RM-3');
        $seatId = $this->seat($position, 2, 'TAN');
        $employee = Employee::factory()->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama, 'nama' => 'Pegawai Audit Uji']);

        $this->editor()->call('viewOccupants', $seatId)->call('closeOccupants');

        $log = MasterAuditLog::where('action', 'seat_access.view_occupants')->sole();
        $this->assertSame('seat_access', $log->entity_type);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(
            ['unit' => $this->unit->nama, 'jabatan' => 'Asisten Audit', 'rm_level' => 'RM-3', 'bidang' => 'TAN', 'jumlah_baris' => 1],
            $log->new_values
        );
        $this->assertStringNotContainsString($employee->nik, json_encode($log->new_values).$log->entity_label);
        $this->assertStringNotContainsString('Pegawai Audit Uji', json_encode($log->new_values).$log->entity_label);
    }

    public function test_opening_an_empty_or_foreign_seat_is_not_recorded_as_personnel_access(): void
    {
        $emptySeat = $this->seat($this->position('Asisten Tanpa Pemegang'), 1);
        $other = Unit::create(['nama' => 'Kebun Asing Audit', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 3]);
        $foreign = DB::table('position_unit')->insertGetId(['unit_id' => $other->id, 'position_id' => $this->position('Asisten Asing Audit')->id, 'kuota' => 1, 'bidang' => 'TAN', 'created_at' => now(), 'updated_at' => now()]);

        $this->editor()->call('viewOccupants', $emptySeat)->call('viewOccupants', $foreign);

        $this->assertSame(0, MasterAuditLog::where('entity_type', 'seat_access')->count());
    }

    public function test_occupant_drawer_shows_an_empty_state_for_a_seat_without_holders(): void
    {
        $seatId = $this->seat($this->position('Asisten Kosong'), 1);

        $this->editor()->call('viewOccupants', $seatId)
            ->assertSee('Belum ada pegawai pada jabatan ini')
            ->assertSee('0 Personil');
    }

    public function test_occupants_of_a_seat_in_another_unit_cannot_be_opened(): void
    {
        $other = Unit::create(['nama' => 'Kebun Lain', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 2]);
        $position = $this->position('Asisten Asing');
        $foreign = DB::table('position_unit')->insertGetId(['unit_id' => $other->id, 'position_id' => $position->id, 'kuota' => 1, 'bidang' => 'TAN', 'created_at' => now(), 'updated_at' => now()]);

        $this->editor()->call('viewOccupants', $foreign)->assertSet('occupantSeatId', null);
    }

    public function test_edit_dialog_hands_over_to_the_impact_preview(): void
    {
        $seatId = $this->seat($this->position('Asisten Ubah'), 2);

        $this->editor()
            ->call('openEditSeat', $seatId)
            ->assertSee('Ubah Kuota & Bidang', false)
            ->call('startUpdateSeat', $seatId, 3, 'TEK')
            ->assertSet('editSeatId', null)
            ->assertSet('pendingOperation', 'seat.update');
    }

    public function test_rm_band_chip_follows_the_category_colours_and_unit_overview_lists_every_unit(): void
    {
        $this->seat($this->position('Kepala Warna', 'Karpim', 'RM-1'), 1);

        $this->editor()->assertSeeHtml('bg-orange-50 text-orange-800 border-orange-200');
        $this->assertSame('bg-surface-100 text-surface-700 border-surface-200', Position::rmTheme('RM-4'));

        Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertSee('Cari nama unit kerja')
            ->assertSee('Kebun Editor Uji');
    }

    public function test_overview_shows_cross_unit_kpis_and_filled_counts_before_a_unit_is_chosen(): void
    {
        $position = $this->position('Asisten Ringkasan');
        $this->seat($position, 1);
        Employee::factory()->count(2)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $position->nama]);

        $component = Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertSee('Formasi Jabatan')
            ->assertSee('Total Kuota')
            ->assertSee('1 formasi melebihi kuota');

        $this->assertSame(['units' => 1, 'seats' => 1, 'kuota' => 1, 'filled' => 2, 'over' => 1], $component->instance()->overviewStats);
    }

    public function test_add_dialog_is_one_form_with_name_suggestions_and_no_separate_create_button(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.master.formasi.show', $this->unit))->assertOk();
        $page->assertSee('Tambah Jabatan')->assertDontSee('Buat Jabatan Baru')->assertDontSee('Tambah Formasi Jabatan');

        $sibling = Unit::create(['nama' => 'Kebun Saudara', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 2]);
        $suggested = $this->position('Asisten Saran');
        DB::table('position_unit')->insert(['unit_id' => $sibling->id, 'position_id' => $suggested->id, 'kuota' => 1, 'bidang' => 'TAN', 'created_at' => now(), 'updated_at' => now()]);

        $this->editor()
            ->call('openAddSeat')
            ->assertSee('Tambah Jabatan ke Formasi')
            ->assertSeeHtml('role="combobox"')
            ->assertDontSeeHtml('<datalist')
            ->assertSee('Asisten Saran')
            ->assertSee('saran akan muncul')
            ->assertDontSee('Buat jabatan baru')
            ->assertDontSee('opsi tersedia');
    }

    public function test_seat_table_searches_and_filters_by_rm_band_bidang_and_status(): void
    {
        $this->seat($this->position('Asisten Alfa', 'Karpim', 'RM-3'), 2, 'TAN');
        $this->seat($this->position('Kepala Bravo', 'Karpim', 'RM-1'), 1, 'KEU');
        $nonaktif = $this->position('Asisten Charlie', 'Karpim', 'RM-3', false);
        $this->seat($nonaktif, 1, 'TAN');
        $full = $this->position('Asisten Delta', 'Karpim', 'RM-3');
        $this->seat($full, 1, 'TEK');
        Employee::factory()->create(['unit_kerja' => $this->unit->nama, 'jabatan' => 'Asisten Delta']);

        $names = fn ($editor) => $editor->instance()->filteredSeats->pluck('jabatan')->sort()->values()->all();

        $editor = $this->editor();
        $this->assertCount(4, $editor->instance()->filteredSeats);

        $editor->set('seatQuery', 'bravo');
        $this->assertSame(['Kepala Bravo'], $names($editor));

        $editor->call('resetSeatFilters')->set('rmFilter', 'RM-3');
        $this->assertSame(['Asisten Alfa', 'Asisten Charlie', 'Asisten Delta'], $names($editor));

        $editor->set('bidangFilter', 'TAN');
        $this->assertSame(['Asisten Alfa', 'Asisten Charlie'], $names($editor));

        $editor->call('resetSeatFilters')->set('seatStatusFilter', 'full');
        $this->assertSame(['Asisten Delta'], $names($editor));

        $editor->set('seatStatusFilter', 'empty');
        $this->assertSame(['Asisten Alfa', 'Asisten Charlie', 'Kepala Bravo'], $names($editor));

        $editor->set('seatStatusFilter', 'inactive');
        $this->assertSame(['Asisten Charlie'], $names($editor));

        $editor->set('seatQuery', 'tidak ada')->assertSee('Tidak ada jabatan yang sesuai');
        $editor->call('resetSeatFilters')->assertSet('seatQuery', '')->assertSet('seatStatusFilter', '');
        $this->assertFalse($editor->instance()->hasSeatFilters());
    }

    public function test_unit_list_uses_shared_toolbar_and_header(): void
    {
        Unit::create(['nama' => 'Kebun Kedua Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => true, 'urutan' => 2]);

        $list = Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertSee('Daftar Unit Kerja')
            ->assertSee('2 Unit Kerja')
            ->assertSee('Filter Lanjutan')
            ->assertSeeHtml('formasi-units-search')
            ->assertDontSee('Reset Filter');

        $list->set('statusFilter', 'inactive')->assertSee('Reset Filter')->assertSee('0 Unit Kerja');
    }

    public function test_seat_list_uses_shared_toolbar_and_header(): void
    {
        $this->seat($this->position('Asisten Alfa', 'Karpim', 'RM-3'), 2, 'TAN');
        $this->seat($this->position('Kepala Bravo', 'Karpim', 'RM-1'), 1, 'KEU');

        $editor = $this->editor()
            ->assertSee('Formasi Jabatan — Kebun Editor Uji')
            ->assertSee('2 Jabatan')
            ->assertSee('Filter Lanjutan')
            ->assertSeeHtml('formasi-seats-search');

        $editor->set('rmFilter', 'RM-1')->assertSee('1 Jabatan')->assertSee('Reset Filter')->assertSee('Hapus filter RM Band');
    }

    public function test_seat_reset_keeps_unit_filters(): void
    {
        $editor = $this->editor()
            ->set('unitQuery', 'Kebun')
            ->set('wilayahFilter', 'Wilayah Kalimantan Barat')
            ->set('statusFilter', 'active')
            ->set('seatQuery', 'alfa')
            ->set('rmFilter', 'RM-3')
            ->set('bidangFilter', 'TAN')
            ->set('seatStatusFilter', 'full')
            ->call('resetSeatFilters')
            ->assertSet('seatQuery', '')
            ->assertSet('rmFilter', '')
            ->assertSet('bidangFilter', '')
            ->assertSet('seatStatusFilter', '')
            ->assertSet('unitQuery', 'Kebun')
            ->assertSet('wilayahFilter', 'Wilayah Kalimantan Barat')
            ->assertSet('statusFilter', 'active');

        $editor->set('seatQuery', 'alfa')->set('rmFilter', 'RM-3')->call('resetUnitFilters')
            ->assertSet('unitQuery', '')
            ->assertSet('wilayahFilter', '')
            ->assertSet('statusFilter', '')
            ->assertSet('seatQuery', 'alfa')
            ->assertSet('rmFilter', 'RM-3');
    }

    public function test_over_quota_kpi_card_toggles_the_status_filter(): void
    {
        $over = $this->position('Asisten Berlebih');
        $this->seat($over, 1);
        $this->seat($this->position('Asisten Normal'), 2);
        Employee::factory()->count(2)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $over->nama]);

        $editor = $this->editor()->call('toggleOverQuotaFilter')->assertSet('seatStatusFilter', 'over');
        $this->assertSame(['Asisten Berlebih'], $editor->instance()->filteredSeats->pluck('jabatan')->all());

        $editor->call('toggleOverQuotaFilter')->assertSet('seatStatusFilter', '');
        $this->assertCount(2, $editor->instance()->filteredSeats);
    }

    public function test_row_actions_are_one_kebab_menu_with_the_destructive_item_separated(): void
    {
        $this->seat($this->position('Asisten Menu'), 1);

        $this->editor()
            ->assertSeeHtml('aria-label="Aksi untuk Asisten Menu"')
            ->assertSee('Ubah Kuota & Bidang')
            ->assertSee('Hapus Formasi Jabatan')
            ->assertSeeHtml('role="separator"');

        $list = Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertSeeHtml('aria-label="Aksi untuk unit Kebun Editor Uji"')
            ->assertSee('Kelola Formasi')
            ->assertSee('Ubah Unit')
            ->assertSee('Nonaktifkan Unit')
            ->assertSee('Hapus Unit');
        $list->assertSeeHtml(route('admin.master.formasi.show', $this->unit));

        $this->actingAs($this->admin)->get(route('admin.master.formasi.show', $this->unit))->assertSee('Aksi Unit');
    }

    public function test_unit_list_warns_about_employees_on_unit_names_missing_from_the_master(): void
    {
        $registered = $this->position('Asisten Terdaftar');
        $this->seat($registered, 2);
        Employee::factory()->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $registered->nama]);

        Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertDontSee('belum terdaftar di Master Unit');

        Employee::factory()->count(2)->create(['unit_kerja' => 'DISTRIK LAMA - KEBUN HILANG']);
        Employee::factory()->create(['unit_kerja' => 'BAGIAN LAMA']);

        $list = Livewire::actingAs($this->admin)->test(FormasiEditor::class)
            ->assertSee('3 pegawai tercatat pada 2 unit kerja yang belum terdaftar di Master Unit')
            ->assertSee('DISTRIK LAMA - KEBUN HILANG (2)')
            ->assertSee('BAGIAN LAMA (1)');
        $this->assertSame(['DISTRIK LAMA - KEBUN HILANG', 'BAGIAN LAMA'], $list->instance()->unregisteredEmployeeUnits->pluck('unit_kerja')->all());

        $this->editor()->assertDontSee('belum terdaftar di Master Unit');
    }

    public function test_seat_table_paginates_with_a_selectable_page_size_and_no_inner_scroll(): void
    {
        foreach (range(1, 12) as $n) {
            $this->seat($this->position(sprintf('Asisten Nomor %02d', $n)), 1);
        }

        $editor = $this->editor()
            ->assertSet('perPage', 25)
            ->assertSee('Menampilkan')
            ->assertDontSeeHtml('max-h-[68vh]');
        $this->assertCount(12, $editor->instance()->pagedSeats);

        $editor->set('perPage', 10)->assertSee('baris per halaman');
        $this->assertCount(10, $editor->instance()->pagedSeats);
        $this->assertSame(12, $editor->instance()->pagedSeats->total());
        $this->assertSame(2, $editor->instance()->pagedSeats->lastPage());

        $editor->call('gotoPage', 2);
        $this->assertCount(2, $editor->instance()->pagedSeats);
        $this->assertSame(2, $editor->instance()->pagedSeats->currentPage());

        $editor->set('seatQuery', 'Nomor 05');
        $this->assertSame(1, $editor->instance()->pagedSeats->currentPage());
        $this->assertCount(1, $editor->instance()->pagedSeats);

        $editor->call('resetSeatFilters')->call('gotoPage', 2)->call('sortBy', 'kuota');
        $this->assertSame(1, $editor->instance()->pagedSeats->currentPage());

        $editor->set('perPage', 7)->assertSet('perPage', 25);
    }

    public function test_unit_list_paginates_and_clamps_a_page_beyond_the_last(): void
    {
        foreach (range(1, 11) as $n) {
            Unit::create(['nama' => sprintf('Unit Halaman %02d', $n), 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 10 + $n]);
        }

        $list = Livewire::actingAs($this->admin)->test(FormasiEditor::class)->set('perPage', 10);
        $this->assertCount(10, $list->instance()->pagedOverview);
        $this->assertSame(12, $list->instance()->pagedOverview->total());

        $list->call('gotoPage', 99);
        $this->assertSame(2, $list->instance()->pagedOverview->currentPage());
        $this->assertCount(2, $list->instance()->pagedOverview);
    }

    public function test_seat_export_follows_the_active_filters_across_all_pages_and_never_exposes_employee_data(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        foreach (range(1, 12) as $n) {
            $this->seat($this->position(sprintf('Asisten Ekspor %02d', $n), 'Karpim', 'RM-3'), 1, 'TAN');
        }
        $this->seat($this->position('Kepala Ekspor', 'Karpim', 'RM-1'), 1, 'KEU');
        $this->seat($this->position('=HYPERLINK("http://x")', 'Karpim', 'RM-3'), 1, 'TAN');

        $this->editor()->set('perPage', 10)->set('rmFilter', 'RM-3')->call('exportFormasi');

        Excel::assertDownloaded('/^Formasi_Jabatan_kebun_editor_uji_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/', function (FormasiExport $export) {
            $rows = $export->collection();
            $this->assertCount(13, $rows);
            $this->assertNotContains('Kepala Ekspor', $rows->pluck(2)->all());
            $this->assertSame(['NO', 'UNIT KERJA', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'STATUS FORMASI', 'STATUS JABATAN'], $export->headings());
            $this->assertStringStartsWith("'=", $export->map($rows->firstWhere(2, '=HYPERLINK("http://x")'))[2]);
            $this->assertStringContainsString($this->admin->name, $export->properties()['description']);

            return true;
        });
    }

    public function test_unit_list_export_follows_the_filters_and_is_admin_only(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        Unit::create(['nama' => 'Kebun Nonaktif Ekspor', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Timur', 'is_active' => false, 'urutan' => 9]);

        Livewire::actingAs($this->admin)->test(FormasiEditor::class)->set('statusFilter', 'inactive')->call('exportFormasi');

        Excel::assertDownloaded('/^Formasi_Unit_PTPN4_Reg5_\d{8}_\d{6}\.xlsx$/', function (FormasiExport $export) {
            $this->assertSame(['Kebun Nonaktif Ekspor'], $export->collection()->pluck(1)->all());
            $this->assertSame('Nonaktif', $export->collection()->first()[3]);

            return true;
        });

        $user = User::factory()->create(['role' => 'user']);
        Livewire::actingAs($user)->test(FormasiEditor::class)->assertForbidden();
    }

    public function test_row_menus_follow_the_menu_button_pattern_and_have_a_desktop_sized_target(): void
    {
        $this->seat($this->position('Asisten Menu Aksesibel'), 1);

        $this->editor()
            ->assertSeeHtml('aria-haspopup="menu"')
            ->assertSeeHtml('role="menu"')
            ->assertSeeHtml('role="menuitem" tabindex="-1"')
            ->assertSeeHtml('@keydown.arrow-down.prevent="move(1)"')
            ->assertSeeHtml('w-8 h-8');

        $this->actingAs($this->admin)->get(route('admin.master.formasi.show', $this->unit))
            ->assertOk()
            ->assertSee('Aksi Unit')
            ->assertDontSee('Setiap perubahan dicatat pada riwayat perubahan master');
    }
}
