<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\MasterAuditLog;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_access_master_units_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.master.units.index'));

        $response->assertOk();
        $response->assertViewHas(['units', 'totalUnits', 'activeUnits', 'inactiveUnits', 'wilayahList']);
        $response->assertSee('Master Unit Kerja');
    }

    public function test_admin_can_create_new_master_unit_and_it_appears_in_employee_form(): void
    {
        $unitName = 'Kebun Percobaan Baru Kalimantan';
        Unit::where('nama', $unitName)->delete();

        // 1. Admin creates unit via Master Data
        $postData = [
            'nama' => $unitName,
            'kode' => 'KPBK',
            'wilayah' => 'Wilayah Kalimantan Barat',
            'urutan' => 999,
            'is_active' => '1',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.master.units.store'), $postData);
        $response->assertRedirect(route('admin.master.units.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('units', [
            'nama' => $unitName,
            'wilayah' => 'Wilayah Kalimantan Barat',
            'is_active' => true,
        ]);

        // 2. Verify it dynamically appears in the add-employee form
        $createFormResponse = $this->actingAs($this->admin)->get(route('admin.employees.create'));
        $createFormResponse->assertOk();
        $createFormResponse->assertSee($unitName);

        // Clean up
        Unit::where('nama', $unitName)->delete();
    }

    public function test_admin_can_toggle_unit_active_status(): void
    {
        $unit = Unit::firstOrCreate(
            ['nama' => 'Unit Testing Toggle'],
            ['wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true]
        );

        $this->assertTrue($unit->is_active);

        // Toggle to inactive
        $response = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit dinonaktifkan sementara']);
        $response->assertRedirect();

        $unit->refresh();
        $this->assertFalse($unit->is_active);

        // Toggle back to active
        $response2 = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit));
        $response2->assertRedirect();

        $unit->refresh();
        $this->assertTrue($unit->is_active);

        $unit->delete();
    }

    public function test_admin_cannot_delete_unit_with_assigned_employees(): void
    {
        $unitName = 'Kebun Testing Proteksi';
        $nik = '88990099';

        Unit::where('nama', $unitName)->delete();
        Employee::where('nik', $nik)->delete();

        $unit = Unit::create([
            'nama' => $unitName,
            'wilayah' => 'Wilayah Kalimantan Timur',
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'PEGAWAI PROTEKSI UNIT',
            'jabatan' => 'Mandor',
            'unit_kerja' => $unitName,
            'level' => 'Karpel',
        ]);

        // Try to delete unit
        $response = $this->actingAs($this->admin)->delete(route('admin.master.units.destroy', $unit));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('units', ['nama' => $unitName]);

        // Clean up
        $employee->delete();
        $unit->delete();
    }

    public function test_admin_can_access_master_positions_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.master.positions.index'));

        $response->assertOk();
        $response->assertViewHas(['positions', 'totalPositions', 'karpimCount', 'karpelCount', 'activeCount']);
        $response->assertSee('Master Jabatan &amp; Posisi', false);
    }

    public function test_admin_can_create_and_toggle_position(): void
    {
        $posName = 'Specialist Carbon Offset & ESG';
        Position::where('nama', $posName)->delete();

        // 1. Create Position
        $response = $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => $posName,
            'bidang' => 'UMU',
            'level' => 'Karpim',
            'rm_level' => 'RM-2',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.master.positions.index'));
        $response->assertSessionHas('success');

        $position = Position::where('nama', $posName)->first();
        $this->assertNotNull($position);
        $this->assertEquals('RM-2', $position->rm_level);
        $this->assertTrue($position->is_active);

        // 2. Toggle Status
        $toggleResponse = $this->actingAs($this->admin)->post(route('admin.master.positions.toggle', $position), ['reason' => 'Jabatan dinonaktifkan sementara']);
        $toggleResponse->assertRedirect();

        $position->refresh();
        $this->assertFalse($position->is_active);

        // Clean up
        $position->delete();
    }

    public function test_ajax_json_quick_store_unit(): void
    {
        $unitName = 'Unit AJAX Quick Add Test';
        Unit::where('nama', $unitName)->delete();

        $response = $this->actingAs($this->admin)->postJson(route('admin.master.units.store'), [
            'nama' => $unitName,
            'wilayah' => 'Wilayah Kalimantan Selatan/Tengah',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'unit' => [
                'nama' => $unitName,
                'wilayah' => 'Wilayah Kalimantan Selatan/Tengah',
            ],
        ]);

        $this->assertDatabaseHas('units', ['nama' => $unitName]);
        Unit::where('nama', $unitName)->delete();
    }

    public function test_creating_position_with_unit_stores_bidang_on_the_seat(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Uji Formasi', 'kode' => 'Kebun', 'wilayah' => 'Kalimantan Barat', 'is_active' => true, 'urutan' => 99]);

        $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => 'Asisten Uji Kursi',
            'bidang' => 'KEU',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
            'unit_nama' => $unit->nama,
        ])->assertRedirect(route('admin.master.positions.index'));

        $position = Position::where('nama', 'Asisten Uji Kursi')->firstOrFail();
        $this->assertDatabaseHas('position_unit', [
            'position_id' => $position->id,
            'unit_id' => $unit->id,
            'kuota' => 1,
            'bidang' => 'KEU',
        ]);
    }

    public function test_position_index_filters_by_seat_bidang(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Uji Filter', 'kode' => 'Kebun', 'wilayah' => 'Kalimantan Barat', 'is_active' => true, 'urutan' => 98]);
        $tan = Position::create(['nama' => 'Asisten Filter Tanaman', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);
        $keu = Position::create(['nama' => 'Asisten Filter Keuangan', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);
        $unit->positions()->attach($tan->id, ['kuota' => 1, 'bidang' => 'TAN']);
        $unit->positions()->attach($keu->id, ['kuota' => 1, 'bidang' => 'KEU']);

        $response = $this->actingAs($this->admin)->get(route('admin.master.positions.index', ['bidang' => 'TAN']));

        $response->assertOk();
        $response->assertSee('Asisten Filter Tanaman');
        $response->assertDontSee('Asisten Filter Keuangan');
    }

    public function test_unit_crud_writes_audit_rows(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master.units.store'), [
            'nama' => 'Kebun Audit Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 91,
        ])->assertRedirect(route('admin.master.units.index'));

        $unit = Unit::where('nama', 'Kebun Audit Uji')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.master.units.update', $unit), [
            'nama' => 'Kebun Audit Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 91,
            'is_active' => '1', 'reason' => 'Penyesuaian nama unit resmi',
        ])->assertRedirect(route('admin.master.units.index'));

        $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit digabung ke unit lain'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->delete(route('admin.master.units.destroy', $unit), ['reason' => 'Unit dihapus dari master'])
            ->assertSessionHas('success');

        $this->assertSame(
            ['unit.created', 'unit.renamed', 'unit.deactivated', 'unit.deleted'],
            MasterAuditLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_position_crud_writes_audit_rows(): void
    {
        $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => 'Asisten Audit Uji', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'bidang' => 'TAN',
        ])->assertRedirect(route('admin.master.positions.index'));

        $position = Position::where('nama', 'Asisten Audit Uji')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.master.positions.update', $position), [
            'nama' => 'Asisten Audit Baru', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'bidang' => 'TAN',
            'is_active' => '1', 'reason' => 'Penyesuaian nomenklatur jabatan',
        ])->assertRedirect(route('admin.master.positions.index'));

        $this->actingAs($this->admin)->post(route('admin.master.positions.toggle', $position), ['reason' => 'Jabatan tidak dipakai lagi'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->delete(route('admin.master.positions.destroy', $position), ['reason' => 'Jabatan dihapus dari master'])
            ->assertSessionHas('success');

        $this->assertSame(
            ['position.created', 'position.renamed', 'position.deactivated', 'position.deleted'],
            MasterAuditLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_blocked_unit_deactivation_redirects_back_with_an_error_message(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Berpegawai Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 92]);
        Employee::factory()->count(2)->create(['unit_kerja' => $unit->nama]);

        $response = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit), ['reason' => 'Unit digabung ke unit lain']);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('2 pegawai', session('error'));
        $this->assertTrue($unit->fresh()->is_active);
        $this->assertSame(0, MasterAuditLog::count());
    }

    public function test_rename_without_reason_is_refused_with_a_message(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Nama Lama', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 93]);

        $response = $this->actingAs($this->admin)->put(route('admin.master.units.update', $unit), [
            'nama' => 'Kebun Nama Baru', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'urutan' => 93, 'is_active' => '1',
        ]);

        $response->assertSessionHas('error');
        $this->assertSame('Kebun Nama Lama', $unit->fresh()->nama);
    }

    public function test_creating_karpim_position_with_unit_and_without_bidang_is_refused(): void
    {
        $unit = Unit::create(['nama' => 'Kebun Kursi Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 94]);

        $response = $this->actingAs($this->admin)->post(route('admin.master.positions.store'), [
            'nama' => 'Asisten Tanpa Bidang', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'unit_nama' => $unit->nama,
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('positions', ['nama' => 'Asisten Tanpa Bidang']);
    }

    public function test_ajax_json_quick_store_position(): void
    {
        $posName = 'Asisten AJAX Quick Add Test';
        Position::where('nama', $posName)->delete();

        $response = $this->actingAs($this->admin)->postJson(route('admin.master.positions.store'), [
            'nama' => $posName,
            'bidang' => 'TAN',
            'level' => 'Karpim',
            'rm_level' => 'RM-3',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'position' => [
                'nama' => $posName,
                'bidang' => 'TAN',
                'level' => 'Karpim',
                'rm_level' => 'RM-3',
            ],
        ]);

        $this->assertDatabaseHas('positions', ['nama' => $posName]);
        Position::where('nama', $posName)->delete();
    }
}
