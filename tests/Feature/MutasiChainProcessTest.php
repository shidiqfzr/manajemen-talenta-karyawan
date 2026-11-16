<?php

namespace Tests\Feature;

use App\Livewire\Admin\MutasiChainProcess;
use App\Livewire\Admin\MutasiChainProposalTable;
use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\JobHistory;
use App\Models\MutasiChainProposal;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\JobVacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MutasiChainProcessTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_render_chain_mutasi_page(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('admin.mutasi.chain'))
            ->assertOk()
            ->assertSee('Mutasi Berantai & Penugasan Kolektif')
            ->assertSeeLivewire('admin.mutasi-chain-process');
    }

    public function test_inspecting_a_booked_vacant_target_flags_a_conflict_instead_of_crashing(): void
    {
        $unit = Unit::factory()->create(['nama' => 'Kebun Dipesan']);
        $seat = Position::factory()->create(['nama' => 'Posisi Dipesan', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Asal', 'rm_level' => 'RM-3']);
        $unit->positions()->attach($seat->id);

        $mover = Employee::factory()->create(['jabatan' => 'Asisten Asal', 'unit_kerja' => 'Kebun Dipesan']);
        $booker = Employee::factory()->create(['jabatan' => 'Jabatan Lain', 'unit_kerja' => 'Kebun Lain']);
        JobHistory::create(['employee_nik' => $booker->nik, 'jabatan' => 'Posisi Dipesan', 'unit_kerja' => 'Kebun Dipesan', 'tmt_awal' => now()->addMonth()->toDateString(), 'is_applied' => false]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $mover->nik)
            ->set('nodes.0.jenis_mutasi', 'ROTASI')
            ->set('nodes.0.target_unit_kerja', 'Kebun Dipesan')
            ->set('nodes.0.target_jabatan', 'Posisi Dipesan')
            ->assertSet('nodes.0.link_type', 'conflict')
            ->assertSet('nodes.0.occupant_nik', null);
    }

    public function test_renders_unit_combobox_component_when_employee_selected(): void
    {
        $emp = Employee::factory()->create(['nama' => 'Budi Santoso', 'unit_kerja' => 'Kebun Danau Salak']);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->assertSee('Pilih karyawan pada slot ini terlebih dahulu')
            ->set('nodes.0.employee_nik', $emp->nik)
            ->assertSeeHtml('id="unitKerjaInputMutasi_0"')
            ->assertSee('Filter Wilayah Kerja');
    }

    public function test_can_add_and_remove_nodes_in_chain(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->assertCount('nodes', 2)
            ->call('addNode')
            ->assertCount('nodes', 3)
            ->call('removeNode', 2)
            ->assertCount('nodes', 2)
            ->call('removeNode', 1) // Cannot remove if count <= 2
            ->assertHasErrors(['nodes']);
    }

    public function test_connect_occupant_to_chain_action(): void
    {
        $unitA = Unit::factory()->create(['nama' => 'Kebun A']);
        $unitB = Unit::factory()->create(['nama' => 'Kebun B']);
        $unitC = Unit::factory()->create(['nama' => 'Kebun C']);

        Position::factory()->create(['nama' => 'Asisten Kebun A', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Kebun B', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Kebun C', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create([
            'nama' => 'KARYAWAN A',
            'jabatan' => 'Asisten Kebun A',
            'unit_kerja' => 'Kebun A',
        ]);

        $empB = Employee::factory()->create([
            'nama' => 'KARYAWAN B',
            'jabatan' => 'Asisten Kebun B',
            'unit_kerja' => 'Kebun B',
        ]);

        $empC = Employee::factory()->create([
            'nama' => 'KARYAWAN C',
            'jabatan' => 'Asisten Kebun C',
            'unit_kerja' => 'Kebun C',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $empA->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun B')
            ->set('nodes.0.target_jabatan', 'Asisten Kebun B')
            // Node 0 should detect occupant empB
            ->assertSet('nodes.0.occupant_nik', $empB->nik)
            // One-click action to connect empB: Since Slot 1 is empty, it SMARTLY fills Slot 1 directly!
            ->call('connectOccupantToChain', 0)
            ->assertCount('nodes', 2)
            ->assertSet('nodes.1.employee_nik', $empB->nik)
            ->assertSet('nodes.1.employee_nama', $empB->nama)
            // Now set Slot 1 to target empC's position in Kebun C
            ->set('nodes.1.target_unit_kerja', 'Kebun C')
            ->set('nodes.1.target_jabatan', 'Asisten Kebun C')
            ->assertSet('nodes.1.occupant_nik', $empC->nik)
            // Connecting empC: Since Slot 1 is already full and no empty slots remain, it appends/inserts Slot 2!
            ->call('connectOccupantToChain', 1)
            ->assertCount('nodes', 3)
            ->assertSet('nodes.2.employee_nik', $empC->nik)
            ->assertSet('nodes.2.employee_nama', $empC->nama);
    }

    public function test_can_submit_chain_mutation_atomically(): void
    {
        $u1 = Unit::factory()->create(['nama' => 'Kebun Meliau']);
        $u2 = Unit::factory()->create(['nama' => 'Kebun Belian']);
        $u3 = Unit::factory()->create(['nama' => 'Kebun Emas']);

        Position::factory()->create(['nama' => 'Asisten Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Belian', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Emas', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Meliau', 'unit_kerja' => 'Kebun Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Belian', 'unit_kerja' => 'Kebun Belian', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empC = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Emas', 'unit_kerja' => 'Kebun Emas', 'tmt_unit_kerja' => now()->subYears(3)]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nomorSk', 'SK/KOLEKTIF/2026/001')
            ->set('tanggalSk', now()->toDateString())
            ->set('tmtAwal', now()->toDateString())
            // Node 0: A -> Belian
            ->set('nodes.0.employee_nik', $empA->nik)
            ->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'Kebun Belian')
            ->set('nodes.0.target_jabatan', 'Asisten Belian')
            // Node 1: B -> Emas
            ->set('nodes.1.employee_nik', $empB->nik)
            ->set('nodes.1.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.1.target_unit_kerja', 'Kebun Emas')
            ->set('nodes.1.target_jabatan', 'Asisten Emas')
            // Node 2: C -> Meliau (completing the closed cycle!)
            ->call('addNode', $empC->nik, 'Asisten Meliau', 'Kebun Meliau', 'MUTASI_UNIT')
            ->call('runTopologyAnalysis')
            ->assertSet('chainAnalysis.is_valid', true)
            ->assertSet('chainAnalysis.topology', 'cyclic')
            // Open review modal and verify it renders with ARIA dialog and anti double-click protection
            ->call('openReviewModal')
            ->assertSet('showReviewModal', true)
            ->assertSee('Konfirmasi Eksekusi Mutasi Kolektif')
            ->assertSee('SK/KOLEKTIF/2026/001')
            ->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')
            ->assertSeeHtml('wire:target="submitChain"')
            // Submit
            ->call('submitChain')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.mutasi.index', ['tab' => 'history']));

        // Verify employees were updated
        $empA->refresh();
        $this->assertEquals('Asisten Belian', $empA->jabatan);
        $this->assertEquals('Kebun Belian', $empA->unit_kerja);

        $empB->refresh();
        $this->assertEquals('Asisten Emas', $empB->jabatan);
        $this->assertEquals('Kebun Emas', $empB->unit_kerja);

        $empC->refresh();
        $this->assertEquals('Asisten Meliau', $empC->jabatan);
        $this->assertEquals('Kebun Meliau', $empC->unit_kerja);

        // Verify JobHistories have matching batch_id and correct chain_order
        $histories = JobHistory::where('nomor_sk', 'SK/KOLEKTIF/2026/001')->orderBy('chain_order')->get();
        $this->assertCount(3, $histories);
        $this->assertNotNull($histories[0]->batch_id);
        $this->assertEquals($histories[0]->batch_id, $histories[1]->batch_id);
        $this->assertEquals($histories[1]->batch_id, $histories[2]->batch_id);
        $this->assertEquals(1, $histories[0]->chain_order);
        $this->assertEquals(2, $histories[1]->chain_order);
        $this->assertEquals(3, $histories[2]->chain_order);
    }

    public function test_retired_employee_is_rejected_in_chain_mutation(): void
    {
        $u1 = Unit::factory()->create(['nama' => 'Kebun Meliau']);
        $u2 = Unit::factory()->create(['nama' => 'Kebun Belian']);

        Position::factory()->create(['nama' => 'Asisten Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Belian', 'rm_level' => 'RM-3']);

        $activeEmp = Employee::factory()->create([
            'nama' => 'Karyawan Aktif',
            'jabatan' => 'Asisten Meliau',
            'unit_kerja' => 'Kebun Meliau',
            'tanggal_pensiun' => now()->addYears(3),
        ]);

        $retiredEmp = Employee::factory()->create([
            'nama' => 'Karyawan Pensiun',
            'jabatan' => 'Asisten Belian',
            'unit_kerja' => 'Kebun Belian',
            'tanggal_pensiun' => now()->subMonth(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $activeEmp->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun Belian')
            ->set('nodes.0.target_jabatan', 'Asisten Belian')
            ->set('nodes.1.employee_nik', $retiredEmp->nik)
            ->set('nodes.1.target_unit_kerja', 'Kebun Meliau')
            ->set('nodes.1.target_jabatan', 'Asisten Meliau')
            ->call('runTopologyAnalysis')
            ->assertSet('chainAnalysis.is_valid', false)
            ->assertSee('telah memasuki Batas Usia Pensiun');
    }

    public function test_target_jabatan_cascading_and_rotasi_autolock(): void
    {
        $unitA = Unit::factory()->create(['nama' => 'Kebun Danau Salak']);
        $unitB = Unit::factory()->create(['nama' => 'PKS Ngabang']);

        $posA1 = Position::factory()->create(['nama' => 'Asisten Tanaman A', 'rm_level' => 'RM-3']);
        $posA2 = Position::factory()->create(['nama' => 'Asisten Afdeling B', 'rm_level' => 'RM-3']);
        $posB1 = Position::factory()->create(['nama' => 'Asisten Pengolahan', 'rm_level' => 'RM-3']);

        $unitA->positions()->attach([$posA1->id, $posA2->id]);
        $unitB->positions()->attach([$posB1->id]);

        $emp = Employee::factory()->create([
            'nama' => 'KARYAWAN TEST',
            'jabatan' => 'Asisten Tanaman A',
            'unit_kerja' => 'Kebun Danau Salak',
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $emp->nik);

        // 1. Without target_unit_kerja, available positions must be empty (disabled)
        $this->assertEmpty($component->instance()->getAvailableTargetPositionsForNode(0));

        // 2. When selecting ROTASI, target_unit_kerja automatically locks to Kebun Danau Salak
        $component->set('nodes.0.jenis_mutasi', 'ROTASI')
            ->assertSet('nodes.0.target_unit_kerja', 'Kebun Danau Salak');

        // Positions for ROTASI in Kebun Danau Salak should now be available
        $positions = $component->instance()->getAvailableTargetPositionsForNode(0);
        $this->assertContains('Asisten Tanaman A', $positions);
        $this->assertContains('Asisten Afdeling B', $positions);
        $this->assertNotContains('Asisten Pengolahan', $positions);

        // 3. Changing to MUTASI_UNIT and setting target unit to PKS Ngabang
        $component->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'PKS Ngabang');

        $positionsNgabang = $component->instance()->getAvailableTargetPositionsForNode(0);
        $this->assertContains('Asisten Pengolahan', $positionsNgabang);
        $this->assertNotContains('Asisten Afdeling B', $positionsNgabang);

        // 4. When selecting DEMOSI on an RM-3 employee where target unit has no lower Karpim positions
        // Must strictly return empty array and NOT leak entire company-wide positions
        $component->set('nodes.0.jenis_mutasi', 'DEMOSI');
        $positionsDemosi = $component->instance()->getAvailableTargetPositionsForNode(0);
        $this->assertEmpty($positionsDemosi);
        $component->assertSee('Tidak Ada Formasi Eselon Sesuai di Unit Ini')
            ->assertSee('Tidak ditemukan formasi yang memenuhi kualifikasi tingkat eselon');
    }

    public function test_ordered_flow_nodes_orders_by_causal_domino_path(): void
    {
        // Setup 3 units & positions
        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Unit::factory()->create(['nama' => 'Kebun C']);
        Unit::factory()->create(['nama' => 'Kebun Vacant']);

        Position::factory()->create(['nama' => 'Pos A', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Pos B', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Pos C', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Pos Vacant', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Pos A', 'unit_kerja' => 'Kebun A']);
        $empB = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Pos B', 'unit_kerja' => 'Kebun B']);
        $empC = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Pos C', 'unit_kerja' => 'Kebun C']);

        // Admin enters nodes out of order:
        // Slot 0: C -> Pos Vacant (Filling vacant spot)
        // Slot 1: A -> Pos B (Occupied by B)
        // Slot 2: B -> Pos C (Occupied by C)
        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            // Slot 0
            ->set('nodes.0.employee_nik', $empC->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun Vacant')
            ->set('nodes.0.target_jabatan', 'Pos Vacant')
            // Slot 1
            ->set('nodes.1.employee_nik', $empA->nik)
            ->set('nodes.1.target_unit_kerja', 'Kebun B')
            ->set('nodes.1.target_jabatan', 'Pos B')
            // Add Slot 2
            ->call('addNode', $empB->nik, 'Pos C', 'Kebun C', 'MUTASI_UNIT');

        $ordered = $component->instance()->getOrderedFlowNodes();

        // Causal flow must be: A (Slot 1) -> B (Slot 2) -> C (Slot 0) -> Vacant
        $this->assertCount(3, $ordered);
        $this->assertEquals($empA->nik, $ordered[0]['employee_nik']);
        $this->assertEquals(2, $ordered[0]['slot_number']); // #Slot 2

        $this->assertEquals($empB->nik, $ordered[1]['employee_nik']);
        $this->assertEquals(3, $ordered[1]['slot_number']); // #Slot 3

        $this->assertEquals($empC->nik, $ordered[2]['employee_nik']);
        $this->assertEquals(1, $ordered[2]['slot_number']); // #Slot 1
    }

    public function test_sticky_bar_displays_contextual_validation_error_when_invalid(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nomorSk', 'SK/KOLEKTIF/2026/001')
            // Click review while incomplete
            ->call('openReviewModal')
            ->assertHasErrors(['chain_topology'])
            ->assertSee('Pemberitahuan Validasi Rantai Mutasi')
            ->assertSee('Lengkapi minimal 2 karyawan beserta formasi dan unit kerja target')
            ->assertSee('Tinjau Konfigurasi');
    }

    public function test_submit_chain_successfully_with_empty_tmt_akhir_estimasi(): void
    {
        $unitA = Unit::factory()->create(['nama' => 'Kebun Danau Salak']);
        $unitB = Unit::factory()->create(['nama' => 'Kebun Pelaihari']);

        Position::factory()->create(['nama' => 'Asisten Tanaman', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create([
            'nama' => 'INDRAMED MANIHURUK',
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Danau Salak',
            'rm_level' => 'RM-3',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYears(3)->toDateString(),
        ]);

        $empB = Employee::factory()->create([
            'nama' => 'BUDI SANTOSO',
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Pelaihari',
            'rm_level' => 'RM-3',
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYears(3)->toDateString(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nomorSk', 'SK.DIR/REG5/MUT-KOLEKTIF/01/2026')
            ->set('tanggalSk', now()->toDateString())
            ->set('tmtAwal', now()->addMonth()->startOfMonth()->toDateString())
            // Node 0: empA -> Kebun Pelaihari / Asisten Tanaman (Occupied by empB)
            ->set('nodes.0.employee_nik', $empA->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun Pelaihari')
            ->set('nodes.0.target_jabatan', 'Asisten Tanaman')
            ->set('nodes.0.status_jabatan', 'Definitif')
            ->set('nodes.0.tmt_akhir_estimasi', '') // Deliberately set empty string
            // Node 1: empB -> Kebun Danau Salak / Asisten Afdeling (Occupied by empA - Cyclic Swap)
            ->set('nodes.1.employee_nik', $empB->nik)
            ->set('nodes.1.target_unit_kerja', 'Kebun Danau Salak')
            ->set('nodes.1.target_jabatan', 'Asisten Afdeling')
            ->set('nodes.1.status_jabatan', 'Definitif')
            ->set('nodes.1.tmt_akhir_estimasi', '') // Deliberately set empty string
            ->call('submitChain')
            ->assertHasNoErrors()
            ->assertSessionHas('mutasi_success');

        $this->assertDatabaseHas('job_histories', [
            'employee_nik' => $empA->nik,
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Pelaihari',
            'tmt_akhir_estimasi' => null,
        ]);

        $this->assertDatabaseHas('job_histories', [
            'employee_nik' => $empB->nik,
            'jabatan' => 'Asisten Afdeling',
            'unit_kerja' => 'Kebun Danau Salak',
            'tmt_akhir_estimasi' => null,
        ]);
    }

    public function test_can_open_and_close_talent_drawer(): void
    {
        $emp = Employee::factory()->create([
            'nama' => 'Ahmad Dahlan',
            'jabatan' => 'Asisten Tanaman',
            'unit_kerja' => 'Kebun Danau Salak',
            'golongan' => 'IIIA',
            'rm_level' => 'RM-3',
            'tanggal_dalam_jabatan' => '2022-01-01',
            'tmt_unit_kerja' => '2022-01-01',
        ]);

        Evaluation::create([
            'employee_nik' => $emp->nik,
            'kategori_9box' => 'Box 9: Star',
            'skor_smkbk_9box' => 95.5,
            'skor_cli_9box' => 90.0,
            'lembaga_asesmen' => 'Holding Assessment Center',
            'tanggal_pelaksanaan_asesmen' => '2025-06-01',
            'expired_asesmen' => '2028-06-01',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->assertSet('showTalentDrawer', false)
            ->call('openTalentDrawer', $emp->nik)
            ->assertSet('showTalentDrawer', true)
            ->assertSet('selectedTalentNik', $emp->nik)
            ->assertSet('selectedTalentData.kategori_9box', 'Box 9: Star')
            ->assertSet('selectedTalentData.is_tenure_unit_compliant', true)
            ->assertSeeHtml('Profil &amp; Rekam Jejak Talenta')
            ->assertSee('Box 9: Star')
            ->call('closeTalentDrawer')
            ->assertSet('showTalentDrawer', false)
            ->assertSet('selectedTalentNik', null);
    }

    public function test_renders_unified_compact_executive_flow_diagram(): void
    {
        $empA = Employee::factory()->create([
            'nama' => 'Budi Santoso',
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
            'rm_level' => 'RM-3',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->assertSee('Peta Alur Mutasi')
            ->assertDontSee('Diagram per Alur Mutasi')
            ->set('nodes.0.employee_nik', $empA->nik)
            ->assertSeeHtml('Slot 1')
            ->assertSee('Budi Santoso');
    }

    public function test_renders_stacked_cluster_cards_for_multi_cyclic_mutation(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 2', 'rm_level' => 'RM-3']);

        $empA = Employee::factory()->create(['nama' => 'SEPTIAN ANANDA', 'jabatan' => 'Asisten Afdeling 1', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empB = Employee::factory()->create(['nama' => 'AHMAD RIFAI', 'jabatan' => 'Asisten Afdeling 2', 'unit_kerja' => 'Kebun B', 'tmt_unit_kerja' => now()->subYears(3)]);

        Unit::factory()->create(['nama' => 'Kantor Direksi']);
        Position::factory()->create(['nama' => 'Kepala Bagian SDM', 'rm_level' => 'RM-1']);
        Position::factory()->create(['nama' => 'Kasubag Pengembangan SDM', 'rm_level' => 'RM-2']);

        $empC = Employee::factory()->create(['nama' => 'DJOKO PURWANTO', 'jabatan' => 'Kasubag Pengembangan SDM', 'unit_kerja' => 'Kantor Direksi', 'tmt_unit_kerja' => now()->subYears(3)]);
        $empD = Employee::factory()->create(['nama' => 'DONNY USMAN', 'jabatan' => 'Kepala Bagian SDM', 'unit_kerja' => 'Kantor Direksi', 'tmt_unit_kerja' => now()->subYears(3)]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->call('addNode')
            ->call('addNode')
            // Cycle 1: Septian <-> Ahmad (Cross-unit transfer)
            ->set('nodes.0.employee_nik', $empA->nik)
            ->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'Kebun B')
            ->set('nodes.0.target_jabatan', 'Asisten Afdeling 2')
            ->set('nodes.1.employee_nik', $empB->nik)
            ->set('nodes.1.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.1.target_unit_kerja', 'Kebun A')
            ->set('nodes.1.target_jabatan', 'Asisten Afdeling 1')
            // Cycle 2: Djoko <-> Donny (Same unit promotion/demosi)
            ->set('nodes.2.employee_nik', $empC->nik)
            ->set('nodes.2.jenis_mutasi', 'PROMOSI')
            ->set('nodes.2.target_unit_kerja', 'Kantor Direksi')
            ->set('nodes.2.target_jabatan', 'Kepala Bagian SDM')
            ->set('nodes.2.alasan_dispensasi_direksi', 'Rekomendasi asesmen Direksi 2026')
            ->set('nodes.2.tanggal_dispensasi_direksi', now()->toDateString())
            ->set('nodes.3.employee_nik', $empD->nik)
            ->set('nodes.3.jenis_mutasi', 'DEMOSI')
            ->set('nodes.3.target_unit_kerja', 'Kantor Direksi')
            ->set('nodes.3.target_jabatan', 'Kasubag Pengembangan SDM')
            ->assertSee('2 Rantai Tertutup (Tanpa Lowongan Baru)')
            ->assertSee('Alur Mutasi #1 • Tukar Jabatan (2 Karyawan)')
            ->assertSee('Alur Mutasi #2 • Tukar Jabatan (2 Karyawan)')
            ->assertSee('SEPTIAN ANANDA')
            ->assertSee('AHMAD RIFAI')
            ->assertSee('DJOKO PURWANTO')
            ->assertSee('Tanpa Lowongan Baru')
            // Open review modal and verify it groups by cluster
            ->set('nomorSk', 'SK/TEST/MULTI/001')
            ->set('tanggalSk', '2026-10-20')
            ->set('tmtAwal', '2026-11-01')
            ->call('openReviewModal')
            ->assertSet('showReviewModal', true)
            ->assertSee('Konfirmasi Eksekusi Mutasi Kolektif')
            ->assertSeeHtml('Alur Mutasi #1 • Tukar Jabatan (2 Karyawan)')
            ->assertSeeHtml('Alur Mutasi #2 • Tukar Jabatan (2 Karyawan)');
    }

    public function test_slot_list_is_grouped_per_cluster_with_global_slot_numbering(): void
    {
        Unit::factory()->create(['nama' => 'Kebun A']);
        Unit::factory()->create(['nama' => 'Kebun B']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 1', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 2', 'rm_level' => 'RM-3']);
        Unit::factory()->create(['nama' => 'Kebun C']);
        Unit::factory()->create(['nama' => 'Kebun D']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 3', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Afdeling 4', 'rm_level' => 'RM-3']);

        $a = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Afdeling 1', 'unit_kerja' => 'Kebun A', 'tmt_unit_kerja' => now()->subYears(3)]);
        $b = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Afdeling 2', 'unit_kerja' => 'Kebun B', 'tmt_unit_kerja' => now()->subYears(3)]);
        $c = Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Afdeling 3', 'unit_kerja' => 'Kebun C', 'tmt_unit_kerja' => now()->subYears(3)]);
        $d = Employee::factory()->create(['nama' => 'KARYAWAN D', 'jabatan' => 'Asisten Afdeling 4', 'unit_kerja' => 'Kebun D', 'tmt_unit_kerja' => now()->subYears(3)]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->call('addNode')
            ->call('addNode');

        // Draft phase: no analysed klaster yet, every slot sits in one drafting group
        $groups = $component->instance()->getSlotGroups();
        $this->assertCount(1, $groups);
        $this->assertSame('draft', $groups[0]['type']);
        $this->assertSame([0, 1, 2, 3], $groups[0]['indices']);
        $this->assertFalse($groups[0]['is_loose']); // sole drafting group is a normal start, not a warning
        $component->assertSee('Rincian Mutasi Karyawan')->assertDontSee('Daftar Mata Rantai');

        $component
            ->set('nodes.0.employee_nik', $a->nik)
            ->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'Kebun B')
            ->set('nodes.0.target_jabatan', 'Asisten Afdeling 2')
            ->set('nodes.1.employee_nik', $b->nik)
            ->set('nodes.1.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.1.target_unit_kerja', 'Kebun A')
            ->set('nodes.1.target_jabatan', 'Asisten Afdeling 1')
            ->set('nodes.2.employee_nik', $c->nik)
            ->set('nodes.2.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.2.target_unit_kerja', 'Kebun D')
            ->set('nodes.2.target_jabatan', 'Asisten Afdeling 4')
            ->set('nodes.3.employee_nik', $d->nik)
            ->set('nodes.3.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.3.target_unit_kerja', 'Kebun C')
            ->set('nodes.3.target_jabatan', 'Asisten Afdeling 3');

        $groups = $component->instance()->getSlotGroups();
        $this->assertCount(2, $groups);
        $this->assertSame([0, 1], $groups[0]['indices']);
        $this->assertSame([2, 3], $groups[1]['indices']);
        $this->assertTrue($groups[0]['is_zero_vacancy']);
        $this->assertFalse($groups[0]['is_loose']);

        // Each klaster has its own section header while slot numbers stay global (#1..#4)
        $component
            ->assertSeeHtml('id="slot-group-klaster-1"')
            ->assertSeeHtml('id="slot-group-klaster-2"')
            ->assertSeeHtml('id="node-card-3"');
    }

    public function test_draft_flow_never_draws_an_arrow_between_unlinked_slots(): void
    {
        foreach (['A', 'B', 'C', 'X'] as $u) {
            Unit::factory()->create(['nama' => "Kebun {$u}"]);
        }
        foreach (['1', '2', '3', '4', '5'] as $p) {
            Position::factory()->create(['nama' => "Asisten Afdeling {$p}", 'rm_level' => 'RM-3']);
        }

        $make = fn (string $nama, string $jabatan, string $unit) => Employee::factory()->create([
            'nama' => $nama, 'jabatan' => $jabatan, 'unit_kerja' => $unit, 'tmt_unit_kerja' => now()->subYears(3),
        ]);
        $a = $make('KARYAWAN A', 'Asisten Afdeling 1', 'Kebun A');
        $b = $make('KARYAWAN B', 'Asisten Afdeling 2', 'Kebun B');
        $c = $make('KARYAWAN C', 'Asisten Afdeling 3', 'Kebun C');
        $d = $make('KARYAWAN D', 'Asisten Afdeling 4', 'Kebun X');
        $e = $make('KARYAWAN E', 'Asisten Afdeling 5', 'Kebun X');

        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->call('addNode')
            ->call('addNode')
            ->call('addNode')
            // Cycle A -> B -> C -> A
            ->set('nodes.0.employee_nik', $a->nik)->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'Kebun B')->set('nodes.0.target_jabatan', 'Asisten Afdeling 2')
            ->set('nodes.1.employee_nik', $b->nik)->set('nodes.1.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.1.target_unit_kerja', 'Kebun C')->set('nodes.1.target_jabatan', 'Asisten Afdeling 3')
            ->set('nodes.2.employee_nik', $c->nik)->set('nodes.2.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.2.target_unit_kerja', 'Kebun A')->set('nodes.2.target_jabatan', 'Asisten Afdeling 1')
            // D takes E's seat, but E has no target yet (incomplete) -> topology is not valid
            ->set('nodes.3.employee_nik', $d->nik)->set('nodes.3.jenis_mutasi', 'ROTASI')
            ->set('nodes.3.target_unit_kerja', 'Kebun X')->set('nodes.3.target_jabatan', 'Asisten Afdeling 5')
            ->set('nodes.4.employee_nik', $e->nik)
            ->call('runTopologyAnalysis')
            ->assertSet('chainAnalysis.is_valid', false);

        $clusters = $component->instance()->getOrderedFlowClusters();

        // Two real flows; E (last of D -> E) must never be followed by an arrow into the cycle
        $this->assertCount(2, $clusters);
        $this->assertSame([3, 4], $clusters[1]['node_indices']);
        $this->assertSame([0, 1, 2], $clusters[0]['node_indices']);

        $this->assertEmpty(array_intersect($clusters[0]['node_indices'], $clusters[1]['node_indices']));
    }

    public function test_chain_node_renders_talent_readiness_metrics_strip_and_profile_card(): void
    {
        $emp = Employee::factory()->create([
            'nama' => 'AHMAD KURNIAWAN',
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
            'golongan' => 'IIIA',
            'rm_level' => 'RM-3',
            'tanggal_dalam_jabatan' => now()->subYears(2)->toDateString(),
            'tmt_unit_kerja' => now()->subYears(3)->toDateString(),
        ]);

        Evaluation::create([
            'employee_nik' => $emp->nik,
            'kategori_9box' => 'Box 8: Top Performer',
            'skor_smkbk_9box' => 92.0,
            'skor_cli_9box' => 88.5,
            'lembaga_asesmen' => 'Internal Assessment Center',
            'tanggal_pelaksanaan_asesmen' => '2025-05-10',
            'expired_asesmen' => '2028-05-10',
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $emp->nik)
            ->assertSet('nodes.0.profile_data.nik', $emp->nik)
            ->assertSet('nodes.0.profile_data.kategori_9box', 'Box 8: Top Performer')
            ->assertSeeHtml('Kesiapan Talenta &amp; Masa Kerja')
            ->assertSee('Masa Jabatan')
            ->assertSee('Masa Unit')
            ->assertSee('Usia (BUP)')
            ->assertSee('Box 8: Top Performer')
            ->assertSee('Lihat 9-Box');
    }

    public function test_chain_node_renders_unified_compliance_guard_for_tenure_and_promotion(): void
    {
        $unitA = Unit::factory()->create(['nama' => 'Kebun Meliau']);
        $unitB = Unit::factory()->create(['nama' => 'Kebun Belian']);

        Position::factory()->create(['nama' => 'Asisten Kebun Meliau', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Manajer Kebun Belian', 'rm_level' => 'RM-2']);

        // Employee with < 2 years in unit, targeting higher RM (promotion)
        $emp = Employee::factory()->create([
            'nama' => 'RIAN PERDANA',
            'jabatan' => 'Asisten Kebun Meliau',
            'unit_kerja' => 'Kebun Meliau',
            'golongan' => 'IIIA',
            'rm_level' => 'RM-3',
            'tanggal_dalam_jabatan' => now()->subMonths(6)->toDateString(),
            'tmt_unit_kerja' => now()->subMonths(8)->toDateString(),
        ]);

        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $emp->nik)
            ->set('nodes.0.jenis_mutasi', 'PROMOSI')
            ->set('nodes.0.target_unit_kerja', 'Kebun Belian')
            ->set('nodes.0.target_jabatan', 'Manajer Kebun Belian');

        // Both tenure and promotion guards should be visible and require dispensation
        $component->assertSee('Masa Dinas Unit Kerja')
            ->assertSee('Perlu Dispensasi')
            ->assertDontSee('Persetujuan Dispensasi Direksi')
            ->assertSee('Dasar Persetujuan Direksi')
            ->assertSee('(min. 2 tahun)')
            ->assertSee('kurang')
            ->assertSee('Kualifikasi Promosi')
            ->assertSee('belum terpenuhi')
            ->assertSet('nodes.0.dispensasi_direksi', false)
            // The footer names who still lacks Direksi evidence, same pattern as Mutasi Tunggal
            ->assertSee('RIAN PERDANA: dasar persetujuan Direksi belum dilengkapi.');

        // Setting dispensations
        // One piece of evidence (reference + approval date) covers every unmet rule of the node and fans out to the per-rule audit fields
        $today = now()->toDateString();
        $basis = 'Nota Dinas Direksi No 123 (disetujui '.now()->format('d/m/Y').')';

        $component->assertSee('Pengecualian regulasi yang diajukan')
            // Reference alone is not enough
            ->set('nodes.0.alasan_dispensasi_direksi', 'Nota Dinas Direksi No 123')
            ->assertSet('nodes.0.dispensasi_direksi', false)
            ->assertSet('nodes.0.dispensasi_tenure', false)
            ->set('nodes.0.tanggal_dispensasi_direksi', $today)
            ->assertSet('nodes.0.dispensasi_direksi', true)
            ->assertSet('nodes.0.dispensasi_tenure', true)
            ->assertSet('nodes.0.alasan_dispensasi_tenure', $basis)
            ->assertSet('nodes.0.dispensasi_promosi', true)
            ->assertSet('nodes.0.alasan_dispensasi_promosi', $basis)
            ->assertSet('nodes.0.is_dispensasi', true)
            ->assertSee('Dispensasi Tercatat')
            ->assertDontSee('dasar persetujuan Direksi belum dilengkapi.');

        // A future approval date is not evidence: the approval is withdrawn again
        $component->set('nodes.0.tanggal_dispensasi_direksi', now()->addDay()->toDateString())
            ->assertSet('nodes.0.dispensasi_direksi', false)
            ->assertSet('nodes.0.is_dispensasi', false);
    }

    public function test_chain_review_is_refused_without_dispensation_evidence_even_if_flags_are_tampered(): void
    {
        Unit::factory()->create(['nama' => 'Kebun Meliau']);
        Unit::factory()->create(['nama' => 'Kebun Belian']);
        Position::factory()->create(['nama' => 'Asisten Kebun Belian', 'rm_level' => 'RM-3']);

        $emp = Employee::factory()->create([
            'jabatan' => 'Asisten Kebun Meliau',
            'unit_kerja' => 'Kebun Meliau',
            'rm_level' => 'RM-3',
            'tmt_unit_kerja' => now()->subMonths(8)->toDateString(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $emp->nik)
            ->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'Kebun Belian')
            ->set('nodes.0.target_jabatan', 'Asisten Kebun Belian')
            // Client-side state forged to claim an approval that has no evidence behind it
            ->set('nodes.0.dispensasi_tenure', true)
            ->set('nodes.0.is_dispensasi', true)
            ->set('nodes.0.alasan_dispensasi_tenure', 'x')
            ->set('nomorSk', 'SK/TEST/001')
            ->set('tanggalSk', now()->toDateString())
            ->set('tmtAwal', now()->toDateString())
            ->call('openReviewModal')
            ->assertSet('showReviewModal', false)
            ->assertHasErrors(['chain_topology']);
    }

    /**
     * ── UAT SCENARIO 1: Comprehensive Multi-Unit Domino Chain ────────────────
     * Verifies an open domino chain across 3 units + 1 vacant target:
     * 1. Ahmad (Kebun Danau Salak) -> Kebun Pelaihari (Occupied by Bambang)
     * 2. Bambang (Kebun Pelaihari) -> Promosi to PKS Ngabang (Occupied by Candra)
     * 3. Candra (PKS Ngabang) -> Regional Office (Vacant Head Office position, with tenure dispensation)
     * Tests: Occupancy detection, 1-click connect, open domino topology, atomic batch commit,
     * sequential chain_order, and dynamic JobVacancyService lifecycle synchronization.
     */
    public function test_uat_multi_unit_domino_chain_with_promotion_dispensation_and_vacancy_lifecycle(): void
    {
        // 1. Setup 4 Units across plantation regions
        $uDanauSalak = Unit::factory()->create(['nama' => 'Kebun Danau Salak']);
        $uPelaihari = Unit::factory()->create(['nama' => 'Kebun Pelaihari']);
        $uNgabang = Unit::factory()->create(['nama' => 'PKS Ngabang']);
        $uRegOffice = Unit::factory()->create(['nama' => 'Regional Office']);

        // 2. Setup Positions and attach to units
        $posAsistenDS = Position::factory()->create(['nama' => 'Asisten Afdeling I', 'rm_level' => 'RM-3']);
        $posAsistenPel = Position::factory()->create(['nama' => 'Asisten Afdeling I', 'rm_level' => 'RM-3']);
        $posAskepNgabang = Position::factory()->create(['nama' => 'Asisten Kepala Pabrik', 'rm_level' => 'RM-2']);
        $posKasubagRO = Position::factory()->create(['nama' => 'Kepala Sub Bagian Pengolahan', 'rm_level' => 'RM-2']);

        $uDanauSalak->positions()->attach([$posAsistenDS->id]);
        $uPelaihari->positions()->attach([$posAsistenPel->id]);
        $uNgabang->positions()->attach([$posAskepNgabang->id]);
        $uRegOffice->positions()->attach([$posKasubagRO->id]);

        // 3. Setup Vacancy in Regional Office (Previously occupied by an employee who left)
        $pastEmp = Employee::factory()->create([
            'nama' => 'MANTAN PEJABAT RO',
            'jabatan' => 'Mantan Staf',
            'unit_kerja' => 'Pensiun',
        ]);
        JobHistory::create([
            'employee_nik' => $pastEmp->nik,
            'jabatan' => 'Kepala Sub Bagian Pengolahan',
            'unit_kerja' => 'Regional Office',
            'tmt_awal' => now()->subYears(2)->toDateString(),
            'tmt_akhir' => now()->subMonths(1)->toDateString(), // Departed, position is now vacant!
            'status_jabatan' => 'Definitif',
            'nomor_sk' => 'SK/PAST/001',
            'tanggal_sk' => now()->subMonths(1)->toDateString(),
            'is_applied' => true,
        ]);

        // Verify that Regional Office position is recognized as VACANT initially
        $vacancyService = app(JobVacancyService::class);
        $initialVacancies = $vacancyService->getVacancies(unitKerja: 'Regional Office');
        $this->assertTrue($initialVacancies->contains('jabatan', 'Kepala Sub Bagian Pengolahan'));

        // 4. Setup 3 Active Employees in the domino chain
        // Karyawan 1: Ahmad Zaki in Kebun Danau Salak (Tenure 3.5 years -> Compliant)
        $empAhmad = Employee::factory()->create([
            'nik' => '10001001',
            'nama' => 'AHMAD ZAKI',
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
            'rm_level' => 'RM-3',
            'level' => 'Karpim',
            'golongan' => 'IIIA/04',
            'tmt_unit_kerja' => now()->subYears(3)->subMonths(6)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYears(3)->subMonths(6)->toDateString(),
        ]);
        JobHistory::create([
            'employee_nik' => $empAhmad->nik,
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Danau Salak',
            'tmt_awal' => $empAhmad->tmt_unit_kerja,
            'status_jabatan' => 'Definitif',
            'is_applied' => true,
        ]);

        // Karyawan 2: Bambang Prakoso in Kebun Pelaihari (Tenure 2.5 years, High Performer for Promotion)
        $empBambang = Employee::factory()->create([
            'nik' => '10002002',
            'nama' => 'BAMBANG PRAKOSO',
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Pelaihari',
            'rm_level' => 'RM-3',
            'level' => 'Karpim',
            'golongan' => 'IIIB/08',
            'tmt_unit_kerja' => now()->subYears(2)->subMonths(6)->toDateString(),
            'tanggal_dalam_jabatan' => now()->subYears(2)->subMonths(6)->toDateString(),
        ]);
        JobHistory::create([
            'employee_nik' => $empBambang->nik,
            'jabatan' => 'Asisten Afdeling I',
            'unit_kerja' => 'Kebun Pelaihari',
            'tmt_awal' => $empBambang->tmt_unit_kerja,
            'status_jabatan' => 'Definitif',
            'is_applied' => true,
        ]);
        Evaluation::create([
            'employee_nik' => $empBambang->nik,
            'tahun' => now()->year - 1,
            'kinerja' => 92.5,
            'potensi' => 90.0,
            'box' => 8, // Top Performer
        ]);

        // Karyawan 3: Candra Kusuma in PKS Ngabang (Tenure 0.8 years -> Requires Tenure Dispensation)
        $empCandra = Employee::factory()->create([
            'nik' => '10003003',
            'nama' => 'CANDRA KUSUMA',
            'jabatan' => 'Asisten Kepala Pabrik',
            'unit_kerja' => 'PKS Ngabang',
            'rm_level' => 'RM-2',
            'level' => 'Karpim',
            'golongan' => 'IIIC/06',
            'tmt_unit_kerja' => now()->subMonths(10)->toDateString(), // < 2 years
            'tanggal_dalam_jabatan' => now()->subMonths(10)->toDateString(),
        ]);
        JobHistory::create([
            'employee_nik' => $empCandra->nik,
            'jabatan' => 'Asisten Kepala Pabrik',
            'unit_kerja' => 'PKS Ngabang',
            'tmt_awal' => $empCandra->tmt_unit_kerja,
            'status_jabatan' => 'Definitif',
            'is_applied' => true,
        ]);

        // 5. Initiate Livewire Component & configure Domino Chain
        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            // SK Header Metadata
            ->set('nomorSk', 'SK/DIR/KOL-UAT/2026/099')
            ->set('tanggalSk', now()->toDateString())
            ->set('tmtAwal', now()->toDateString())
            // Slot 0: Ahmad Zaki -> Kebun Pelaihari (Occupied by Bambang)
            ->set('nodes.0.employee_nik', $empAhmad->nik)
            ->set('nodes.0.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.0.target_unit_kerja', 'Kebun Pelaihari')
            ->set('nodes.0.target_jabatan', 'Asisten Afdeling I')
            // Verify Slot 0 detected occupant Bambang and flags as conflict (pending chain connection)
            ->assertSet('nodes.0.occupant_nik', $empBambang->nik)
            ->assertSet('nodes.0.link_type', 'conflict')
            // One-click connect: Bambang fills Slot 1, resolving conflict into 'chain'
            ->call('connectOccupantToChain', 0)
            ->assertSet('nodes.0.link_type', 'chain')
            ->assertSet('nodes.1.employee_nik', $empBambang->nik)
            ->assertSet('nodes.1.employee_nama', $empBambang->nama)
            // Slot 1: Bambang Promoted -> PKS Ngabang (Occupied by Candra)
            ->set('nodes.1.jenis_mutasi', 'PROMOSI')
            ->set('nodes.1.target_unit_kerja', 'PKS Ngabang')
            ->set('nodes.1.target_jabatan', 'Asisten Kepala Pabrik')
            // Verify Slot 1 detected occupant Candra as conflict
            ->assertSet('nodes.1.occupant_nik', $empCandra->nik)
            ->assertSet('nodes.1.link_type', 'conflict')
            // One-click connect: Candra fills Slot 2, resolving conflict into 'chain'
            ->call('connectOccupantToChain', 1)
            ->assertSet('nodes.1.link_type', 'chain')
            ->assertCount('nodes', 3)
            ->assertSet('nodes.2.employee_nik', $empCandra->nik)
            ->assertSet('nodes.2.employee_nama', $empCandra->nama)
            // Bambang's accelerated promotion to RM-2 needs Direksi dispensation, same as the tenure rule
            ->set('nodes.1.alasan_dispensasi_direksi', 'Rekomendasi asesmen Top Performer 2026')
            ->set('nodes.1.tanggal_dispensasi_direksi', now()->toDateString())
            // Slot 2: Candra -> Regional Office (Vacant Head Office position)
            ->set('nodes.2.jenis_mutasi', 'MUTASI_UNIT')
            ->set('nodes.2.target_unit_kerja', 'Regional Office')
            ->set('nodes.2.target_jabatan', 'Kepala Sub Bagian Pengolahan')
            // Verify Slot 2 detected VACANT target
            ->assertSet('nodes.2.link_type', 'vacant')
            // Candra has tenure < 2 years, apply tenure dispensation
            ->set('nodes.2.alasan_dispensasi_direksi', 'Kebutuhan mendesak Kantor Direksi per Nota Dinas No. 88/ND/2026')
            ->set('nodes.2.tanggal_dispensasi_direksi', now()->toDateString())
            // Run Topology Analysis
            ->call('runTopologyAnalysis')
            ->assertSet('chainAnalysis.is_valid', true)
            ->assertSet('chainAnalysis.topology', 'domino')
            ->assertCount('chainAnalysis.nodes_analysis', 3)
            ->assertCount('chainAnalysis.unfilled_vacancies', 1)
            ->assertCount('chainAnalysis.closed_vacancies', 1)
            // Verify Review Modal opens successfully
            ->call('openReviewModal')
            ->assertSet('showReviewModal', true)
            // Submit Atomic Chain Transaction
            ->call('submitChain')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.mutasi.index', ['tab' => 'history']));

        // 6. VERIFY DATABASE SNAPSHOT & ATOMICITY
        // Refresh all 3 employees
        $empAhmad->refresh();
        $this->assertEquals('Kebun Pelaihari', $empAhmad->unit_kerja);
        $this->assertEquals('Asisten Afdeling I', $empAhmad->jabatan);
        $this->assertEquals('RM-3', $empAhmad->rm_level);

        $empBambang->refresh();
        $this->assertEquals('PKS Ngabang', $empBambang->unit_kerja);
        $this->assertEquals('Asisten Kepala Pabrik', $empBambang->jabatan);
        $this->assertEquals('RM-2', $empBambang->rm_level); // Promoted to RM-2!

        $empCandra->refresh();
        $this->assertEquals('Regional Office', $empCandra->unit_kerja);
        $this->assertEquals('Kepala Sub Bagian Pengolahan', $empCandra->jabatan);
        $this->assertEquals('RM-2', $empCandra->rm_level);

        // Verify JobHistories audit trail
        $histories = JobHistory::where('nomor_sk', 'SK/DIR/KOL-UAT/2026/099')
            ->orderBy('chain_order')
            ->get();

        $this->assertCount(3, $histories);
        $batchId = $histories[0]->batch_id;
        $this->assertNotEmpty($batchId);
        // All 3 records MUST share the same atomic batch UUID
        $this->assertEquals($batchId, $histories[1]->batch_id);
        $this->assertEquals($batchId, $histories[2]->batch_id);

        // Sequence must be strictly topological (1, 2, 3)
        $this->assertEquals(1, $histories[0]->chain_order);
        $this->assertEquals('10001001', $histories[0]->employee_nik);

        $this->assertEquals(2, $histories[1]->chain_order);
        $this->assertEquals('10002002', $histories[1]->employee_nik);
        $this->assertEquals('PROMOSI', $histories[1]->jenis_mutasi);

        $this->assertEquals(3, $histories[2]->chain_order);
        $this->assertEquals('10003003', $histories[2]->employee_nik);
        $this->assertTrue((bool) $histories[2]->is_dispensasi);
        $this->assertStringContainsString('Nota Dinas No. 88/ND/2026', $histories[2]->alasan_dispensasi);

        // 7. VERIFY JOB VACANCY LIFECYCLE SYNCHRONIZATION
        // 7a. The vacated position left by Ahmad in Kebun Danau Salak MUST now appear as VACANT!
        $vacanciesDS = $vacancyService->getVacancies(unitKerja: 'Kebun Danau Salak');
        $this->assertTrue($vacanciesDS->contains('jabatan', 'Asisten Afdeling I'));
        $vacantDSItem = $vacanciesDS->firstWhere('jabatan', 'Asisten Afdeling I');
        $this->assertEquals('10001001', $vacantDSItem->last_occupant_nik);

        // 7b. The previously vacant position in Regional Office is now FILLED by Candra and MUST NOT appear as VACANT!
        $vacanciesRO = $vacancyService->getVacancies(unitKerja: 'Regional Office');
        $this->assertFalse($vacanciesRO->contains('jabatan', 'Kepala Sub Bagian Pengolahan'));
    }

    public function test_non_admin_cannot_mount_the_component(): void
    {
        $staff = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($staff)->test(MutasiChainProcess::class)->assertForbidden();
    }

    // ── Usulan Mutasi Berantai: draf, persetujuan empat mata, navigasi alur (DOMAIN.md §5.2.6) ──

    /**
     * Three employees whose seats form a closed rotation when A → Belian, B → Emas, C → Meliau.
     *
     * @return array{0: Employee, 1: Employee, 2: Employee}
     */
    private function makeRotationEmployees(): array
    {
        foreach (['Meliau', 'Belian', 'Emas'] as $name) {
            Unit::factory()->create(['nama' => "Kebun {$name}"]);
            Position::factory()->create(['nama' => "Asisten {$name}", 'rm_level' => 'RM-3']);
        }

        return [
            Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Meliau', 'unit_kerja' => 'Kebun Meliau', 'tmt_unit_kerja' => now()->subYears(3)]),
            Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Belian', 'unit_kerja' => 'Kebun Belian', 'tmt_unit_kerja' => now()->subYears(3)]),
            Employee::factory()->create(['nama' => 'KARYAWAN C', 'jabatan' => 'Asisten Emas', 'unit_kerja' => 'Kebun Emas', 'tmt_unit_kerja' => now()->subYears(3)]),
        ];
    }

    private function buildRotation($component, Employee $a, Employee $b, Employee $c)
    {
        return $component
            ->set('nomorSk', 'SK/KOLEKTIF/2026/090')
            ->set('tanggalSk', now()->toDateString())
            ->set('tmtAwal', now()->toDateString())
            ->set('nodes.0.employee_nik', $a->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun Belian')
            ->set('nodes.0.target_jabatan', 'Asisten Belian')
            ->set('nodes.1.employee_nik', $b->nik)
            ->set('nodes.1.target_unit_kerja', 'Kebun Emas')
            ->set('nodes.1.target_jabatan', 'Asisten Emas')
            ->call('addNode', $c->nik, 'Asisten Meliau', 'Kebun Meliau', 'MUTASI_UNIT');
    }

    public function test_draft_is_saved_and_restored_with_fresh_analysis(): void
    {
        Storage::fake('local');
        [$a, $b, $c] = $this->makeRotationEmployees();

        $component = $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)
            ->set('fileSk', UploadedFile::fake()->create('sk-kolektif.pdf', 120, 'application/pdf'))
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSee('Draf usulan tersimpan');

        $proposal = MutasiChainProposal::sole();
        $this->assertSame(MutasiChainProposal::STATUS_DRAFT, $proposal->status);
        $this->assertSame(3, $proposal->participant_count);
        Storage::disk('local')->assertExists($proposal->file_sk);
        $component->assertSet('proposalId', $proposal->id)->assertSet('fileSk', null);

        // Nothing moved yet
        $this->assertSame('Asisten Meliau', $a->refresh()->jabatan);

        // Reopening the draft rebuilds every participant and re-runs the analysis on current data
        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->assertSet('nomorSk', 'SK/KOLEKTIF/2026/090')
            ->assertCount('nodes', 3)
            ->assertSet('nodes.0.employee_nama', 'KARYAWAN A')
            ->assertSet('nodes.0.occupant_nik', $b->nik)
            ->assertSet('chainAnalysis.topology', 'cyclic')
            ->assertSee('Usulan Mutasi Berantai #'.$proposal->id)
            ->assertSee('Berkas SK sudah tersimpan pada usulan ini.')
            ->assertSee('Simpan Draf')
            ->assertSee('Tinjau &amp; Eksekusi Rantai Mutasi', false);
    }

    public function test_approval_policy_locks_the_submitted_proposal_until_another_admin_approves(): void
    {
        config(['hris.mutasi_chain.require_approval' => true]);
        [$a, $b, $c] = $this->makeRotationEmployees();

        $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)
            ->assertSet('reviewMode', 'submit')
            ->assertSee('Tinjau &amp; Ajukan Persetujuan', false)
            ->call('openReviewModal')
            ->assertSet('showReviewModal', true)
            ->assertSee('Konfirmasi Pengajuan Usulan Mutasi Kolektif')
            ->assertSeeHtml('wire:click="submitForApproval"')
            ->call('submitForApproval')
            ->assertHasNoErrors();

        $proposal = MutasiChainProposal::sole();
        $this->assertSame(MutasiChainProposal::STATUS_PENDING, $proposal->status);
        $this->assertSame('Asisten Meliau', $a->refresh()->jabatan, 'Submitting must not move anyone.');

        // The maker sees a locked proposal and may only withdraw it; the server refuses any change
        $makerView = fn () => Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class, ['usulan' => $proposal->id]);
        $makerView()
            ->assertSet('isLocked', true)
            ->assertSet('reviewMode', 'none')
            ->assertSee('Menunggu persetujuan admin lain')
            ->assertSee('Tarik Usulan')
            ->assertDontSee('Simpan Draf')
            ->assertSeeHtml('<fieldset disabled');
        $makerView()->set('nodes.0.target_jabatan', 'Asisten Emas')->assertForbidden();
        $makerView()->call('addNode')->assertForbidden();
        $makerView()->call('saveDraft')->assertForbidden();
        $this->assertSame('Asisten Belian', MutasiChainProposal::sole()->payload[0]['target_jabatan']);

        // Another admin approves: the SK is issued from the stored inputs
        $checker = User::factory()->create(['role' => 'admin', 'name' => 'Admin Penyetuju']);
        Livewire::actingAs($checker)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->assertSet('reviewMode', 'approve')
            ->assertSee('Kembalikan')
            ->call('openReviewModal')
            ->assertSee('Konfirmasi Persetujuan Usulan Mutasi Kolektif')
            ->assertSeeHtml('wire:click="approveProposal"')
            ->call('approveProposal')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.mutasi.index', ['tab' => 'history']));

        $this->assertSame('Asisten Belian', $a->refresh()->jabatan);
        $this->assertSame('Asisten Meliau', $c->refresh()->jabatan);
        $proposal->refresh();
        $this->assertSame(MutasiChainProposal::STATUS_EXECUTED, $proposal->status);
        $this->assertSame($checker->id, $proposal->decided_by);
        $this->assertSame(3, JobHistory::where('batch_id', $proposal->batch_id)->count());
    }

    public function test_maker_cannot_approve_and_direct_issue_is_refused_under_the_approval_policy(): void
    {
        config(['hris.mutasi_chain.require_approval' => true]);
        [$a, $b, $c] = $this->makeRotationEmployees();

        $component = $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)
            ->call('submitChain')
            ->assertHasErrors('chain_topology');
        $this->assertSame('Asisten Meliau', $a->refresh()->jabatan);

        $component->call('submitForApproval');
        $proposal = MutasiChainProposal::sole();

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->call('approveProposal')
            ->assertHasErrors('chain_topology');
        $this->assertSame(MutasiChainProposal::STATUS_PENDING, $proposal->refresh()->status);
    }

    public function test_checker_returns_the_proposal_with_a_reason_and_the_maker_can_revise_it(): void
    {
        config(['hris.mutasi_chain.require_approval' => true]);
        [$a, $b, $c] = $this->makeRotationEmployees();
        $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)->call('submitForApproval');
        $proposal = MutasiChainProposal::sole();

        $checker = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($checker)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->call('openRejectModal')
            ->assertSee('Kembalikan Usulan untuk Revisi')
            ->set('rejectReason', 'pendek')
            ->call('rejectProposal')
            ->assertHasErrors(['rejectReason' => 'min'])
            ->set('rejectReason', 'Formasi tujuan slot #2 perlu dicek ulang.')
            ->call('rejectProposal')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.mutasi.index', ['tab' => 'usulan']));

        $this->assertSame(MutasiChainProposal::STATUS_REJECTED, $proposal->refresh()->status);

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->assertSet('isLocked', false)
            ->assertSee('Dikembalikan untuk Revisi')
            ->assertSee('Formasi tujuan slot #2 perlu dicek ulang.')
            ->set('nomorSk', 'SK/KOLEKTIF/2026/091')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $this->assertSame(MutasiChainProposal::STATUS_DRAFT, $proposal->refresh()->status);
        $this->assertSame('SK/KOLEKTIF/2026/091', $proposal->nomor_sk);
    }

    public function test_drafts_stay_private_to_their_maker(): void
    {
        [$a, $b, $c] = $this->makeRotationEmployees();
        $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)->call('saveDraft');
        $proposal = MutasiChainProposal::sole();

        $otherAdmin = User::factory()->create(['role' => 'admin']);
        Livewire::actingAs($otherAdmin)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->assertNotFound();
    }

    public function test_direct_issue_from_a_saved_draft_closes_it_as_executed(): void
    {
        [$a, $b, $c] = $this->makeRotationEmployees();
        $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)->call('saveDraft');
        $proposal = MutasiChainProposal::sole();

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class, ['usulan' => $proposal->id])
            ->assertSet('reviewMode', 'execute')
            ->call('submitChain')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.mutasi.index', ['tab' => 'history']));

        $proposal->refresh();
        $this->assertSame(MutasiChainProposal::STATUS_EXECUTED, $proposal->status);
        $this->assertSame(3, JobHistory::where('batch_id', $proposal->batch_id)->count());
        $this->assertSame('Asisten Belian', $a->refresh()->jabatan);
    }

    public function test_each_flow_group_links_to_its_diagram_and_offers_to_fill_the_vacancy_it_leaves(): void
    {
        foreach (['Meliau', 'Belian', 'Emas'] as $name) {
            Unit::factory()->create(['nama' => "Kebun {$name}"]);
            Position::factory()->create(['nama' => "Asisten {$name}", 'rm_level' => 'RM-3']);
        }
        Position::factory()->create(['nama' => 'Asisten Kosong', 'rm_level' => 'RM-3']);
        $a = Employee::factory()->create(['nama' => 'KARYAWAN A', 'jabatan' => 'Asisten Meliau', 'unit_kerja' => 'Kebun Meliau', 'tmt_unit_kerja' => now()->subYears(3)]);
        $b = Employee::factory()->create(['nama' => 'KARYAWAN B', 'jabatan' => 'Asisten Belian', 'unit_kerja' => 'Kebun Belian', 'tmt_unit_kerja' => now()->subYears(3)]);

        // Open chain: A fills a vacant seat, B takes A's seat, so B's seat is left behind
        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $a->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun Emas')
            ->set('nodes.0.target_jabatan', 'Asisten Kosong')
            ->set('nodes.1.employee_nik', $b->nik)
            ->set('nodes.1.target_unit_kerja', 'Kebun Meliau')
            ->set('nodes.1.target_jabatan', 'Asisten Meliau')
            ->assertSet('chainAnalysis.topology', 'domino')
            ->assertSee('Rantai Terbuka (2 Karyawan)')
            ->assertSee('1 lowongan baru')
            ->assertSee('Isi Lowongan Tersisa')
            ->assertSeeHtml('id="alur-klaster-1"')
            ->assertSeeHtml('id="rincian-klaster-1"')
            ->assertSeeHtml('href="#alur-klaster-1"')
            ->assertSee('Lihat Rincian');

        $group = $component->instance()->getSlotGroups()[0];
        $this->assertSame(1, $group['cluster_id']);
        $this->assertSame('Asisten Belian', $group['unfilled_vacancies'][0]['jabatan']);
        $this->assertSame(0, $group['conflict_count']);

        // One click adds a slot that already targets the vacancy; the admin only picks who fills it
        $component->call('fillVacancy', 1, 0)
            ->assertCount('nodes', 3)
            ->assertSet('nodes.2.target_jabatan', 'Asisten Belian')
            ->assertSet('nodes.2.target_unit_kerja', 'Kebun Belian')
            ->assertSet('nodes.2.employee_nik', '')
            ->assertDispatched('chain-slot-added', index: 2);
    }

    public function test_flow_group_reports_a_target_still_held_outside_the_chain(): void
    {
        foreach (['Meliau', 'Belian'] as $name) {
            Unit::factory()->create(['nama' => "Kebun {$name}"]);
            Position::factory()->create(['nama' => "Asisten {$name}", 'rm_level' => 'RM-3']);
        }
        $a = Employee::factory()->create(['jabatan' => 'Asisten Meliau', 'unit_kerja' => 'Kebun Meliau']);
        Employee::factory()->create(['jabatan' => 'Asisten Belian', 'unit_kerja' => 'Kebun Belian']);

        $component = Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProcess::class)
            ->set('nodes.0.employee_nik', $a->nik)
            ->set('nodes.0.target_unit_kerja', 'Kebun Belian')
            ->set('nodes.0.target_jabatan', 'Asisten Belian')
            ->assertSee('1 formasi tujuan masih terisi');

        $this->assertSame(1, $component->instance()->getSlotGroups()[0]['conflict_count']);
    }

    public function test_a_role_that_may_only_approve_cannot_start_a_new_chain(): void
    {
        config(['hris.permissions' => ['mutasi.prepare' => ['admin'], 'mutasi.approve' => ['admin', 'hr_manager']]]);
        $manager = User::factory()->create(['role' => 'hr_manager']);

        Livewire::actingAs($manager)->test(MutasiChainProcess::class)->assertForbidden();
    }

    public function test_hub_lists_proposals_in_their_own_tab(): void
    {
        [$a, $b, $c] = $this->makeRotationEmployees();
        $this->buildRotation(Livewire::actingAs($this->adminUser)->test(MutasiChainProcess::class), $a, $b, $c)->call('saveDraft');

        $this->actingAs($this->adminUser)
            ->get(route('admin.mutasi.index', ['tab' => 'usulan']))
            ->assertOk()
            ->assertSee('Usulan Berantai')
            ->assertSee('Susun Usulan Baru')
            ->assertSeeLivewire('admin.mutasi-chain-proposal-table');

        Livewire::actingAs($this->adminUser)
            ->test(MutasiChainProposalTable::class)
            ->assertSee('SK/KOLEKTIF/2026/090')
            ->assertSee('Draf')
            ->assertSee('Lanjutkan Penyusunan')
            ->set('status', 'pending')
            ->assertDontSee('SK/KOLEKTIF/2026/090');

        // Another admin does not see someone else's unsubmitted draft
        Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(MutasiChainProposalTable::class)
            ->assertDontSee('SK/KOLEKTIF/2026/090');
    }
}
