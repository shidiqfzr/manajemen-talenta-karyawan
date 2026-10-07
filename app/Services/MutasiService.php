<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MutasiService
{
    // -------------------------------------------------------------------------
    // Analysis
    // -------------------------------------------------------------------------

    /**
     * Analyse what will happen if an employee is moved to the given target position.
     * Returns a structured result that drives the live impact panel in the UI.
     */
    public function analyzeTarget(string $jabatan, string $unitKerja): array
    {
        $occupant = Employee::with('currentJob')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->first();

        return [
            'vacant' => $occupant === null,
            'occupant' => $occupant,
            'occupant_job' => $occupant?->currentJob,
        ];
    }

    // -------------------------------------------------------------------------
    // Core Mutasi — single entry point for ALL transfer types
    // -------------------------------------------------------------------------

    /**
     * Process any position change atomically.
     *
     * Auto-detects:
     *  - Target VACANT  → straight transfer (creates 1 chain vacancy at employee's old position)
     *  - Target OCCUPIED → swap (both employees exchange positions, zero net vacancies)
     *
     * Returns an array describing the result for the success flash message.
     *
     * @throws \InvalidArgumentException if employee is already at the target position
     * @throws \RuntimeException if target occupant has no active job (edge-case guard)
     */
    public function processMutasi(
        string $employeeNik,
        string $targetJabatan,
        string $targetUnitKerja,
        string $tmtAwal,
        string $jenisMutasi = 'MUTASI_UNIT',
        ?string $nomorSk = null,
        ?string $tanggalSk = null,
        ?string $catatan = null,
    ): array {
        return DB::transaction(function () use (
            $employeeNik, $targetJabatan, $targetUnitKerja,
            $tmtAwal, $jenisMutasi, $nomorSk, $tanggalSk, $catatan
        ) {
            $employee = Employee::with('currentJob')
                ->where('nik', $employeeNik)
                ->firstOrFail();

            // Guard: same position
            if (
                $employee->jabatan === $targetJabatan &&
                $employee->unit_kerja === $targetUnitKerja
            ) {
                throw new \InvalidArgumentException(
                    "Karyawan {$employee->nama} sudah berada di posisi {$targetJabatan} di {$targetUnitKerja}."
                );
            }

            $tmtAkhir = Carbon::parse($tmtAwal)->subDay()->toDateString();
            $analysis = $this->analyzeTarget($targetJabatan, $targetUnitKerja);

            if ($analysis['vacant']) {
                // ── Straight transfer ─────────────────────────────────────
                $this->doTransfer(
                    $employee, $targetJabatan, $targetUnitKerja,
                    $tmtAwal, $tmtAkhir, $jenisMutasi, $nomorSk, $tanggalSk, $catatan
                );

                return [
                    'type' => 'transfer',
                    'employee' => $employee->nama,
                    'to_jabatan' => $targetJabatan,
                    'to_unit' => $targetUnitKerja,
                    'displaced' => null,
                ];
            } else {
                // ── Swap (target occupied) ────────────────────────────────
                $occupant = $analysis['occupant'];

                if (! $occupant->currentJob) {
                    throw new \RuntimeException(
                        "Karyawan {$occupant->nama} yang menempati posisi tersebut tidak memiliki riwayat jabatan aktif."
                    );
                }

                $this->doSwap(
                    $employee, $occupant, $targetJabatan, $targetUnitKerja,
                    $tmtAwal, $tmtAkhir, $jenisMutasi, $nomorSk, $tanggalSk, $catatan
                );

                return [
                    'type' => 'swap',
                    'employee' => $employee->nama,
                    'to_jabatan' => $targetJabatan,
                    'to_unit' => $targetUnitKerja,
                    'displaced' => $occupant->nama,
                ];
            }
        });
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** Move one employee to a (currently vacant) target position. */
    private function doTransfer(
        Employee $employee,
        string $targetJabatan,
        string $targetUnitKerja,
        string $tmtAwal,
        string $tmtAkhir,
        string $jenisMutasi,
        ?string $nomorSk,
        ?string $tanggalSk,
        ?string $catatan,
    ): void {
        // 1. Close current job history
        $employee->currentJob?->update(['tmt_akhir' => $tmtAkhir]);

        // 2. Update employee profile (bypass observer to avoid double history row)
        $dispatcher = Employee::getEventDispatcher();
        $employee->unsetEventDispatcher();
        $employee->update([
            'jabatan' => $targetJabatan,
            'unit_kerja' => $targetUnitKerja,
            'tanggal_dalam_jabatan' => $tmtAwal,
            'tmt_unit_kerja' => $tmtAwal,
        ]);
        Employee::setEventDispatcher($dispatcher);

        // 3. Create new active job history
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
            'catatan' => $catatan ?? "Mutasi ke {$targetJabatan} di {$targetUnitKerja}",
        ]);
    }

    /**
     * Atomically swap two employees' positions.
     * Creates ZERO net vacancies — both old histories close and two new ones open simultaneously.
     */
    private function doSwap(
        Employee $empA,
        Employee $empB,
        string $targetJabatan,  // empA's target (= empB's current position)
        string $targetUnitKerja,
        string $tmtAwal,
        string $tmtAkhir,
        string $jenisMutasi,
        ?string $nomorSk,
        ?string $tanggalSk,
        ?string $catatan,
    ): void {
        // Snapshot both positions BEFORE touching anything
        $posA = [
            'jabatan' => $empA->jabatan,
            'unit_kerja' => $empA->unit_kerja,
            'level' => $empA->level,
            'golongan' => $empA->golongan,
        ];
        $posB = [
            'jabatan' => $empB->jabatan,
            'unit_kerja' => $empB->unit_kerja,
            'level' => $empB->level,
            'golongan' => $empB->golongan,
        ];

        // 1. Close both active job histories
        $empA->currentJob->update(['tmt_akhir' => $tmtAkhir]);
        $empB->currentJob->update(['tmt_akhir' => $tmtAkhir]);

        // 2. Swap employee profiles atomically (bypass observer)
        $dispatcher = Employee::getEventDispatcher();
        $empA->unsetEventDispatcher();
        $empB->unsetEventDispatcher();

        $empA->update([
            'jabatan' => $posB['jabatan'],
            'unit_kerja' => $posB['unit_kerja'],
            'tanggal_dalam_jabatan' => $tmtAwal,
            'tmt_unit_kerja' => $tmtAwal,
        ]);
        $empB->update([
            'jabatan' => $posA['jabatan'],
            'unit_kerja' => $posA['unit_kerja'],
            'tanggal_dalam_jabatan' => $tmtAwal,
            'tmt_unit_kerja' => $tmtAwal,
        ]);

        Employee::setEventDispatcher($dispatcher);

        // 3. Create two new active job history rows (cross-swapped)
        $baseNote = $catatan ?? 'Tukar jabatan / rotasi bersilang';
        JobHistory::create([
            'employee_nik' => $empA->nik,
            'jabatan' => $posB['jabatan'],
            'unit_kerja' => $posB['unit_kerja'],
            'level' => $posB['level'],
            'golongan' => $posB['golongan'],
            'tmt_awal' => $tmtAwal,
            'tmt_akhir' => null,
            'jenis_mutasi' => $jenisMutasi,
            'nomor_sk' => $nomorSk,
            'tanggal_sk' => $tanggalSk,
            'catatan' => "{$baseNote} — bertukar dengan {$empB->nama}",
        ]);
        JobHistory::create([
            'employee_nik' => $empB->nik,
            'jabatan' => $posA['jabatan'],
            'unit_kerja' => $posA['unit_kerja'],
            'level' => $posA['level'],
            'golongan' => $posA['golongan'],
            'tmt_awal' => $tmtAwal,
            'tmt_akhir' => null,
            'jenis_mutasi' => $jenisMutasi,
            'nomor_sk' => $nomorSk,
            'tanggal_sk' => $tanggalSk,
            'catatan' => "{$baseNote} — bertukar dengan {$empA->nama}",
        ]);
    }

    // -------------------------------------------------------------------------
    // History / Reporting
    // -------------------------------------------------------------------------

    /**
     * Paginated system-wide mutation history (all job_histories, newest first).
     */
    public function getMutasiHistory(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $jenisMutasi = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return JobHistory::with('employee')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('jabatan', 'like', "%{$search}%")
                        ->orWhere('unit_kerja', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($q3) => $q3->where('nama', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%")
                        );
                });
            })
            ->when($unitKerja, fn ($q) => $q->where('unit_kerja', $unitKerja))
            ->when($jenisMutasi, fn ($q) => $q->where('jenis_mutasi', $jenisMutasi))
            ->orderByDesc('tmt_awal')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * All distinct jabatan values known in the system (for dropdowns).
     */
    public function getKnownJabatan(): Collection
    {
        return DB::table('job_histories')
            ->select('jabatan')->distinct()
            ->orderBy('jabatan')->pluck('jabatan');
    }

    /**
     * All distinct unit_kerja values known in the system (for dropdowns).
     */
    public function getKnownUnits(): Collection
    {
        return DB::table('job_histories')
            ->select('unit_kerja')->distinct()
            ->orderBy('unit_kerja')->pluck('unit_kerja');
    }
}
