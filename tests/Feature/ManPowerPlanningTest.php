<?php

namespace Tests\Feature;

use App\Livewire\Admin\ManPowerPlanning;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Models\User;
use App\Services\ManPowerPlanningService;
use App\Services\Mpp\MppSupplyPlanService;
use Carbon\Carbon;
use Database\Seeders\PositionUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Concerns\CreatesMppEmployees;
use Tests\TestCase;

class ManPowerPlanningTest extends TestCase
{
    use CreatesMppEmployees;
    use RefreshDatabase;

    /** Tanggal acuan tetap agar default "Posisi per" (bulan berjalan) dapat diuji. */
    private const TODAY = '2026-11-05 09:00:00';

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::TODAY);
        $this->user = User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function pasokan(array $plan, string $rm, string $bidang): int
    {
        return $plan['ckp'][$rm][$bidang] + $plan['talent_scouting'][$rm][$bidang] + $plan['rbb'][$rm][$bidang];
    }

    /** Simpan rencana pasokan satu tahun dengan perubahan sel tertentu, mis. ['rbb.RM-3.TAN' => 9]. */
    private function savePlan(int $year, array $changes): void
    {
        $plan = app(MppSupplyPlanService::class);
        $expected = $plan->matrixFor($year);
        $cells = $expected;
        foreach ($changes as $path => $value) {
            [$source, $rm, $bidang] = explode('.', $path);
            $cells[$source][$rm][$bidang] = $value;
        }
        $plan->save($year, $cells, $expected, 'Penyesuaian hasil rapat SDM', $this->user);
    }

    /** Pejabat RM-1 bidang TAN (heuristik "kebun"). */
    private function makeKarpim(string $nik, string $tanggalLahir, array $overrides = []): Employee
    {
        Position::firstOrCreate(
            ['nama' => 'Manajer Regulatif'],
            ['rm_level' => 'RM-1', 'is_overflow' => false, 'is_active' => true]
        );

        return Employee::create(array_merge([
            'nik' => $nik,
            'nama' => 'Pegawai '.$nik,
            'jabatan' => 'Manajer Regulatif',
            'unit_kerja' => 'Kebun A',
            'level' => 'Karpim',
            'golongan' => 'IIIA/00',
            'tmt_bekerja' => '2000-01-01',
            'tanggal_lahir' => $tanggalLahir,
            'status_penugasan' => 'Definitif',
        ], $overrides));
    }

    // ───────────────────────── Halaman & komponen ─────────────────────────

    public function test_authenticated_admin_can_access_mutasi_mpp_tab(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.mutasi.index', ['tab' => 'mpp']));

        $response->assertStatus(200);
        $response->assertSee('Perencanaan Formasi (MPP)');
        $response->assertSee('Matriks Formasi &amp; Kebutuhan Tenaga Kerja (MPP)', false);
    }

    public function test_authenticated_admin_can_access_mutasi_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('admin.mutasi.create'));

        $response->assertStatus(200);
        $response->assertSee('Buat Mutasi &amp; Penempatan Baru', false);
        $response->assertSee('Kembali ke Hub Mutasi');
    }

    public function test_matrix_renders_from_employee_records_only(): void
    {
        $this->seed(PositionUnitSeeder::class);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSee('Standar Formasi Baku')
            ->assertSee('451')
            ->assertSee('Posisi per')
            ->assertSee('RM-1')
            ->assertSee('JUMLAH')
            // Satu sumber kebenaran: tidak ada lagi sakelar angka acuan korporat.
            ->assertDontSee('Formasi Baku Korporasi')
            ->assertDontSee('Sync Karyawan Database');
    }

    public function test_mpp_component_can_filter_by_field(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('setFilterBidang', 'KEU')
            ->assertSet('filterBidang', 'KEU');
    }

    public function test_mpp_export_csv_streams_file(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('exportCsv')
            ->assertStatus(200)
            ->assertFileDownloaded('mpp_perencanaan_formasi_2026_2027_posisi_2026-11_'.Carbon::now()->format('Ymd_His').'.csv');
    }

    // ───────────────────────── Tahun & posisi per bulan ─────────────────────────

    public function test_default_position_is_the_current_month_of_the_current_year(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSet('planningYear', 2026)
            ->assertSet('asOfMonth', 11)
            ->assertSee('Realisasi per Nov 2026')
            ->assertSee('Pensiun sd Des 2026')
            ->assertSee('Kebutuhan 2027');
    }

    public function test_changing_the_year_resets_the_position_month(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('setPlanningYear', 2028)
            ->assertSet('asOfMonth', 1)
            ->assertSee('Realisasi per Jan 2028')
            ->assertSee('Kebutuhan 2029')
            ->assertSee('Kekurangan Formasi 2028')
            ->call('setPlanningYear', 2025)
            ->assertSet('asOfMonth', 12)
            ->assertSee('Realisasi per Des 2025')
            ->set('planningYear', 2027)
            ->assertSet('asOfMonth', 1);
    }

    public function test_month_filter_sets_the_position_date(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('setAsOfMonth', 6)
            ->assertSet('asOfMonth', 6)
            ->assertSee('Realisasi per Jun 2026')
            ->assertSee('Kursi kosong sejak MBT · Jul 2026 – Des 2027')
            ->call('setAsOfMonth', 13)
            ->assertSet('asOfMonth', 6)
            ->set('asOfMonth', 0)
            ->assertSet('asOfMonth', 6);
    }

    public function test_default_as_of_month_rule(): void
    {
        $service = new ManPowerPlanningService;

        $this->assertSame(11, $service->defaultAsOfMonth(2026));
        $this->assertSame(12, $service->defaultAsOfMonth(2025));
        $this->assertSame(1, $service->defaultAsOfMonth(2027));
    }

    // ───────────────────────── Data pegawai pada tanggal posisi ─────────────────────────

    public function test_realisasi_and_pensiun_follow_the_mbt_date_relative_to_the_position(): void
    {
        // Lahir 1971-09-02 -> pensiun 2027-09-01, MBT 2026-09-01.
        $this->makeKarpim('77770001', '1971-09-02');
        // Lahir 1972-05-14 -> pensiun 2028-05-01, MBT 2027-05-01.
        $this->makeKarpim('77770002', '1972-05-14');
        $service = new ManPowerPlanningService;

        $june = $service->getPositionCounts(2026, 6);
        $this->assertSame(2, $june['realisasi']['RM-1']['TAN']);
        $this->assertSame(1, $june['pensiun_t']['RM-1']['TAN']);
        $this->assertSame(1, $june['pensiun_t1']['RM-1']['TAN']);

        // Per Oktober pejabat pertama sudah bebas tugas: tidak lagi menjabat, kursinya sudah kosong.
        $october = $service->getPositionCounts(2026, 10);
        $this->assertSame(1, $october['realisasi']['RM-1']['TAN']);
        $this->assertSame(0, $october['pensiun_t']['RM-1']['TAN']);
        $this->assertSame(1, $october['pensiun_t1']['RM-1']['TAN']);
    }

    public function test_position_excludes_ended_contracts_and_later_hires(): void
    {
        $this->makeKarpim('77770003', '1990-01-10', [
            'status_karyawan' => 'Kontrak (PKWT)',
            'tanggal_berakhir_kontrak' => '2026-05-31',
        ]);
        $this->makeKarpim('77770004', '1990-01-10', ['tmt_bekerja' => '2026-08-01']);
        $service = new ManPowerPlanningService;

        $this->assertSame(1, $service->getPositionCounts(2026, 5)['realisasi']['RM-1']['TAN']);
        $this->assertSame(0, $service->getPositionCounts(2026, 6)['realisasi']['RM-1']['TAN']);
        $this->assertSame(1, $service->getPositionCounts(2026, 8)['realisasi']['RM-1']['TAN']);
    }

    public function test_overflow_positions_are_counted_outside_realisasi(): void
    {
        Position::create(['nama' => 'Manajer Regulatif', 'rm_level' => 'RM-1', 'is_overflow' => false, 'is_active' => true]);
        Position::create(['nama' => 'Advisor', 'rm_level' => 'RM-1', 'is_overflow' => true, 'is_active' => true]);
        $this->makeKarpim('99991111', '1990-01-10');
        $this->makeKarpim('99992222', '1990-01-10', ['jabatan' => 'Advisor', 'unit_kerja' => 'Kantor Direksi']);

        $counts = (new ManPowerPlanningService)->getPositionCounts(2026, 11);

        $this->assertSame(1, array_sum($counts['realisasi']['RM-1']));
        $this->assertSame(1, array_sum($counts['advisor']['RM-1']));
        $this->assertSame(0, array_sum($counts['diperban']['RM-1']));
    }

    // ───────────────────────── Standar formasi & pasokan ─────────────────────────

    public function test_standar_formasi_is_derived_from_seats(): void
    {
        $this->seed(PositionUnitSeeder::class);

        DB::table('position_unit')
            ->where('unit_id', Unit::where('nama', 'Kebun Tajati')->value('id'))
            ->where('position_id', Position::where('nama', 'Manajer')->value('id'))
            ->increment('kuota', 3);

        $service = new ManPowerPlanningService;
        $this->assertSame([
            'RM-1' => ['KEU' => 2, 'TAN' => 30, 'TEK' => 11, 'UMU' => 6],
            'RM-2' => ['KEU' => 4, 'TAN' => 41, 'TEK' => 12, 'UMU' => 15],
            'RM-3' => ['KEU' => 62, 'TAN' => 148, 'TEK' => 60, 'UMU' => 63],
        ], $service->standarFormasi());
        $this->assertSame(454, $service->getMppMatrix(2026, 11)['stats']['total_formasi']);
    }

    public function test_supply_rows_show_the_remaining_plan(): void
    {
        $this->savePlan(2028, ['rbb.RM-3.TAN' => 9]);

        $matrix = (new ManPowerPlanningService)->getMppMatrix(2028, 1)['matrix'];

        $this->assertSame(9, $matrix[8]['levels']['RM-3']['fields']['TAN']);
        $this->assertSame('Sisa RBB 2028', $matrix[8]['label']);
        $this->assertSame('Sisa CKP Internal', $matrix[6]['label']);
    }

    public function test_supply_already_hired_this_year_is_not_subtracted_twice(): void
    {
        // Rencana RBB 2026 RM-3 KEU = 8; satu pegawai RBB masuk Maret 2026 dan kini tercatat di Realisasi.
        $this->savePlan(2026, ['rbb.RM-3.KEU' => 8]);
        $this->makeAsistenKeuangan('88880001', ['jalur_masuk' => 'RBB', 'tmt_bekerja' => '2026-03-01']);
        $this->makeAsistenKeuangan('88880002', ['jalur_masuk' => 'Reguler', 'tmt_bekerja' => '2026-03-01']);
        $this->makeAsistenKeuangan('88880003', ['jalur_masuk' => 'RBB', 'tmt_bekerja' => '2025-03-01']);
        $service = new ManPowerPlanningService;

        $october = $service->getMppMatrix(2026, 10)['matrix'];
        $this->assertSame(3, $october[2]['levels']['RM-3']['fields']['KEU']);
        $this->assertSame(7, $october[8]['levels']['RM-3']['fields']['KEU']);

        // Sebelum pegawai itu masuk, seluruh rencana masih tersisa.
        $february = $service->getMppMatrix(2026, 2)['matrix'];
        $this->assertSame(8, $february[8]['levels']['RM-3']['fields']['KEU']);
    }

    public function test_ckp_is_realised_on_the_promotion_into_karpim_not_on_the_hire_date(): void
    {
        // Karpel sejak 2015, dipromosikan lewat CKP menjadi staf RM-3 pada 1 Maret 2026.
        $promoted = $this->makeAsistenKeuangan('88880031', ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2015-02-01', 'tanggal_dalam_jabatan' => '2026-03-01']);
        JobHistory::create([
            'employee_nik' => $promoted->nik, 'jabatan' => 'Krani Keuangan', 'unit_kerja' => 'Bagian Keuangan Uji',
            'level' => 'Karpel', 'tmt_awal' => '2015-02-01', 'jenis_mutasi' => 'PENUGASAN', 'is_applied' => true,
        ]);
        JobHistory::create([
            'employee_nik' => $promoted->nik, 'jabatan' => 'Asisten Keuangan Uji', 'unit_kerja' => 'Bagian Keuangan Uji',
            'level' => 'Karpim', 'tmt_awal' => '2026-03-01', 'jenis_mutasi' => 'PROMOSI', 'is_applied' => true,
        ]);
        // Penempatan pertama Karpim tercatat tanpa tanggal mulai bekerja pada master pegawai.
        $placed = $this->makeAsistenKeuangan('88880032', ['jalur_masuk' => 'CKP', 'tmt_bekerja' => null, 'tanggal_dalam_jabatan' => '2026-09-01']);
        JobHistory::create([
            'employee_nik' => $placed->nik, 'jabatan' => 'Asisten Keuangan Uji', 'unit_kerja' => 'Bagian Keuangan Uji',
            'level' => 'Karpim', 'tmt_awal' => '2026-09-01', 'jenis_mutasi' => 'PENUGASAN', 'is_applied' => true,
        ]);
        $service = new ManPowerPlanningService;
        $this->savePlan(2026, ['ckp.RM-3.KEU' => 2]);

        // Rencana CKP 2026 RM-3 KEU = 2: belum ada promosi per Februari, satu per Juni, dua per Oktober.
        $this->assertSame(0, $service->getPositionCounts(2026, 2)['hired_supply']['ckp']['RM-3']['KEU']);
        $this->assertSame(1, $service->getPositionCounts(2026, 6)['hired_supply']['ckp']['RM-3']['KEU']);
        $this->assertSame(2, $service->getPositionCounts(2026, 10)['hired_supply']['ckp']['RM-3']['KEU']);
        $this->assertSame(0, $service->getMppMatrix(2026, 10)['matrix'][6]['levels']['RM-3']['fields']['KEU']);
    }

    public function test_cancelled_or_scheduled_placements_do_not_realise_supply(): void
    {
        $employee = $this->makeAsistenKeuangan('88880033', ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2015-02-01']);
        JobHistory::where('employee_nik', $employee->nik)->forceDelete();
        // Terjadwal (belum diterapkan) dan dibatalkan: keduanya bukan realisasi.
        JobHistory::create([
            'employee_nik' => $employee->nik, 'jabatan' => 'Asisten Keuangan Uji', 'unit_kerja' => 'Bagian Keuangan Uji',
            'level' => 'Karpim', 'tmt_awal' => '2026-03-01', 'jenis_mutasi' => 'PROMOSI', 'is_applied' => false,
        ]);
        JobHistory::create([
            'employee_nik' => $employee->nik, 'jabatan' => 'Asisten Keuangan Uji', 'unit_kerja' => 'Bagian Keuangan Uji',
            'level' => 'Karpim', 'tmt_awal' => '2026-04-01', 'jenis_mutasi' => 'PROMOSI', 'is_applied' => true,
        ])->delete();

        $hired = (new ManPowerPlanningService)->getPositionCounts(2026, 10)['hired_supply'];

        // Tanpa penempatan Karpim yang sah, tanggal mulai bekerja 2015 dipakai: bukan realisasi 2026.
        $this->assertSame(0, $hired['ckp']['RM-3']['KEU']);
    }

    public function test_remaining_supply_never_goes_below_zero(): void
    {
        $this->savePlan(2026, ['ckp.RM-3.KEU' => 2]);
        foreach (['88880011', '88880012', '88880013'] as $nik) {
            $this->makeAsistenKeuangan($nik, ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2026-02-01']);
        }

        // Rencana CKP 2026 RM-3 KEU = 2, sudah masuk 3.
        $matrix = (new ManPowerPlanningService)->getMppMatrix(2026, 11)['matrix'];

        $this->assertSame(0, $matrix[6]['levels']['RM-3']['fields']['KEU']);
    }

    // ───────────────────────── Rumus Kebutuhan ─────────────────────────

    public function test_kebutuhan_follows_the_formula_and_totals_equal_the_cells(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->makeKarpim('77770010', '1971-12-20');
        $this->makeAsistenKeuangan('88880020', ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2026-04-01']);
        $this->savePlan(2027, ['talent_scouting.RM-3.TAN' => 2]);
        $matrix = (new ManPowerPlanningService)->getMppMatrix(2026, 11)['matrix'];
        $planT1 = app(MppSupplyPlanService::class)->matrixFor(2027);

        foreach (['RM-1', 'RM-2', 'RM-3'] as $rm) {
            foreach (['KEU', 'TAN', 'TEK', 'UMU'] as $bidang) {
                $cell = fn (int $no) => $matrix[$no]['levels'][$rm][$bidang] ?? $matrix[$no]['levels'][$rm]['fields'][$bidang];
                $base = $cell(1) - $cell(2) + $cell(4);
                $sisa = $cell(6) + $cell(7) + $cell(8);

                $this->assertSame($base - $sisa, $cell(9), "Kebutuhan T {$rm} {$bidang}");
                $this->assertSame($base + $cell(5) - ($sisa + $this->pasokan($planT1, $rm, $bidang)), $cell(10), "Kebutuhan T+1 {$rm} {$bidang}");
            }
        }

        foreach ($matrix as $row) {
            $this->assertSame(array_sum(array_column($row['levels'], 'subtotal')), $row['grand_total'], "Total baris {$row['no']}");
        }
    }

    public function test_shortage_and_surplus_totals_are_split(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->savePlan(2026, ['rbb.RM-3.KEU' => 200]);
        $service = new ManPowerPlanningService;
        $mpp = $service->getMppMatrix(2026, 11);

        foreach ([9, 10] as $no) {
            $row = $mpp['matrix'][$no];
            $cells = array_merge(...array_map(fn ($level) => array_values($level['fields']), array_values($row['levels'])));
            $this->assertSame(array_sum(array_filter($cells, fn ($v) => $v > 0)), $row['shortage_total']);
            $this->assertSame(-array_sum(array_filter($cells, fn ($v) => $v < 0)), $row['surplus_total']);
            $this->assertSame($row['grand_total'], $row['shortage_total'] - $row['surplus_total']);

            foreach ($row['levels'] as $level) {
                $this->assertSame($level['subtotal'], $level['shortage'] - $level['surplus']);
            }
        }
        $this->assertGreaterThan(0, $mpp['matrix'][9]['surplus_total']);
        $this->assertSame($mpp['matrix'][9]['shortage_total'], $mpp['stats']['total_kekurangan']);
        $this->assertSame($mpp['matrix'][9]['surplus_total'], $mpp['stats']['total_surplus']);

    }

    public function test_row_where_every_need_is_a_surplus(): void
    {
        // Tanpa kursi master (formasi 0) dan pasokan RM-3 besar: kekurangan 0, surplus positif, bersih negatif.
        $changes = [];
        foreach (['KEU', 'TAN', 'TEK', 'UMU'] as $bidang) {
            $changes["rbb.RM-3.{$bidang}"] = 999;
        }
        $this->savePlan(2028, $changes);

        $row = (new ManPowerPlanningService)->getMppMatrix(2028, 1)['matrix'][9];

        $this->assertSame(0, $row['shortage_total']);
        $this->assertGreaterThan(0, $row['surplus_total']);
        $this->assertLessThan(0, $row['grand_total']);
    }

    public function test_kebutuhan_t1_subtracts_t_and_t1_supply(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $service = new ManPowerPlanningService;
        $before = $service->getMppMatrix(2026, 11)['matrix'];

        $this->savePlan(2027, ['ckp.RM-3.KEU' => 3]);
        $after = $service->getMppMatrix(2026, 11)['matrix'];

        $cell = fn (array $m, int $row) => $m[$row]['levels']['RM-3']['fields']['KEU'];
        $this->assertSame($cell($before, 10) - 3, $cell($after, 10));
        $this->assertSame($cell($before, 9), $cell($after, 9));
    }

    public function test_unplanned_year_counts_supply_as_zero_and_flags_it(): void
    {
        $service = new ManPowerPlanningService;
        $mpp = $service->getMppMatrix(2029, 1);

        foreach ([3, 6, 7, 8] as $no) {
            $this->assertSame(0, $mpp['matrix'][$no]['grand_total']);
        }
        $this->assertTrue($mpp['stats']['supply_plan_missing']);
        $this->assertSame([2029, 2030], $mpp['stats']['supply_plan_missing_years']);
        // Tidak ada rencana bawaan sistem: tahun berjalan pun belum direncanakan sampai admin mengisinya.
        $this->assertSame([2026, 2027], $service->getMppMatrix(2026, 11)['stats']['supply_plan_missing_years']);
        $this->savePlan(2026, ['rbb.RM-3.KEU' => 1]);
        $this->assertSame([2027], $service->getMppMatrix(2026, 11)['stats']['supply_plan_missing_years']);
    }

    // ───────────────────────── Drilldown ─────────────────────────

    public function test_each_row_declares_what_its_cells_open(): void
    {
        $drills = array_map(fn ($row) => $row['drill'], (new ManPowerPlanningService)->getMppMatrix(2026, 11)['matrix']);

        $this->assertSame([
            1 => null, 2 => 'realisasi', 3 => null, 4 => 'pensiun_t', 5 => 'pensiun_t1',
            6 => 'supply_ckp', 7 => 'supply_talent_scouting', 8 => 'supply_rbb',
            9 => 'formula', 10 => 'formula', 11 => 'advisor', 12 => 'diperban',
        ], $drills);
    }

    public function test_every_personnel_cell_opens_exactly_its_count(): void
    {
        $this->makeKarpim('77770011', '1972-05-14'); // MBT 2027-05
        $this->makeKarpim('77770012', '1971-09-02'); // MBT 2026-09
        $this->makeKarpim('77770013', '1990-01-10');
        Position::create(['nama' => 'Advisor RM-1', 'rm_level' => 'RM-1', 'is_overflow' => true, 'is_active' => true]);
        $this->makeKarpim('77770014', '1990-01-10', ['jabatan' => 'Advisor RM-1']);
        $service = new ManPowerPlanningService;
        $matrix = $service->getMppMatrix(2026, 6)['matrix'];

        $checked = 0;
        foreach ($matrix as $row) {
            // Baris pasokan menampilkan sisa rencana; personilnya diuji pada test pasokan di bawah.
            if ($row['drill'] === null || $row['drill'] === 'formula' || str_starts_with($row['drill'], 'supply_')) {
                continue;
            }
            foreach ($row['levels'] as $rm => $level) {
                foreach ($level['fields'] as $bidang => $value) {
                    $this->assertCount($value, $service->getDrilldownEmployees($rm, $bidang, $row['drill'], 2026, 6), "{$row['label']} {$rm} {$bidang}");
                    $checked += $value;
                }
            }
        }
        $this->assertGreaterThanOrEqual(5, $checked);
        $this->assertSame('77770011', $service->getDrilldownEmployees('RM-1', 'TAN', 'pensiun_t1', 2026, 6)[0]['nik']);
    }

    public function test_supply_cells_report_plan_realised_and_remaining(): void
    {
        // Rencana CKP RM-3: KEU 1, TAN 2. Tiga promosi CKP KEU dan satu TAN sudah ditempatkan Maret 2026.
        $this->savePlan(2026, ['ckp.RM-3.KEU' => 1, 'ckp.RM-3.TAN' => 2]);
        foreach (['88880041', '88880042', '88880043'] as $nik) {
            $this->makeAsistenKeuangan($nik, ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2026-03-01']);
        }
        Position::create(['nama' => 'Asisten Afdeling Uji', 'rm_level' => 'RM-3', 'is_overflow' => false, 'is_active' => true]);
        $this->makeAsistenKeuangan('88880044', [
            'jalur_masuk' => 'CKP', 'tmt_bekerja' => '2026-03-01', 'jabatan' => 'Asisten Afdeling Uji', 'unit_kerja' => 'Kebun Uji',
        ]);
        $service = new ManPowerPlanningService;
        $mpp = $service->getMppMatrix(2026, 11);

        $this->assertSame(['rencana' => 1, 'terealisasi' => 3, 'sisa' => 0], $mpp['supply_breakdown']['ckp']['RM-3']['KEU']);
        $this->assertSame(['rencana' => 2, 'terealisasi' => 1, 'sisa' => 1], $mpp['supply_breakdown']['ckp']['RM-3']['TAN']);
        $this->assertSame(0, $mpp['matrix'][6]['levels']['RM-3']['fields']['KEU']);
        $this->assertSame(1, $mpp['matrix'][6]['levels']['RM-3']['fields']['TAN']);

        $people = $service->getDrilldownEmployees('RM-3', 'KEU', 'supply_ckp', 2026, 11);
        $this->assertCount(3, $people);
        $this->assertSame('01 Mar 2026', $people[0]['tanggal_penempatan']);
        $this->assertCount(0, $service->getDrilldownEmployees('RM-3', 'KEU', 'supply_rbb', 2026, 11));
    }

    public function test_supply_drawer_explains_plan_versus_realised(): void
    {
        $this->savePlan(2026, ['ckp.RM-3.KEU' => 1]);
        foreach (['88880051', '88880052', '88880053'] as $nik) {
            $this->makeAsistenKeuangan($nik, ['jalur_masuk' => 'CKP', 'tmt_bekerja' => '2026-03-01']);
        }

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            // Sisa 0 tetapi ada realisasi: sel tetap dapat dibuka agar kelebihan realisasi terlihat.
            ->assertSeeHtml("openDrilldown('RM-3', 'KEU', 6)")
            ->assertDontSeeHtml("openDrilldown('RM-3', 'TAN', 6)")
            ->call('openDrilldown', 'RM-3', 'KEU', 6)
            ->assertSet('drilldownMode', 'supply')
            ->assertSet('drilldownSupply', ['rencana' => 1, 'terealisasi' => 3, 'sisa' => 0])
            ->assertCount('drilldownEmployees', 3)
            ->assertSee('Rincian Pasokan CKP Internal')
            ->assertSee('Terealisasi melebihi rencana: 2')
            ->assertSee('3 Personil')
            ->assertSee('Export CSV');
    }

    public function test_supply_and_formula_drawers_share_one_breakdown_card(): void
    {
        $this->savePlan(2026, ['ckp.RM-3.UMU' => 4]);

        $component = Livewire::actingAs($this->user)->test(ManPowerPlanning::class);

        // Operator tampil sebagai kolom sendiri di antara suku, bukan awalan label.
        $component->call('openDrilldown', 'RM-3', 'UMU', 6)
            ->assertSeeHtml('data-breakdown-card="supply"')
            ->assertSee('Rincian Rencana vs Realisasi Pasokan')
            // Penanda ASCII: respons Livewire setelah call() berupa JSON yang meng-escape karakter "−".
            ->assertSeeHtmlInOrder(['Rencana 2026', 'data-op="minus"', 'Terealisasi', 'data-op="equals"', 'Sisa'])
            ->assertDontSee('- Terealisasi');

        $component->call('closeDrilldown')
            ->call('openDrilldown', 'RM-3', 'UMU', 9)
            ->assertSeeHtml('data-breakdown-card="formula"')
            ->assertSee('Rincian Formula Kebutuhan Bersih')
            ->assertSeeHtmlInOrder([
                'data-breakdown-card="formula"', 'Formasi', 'data-op="minus"', 'Realisasi', 'data-op="plus"', 'Pensiun',
                'data-op="minus"', 'Pasokan', 'data-op="equals"', 'Kebutuhan',
            ])
            ->assertDontSee('- Realisasi')
            ->assertDontSee('= Kebutuhan');
    }

    public function test_opening_a_drawer_reads_the_employee_table_once(): void
    {
        $this->makeKarpim('77770061', '1990-01-10');
        $component = Livewire::actingAs($this->user)->test(ManPowerPlanning::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $component->call('openDrilldown', 'RM-1', 'TAN', 2);
        $employeeScans = collect(DB::getQueryLog())->pluck('query')
            ->filter(fn (string $q) => preg_match('/from\s+"?employees"?\s*(where\s+"?employees"?\."?deleted_at"?\s+is\s+null)?\s*$/i', trim($q)))
            ->count();
        DB::disableQueryLog();

        // Matriks drawer, daftar personil, dan render ulang memakai satu pembacaan data pegawai.
        $this->assertSame(1, $employeeScans);
        $component->assertCount('drilldownEmployees', 1);
    }

    public function test_plan_cells_do_not_open_a_drawer_and_kebutuhan_opens_the_formula(): void
    {
        $this->seed(PositionUnitSeeder::class);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('openDrilldown', 'RM-1', 'TAN', 1)
            ->assertSet('showDrilldown', false)
            ->call('openDrilldown', 'RM-3', 'KEU', 9)
            ->assertSet('showDrilldown', true)
            ->assertSet('drilldownEmployees', [])
            ->assertSee('Rincian Formula Kebutuhan')
            ->assertDontSee('0 Personil')
            ->assertDontSee('Export CSV');
    }

    public function test_drilldown_formula_for_t1_uses_cumulative_remaining_supply(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->savePlan(2027, ['ckp.RM-3.KEU' => 3]);
        $planT = app(MppSupplyPlanService::class)->matrixFor(2026);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('openDrilldown', 'RM-3', 'KEU', 10)
            ->assertSet('drilldownFormula.pasokan', $this->pasokan($planT, 'RM-3', 'KEU') + 3);
    }

    public function test_reference_cells_show_their_values_without_a_drilldown(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->savePlan(2026, ['ckp.RM-3.TAN' => 7]);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSeeHtml('data-cell="1-RM-1-TAN">27<')
            ->assertSeeHtml('data-cell="6-RM-3-TAN">7<')
            ->assertDontSeeHtml("openDrilldown('RM-1', 'TAN', 1)");
    }

    // ───────────────────────── Tampilan ─────────────────────────

    public function test_kebutuhan_kpi_shows_shortage_surplus_and_net(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $stats = (new ManPowerPlanningService)->getMppMatrix(2026, 11)['stats'];

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSee('Kekurangan Formasi 2026')
            ->assertSee('Surplus '.$stats['total_surplus'])
            ->assertSee('Kebutuhan Bersih '.sprintf('%+d', $stats['total_kebutuhan']));
    }

    public function test_pensiun_kpi_names_the_mbt_window(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSee('Kursi kosong sejak MBT · Des 2026 – Des 2027')
            ->assertDontSee('BUP periode')
            ->call('setAsOfMonth', 12)
            ->assertSee('Kursi kosong sejak MBT · Jan 2027 – Des 2027');
    }

    public function test_kebutuhan_totals_show_net_with_the_split_only_when_both_exist(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $this->savePlan(2026, ['rbb.RM-3.KEU' => 200]);
        $row = (new ManPowerPlanningService)->getMppMatrix(2026, 11)['matrix'][9];
        $this->assertGreaterThan(0, $row['surplus_total']);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSee('kurang '.$row['shortage_total'].' · surplus '.$row['surplus_total'])
            ->assertDontSee('−0')
            ->assertDontSee('bersih +');
    }

    public function test_kpi_cards_follow_the_shared_card_rules(): void
    {
        $this->seed(PositionUnitSeeder::class);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertDontSeeHtml('absolute bottom-0 left-0 right-0 h-1')
            ->assertDontSeeHtml('text-teal-700');
    }

    public function test_rm_band_labels_follow_the_domain_definition(): void
    {
        $this->assertSame(['RM-1', 'RM-2', 'RM-3'], array_keys(ManPowerPlanningService::RM_BANDS));

        $component = Livewire::actingAs($this->user)->test(ManPowerPlanning::class);

        foreach (ManPowerPlanningService::RM_BANDS as $band) {
            $component->assertSee($band['short'])->assertSee($band['positions']);
        }
        $component->assertDontSee('Manajer / Kabag')
            ->assertDontSee('Manajer Kebun / Kepala Bagian / Askep');
    }

    public function test_formasi_badges_follow_the_master_seats(): void
    {
        $this->seed(PositionUnitSeeder::class);
        DB::table('position_unit')
            ->where('unit_id', Unit::where('nama', 'Kebun Tajati')->value('id'))
            ->where('position_id', Position::where('nama', 'Manajer')->value('id'))
            ->increment('kuota', 3);

        $this->actingAs($this->user)
            ->get(route('admin.mutasi.index', ['tab' => 'mpp']))
            ->assertOk()
            // Nilai lencana diperiksa langsung; pencarian teks "451" di seluruh halaman bisa kena ID acak Livewire.
            ->assertViewHas('mppFormasiTotal', 454)
            ->assertSeeInOrder(['Perencanaan Formasi (MPP)', '454']);
    }

    // ───────────────────────── Rencana pasokan ─────────────────────────

    public function test_missing_plan_notice_is_shown(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSee('Rencana pasokan 2026 belum diisi. Angka pasokan dihitung 0.')
            ->assertSee('Rencana pasokan 2027 belum diisi. Angka pasokan dihitung 0.')
            ->assertSee('Kelola Rencana Pasokan')
            // Editor dibuka pada tahun dan posisi yang sama dengan matriks.
            ->assertSeeHtml("open-mpp-supply-plan-editor', { year: 2026, month: 11 }");

        $this->savePlan(2026, ['ckp.RM-3.KEU' => 1]);
        $this->savePlan(2027, ['ckp.RM-3.KEU' => 1]);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertDontSee('belum diisi. Angka pasokan dihitung 0.');
    }

    public function test_supply_plan_provenance_is_shown(): void
    {
        // Belum ada rencana: tidak ada keterangan asal-usul, hanya pemberitahuan belum diisi.
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertDontSee('diperbarui oleh')
            ->assertDontSee('angka acuan awal sistem');

        Carbon::setTestNow('2026-11-05 14:20:00');
        $this->savePlan(2026, ['ckp.RM-3.KEU' => 5]);

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->assertSee('Rencana pasokan 2026 diperbarui oleh '.$this->user->name.' · 05 Nov 2026 14:20');
    }

    public function test_matrix_confirms_a_saved_supply_plan(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->dispatch('mpp-supply-plan-saved', year: 2027)
            ->assertSee('Rencana pasokan 2027 berhasil disimpan.');
    }

    public function test_matrix_listens_for_saved_supply_plans(): void
    {
        $component = Livewire::actingAs($this->user)->test(ManPowerPlanning::class);

        // getListeners() bersifat protected pada Livewire 3; dibaca lewat closure terikat.
        $listeners = (fn () => $this->getListeners())->call($component->instance());

        $this->assertArrayHasKey('mpp-supply-plan-saved', $listeners);
    }

    // ───────────────────────── Ekspor & keamanan ─────────────────────────

    public function test_mpp_export_csv_has_shortage_and_surplus_columns(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $matrix = (new ManPowerPlanningService)->getMppMatrix(2026, 11)['matrix'];

        $component = Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('exportCsv');

        $csv = ltrim(base64_decode($component->effects['download']['content']), "\xEF\xBB\xBF");
        $lines = array_map('str_getcsv', array_values(array_filter(explode("\n", $csv))));
        $header = $lines[0];
        $shortageCol = array_search('TOTAL KEKURANGAN', $header, true);
        $surplusCol = array_search('TOTAL SURPLUS', $header, true);
        $this->assertNotFalse($shortageCol);
        $this->assertNotFalse($surplusCol);

        $byLabel = collect(array_slice($lines, 1))->keyBy(1);
        $this->assertSame((string) $matrix[9]['shortage_total'], $byLabel['Kebutuhan 2026'][$shortageCol]);
        $this->assertSame((string) $matrix[9]['surplus_total'], $byLabel['Kebutuhan 2026'][$surplusCol]);
        $this->assertSame('', $byLabel['Realisasi per Nov 2026'][$shortageCol]);
        $this->assertSame('', $byLabel['Realisasi per Nov 2026'][$surplusCol]);
    }

    public function test_non_admin_cannot_export_mpp_data(): void
    {
        $staff = User::factory()->create(['role' => 'user']);

        Livewire::actingAs($staff)->test(ManPowerPlanning::class)->call('exportCsv')->assertForbidden();
        Livewire::actingAs($staff)->test(ManPowerPlanning::class)->call('exportDrilldownCsv')->assertForbidden();
    }

    public function test_mpp_exports_are_written_to_the_audit_log_without_nik(): void
    {
        $this->makeKarpim('77770007', '1990-01-10');
        Log::spy();

        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('openDrilldown', 'RM-1', 'TAN', 2)
            ->call('exportDrilldownCsv')
            ->call('exportCsv');

        foreach (['Ekspor rincian personil MPP', 'Ekspor matriks MPP'] as $message) {
            Log::shouldHaveReceived('info')
                ->withArgs(fn ($msg, $ctx = []) => $msg === $message
                    && ($ctx['user_id'] ?? null) === $this->user->id
                    && ($ctx['posisi_per'] ?? null) === '2026-11'
                    && ! str_contains(json_encode($ctx), '77770007'))
                ->once();
        }
    }

    public function test_mpp_component_ignores_unknown_filters_and_drilldown_targets(): void
    {
        Livewire::actingAs($this->user)
            ->test(ManPowerPlanning::class)
            ->call('setFilterBidang', 'XYZ')
            ->assertSet('filterBidang', 'ALL')
            ->call('setPlanningYear', 1900)
            ->assertSet('planningYear', 2026)
            ->set('planningYear', 3000)
            ->assertSet('planningYear', 2026)
            ->call('openDrilldown', 'RM-9', 'KEU', 2)
            ->assertSet('showDrilldown', false);
    }

    // ───────────────────────── Klasifikasi RM & bidang ─────────────────────────

    public function test_determine_bidang_prefers_the_seat(): void
    {
        $this->seed(PositionUnitSeeder::class);

        $employee = new Employee(['jabatan' => 'Asisten Tata Usaha', 'unit_kerja' => 'Distrik Petani Mitra']);

        $this->assertSame('KEU', (new ManPowerPlanningService)->determineBidang($employee));
    }

    public function test_determine_bidang_falls_back_to_heuristic_without_a_seat(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $service = new ManPowerPlanningService;

        $this->assertSame('TAN', $service->determineBidang(new Employee(['jabatan' => 'Advisor RM-1', 'unit_kerja' => 'Kebun Tajati'])));
        $this->assertSame('TEK', $service->determineBidang(new Employee(['jabatan' => 'Operator Boiler', 'unit_kerja' => 'PKS Kembayan'])));
    }

    public function test_classify_position_flags_values_from_master_formasi_versus_estimates(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $service = new ManPowerPlanningService;

        $seat = $service->classifyPosition(new Employee(['jabatan' => 'Manajer', 'unit_kerja' => 'Kebun Tajati']));
        $this->assertSame('TAN', $seat['bidang']);
        $this->assertSame(Position::where('nama', 'Manajer')->value('rm_level'), $seat['rm_band']);
        $this->assertTrue($seat['bidang_from_formasi']);
        $this->assertTrue($seat['rm_from_formasi']);

        $estimate = $service->classifyPosition(new Employee(['jabatan' => 'Operator Boiler', 'unit_kerja' => 'PKS Kembayan', 'level' => 'Karpel']));
        $this->assertSame('TEK', $estimate['bidang']);
        $this->assertFalse($estimate['bidang_from_formasi']);
        $this->assertFalse($estimate['rm_from_formasi']);
    }

    public function test_determine_rm_level_does_not_query_positions_per_employee(): void
    {
        Position::create(['nama' => 'Manajer Regulatif', 'rm_level' => 'RM-1', 'is_overflow' => false, 'is_active' => true]);
        Position::getAllCached();
        $service = new ManPowerPlanningService;

        DB::flushQueryLog();
        DB::enableQueryLog();
        for ($i = 0; $i < 3; $i++) {
            $service->determineRmLevel(new Employee(['jabatan' => 'Manajer Regulatif', 'level' => 'Karpim']));
            $service->determineRmLevel(new Employee(['jabatan' => 'Operator Boiler', 'level' => 'Karpel']));
        }
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'positions'));
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    public function test_determine_rm_level_uses_the_karpel_band_from_the_master(): void
    {
        $this->seed(PositionUnitSeeder::class);
        $service = new ManPowerPlanningService;

        $this->assertSame('RM-5', $service->determineRmLevel(new Employee(['jabatan' => 'Satpam', 'unit_kerja' => 'Bagian Sekretariat & Hukum', 'level' => 'Karpel'])));
        $this->assertSame('RM-4', $service->determineRmLevel(new Employee(['jabatan' => 'Operator Boiler', 'unit_kerja' => 'PKS Kembayan', 'level' => 'Karpel'])));
    }
}
