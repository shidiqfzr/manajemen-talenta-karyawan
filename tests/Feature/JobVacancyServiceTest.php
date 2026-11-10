<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\JobVacancyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUT: App\Services\JobVacancyService, formasi-based vacancy tracker (DOMAIN.md §9.3).
 * Vacancy is always quota minus definitive holders, computed live from position_unit and employees.
 */
class JobVacancyServiceTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private JobVacancyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(JobVacancyService::class);
        $this->unit = Unit::create(['nama' => 'Kebun Tracker Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
    }

    private function seat(string $jabatan, int $kuota, ?Unit $unit = null, bool $positionActive = true, string $rm = 'RM-3', string $bidang = 'TAN'): Position
    {
        $position = Position::create(['nama' => $jabatan, 'level' => 'Karpim', 'rm_level' => $rm, 'is_active' => $positionActive]);

        DB::table('position_unit')->insert([
            'unit_id' => ($unit ?? $this->unit)->id,
            'position_id' => $position->id,
            'kuota' => $kuota,
            'bidang' => $bidang,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $position;
    }

    private function holders(Position $position, int $count, string $status = 'Definitif', ?Unit $unit = null): void
    {
        Employee::factory()->count($count)->create([
            'unit_kerja' => ($unit ?? $this->unit)->nama,
            'jabatan' => $position->nama,
            'status_penugasan' => $status,
        ]);
    }

    private function row(string $jabatan): ?object
    {
        return $this->service->getFormasiVacancies()->firstWhere('jabatan', $jabatan);
    }

    public function test_classify_seat_covers_every_state(): void
    {
        $this->assertSame('empty', JobVacancyService::classifySeat(2, 0, 0));
        $this->assertSame('acting', JobVacancyService::classifySeat(1, 0, 1));
        $this->assertSame('partial', JobVacancyService::classifySeat(3, 1, 0));
        $this->assertSame('partial', JobVacancyService::classifySeat(2, 1, 1));
        $this->assertSame('full', JobVacancyService::classifySeat(2, 2, 0));
        $this->assertSame('over', JobVacancyService::classifySeat(1, 2, 0));
    }

    public function test_a_seat_that_was_never_filled_is_vacant(): void
    {
        $this->seat('Manajer Belum Terisi', 1);

        $row = $this->row('Manajer Belum Terisi');

        $this->assertNotNull($row);
        $this->assertSame('empty', $row->status);
        $this->assertSame(1, $row->vacant_slots);
        $this->assertNull($row->vacant_since);
        $this->assertSame('Kebun Tracker Uji', $row->unit_kerja);
    }

    public function test_multi_seat_formasi_stays_vacant_until_every_seat_is_filled(): void
    {
        $position = $this->seat('Asisten Dua Kursi', 3);
        $this->holders($position, 1);

        $row = $this->row('Asisten Dua Kursi');

        $this->assertSame('partial', $row->status);
        $this->assertSame(2, $row->vacant_slots);
        $this->assertSame(1, $row->filled);

        $this->holders($position, 2);

        $this->assertNull($this->row('Asisten Dua Kursi'), 'A full formasi is not a vacancy.');
    }

    public function test_acting_holder_does_not_fill_the_formasi(): void
    {
        $position = $this->seat('Manajer Dijabat Plt', 1);
        $this->holders($position, 1, 'Plt');

        $row = $this->row('Manajer Dijabat Plt');

        $this->assertSame('acting', $row->status);
        $this->assertSame(1, $row->vacant_slots);
        $this->assertSame(0, $row->filled);
        $this->assertSame(1, $row->acting);
    }

    public function test_over_quota_formasi_is_not_a_vacancy_but_counts_in_statistics(): void
    {
        $position = $this->seat('Asisten Kelebihan', 1);
        $this->holders($position, 2);

        $this->assertNull($this->row('Asisten Kelebihan'));
        $this->assertSame(1, $this->service->getFormasiStatistics()['over_count']);
    }

    public function test_inactive_unit_and_inactive_position_are_excluded(): void
    {
        $inactiveUnit = Unit::create(['nama' => 'Unit Nonaktif Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => false, 'urutan' => 2]);
        $this->seat('Jabatan Unit Mati', 1, $inactiveUnit);
        $this->seat('Jabatan Nonaktif', 1, null, positionActive: false);
        $this->seat('Jabatan Aktif', 1);

        $jabatan = $this->service->getFormasiVacancies()->pluck('jabatan')->all();

        $this->assertSame(['Jabatan Aktif'], $jabatan);
    }

    public function test_vacant_since_and_severity_follow_the_latest_departure(): void
    {
        $position = $this->seat('Manajer Lama Kosong', 1);
        $former = Employee::factory()->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);

        JobHistory::create([
            'employee_nik' => $former->nik,
            'jabatan' => $position->nama,
            'unit_kerja' => $this->unit->nama,
            'tmt_awal' => now()->subYears(2)->toDateString(),
            'tmt_akhir' => now()->subDays(120)->toDateString(),
        ]);

        $row = $this->row('Manajer Lama Kosong');

        $this->assertSame(120, $row->days_vacant);
        $this->assertSame('red', $row->severity);
        $this->assertNotNull($row->vacant_since);
    }

    public function test_pending_mutation_marks_the_formasi_as_reserved(): void
    {
        $position = $this->seat('Manajer Dipesan', 1);
        $successor = Employee::factory()->create(['nama' => 'Sari Pengganti', 'unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);

        JobHistory::create([
            'employee_nik' => $successor->nik,
            'jabatan' => $position->nama,
            'unit_kerja' => $this->unit->nama,
            'tmt_awal' => now()->addMonth()->toDateString(),
            'is_applied' => false,
        ]);

        $row = $this->row('Manajer Dipesan');

        $this->assertTrue($row->is_reserved);
        $this->assertSame('Sari Pengganti', $row->reserved_occupant_name);
    }

    private function otherUnit(string $nama = 'Pabrik Pembanding', string $wilayah = 'Wilayah Kalimantan Barat'): Unit
    {
        return Unit::create(['nama' => $nama, 'kode' => 'PKS', 'wilayah' => $wilayah, 'is_active' => true, 'urutan' => 2]);
    }

    public function test_seats_include_filled_formasi_and_carry_family_and_wilayah(): void
    {
        $first = $this->seat('Asisten Afdeling I', 1);
        $this->seat('Asisten Afdeling II', 1);
        $this->holders($first, 1);

        $seats = $this->service->getFormasiSeats();

        $this->assertCount(2, $seats);
        $full = $seats->firstWhere('jabatan', 'Asisten Afdeling I');
        $this->assertSame('full', $full->status);
        $this->assertSame('Asisten Afdeling', $full->family);
        $this->assertSame('Wilayah Kalimantan Barat', $full->wilayah);
        $this->assertSame('empty', $seats->firstWhere('jabatan', 'Asisten Afdeling II')->status);
    }

    public function test_kosong_status_matches_every_vacant_state_only(): void
    {
        $full = $this->seat('Manajer Penuh', 1);
        $this->holders($full, 1);
        $partial = $this->seat('Manajer Sebagian', 2);
        $this->holders($partial, 1);
        $acting = $this->seat('Manajer Plt', 1);
        $this->holders($acting, 1, 'Plt');
        $this->seat('Manajer Kosong', 1);

        $names = $this->service->getFormasiSeats(status: 'kosong')->pluck('jabatan')->sort()->values()->all();

        $this->assertSame(['Manajer Kosong', 'Manajer Plt', 'Manajer Sebagian'], $names);
    }

    public function test_rm_bidang_and_wilayah_filters(): void
    {
        $kaltim = $this->otherUnit('Kebun Kaltim', 'Wilayah Kalimantan Timur');
        $this->seat('Kepala Keuangan', 1, null, rm: 'RM-2', bidang: 'KEU');
        $this->seat('Asisten Tanaman', 1, $kaltim, rm: 'RM-3', bidang: 'TAN');

        $this->assertSame(['Kepala Keuangan'], $this->service->getFormasiSeats(rmLevel: 'RM-2')->pluck('jabatan')->all());
        $this->assertSame(['Asisten Tanaman'], $this->service->getFormasiSeats(bidang: 'TAN')->pluck('jabatan')->all());
        $this->assertSame(['Asisten Tanaman'], $this->service->getFormasiSeats(wilayah: 'Wilayah Kalimantan Timur')->pluck('jabatan')->all());
        $this->assertSame([], $this->service->getFormasiSeats(rmLevel: 'RM-9')->pluck('jabatan')->all());
    }

    public function test_search_matches_the_family_name(): void
    {
        $this->seat('Asisten Afdeling IV', 1);
        $this->seat('Manajer Pabrik', 1);

        $this->assertSame(['Asisten Afdeling IV'], $this->service->getFormasiSeats(search: 'afdeling')->pluck('jabatan')->all());
    }

    public function test_distribution_groups_numbered_positions_into_one_family(): void
    {
        $unitB = $this->otherUnit();
        $a1 = $this->seat('Asisten Afdeling I', 1);
        $this->holders($a1, 1);
        $this->seat('Asisten Afdeling II', 1);
        $a3 = $this->seat('Asisten Afdeling III', 2, $unitB);
        $this->holders($a3, 1, 'Definitif', $unitB);

        $row = $this->service->getJabatanDistribution()->firstWhere('family', 'Asisten Afdeling');

        $this->assertSame(3, $row->jabatan_count);
        $this->assertSame(2, $row->unit_count);
        $this->assertSame(4, $row->kuota);
        $this->assertSame(2, $row->filled);
        $this->assertSame(2, $row->vacant_slots);
        $this->assertSame(2, $row->vacant_units);
        $this->assertSame(50, $row->fill_rate);
        $this->assertSame(['RM-3'], $row->rm_levels);
        $this->assertCount(3, $row->seats);
    }

    public function test_distribution_filters_seats_before_grouping(): void
    {
        $unitB = $this->otherUnit();
        $this->seat('Asisten Afdeling I', 1);
        $this->seat('Asisten Afdeling III', 2, $unitB);

        $filtered = $this->service->getJabatanDistribution(unitKerja: 'Pabrik Pembanding');
        $row = $filtered->firstWhere('family', 'Asisten Afdeling');

        $this->assertCount(1, $filtered);
        $this->assertSame(2, $row->kuota);
        $this->assertSame(1, $row->unit_count);
        $this->assertCount(1, $row->seats);
    }

    public function test_severity_filter_applies_to_seats_before_grouping(): void
    {
        $old = $this->seat('Asisten Afdeling I', 1);
        $this->seat('Asisten Afdeling II', 1);
        $former = Employee::factory()->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create([
            'employee_nik' => $former->nik,
            'jabatan' => $old->nama,
            'unit_kerja' => $this->unit->nama,
            'tmt_awal' => now()->subYears(2)->toDateString(),
            'tmt_akhir' => now()->subDays(200)->toDateString(),
        ]);

        $this->assertSame(['Asisten Afdeling I'], $this->service->getFormasiSeats(severity: 'red')->pluck('jabatan')->all());
        $this->assertSame(['Asisten Afdeling II'], $this->service->getFormasiSeats(severity: 'unknown')->pluck('jabatan')->all());

        $row = $this->service->getJabatanDistribution(severity: 'red')->firstWhere('family', 'Asisten Afdeling');
        $this->assertSame(1, $row->kuota);
        $this->assertCount(1, $row->seats);
    }

    public function test_pejabat_sementara_pjs_holder_does_not_fill_the_formasi(): void
    {
        $position = $this->seat('Manajer Dijabat Pjs', 1);
        $this->holders($position, 1, 'Pejabat Sementara (Pjs)');

        $row = $this->row('Manajer Dijabat Pjs');

        $this->assertNotNull($row, 'A formasi held only by Pejabat Sementara (Pjs) is still vacant.');
        $this->assertSame('acting', $row->status);
        $this->assertSame(0, $row->filled);
        $this->assertSame(1, $row->acting);
    }

    public function test_full_formasi_carry_no_vacancy_age_or_severity(): void
    {
        $position = $this->seat('Manajer Penuh Lagi', 1);
        $this->holders($position, 1);
        $former = Employee::factory()->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create([
            'employee_nik' => $former->nik,
            'jabatan' => $position->nama,
            'unit_kerja' => $this->unit->nama,
            'tmt_awal' => now()->subYears(2)->toDateString(),
            'tmt_akhir' => now()->subDays(400)->toDateString(),
        ]);
        $this->seat('Manajer Belum Pernah Terisi', 1);

        $full = $this->service->getFormasiSeats()->firstWhere('jabatan', 'Manajer Penuh Lagi');

        $this->assertSame('full', $full->status);
        $this->assertNull($full->vacant_since);
        $this->assertNull($full->days_vacant);
        $this->assertSame('none', $full->severity);
        $this->assertSame(['Manajer Belum Pernah Terisi'], $this->service->getFormasiSeats(severity: 'unknown')->pluck('jabatan')->all());
        $this->assertSame([], $this->service->getFormasiSeats(severity: 'red')->pluck('jabatan')->all());
    }

    public function test_fill_rate_never_lets_an_over_quota_formasi_hide_an_empty_one(): void
    {
        $over = $this->seat('Asisten Afdeling I', 1);
        $this->holders($over, 2);
        $this->seat('Asisten Afdeling II', 1);

        $family = $this->service->getJabatanDistribution()->firstWhere('family', 'Asisten Afdeling');

        $this->assertSame(50, $family->fill_rate);
        $this->assertSame(50, $this->service->getFormasiStatistics()['fill_rate']);
    }

    public function test_distribution_orders_families_with_most_vacancies_first(): void
    {
        $this->seat('Manajer Satu', 1);
        $this->seat('Asisten Dua', 3);

        $this->assertSame(['Asisten Dua', 'Manajer Satu'], $this->service->getJabatanDistribution()->pluck('family')->all());
    }

    public function test_query_count_is_fixed_regardless_of_seat_count(): void
    {
        $units = [$this->unit, $this->otherUnit('Unit Dua'), $this->otherUnit('Unit Tiga')];
        foreach (range(1, 30) as $n) {
            $unit = $units[$n % 3];
            $position = $this->seat("Asisten Afdeling {$n}", 1, $unit);
            if ($n % 2 === 0) {
                $this->holders($position, 1, 'Definitif', $unit);
            }
        }
        $successor = Employee::factory()->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']);
        JobHistory::create(['employee_nik' => $successor->nik, 'jabatan' => 'Asisten Afdeling 1', 'unit_kerja' => $this->unit->nama, 'tmt_awal' => now()->addMonth()->toDateString(), 'is_applied' => false]);

        DB::enableQueryLog();
        $this->service->getJabatanDistribution();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, $queries);
    }

    public function test_filters_by_search_unit_and_status(): void
    {
        $other = Unit::create(['nama' => 'Pabrik Pembanding', 'kode' => 'PKS', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 2]);
        $this->seat('Manajer Alfa', 1);
        $acting = $this->seat('Manajer Beta', 1, $other);
        $this->holders($acting, 1, 'Pj', $other);

        $this->assertSame(['Manajer Alfa'], $this->service->getFormasiVacancies(search: 'Alfa')->pluck('jabatan')->all());
        $this->assertSame(['Manajer Beta'], $this->service->getFormasiVacancies(unitKerja: 'Pabrik Pembanding')->pluck('jabatan')->all());
        $this->assertSame(['Manajer Beta'], $this->service->getFormasiVacancies(status: 'acting')->pluck('jabatan')->all());
        $this->assertSame([], $this->service->getFormasiVacancies(status: 'bogus')->pluck('jabatan')->all(), 'An unknown status matches nothing.');
    }

    public function test_statistics_summarise_the_whole_formasi(): void
    {
        $a = $this->seat('Manajer A', 2);
        $this->holders($a, 1);
        $this->seat('Manajer B', 1);
        $c = $this->seat('Manajer C', 1);
        $this->holders($c, 1);

        $stats = $this->service->getFormasiStatistics();

        $this->assertSame(3, $stats['total_seats']);
        $this->assertSame(4, $stats['total_kuota']);
        $this->assertSame(2, $stats['filled']);
        $this->assertSame(2, $stats['vacant_slots']);
        $this->assertSame(2, $stats['vacant_formasi']);
        $this->assertSame(50, $stats['fill_rate']);
    }

    public function test_unit_summary_counts_vacant_slots_per_unit(): void
    {
        $a = $this->seat('Manajer A', 2);
        $this->holders($a, 1);
        $this->seat('Manajer B', 1);

        $summary = $this->service->getFormasiUnitSummary();

        $this->assertSame(2, $summary[$this->unit->id]['vacant_slots']);
        $this->assertSame(2, $summary[$this->unit->id]['vacant_formasi']);
    }
}
