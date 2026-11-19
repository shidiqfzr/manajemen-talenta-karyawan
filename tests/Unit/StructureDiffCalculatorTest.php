<?php

namespace Tests\Unit;

use App\Services\Structure\StructureDiffCalculator;
use PHPUnit\Framework\TestCase;

class StructureDiffCalculatorTest extends TestCase
{
    private function seat(string $rm, string $bidang, int $kuota): array
    {
        return [
            'rm_level' => $rm,
            'level' => in_array($rm, ['RM-1', 'RM-2', 'RM-3'], true) ? 'Karpim' : 'Karpel',
            'bidang' => $bidang,
            'kuota' => $kuota,
        ];
    }

    /** @param array<string, array<string, array>> $units unit => [jabatan => seat] */
    private function structure(array $units, array $inactive = []): array
    {
        $out = [];
        foreach ($units as $name => $seats) {
            $out[$name] = ['kode' => 'Bagian', 'wilayah' => 'Regional Office (Kantor Direksi Pontianak)', 'urutan' => 1, 'is_active' => ! in_array($name, $inactive, true), 'seats' => $seats];
        }

        return ['units' => $out];
    }

    /** @return list<string> */
    private function types(array $changes): array
    {
        return array_column($changes, 'type');
    }

    public function test_identical_structures_give_an_empty_diff(): void
    {
        $structure = $this->structure(['Bagian Tanaman' => ['Kepala Bagian' => $this->seat('RM-1', 'TAN', 1)]]);

        $diff = (new StructureDiffCalculator)->calculate($structure, $structure);

        $this->assertTrue($diff->isEmpty());
        $this->assertSame($diff->totals['before'], $diff->totals['after']);
    }

    public function test_unit_rename_through_mapping_is_one_change_without_seat_changes(): void
    {
        $seats = ['Group Manager' => $this->seat('RM-1', 'TAN', 1)];
        $current = $this->structure(['Group Manager Kalbar' => $seats]);
        $target = $this->structure(['Unit Group Kalimantan Barat' => $seats]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current, ['Group Manager Kalbar' => 'Unit Group Kalimantan Barat']);

        $this->assertSame(['unit.rename'], $this->types($diff->changes));
        $this->assertSame('Group Manager Kalbar', $diff->changes[0]['from']);
        $this->assertSame('Unit Group Kalimantan Barat', $diff->changes[0]['to']);
    }

