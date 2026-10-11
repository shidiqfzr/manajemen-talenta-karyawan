<?php

namespace Tests\Feature;

use App\Livewire\Admin\Employees\PenugasanSementaraManager;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\PenugasanSementaraService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class PenugasanSementaraTest extends TestCase
{
    use RefreshDatabase;

    protected PenugasanSementaraService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PenugasanSementaraService;
    }

    public function test_tambah_penugasan_sementara_berhasil()
    {
        $employee = Employee::create([
            'nik' => '12345678',
            'nama' => 'DUMMY EMPLOYEE',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jabatan' => 'Manajer Kebun',
            'unit_kerja' => 'Kebun A',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_lahir' => '1990-01-01',
            'tanggal_pensiun' => '2046-01-01',
            'tanggal_mbt' => '2045-01-01',
        ]);

        $data = [
            'jabatan' => 'Manajer Pabrik',
            'unit_kerja' => 'PKS B',
            'status_penugasan' => 'Plt',
            'tmt_awal' => '2026-10-01',
            'nomor_sk' => 'SK-123/2026',
        ];

        $history = $this->service->tambahPenugasan($employee, $data);

        $this->assertDatabaseHas('job_histories', [
            'id' => $history->id,
            'employee_nik' => '12345678',
            'jabatan' => 'Manajer Pabrik',
            'unit_kerja' => 'PKS B',
            'status_penugasan' => 'Plt',
            'tmt_akhir' => null,
            'jenis_mutasi' => 'PENUGASAN_SEMENTARA',
        ]);

        $this->assertDatabaseHas('employees', [
            'nik' => '12345678',
            'jabatan' => 'Manajer Kebun',
            'unit_kerja' => 'Kebun A',
            'status_penugasan' => 'Definitif',
        ]);
    }

    public function test_tambah_penugasan_ditolak_jika_status_tidak_valid()
    {
        $this->expectException(InvalidArgumentException::class);

        $employee = Employee::create([
            'nik' => '87654321',
            'nama' => 'DUMMY EMPLOYEE 2',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jabatan' => 'Manajer Kebun',
            'unit_kerja' => 'Kebun A',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_lahir' => '1990-01-01',
            'tanggal_pensiun' => '2046-01-01',
            'tanggal_mbt' => '2045-01-01',
        ]);

        $data = [
            'jabatan' => 'Manajer Pabrik',
            'unit_kerja' => 'PKS B',
            'status_penugasan' => 'Definitif',
            'tmt_awal' => '2026-10-01',
        ];

        $this->service->tambahPenugasan($employee, $data);
    }

    public function test_cabut_penugasan_berhasil()
    {
        $employee = Employee::create([
            'nik' => '11223344',
            'nama' => 'DUMMY EMPLOYEE 3',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jabatan' => 'Manajer Kebun',
            'unit_kerja' => 'Kebun A',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_lahir' => '1990-01-01',
            'tanggal_pensiun' => '2046-01-01',
            'tanggal_mbt' => '2045-01-01',
        ]);

        $history = JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan' => 'Askep',
            'unit_kerja' => 'Kebun C',
            'status_penugasan' => 'Plt',
            'tmt_awal' => '2026-05-01',
            'tmt_akhir' => null,
        ]);

        $this->service->cabutPenugasan($history, '2026-10-15');

        $this->assertEquals('2026-10-15', $history->fresh()->tmt_akhir->format('Y-m-d'));
    }

    public function test_livewire_penugasan_sementara_manager_loads_positions_for_unit_without_error()
    {
        $unit = Unit::factory()->create(['nama' => 'Unit Kebun Test']);
        $position = Position::factory()->create(['nama' => 'Asisten Tanaman Test', 'is_active' => true]);
        $unit->positions()->attach($position->id);

        $employee = Employee::create([
            'nik' => '99887766',
            'nama' => 'TEST EMPLOYEE MANAGER',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jabatan' => 'Manajer Kebun',
            'unit_kerja' => 'Kebun Asal',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_lahir' => '1990-01-01',
            'tanggal_pensiun' => '2046-01-01',
            'tanggal_mbt' => '2045-01-01',
        ]);

        Livewire::test(PenugasanSementaraManager::class, ['employee' => $employee])
            ->set('unitKerja', 'Unit Kebun Test')
            ->assertSet('jabatan', '')
            ->assertCount('positions', 1);
    }

    public function test_filter_karyawan_status_penugasan_sementara_berhasil()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $emp1 = Employee::create([
            'nik' => '11112222',
            'nama' => 'PEGAWAI PLT TEST',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Asal',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_lahir' => '1990-01-01',
            'tanggal_pensiun' => '2046-01-01',
            'tanggal_mbt' => '2045-01-01',
        ]);

        JobHistory::create([
            'employee_nik' => $emp1->nik,
            'jabatan' => 'Pjs Manajer Kebun',
            'unit_kerja' => 'Kebun Seberang',
            'status_penugasan' => 'Pejabat Sementara (Pjs)',
            'tmt_awal' => '2026-10-01',
            'tmt_akhir' => null,
        ]);

        $emp2 = Employee::create([
            'nik' => '33334444',
            'nama' => 'PEGAWAI DEFINITIF MURNI',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Asal',
            'status_penugasan' => 'Definitif',
            'tmt_bekerja' => '2010-01-01',
            'tanggal_lahir' => '1990-01-01',
            'tanggal_pensiun' => '2046-01-01',
            'tanggal_mbt' => '2045-01-01',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.employees.index', ['status_penugasan' => 'sementara']));
        $response->assertStatus(200);
        $response->assertSee('PEGAWAI PLT TEST');
        $response->assertDontSee('PEGAWAI DEFINITIF MURNI');
    }
}
