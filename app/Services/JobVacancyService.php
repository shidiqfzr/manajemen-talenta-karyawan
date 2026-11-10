<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Services\Formasi\JabatanFamily;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JobVacancyService
{
    /**
     * Retrieve all vacant positions (Jabatan & Unit Kerja where previous occupant departed
     * and no current active employee occupies it).
     */
    public function getVacancies(?string $search = null, ?string $unitKerja = null): Collection
    {
        // 1. Find the latest departure record for every vacated (jabatan, unit_kerja) pair
        $vacatedSubquery = DB::table('job_histories as jh')
            ->select(
                'jh.jabatan',
                'jh.unit_kerja',
                DB::raw('MAX(jh.tmt_akhir) as vacant_since'),
                DB::raw('MAX(jh.id) as latest_history_id')
            )
            ->whereNotNull('jh.tmt_akhir')
            ->groupBy('jh.jabatan', 'jh.unit_kerja');

        $query = DB::table(DB::raw("({$vacatedSubquery->toSql()}) as v"))
            ->mergeBindings($vacatedSubquery)
            ->join('job_histories as jh', 'jh.id', '=', 'v.latest_history_id')
            ->leftJoin('employees as last_emp', 'last_emp.nik', '=', 'jh.employee_nik')
            // Exclude positions that are currently occupied by an active employee
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('employees as current_emp')
                    ->whereColumn('current_emp.jabatan', 'v.jabatan')
                    ->whereColumn('current_emp.unit_kerja', 'v.unit_kerja');
            })
            ->select([
                'v.jabatan',
                'v.unit_kerja',
                'v.vacant_since',
                'jh.level',
                'jh.golongan',
                'jh.employee_nik as last_occupant_nik',
                'last_emp.nama as last_occupant_name',
                'last_emp.foto as last_occupant_photo',
            ])
            ->orderBy('v.vacant_since', 'asc'); // Longest vacant first

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('v.jabatan', 'like', "%{$search}%")
                    ->orWhere('v.unit_kerja', 'like', "%{$search}%")
                    ->orWhere('last_emp.nama', 'like', "%{$search}%")
                    ->orWhere('jh.employee_nik', 'like', "%{$search}%");
            });
        }

        if ($unitKerja) {
            $query->where('v.unit_kerja', $unitKerja);
        }

        $results = $query->get();

        // Fetch all pending scheduled mutations (is_applied = false)
        $scheduledByPosition = JobHistory::with('employee')
            ->where('is_applied', false)
            ->get()
            ->keyBy(fn ($jh) => "{$jh->jabatan}|{$jh->unit_kerja}");

        // Enhance each record with Carbon duration helpers and reservation status
        return $results->map(function ($item) use ($scheduledByPosition) {
            $vacantDate = $item->vacant_since ? Carbon::parse($item->vacant_since) : null;
            $daysVacant = $vacantDate ? (int) $vacantDate->diffInDays(now()) : 0;

            // Severity level:
            // green: < 30 days, yellow: 30 - 90 days, red: > 90 days
            $severity = 'green';
            if ($daysVacant > 90) {
                $severity = 'red';
            } elseif ($daysVacant >= 30) {
                $severity = 'yellow';
            }

            $key = "{$item->jabatan}|{$item->unit_kerja}";
            $scheduled = $scheduledByPosition->get($key);

            $item->days_vacant = $daysVacant;
            $item->duration_human = $vacantDate ? $vacantDate->diffForHumans(now(), ['parts' => 2, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]) : 'Tidak diketahui';
            $item->vacant_since_formatted = $vacantDate ? $vacantDate->translatedFormat('d M Y') : '—';
            $item->severity = $severity;
            $item->is_reserved = $scheduled !== null;
            $item->reserved_occupant_name = $scheduled?->employee?->nama;
            $item->reserved_occupant_nik = $scheduled?->employee_nik;
            $item->reserved_tmt = $scheduled?->tmt_awal ? Carbon::parse($scheduled->tmt_awal)->translatedFormat('d M Y') : null;
            $item->reserved_sk = $scheduled?->nomor_sk;

            return $item;
        });
    }

    /**
     * Get summary metrics for the vacancy dashboard.
     */
    public function getStatistics(): array
    {
        $allVacancies = $this->getVacancies();

        $totalVacancies = $allVacancies->count();
        $unitsAffected = $allVacancies->pluck('unit_kerja')->unique()->count();
        $longestVacant = $allVacancies->first(); // Since it is ordered by vacant_since ASC

        $criticalCount = $allVacancies->where('severity', 'red')->count();
        $moderateCount = $allVacancies->where('severity', 'yellow')->count();
        $recentCount = $allVacancies->where('severity', 'green')->count();

        return [
            'total_vacancies' => $totalVacancies,
            'units_affected' => $unitsAffected,
            'longest_vacant' => $longestVacant,
            'critical_count' => $criticalCount,
            'moderate_count' => $moderateCount,
            'recent_count' => $recentCount,
        ];
    }

    /**
     * Get list of all distinct units that have vacancies for filtering dropdowns.
     */
    public function getVacantUnits(): Collection
    {
        return $this->getVacancies()->pluck('unit_kerja')->unique()->values();
    }

    /** Label status formasi jabatan; dipakai layar Formasi, filter, dan ekspor agar istilahnya sama. */
    public const SEAT_STATUS_LABELS = [
        'over' => 'Melebihi kuota',
        'partial' => 'Sebagian kosong',
        'acting' => 'Diisi Pj/Plt',
        'empty' => 'Kosong',
        'full' => 'Penuh',
    ];

    /** Status yang masih memiliki kekosongan (kuota belum terisi pejabat definitif). */
    public const VACANT_STATUSES = ['partial', 'acting', 'empty'];

    /** Nilai filter status yang mencakup seluruh VACANT_STATUSES sekaligus. */
    public const STATUS_ANY_VACANT = 'kosong';

    /**
     * Sumber tunggal status formasi jabatan. Hanya pejabat definitif yang mengisi kuota; Pj/Plt adalah penugasan
     * sementara sehingga formasi yang hanya dijabat Pj/Plt tetap lowong (DOMAIN.md §5.2).
     */
    public static function classifySeat(int $kuota, int $definitive, int $acting): string
    {
        return match (true) {
            $definitive > $kuota => 'over',
            $definitive >= $kuota => 'full',
            $definitive === 0 && $acting > 0 => 'acting',
            $definitive === 0 => 'empty',
            default => 'partial',
        };
    }

    /**
     * Seluruh formasi jabatan (unit aktif, jabatan aktif), terisi maupun kosong, beserta statusnya. Dihitung langsung
     * dari position_unit dan karyawan, tanpa status tersimpan, sehingga tidak dapat bergeser dari data karyawan.
     * Formasi yang tidak pernah terisi ikut terdeteksi. Urutan bawaan: kekosongan terbanyak, lalu terlama kosong.
     *
     * `$status` berisi kunci SEAT_STATUS_LABELS atau STATUS_ANY_VACANT; nilai lain tidak cocok dengan apa pun.
     *
     * @return Collection<int, object>
     */
    public function getFormasiSeats(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $status = null,
        ?string $wilayah = null,
        ?string $rmLevel = null,
        ?string $bidang = null,
    ): Collection {
        $rows = $this->formasiRows();

        if ($search !== null && $search !== '') {
            $term = mb_strtolower($search);
            $rows = $rows->filter(fn (object $row) => str_contains(mb_strtolower($row->jabatan), $term)
                || str_contains(mb_strtolower($row->family), $term)
                || str_contains(mb_strtolower($row->unit_kerja), $term));
        }

        foreach (['unit_kerja' => $unitKerja, 'wilayah' => $wilayah, 'rm_level' => $rmLevel, 'bidang' => $bidang] as $column => $value) {
            if ($value !== null && $value !== '') {
                $rows = $rows->where($column, $value);
            }
        }

        if ($status !== null && $status !== '') {
            $rows = $status === self::STATUS_ANY_VACANT
                ? $rows->whereIn('status', self::VACANT_STATUSES)
                : $rows->where('status', $status);
        }

        return $rows
            ->sortBy([
                fn (object $a, object $b) => $b->vacant_slots <=> $a->vacant_slots,
                // Formasi that were never filled have no date and follow the dated ones.
                fn (object $a, object $b) => ($b->days_vacant ?? -1) <=> ($a->days_vacant ?? -1),
                fn (object $a, object $b) => strcmp($a->unit_kerja, $b->unit_kerja),
                fn (object $a, object $b) => strcmp($a->jabatan, $b->jabatan),
            ])
            ->values();
    }

    /**
     * Pelacak formasi kosong: formasi jabatan yang kuotanya belum terisi pejabat definitif.
     * Bawaan menampilkan seluruh status kosong; `$status` dapat mempersempitnya.
     *
     * @return Collection<int, object>
     */
    public function getFormasiVacancies(?string $search = null, ?string $unitKerja = null, ?string $status = null): Collection
    {
        return $this->getFormasiSeats($search, $unitKerja, $status !== null && $status !== '' ? $status : self::STATUS_ANY_VACANT);
    }

    /**
     * Sebaran per rumpun jabatan (JabatanFamily): satu baris per rumpun dengan total kuota, terisi, kosong, dan
     * jumlah unit, beserta rincian formasi per unit. Filter diterapkan pada formasi sebelum dikelompokkan.
     *
     * @return Collection<int, object>
     */
    public function getJabatanDistribution(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $status = null,
        ?string $wilayah = null,
        ?string $rmLevel = null,
        ?string $bidang = null,
    ): Collection {
        return $this->getFormasiSeats($search, $unitKerja, $status, $wilayah, $rmLevel, $bidang)
            ->groupBy('family')
            ->map(function (Collection $seats, string $family): object {
                $kuota = (int) $seats->sum('kuota');
                $filled = (int) $seats->sum('filled');

                return (object) [
                    'family' => $family,
                    'rm_levels' => $seats->pluck('rm_level')->filter()->unique()->sort()->values()->all(),
                    'jabatan_count' => $seats->pluck('jabatan')->unique()->count(),
                    'unit_count' => $seats->pluck('unit_id')->unique()->count(),
                    'kuota' => $kuota,
                    'filled' => $filled,
                    'acting' => (int) $seats->sum('acting'),
                    'vacant_slots' => (int) $seats->sum('vacant_slots'),
                    'vacant_units' => $seats->where('vacant_slots', '>', 0)->pluck('unit_id')->unique()->count(),
                    'fill_rate' => $kuota > 0 ? (int) round(min($filled, $kuota) / $kuota * 100) : 0,
                    'seats' => $seats->values(),
                ];
            })
            ->sortBy([
                fn (object $a, object $b) => $b->vacant_slots <=> $a->vacant_slots,
                fn (object $a, object $b) => strcmp($a->family, $b->family),
            ])
            ->values();
    }

    /**
     * Ringkasan seluruh formasi (bukan hanya yang lowong) untuk kartu KPI.
     *
     * @return array{total_seats: int, total_kuota: int, filled: int, vacant_slots: int, vacant_formasi: int, acting_count: int, over_count: int, fill_rate: int}
     */
    public function getFormasiStatistics(): array
    {
        $rows = $this->formasiRows();
        $kuota = (int) $rows->sum('kuota');
        $filled = (int) $rows->sum('filled');

        return [
            'total_seats' => $rows->count(),
            'total_kuota' => $kuota,
            'filled' => $filled,
            'vacant_slots' => (int) $rows->sum('vacant_slots'),
            'vacant_formasi' => $rows->where('vacant_slots', '>', 0)->count(),
            'acting_count' => $rows->where('status', 'acting')->count(),
            'over_count' => $rows->where('status', 'over')->count(),
            'fill_rate' => $kuota > 0 ? (int) round(min($filled, $kuota) / $kuota * 100) : 0,
        ];
    }

    /**
     * Jumlah lowongan per unit untuk kolom "Lowong" pada daftar unit.
     *
     * @return array<int, array{vacant_slots: int, vacant_formasi: int}> keyed by unit id
     */
    public function getFormasiUnitSummary(): array
    {
        return $this->formasiRows()
            ->groupBy('unit_id')
            ->map(fn (Collection $rows) => [
                'vacant_slots' => (int) $rows->sum('vacant_slots'),
                'vacant_formasi' => $rows->where('vacant_slots', '>', 0)->count(),
            ])
            ->all();
    }

    /**
     * Seluruh formasi jabatan pada unit dan jabatan aktif beserta statusnya. Empat kueri tetap (formasi, pemegang,
     * tanggal keluar terakhir, pemesanan) tanpa N+1.
     *
     * @return Collection<int, object>
     */
    private function formasiRows(): Collection
    {
        $seats = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('units.is_active', true)
            ->where('positions.is_active', true)
            ->get([
                'position_unit.id as position_unit_id', 'position_unit.kuota', 'position_unit.bidang',
                'units.id as unit_id', 'units.nama as unit_kerja', 'units.wilayah',
                'positions.nama as jabatan', 'positions.level', 'positions.rm_level',
            ]);

        if ($seats->isEmpty()) {
            return collect();
        }

        // Holders per (unit, jabatan): definitive vs acting (Pj/Plt).
        $holders = Employee::query()
            ->selectRaw('unit_kerja, jabatan, status_penugasan, count(*) as total')
            ->groupBy('unit_kerja', 'jabatan', 'status_penugasan')
            ->get()
            ->groupBy(fn ($row) => "{$row->unit_kerja}|{$row->jabatan}");

        $lastDeparture = DB::table('job_histories')
            ->whereNull('deleted_at')
            ->whereNotNull('tmt_akhir')
            ->selectRaw('jabatan, unit_kerja, max(tmt_akhir) as vacant_since')
            ->groupBy('jabatan', 'unit_kerja')
            ->get()
            ->keyBy(fn ($row) => "{$row->jabatan}|{$row->unit_kerja}");

        $reserved = JobHistory::with('employee:nik,nama')
            ->where('is_applied', false)
            ->get()
            ->keyBy(fn ($jh) => "{$jh->jabatan}|{$jh->unit_kerja}");

        return $seats->map(function (object $seat) use ($holders, $lastDeparture, $reserved): object {
            $group = $holders->get("{$seat->unit_kerja}|{$seat->jabatan}", collect());
            $acting = (int) $group->filter(fn ($row) => in_array(strtolower((string) $row->status_penugasan), ['pj', 'plt'], true))->sum('total');
            $definitive = (int) $group->sum('total') - $acting;
            $kuota = (int) $seat->kuota;

            $key = "{$seat->jabatan}|{$seat->unit_kerja}";
            $departure = $lastDeparture->get($key)?->vacant_since;
            $vacantSince = $departure ? Carbon::parse($departure) : null;
            $daysVacant = $vacantSince ? (int) $vacantSince->diffInDays(now()) : null;
            $booking = $reserved->get($key);

            $seat->family = JabatanFamily::of($seat->jabatan);
            $seat->kuota = $kuota;
            $seat->filled = $definitive;
            $seat->acting = $acting;
            $seat->vacant_slots = max($kuota - $definitive, 0);
            $seat->status = self::classifySeat($kuota, $definitive, $acting);
            $seat->status_label = self::SEAT_STATUS_LABELS[$seat->status];
            $seat->vacant_since = $vacantSince?->toDateString();
            $seat->vacant_since_formatted = $vacantSince?->translatedFormat('d M Y');
            $seat->days_vacant = $daysVacant;
            $seat->severity = match (true) {
                $daysVacant === null => 'unknown',
                $daysVacant > 90 => 'red',
                $daysVacant >= 30 => 'yellow',
                default => 'green',
            };
            $seat->is_reserved = $booking !== null;
            $seat->reserved_occupant_name = $booking?->employee?->nama;
            $seat->reserved_tmt = $booking?->tmt_awal ? Carbon::parse($booking->tmt_awal)->translatedFormat('d M Y') : null;

            return $seat;
        });
    }

    /**
     * Fill a vacant position by assigning / promoting / transferring an employee.
     */
    public function fillVacancy(
        string $nik,
        string $targetJabatan,
        string $targetUnitKerja,
        string $tmtAwal,
        ?string $jenisMutasi = 'MUTASI_UNIT',
        ?string $nomorSk = null,
        ?string $tanggalSk = null,
        ?string $catatan = null
    ): bool {
        return DB::transaction(function () use (
            $nik,
            $targetJabatan,
            $targetUnitKerja,
            $tmtAwal,
            $jenisMutasi,
            $nomorSk,
            $tanggalSk,
            $catatan
        ) {
            $employee = Employee::where('nik', $nik)->firstOrFail();

            // 1. Close current active job history
            $currentJob = $employee->currentJob;
            if ($currentJob) {
                $currentJob->update([
                    'tmt_akhir' => Carbon::parse($tmtAwal)->subDay()->toDateString(),
                ]);
            }

            // 2. Update employee's primary profile
            // Temporarily disable model events so EmployeeObserver doesn't create a duplicate row
            $dispatcher = Employee::getEventDispatcher();
            $employee->unsetEventDispatcher();
            $employee->update([
                'jabatan' => $targetJabatan,
                'unit_kerja' => $targetUnitKerja,
                'tanggal_dalam_jabatan' => $tmtAwal,
                'tmt_unit_kerja' => $tmtAwal,
            ]);
            // Restore the event dispatcher so subsequent Eloquent operations work normally
            Employee::setEventDispatcher($dispatcher);

            // 3. Create the new active JobHistory row
            JobHistory::create([
                'employee_nik' => $employee->nik,
                'jabatan' => $targetJabatan,
                'unit_kerja' => $targetUnitKerja,
                'status_penugasan' => $employee->status_penugasan ?? 'Definitif',
                'level' => $employee->level,
                'golongan' => $employee->golongan,
                'tmt_awal' => $tmtAwal,
                'tmt_akhir' => null,
                'jenis_mutasi' => $jenisMutasi,
                'nomor_sk' => $nomorSk,
                'tanggal_sk' => $tanggalSk,
                'catatan' => $catatan ?? "Mengisi formasi/jabatan kosong di {$targetUnitKerja}",
            ]);

            return true;
        });
    }

    /**
     * Atomically swap the positions of two active employees (Tukar Jabatan / Rotasi Bersilang).
     * The operation runs inside a single transaction so there is never a moment
     * where two people share the same position or a phantom vacancy is created.
     *
     * Returns true on success; throws an exception on failure (transaction rolls back automatically).
     */
    public function switchPositions(
        string $nikA,
        string $nikB,
        string $tmtAwal,
        ?string $jenisMutasi = 'ROTASI',
        ?string $nomorSk = null,
        ?string $tanggalSk = null,
        ?string $catatan = null
    ): bool {
        if ($nikA === $nikB) {
            throw new \InvalidArgumentException('Kedua karyawan harus berbeda untuk melakukan tukar jabatan.');
        }

        return DB::transaction(function () use (
            $nikA, $nikB, $tmtAwal, $jenisMutasi, $nomorSk, $tanggalSk, $catatan
        ) {
            $employeeA = Employee::with('currentJob')->where('nik', $nikA)->firstOrFail();
            $employeeB = Employee::with('currentJob')->where('nik', $nikB)->firstOrFail();

            if (! $employeeA->currentJob) {
                throw new \RuntimeException("Karyawan {$employeeA->nama} tidak memiliki jabatan aktif untuk ditukar.");
            }
            if (! $employeeB->currentJob) {
                throw new \RuntimeException("Karyawan {$employeeB->nama} tidak memiliki jabatan aktif untuk ditukar.");
            }

            // Snapshot positions BEFORE any changes
            $posA = [
                'jabatan' => $employeeA->jabatan,
                'unit_kerja' => $employeeA->unit_kerja,
                'status_penugasan' => $employeeA->status_penugasan,
                'level' => $employeeA->level,
                'golongan' => $employeeA->golongan,
            ];
            $posB = [
                'jabatan' => $employeeB->jabatan,
                'unit_kerja' => $employeeB->unit_kerja,
                'status_penugasan' => $employeeB->status_penugasan,
                'level' => $employeeB->level,
                'golongan' => $employeeB->golongan,
            ];

            $tmtAkhir = Carbon::parse($tmtAwal)->subDay()->toDateString();

            // 1. Close both active job histories
            $employeeA->currentJob->update(['tmt_akhir' => $tmtAkhir]);
            $employeeB->currentJob->update(['tmt_akhir' => $tmtAkhir]);

            // 2. Swap employee profile records (disable events to prevent observer double-insert)
            $dispatcher = Employee::getEventDispatcher();
            $employeeA->unsetEventDispatcher();
            $employeeB->unsetEventDispatcher();

            $employeeA->update([
                'jabatan' => $posB['jabatan'],
                'unit_kerja' => $posB['unit_kerja'],
                'tanggal_dalam_jabatan' => $tmtAwal,
                'tmt_unit_kerja' => $tmtAwal,
            ]);
            $employeeB->update([
                'jabatan' => $posA['jabatan'],
                'unit_kerja' => $posA['unit_kerja'],
                'tanggal_dalam_jabatan' => $tmtAwal,
                'tmt_unit_kerja' => $tmtAwal,
            ]);

            // Restore event dispatcher
            Employee::setEventDispatcher($dispatcher);

            // 3. Create two new active job history rows (cross-swapped)
            $baseNote = $catatan ?? 'Tukar jabatan (rotasi bersilang)';
            JobHistory::create([
                'employee_nik' => $nikA,
                'jabatan' => $posB['jabatan'],
                'unit_kerja' => $posB['unit_kerja'],
                'status_penugasan' => $posB['status_penugasan'] ?? 'Definitif',
                'level' => $posB['level'],
                'golongan' => $posB['golongan'],
                'tmt_awal' => $tmtAwal,
                'tmt_akhir' => null,
                'jenis_mutasi' => $jenisMutasi,
                'nomor_sk' => $nomorSk,
                'tanggal_sk' => $tanggalSk,
                'catatan' => "{$baseNote} — bertukar dengan {$employeeB->nama}",
            ]);
            JobHistory::create([
                'employee_nik' => $nikB,
                'jabatan' => $posA['jabatan'],
                'unit_kerja' => $posA['unit_kerja'],
                'status_penugasan' => $posA['status_penugasan'] ?? 'Definitif',
                'level' => $posA['level'],
                'golongan' => $posA['golongan'],
                'tmt_awal' => $tmtAwal,
                'tmt_akhir' => null,
                'jenis_mutasi' => $jenisMutasi,
                'nomor_sk' => $nomorSk,
                'tanggal_sk' => $tanggalSk,
                'catatan' => "{$baseNote} — bertukar dengan {$employeeA->nama}",
            ]);

            return true;
        });
    }
}
