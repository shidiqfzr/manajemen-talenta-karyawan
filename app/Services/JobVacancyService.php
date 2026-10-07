<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
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

        // Enhance each record with Carbon duration helpers
        return $results->map(function ($item) {
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

            $item->days_vacant = $daysVacant;
            $item->duration_human = $vacantDate ? $vacantDate->diffForHumans(now(), ['parts' => 2, 'syntax' => Carbon::DIFF_RELATIVE_TO_NOW]) : 'Tidak diketahui';
            $item->vacant_since_formatted = $vacantDate ? $vacantDate->translatedFormat('d M Y') : '—';
            $item->severity = $severity;

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
                'level' => $employeeA->level,
                'golongan' => $employeeA->golongan,
            ];
            $posB = [
                'jabatan' => $employeeB->jabatan,
                'unit_kerja' => $employeeB->unit_kerja,
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
