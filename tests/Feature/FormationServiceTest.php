<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\Mutasi\FormationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SUT: App\Services\Mutasi\FormationService::analyzeTarget() (quota-aware occupancy of a target formasi, DOMAIN.md §5.2).
 * A formasi accepts a placement while all its holders, Pj/Plt included, are fewer than its quota.
 */
class FormationServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormationService $formations;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formations = app(FormationService::class);
        $this->unit = Unit::create(['nama' => 'Kebun Analisis Uji', 'kode' => 'Kebun', 'wilayah' => 'Wilayah Kalimantan Barat', 'is_active' => true, 'urutan' => 1]);
    }

    private function seat(string $jabatan, int $kuota): Position
    {
        $position = Position::create(['nama' => $jabatan, 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);

        DB::table('position_unit')->insert([
            'unit_id' => $this->unit->id, 'position_id' => $position->id, 'kuota' => $kuota, 'bidang' => 'TAN',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $position;
    }

    /** @return Collection<int, Employee> */
    private function holders(string $jabatan, int $count, string $status = 'Definitif')
    {
        return Employee::factory()->count($count)->create(['unit_kerja' => $this->unit->nama, 'jabatan' => $jabatan, 'status_penugasan' => $status]);
    }

    private function book(string $jabatan, int $count): void
    {
        foreach (Employee::factory()->count($count)->create(['unit_kerja' => 'Unit Lain', 'jabatan' => 'Jabatan Lain']) as $successor) {
            JobHistory::create(['employee_nik' => $successor->nik, 'jabatan' => $jabatan, 'unit_kerja' => $this->unit->nama, 'tmt_awal' => now()->addMonth()->toDateString(), 'is_applied' => false]);
        }
    }

    private function analyze(string $jabatan): array
    {
        return $this->formations->analyzeTarget($jabatan, $this->unit->nama);
    }

    public function test_a_single_seat_formasi_behaves_as_before(): void
    {
        $this->seat('Manajer Kosong', 1);
        $vacant = $this->analyze('Manajer Kosong');

        $this->assertSame('vacant', $vacant['type']);
        $this->assertTrue($vacant['vacant']);
        $this->assertNull($vacant['occupant']);
        $this->assertSame(1, $vacant['free_slots']);
        $this->assertFalse($vacant['multi_seat']);

        $this->seat('Manajer Terisi', 1);
        $holder = $this->holders('Manajer Terisi', 1)->first();
        $occupied = $this->analyze('Manajer Terisi');

        $this->assertSame('occupied', $occupied['type']);
        $this->assertFalse($occupied['vacant']);
        $this->assertSame($holder->nik, $occupied['occupant']->nik);
        $this->assertSame(0, $occupied['free_slots']);
        $this->assertFalse($occupied['multi_seat']);
    }

    public function test_a_multi_seat_formasi_with_free_quota_is_vacant_for_placement(): void
    {
        $this->seat('Asisten Tiga Kursi', 3);
        $this->holders('Asisten Tiga Kursi', 1);

        $analysis = $this->analyze('Asisten Tiga Kursi');

        $this->assertSame('vacant', $analysis['type']);
        $this->assertTrue($analysis['vacant']);
        $this->assertNull($analysis['occupant'], 'No holder has to be swapped out while a seat is free.');
        $this->assertSame(3, $analysis['kuota']);
        $this->assertSame(1, $analysis['holders']);
        $this->assertSame(2, $analysis['free_slots']);
        $this->assertTrue($analysis['multi_seat']);
    }

    public function test_a_full_multi_seat_formasi_is_occupied_and_names_a_stable_first_holder(): void
    {
        $this->seat('Asisten Dua Kursi', 2);
        $holders = $this->holders('Asisten Dua Kursi', 2);

        $analysis = $this->analyze('Asisten Dua Kursi');

        $this->assertSame('occupied', $analysis['type']);
        $this->assertFalse($analysis['vacant']);
        $this->assertSame($holders->pluck('nik')->sort()->first(), $analysis['occupant']->nik);
        $this->assertSame(0, $analysis['free_slots']);
        $this->assertTrue($analysis['multi_seat']);
    }

    public function test_acting_holders_keep_their_seat(): void
    {
        $this->seat('Manajer Dijabat Plt', 1);
        $this->holders('Manajer Dijabat Plt', 1, 'Plt');
        $this->seat('Asisten Dua Kursi Pjs', 2);
        $this->holders('Asisten Dua Kursi Pjs', 1, 'Pejabat Sementara (Pjs)');

        $this->assertSame('occupied', $this->analyze('Manajer Dijabat Plt')['type'], 'A formasi held by a Pj/Plt is not released.');
        $this->assertSame('vacant', $this->analyze('Asisten Dua Kursi Pjs')['type']);
        $this->assertSame(1, $this->analyze('Asisten Dua Kursi Pjs')['free_slots']);
    }

    public function test_pending_bookings_reserve_only_when_they_use_up_the_free_quota(): void
    {
        $this->seat('Asisten Tiga Kursi', 3);
        $this->holders('Asisten Tiga Kursi', 1);

        $this->book('Asisten Tiga Kursi', 1);
        $oneBooking = $this->analyze('Asisten Tiga Kursi');
        $this->assertFalse($oneBooking['is_reserved'], 'Two free seats, one booked: one seat is still open.');
        $this->assertTrue($oneBooking['vacant']);

        $this->book('Asisten Tiga Kursi', 1);
        $twoBookings = $this->analyze('Asisten Tiga Kursi');
        $this->assertTrue($twoBookings['is_reserved']);
        $this->assertSame('reserved', $twoBookings['type']);
        $this->assertFalse($twoBookings['vacant']);
    }

    public function test_a_single_seat_booking_still_reserves_the_formasi(): void
    {
        $this->seat('Manajer Dipesan', 1);
        $this->book('Manajer Dipesan', 1);

        $analysis = $this->analyze('Manajer Dipesan');

        $this->assertTrue($analysis['is_reserved']);
        $this->assertSame('reserved', $analysis['type']);
        $this->assertFalse($analysis['vacant']);
    }

    public function test_a_pair_outside_the_master_formasi_counts_as_one_seat(): void
    {
        Position::create(['nama' => 'Jabatan Di Luar Master', 'level' => 'Karpim', 'rm_level' => 'RM-3', 'is_active' => true]);
        $this->holders('Jabatan Di Luar Master', 1);

        $analysis = $this->analyze('Jabatan Di Luar Master');

        $this->assertSame('occupied', $analysis['type']);
        $this->assertSame(1, $analysis['kuota']);
        $this->assertFalse($analysis['multi_seat']);
    }
}
