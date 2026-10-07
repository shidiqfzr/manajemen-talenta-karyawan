<?php

namespace Tests\Feature;

use App\Livewire\Admin\MutasiProcess;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MutasiProcessTest extends TestCase
{
    use RefreshDatabase;

    protected $empRM3;
    protected $empRM2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create positions
        Position::create(['nama' => 'Manajer Kebun', 'rm_level' => 'RM-1']);
        Position::create(['nama' => 'Askep Tanaman', 'rm_level' => 'RM-2']);
        Position::create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        // 2. Create employees and active jobs
        // RM-3 Employee (Current: Asisten Afdeling, Target Promosi: Askep Tanaman/Manajer Kebun)
        $this->empRM3 = Employee::create([
            'nik' => '11111111', 
            'nama' => 'Pekerja RM3',
            'jabatan' => 'Asisten Afdeling', 
            'unit_kerja' => 'Kebun Inti', 
            'level' => 'Karpim', 
            'golongan' => 'IIIA/00',
            'tmt_bekerja' => '2010-01-01',
        ]);
        JobHistory::create([
            'employee_nik' => '11111111', 'jabatan' => 'Asisten Afdeling', 'unit_kerja' => 'Kebun Inti',
            'tmt_awal' => '2020-01-01', 'jenis_mutasi' => 'PROMOSI', 'level' => 'Karpim', 'golongan' => 'IIIA/00',
        ]);

        // RM-2 Employee (Occupying target position)
        $this->empRM2 = Employee::create([
            'nik' => '22222222', 
            'nama' => 'Pekerja RM2',
            'jabatan' => 'Askep Tanaman', 
            'unit_kerja' => 'Kebun Inti', 
            'level' => 'Karpim', 
            'golongan' => 'IIIC/00',
            'tmt_bekerja' => '2010-01-01',
        ]);
        JobHistory::create([
            'employee_nik' => '22222222', 'jabatan' => 'Askep Tanaman', 'unit_kerja' => 'Kebun Inti',
            'tmt_awal' => '2019-01-01', 'jenis_mutasi' => 'PROMOSI', 'level' => 'Karpim', 'golongan' => 'IIIC/00',
        ]);
        
        // RM-1 position exists but vacant (Manajer Kebun, Unit: Pabrik X)
        // Creating an inactive history so it gets loaded in MutasiService::getKnownJabatan()
        JobHistory::create([
            'employee_nik' => '11111111', 'jabatan' => 'Manajer Kebun', 'unit_kerja' => 'Pabrik X',
            'tmt_awal' => '2015-01-01', 'tmt_akhir' => '2023-01-01', 'jenis_mutasi' => 'PROMOSI', 'level' => 'Karpim', 'golongan' => 'IIIA/00',
        ]);
    }

    public function test_promosi_filter_shows_higher_rm_levels()
    {
        Livewire::test(MutasiProcess::class)
            ->set('jenisMutasi', 'PROMOSI')
            ->set('employeeNik', $this->empRM3->nik)
            ->assertSet('jenisMutasi', 'PROMOSI')
            ->assertSee('Askep Tanaman') // RM-2 is visible
            ->assertSee('Manajer Kebun') // RM-1 is visible
            ->assertDontSeeHtml('value="Asisten Afdeling"'); // RM-3 is not visible (same level)
    }

    public function test_demosi_filter_shows_lower_rm_levels()
    {
        Livewire::test(MutasiProcess::class)
            ->set('jenisMutasi', 'DEMOSI')
            ->set('employeeNik', $this->empRM2->nik) // RM-2
            ->assertSee('Asisten Afdeling') // RM-3 is visible
            ->assertDontSeeHtml('value="Manajer Kebun"'); // RM-1 is not visible
    }

    public function test_rotasi_filter_shows_same_rm_level()
    {
        Livewire::test(MutasiProcess::class)
            ->set('jenisMutasi', 'ROTASI')
            ->set('employeeNik', $this->empRM2->nik) // RM-2
            ->assertSee('Askep Tanaman') // RM-2 is visible
            ->assertDontSeeHtml('value="Manajer Kebun"') // RM-1 not visible
            ->assertDontSeeHtml('value="Asisten Afdeling"'); // RM-3 not visible
    }

    public function test_mutasi_to_occupied_target_requires_swap_confirmation()
    {
        Livewire::test(MutasiProcess::class)
            ->set('jenisMutasi', 'PROMOSI')
            ->set('employeeNik', $this->empRM3->nik)
            ->set('targetJabatan', 'Askep Tanaman') // Occupied by empRM2
            ->set('targetUnitKerja', 'Kebun Inti')
            ->set('tmtAwal', '2026-01-01')
            ->call('submitMutasi')
            ->assertHasErrors(['swapConfirmed']);
    }

    public function test_successful_swap_mutation()
    {
        Livewire::test(MutasiProcess::class)
            ->set('jenisMutasi', 'PROMOSI')
            ->set('employeeNik', $this->empRM3->nik)
            ->set('targetJabatan', 'Askep Tanaman')
            ->set('targetUnitKerja', 'Kebun Inti')
            ->set('tmtAwal', '2026-01-01')
            ->set('swapConfirmed', true)
            ->call('submitMutasi')
            ->assertHasNoErrors()
            ->assertRedirect();
            
        // Assert positions swapped
        $this->assertEquals('Askep Tanaman', $this->empRM3->fresh()->jabatan);
        $this->assertEquals('Asisten Afdeling', $this->empRM2->fresh()->jabatan);
    }
}
