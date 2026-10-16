<?php

namespace Tests\Feature;

use App\Livewire\Admin\MutasiProcess;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
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
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Diperbantukan')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Catatan test')
            // Missing tanggalBerakhirSanksi
            ->call('submitMutasi')
            ->assertHasErrors(['form.tanggalBerakhirSanksi'])
            // Date before tmtAwal
            ->set('form.tmtAwal', '2026-10-15')
            ->set('form.tanggalBerakhirSanksi', '2026-10-10')
            ->call('submitMutasi')
            ->assertHasErrors(['form.tanggalBerakhirSanksi']);
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
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->assertSeeHtml('Kepala Sub Bagian Diperbantukan') // Kantor unit should see Kasubag
            ->assertDontSeeHtml('Asisten Kepala Diperbantukan'); // Filtered out by smart override & operational rule!
    }

    public function test_swap_checkbox_is_required_for_occupied_positions()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        $empB = Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);

        Position::factory()->create(['nama' => 'Posisi B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach(Position::where('nama', 'Posisi B')->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Catatan test')
            // Missing swapConfirmed
            ->call('submitMutasi')
            ->assertHasErrors(['swapConfirmed']);
    }

    public function test_swap_forbids_different_rm_levels()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-3']);
        $empB = Employee::factory()->create(['jabatan' => 'Kasubag B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);

        Position::factory()->create(['nama' => 'Kasubag B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach(Position::where('nama', 'Kasubag B')->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Kasubag B')
            ->set('form.swapConfirmed', true)
            ->set('form.dispensasiPromosi', true)
            ->set('form.alasanDispensasiPromosi', 'Dispensasi khusus')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Catatan test')
            ->call('submitMutasi')
            ->assertHasErrors(['swapConfirmed']);
    }

    public function test_rotasi_enforces_same_unit()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Bagian SDM']);
        Position::factory()->create(['nama' => 'Asisten B', 'rm_level' => 'RM-3']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A') // Different unit
            ->set('form.targetJabatan', 'Asisten B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Rotasi beda unit harus gagal')
            ->call('submitMutasi')
            ->assertHasErrors(['targetUnitKerja']);
    }

    public function test_mutasi_unit_forbids_same_unit()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Bagian SDM']);
        Position::factory()->create(['nama' => 'Asisten B', 'rm_level' => 'RM-3']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Bagian SDM') // Same unit
            ->set('form.targetJabatan', 'Asisten B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Mutasi unit ke unit sama harus gagal')
            ->call('submitMutasi')
            ->assertHasErrors(['targetUnitKerja']);
    }

    public function test_promosi_updates_employee_rm_level_and_new_golongan_and_karpim()
    {
        // Karpel RM-4 promoted to Karpim RM-3 via CKP
        $emp = Employee::factory()->create([
            'jabatan' => 'Mandor Panen',
            'unit_kerja' => 'Kebun Sawit A',
            'level' => 'Karpel',
            'rm_level' => 'RM-4',
            'golongan' => 'IID',
            'job_grade' => '8',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Asisten Afdeling')
            ->set('form.newGolongan', 'IIIA/00')
            ->set('form.newJobGrade', '11')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK.DIR/2026/CKP-01')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Promosi kelulusan jalur CKP')
            ->call('submitMutasi')
            ->assertHasNoErrors();

        $emp->refresh();
        $this->assertEquals('Asisten Afdeling', $emp->jabatan);
        $this->assertEquals('RM-3', $emp->rm_level);
        $this->assertEquals('Karpim', $emp->level); // Promoted to Karpim
        $this->assertEquals('IIIA/00', $emp->golongan);
        $this->assertEquals('11', $emp->job_grade);

        $history = JobHistory::where('employee_nik', $emp->nik)->where('jabatan', 'Asisten Afdeling')->first();
        $this->assertEquals('Karpim', $history->level);
        $this->assertEquals('IIIA/00', $history->golongan);
    }

    public function test_sk_pdf_file_upload_is_stored_in_private_storage_and_recorded(): void
    {
        Storage::fake('local');

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Asisten Kebun B', 'rm_level' => 'RM-3']);

        $file = UploadedFile::fake()->create('sk_direksi_mutasi.pdf', 500, 'application/pdf');

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Asisten Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK.DIR/2026/099')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.fileSk', $file)
            ->set('form.catatan', 'Rotasi afdeling')
            ->call('submitMutasi')
            ->assertHasNoErrors();

        $history = JobHistory::where('employee_nik', $emp->nik)->where('jabatan', 'Asisten Kebun B')->first();
        $this->assertNotNull($history);
        $this->assertNotNull($history->file_sk);
        Storage::disk('local')->assertExists($history->file_sk);
    }

    public function test_download_sk_route_streams_private_file(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['role' => 'admin']);

        $emp = Employee::factory()->create(['jabatan' => 'Asisten Kebun A', 'unit_kerja' => 'Kebun Sawit A']);
        Storage::disk('local')->put('dokumen_sk/test_sk.pdf', 'dummy pdf content');

        $history = JobHistory::create([
            'employee_nik' => $emp->nik,
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'tmt_awal' => now()->toDateString(),
            'file_sk' => 'dokumen_sk/test_sk.pdf',
            'nomor_sk' => 'SK/2026/TEST',
        ]);

        $response = $this->actingAs($user)->get(route('admin.mutasi.downloadSk', $history->id));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
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
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PENUGASAN')
            ->set('form.statusJabatan', 'Plt')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Manajer Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Catatan test')
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
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.statusJabatan', 'Definitif')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Manajer Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Catatan test')
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
            ->set('form.employeeNik', $emp->nik)
            ->set('form.statusJabatan', 'Plt')
            ->set('form.jenisMutasi', 'SANKSI_INDISIPLINER')
            ->assertSet('form.statusJabatan', 'Definitif');
    }

    public function test_penugasan_khusus_auto_locks_status_jabatan_to_definitif()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten Kebun', 'unit_kerja' => 'Kebun Sawit A']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.statusJabatan', 'Pj')
            ->set('form.jenisMutasi', 'PENUGASAN_KHUSUS')
            ->assertSet('form.statusJabatan', 'Definitif');
    }

    public function test_nomenklatur_diperbantukan_for_operational_unit()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten Kebun', 'unit_kerja' => 'Kebun Sawit A']);
        Position::factory()->create(['nama' => 'Asisten Kebun', 'rm_level' => 'RM-3']);

        Position::factory()->create(['nama' => 'Karyawan Diperbantukan RM-3', 'rm_level' => 'RM-3', 'is_diperban' => true]);
        Position::factory()->create(['nama' => 'Asisten Diperbantukan', 'rm_level' => 'RM-3', 'is_diperban' => true]);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->assertSeeHtml('Asisten Diperbantukan')
            ->assertDontSeeHtml('Karyawan Diperbantukan RM-3');
    }

    public function test_nomenklatur_diperbantukan_for_rm4_bypasses_filter()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Karyawan Pelaksana', 'unit_kerja' => 'Kebun Sawit A', 'level' => 'Karpel']);
        Position::factory()->create(['nama' => 'Karyawan Pelaksana', 'rm_level' => 'RM-4']);

        Position::factory()->create(['nama' => 'Karyawan Diperbantukan RM-4', 'rm_level' => 'RM-4', 'is_diperban' => true]);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'SANKSI_INDISIPLINER')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->assertSeeHtml('Karyawan Diperbantukan RM-4');
    }

    public function test_tenure_guard_requires_dispensasi_when_under_two_years(): void
    {
        // Emp has only 6 months of tenure in current unit
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'tmt_unit_kerja' => now()->subMonths(6)->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Asisten Kebun B', 'rm_level' => 'RM-3']);
        Unit::factory()->create(['nama' => 'Kebun Sawit B']);

        // Submitting to another unit without dispensation should fail
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Rotasi kebun')
            ->call('submitMutasi')
            ->assertHasErrors(['form.dispensasiTenure']);

        // With dispensation checked and reason filled, it should pass
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Rotasi kebun')
            ->set('form.dispensasiTenure', true)
            ->set('form.alasanDispensasiTenure', 'Kebutuhan mendesak operasional Kebun B')
            ->call('submitMutasi')
            ->assertHasNoErrors();
    }

    public function test_promosi_requires_dispensasi_when_not_eligible(): void
    {
        // Emp is RM-3 with Gol IIIA and 1 year tenure (not eligible for RM-2 without dispensation)
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'golongan' => 'IIIA',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYear()->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);

        // Submitting promotion without dispensation should fail
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/PROMOSI/01')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Promosi askep')
            ->call('submitMutasi')
            ->assertHasErrors(['form.dispensasiPromosi']);

        // With dispensation checked and reason filled, it should pass
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/PROMOSI/01')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Promosi askep')
            ->set('form.dispensasiPromosi', true)
            ->set('form.alasanDispensasiPromosi', 'Prestasi panen unggul dan rekomendasi Direksi')
            ->call('submitMutasi')
            ->assertHasNoErrors();
    }

    public function test_retired_employees_are_excluded_from_candidate_dropdown(): void
    {
        // Active employee
        $activeEmp = Employee::factory()->create([
            'nik' => '11111111',
            'nama' => 'Pegawai Aktif',
            'tanggal_pensiun' => now()->addYears(5)->toDateString(),
        ]);

        // Retired employee (BUP passed)
        $retiredEmp = Employee::factory()->create([
            'nik' => '99999999',
            'nama' => 'Pegawai Pensiun',
            'tanggal_pensiun' => now()->subMonths(3)->toDateString(),
        ]);

        Livewire::test(MutasiProcess::class)
            ->assertSeeHtml('Pegawai Aktif')
            ->assertDontSeeHtml('Pegawai Pensiun');
    }

    public function test_mutating_retired_employee_fails_validation(): void
    {
        $retiredEmp = Employee::factory()->create([
            'nik' => '88888888',
            'nama' => 'Pegawai Sudah Pensiun',
            'jabatan' => 'Asisten SDM',
            'unit_kerja' => 'Bagian SDM',
            'tanggal_pensiun' => now()->subMonth()->toDateString(),
        ]);

        Position::factory()->create(['nama' => 'Kepala Bagian SDM', 'rm_level' => 'RM-1']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $retiredEmp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Kepala Bagian SDM')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/999')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Mencoba memutasi pegawai pensiun')
            ->call('submitMutasi')
            ->assertHasErrors(['employeeNik']);
    }
}
