<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Evaluation;
use App\Models\Position;
use App\Models\Unit;
use App\Services\EvaluationService;
use App\Services\KepegawaianPolicy;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;

/**
 * Synthetic demo employees for local development only (SECURITY.md §3). Run with `composer demo`.
 *
 * Never production, and never on a database that already holds employees: demo data must not mix with real data.
 * Employees attach to the real master units and positions (one holder per seat, never above a seat's quota) and are
 * created through Eloquent with model events on, so EmployeeObserver derives the RM Band, retirement and MBT dates
 * and writes the initial job_histories row. No formula is copied here. Evaluations go through EvaluationService.
 * The whole run is one transaction, and the data is the same on every run.
 */
class DemoEmployeeSeeder extends Seeder
{
    public const DEMO_COUNT = 60;

    /** Karpim holders among DEMO_COUNT; the rest are Karpel (fewer if the master data has fewer Karpel seats). */
    public const KARPIM_COUNT = 45;

    /**
     * Karpim holders per upper RM Band; the remaining Karpim seats are RM-3 (or unbanded). Roughly follows the real
     * structure, where RM-3 holds most seats, so screens grouped by band all show someone.
     */
    public const KARPIM_BAND_TARGETS = ['RM-1' => 5, 'RM-2' => 7];

    /** Holders with an acting assignment (Plt); they also count towards the seat's quota. */
    public const ACTING_COUNT = 4;

    /** Employees that receive a 9-box evaluation; the boxes cycle 1 to 9, so every cell has someone. */
    public const EVALUATED_COUNT = 40;

    /** Demo NIK = prefix + six digits, so demo records are recognisable. */
    public const NIK_PREFIX = '99';

    public const NAME_PREFIX = 'Karyawan Contoh';

    private const SEED = 20261121;

    /** Positions in the list (0-based) whose MBT falls inside the warning window, and past MBT with retirement ahead. */
    private const APPROACHING_MBT = [5, 17, 29, 41, 53];

    private const PAST_MBT = [10, 30, 50];

    /** Demo golongan per band, in the [Gol]/[Berkala] format. Data only: the RM Band itself is derived by the domain code. */
    private const GOLONGAN_BY_BAND = [
        'RM-1' => ['IVA', 'IVB'],
        'RM-2' => ['IIIC', 'IIID'],
        'RM-3' => ['IIIA', 'IIIB'],
        'Karpel' => ['IIA', 'IIB', 'IIC', 'IID'],
    ];

    private const BIRTHPLACES = ['Pontianak', 'Banjarmasin', 'Samarinda', 'Palangka Raya', 'Sintang', 'Balikpapan'];

