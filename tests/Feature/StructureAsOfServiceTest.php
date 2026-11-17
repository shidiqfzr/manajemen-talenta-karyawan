<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\StructureDecree;
use App\Services\Structure\StructureAsOfService;
use App\Services\Structure\StructureReader;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesStructureFixtures;
use Tests\TestCase;

/**
 * Struktur organisasi menurut tanggal (DOMAIN.md §3.7): versi struktur = SK Diterapkan terbaru yang sudah berlaku pada
 * tanggal itu, pemangku jabatan dibaca dari job_histories. Tidak ada rekonstruksi untuk tanggal sebelum SK pertama.
 */
class StructureAsOfServiceTest extends TestCase
{
    use CreatesStructureFixtures;
    use RefreshDatabase;

    private StructureAsOfService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(StructureAsOfService::class);
        $this->baseStructure();
    }

    private function decree(string $number, string $effective, ?array $snapshot, string $status = StructureDecree::STATUS_APPLIED): StructureDecree
    {
        return StructureDecree::create([
            'sk_number' => $number,
            'title' => 'Perubahan Struktur Organisasi',
            'sk_date' => $effective,
            'effective_date' => $effective,
            'status' => $status,
            'source_path' => 'decrees/'.$number.'.xlsx',
            'source_sha256' => str_repeat('a', 64),
            'snapshot' => $snapshot,
            'applied_at' => $status === StructureDecree::STATUS_APPLIED ? now() : null,
        ]);
    }

    private function seat(string $unit, string $jabatan, int $kuota = 1): array
    {
        return ['units' => [$unit => ['kode' => 'Bagian', 'wilayah' => 'Wilayah X', 'urutan' => 1, 'is_active' => true, 'seats' => [$jabatan => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => $kuota]]]]];
    }

    private function holder(string $nik, string $nama, string $unit, string $jabatan, string $from, ?string $until = null, array $extra = []): void
    {
        Employee::factory()->create(['nik' => $nik, 'nama' => $nama]);
        // EmployeeObserver menulis baris data_awal saat karyawan dibuat; uji ini mengatur riwayatnya sendiri.
        JobHistory::withTrashed()->where('employee_nik', $nik)->forceDelete();
        JobHistory::create(array_merge([
            'employee_nik' => $nik,
            'jabatan' => $jabatan,
            'unit_kerja' => $unit,
            'tmt_awal' => $from,
            'tmt_akhir' => $until,
            'is_applied' => true,
            'sumber' => JobHistory::SUMBER_MUTASI,
        ], $extra));
    }

    public function test_no_structure_is_available_before_the_first_applied_decree(): void
    {
        $this->decree('SK/1', '2026-09-01', app(StructureReader::class)->current());

        $this->assertNull($this->service->decreeFor(Carbon::parse('2026-08-31')));
        $this->assertNull($this->service->report(Carbon::parse('2026-08-31')));
    }

    public function test_the_decree_effective_on_the_requested_day_applies_that_day(): void
    {
        $first = $this->decree('SK/1', '2026-09-01', app(StructureReader::class)->current());

        $this->assertSame($first->id, $this->service->decreeFor(Carbon::parse('2026-09-01'))->id);
    }

    public function test_the_latest_decree_in_force_wins_and_a_later_one_does_not_leak_back(): void
    {
        $first = $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Lama', 'Kepala Lama'));
        $second = $this->decree('SK/2', '2026-12-01', $this->seat('Bagian Baru', 'Kepala Baru'));

        $this->assertSame($first->id, $this->service->decreeFor(Carbon::parse('2026-11-30'))->id);
        $this->assertSame($second->id, $this->service->decreeFor(Carbon::parse('2026-12-01'))->id);
        $this->assertSame($second->id, $this->service->decreeFor(Carbon::parse('2030-01-01'))->id);
    }

    public function test_only_applied_decrees_with_a_snapshot_count(): void
    {
        $this->decree('SK/draft', '2026-09-01', $this->seat('Bagian X', 'Kepala X'), StructureDecree::STATUS_DRAFT);
        $this->decree('SK/approved', '2026-09-01', $this->seat('Bagian X', 'Kepala X'), StructureDecree::STATUS_APPROVED);
        $this->decree('SK/cancelled', '2026-09-01', $this->seat('Bagian X', 'Kepala X'), StructureDecree::STATUS_CANCELLED);
        $this->decree('SK/nosnapshot', '2026-09-01', null);

        $this->assertNull($this->service->decreeFor(Carbon::parse('2026-10-01')));
    }

    public function test_report_lists_seats_of_that_version_and_not_the_current_structure(): void
    {
        $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Lama', 'Kepala Lama', 3));

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
        $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Tanaman', 'Kepala Bagian', 2));
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
        $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Tanaman', 'Kepala Bagian'));
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
        $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('12345678', 'Dewi', 'Bagian Tanaman', 'Kepala Bagian', '2026-09-01', null, ['status_penugasan' => 'Plt']);

        $holder = $this->service->report(Carbon::parse('2026-10-01'))['rows'][0]['holders'][0];

        $this->assertSame('Plt', $holder['status_penugasan']);
        $this->assertSame('*****678', $holder['nik_masked']);
        $this->assertStringNotContainsString('12345678', json_encode($holder));
    }

    public function test_holders_placed_outside_the_structure_are_counted_not_dropped(): void
    {
        $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Tanaman', 'Kepala Bagian'));
        $this->holder('10000001', 'Penampung', 'Bagian Tanaman', 'Jabatan Penampungan', '2026-09-01');
        $this->holder('10000002', 'Unit Lama', 'Unit Yang Dulu Ada', 'Kepala Bagian', '2026-09-01');

        $report = $this->service->report(Carbon::parse('2026-10-01'));

        $this->assertSame([], $report['rows'][0]['holders']);
        $this->assertSame(2, $report['outside_structure']);
    }

    public function test_compare_with_current_structure_lists_differences(): void
    {
        $this->decree('SK/1', '2026-09-01', $this->seat('Bagian Lama', 'Kepala Lama', 3));

        $diff = $this->service->compareWithCurrent(Carbon::parse('2026-10-01'));

        $this->assertNotNull($diff);
        $this->assertFalse($diff->isEmpty(), 'struktur sekarang (Bagian Tanaman, Kebun Tajati) berbeda dari versi SK/1');
        $this->assertNotEmpty($diff->ofType('seat.add'), 'arah selisih: dari versi pada tanggal itu menuju struktur sekarang');
    }

    public function test_compare_is_null_when_no_version_exists_and_empty_when_the_structure_is_unchanged(): void
    {
        $this->assertNull($this->service->compareWithCurrent(Carbon::parse('2026-10-01')));

        $this->decree('SK/1', '2026-09-01', app(StructureReader::class)->current());

        $this->assertTrue($this->service->compareWithCurrent(Carbon::parse('2026-10-01'))->isEmpty());
    }
}
