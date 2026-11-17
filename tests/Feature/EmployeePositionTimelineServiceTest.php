<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\StructureDecree;
use App\Services\Structure\EmployeePositionTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Riwayat jabatan seorang karyawan terhadap versi struktur yang berlaku pada setiap penempatan (DOMAIN.md §3.7).
 * Pencarian karyawan tidak pernah mengeluarkan NIK utuh: pilihan diwakili token terenkripsi.
 */
class EmployeePositionTimelineServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmployeePositionTimelineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmployeePositionTimelineService::class);
    }

    private function decree(string $number, string $effective, array $units): StructureDecree
    {
        return StructureDecree::create([
            'sk_number' => $number, 'title' => 'Perubahan Struktur', 'sk_date' => $effective, 'effective_date' => $effective,
            'status' => StructureDecree::STATUS_APPLIED, 'source_path' => 'decrees/'.md5($number).'.xlsx', 'source_sha256' => str_repeat('a', 64),
            'applied_at' => now(), 'snapshot' => ['units' => $units],
        ]);
    }

    private function unit(string $jabatan): array
    {
        return ['kode' => 'Bagian', 'wilayah' => 'W', 'urutan' => 1, 'is_active' => true, 'seats' => [$jabatan => ['rm_level' => 'RM-1', 'level' => 'Karpim', 'bidang' => 'TAN', 'kuota' => 1]]];
    }

    private function employee(string $nik, string $nama, array $attributes = []): Employee
    {
        $employee = Employee::factory()->create(['nik' => $nik, 'nama' => $nama] + $attributes);
        // EmployeeObserver menulis baris data_awal; uji ini mengatur riwayatnya sendiri.
        JobHistory::withTrashed()->where('employee_nik', $nik)->forceDelete();

        return $employee;
    }

    private function history(string $nik, string $unit, string $jabatan, string $from, ?string $until = null, array $extra = []): JobHistory
    {
        return JobHistory::create($extra + [
            'employee_nik' => $nik, 'unit_kerja' => $unit, 'jabatan' => $jabatan, 'tmt_awal' => $from, 'tmt_akhir' => $until,
            'is_applied' => true, 'sumber' => JobHistory::SUMBER_MUTASI,
        ]);
    }

    public function test_search_needs_at_least_three_characters(): void
    {
        $this->employee('10000001', 'Dewi Lestari');

        $this->assertSame([], $this->service->search(''));
        $this->assertSame([], $this->service->search('de'));
        $this->assertCount(1, $this->service->search('dew'));
    }

    public function test_search_matches_name_case_insensitively_and_exact_nik_and_never_returns_the_full_nik(): void
    {
        $this->employee('12345678', 'Dewi Lestari', ['jabatan' => 'Manajer', 'unit_kerja' => 'Kebun Kumai']);
        $this->employee('87654321', 'Budi Santoso');

        $byName = $this->service->search('LESTARI');
        $byNik = $this->service->search('12345678');

        $this->assertSame(['Dewi Lestari'], array_column($byName, 'nama'));
        $this->assertSame(['Dewi Lestari'], array_column($byNik, 'nama'));
        $this->assertSame('*****678', $byName[0]['nik_masked']);
        $this->assertSame(['Manajer', 'Kebun Kumai'], [$byName[0]['jabatan'], $byName[0]['unit_kerja']]);
        $this->assertStringNotContainsString('12345678', json_encode($byName), 'token dan hasil tidak boleh memuat NIK utuh');
    }

    public function test_search_treats_like_wildcards_and_injection_text_as_plain_text_and_caps_results(): void
    {
        foreach (range(1, 12) as $i) {
            $this->employee(sprintf('2000%04d', $i), 'Sari Nomor '.$i);
        }

        $this->assertSame([], $this->service->search('%%%'));
        $this->assertSame([], $this->service->search("' OR 1=1 --"));
        $this->assertCount(8, $this->service->search('Sari Nomor'));
    }

    public function test_a_token_resolves_to_its_nik_and_a_tampered_token_resolves_to_nothing(): void
    {
        $this->employee('12345678', 'Dewi Lestari');
        $token = $this->service->search('Dewi')[0]['token'];

        $this->assertSame('12345678', $this->service->resolve($token));
        $altered = substr_replace($token, $token[12] === 'A' ? 'B' : 'A', 12, 1);
        $this->assertNull($this->service->resolve($altered), 'isi token yang diubah ditolak oleh tanda tangan enkripsi');
        $this->assertNull($this->service->resolve('bukan-token'));
        $this->assertNull($this->service->resolve(''));
    }

    public function test_a_token_for_a_deleted_employee_resolves_to_nothing(): void
    {
        $employee = $this->employee('12345678', 'Dewi Lestari');
        $token = $this->service->search('Dewi')[0]['token'];
        $employee->forceDelete();

        $this->assertNull($this->service->resolve($token));
    }

    public function test_timeline_lists_placements_newest_first_with_the_structure_version_in_force_at_each_start(): void
    {
        $this->decree('SK/1', '2026-09-01', ['Bagian A' => $this->unit('Kepala A')]);
        $this->decree('SK/2', '2026-11-01', ['Bagian B' => $this->unit('Kepala B')]);
        $this->employee('12345678', 'Dewi Lestari');
        $this->history('12345678', 'Bagian A', 'Kepala A', '2026-09-15', '2026-10-31');
        $this->history('12345678', 'Bagian B', 'Kepala B', '2026-11-01', null, ['status_penugasan' => 'Plt']);

        $timeline = $this->service->timeline('12345678');

        $this->assertSame(['Kepala B', 'Kepala A'], array_column($timeline, 'jabatan'));
        $this->assertSame(['SK/2', 'SK/1'], array_column($timeline, 'structure_sk'));
        $this->assertSame([true, true], array_column($timeline, 'in_structure'));
        $this->assertNull($timeline[0]['tmt_akhir'], 'penempatan berjalan memiliki akhir kosong');
        $this->assertSame('Plt', $timeline[0]['status_penugasan']);
    }

    public function test_timeline_flags_placements_before_the_first_decree_and_outside_the_structure(): void
    {
        $this->decree('SK/1', '2026-09-01', ['Bagian A' => $this->unit('Kepala A')]);
        $this->employee('12345678', 'Dewi Lestari');
        $this->history('12345678', 'Bagian Lama', 'Kepala Lama', '2026-01-01', '2026-08-31');
        $this->history('12345678', 'Bagian A', 'Jabatan Penampungan', '2026-09-01');

        $timeline = $this->service->timeline('12345678');

        $this->assertSame([null, null], [$timeline[1]['structure_sk'], $timeline[1]['in_structure']], 'sebelum SK pertama: tidak ada versi pembanding');
        $this->assertSame(['SK/1', false], [$timeline[0]['structure_sk'], $timeline[0]['in_structure']], 'jabatan tidak tercantum pada versi yang berlaku');
    }

    public function test_timeline_excludes_unapplied_and_deleted_rows_and_other_employees(): void
    {
        $this->employee('12345678', 'Dewi Lestari');
        $this->employee('87654321', 'Budi Santoso');
        $this->history('12345678', 'Bagian A', 'Terjadwal', '2026-12-01', null, ['is_applied' => false]);
        $this->history('12345678', 'Bagian A', 'Dihapus', '2026-09-01')->delete();
        $this->history('12345678', 'Bagian A', 'Aktif', '2026-09-02');
        $this->history('87654321', 'Bagian A', 'Orang Lain', '2026-09-02');

        $this->assertSame(['Aktif'], array_column($this->service->timeline('12345678'), 'jabatan'));
    }

    public function test_timeline_keeps_the_official_sk_number_only_for_real_mutations(): void
    {
        $this->employee('12345678', 'Dewi Lestari');
        $this->history('12345678', 'Bagian A', 'Kepala A', '2026-09-01', '2026-09-30', ['nomor_sk' => 'SK/MUTASI/9', 'jenis_mutasi' => 'PROMOSI']);
        $this->history('12345678', 'Bagian A', 'Kepala B', '2026-10-01', null, ['sumber' => JobHistory::SUMBER_DATA_AWAL]);

        $timeline = $this->service->timeline('12345678');

        $this->assertSame(['Data Awal', 'Mutasi Resmi'], array_column($timeline, 'sumber_label'));
        $this->assertSame([null, 'SK/MUTASI/9'], array_column($timeline, 'nomor_sk'));
    }
}
