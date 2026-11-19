<?php

namespace Tests\Feature;

use App\Livewire\Admin\MutasiProcess;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MutasiProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The screen is admin-only; every Livewire test below runs as an admin unless it says otherwise
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        // Base Setup for all tests
        Unit::factory()->create(['nama' => 'Bagian SDM', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)']);
        Unit::factory()->create(['nama' => 'Kebun Sawit A', 'wilayah' => 'Wilayah Kalimantan Barat']);
    }

    public function test_promosi_filters_out_lower_rm_levels()
    {
        // Emp is RM-3
        $emp = Employee::factory()->create(['jabatan' => 'Asisten SDM', 'unit_kerja' => 'Bagian SDM']);
        Position::factory()->create(['nama' => 'Asisten SDM', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Kepala Bagian SDM', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'General Manager SDM', 'rm_level' => 'RM-1']);
        Position::factory()->create(['nama' => 'Staff SDM', 'rm_level' => 'RM-4']);

        $unit = Unit::where('nama', 'Bagian SDM')->first();
        $unit->positions()->attach(Position::whereIn('nama', ['Asisten SDM', 'Kepala Bagian SDM', 'General Manager SDM', 'Staff SDM'])->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->assertSeeHtml('<option value="Kepala Bagian SDM">Kepala Bagian SDM</option>')
            ->assertDontSeeHtml('<option value="General Manager SDM">General Manager SDM</option>') // Two bands up must not appear
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

    public function test_temporary_assignment_into_occupied_position_fails_validation()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);

        Position::factory()->create(['nama' => 'Posisi B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach(Position::where('nama', 'Posisi B')->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'PENUGASAN')
            ->set('form.statusJabatan', 'Plt')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi B')
            ->set('form.swapConfirmed', true)
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Plt ke formasi terisi harus gagal')
            ->call('submitMutasi')
            ->assertHasErrors(['statusJabatan']);

        $this->assertEquals('Posisi A', $empA->refresh()->jabatan);
    }

    public function test_tampered_analysis_state_cannot_bypass_swap_confirmation()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Catatan test')
            ->set('targetAnalysis', null) // client-side tampering
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
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
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
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
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
            ->assertHasErrors(['form.alasanDispensasiDireksi', 'form.tanggalDispensasiDireksi']);

        // Reference without the approval date: both pieces of evidence are required, same as in Mutasi Berantai
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Rotasi kebun')
            ->set('form.alasanDispensasiDireksi', 'ND-012/DIR/2026')
            ->call('submitMutasi')
            ->assertHasErrors(['form.tanggalDispensasiDireksi'])
            ->assertHasNoErrors(['form.alasanDispensasiDireksi'])
            ->assertSee('Dasar Persetujuan Direksi')
            ->assertDontSee('Persetujuan Dispensasi Direksi');

        // With the document reference and approval date filled, it should pass
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Rotasi kebun')
            ->set('form.alasanDispensasiDireksi', 'ND-012/DIR/2026')
            ->set('form.tanggalDispensasiDireksi', now()->toDateString())
            ->call('submitMutasi')
            ->assertHasNoErrors();

        // The approval is derived from the evidence and stored on the SK together with its reference and date.
        $history = JobHistory::where('employee_nik', $emp->nik)->latest('id')->first();
        $this->assertTrue((bool) $history->is_dispensasi);
        $this->assertStringContainsString('ND-012/DIR/2026 (disetujui '.now()->format('d/m/Y').')', (string) $history->alasan_dispensasi);
    }

    public function test_dispensation_evidence_requires_a_real_reference_and_a_non_future_date(): void
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'tmt_unit_kerja' => now()->subMonths(6)->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Asisten Kebun B', 'rm_level' => 'RM-3']);
        Unit::factory()->create(['nama' => 'Kebun Sawit B']);

        $component = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Kebun B')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/123')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Rotasi kebun');

        // A throw-away reference is not evidence
        $component->set('form.alasanDispensasiDireksi', 'ok')
            ->set('form.tanggalDispensasiDireksi', now()->toDateString())
            ->call('submitMutasi')
            ->assertHasErrors(['form.alasanDispensasiDireksi' => 'min']);

        // Direksi cannot have approved it in the future
        $component->set('form.alasanDispensasiDireksi', 'ND-012/DIR/2026')
            ->set('form.tanggalDispensasiDireksi', now()->addDay()->toDateString())
            ->call('submitMutasi')
            ->assertHasErrors(['form.tanggalDispensasiDireksi' => 'before_or_equal']);

        $this->assertDatabaseMissing('job_histories', ['employee_nik' => $emp->nik, 'unit_kerja' => 'Kebun Sawit B']);
    }

    public function test_unmet_promotion_requires_direksi_dispensation_and_is_recorded_in_audit_trail(): void
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

        $component = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/PROMOSI/01')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Promosi askep');

        // Like the tenure rule: without the Direksi approval the process is blocked
        $component->call('submitMutasi')->assertHasErrors(['form.alasanDispensasiDireksi', 'form.tanggalDispensasiDireksi']);
        $this->assertDatabaseMissing('job_histories', ['employee_nik' => $emp->nik, 'jabatan' => 'Askep Tanaman']);

        // Reference without the approval date: the date is required too
        $component->set('form.alasanDispensasiDireksi', 'Rekomendasi asesmen 2026')
            ->call('submitMutasi')
            ->assertHasErrors(['form.tanggalDispensasiDireksi']);

        $component->set('form.tanggalDispensasiDireksi', now()->toDateString())
            ->call('submitMutasi')
            ->assertHasNoErrors();

        // The unmet qualification and its approval are snapshotted on the job history; tenure flag stays untouched
        $history = JobHistory::where('employee_nik', $emp->nik)->latest('id')->first();
        $this->assertStringContainsString('belum terpenuhi', (string) $history->catatan_kepatuhan);
        $this->assertFalse((bool) $history->is_dispensasi);
    }

    public function test_single_dispensation_covers_tenure_and_promotion_exceptions_together(): void
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'golongan' => 'IIIA',
            'tmt_unit_kerja' => now()->subMonths(6)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYear()->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);
        Unit::factory()->create(['nama' => 'Kebun Sawit B']);

        $component = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/PROMOSI/03')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Promosi askep');

        // Both exceptions are listed in one card with a single approval
        $component->assertSee('Pengecualian regulasi yang diajukan')
            ->assertSee('Masa Dinas Unit Kerja')
            ->assertSee('Kualifikasi Promosi')
            // The tenure exception states the standard and the shortfall (24 - 6 = 18 months), computed by the service
            ->assertSee('Masa Dinas Unit Kerja')
            ->assertSee('(min. 2 tahun)')
            ->assertSee('Saat ini 0 th 6 bln', false)
            ->assertSee('kurang 1 th 6 bln')
            ->assertSeeHtml('data-dispensasi-card')
            ->assertSee('Perlu Dispensasi');
        $this->assertSame(1, substr_count($component->html(), 'data-dispensasi-block'));

        $component->call('submitMutasi')->assertHasErrors(['form.alasanDispensasiDireksi', 'form.tanggalDispensasiDireksi']);

        $component->set('form.alasanDispensasiDireksi', 'Nota Dinas Direksi No. 77/2026')
            ->set('form.tanggalDispensasiDireksi', now()->toDateString())
            // Complete evidence turns the status to recorded before anything is submitted
            ->assertSee('Dispensasi Tercatat')
            ->call('submitMutasi')
            ->assertHasNoErrors();

        // The one approval is stored for both rules in the audit trail
        $history = JobHistory::where('employee_nik', $emp->nik)->latest('id')->first();
        $this->assertTrue((bool) $history->is_dispensasi);
        $this->assertStringContainsString('Nota Dinas Direksi No. 77/2026', (string) $history->alasan_dispensasi);
        $this->assertStringContainsString('Dispensasi promosi disetujui: Nota Dinas Direksi No. 77/2026', (string) $history->catatan_kepatuhan);
    }

    public function test_promotion_dispensation_is_recorded_without_waiving_tenure(): void
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'golongan' => 'IIIA',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYear()->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->assertSeeHtml('wire:model.live.debounce.300ms="form.alasanDispensasiDireksi"')
            ->set('form.alasanDispensasiDireksi', 'Rekomendasi asesmen 2026')
            ->set('form.tanggalDispensasiDireksi', now()->toDateString())
            ->set('form.tmtAwal', now()->toDateString())
            ->set('form.nomorSk', 'SK/PROMOSI/02')
            ->set('form.tanggalSk', now()->toDateString())
            ->set('form.catatan', 'Promosi askep')
            ->call('submitMutasi')
            ->assertHasNoErrors();

        $history = JobHistory::where('employee_nik', $emp->nik)->latest('id')->first();
        $this->assertStringContainsString('Dispensasi promosi disetujui: Rekomendasi asesmen 2026', (string) $history->catatan_kepatuhan);
        // Promotion approval never flips the tenure dispensation flag
        $this->assertFalse((bool) $history->is_dispensasi);
    }

    public function test_retired_employees_are_excluded_from_candidate_dropdown(): void
    {
        // Active employee
        $activeEmp = Employee::factory()->create([
            'nik' => '11111111',
            'nama' => 'Karyawan Aktif',
            'tanggal_pensiun' => now()->addYears(5)->toDateString(),
        ]);

        // Retired employee (BUP passed)
        $retiredEmp = Employee::factory()->create([
            'nik' => '99999999',
            'nama' => 'Karyawan Pensiun',
            'tanggal_pensiun' => now()->subMonths(3)->toDateString(),
        ]);

        Livewire::test(MutasiProcess::class)
            ->assertSeeHtml('Karyawan Aktif')
            ->assertDontSeeHtml('Karyawan Pensiun');
    }

    public function test_mutating_retired_employee_fails_validation(): void
    {
        $retiredEmp = Employee::factory()->create([
            'nik' => '88888888',
            'nama' => 'Karyawan Sudah Pensiun',
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
            ->set('form.catatan', 'Mencoba memutasi karyawan pensiun')
            ->call('submitMutasi')
            ->assertHasErrors(['employeeNik']);
    }

    public function test_employee_first_flow_locks_target_card_until_employee_is_selected()
    {
        $emp = Employee::factory()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Asisten SDM',
            'unit_kerja' => 'Bagian SDM',
        ]);

        Livewire::test(MutasiProcess::class)
            // 1. Without employee selected: Card 2 shows locked banner
            ->assertSee('Formulir Penugasan Terkunci')
            ->assertSee('Pilih karyawan aktif terlebih dahulu pada panel kiri')
            ->assertDontSeeHtml('name="unit_kerja"')
            // 2. Once employee is selected: Card 2 unlocks and renders input controls
            ->set('form.employeeNik', $emp->nik)
            ->assertDontSee('Formulir Penugasan Terkunci')
            ->assertSee('Penugasan & Posisi Tujuan')
            ->assertSeeHtml('name="unit_kerja"');
    }

    public function test_reserved_position_blocks_submission_with_validation_error()
    {
        $empA = Employee::factory()->create([
            'nama' => 'Ahmad R',
            'jabatan' => 'Asisten A',
            'unit_kerja' => 'Kebun Sawit A',
            'rm_level' => 'RM-3',
        ]);

        $empB = Employee::factory()->create([
            'nama' => 'Bambang S',
            'jabatan' => 'Asisten B',
            'unit_kerja' => 'Kebun Sawit B',
            'rm_level' => 'RM-3',
        ]);

        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        $unitA = Unit::where('nama', 'Kebun Sawit A')->first();
        $unitB = Unit::where('nama', 'Kebun Sawit B')->first();
        if ($unitB) {
            $unitB->positions()->syncWithoutDetaching(Position::where('nama', 'Asisten Afdeling 1')->pluck('id'));
        }

        // empB has a future-dated scheduled mutation reserving 'Asisten Afdeling 1' at 'Kebun Sawit B'
        JobHistory::create([
            'employee_id' => $empB->id,
            'employee_nik' => $empB->nik,
            'jabatan' => 'Asisten Afdeling 1',
            'unit_kerja' => 'Kebun Sawit B',
            'tmt_awal' => now()->addMonth()->startOfMonth()->toDateString(),
            'sk_nomor' => 'SK/FUTURE/001',
            'sk_tanggal' => now()->toDateString(),
            'is_applied' => false,
        ]);

        // empA tries to target the reserved position
        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Afdeling 1')
            ->call('openSummaryModal')
            ->assertHasErrors(['targetJabatan'])
            ->assertSet('showSummaryModal', false);
    }

    private function blockedPromotion(array $candidate = [], bool $withGoodEvaluation = false)
    {
        $empA = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-3'] + $candidate);
        if ($withGoodEvaluation) {
            Evaluation::create([
                'employee_nik' => $empA->nik,
                'kategori_9box' => 'Box 9: Star',
                'skor_smkbk_9box' => 95.5,
                'skor_cli_9box' => 90.0,
                'lembaga_asesmen' => 'Holding Assessment Center',
                'tanggal_pelaksanaan_asesmen' => now()->subMonths(6)->toDateString(),
                'expired_asesmen' => now()->addYears(2)->toDateString(),
            ]);
        }
        Employee::factory()->create(['jabatan' => 'Kasubag B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Kasubag B', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Kasubag Kosong', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()
            ->attach(Position::whereIn('nama', ['Kasubag B', 'Kasubag Kosong'])->pluck('id')->toArray());

        return Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Kasubag B');
    }

    public function test_occupied_cross_rm_target_blocks_sk_section_and_primary_action()
    {
        $c = $this->blockedPromotion();

        $this->assertStringContainsString('Mutasi Berantai', $c->instance()->blockingReason);
        $c->assertSeeHtml('data-blocked="true"')
            ->assertSeeHtml('data-sk-locked="true"')
            ->assertSee('Dibuka setelah kendala penempatan di atas diselesaikan')
            ->assertDontSeeHtml('wire:model.live.debounce.300ms="form.nomorSk"');
        $this->assertMatchesRegularExpression('/<button[^>]*wire:click="openSummaryModal"[^>]*\sdisabled/', $c->html());
    }

    public function test_unblocked_target_renders_sk_form_without_lock_row()
    {
        $this->blockedPromotion()->set('form.targetJabatan', 'Kasubag Kosong')
            ->assertDontSeeHtml('data-sk-locked="true"')
            ->assertSeeHtml('wire:model.live.debounce.300ms="form.nomorSk"');
    }

    public function test_occupied_block_has_single_chain_cta_and_disabled_review_button()
    {
        $c = $this->blockedPromotion();

        $this->assertTrue($c->instance()->resolvableByChain);
        $c->assertSee('Selesaikan melalui Mutasi Berantai')
            ->assertSee('Pejabat petahana perlu dipindahkan lebih dulu.')
            ->assertSee('Tinjau &amp; Eksekusi Mutasi', false)
            ->assertSeeHtml('data-blocked="true"');

        // One primary CTA: only the blocker card links to the chain flow; the action bar keeps a disabled review button.
        $this->assertSame(1, substr_count($c->html(), 'href="'.route('admin.mutasi.chain').'"'));
        $this->assertMatchesRegularExpression('/<button[^>]*wire:click="openSummaryModal"[^>]*\sdisabled/', $c->html());
    }

    #[DataProvider('nonChainBlocks')]
    public function test_non_chain_blocks_keep_disabled_review_button(string $case)
    {
        if ($case === 'retired') {
            $emp = Employee::factory()->create([
                'jabatan' => 'Asisten SDM', 'unit_kerja' => 'Bagian SDM',
                'tanggal_pensiun' => now()->subMonth()->toDateString(),
            ]);
            $c = Livewire::test(MutasiProcess::class)->set('form.employeeNik', $emp->nik);
        } elseif ($case === 'same') {
            $emp = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
            $c = Livewire::test(MutasiProcess::class)
                ->set('form.employeeNik', $emp->nik)
                ->set('form.jenisMutasi', 'ROTASI')
                ->set('form.targetUnitKerja', 'Bagian SDM')
                ->set('form.targetJabatan', 'Posisi A');
        } else {
            $empA = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Kebun Sawit A', 'rm_level' => 'RM-3']);
            Employee::factory()->create(['jabatan' => 'Asisten Afdeling 1', 'unit_kerja' => 'Kebun Sawit A', 'rm_level' => 'RM-3']);
            $successor = Employee::factory()->create(['jabatan' => 'Asisten C', 'unit_kerja' => 'Kebun Sawit B', 'rm_level' => 'RM-3']);
            Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
            JobHistory::create([
                'employee_id' => $successor->id,
                'employee_nik' => $successor->nik,
                'jabatan' => 'Asisten Afdeling 1',
                'unit_kerja' => 'Kebun Sawit A',
                'tmt_awal' => now()->addMonth()->startOfMonth()->toDateString(),
                'sk_nomor' => 'SK/FUTURE/003',
                'sk_tanggal' => now()->toDateString(),
                'is_applied' => false,
            ]);
            $c = Livewire::test(MutasiProcess::class)
                ->set('form.employeeNik', $empA->nik)
                ->set('form.jenisMutasi', 'ROTASI')
                ->set('form.targetUnitKerja', 'Kebun Sawit A')
                ->set('form.targetJabatan', 'Asisten Afdeling 1');
        }

        $this->assertFalse($c->instance()->resolvableByChain);
        $c->assertSee('Tinjau &amp; Eksekusi Mutasi', false)
            ->assertDontSee('Selesaikan melalui Mutasi Berantai')
            ->assertSeeHtml('data-blocked="true"');
        $this->assertMatchesRegularExpression('/<button[^>]*wire:click="openSummaryModal"[^>]*\sdisabled/', $c->html());
    }

    public static function nonChainBlocks(): array
    {
        return ['retired' => ['retired'], 'same position' => ['same'], 'reserved' => ['reserved']];
    }

    public function test_unblocked_target_keeps_enabled_review_button()
    {
        $c = $this->blockedPromotion()->set('form.targetJabatan', 'Kasubag Kosong');

        $this->assertFalse($c->instance()->resolvableByChain);
        $c->assertSee('Tinjau &amp; Eksekusi Mutasi', false)
            ->assertDontSee('Selesaikan melalui Mutasi Berantai')
            ->assertSeeHtml('data-blocked="false"');
    }

    public function test_switching_to_blocked_target_clears_stale_validation_errors()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-3']);
        Employee::factory()->create(['jabatan' => 'Kasubag B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Kasubag B', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Kasubag Kosong', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()
            ->attach(Position::whereIn('nama', ['Kasubag B', 'Kasubag Kosong'])->pluck('id')->toArray());

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Kasubag Kosong')
            ->call('openSummaryModal') // SK fields blank → validation errors
            ->assertHasErrors()
            ->set('form.targetJabatan', 'Kasubag B') // occupied, other RM → blocked
            ->assertHasNoErrors();
    }

    public function test_switching_to_vacant_target_clears_blocking_state()
    {
        $c = $this->blockedPromotion()->set('form.targetJabatan', 'Kasubag Kosong');

        $this->assertNull($c->instance()->blockingReason);
        $c->assertDontSeeHtml('data-blocked="true"');
    }

    public function test_equal_rm_swap_into_a_full_multi_seat_formasi_is_blocked_with_a_clear_reason()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Employee::factory()->count(2)->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        $seat = Position::factory()->create(['nama' => 'Posisi B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach($seat->id, ['kuota' => 2]);

        $c = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi B');

        $this->assertFalse($c->instance()->isSwapAllowed);
        $this->assertStringContainsString('berkuota 2', $c->instance()->swapIncompatibilityMessage);
        $this->assertNotNull($c->instance()->blockingReason);
        $this->assertTrue($c->instance()->resolvableByChain);
    }

    public function test_multi_seat_formasi_with_a_free_seat_is_a_vacant_target_and_not_blocked()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        $seat = Position::factory()->create(['nama' => 'Posisi B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach($seat->id, ['kuota' => 3]);

        $c = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi B');

        $this->assertSame('vacant', $c->instance()->targetAnalysis['type']);
        $this->assertNull($c->instance()->blockingReason);
    }

    public function test_equal_rm_swap_is_not_blocked()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Employee::factory()->create(['jabatan' => 'Posisi B', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);
        Position::factory()->create(['nama' => 'Posisi B', 'rm_level' => 'RM-2']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()->attach(Position::where('nama', 'Posisi B')->pluck('id')->toArray());

        $c = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi B');

        $this->assertNull($c->instance()->blockingReason);
    }

    public function test_same_position_target_is_blocked()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);

        $c = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi A');

        $this->assertStringContainsString('formasi dan unit kerja yang sama', $c->instance()->blockingReason);
    }

    public function test_reserved_target_is_blocked()
    {
        $empA = Employee::factory()->create(['nama' => 'Ahmad R', 'jabatan' => 'Asisten A', 'unit_kerja' => 'Kebun Sawit A', 'rm_level' => 'RM-3']);
        $empB = Employee::factory()->create(['nama' => 'Bambang S', 'jabatan' => 'Asisten B', 'unit_kerja' => 'Kebun Sawit B', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        Unit::where('nama', 'Kebun Sawit B')->first()?->positions()->syncWithoutDetaching(Position::where('nama', 'Asisten Afdeling 1')->pluck('id'));

        JobHistory::create([
            'employee_id' => $empB->id,
            'employee_nik' => $empB->nik,
            'jabatan' => 'Asisten Afdeling 1',
            'unit_kerja' => 'Kebun Sawit B',
            'tmt_awal' => now()->addMonth()->startOfMonth()->toDateString(),
            'sk_nomor' => 'SK/FUTURE/001',
            'sk_tanggal' => now()->toDateString(),
            'is_applied' => false,
        ]);

        $c = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Afdeling 1');

        $this->assertStringContainsString('dipesan', $c->instance()->blockingReason);
    }

    public function test_blocked_target_shows_single_blocking_status()
    {
        $this->blockedPromotion()
            ->assertSee('Tidak Dapat Diproses Tunggal')
            ->assertSee('Perlu Mutasi Berantai')
            ->assertDontSee('Swap Ditolak')
            ->assertDontSee('Konflik Okupansi')
            ->assertDontSee('Memerlukan Perhatian / Dispensasi');
    }

    public function test_unmet_promotion_shows_required_dispensation_status()
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'golongan' => 'IIIA',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYear()->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->assertSee('Perlu Dispensasi')
            ->assertSee('Kualifikasi Promosi')
            ->assertSee('Dasar Persetujuan Direksi')
            // Every requirement reads "requirement - verdict" with its facts on one line; a missing assessment can be completed
            ->assertSee('terpenuhi')
            ->assertSee('belum terpenuhi')
            ->assertSee('Gol: IIIA')
            ->assertSee('Masa Dinas: 1 th 0 bln')
            ->assertSee('Lengkapi data evaluasi')
            ->assertDontSee('Tidak Dapat Diproses Tunggal');
    }

    private function occupiedRm1Promotion(array $incumbent = [])
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Kasubag Madya', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2',
            'golongan' => 'IIID',
            'tanggal_dalam_jabatan' => now()->subMonths(19)->toDateString(),
            // Tetap: tanggal acak dari pabrik kadang persis sama dengan masa kerja pejabat petahana yang dihitung pada uji.
            'tmt_unit_kerja' => now()->subYears(10)->toDateString(),
        ]);
        Employee::factory()->create(['jabatan' => 'Kabag Penuh', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-1'] + $incumbent);
        Position::factory()->create(['nama' => 'Kabag Penuh', 'rm_level' => 'RM-1']);
        Position::factory()->create(['nama' => 'Kabag Kosong', 'rm_level' => 'RM-1']);
        Unit::where('nama', 'Bagian SDM')->first()->positions()
            ->attach(Position::whereIn('nama', ['Kabag Penuh', 'Kabag Kosong'])->pluck('id')->toArray());

        return Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Kabag Penuh');
    }

    public function test_promotion_advisory_never_uses_indigo_palette()
    {
        $c = $this->blockedPromotion();

        $c->assertSee('Kualifikasi Promosi')->assertDontSee('indigo-');
    }

    public function test_primary_action_buttons_use_shared_button_component()
    {
        $html = $this->blockedPromotion()->html();

        // Shared component markup carries the disabled-primary treatment; no hand-rolled green button remains.
        $this->assertStringContainsString('disabled:bg-surface-200', $html);
        $this->assertStringNotContainsString('bg-primary-700 hover:bg-primary-800 text-white font-bold text-xs', $html);
    }

    public function test_incumbent_uses_profile_card_without_status_badges()
    {
        $c = $this->occupiedRm1Promotion();

        $c->assertSee('Profil Pejabat Petahana Saat Ini')
            ->assertDontSee('incumbent-status-pill')
            ->assertDontSee('Occupied');
    }

    public function test_dispensation_fields_are_locked_while_the_formasi_is_occupied()
    {
        $c = $this->occupiedRm1Promotion();

        // The card stays readable while the formasi is occupied, but approval cannot be entered until it is available.
        $c->assertSee('Kualifikasi Promosi')
            ->assertSee('Pengecualian regulasi yang diajukan')
            ->assertSee('Belum Tersedia')
            ->assertDontSee('Perlu Dispensasi')
            ->assertSee('Dasar Persetujuan Direksi')
            ->assertSee('Tersedia setelah kendala penempatan di atas selesai.')
            ->assertSeeHtml('data-promotion-criteria')
            ->assertSeeHtml('data-dispensasi-locked')
            ->assertDontSee('Lihat detail kriteria')
            ->assertDontSee('Langkah 1 dari 2')
            ->assertDontSee('Langkah 2 dari 2');

        $html = $c->html();
        $this->assertMatchesRegularExpression('/<input[^>]*form\.alasanDispensasiDireksi"[^>]*\sdisabled/s', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*form\.tanggalDispensasiDireksi"[^>]*\sdisabled/s', $html);
        // The approval is no longer a self-attested checkbox
        $this->assertStringNotContainsString('form.dispensasiDireksi"', $html);
    }

    public function test_action_bar_summarises_every_outstanding_issue_with_a_count()
    {
        // Occupied formasi (blocking) + unmet promotion without Direksi evidence = two things still to resolve
        $this->occupiedRm1Promotion()
            ->assertSee('2 hal perlu diselesaikan')
            ->assertSee('Pejabat petahana perlu dipindahkan lebih dulu.')
            ->assertSee('Dasar persetujuan Direksi belum dilengkapi.')
            ->assertSee('id="blocking-reason"', false);

        // Eligible candidate (Gol IIIC + good evaluation): the formasi is the only issue, shown as a plain sentence without a count
        $this->blockedPromotion(['golongan' => 'IIIC'], withGoodEvaluation: true)
            ->assertDontSee('hal perlu diselesaikan')
            ->assertDontSee('Dasar persetujuan Direksi belum dilengkapi.')
            ->assertSee('Pejabat petahana perlu dipindahkan lebih dulu.');
    }

    public function test_compliance_header_has_only_title_and_status()
    {
        $this->occupiedRm1Promotion()
            ->assertSee('Uji Kelayakan Regulasi &amp; Kepatuhan Penugasan', false)
            ->assertSee('Tidak Dapat Diproses Tunggal')
            ->assertDontSee('Pemeriksaan kesesuaian masa dinas unit');
    }

    public function test_incumbent_card_shows_same_profile_format_as_selected_employee()
    {
        $this->occupiedRm1Promotion()
            ->assertSee('Profil Pejabat Petahana Saat Ini')
            ->assertSee('Kesiapan Talenta &amp; Masa Kerja', false)
            ->assertSee('Klasifikasi Karyawan');
    }

    public function test_incumbent_unit_tenure_is_shown_once_and_separate_from_candidate()
    {
        $c = $this->occupiedRm1Promotion(['tmt_unit_kerja' => now()->subYears(3)->toDateString()]);

        $this->assertSame(1, substr_count($c->html(), '3 th 0 bln'));
    }

    public function test_promotion_block_message_is_short()
    {
        $c = $this->blockedPromotion();

        $this->assertSame(
            'Formasi target saat ini diduduki oleh '.Employee::where('jabatan', 'Kasubag B')->value('nama').' (RM-2). Pejabat petahana perlu dipindahkan lebih dulu melalui Mutasi Berantai.',
            $c->instance()->swapIncompatibilityMessage
        );
        $c->assertDontSee('Swap Bilateral');
    }

    public function test_same_unit_promotion_does_not_claim_unit_tenure_compliance()
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun Sawit A',
            'golongan' => 'IIIA',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYear()->toDateString(),
        ]);
        Position::factory()->create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'PROMOSI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Askep Tanaman')
            ->assertDontSee('Masa Dinas Unit Memenuhi Syarat')
            ->assertSee('Masa Dinas: 1 th 0 bln')
            ->assertDontSee('1.0 thn');
    }

    public function test_mutasi_form_has_no_decorative_tags_or_developer_copy()
    {
        $this->blockedPromotion()
            ->assertDontSee('Asal Posisi')
            ->assertDontSee('Tujuan Penempatan')
            ->assertDontSee('SK Penugasan Individual')
            ->assertDontSee('Klausul SK Direksi')
            ->assertSee('Lihat 9-Box')
            ->assertDontSee('9-Box Lengkap')
            // unblocked state shows the action-bar subline, which must not use developer wording either
            ->set('form.targetJabatan', 'Kasubag Kosong')
            ->set('form.alasanDispensasiDireksi', 'ND-012/DIR/2026')
            ->set('form.tanggalDispensasiDireksi', now()->toDateString())
            ->assertDontSee('atomik')
            ->assertSee('disimpan sekaligus');
    }

    public function test_retired_employee_is_blocked()
    {
        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten SDM',
            'unit_kerja' => 'Bagian SDM',
            'tanggal_pensiun' => now()->subMonth()->toDateString(),
        ]);

        $c = Livewire::test(MutasiProcess::class)->set('form.employeeNik', $emp->nik);

        $this->assertStringContainsString('batas usia pensiun', $c->instance()->blockingReason);
    }

    public function test_occupied_and_reserved_target_is_treated_as_reserved()
    {
        $empA = Employee::factory()->create(['jabatan' => 'Asisten A', 'unit_kerja' => 'Kebun Sawit A', 'rm_level' => 'RM-3']);
        Employee::factory()->create(['jabatan' => 'Asisten Afdeling 1', 'unit_kerja' => 'Kebun Sawit A', 'rm_level' => 'RM-3']);
        $successor = Employee::factory()->create(['jabatan' => 'Asisten C', 'unit_kerja' => 'Kebun Sawit B', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);

        // the occupied formasi is already promised to someone else by a scheduled mutation
        JobHistory::create([
            'employee_id' => $successor->id,
            'employee_nik' => $successor->nik,
            'jabatan' => 'Asisten Afdeling 1',
            'unit_kerja' => 'Kebun Sawit A',
            'tmt_awal' => now()->addMonth()->startOfMonth()->toDateString(),
            'sk_nomor' => 'SK/FUTURE/002',
            'sk_tanggal' => now()->toDateString(),
            'is_applied' => false,
        ]);

        $c = Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Kebun Sawit A')
            ->set('form.targetJabatan', 'Asisten Afdeling 1');

        $this->assertSame('reserved', $c->instance()->targetAnalysis['type']);
        $this->assertStringContainsString('dipesan', $c->instance()->blockingReason);
        $c->assertSee('Telah Dipesan')
            ->assertDontSee('Target Terisi: Tukar Jabatan 2-Arah');
    }

    public function test_non_occupancy_blocks_do_not_claim_chain_mutation_label()
    {
        $emp = Employee::factory()->create(['jabatan' => 'Posisi A', 'unit_kerja' => 'Bagian SDM', 'rm_level' => 'RM-2']);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $emp->nik)
            ->set('form.jenisMutasi', 'ROTASI')
            ->set('form.targetUnitKerja', 'Bagian SDM')
            ->set('form.targetJabatan', 'Posisi A')
            ->assertSee('Tidak Dapat Diproses')
            ->assertDontSee('Tidak Dapat Diproses Tunggal');
    }

    public function test_occupied_block_card_is_announced()
    {
        $this->blockedPromotion()
            ->assertSee('role="alert"', false)
            ->assertSeeHtmlInOrder(['role="alert"', 'Formasi Masih Terisi Pejabat Definitif']);
    }

    public function test_disabled_review_button_references_its_reason()
    {
        $empA = Employee::factory()->create(['nama' => 'Ahmad R', 'jabatan' => 'Asisten A', 'unit_kerja' => 'Kebun Sawit A', 'rm_level' => 'RM-3']);
        $empB = Employee::factory()->create(['nama' => 'Bambang S', 'jabatan' => 'Asisten B', 'unit_kerja' => 'Kebun Sawit B', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        Unit::where('nama', 'Kebun Sawit B')->first()?->positions()->syncWithoutDetaching(Position::where('nama', 'Asisten Afdeling 1')->pluck('id'));

        JobHistory::create([
            'employee_id' => $empB->id,
            'employee_nik' => $empB->nik,
            'jabatan' => 'Asisten Afdeling 1',
            'unit_kerja' => 'Kebun Sawit B',
            'tmt_awal' => now()->addMonth()->startOfMonth()->toDateString(),
            'sk_nomor' => 'SK/FUTURE/001',
            'sk_tanggal' => now()->toDateString(),
            'is_applied' => false,
        ]);

        Livewire::test(MutasiProcess::class)
            ->set('form.employeeNik', $empA->nik)
            ->set('form.jenisMutasi', 'MUTASI_UNIT')
            ->set('form.targetUnitKerja', 'Kebun Sawit B')
            ->set('form.targetJabatan', 'Asisten Afdeling 1')
            ->assertSee('aria-describedby="blocking-reason"', false)
            ->assertSee('id="blocking-reason"', false)
            ->assertSeeHtml('min-h-11');
    }

    public function test_action_controls_meet_touch_target()
    {
        $html = $this->blockedPromotion()->html();

        // Tombol tinjau di bar dan Batal: masing-masing min-h-11 (44px).
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'min-h-11'));
        // CTA Mutasi Berantai pada kartu formasi terisi dibuat ringkas (36px), tetap di atas minimum WCAG AA (24px).
        $this->assertStringContainsString('min-h-9', $html);
    }

    public function test_non_admin_cannot_mount_the_component(): void
    {
        $staff = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($staff)->test(MutasiProcess::class)->assertForbidden();
    }

    public function test_a_single_mutation_takes_effect_at_once_so_it_needs_the_approve_permission(): void
    {
        config(['hris.permissions' => [
            'mutasi.prepare' => ['admin', 'hr_staff'],
            'mutasi.approve' => ['admin', 'hr_manager'],
        ]]);

        // Preparer role: may draft chain proposals, but cannot issue a placement on its own
        Livewire::actingAs(User::factory()->create(['role' => 'hr_staff']))->test(MutasiProcess::class)->assertForbidden();

        Livewire::actingAs(User::factory()->create(['role' => 'hr_manager']))->test(MutasiProcess::class)->assertOk();
    }
}
