<?php

namespace App\Services\Mutasi;

use App\Exceptions\MutasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Unit;
use App\Services\Data\MutasiRequest;
use App\Services\ManPowerPlanningService;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Governance rules of a mutation (DOMAIN.md §5.2 & §5.4): retirement age, unit tenure,
 * promotion path & eligibility. Thresholds live in MutasiPolicy.
 */
class MutasiGovernanceGuard
{
    public function __construct(
        private readonly FormationService $formations,
        private readonly ManPowerPlanningService $planning,
    ) {}

    /**
     * Check tenure compliance in the current unit kerja based on DOMAIN.md §5.2.1
     * (Pegawai minimal menjabat 2-3 tahun di unit kerja sebelum diperbolehkan rotasi unit).
     */
    public function checkTenureCompliance(Employee $emp, string $targetUnitKerja): array
    {
        $minMonths = MutasiPolicy::MIN_UNIT_TENURE_MONTHS;
        $minYears = MutasiPolicy::minUnitTenureYears();

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
                'message' => "Data TMT Unit Kerja belum tercatat. Standar minimal masa dinas adalah {$minYears} tahun ({$minMonths} bulan) sesuai Regulasi Masa Kerja/Tenure PTPN IV Regional V.",
            ];
        }

        $carbonTmt = Carbon::parse($tmtDate);
        $months = (int) $carbonTmt->diffInMonths(now());
        $years = round($months / 12, 1);
        $isCompliant = $months >= $minMonths;

        return [
            'is_applicable' => true,
            'is_compliant' => $isCompliant,
            'months' => $months,
            'years' => $years,
            'tmt_unit_kerja' => $carbonTmt->toDateString(),
            'message' => $isCompliant
                ? 'Masa dinas di unit saat ini ('.TalentProfileBuilder::formatMonths($months).") telah memenuhi standar minimal {$minYears} tahun."
                : 'Pegawai baru bertugas '.TalentProfileBuilder::formatMonths($months)." di {$emp->unit_kerja} (standar minimal 2-3 tahun sesuai Regulasi Masa Kerja/Tenure PTPN IV Regional V). Memerlukan Dispensasi Khusus Direksi.",
        ];
    }

    /**
     * Check employee promotion readiness and eligibility based on DOMAIN.md §5.2.3 and §6.
     */
    public function checkPromotionEligibility(Employee $emp, string $targetJabatan): array
    {
        $currentRm = $this->planning->determineRmLevel($emp);
        $currentRmNum = FormationService::rmNumber($currentRm);

        $targetRm = $this->formations->resolvePositionRm($targetJabatan);
        $targetRmNum = FormationService::rmNumber($targetRm);

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
            $hasMinGol = in_array($gol, MutasiPolicy::RM3_TO_RM2_GOLONGAN, true);
            $hasMinTenure = $jobTenureYears >= MutasiPolicy::RM3_TO_RM2_MIN_TENURE_YEARS;
            $golOrTenureMet = $hasMinGol || $hasMinTenure;

            $criteria[] = [
                'label' => 'Golongan Minimal IIIC atau Masa Dinas Asisten ≥ 4 Tahun',
                'met' => $golOrTenureMet,
                'value' => "Gol: {$emp->golongan} | Masa Dinas: ".TalentProfileBuilder::formatMonths($jobTenureMonths),
                'requirement' => 'Min. Gol IIIC atau Masa Dinas ≥ 4 tahun',
            ];

            // Kinerja check
            $hasGoodEval = false;
            $evalText = 'Belum ada data evaluasi SMKBK / 9-Box';
            if ($latestEvaluation) {
                $score = $latestEvaluation->skor_smkbk_9box ?? 0;
                $box = $latestEvaluation->kategori_9box ?? '';
                $hasGoodEval = $score >= MutasiPolicy::GOOD_EVALUATION_MIN_SCORE
                    || Str::contains($box, MutasiPolicy::GOOD_EVALUATION_BOXES);
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
            // RM-2 -> RM-1: Syarat minimal Golongan IIID/IVA dan menjabat Askep/Kasubag min 3 tahun
            $hasMinGol = in_array($gol, MutasiPolicy::RM2_TO_RM1_GOLONGAN, true);
            $criteria[] = [
                'label' => 'Standar Golongan Minimal IIID / IVA',
                'met' => $hasMinGol,
                'value' => "Gol: {$emp->golongan}",
                'requirement' => 'Golongan IIID s.d. IV',
            ];

            $hasAskepTenure = $jobTenureYears >= MutasiPolicy::RM2_TO_RM1_MIN_TENURE_YEARS;
            $criteria[] = [
                'label' => 'Masa Dinas Jabatan Madya (Askep/Kasubag) ≥ 3 Tahun',
                'met' => $hasAskepTenure,
                'value' => TalentProfileBuilder::formatMonths($jobTenureMonths),
                'requirement' => 'Minimal 3 tahun menjabat',
            ];

            $isEligible = $hasMinGol && $hasAskepTenure;
        } elseif ($currentRmNum >= 4 && $targetRmNum <= 3) {
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

    public function isRetired(Employee $emp): bool
    {
        return $emp->tanggal_pensiun && Carbon::parse($emp->tanggal_pensiun)->lt(now()->startOfMonth());
    }

    /**
     * Server-side governance guard for a single mutation (DOMAIN.md §5.2 & §5.4).
     * Mirrors the UI validation so rules cannot be bypassed by tampered component state.
     *
     * @throws MutasiRuleViolationException
     */
    public function assertAllowed(Employee $employee, MutasiRequest $request): void
    {
        [$targetJabatan, $targetUnitKerja, $jenisMutasi, $statusJabatan, $hasDispensasi] = [
            $request->targetJabatan, $request->targetUnitKerja, $request->jenisMutasi,
            $request->statusJabatan, $request->hasDispensasi(),
        ];

        if (! in_array($jenisMutasi, JobHistory::MUTASI_TYPES, true)) {
            throw new MutasiRuleViolationException("Jenis mutasi '{$jenisMutasi}' tidak dikenal dalam Pedoman Tata Kelola SDM.");
        }

        if (! in_array($statusJabatan, JobHistory::STATUS_JABATAN, true)) {
            throw new MutasiRuleViolationException("Status jabatan '{$statusJabatan}' tidak valid.");
        }

        if ($this->isRetired($employee)) {
            throw new MutasiRuleViolationException(
                "Pegawai {$employee->nama} telah memasuki Batas Usia Pensiun dan tidak dapat dimutasi."
            );
        }

        if ($jenisMutasi === 'ROTASI' && $targetUnitKerja !== $employee->unit_kerja) {
            throw new MutasiRuleViolationException("Rotasi & Alih Tugas hanya berlaku di dalam unit kerja yang sama ({$employee->unit_kerja}).");
        }

        if ($jenisMutasi === 'MUTASI_UNIT' && $targetUnitKerja === $employee->unit_kerja) {
            throw new MutasiRuleViolationException('Mutasi Unit wajib menuju unit kerja yang berbeda (gunakan jenis Rotasi jika tetap di unit yang sama).');
        }

        // Definitive placement must match the direction of the RM change (prevents back-door promotions).
        if ($statusJabatan === 'Definitif' && ! $this->formations->isPoolPosition($targetJabatan)) {
            $currentRmNum = FormationService::rmNumber($this->planning->determineRmLevel($employee));
            $targetRmNum = FormationService::rmNumber($this->formations->resolvePositionRm($targetJabatan));

            if ($currentRmNum !== null && $targetRmNum !== null) {
                if ($targetRmNum < $currentRmNum && $jenisMutasi !== 'PROMOSI') {
                    throw new MutasiRuleViolationException('Penempatan definitif ke tingkat eselon yang lebih tinggi wajib diproses sebagai Promosi.');
                }
                if ($targetRmNum > $currentRmNum && $jenisMutasi !== 'DEMOSI') {
                    throw new MutasiRuleViolationException('Penempatan definitif ke tingkat eselon yang lebih rendah wajib diproses sebagai Demosi.');
                }
            }
        }

        $tenure = $this->checkTenureCompliance($employee, $targetUnitKerja);
        if ($tenure['is_applicable'] && ! $tenure['is_compliant'] && ! $hasDispensasi) {
            throw new MutasiRuleViolationException(
                "Pegawai {$employee->nama} belum memenuhi masa dinas minimal 2 tahun di {$employee->unit_kerja}. Wajib melampirkan persetujuan Dispensasi Khusus Direksi beserta alasannya."
            );
        }

        if ($jenisMutasi === 'PROMOSI') {
            $promo = $this->checkPromotionEligibility($employee, $targetJabatan);
            if ($promo['is_applicable'] && ! $promo['is_eligible'] && ! $hasDispensasi) {
                throw new MutasiRuleViolationException(
                    "Pegawai {$employee->nama} belum memenuhi kriteria formal promosi ke {$targetJabatan}. Wajib melampirkan persetujuan Dispensasi Promosi beserta alasannya."
                );
            }
        }
    }
}
