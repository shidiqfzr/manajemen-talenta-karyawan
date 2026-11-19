<?php

namespace Tests\Feature;

use App\Models\JobHistory;
use App\Models\StructureDecree;
use App\Services\Structure\PlacementStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStructureVersions;
use Tests\TestCase;

/**
 * Mencocokkan penempatan (baris riwayat jabatan) dengan versi struktur yang berlaku pada tanggal mulainya (DOMAIN.md §3.7),
 * untuk kolom "Struktur yang Berlaku" pada Riwayat Karir karyawan.
 */
class PlacementStructureServiceTest extends TestCase
{
    use CreatesStructureVersions;
    use RefreshDatabase;

    private PlacementStructureService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PlacementStructureService::class);
        $this->employeeWithoutHistory('12345678', 'Dewi Lestari');
    }

    public function test_each_placement_is_matched_with_the_decree_in_force_at_its_start_date(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian A', 'Kepala A'));
        $this->appliedDecree('SK/2', '2026-11-01', $this->seatSnapshot('Bagian B', 'Kepala B'));
        $first = $this->placement('12345678', 'Bagian A', 'Kepala A', '2026-09-15', '2026-10-31');
        $second = $this->placement('12345678', 'Bagian B', 'Kepala B', '2026-11-01');

        $result = $this->service->annotate(JobHistory::all());

        $this->assertSame(['structure_sk' => 'SK/1', 'in_structure' => true], $result[$first->id]);
        $this->assertSame(['structure_sk' => 'SK/2', 'in_structure' => true], $result[$second->id], 'SK berlaku pada hari tanggal berlakunya');
    }

    public function test_a_placement_before_the_first_decree_has_no_comparison_version(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian A', 'Kepala A'));
        $early = $this->placement('12345678', 'Bagian Lama', 'Kepala Lama', '2026-01-01', '2026-08-31');

        $this->assertSame(['structure_sk' => null, 'in_structure' => null], $this->service->annotate(JobHistory::all())[$early->id]);
    }

    public function test_a_placement_whose_unit_or_jabatan_is_not_in_the_version_is_flagged(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian A', 'Kepala A'));
        $pool = $this->placement('12345678', 'Bagian A', 'Jabatan Penampungan', '2026-09-01');
        $gone = $this->placement('12345678', 'Unit Yang Dulu Ada', 'Kepala A', '2026-09-02');

        $result = $this->service->annotate(JobHistory::all());

        $this->assertSame(['SK/1', false], [$result[$pool->id]['structure_sk'], $result[$pool->id]['in_structure']]);
        $this->assertSame(['SK/1', false], [$result[$gone->id]['structure_sk'], $result[$gone->id]['in_structure']]);
    }

    public function test_only_applied_decrees_with_a_snapshot_are_considered(): void
    {
        $this->appliedDecree('SK/draft', '2026-09-01', $this->seatSnapshot('Bagian A', 'Kepala A'), StructureDecree::STATUS_DRAFT);
        $this->appliedDecree('SK/nosnapshot', '2026-09-01', null);
        $row = $this->placement('12345678', 'Bagian A', 'Kepala A', '2026-10-01');

        $this->assertNull($this->service->annotate(JobHistory::all())[$row->id]['structure_sk']);
    }

    public function test_no_placements_need_no_queries_for_versions(): void
    {
        $this->assertSame([], $this->service->annotate(collect()));
    }
}
