<?php

namespace Tests\Feature;

use App\Models\Employee;
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

    private const CONFLICT = 'ditargetkan oleh lebih dari satu karyawan';

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

    public function test_a_single_seat_formasi_still_takes_one_participant_only(): void
    {
        $nodes = $this->twoMoversInto('Manajer Kosong', kuota: 1, holders: 0);

        $this->assertStringContainsString(self::CONFLICT, $this->errorsFor($nodes));
    }
}