    public function test_quota_and_bidang_change_is_one_seat_update_with_only_changed_fields(): void
    {
        $current = $this->structure(['Bagian Tanaman' => ['Asisten' => $this->seat('RM-3', 'TAN', 1)]]);
        $target = $this->structure(['Bagian Tanaman' => ['Asisten' => $this->seat('RM-3', 'KEU', 3)]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertSame(['seat.update'], $this->types($diff->changes));
        $this->assertSame(['kuota' => 1, 'bidang' => 'TAN'], $diff->changes[0]['old']);
        $this->assertSame(['kuota' => 3, 'bidang' => 'KEU'], $diff->changes[0]['new']);
        $this->assertSame(['TAN' => 1], $diff->totals['before']['kuota_by_bidang']);
        $this->assertSame(['KEU' => 3], $diff->totals['after']['kuota_by_bidang']);
    }

    public function test_seat_missing_from_a_covered_unit_is_removed_and_uncovered_units_are_ignored(): void
    {
        $current = $this->structure([
            'Bagian Tanaman' => ['Asisten' => $this->seat('RM-3', 'TAN', 1), 'Kepala' => $this->seat('RM-1', 'TAN', 1)],
            'Kebun Tajati' => ['Manajer' => $this->seat('RM-1', 'TAN', 1)],
        ]);
        $target = $this->structure(['Bagian Tanaman' => ['Kepala' => $this->seat('RM-1', 'TAN', 1)]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertSame(['seat.remove'], $this->types($diff->changes));
        $this->assertSame('Asisten', $diff->changes[0]['jabatan']);
        $this->assertSame(2, $diff->totals['after']['units']);
    }

    public function test_a_unit_is_closed_only_when_listed(): void
    {
        $current = $this->structure(['Bagian A' => [], 'Bagian B' => []]);
        $target = $this->structure([]);

        $calculator = new StructureDiffCalculator;

        $this->assertTrue($calculator->calculate($target, $current)->isEmpty());
        $closed = $calculator->calculate($target, $current, [], ['Bagian B']);
        $this->assertSame(['unit.close'], $this->types($closed->changes));
        $this->assertSame(1, $closed->totals['after']['units']);
    }

    public function test_a_seat_moved_between_units_is_remove_add_and_an_informational_move(): void
    {
        $x = $this->seat('RM-3', 'UMU', 2);
        $current = $this->structure(['Bagian SDM' => ['Asisten ESG' => $x], 'Bagian Teknik' => ['Kepala' => $this->seat('RM-1', 'TEK', 1)]]);
        $target = $this->structure(['Bagian SDM' => [], 'Bagian Teknik' => ['Kepala' => $this->seat('RM-1', 'TEK', 1), 'Asisten ESG' => $x]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertEqualsCanonicalizing(['seat.remove', 'seat.add', 'seat.move'], $this->types($diff->changes));
        $move = array_values(array_filter($diff->changes, fn ($c) => $c['type'] === 'seat.move'))[0];
        $this->assertSame(['Bagian SDM', 'Bagian Teknik'], [$move['from_unit'], $move['to_unit']]);
        $this->assertSame($diff->totals['before']['kuota'], $diff->totals['after']['kuota']);
    }

    public function test_one_seat_removed_from_one_unit_and_added_to_two_gives_two_moves(): void
    {
        $x = $this->seat('RM-3', 'KEU', 1);
        $current = $this->structure(['Distrik' => ['Asisten Tata Usaha' => $this->seat('RM-3', 'KEU', 2)], 'Kemitraan Kalbar' => [], 'Kemitraan Kaltim' => []]);
        $target = $this->structure(['Distrik' => [], 'Kemitraan Kalbar' => ['Asisten Tata Usaha' => $x], 'Kemitraan Kaltim' => ['Asisten Tata Usaha' => $x]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $moves = array_values(array_filter($diff->changes, fn ($c) => $c['type'] === 'seat.move'));
        $this->assertCount(2, $moves);
        $this->assertSame(['Distrik'], array_values(array_unique(array_column($moves, 'from_unit'))));
    }

    public function test_an_rm_change_is_one_position_wide_change_even_in_two_units(): void
    {
        $current = $this->structure([
            'Bagian A' => ['Asisten' => $this->seat('RM-3', 'TAN', 1)],
            'Bagian B' => ['Asisten' => $this->seat('RM-3', 'TAN', 1)],
        ]);
        $target = $this->structure([
            'Bagian A' => ['Asisten' => $this->seat('RM-2', 'TAN', 1)],
            'Bagian B' => ['Asisten' => $this->seat('RM-2', 'TAN', 1)],
        ]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertSame(['position.rm_change'], $this->types($diff->changes));
        $this->assertSame(['Asisten', 'RM-3', 'RM-2', 'Karpim'], [$diff->changes[0]['jabatan'], $diff->changes[0]['from'], $diff->changes[0]['to'], $diff->changes[0]['level']]);
    }

    public function test_an_rm_change_across_the_karpim_karpel_boundary_carries_the_new_level(): void
    {
        $current = $this->structure(['Bagian A' => ['Krani' => $this->seat('RM-3', 'UMU', 1)]]);
        $target = $this->structure(['Bagian A' => ['Krani' => $this->seat('RM-5', 'UMU', 1)]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertSame('Karpel', $diff->changes[0]['level']);
    }

    public function test_a_position_mapping_is_one_rename_and_no_remove_or_add(): void
    {
        $current = $this->structure(['Bagian SDM' => ['Kepala Bagian SDM & Sistem Manajemen' => $this->seat('RM-1', 'UMU', 1)]]);
        $target = $this->structure(['Bagian SDM' => ['Kepala Bagian SDM' => $this->seat('RM-1', 'UMU', 1)]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current, [], [], ['Kepala Bagian SDM & Sistem Manajemen' => 'Kepala Bagian SDM']);

        $this->assertSame(['position.rename'], $this->types($diff->changes));
    }

    public function test_a_workbook_unit_matching_an_inactive_unit_is_reactivated_not_added(): void
    {
        $current = $this->structure(['Bagian Lama' => ['Kepala' => $this->seat('RM-1', 'UMU', 1)], 'Bagian Tidur' => []], ['Bagian Lama', 'Bagian Tidur']);
        $target = $this->structure(['Bagian Lama' => ['Kepala' => $this->seat('RM-1', 'UMU', 1)]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertSame(['unit.reactivate'], $this->types($diff->changes));
        $this->assertSame('Bagian Lama', $diff->changes[0]['unit']);
    }

    public function test_a_new_unit_is_added_with_all_its_seats(): void
    {
        $current = $this->structure([]);
        $target = $this->structure(['Kebun Baru' => ['Manajer' => $this->seat('RM-1', 'TAN', 1), 'Asisten' => $this->seat('RM-3', 'TAN', 4)]]);

        $diff = (new StructureDiffCalculator)->calculate($target, $current);

        $this->assertSame(['unit.add', 'seat.add', 'seat.add'], $this->types($diff->changes));
        $this->assertSame(5, $diff->totals['after']['kuota']);
    }

    public function test_digest_is_stable_and_changes_with_the_content(): void
    {
        $current = $this->structure(['Bagian A' => ['Asisten' => $this->seat('RM-3', 'TAN', 1)]]);
        $targetA = $this->structure(['Bagian A' => ['Asisten' => $this->seat('RM-3', 'TAN', 2)]]);
        $targetB = $this->structure(['Bagian A' => ['Asisten' => $this->seat('RM-3', 'TAN', 3)]]);
        $calculator = new StructureDiffCalculator;

        $this->assertSame($calculator->calculate($targetA, $current)->digest(), $calculator->calculate($targetA, $current)->digest());
        $this->assertNotSame($calculator->calculate($targetA, $current)->digest(), $calculator->calculate($targetB, $current)->digest());
    }
}
