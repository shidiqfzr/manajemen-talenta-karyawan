<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Mutasi\ChainTopologyAnalyzer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SUT: App\Services\Mutasi\ChainTopologyAnalyzer, the duplicate-target guard of a collective chain (DOMAIN.md §5.2.2).
 * A formasi accepts as many participants as it has free seats (at least one).
 */
class ChainTopologyAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    private const CONFLICT = 'melebihi sisa kuota';

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = Unit::factory()->create(['nama' => 'Unit Rantai']);
    }

    /** @return list<array<string, string>> two participants who both aim at the same formasi */
    private function twoMoversInto(string $jabatan, int $kuota, int $holders): array
    {
        $seat = Position::factory()->create(['nama' => $jabatan, 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Asal A', 'rm_level' => 'RM-3']);
        Position::factory()->create(['nama' => 'Asisten Asal B', 'rm_level' => 'RM-3']);
        $this->unit->positions()->attach($seat->id, ['kuota' => $kuota]);

        Employee::factory()->count($holders)->create(['jabatan' => $jabatan, 'unit_kerja' => 'Unit Rantai']);
        $a = Employee::factory()->create(['jabatan' => 'Asisten Asal A', 'unit_kerja' => 'Unit Rantai']);
        $b = Employee::factory()->create(['jabatan' => 'Asisten Asal B', 'unit_kerja' => 'Unit Rantai']);

        return array_map(fn (Employee $e) => [
            'employee_nik' => $e->nik,
            'target_jabatan' => $jabatan,
            'target_unit_kerja' => 'Unit Rantai',
            'jenis_mutasi' => 'ROTASI',
            'status_jabatan' => 'Definitif',
        ], [$a, $b]);
    }

    private function errorsFor(array $nodes): string
    {
        return implode(' | ', app(ChainTopologyAnalyzer::class)->validateChainTopology($nodes)['errors']);
    }

    public function test_a_participant_whose_retirement_date_has_arrived_is_refused_even_before_the_separation_runs(): void
    {
        $nodes = $this->twoMoversInto('Asisten Pengolahan', kuota: 3, holders: 0);
        Employee::findOrFail($nodes[0]['employee_nik'])->update(['tanggal_pensiun' => now()->startOfMonth()->toDateString()]);

        $errors = $this->errorsFor($nodes);

        $this->assertStringContainsString('telah memasuki Batas Usia Pensiun', $errors);
        $this->assertStringContainsString($nodes[0]['employee_nik'], $errors);
    }

    public function test_a_participant_separated_for_another_reason_is_not_reported_as_retired(): void
    {
        $nodes = $this->twoMoversInto('Asisten Pengolahan', kuota: 3, holders: 0);
        Employee::findOrFail($nodes[0]['employee_nik'])->update(['tanggal_berhenti' => now()->subDay()->toDateString(), 'alasan_berhenti' => 'Mengundurkan Diri']);

        $errors = $this->errorsFor($nodes);

        $this->assertStringContainsString('telah berhenti (Mengundurkan Diri)', $errors);
        $this->assertStringNotContainsString('Batas Usia Pensiun', $errors);
    }

    public function test_two_participants_may_fill_two_free_seats_of_a_multi_seat_formasi(): void
    {
        $nodes = $this->twoMoversInto('Asisten Pengolahan', kuota: 3, holders: 1);

        $this->assertStringNotContainsString(self::CONFLICT, $this->errorsFor($nodes));
    }

    public function test_a_second_participant_is_a_conflict_when_only_one_seat_is_free(): void
    {
        $nodes = $this->twoMoversInto('Asisten Pengolahan', kuota: 2, holders: 1);

        $this->assertStringContainsString(self::CONFLICT, $this->errorsFor($nodes));
    }

    public function test_a_booking_outside_the_chain_takes_one_of_the_free_seats(): void
    {
        $nodes = $this->twoMoversInto('Asisten Pengolahan', kuota: 3, holders: 1);
        $outsider = Employee::factory()->create(['jabatan' => 'Jabatan Lain', 'unit_kerja' => 'Unit Lain']);
        JobHistory::create(['employee_nik' => $outsider->nik, 'jabatan' => 'Asisten Pengolahan', 'unit_kerja' => 'Unit Rantai', 'tmt_awal' => now()->addMonth()->toDateString(), 'is_applied' => false]);

        $this->assertStringContainsString(self::CONFLICT, $this->errorsFor($nodes), 'Two free seats, one already booked: only one participant fits.');
    }

    public function test_a_participants_own_booking_does_not_take_a_seat(): void
    {
        $nodes = $this->twoMoversInto('Asisten Pengolahan', kuota: 3, holders: 1);
        JobHistory::create(['employee_nik' => $nodes[0]['employee_nik'], 'jabatan' => 'Asisten Pengolahan', 'unit_kerja' => 'Unit Rantai', 'tmt_awal' => now()->addMonth()->toDateString(), 'is_applied' => false]);

        $this->assertStringNotContainsString(self::CONFLICT, $this->errorsFor($nodes));
    }

    public function test_several_holders_leaving_one_formasi_report_the_seats_left_unfilled(): void
    {
        $unitPositions = ['Asisten Pengolahan' => 3, 'Posisi Tujuan X' => 1, 'Posisi Tujuan Y' => 1];
        foreach ($unitPositions as $nama => $kuota) {
            $this->unit->positions()->attach(Position::factory()->create(['nama' => $nama, 'rm_level' => 'RM-3'])->id, ['kuota' => $kuota]);
        }
        Position::factory()->create(['nama' => 'Asisten Asal A', 'rm_level' => 'RM-3']);

        $leaverP = Employee::factory()->create(['jabatan' => 'Asisten Pengolahan', 'unit_kerja' => 'Unit Rantai']);
        $leaverR = Employee::factory()->create(['jabatan' => 'Asisten Pengolahan', 'unit_kerja' => 'Unit Rantai']);
        $arriving = Employee::factory()->create(['jabatan' => 'Asisten Asal A', 'unit_kerja' => 'Unit Rantai']);
        $node = fn (Employee $e, string $target) => ['employee_nik' => $e->nik, 'target_jabatan' => $target, 'target_unit_kerja' => 'Unit Rantai', 'jenis_mutasi' => 'ROTASI', 'status_jabatan' => 'Definitif'];

        $result = app(ChainTopologyAnalyzer::class)->validateChainTopology([
            $node($leaverP, 'Posisi Tujuan X'),
            $node($leaverR, 'Posisi Tujuan Y'),
            $node($arriving, 'Asisten Pengolahan'),
        ]);

        $unfilled = collect($result['unfilled_vacancies'])->where('jabatan', 'Asisten Pengolahan');
        $this->assertCount(1, $unfilled, 'Two holders leave, one participant arrives: one seat stays open.');
    }

    public function test_a_single_seat_formasi_still_takes_one_participant_only(): void
    {
        $nodes = $this->twoMoversInto('Manajer Kosong', kuota: 1, holders: 0);

        $this->assertStringContainsString(self::CONFLICT, $this->errorsFor($nodes));
    }
}