    /** Free-text assessment labels used on the evaluation screens. */
    private const ASSESSMENT_CATEGORIES = ['Sangat Baik', 'Baik', 'Baik', 'Cukup', 'Kurang'];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Refusing to seed demo employees in production: they are synthetic and for development machines only.');
        }

        if (Employee::query()->exists()) {
            throw new RuntimeException('Refusing to seed demo employees: the employees table is not empty. Demo data must never mix with real data.');
        }

        $seats = $this->eligibleSeats();

        if ($seats->isEmpty()) {
            throw new RuntimeException('No master units and positions were found. Run `composer setup` first (it seeds the master structure).');
        }

        $chosen = $this->chooseSeats($seats);
        $rng = new Randomizer(new Mt19937(self::SEED));
        $evaluations = app(EvaluationService::class);

        DB::transaction(function () use ($chosen, $rng, $evaluations): void {
            foreach ($chosen->values() as $index => $seat) {
                $employee = Employee::create($this->employeeData($index, $seat['unit'], $seat['position'], $rng));

                if ($index < self::EVALUATED_COUNT) {
                    $evaluations->processStore($this->evaluationData($index, $employee, $seat['position'], $rng));
                }
            }
        });

        $this->command?->info('Seeded '.$chosen->count().' synthetic demo employees ("'.self::NAME_PREFIX.' NN").');
    }

    /**
     * Seats a demo employee may take: active unit, active non-pool position, quota of at least one.
     *
     * @return Collection<int, array{unit: Unit, position: Position}>
     */
    private function eligibleSeats(): Collection
    {
        return Unit::active()->orderBy('urutan')->orderBy('nama')
            ->with(['positions' => fn ($query) => $query
                ->where('positions.is_active', true)
                ->where('positions.is_overflow', false)
                ->where('positions.is_diperban', false)
                ->wherePivot('kuota', '>=', 1)
                ->orderBy('positions.nama')])
            ->get()
            ->flatMap(fn (Unit $unit) => $unit->positions->map(fn (Position $position): array => ['unit' => $unit, 'position' => $position]));
    }

    /**
     * About KARPIM_COUNT Karpim seats and the rest Karpel, each spread round-robin across the units.
     *
     * @param  Collection<int, array{unit: Unit, position: Position}>  $seats
     * @return Collection<int, array{unit: Unit, position: Position}>
     */
    private function chooseSeats(Collection $seats): Collection
    {
        $karpel = $seats->filter(fn (array $seat): bool => $seat['position']->level === 'Karpel');
        $karpim = $seats->reject(fn (array $seat): bool => $seat['position']->level === 'Karpel');

        $karpelWanted = min(self::DEMO_COUNT - self::KARPIM_COUNT, $karpel->count());
        $karpimWanted = min(self::DEMO_COUNT - $karpelWanted, $karpim->count());

        return $this->karpimSeats($karpim, $karpimWanted)->concat($this->roundRobin($karpel, $karpelWanted));
    }

    /**
     * The upper bands first (KARPIM_BAND_TARGETS), then RM-3 or unbanded seats, then any unused Karpim seat if a band
     * has fewer seats than its target.
     *
     * @param  Collection<int, array{unit: Unit, position: Position}>  $karpim
     * @return Collection<int, array{unit: Unit, position: Position}>
     */
    private function karpimSeats(Collection $karpim, int $wanted): Collection
    {
        $key = fn (array $seat): string => $seat['unit']->id.'-'.$seat['position']->id;
        $picked = collect();

        foreach (self::KARPIM_BAND_TARGETS as $band => $target) {
            $inBand = $karpim->filter(fn (array $seat): bool => $seat['position']->rm_level === $band);
            $picked = $picked->concat($this->roundRobin($inBand, max(0, min($target, $wanted - $picked->count()))));
        }

        $rest = $karpim->reject(fn (array $seat): bool => array_key_exists((string) $seat['position']->rm_level, self::KARPIM_BAND_TARGETS));
        $picked = $picked->concat($this->roundRobin($rest, max(0, $wanted - $picked->count())));

        if ($picked->count() < $wanted) {
            $used = $picked->map($key)->all();
            $unused = $karpim->reject(fn (array $seat): bool => in_array($key($seat), $used, true));
            $picked = $picked->concat($this->roundRobin($unused, $wanted - $picked->count()));
        }

        return $picked;
    }

    /**
     * @param  Collection<int, array{unit: Unit, position: Position}>  $seats
     * @return Collection<int, array{unit: Unit, position: Position}>
     */
    private function roundRobin(Collection $seats, int $limit): Collection
    {
        $perUnit = $seats->groupBy(fn (array $seat): int => $seat['unit']->id)->map(fn (Collection $group) => $group->values())->values();
        $picked = collect();

        for ($round = 0; $picked->count() < $limit; $round++) {
            $added = false;

            foreach ($perUnit as $group) {
                if ($picked->count() < $limit && isset($group[$round])) {
                    $picked->push($group[$round]);
                    $added = true;
                }
            }

            if (! $added) {
                break;
            }
        }

        return $picked;
    }

    /**
     * rm_level, tanggal_pensiun and tanggal_mbt are left empty on purpose: EmployeeObserver derives them.
     *
     * @return array<string, mixed>
     */
    private function employeeData(int $index, Unit $unit, Position $position, Randomizer $rng): array
    {
        $number = $index + 1;
        $birth = $this->birthDate($index, $rng);

        $hired = $birth->copy()->addYears(22)->addDays($rng->getInt(0, 730));
        $hired = $hired->greaterThan(today()) ? today()->copy() : $hired;
        $inPosition = $hired->copy()->addDays($rng->getInt(0, max(0, (int) $hired->diffInDays(today()))));
        $inUnit = $hired->copy()->addDays($rng->getInt(0, max(0, (int) $hired->diffInDays($inPosition))));

        $acting = in_array($index, $this->actingIndexes(), true);

        return [
            'nik' => self::NIK_PREFIX.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'nama' => self::NAME_PREFIX.' '.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'jabatan' => $position->nama,
            'unit_kerja' => $unit->nama,
            'level' => $position->level === 'Karpel' ? 'Karpel' : 'Karpim',
            'status_penugasan' => $acting ? Employee::STATUS_PLT : Employee::STATUS_DEFINITIF,
            'status_karyawan' => 'Tetap (PKWTT)',
            'golongan' => $this->golongan($position, $rng),
            'tanggal_lahir' => $birth->toDateString(),
            'tempat_lahir' => self::BIRTHPLACES[$index % count(self::BIRTHPLACES)],
            'jenis_kelamin' => array_keys(Employee::GENDERS)[$index % 2],
            'agama' => Employee::RELIGIONS[$index % 3],
            'pendidikan_terakhir' => ['D3', 'D4 / S1', 'S2'][$index % 3],
            'tmt_bekerja' => $hired->toDateString(),
            'tanggal_dalam_jabatan' => $inPosition->toDateString(),
            'tmt_unit_kerja' => $inUnit->toDateString(),
        ];
    }

    /**
     * Some employees are within the MBT warning window, some are past MBT with retirement still ahead, the rest are far
     * from retirement. The retirement month is chosen first; the birth date is the day inside it that yields that month.
     */
    private function birthDate(int $index, Randomizer $rng): Carbon
    {
        $monthsAhead = match (true) {
            in_array($index, self::APPROACHING_MBT, true) => $rng->getInt(7, 12),
            in_array($index, self::PAST_MBT, true) => $rng->getInt(1, 5),
            default => null,
        };

        if ($monthsAhead === null) {
            return today()->copy()->subYears($rng->getInt(25, 50))->subDays($rng->getInt(0, 364));
        }

        return today()->copy()->startOfMonth()->addMonths($monthsAhead)
            ->subYears(KepegawaianPolicy::BATAS_USIA_PENSIUN)->addDays($rng->getInt(0, 27));
    }

    /**
     * @return list<int>
     */
    private function actingIndexes(): array
    {
        return array_map(fn (int $step): int => 3 + $step * 15, range(0, self::ACTING_COUNT - 1));
    }

    private function golongan(Position $position, Randomizer $rng): string
    {
        $band = $position->level === 'Karpel' ? 'Karpel' : ($position->rm_level ?: 'RM-3');
        $options = self::GOLONGAN_BY_BAND[$band] ?? self::GOLONGAN_BY_BAND['RM-3'];

        return $options[$rng->getInt(0, count($options) - 1)].'/'.str_pad((string) $rng->getInt(0, 4), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Box number cycles 1 to 9 (potential rises by row, performance by column); each score sits in its third of 0 to 100.
     *
     * @return array<string, mixed>
     */
    private function evaluationData(int $index, Employee $employee, Position $position, Randomizer $rng): array
    {
        $box = ($index % 9) + 1;
        $potential = intdiv($box - 1, 3);
        $performance = ($box - 1) % 3;
        $assessed = today()->copy()->subDays($rng->getInt(10, 360));

        return [
            'employee_nik' => $employee->nik,
            'nilai_kepemimpinan' => $rng->getInt(50, 90) / 10,
            'nilai_perilaku_budaya' => $rng->getInt(50, 90) / 10,
            'nilai_pengalaman_teknis' => $rng->getInt(50, 90) / 10,
            'nilai_kematangan_pribadi' => $rng->getInt(50, 90) / 10,
            'skor_cli_9box' => $this->scoreInThird($potential, $rng),
            'skor_smkbk_9box' => $this->scoreInThird($performance, $rng),
            'kategori_9box' => Evaluation::boxValue($box),
            'bidang_tugas' => $position->pivot?->bidang,
            'lembaga_asesmen' => 'Lembaga Asesmen Contoh',
            'tanggal_pelaksanaan_asesmen' => $assessed->toDateString(),
            'hasil_skor_asesmen' => $rng->getInt(50, 90) / 10,
            'kategori_asesmen' => self::ASSESSMENT_CATEGORIES[$index % count(self::ASSESSMENT_CATEGORIES)],
            'expired_asesmen' => $assessed->copy()->addYears(2)->toDateString(),
            'keterangan' => 'Data contoh untuk pengembangan lokal.',
        ];
    }

    private function scoreInThird(int $third, Randomizer $rng): int
    {
        return match ($third) {
            0 => $rng->getInt(10, 33),
            1 => $rng->getInt(40, 60),
            default => $rng->getInt(70, 95),
        };
    }
}
