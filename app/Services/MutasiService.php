<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
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
        // Check if the position is an overflow/advisor or diperban (pool) position
        $position = Position::getAllCached()->get($jabatan);
        $isPool = $position ? ($position->is_overflow || $position->is_diperban) : false;

        if ($isPool) {
            // Pools are infinitely vacant and should never trigger a Swap
            return [
                'type' => 'pool',
                'vacant' => true,
                'occupant' => null,
                'occupant_job' => null,
            ];
        }

        $occupant = Employee::with('currentJob')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->first();

        return [
            'type' => $occupant === null ? 'vacant' : 'occupied',
            'vacant' => $occupant === null,
            'occupant' => $occupant,
            'occupant_job' => $occupant?->currentJob,
        ];
    }

    /**
     * Check tenure compliance in the current unit kerja based on DOMAIN.md §5.2.1
     * (Pegawai minimal menjabat 2-3 tahun di unit kerja sebelum diperbolehkan rotasi unit).
     */
    public function checkTenureCompliance(Employee $emp, string $targetUnitKerja): array
    {
        $isDifferentUnit = $emp->unit_kerja !== $targetUnitKerja;
        if (! $isDifferentUnit) {
            return [
                'is_applicable' => false,
                'is_compliant' => true,
                'months' => 0,
                'years' => 0.0,
                'tmt_unit_kerja' => $emp->tmt_unit_kerja?->toDateString(),
                'message' => 'Rotasi internal dalam unit kerja yang sama (tidak pindah lokasi/unit).',
            ];
        }

        $tmtDate = $emp->tmt_unit_kerja ?? $emp->tanggal_dalam_jabatan;
        if (! $tmtDate) {
            return [
                'is_applicable' => true,
                'is_compliant' => false,
                'months' => 0,
                'years' => 0.0,
                'tmt_unit_kerja' => null,
                'message' => 'Data TMT Unit Kerja belum tercatat. Standar minimal masa dinas adalah 2 tahun (24 bulan) sesuai DOMAIN.md §5.2.1.',
            ];
        }

        $carbonTmt = Carbon::parse($tmtDate);
        $months = (int) $carbonTmt->diffInMonths(now());
        $years = round($months / 12, 1);
        $isCompliant = $months >= 24;

        return [
            'is_applicable' => true,
            'is_compliant' => $isCompliant,
            'months' => $months,
            'years' => $years,
            'tmt_unit_kerja' => $carbonTmt->toDateString(),
            'message' => $isCompliant
                ? "Masa dinas di unit saat ini ({$years} tahun / {$months} bulan) telah memenuhi standar minimal 2 tahun."
                : "Pegawai baru bertugas {$years} tahun ({$months} bulan) di {$emp->unit_kerja} (standar minimal 2-3 tahun sesuai DOMAIN.md §5.2.1). Memerlukan Dispensasi Khusus Direksi.",
        ];
    }

    /**
     * Check employee promotion readiness and eligibility based on DOMAIN.md §5.2.3 and §6.
     */
    public function checkPromotionEligibility(Employee $emp, string $targetJabatan): array
    {
        $currentRm = app(ManPowerPlanningService::class)->determineRmLevel($emp);
        $currentRmNum = $this->parseRmNumber($currentRm);

        $positions = Position::getAllCached();
        $targetRm = isset($positions[$targetJabatan]) && $positions[$targetJabatan]->rm_level
            ? $positions[$targetJabatan]->rm_level
            : app(ManPowerPlanningService::class)->determineRmLevelByJobTitle($targetJabatan);
        $targetRmNum = $this->parseRmNumber($targetRm);

        // If not a promotion (or target rank not higher), return not applicable
        if ($targetRmNum === null || $currentRmNum === null || $targetRmNum >= $currentRmNum) {
            return [
                'is_applicable' => false,
                'is_eligible' => true,
                'current_rm' => $currentRm,
                'target_rm' => $targetRm,
                'criteria' => [],
                'summary' => 'Bukan kenaikan eselon (promosi struktural).',
            ];
        }

        $criteria = [];
        $isEligible = true;

        // 1. Calculate job tenure (masa dinas jabatan saat ini)
        $tmtJabatan = $emp->tanggal_dalam_jabatan ?? $emp->tmt_bekerja;
        $jobTenureMonths = $tmtJabatan ? (int) Carbon::parse($tmtJabatan)->diffInMonths(now()) : 0;
        $jobTenureYears = round($jobTenureMonths / 12, 1);

        // 2. Fetch latest evaluation / assessment
        $latestEvaluation = $emp->evaluations()->latest('id')->first();

        // 3. Normalize employee golongan
        $gol = strtoupper(trim(explode('/', $emp->golongan ?? '')[0])); // e.g. 'IIIC', 'IVA'

        if ($currentRmNum === 3 && $targetRmNum === 2) {
            // RM-3 -> RM-2: Syarat minimal Golongan IIIC atau masa dinas Asisten >= 4 tahun, dan evaluasi kinerja minimal "Baik"
            $hasMinGol = in_array($gol, ['IIIC', 'IIID', 'IVA', 'IVB', 'IVC', 'IVD']);
            $hasMinTenure = $jobTenureYears >= 4.0;
            $golOrTenureMet = $hasMinGol || $hasMinTenure;

            $criteria[] = [
                'label' => 'Golongan Minimal IIIC atau Masa Dinas Asisten ≥ 4 Tahun',
                'met' => $golOrTenureMet,
                'value' => "Gol: {$emp->golongan} | Masa Dinas: {$jobTenureYears} thn",
                'requirement' => 'Min. Gol IIIC atau Masa Dinas ≥ 4 tahun',
            ];

            // Kinerja check
            $hasGoodEval = false;
            $evalText = 'Belum ada data evaluasi SMKBK / 9-Box';
            if ($latestEvaluation) {
                $score = $latestEvaluation->skor_smkbk_9box ?? 0;
                $box = $latestEvaluation->kategori_9box ?? '';
                $hasGoodEval = $score >= 75 || str_contains($box, 'Box 5') || str_contains($box, 'Box 6') || str_contains($box, 'Box 8') || str_contains($box, 'Box 9');
                $evalText = 'SMKBK: '.($score ?: '—').' ('.($box ?: 'Tercatat').')';
            }

            $criteria[] = [
                'label' => 'Evaluasi Kinerja Minimal Kategori "Baik"',
                'met' => $hasGoodEval,
                'value' => $evalText,
                'requirement' => 'Kategori Baik / Box 5, 6, 8, atau 9',
            ];

            $isEligible = $golOrTenureMet && $hasGoodEval;
        } elseif ($currentRmNum === 2 && $targetRmNum === 1) {
            // RM-2 -> RM-1: Syarat minimal Golongan IIID/IVA, menjabat Askep min 3 tahun, sertifikat asesmen kompetensi holding
            $hasMinGol = in_array($gol, ['IIID', 'IVA', 'IVB', 'IVC', 'IVD']);
            $criteria[] = [
                'label' => 'Standar Golongan Minimal IIID / IVA',
                'met' => $hasMinGol,
                'value' => "Gol: {$emp->golongan}",
                'requirement' => 'Golongan IIID s.d. IV',
            ];

            $hasAskepTenure = $jobTenureYears >= 3.0;
            $criteria[] = [
                'label' => 'Masa Dinas Jabatan Madya (Askep/Kasubag) ≥ 3 Tahun',
                'met' => $hasAskepTenure,
                'value' => "{$jobTenureYears} tahun ({$jobTenureMonths} bulan)",
                'requirement' => 'Minimal 3 tahun menjabat',
            ];

            $hasValidAssessment = false;
            $asesmenText = 'Belum memiliki sertifikasi asesmen holding';
            if ($latestEvaluation && $latestEvaluation->expired_asesmen) {
                $hasValidAssessment = Carbon::parse($latestEvaluation->expired_asesmen)->gte(Carbon::today());
                $asesmenText = 'Kedaluwarsa: '.Carbon::parse($latestEvaluation->expired_asesmen)->format('d/m/Y');
            } elseif ($latestEvaluation && $latestEvaluation->hasil_skor_asesmen) {
                $hasValidAssessment = true;
                $asesmenText = "Skor: {$latestEvaluation->hasil_skor_asesmen}";
            }

            $criteria[] = [
                'label' => 'Sertifikat Asesmen Kompetensi Holding Aktif',
                'met' => $hasValidAssessment,
                'value' => $asesmenText,
                'requirement' => 'Asesmen holding valid/aktif',
            ];

            $isEligible = $hasMinGol && $hasAskepTenure && $hasValidAssessment;
        } elseif ($currentRmNum === 4 && $targetRmNum <= 3) {
            // Pelaksana ke Staf via CKP
            $criteria[] = [
                'label' => 'Jalur Karir CKP (Calon Karyawan Pimpinan)',
                'met' => true,
                'value' => 'Promosi Karpel ke Karpim',
                'requirement' => 'Lulus seleksi CKP internal',
            ];
            $isEligible = true;
        }

        return [
            'is_applicable' => true,
            'is_eligible' => $isEligible,
            'current_rm' => $currentRm,
            'target_rm' => $targetRm,
            'criteria' => $criteria,
            'summary' => $isEligible
                ? "Seluruh kriteria formal promosi ke {$targetRm} telah terpenuhi."
                : "Terdapat kriteria formal promosi ke {$targetRm} yang belum terpenuhi. Memerlukan dispensasi promosi khusus.",
        ];
    }

    /**
     * Retrieve and filter available target positions for a specific employee and unit.
     */
    public function getAvailableTargetPositions(Employee $emp, string $targetUnitKerja, string $jenisMutasi): array
    {
        $unit = Unit::where('nama', $targetUnitKerja)->first();
        $allJabatan = [];

        if ($unit) {
            $allJabatan = $unit->positions()->active()->pluck('nama')->toArray();
        } else {
            $historicalJobs = JobHistory::where('unit_kerja', $targetUnitKerja)
                ->pluck('jabatan')->unique()->toArray();
            $activeJobs = Employee::where('unit_kerja', $targetUnitKerja)
                ->pluck('jabatan')->unique()->toArray();

            $allJabatan = array_unique(array_merge($historicalJobs, $activeJobs));
            if (empty($allJabatan)) {
                $allJabatan = app(ManPowerPlanningService::class)->getKnownJabatan()->toArray();
            }
        }

        $empRmLevel = app(ManPowerPlanningService::class)->determineRmLevel($emp);
        $empRmNumber = $this->parseRmNumber($empRmLevel);

        $filtered = collect();
        $positions = Position::getAllCached();
        $isTargetOperational = $unit ? $unit->isOperationalUnit() : false; // Fallback to false if unit is not found (though it should be)

        foreach ($allJabatan as $j) {
            $jRmNumber = null;
            if (isset($positions[$j]) && $positions[$j]->rm_level) {
                $jRmNumber = $this->parseRmNumber($positions[$j]->rm_level);
            } else {
                $jRmNumber = app(ManPowerPlanningService::class)->determineRmLevelByJobTitle($j);
            }

            if ($jenisMutasi === 'PROMOSI') {
                if ($jRmNumber < $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'DEMOSI') {
                if ($jRmNumber > $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif (in_array($jenisMutasi, ['ROTASI', 'MUTASI_UNIT'])) {
                if ($jRmNumber === $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'SANKSI_INDISIPLINER') {
                if (isset($positions[$j]) && $positions[$j]->is_diperban && $jRmNumber === $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'PENUGASAN_KHUSUS') {
                if (isset($positions[$j]) && $positions[$j]->is_overflow && ! $positions[$j]->is_diperban && $jRmNumber === $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'PENUGASAN') {
                $filtered->push($j);
            }
        }

        // SMART OVERRIDE FOR POOLS (in-memory filtering from cached master)
        if (in_array($jenisMutasi, ['SANKSI_INDISIPLINER', 'PENUGASAN_KHUSUS'])) {
            $isSanction = $jenisMutasi === 'SANKSI_INDISIPLINER';
            $poolPositions = $positions->filter(
                fn ($pos) => $isSanction ? $pos->is_diperban : $pos->is_overflow
            );

            foreach ($poolPositions as $pos) {
                if (! $isSanction && $pos->is_diperban) {
                    continue;
                }

                $posRm = $this->parseRmNumber($pos->rm_level);
                if ($posRm === $empRmNumber) {
                    $posNameLower = strtolower($pos->nama);

                    // Domain rules
                    $isTanamanPos = str_contains($posNameLower, 'tanaman');
                    $isTanamanUnit = str_contains(strtolower($targetUnitKerja), 'tanaman') || str_contains(strtolower($targetUnitKerja), 'kebun') || str_contains(strtolower($targetUnitKerja), 'group');
                    if ($isTanamanPos && ! $isTanamanUnit) {
                        continue;
                    }

                    $isTeknikPos = str_contains($posNameLower, 'teknik') || str_contains($posNameLower, 'pabrik');
                    $isTeknikUnit = str_contains(strtolower($targetUnitKerja), 'teknik') || str_contains(strtolower($targetUnitKerja), 'pabrik') || str_contains(strtolower($targetUnitKerja), 'group');
                    if ($isTeknikPos && ! $isTeknikUnit) {
                        continue;
                    }

                    // Nomenclature rules
                    if ($pos->is_diperban && $posRm < 4) {
                        if ($isTargetOperational && ! $pos->isOperationalTitle()) {
                            continue;
                        }
                        if (! $isTargetOperational && ! $pos->isKantorTitle()) {
                            continue;
                        }
                    }

                    if (! $filtered->contains($pos->nama)) {
                        $filtered->push($pos->nama);
                    }
                }
            }
        }

        return $filtered->sort()->values()->toArray();
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
        ?string $tanggalBerakhirSanksi = null,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null,
        ?string $fileSk = null,
        ?int $createdBy = null,
        bool $isDispensasi = false,
        ?string $alasanDispensasi = null,
        ?string $newGolongan = null,
        ?string $newJobGrade = null
    ): array {
        return DB::transaction(function () use (
            $employeeNik, $targetJabatan, $targetUnitKerja,
            $tmtAwal, $jenisMutasi, $nomorSk, $tanggalSk, $catatan, $tanggalBerakhirSanksi, $statusJabatan, $tmtAkhirEstimasi,
            $fileSk, $createdBy, $isDispensasi, $alasanDispensasi, $newGolongan, $newJobGrade
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
                    $tmtAwal, $tmtAkhir, $jenisMutasi, $nomorSk, $tanggalSk, $catatan, $tanggalBerakhirSanksi, $statusJabatan, $tmtAkhirEstimasi,
                    $fileSk, $createdBy, $isDispensasi, $alasanDispensasi, $newGolongan, $newJobGrade
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

                // Guard: Lateral Swap Only (DOMAIN.md §5.2.2 & Enterprise Governance)
                $empARm = $employee->rm_level ?? app(ManPowerPlanningService::class)->determineRmLevel($employee);
                $empBRm = $occupant->rm_level ?? app(ManPowerPlanningService::class)->determineRmLevel($occupant);

                if ($empARm !== $empBRm) {
                    throw new \InvalidArgumentException(
                        "Tukar jabatan (Swap) hanya diperbolehkan untuk tingkat eselon/RM yang setara (Lateral Swap). Pegawai {$employee->nama} ({$empARm}) tidak setara dengan {$occupant->nama} ({$empBRm}). Gunakan alur mutasi ke formasi kosong."
                    );
                }

                $this->doSwap(
                    $employee, $occupant, $targetJabatan, $targetUnitKerja,
                    $tmtAwal, $tmtAkhir, $jenisMutasi, $nomorSk, $tanggalSk, $catatan, $tanggalBerakhirSanksi, $statusJabatan, $tmtAkhirEstimasi,
                    $fileSk, $createdBy, $isDispensasi, $alasanDispensasi, $newGolongan, $newJobGrade
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
        ?string $tanggalBerakhirSanksi = null,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null,
        ?string $fileSk = null,
        ?int $createdBy = null,
        bool $isDispensasi = false,
        ?string $alasanDispensasi = null,
        ?string $newGolongan = null,
        ?string $newJobGrade = null
    ): void {
        $isDefinitif = $statusJabatan === 'Definitif';

        // Derive target RM Level
        $positions = Position::getAllCached();
        $targetRm = isset($positions[$targetJabatan]) && $positions[$targetJabatan]->rm_level
            ? $positions[$targetJabatan]->rm_level
            : app(ManPowerPlanningService::class)->determineRmLevelByJobTitle($targetJabatan);

        // Derive Level (Karpim vs Karpel) according to DOMAIN.md §4.1
        $newLevel = $employee->level;
        if ($targetRm === 'RM-4') {
            $newLevel = 'Karpel';
        } elseif (in_array($targetRm, ['RM-1', 'RM-2', 'RM-3'])) {
            $newLevel = 'Karpim';
        }

        $effectiveGolongan = $newGolongan ?: $employee->golongan;
        $effectiveJobGrade = $newJobGrade ?: $employee->job_grade;

        if ($isDefinitif) {
            // 1. Close current definitive job history
            $employee->currentJob?->update(['tmt_akhir' => $tmtAkhir]);

            // 2. Update employee profile (bypass observer to avoid double history row)
            Employee::withoutEvents(function () use (
                $employee, $targetJabatan, $targetUnitKerja, $tmtAwal,
                $tanggalBerakhirSanksi, $targetRm, $newLevel, $statusJabatan,
                $effectiveGolongan, $effectiveJobGrade
            ) {
                $payload = [
                    'jabatan' => $targetJabatan,
                    'unit_kerja' => $targetUnitKerja,
                    'tanggal_dalam_jabatan' => $tmtAwal,
                    'tmt_unit_kerja' => $tmtAwal,
                    'tanggal_berakhir_sanksi' => $tanggalBerakhirSanksi,
                    'rm_level' => $targetRm,
                    'level' => $newLevel,
                    'status_penugasan' => $statusJabatan,
                    'golongan' => $effectiveGolongan,
                ];
                if ($effectiveJobGrade) {
                    $payload['job_grade'] = $effectiveJobGrade;
                }
                $employee->update($payload);
            });
        }

        // 3. Create new active job history
        $this->recordJobHistory(
            employee: $employee,
            jabatan: $targetJabatan,
            unitKerja: $targetUnitKerja,
            tmtAwal: $tmtAwal,
            jenisMutasi: $jenisMutasi,
            nomorSk: $nomorSk,
            tanggalSk: $tanggalSk,
            catatan: $catatan ?? "Mutasi ke {$targetJabatan} di {$targetUnitKerja}",
            statusJabatan: $statusJabatan,
            tmtAkhirEstimasi: $tmtAkhirEstimasi,
            fileSk: $fileSk,
            createdBy: $createdBy,
            isDispensasi: $isDispensasi,
            alasanDispensasi: $alasanDispensasi,
            level: $newLevel,
            golongan: $effectiveGolongan
        );
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
        ?string $tanggalBerakhirSanksi = null,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null,
        ?string $fileSk = null,
        ?int $createdBy = null,
        bool $isDispensasi = false,
        ?string $alasanDispensasi = null,
        ?string $newGolongan = null,
        ?string $newJobGrade = null
    ): void {
        // Snapshot both positions BEFORE touching anything
        $posA = [
            'jabatan' => $empA->jabatan,
            'unit_kerja' => $empA->unit_kerja,
            'level' => $empA->level,
            'rm_level' => $empA->rm_level,
            'golongan' => $empA->golongan,
            'job_grade' => $empA->job_grade,
        ];
        $posB = [
            'jabatan' => $empB->jabatan,
            'unit_kerja' => $empB->unit_kerja,
            'level' => $empB->level,
            'rm_level' => $empB->rm_level,
            'golongan' => $empB->golongan,
            'job_grade' => $empB->job_grade,
        ];

        $isDefinitif = $statusJabatan === 'Definitif';
        $effectiveGolonganA = $newGolongan ?: $empA->golongan;
        $effectiveJobGradeA = $newJobGrade ?: $empA->job_grade;

        // 1. Close active job histories (if Definitif, close empA's definitive job)
        if ($isDefinitif) {
            $empA->currentJob?->update(['tmt_akhir' => $tmtAkhir]);
        }
        $empB->currentJob?->update(['tmt_akhir' => $tmtAkhir]);

        // 2. Swap employee profiles atomically (bypass observer)
        Employee::withoutEvents(function () use (
            $empA, $empB, $posA, $posB, $tmtAwal, $tanggalBerakhirSanksi, $isDefinitif,
            $effectiveGolonganA, $effectiveJobGradeA
        ) {
            if ($isDefinitif) {
                $payloadA = [
                    'jabatan' => $posB['jabatan'],
                    'unit_kerja' => $posB['unit_kerja'],
                    'tanggal_dalam_jabatan' => $tmtAwal,
                    'tmt_unit_kerja' => $tmtAwal,
                    'tanggal_berakhir_sanksi' => $tanggalBerakhirSanksi,
                    'rm_level' => $posB['rm_level'] ?? $empA->rm_level,
                    'level' => $posB['level'] ?? $empA->level,
                    'golongan' => $effectiveGolonganA,
                ];
                if ($effectiveJobGradeA) {
                    $payloadA['job_grade'] = $effectiveJobGradeA;
                }
                $empA->update($payloadA);
            }
            $empB->update([
                'jabatan' => $posA['jabatan'],
                'unit_kerja' => $posA['unit_kerja'],
                'tanggal_dalam_jabatan' => $tmtAwal,
                'tmt_unit_kerja' => $tmtAwal,
                'rm_level' => $posA['rm_level'] ?? $empB->rm_level,
                'level' => $posA['level'] ?? $empB->level,
            ]);
        });

        // 3. Create two new active job history rows (cross-swapped)
        $baseNote = $catatan ?? 'Tukar jabatan / rotasi bersilang';
        $this->recordJobHistory(
            employee: $empA,
            jabatan: $posB['jabatan'],
            unitKerja: $posB['unit_kerja'],
            tmtAwal: $tmtAwal,
            jenisMutasi: $jenisMutasi,
            nomorSk: $nomorSk,
            tanggalSk: $tanggalSk,
            catatan: "{$baseNote} — bertukar dengan {$empB->nama}",
            statusJabatan: $statusJabatan,
            tmtAkhirEstimasi: $tmtAkhirEstimasi,
            fileSk: $fileSk,
            createdBy: $createdBy,
            isDispensasi: $isDispensasi,
            alasanDispensasi: $alasanDispensasi,
            level: $posB['level'] ?? $empA->level,
            golongan: $effectiveGolonganA
        );
        $this->recordJobHistory(
            employee: $empB,
            jabatan: $posA['jabatan'],
            unitKerja: $posA['unit_kerja'],
            tmtAwal: $tmtAwal,
            jenisMutasi: $jenisMutasi,
            nomorSk: $nomorSk,
            tanggalSk: $tanggalSk,
            catatan: "{$baseNote} — bertukar dengan {$empA->nama}",
            statusJabatan: $statusJabatan,
            tmtAkhirEstimasi: $tmtAkhirEstimasi,
            fileSk: $fileSk,
            createdBy: $createdBy,
            isDispensasi: false,
            alasanDispensasi: null,
            level: $posA['level'] ?? $empB->level,
            golongan: $posA['golongan'] ?? $empB->golongan
        );
    }

    /**
     * Record a new job history entry.
     */
    private function recordJobHistory(
        Employee $employee,
        string $jabatan,
        string $unitKerja,
        string $tmtAwal,
        string $jenisMutasi,
        ?string $nomorSk,
        ?string $tanggalSk,
        string $catatan,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null,
        ?string $fileSk = null,
        ?int $createdBy = null,
        bool $isDispensasi = false,
        ?string $alasanDispensasi = null,
        ?string $level = null,
        ?string $golongan = null
    ): JobHistory {
        return JobHistory::create([
            'employee_nik' => $employee->nik,
            'created_by' => $createdBy,
            'jabatan' => $jabatan,
            'unit_kerja' => $unitKerja,
            'level' => $level ?? $employee->level,
            'golongan' => $golongan ?? $employee->golongan,
            'tmt_awal' => $tmtAwal,
            'tmt_akhir' => null,
            'tmt_akhir_estimasi' => $tmtAkhirEstimasi,
            'status_penugasan' => $statusJabatan,
            'jenis_mutasi' => $jenisMutasi,
            'nomor_sk' => $nomorSk,
            'tanggal_sk' => $tanggalSk,
            'file_sk' => $fileSk,
            'catatan' => $catatan,
            'is_dispensasi' => $isDispensasi,
            'alasan_dispensasi' => $alasanDispensasi,
        ]);
    }

    /**
     * Parse numeric rank from an RM string (e.g. 'RM-3' -> 3).
     */
    private function parseRmNumber(?string $rmLevel): ?int
    {
        if (! $rmLevel) {
            return null;
        }

        return (int) str_replace('RM-', '', $rmLevel);
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
        return JobHistory::with(['employee', 'creator'])
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
        $histories = DB::table('job_histories')
            ->select('jabatan')->distinct()
            ->orderBy('jabatan')->pluck('jabatan')->toArray();
        $master = Position::pluck('nama')->toArray();

        return collect(array_unique(array_merge($histories, $master)))->sort()->values();
    }

    /**
     * All distinct unit_kerja values known in the system (for dropdowns).
     */
    public function getKnownUnits(): Collection
    {
        $histories = DB::table('job_histories')
            ->select('unit_kerja')->distinct()
            ->orderBy('unit_kerja')->pluck('unit_kerja')->toArray();

        $master = array_unique(array_merge(...\array_values(Employee::GROUPED_UNITS)));

        return collect(array_unique(array_merge($histories, $master)))->sort()->values();
    }
}
