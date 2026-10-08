<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_employee_with_auto_retirement_and_initial_job_history(): void
    {
        $nik = '88990011';

        // Clean up if already exists from prior run
        Employee::where('nik', $nik)->delete();

        // Ensure the unit and position exist and are linked to satisfy validation
        $unit = Unit::firstOrCreate(['nama' => 'Kebun Inti Gunung Meliau'], ['kode' => 'KIGM', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Asisten Afdeling', 'level' => 'Karpim'], ['is_active' => true]);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 1]]);

        $postData = [
            'nik' => $nik,
            'nama' => 'Budi Prasetyo Testing',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tanggal_lahir' => '1988-06-15',
            'tmt_bekerja' => '2015-01-01',
            'tanggal_dalam_jabatan' => '2023-01-01',
            'job_grade' => 11,
            'person_grade' => 11,
            'status_karyawan' => 'Tetap (PKWTT)',
            // tanggal_pensiun and tanggal_mbt are intentionally left empty!
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);

        $response->assertRedirect(route('admin.employees.index'));
        $response->assertSessionHas('success');

        // 1. Verify Employee created with auto-calculated retirement dates & uppercase name
        $employee = Employee::where('nik', $nik)->first();
        $this->assertNotNull($employee);
        $this->assertEquals('BUDI PRASETYO TESTING', $employee->nama);
        $this->assertEquals('2044-06-01', $employee->tanggal_pensiun->toDateString()); // 1988 + 56 = 2044, startOfMonth of June
        $this->assertEquals('2043-06-01', $employee->tanggal_mbt->toDateString());     // T-1 year from pensiun

        // 2. Verify initial JobHistory was atomically created
        $history = JobHistory::where('employee_nik', $nik)->first();
        $this->assertNotNull($history);
        $this->assertEquals('Asisten Afdeling', $history->jabatan);
        $this->assertEquals('Kebun Inti Gunung Meliau', $history->unit_kerja);
        $this->assertEquals('PENUGASAN', $history->jenis_mutasi);
        $this->assertNull($history->tmt_akhir);

        // Clean up
        $employee->delete();
    }

    public function test_validation_rejects_invalid_nik(): void
    {
        // Case 1: Short NIK (5 digits)
        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'nik' => '12345',
            'nama' => 'Test Invalid',
            'jabatan' => 'Mandor',
            'unit_kerja' => 'Kebun Ngabang',
            'level' => 'Karpel',
        ]);
        $response->assertSessionHasErrors('nik');

        // Case 2: Alphanumeric NIK (contains letters)
        $response2 = $this->actingAs($this->admin)->post(route('admin.employees.store'), [
            'nik' => '1234567A',
            'nama' => 'Test Invalid',
            'jabatan' => 'Mandor',
            'unit_kerja' => 'Kebun Ngabang',
            'level' => 'Karpel',
        ]);
        $response2->assertSessionHasErrors('nik');
    }

    public function test_admin_can_update_employee_data(): void
    {
        $nik = '88990022';
        Employee::where('nik', $nik)->delete();

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'SITI AMINAH',
            'jabatan' => 'Kerani Tata Usaha',
            'unit_kerja' => 'Kebun Parindu',
            'level' => 'Karpel',
            'golongan' => 'IIB/02',
            'tanggal_lahir' => '1990-03-10',
            'tanggal_pensiun' => '2046-04-01',
            'tanggal_mbt' => '2045-04-01',
            'tanggal_dalam_jabatan' => '2020-01-01',
            'job_grade' => 4,
            'person_grade' => 4,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.employees.update', $nik), [
            'nama' => 'Siti Aminah Updated',
            'jabatan' => 'Kerani Kepala',
            'unit_kerja' => 'Kebun Parindu',
            'level' => 'Karpel',
            'golongan' => 'IIC/00',
            'tanggal_lahir' => '1990-03-10',
            'job_grade' => 5,
            'person_grade' => 5,
            'status_karyawan' => 'Tetap (PKWTT)',
        ]);

        $response->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertEquals('SITI AMINAH UPDATED', $employee->nama);
        // jabatan dan golongan tidak boleh berubah via form edit (SSOT rules)
        $this->assertEquals(5, $employee->job_grade);

        // Clean up
        $employee->delete();
    }

    public function test_admin_can_delete_employee(): void
    {
        $nik = '88990033';
        Employee::where('nik', $nik)->delete();

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'PEGAWAI DIHAPUS',
            'jabatan' => 'Operator Pabrik',
            'unit_kerja' => 'PKS Parindu',
            'level' => 'Karpel',
        ]);

        JobHistory::create([
            'employee_nik' => $nik,
            'jabatan' => 'Operator Pabrik',
            'unit_kerja' => 'PKS Parindu',
            'tmt_awal' => '2022-01-01',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.employees.destroy', $nik));

        $response->assertRedirect(route('admin.employees.index'));
        $this->assertNull(Employee::where('nik', $nik)->first());
        $this->assertEquals(0, JobHistory::where('employee_nik', $nik)->count());
    }

    public function test_admin_can_access_create_form_with_master_dropdowns(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.employees.create'));

        $response->assertOk();
        $response->assertViewHas(['groupedUnits', 'religions', 'genders', 'levels', 'familyStatuses', 'educationLevels']);
        $response->assertSee('name="jenis_kelamin"', false);
        $response->assertSee('name="unit_kerja"', false);
        $response->assertSee('name="agama"', false);
        $response->assertSee('name="susunan_keluarga"', false);
        $response->assertSee('name="pendidikan_terakhir"', false);
        $response->assertSee('Regional Office (Kantor Direksi Pontianak)');
    }

    public function test_admin_can_access_edit_form_with_master_dropdowns(): void
    {
        $nik = '88990044';
        Employee::where('nik', $nik)->delete();

        $employee = Employee::create([
            'nik' => $nik,
            'nama' => 'TESTING DROPDOWN EDIT',
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Ngabang',
            'level' => 'Karpim',
            'jenis_kelamin' => 'L',
            'agama' => 'Islam',
            'susunan_keluarga' => 'K/2',
            'pendidikan_terakhir' => 'D4 / S1',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.employees.edit', $nik));

        $response->assertOk();
        $response->assertViewHas(['groupedUnits', 'religions', 'genders', 'levels', 'familyStatuses', 'educationLevels']);
        $response->assertSee('name="jenis_kelamin"', false);
        $response->assertSee('name="unit_kerja"', false);
        $response->assertSee('Kebun Ngabang');

        // Also test show view renders gender
        $showResponse = $this->actingAs($this->admin)->get(route('admin.employees.show', $nik));
        $showResponse->assertOk();
        $showResponse->assertSee('Jenis Kelamin');
        $showResponse->assertSee('Laki-laki (L)');

        $employee->delete();
    }

    public function test_admin_can_create_and_update_employee_with_rm_level_override(): void
    {
        $nik = '88990055';
        Employee::where('nik', $nik)->delete();

        // Ensure the unit and position exist and are linked to satisfy validation
        $unit = Unit::firstOrCreate(['nama' => 'Kebun Inti Gunung Meliau'], ['kode' => 'KIGM', 'wilayah' => 'Lainnya', 'is_active' => true]);
        $pos = Position::firstOrCreate(['nama' => 'Asisten Afdeling', 'level' => 'Karpim'], ['is_active' => true]);
        $unit->positions()->syncWithoutDetaching([$pos->id => ['kuota' => 1]]);

        // 1. Buat pegawai dengan rm_level eksplisit (misal penugasan khusus RM-2 untuk Asisten)
        $postData = [
            'nik' => $nik,
            'nama' => 'TESTING RM OVERRIDE',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Inti Gunung Meliau',
            'level' => 'Karpim',
            'rm_level' => 'RM-2', // Override manual SK
            'golongan' => 'IIIA/00',
            'job_grade' => 11,
            'person_grade' => 11,
            'status_karyawan' => 'Tetap (PKWTT)',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);
        $response->assertRedirect(route('admin.employees.index'));

        $employee = Employee::where('nik', $nik)->first();
        $this->assertNotNull($employee);
        $this->assertEquals('RM-2', $employee->rm_level);

        // Pastikan ManPowerPlanningService memprioritaskan rm_level override ini
        $mppService = app(ManPowerPlanningService::class);
        $this->assertEquals('RM-2', $mppService->determineRmLevel($employee));

        // 2. Akses form edit dan pastikan field rm_level & daftar rmLevels ada
        $editResponse = $this->actingAs($this->admin)->get(route('admin.employees.edit', $nik));
        $editResponse->assertOk();
        $editResponse->assertViewHas('rmLevels');
        $editResponse->assertSee('name="rm_level"', false);
        $editResponse->assertSee('RM-2');

        // 3. Update rm_level ke RM-1
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.employees.update', $nik), array_merge($postData, [
            'rm_level' => 'RM-1',
        ]));
        $updateResponse->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertEquals('RM-2', $employee->rm_level);
        $this->assertEquals('RM-2', $mppService->determineRmLevel($employee));

        $employee->delete();
    }

    public function test_api_unit_positions_returns_filtered_positions_by_level(): void
    {
        // 1. Setup a specific Unit and Positions
        $unitName = 'Testing Unit API';
        $unit = Unit::create([
            'nama' => $unitName,
            'kode' => 'TUA',
            'wilayah' => 'Lainnya',
            'is_active' => true,
        ]);

        $posKarpim = Position::create([
            'nama' => 'Manajer Testing',
            'level' => 'Karpim',
            'is_active' => true,
        ]);

        $posKarpel = Position::create([
            'nama' => 'Kerani Testing',
            'level' => 'Karpel',
            'is_active' => true,
        ]);

        $unit->positions()->attach([
            $posKarpim->id => ['kuota' => 1],
            $posKarpel->id => ['kuota' => 2],
        ]);

        // 2. Request without level should return all
        $responseAll = $this->actingAs($this->admin)->getJson(route('admin.api.unit-positions', [
            'unit_name' => $unitName,
        ]));
        $responseAll->assertOk();
        $responseAll->assertJsonFragment(['positions' => ['Manajer Testing', 'Kerani Testing']]);

        // 3. Request with Karpim level should return only Karpim position
        $responseKarpim = $this->actingAs($this->admin)->getJson(route('admin.api.unit-positions', [
            'unit_name' => $unitName,
            'level' => 'Karpim',
        ]));
        $responseKarpim->assertOk();
        $responseKarpim->assertJsonFragment(['positions' => ['Manajer Testing']]);
        $responseKarpim->assertJsonMissing(['Kerani Testing']);

        // 4. Request with Karpel level should return only Karpel position
        $responseKarpel = $this->actingAs($this->admin)->getJson(route('admin.api.unit-positions', [
            'unit_name' => $unitName,
            'level' => 'Karpel',
        ]));
        $responseKarpel->assertOk();
        $responseKarpel->assertJsonFragment(['positions' => ['Kerani Testing']]);
        $responseKarpel->assertJsonMissing(['Manajer Testing']);

        // Clean up
        $unit->positions()->detach();
        $unit->delete();
        $posKarpim->delete();
        $posKarpel->delete();
    }

    public function test_employee_creation_fails_if_position_invalid_for_unit(): void
    {
        // Setup a unit and a specific position
        $unitName = 'Validation Test Unit';
        $unit = Unit::create([
            'nama' => $unitName,
            'kode' => 'VTU',
            'wilayah' => 'Lainnya',
            'is_active' => true,
        ]);

        $posValid = Position::create([
            'nama' => 'Posisi Valid Karpim',
            'level' => 'Karpim',
            'is_active' => true,
        ]);

        $unit->positions()->attach($posValid->id, ['kuota' => 1]);

        $nik = '88990099';
        Employee::where('nik', $nik)->delete();

        $postData = [
            'nik' => $nik,
            'nama' => 'Testing Validation',
            'jabatan' => 'Posisi Tidak Valid', // Not attached to unit!
            'unit_kerja' => $unitName,
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tanggal_lahir' => '1990-01-01',
            'tmt_bekerja' => '2015-01-01',
            'status_karyawan' => 'Tetap (PKWTT)',
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);

        // Should be redirected back with errors for 'jabatan'
        $response->assertSessionHasErrors('jabatan');
        $this->assertNull(Employee::where('nik', $nik)->first());

        // Now test with valid position
        $postData['jabatan'] = 'Posisi Valid Karpim';
        $responseValid = $this->actingAs($this->admin)->post(route('admin.employees.store'), $postData);
        $responseValid->assertRedirect(route('admin.employees.index'));
        $this->assertNotNull(Employee::where('nik', $nik)->first());

        // Clean up
        $unit->positions()->detach();
        $unit->delete();
        $posValid->delete();
        Employee::where('nik', $nik)->first()->delete();
    }
}
