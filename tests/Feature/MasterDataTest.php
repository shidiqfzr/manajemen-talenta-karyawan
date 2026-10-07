<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::first() ?? User::factory()->create(['role' => 'admin']);
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
        $response = $this->actingAs($this->admin)->post(route('admin.master.units.toggle', $unit));
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
        $toggleResponse = $this->actingAs($this->admin)->post(route('admin.master.positions.toggle', $position));
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
            'wilayah' => 'Wilayah Kalimantan Selatan dan Tengah',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'unit' => [
                'nama' => $unitName,
                'wilayah' => 'Wilayah Kalimantan Selatan dan Tengah',
            ],
        ]);

        $this->assertDatabaseHas('units', ['nama' => $unitName]);
        Unit::where('nama', $unitName)->delete();
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
