<?php

namespace Tests\Feature;

use App\Models\JobHistory;
use App\Models\Position;
use App\Models\StructureDecree;
use App\Services\Structure\StructureAsOfService;
use App\Services\Structure\StructureReader;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStructureFixtures;
use Tests\Concerns\CreatesStructureVersions;
use Tests\TestCase;

/**
 * Struktur organisasi menurut tanggal (DOMAIN.md §3.7): versi struktur = SK Diterapkan terbaru yang sudah berlaku pada
 * tanggal itu, pemangku jabatan dibaca dari job_histories. Tidak ada rekonstruksi untuk tanggal sebelum SK pertama.
 */
class StructureAsOfServiceTest extends TestCase
{
    use CreatesStructureFixtures;
    use CreatesStructureVersions;
    use RefreshDatabase;

    private StructureAsOfService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StructureAsOfService::class);
        $this->baseStructure();
    }

    private function holder(string $nik, string $nama, string $unit, string $jabatan, string $from, ?string $until = null, array $extra = []): void
    {
        $this->employeeWithoutHistory($nik, $nama);
        $this->placement($nik, $unit, $jabatan, $from, $until, $extra);
    }

    public function test_no_structure_is_available_before_the_first_applied_decree(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', app(StructureReader::class)->current());

        $this->assertNull($this->service->decreeFor(Carbon::parse('2026-08-31')));
        $this->assertNull($this->service->report(Carbon::parse('2026-08-31')));
    }

    public function test_the_decree_effective_on_the_requested_day_applies_that_day(): void
    {
        $first = $this->appliedDecree('SK/1', '2026-09-01', app(StructureReader::class)->current());

        $this->assertSame($first->id, $this->service->decreeFor(Carbon::parse('2026-09-01'))->id);
    }

    public function test_the_latest_decree_in_force_wins_and_a_later_one_does_not_leak_back(): void
    {
        $first = $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Lama', 'Kepala Lama'));
        $second = $this->appliedDecree('SK/2', '2026-12-01', $this->seatSnapshot('Bagian Baru', 'Kepala Baru'));

        $this->assertSame($first->id, $this->service->decreeFor(Carbon::parse('2026-11-30'))->id);
        $this->assertSame($second->id, $this->service->decreeFor(Carbon::parse('2026-12-01'))->id);
        $this->assertSame($second->id, $this->service->decreeFor(Carbon::parse('2030-01-01'))->id);
    }

    public function test_only_applied_decrees_with_a_snapshot_count(): void
    {
        $this->appliedDecree('SK/draft', '2026-09-01', $this->seatSnapshot('Bagian X', 'Kepala X'), StructureDecree::STATUS_DRAFT);
        $this->appliedDecree('SK/approved', '2026-09-01', $this->seatSnapshot('Bagian X', 'Kepala X'), StructureDecree::STATUS_APPROVED);
        $this->appliedDecree('SK/cancelled', '2026-09-01', $this->seatSnapshot('Bagian X', 'Kepala X'), StructureDecree::STATUS_CANCELLED);
        $this->appliedDecree('SK/nosnapshot', '2026-09-01', null);

        $this->assertNull($this->service->decreeFor(Carbon::parse('2026-10-01')));
    }

    public function test_report_lists_seats_of_that_version_and_not_the_current_structure(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Lama', 'Kepala Lama', 3));

        $report = $this->service->report(Carbon::parse('2026-10-01'));

        $this->assertSame('SK/1', $report['decree']->sk_number);
        $this->assertCount(1, $report['rows']);
        $this->assertEquals(
            ['unit' => 'Bagian Lama', 'wilayah' => 'Wilayah X', 'jabatan' => 'Kepala Lama', 'rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 3, 'is_active' => true],
            collect($report['rows'][0])->only(['unit', 'wilayah', 'jabatan', 'rm_level', 'level', 'bidang', 'kuota', 'is_active'])->all()
        );
    }

    public function test_holders_are_read_from_job_histories_in_force_on_that_date(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Tanaman', 'Kepala Bagian', 2));
        $this->holder('10000001', 'Ahmad', 'Bagian Tanaman', 'Kepala Bagian', '2026-01-01', '2026-09-30');
        $this->holder('10000002', 'Budi', 'Bagian Tanaman', 'Kepala Bagian', '2026-10-01');
        $this->holder('10000003', 'Citra', 'Bagian Tanaman', 'Kepala Bagian', '2026-11-01');

        $names = fn (string $date) => collect($this->service->report(Carbon::parse($date))['rows'][0]['holders'])->pluck('nama')->sort()->values()->all();

        $this->assertSame(['Ahmad'], $names('2026-09-30'), 'tmt_akhir adalah hari terakhir menjabat');
        $this->assertSame(['Budi'], $names('2026-10-01'), 'tmt_awal adalah hari pertama menjabat');
        $this->assertSame(['Budi', 'Citra'], $names('2026-11-17'));
    }

    public function test_holders_exclude_unapplied_scheduled_rows_and_deleted_rows(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('10000001', 'Terjadwal', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01', null, ['is_applied' => false]);
        $this->holder('10000002', 'Dihapus', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');
        JobHistory::where('employee_nik', '10000002')->first()->delete();
        $this->holder('10000003', 'Aktif', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');

        $row = $this->service->report(Carbon::parse('2026-10-01'))['rows'][0];

        $this->assertSame(['Aktif'], collect($row['holders'])->pluck('nama')->all());
        $this->assertSame(1, $row['holder_count']);
    }

    public function test_holders_keep_their_assignment_status_and_never_expose_the_full_nik(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('12345678', 'Dewi', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01', null, ['status_penugasan' => 'Plt']);

        $holder = $this->service->report(Carbon::parse('2026-10-01'))['rows'][0]['holders'][0];

        $this->assertSame('Plt', $holder['status_penugasan']);
        $this->assertSame('*****678', $holder['nik_masked']);
        $this->assertStringNotContainsString('12345678', json_encode($holder));
    }

    public function test_holders_placed_outside_the_structure_are_counted_not_dropped(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('10000001', 'Penampung', 'Bagian Tanaman', 'Jabatan Penampungan', '2026-09-01');
        $this->holder('10000002', 'Unit Lama', 'Unit Yang Dulu Ada', 'Kepala Bagian', '2026-09-01');

        $report = $this->service->report(Carbon::parse('2026-10-01'));

        $this->assertSame([], $report['rows'][0]['holders']);
        $this->assertSame(2, $report['outside_structure']);
    }

    public function test_outside_structure_holders_are_listed_with_masked_nik_so_the_count_is_traceable(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('12345678', 'Penampung', 'Bagian Tanaman', 'Jabatan Penampungan', '2026-09-01');
        $this->holder('10000002', 'Terdaftar', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');

        $report = $this->service->report(Carbon::parse('2026-10-01'));

        $this->assertCount($report['outside_structure'], $report['outside_holders']);
        $this->assertSame('Penampung', $report['outside_holders'][0]['nama']);
        $this->assertSame('Bagian Tanaman', $report['outside_holders'][0]['unit']);
        $this->assertSame('Jabatan Penampungan', $report['outside_holders'][0]['jabatan']);
        $this->assertSame('*****678', $report['outside_holders'][0]['nik_masked']);
        $this->assertStringNotContainsString('12345678', json_encode($report['outside_holders']));
    }

    public function test_concurrent_assignments_outside_the_structure_are_reported_apart_and_not_as_mismatches(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('10000001', 'Rangkap', 'Bagian Tanaman', 'Asisten Lain', '2026-09-01', null, ['status_penugasan' => 'Plt']);
        $this->holder('10000002', 'Pj', 'Unit Lain', 'Manajer Lain', '2026-09-01', null, ['status_penugasan' => 'Pj']);
        $this->holder('10000003', 'Definitif Salah', 'Bagian Tanaman', 'Asisten Lain', '2026-09-01', null, ['status_penugasan' => 'Definitif']);

        $report = $this->service->report(Carbon::parse('2026-10-01'));

        $this->assertSame(1, $report['outside_structure']);
        $this->assertSame(['Definitif Salah'], array_column($report['outside_holders'], 'nama'));
        $this->assertSame(2, $report['concurrent_outside']);
        $this->assertEqualsCanonicalizing(['Rangkap', 'Pj'], array_column($report['concurrent_holders'], 'nama'));
        $this->assertStringNotContainsString('10000001', json_encode($report['concurrent_holders']));
    }

    public function test_outside_holders_are_diagnosed_so_admins_know_what_to_correct(): void
    {
        Position::create(['nama' => 'Advisor Tanaman', 'level' => 'Karpim', 'rm_level' => 'RM-1', 'is_active' => true, 'is_overflow' => true]);
        $this->appliedDecree('SK/1', '2026-09-01', ['units' => [
            'Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'Wilayah X', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Bagian Tanaman' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                'Kepala Sub Bagian A' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                'Kepala Sub Bagian B' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
            'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Wilayah X', 'urutan' => 2, 'is_active' => true, 'seats' => [
                'Manajer' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
        ]]);
        $this->holder('10000001', 'Pool', 'Bagian Tanaman', 'Advisor Tanaman', '2026-09-01');
        $this->holder('10000002', 'Unit Lama', 'Unit Yang Dulu Ada', 'Manajer', '2026-09-01');
        $this->holder('10000003', 'Salah Unit', 'Bagian Tanaman', 'Manajer', '2026-09-01');
        $this->holder('10000004', 'Terpotong', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');
        $this->holder('10000005', 'Ambigu', 'Bagian Tanaman', 'Kepala Sub', '2026-09-01');
        $this->holder('10000006', 'Asing', 'Bagian Tanaman', 'Pengawas Wilayah', '2026-09-01');

        $byName = collect($this->service->report(Carbon::parse('2026-10-01'))['outside_holders'])->keyBy('nama');

        $this->assertSame(['penampungan', null], [$byName['Pool']['reason'], $byName['Pool']['hint']]);
        $this->assertSame('unit_tidak_ada', $byName['Unit Lama']['reason']);
        $this->assertSame(['jabatan_di_unit_lain', 'Kebun Tajati'], [$byName['Salah Unit']['reason'], $byName['Salah Unit']['hint']]);
        $this->assertSame(['nama_jabatan_berbeda', 'Kepala Bagian Tanaman'], [$byName['Terpotong']['reason'], $byName['Terpotong']['hint']]);
        $this->assertSame(['jabatan_tidak_ada', null], [$byName['Ambigu']['reason'], $byName['Ambigu']['hint']], 'a hint is given only when exactly one seat matches');
        $this->assertSame('jabatan_tidak_ada', $byName['Asing']['reason']);
    }

    public function test_versions_list_each_applied_decree_with_its_period_and_totals_newest_first(): void
    {
        $this->travelTo('2026-12-15 08:00:00');
        $first = $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Lama', 'Kepala Lama', 3));
        $second = $this->appliedDecree('SK/2', '2026-12-01', ['units' => [
            'Bagian Baru' => ['kode' => 'Bagian', 'wilayah' => 'Wilayah X', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Baru' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                'Asisten Baru' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 4],
            ]],
            'Bagian Tutup' => ['kode' => 'Bagian', 'wilayah' => 'Wilayah X', 'urutan' => 2, 'is_active' => false, 'seats' => []],
        ]]);
        $this->appliedDecree('SK/draft', '2026-12-10', null, StructureDecree::STATUS_DRAFT);

        $versions = $this->service->versions();

        $this->assertSame([$second->id, $first->id], array_map(fn (array $v): int => $v['decree']->id, $versions));
        $this->assertSame(['2026-12-01', null], [$versions[0]['from']->toDateString(), $versions[0]['to']?->toDateString()]);
        $this->assertSame(['2026-09-01', '2026-11-30'], [$versions[1]['from']->toDateString(), $versions[1]['to']->toDateString()]);
        $this->assertSame([true, false], [$versions[0]['is_current'], $versions[1]['is_current']]);
        $this->assertSame(['units' => 1, 'seats' => 2, 'kuota' => 5], collect($versions[0])->only(['units', 'seats', 'kuota'])->all(), 'hanya unit aktif dihitung');
        $this->assertSame(['units' => 1, 'seats' => 1, 'kuota' => 3], collect($versions[1])->only(['units', 'seats', 'kuota'])->all());
    }

    public function test_version_totals_count_the_same_jabatan_name_once_per_unit(): void
    {
        $seat = fn (int $kuota): array => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => $kuota];
        $this->appliedDecree('SK/1', '2026-09-01', ['units' => [
            'Kebun A' => ['kode' => 'Kebun', 'wilayah' => 'W', 'urutan' => 1, 'is_active' => true, 'seats' => ['Manajer' => $seat(1), 'Asisten' => $seat(2)]],
            'Kebun B' => ['kode' => 'Kebun', 'wilayah' => 'W', 'urutan' => 2, 'is_active' => true, 'seats' => ['Manajer' => $seat(1), 'Asisten' => $seat(3)]],
        ]]);

        $version = $this->service->versions()[0];

        $this->assertSame(['units' => 2, 'seats' => 4, 'kuota' => 7], collect($version)->only(['units', 'seats', 'kuota'])->all());
        $this->assertSame($version['seats'], $this->service->report(Carbon::parse('2026-10-01'))['totals']['seats'], 'daftar versi dan ringkasan harus sama');
        $this->assertSame($version['kuota'], $this->service->report(Carbon::parse('2026-10-01'))['totals']['kuota']);
    }

    public function test_versions_is_empty_without_any_applied_decree(): void
    {
        $this->assertSame([], $this->service->versions());
    }

    public function test_report_groups_seats_per_unit_with_totals_for_the_overview_table(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', ['units' => [
            'Bagian Tanaman' => ['kode' => 'Bagian', 'wilayah' => 'Wilayah X', 'urutan' => 1, 'is_active' => true, 'seats' => [
                'Kepala Bagian' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
                'Asisten Pemetaan' => ['rm_level' => 'RM-3', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 2],
            ]],
            'Kebun Tajati' => ['kode' => 'Kebun', 'wilayah' => 'Wilayah Y', 'urutan' => 2, 'is_active' => true, 'seats' => [
                'Manajer' => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1],
            ]],
        ]]);
        $this->holder('10000001', 'Ahmad', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01');
        $this->holder('10000002', 'Budi', 'Bagian Tanaman', 'Asisten Pemetaan', '2026-09-01');

        $report = $this->service->report(Carbon::parse('2026-10-01'));

        $this->assertSame(['Bagian Tanaman', 'Kebun Tajati'], array_column($report['units'], 'unit'));
        $this->assertSame(
            ['unit' => 'Bagian Tanaman', 'wilayah' => 'Wilayah X', 'seat_count' => 2, 'kuota' => 3, 'filled' => 2],
            collect($report['units'][0])->only(['unit', 'wilayah', 'seat_count', 'kuota', 'filled'])->all()
        );
        $this->assertCount(2, $report['units'][0]['seats']);
        $this->assertSame(['units' => 2, 'seats' => 3, 'kuota' => 4, 'filled' => 2], $report['totals']);
        $this->assertSame(2, $report['holder_total'], 'seluruh pemangku pada struktur dan di luarnya');
    }

    public function test_compare_with_current_structure_lists_differences(): void
    {
        $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Lama', 'Kepala Lama', 3));

        $diff = $this->service->compareWithCurrent(Carbon::parse('2026-10-01'));

        $this->assertNotNull($diff);
        $this->assertFalse($diff->isEmpty(), 'struktur sekarang (Bagian Tanaman, Kebun Tajati) berbeda dari versi SK/1');
        $this->assertNotEmpty($diff->ofType('seat.add'), 'arah selisih: dari versi pada tanggal itu menuju struktur sekarang');
    }

    public function test_two_versions_can_be_compared_in_either_direction(): void
    {
        $first = $this->appliedDecree('SK/1', '2026-09-01', $this->seatSnapshot('Bagian Lama', 'Kepala Lama', 3));
        $second = $this->appliedDecree('SK/2', '2026-12-01', $this->seatSnapshot('Bagian Baru', 'Kepala Baru', 1));

        $forward = $this->service->compareVersions($first, $second);
        $backward = $this->service->compareVersions($second, $first);

        $this->assertFalse($forward->isEmpty());
        $this->assertSame('Bagian Baru', $forward->ofType('unit.add')[0]['unit'], 'dari versi lama menuju versi baru: unit baru ditambahkan');
        $this->assertSame('Bagian Lama', $backward->ofType('unit.add')[0]['unit']);
        $this->assertTrue($this->service->compareVersions($first, $first)->isEmpty());
    }

    public function test_compare_is_null_when_no_version_exists_and_empty_when_the_structure_is_unchanged(): void
    {
        $this->assertNull($this->service->compareWithCurrent(Carbon::parse('2026-10-01')));

        $this->appliedDecree('SK/1', '2026-09-01', app(StructureReader::class)->current());

        $this->assertTrue($this->service->compareWithCurrent(Carbon::parse('2026-10-01'))->isEmpty());
    }
}
