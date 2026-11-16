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
     * (Karyawan minimal menjabat 2-3 tahun di unit kerja sebelum diperbolehkan rotasi unit).
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
                'required_months' => $minMonths,
                'required_label' => "{$minYears} tahun",
                'shortfall_months' => 0,
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
                'required_months' => $minMonths,
                'required_label' => "{$minYears} tahun",
                'shortfall_months' => $minMonths,
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
            'required_months' => $minMonths,
            'required_label' => "{$minYears} tahun",
            'shortfall_months' => max(0, $minMonths - $months),
            'tmt_unit_kerja' => $carbonTmt->toDateString(),
            'message' => $isCompliant
                ? 'Masa dinas di unit saat ini ('.TalentProfileBuilder::formatMonths($months).") telah memenuhi standar minimal {$minYears} tahun."
                : 'Karyawan baru bertugas '.TalentProfileBuilder::formatMonths($months)." di {$emp->unit_kerja} (standar minimal 2-3 tahun sesuai Regulasi Masa Kerja/Tenure PTPN IV Regional V). Memerlukan Dispensasi Khusus Direksi.",
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
            // A missing assessment is a data gap that can be closed, not a failed standard
            $evalIsDataGap = $latestEvaluation === null;
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
                'is_data_gap' => $evalIsDataGap,
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

    /**
     * Sudah berhenti, atau tanggal pensiun efektifnya (tanggal_pensiun, atau tanggal lahir + BUP) sudah tiba. Aturan yang
     * sama dengan PensiunService::dueEmployees(), sehingga karyawan yang jatuh tempo tidak lolos selama pemberhentian
     * otomatis belum berjalan.
     */
    public function isRetired(Employee $emp): bool
    {
        return $emp->isSeparated() || $emp->isPensionReached();
    }

    /**
     * Klausa alasan karyawan tidak dapat dimutasi, untuk disisipkan pada pesan ("Karyawan X {klausa} dan tidak dapat
     * dimutasi."). Karyawan yang berhenti bukan karena pensiun tidak boleh disebut telah mencapai BUP.
     */
    public function retiredReason(Employee $emp): string
    {
        if ($emp->isSeparated() && $emp->alasan_berhenti !== null && $emp->alasan_berhenti !== Employee::ALASAN_PENSIUN) {
            return "telah berhenti ({$emp->alasan_berhenti})";
        }

        return 'telah memasuki Batas Usia Pensiun';
    }

    /**
     * Per-participant placement rules shared by a single mutation and every node of a chain (DOMAIN.md §5.2.3):
     * valid type and status, ROTASI/MUTASI_UNIT consistent with the unit, and the Promosi/Demosi direction of a
     * definitive RM change. Returns the first violation as a user-facing message, or null when the placement is allowed.
     */
    public function placementRuleViolation(
        Employee $employee,
        string $jenisMutasi,
        string $statusJabatan,
        string $targetJabatan,
        string $targetUnitKerja
    ): ?string {
        if (! in_array($jenisMutasi, JobHistory::MUTASI_TYPES, true)) {
            return "Jenis mutasi '{$jenisMutasi}' untuk karyawan {$employee->nama} tidak dikenal dalam Pedoman Tata Kelola SDM.";
        }

        if (! in_array($statusJabatan, JobHistory::STATUS_JABATAN, true)) {
            return "Status jabatan '{$statusJabatan}' untuk karyawan {$employee->nama} tidak valid.";
        }

        if ($jenisMutasi === 'ROTASI' && $targetUnitKerja !== $employee->unit_kerja) {
            return "Rotasi & Alih Tugas hanya berlaku di dalam unit kerja yang sama ({$employee->unit_kerja}).";
        }

        if ($jenisMutasi === 'MUTASI_UNIT' && $targetUnitKerja === $employee->unit_kerja) {
            return 'Mutasi Unit wajib menuju unit kerja yang berbeda (gunakan jenis Rotasi jika tetap di unit yang sama).';
        }

        // Definitive placement must match the direction of the RM change (prevents back-door promotions).
        if ($statusJabatan === 'Definitif' && ! $this->formations->isPoolPosition($targetJabatan)) {
            $currentRmNum = FormationService::rmNumber($this->planning->determineRmLevel($employee));
            $targetRmNum = FormationService::rmNumber($this->formations->resolvePositionRm($targetJabatan));

            if ($currentRmNum !== null && $targetRmNum !== null) {
                if ($targetRmNum < $currentRmNum && $jenisMutasi !== 'PROMOSI') {
                    return "Penempatan definitif {$employee->nama} ke tingkat eselon yang lebih tinggi wajib diproses sebagai Promosi.";
                }
                if ($targetRmNum > $currentRmNum && $jenisMutasi !== 'DEMOSI') {
                    return "Penempatan definitif {$employee->nama} ke tingkat eselon yang lebih rendah wajib diproses sebagai Demosi.";
                }
            }
        }

        return null;
    }

    /**
     * Server-side governance guard for a single mutation (DOMAIN.md §5.2 & §5.4).
     * Mirrors the UI validation so rules cannot be bypassed by tampered component state.
     *
     * @throws MutasiRuleViolationException
     */
    public function assertAllowed(Employee $employee, MutasiRequest $request): void
    {
        [$targetUnitKerja, $hasDispensasi] = [$request->targetUnitKerja, $request->hasDispensasi()];

        if ($this->isRetired($employee)) {
            throw new MutasiRuleViolationException(
                "Karyawan {$employee->nama} {$this->retiredReason($employee)} dan tidak dapat dimutasi."
            );
        }

        $violation = $this->placementRuleViolation(
            $employee, $request->jenisMutasi, $request->statusJabatan, $request->targetJabatan, $request->targetUnitKerja
        );
        if ($violation !== null) {
            throw new MutasiRuleViolationException($violation);
        }

        $tenure = $this->checkTenureCompliance($employee, $targetUnitKerja);
        if ($tenure['is_applicable'] && ! $tenure['is_compliant'] && ! $hasDispensasi) {
            throw new MutasiRuleViolationException(
                "Karyawan {$employee->nama} belum memenuhi masa dinas minimal 2 tahun di {$employee->unit_kerja}. Wajib melampirkan persetujuan Dispensasi Khusus Direksi beserta alasannya."
            );
        }

        $this->assertPromotionDispensed($employee, $request);
    }

    /**
     * An unmet promotion qualification needs Direksi approval with a written basis, same as the tenure rule.
     *
     * @throws MutasiRuleViolationException
     */
    public function assertPromotionDispensed(Employee $employee, MutasiRequest $request): void
    {
        $promo = $this->checkPromotionEligibility($employee, $request->targetJabatan);

        if (! $promo['is_applicable'] || $promo['is_eligible']) {
            return;
        }

        if (! $request->dispensasiPromosi || mb_strlen(trim((string) $request->alasanDispensasiPromosi)) < 5) {
            throw new MutasiRuleViolationException(
                "Karyawan {$employee->nama} belum memenuhi kualifikasi promosi {$promo['current_rm']} → {$promo['target_rm']}. Wajib melampirkan persetujuan Dispensasi Promosi Direksi beserta dasar persetujuannya."
            );
        }
    }

    /**
     * Audit snapshot of an unmet promotion qualification, or null when it does not apply / is met.
     * Recorded on the job history together with the Direksi approval that allowed it to proceed.
     */
    public function promotionAdvisory(Employee $emp, string $targetJabatan, bool $dispensed = false, ?string $basis = null): ?string
    {
        $promo = $this->checkPromotionEligibility($emp, $targetJabatan);

        if (! $promo['is_applicable'] || $promo['is_eligible']) {
            return null;
        }

        $unmet = collect($promo['criteria'])
            ->reject(fn (array $c) => ! empty($c['met']))
            ->map(fn (array $c) => "{$c['label']} ({$c['value']})")
            ->implode('; ');

        $note = "Kualifikasi promosi {$promo['current_rm']} → {$promo['target_rm']} belum terpenuhi: {$unmet}.";

        // Optional record that Direksi approved it; kept out of is_dispensasi so it can never waive the tenure rule
        if ($dispensed) {
            $note .= ' Dispensasi promosi disetujui'.(filled($basis) ? ": {$basis}" : '').'.';
        }

        return Str::limit($note, 500, '…');
    }
}
