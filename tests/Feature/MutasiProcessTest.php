<?php

namespace Tests\Feature;

use App\Livewire\Admin\MutasiProcess;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MutasiProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Base Setup for all tests
        Unit::factory()->create(['nama' => 'Bagian SDM', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)']);
        Unit::factory()->create(['nama' => 'Kebun Sawit A', 'wilayah' => 'Wilayah Kalimantan Barat']);
    }

    public function test_promosi_filters_out_lower_rm_levels()
    {
        // Emp is RM-3
        $emp = Employee::factory()->create(['jabatan' => 'Asisten SDM', 'unit_kerja' => 'Bagian SDM']);
        Position::factory()->create(['nama' => 'Asisten SDM', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Kepala Bagian SDM', 'rm_level' => 'RM-1']);
        Position::factory()->create(['nama' => 'Staff SDM', 'rm_level' => 'RM-4']);

        $unit = Unit::where('nama', 'Bagian SDM')->first();
        $unit->positions()->attach(Position::whereIn('nama', ['Asisten SDM', 'Kepala Bagian SDM', 'Staff SDM'])->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'PROMOSI')
            ->set('targetUnitKerja', 'Bagian SDM')
            ->assertSeeHtml('<option value="Kepala Bagian SDM">Kepala Bagian SDM</option>')
            ->assertDontSeeHtml('<option value="Staff SDM">Staff SDM</option>') // Lower rank should not appear
            ->assertDontSeeHtml('<option value="Asisten SDM">Asisten SDM</option>'); // Same rank should not appear
    }

    public function test_sanksi_indisipliner_requires_tanggal_berakhir()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Manajer', 'unit_kerja' => 'Kebun Sawit A']);
        Position::factory()->create(['nama' => 'Manajer', 'rm_level' => 'RM-1']);
        Position::factory()->create(['nama' => 'Diperbantukan', 'rm_level' => 'RM-1', 'is_diperban' => true]);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('targetUnitKerja', 'Kebun Sawit A')
            ->set('targetJabatan', 'Diperbantukan')
            ->set('tmtAwal', now()->toDateString())
            ->set('nomorSk', 'SK/123')
            ->set('tanggalSk', now()->toDateString())
            ->set('catatan', 'Catatan test')
            // Missing tanggalBerakhirSanksi
            ->call('submitMutasi')
            ->assertHasErrors(['tanggalBerakhirSanksi'])
            // Date before tmtAwal
            ->set('tmtAwal', '2026-10-15')
            ->set('tanggalBerakhirSanksi', '2026-10-10')
            ->call('submitMutasi')
            ->assertHasErrors(['tanggalBerakhirSanksi']);
    }

    public function test_smart_override_sanksi_filters_out_unrelated_domain_positions()
    {
        // Emp is RM-2
        $emp = Employee::factory()->create(['jabatan' => 'Kasubag SDM', 'unit_kerja' => 'Bagian SDM']);
        Position::factory()->create(['nama' => 'Kasubag SDM', 'rm_level' => 'RM-2']);

        // Create pool positions
        Position::factory()->create(['nama' => 'Kepala Sub Bagian Diperbantukan', 'rm_level' => 'RM-2', 'is_diperban' => true]);
        Position::factory()->create(['nama' => 'Asisten Kepala Diperbantukan', 'rm_level' => 'RM-2', 'is_diperban' => true]);

        // Target unit is SDM (Unit Kantor, so 'Asisten' is rejected, 'Kasubag' is allowed)
        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('targetUnitKerja', 'Bagian SDM')
            ->assertSeeHtml('Kepala Sub Bagian Diperbantukan') // Kantor unit should see Kasubag
            ->assertDontSeeHtml('Asisten Kepala Diperbantukan'); // Filtered out by smart override & operational rule!
    }

    public function test_swap_checkbox_is_required_for_occupied_positions()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM']);
        $empB = Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM']);

        Position::factory()->create(['nama' => 'Posisi B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach(Position::where('nama', 'Posisi B')->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $empA->nik)
            ->set('jenisMutasi', 'ROTASI')
            ->set('targetUnitKerja', 'Bagian SDM')
            ->set('targetJabatan', 'Posisi B')
            ->set('tmtAwal', now()->toDateString())
            ->set('nomorSk', 'SK/123')
            ->set('tanggalSk', now()->toDateString())
            ->set('catatan', 'Catatan test')
            // Missing swapConfirmed
            ->call('submitMutasi')
            ->assertHasErrors(['swapConfirmed']);
    }

    public function test_employee_can_be_assigned_as_plt()
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
        ]);
        Position::factory()->create(['nama' => 'Manajer Kebun B', 'rm_level' => 'RM-1']);
        Unit::factory()->create(['nama' => 'Kebun Sawit B']);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'PENUGASAN')
            ->set('statusJabatan', 'Plt')
            ->set('targetUnitKerja', 'Kebun Sawit B')
            ->set('targetJabatan', 'Manajer Kebun B')
            ->set('tmtAwal', now()->toDateString())
            ->set('nomorSk', 'SK/123')
            ->set('tanggalSk', now()->toDateString())
            ->set('catatan', 'Catatan test')
            ->call('submitMutasi')
            ->assertHasNoErrors();

        $emp->refresh();
        $this->assertEquals('Asisten Kebun A', $emp->jabatan); // Profile doesn't change

        $this->assertEquals(2, $emp->activeJobs()->count());
        $this->assertTrue($emp->hasConcurrentJobs());
    }

    public function test_employee_definitive_mutation_closes_old_job()
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
        ]);
        Position::factory()->create(['nama' => 'Manajer Kebun B', 'rm_level' => 'RM-1']);
        Unit::factory()->create(['nama' => 'Kebun Sawit B']);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'PROMOSI')
            ->set('statusJabatan', 'Definitif')
            ->set('targetUnitKerja', 'Kebun Sawit B')
            ->set('targetJabatan', 'Manajer Kebun B')
            ->set('tmtAwal', now()->toDateString())
            ->set('nomorSk', 'SK/123')
            ->set('tanggalSk', now()->toDateString())
            ->set('catatan', 'Catatan test')
            ->call('submitMutasi')
            ->assertHasNoErrors();

        $emp->refresh();
        $this->assertEquals('Manajer Kebun B', $emp->jabatan); // Profile changes

        $this->assertEquals(1, $emp->activeJobs()->count());
        $this->assertFalse($emp->hasConcurrentJobs());
    }

    public function test_sanksi_auto_locks_status_jabatan_to_definitif()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten Kebun', 'unit_kerja' => 'Kebun Sawit A']);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('statusJabatan', 'Plt')
            ->set('jenisMutasi', 'SANKSI_INDISIPLINER')
            ->assertSet('statusJabatan', 'Definitif');
    }

    public function test_penugasan_khusus_auto_locks_status_jabatan_to_definitif()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten Kebun', 'unit_kerja' => 'Kebun Sawit A']);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('statusJabatan', 'Pj')
            ->set('jenisMutasi', 'PENUGASAN_KHUSUS')
            ->assertSet('statusJabatan', 'Definitif');
    }

    public function test_nomenklatur_diperbantukan_for_operational_unit()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten Kebun', 'unit_kerja' => 'Kebun Sawit A']);
        Position::factory()->create(['nama' => 'Asisten Kebun', 'rm_level' => 'RM-3']);

        Position::factory()->create(['nama' => 'Karyawan Diperbantukan RM-3', 'rm_level' => 'RM-3', 'is_diperban' => true]);
        Position::factory()->create(['nama' => 'Asisten Diperbantukan', 'rm_level' => 'RM-3', 'is_diperban' => true]);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('targetUnitKerja', 'Kebun Sawit A')
            ->assertSeeHtml('Asisten Diperbantukan')
            ->assertDontSeeHtml('Karyawan Diperbantukan RM-3');
    }

    public function test_nomenklatur_diperbantukan_for_rm4_bypasses_filter()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Karyawan Pelaksana', 'unit_kerja' => 'Kebun Sawit A', 'level' => 'Karpel']);
        Position::factory()->create(['nama' => 'Karyawan Pelaksana', 'rm_level' => 'RM-4']);

        Position::factory()->create(['nama' => 'Karyawan Diperbantukan RM-4', 'rm_level' => 'RM-4', 'is_diperban' => true]);

        Livewire::test(MutasiProcess::class)
            ->set('employeeNik', $emp->nik)
            ->set('jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('targetUnitKerja', 'Kebun Sawit A')
            ->assertSeeHtml('Karyawan Diperbantukan RM-4');
    }
}
