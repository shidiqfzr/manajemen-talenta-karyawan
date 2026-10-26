<?php

namespace App\Livewire\Admin;

use App\Exceptions\MutasiIntegrityException;
use App\Exceptions\MutasiRuleViolationException;
use App\Livewire\Forms\MutasiForm;
use App\Models\JobHistory;
use App\Services\Data\MutasiRequest;
use App\Services\Mutasi\TalentProfileBuilder;
use App\Services\MutasiService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class MutasiProcess extends Component
{
    use WithFileUploads;

    public MutasiForm $form;

    // ── Review Summary Modal (DESIGN.md) ──────────────────────────────────
    public bool $showSummaryModal = false;

    // ── Analysis Cerdas ───────────────────────────────────────────────────
    public ?array $tenureAnalysis = null;

    public ?array $promotionAnalysis = null;

    public ?array $occupantTenureAnalysis = null;

    // ── Employee (source details) ──────────────────────────────────────────
    public ?array $employeeDetails = null;

    // ── Live impact analysis ───────────────────────────────────────────────
    /** null = not yet analysed, array = analysis result */
    public ?array $targetAnalysis = null;

    public bool $isSwapAllowed = true;

    public ?string $swapIncompatibilityMessage = null;

    // ── Talent Intelligence Slide-Over Drawer ───────────────────────────────
    public ?string $selectedTalentNik = null;

    public ?array $selectedTalentData = null;

    public bool $showTalentDrawer = false;

    public function mount(?string $jabatan = null, ?string $unit = null, ?string $nik = null): void
    {
        $this->form->initDefaults();

        if ($nik) {
            $this->form->employeeNik = $nik;
            $this->updatedEmployeeNik($nik); // Trigger the employee info load
        }

        // Deep-link pre-fill from vacancy monitoring page
        if ($jabatan) {
            $this->form->targetJabatan = $jabatan;
        }
        if ($unit) {
            $this->form->targetUnitKerja = $unit;
        }

        if ($jabatan && $unit) {
            $this->runAnalysis();
        }
    }

    // ── Livewire lifecycle hooks ───────────────────────────────────────────

    public function updated($property, $value = null): void
    {
        $propName = str_starts_with((string) $property, 'form.')
            ? substr((string) $property, 5)
            : (string) $property;

        $hookMethod = 'updated'.Str::studly($propName);

        if (method_exists($this, $hookMethod)) {
            $this->$hookMethod($value);
        }
    }

    public function updatedJenisMutasi(): void
    {
        if (in_array($this->form->jenisMutasi, ['SANKSI_INDISIPLINER', 'PENUGASAN_KHUSUS'])) {
            $this->form->statusJabatan = 'Definitif';
            $this->form->tmtAkhirEstimasi = '';
        }

        if ($this->form->jenisMutasi === 'ROTASI' && $this->employeeDetails) {
            $this->form->targetUnitKerja = $this->employeeDetails['unit_kerja'];
            $this->form->targetJabatan = '';
            $this->targetAnalysis = null;
            $this->promotionAnalysis = null;
            $this->runTenureAnalysis();
        } elseif ($this->form->jenisMutasi === 'MUTASI_UNIT' && $this->employeeDetails && $this->form->targetUnitKerja === $this->employeeDetails['unit_kerja']) {
            $this->resetTarget(resetUnit: true);
        } else {
            $this->resetTarget(resetUnit: true);
        }
    }

    public function updatedEmployeeNik(?string $value = null): void
    {
        $nik = $value ?? $this->form->employeeNik;
        $this->employeeDetails = $this->resolveEmployee($nik);
        if ($this->employeeDetails) {
            $this->form->newGolongan = $this->employeeDetails['golongan'] ?? '';
            $this->form->newJobGrade = (string) ($this->employeeDetails['job_grade'] ?? '');

            // Contextual default jenisMutasi: if RM-1, PROMOSI is impossible -> fallback to MUTASI_UNIT
            if (($this->employeeDetails['rm_level'] ?? '') === 'RM-1' && $this->form->jenisMutasi === 'PROMOSI') {
                $this->form->jenisMutasi = 'MUTASI_UNIT';
            }

            if ($this->form->jenisMutasi === 'ROTASI') {
                $this->form->targetUnitKerja = $this->employeeDetails['unit_kerja'];
            }
        }
        $this->resetTarget(resetUnit: $this->form->jenisMutasi !== 'ROTASI');
        $this->runTenureAnalysis();
    }

    public function updatedSourceUnitFilter(): void
    {
        $this->form->resetEmployee();
        $this->employeeDetails = null;
        $this->resetTarget();
    }

    public function updatedTargetJabatan(): void
    {
        $this->form->swapConfirmed = false;
        $this->runAnalysis();
    }

    public function updatedTargetUnitKerja(): void
    {
        $this->resetTarget(resetUnit: false);
        $this->runTenureAnalysis();
    }

    private function resetTarget(bool $resetUnit = true): void
    {
        $this->form->resetTarget($resetUnit);
        if ($resetUnit) {
            $this->tenureAnalysis = null;
        }
        $this->targetAnalysis = null;
        $this->promotionAnalysis = null;
        $this->occupantTenureAnalysis = null;
        $this->isSwapAllowed = true;
        $this->swapIncompatibilityMessage = null;
        $this->showSummaryModal = false;
    }

    // ── Modal & Submit Workflow ────────────────────────────────────────────

    /**
     * Validate the mutasi form. Returns true if valid, false if invalid.
     */
    public function validateFormData(): bool
    {
        // Re-derive analysis state server-side; public component properties are client-mutable
        $this->employeeDetails = $this->form->employeeNik ? $this->resolveEmployee($this->form->employeeNik) : null;
        $this->runAnalysis();
        $this->runTenureAnalysis();

        try {
            return $this->form->validateForm(
                tenureAnalysis: $this->tenureAnalysis,
                promotionAnalysis: $this->promotionAnalysis,
                targetAnalysis: $this->targetAnalysis,
                employeeDetails: $this->employeeDetails,
                isSwapAllowed: $this->isSwapAllowed,
                swapIncompatibilityMessage: $this->swapIncompatibilityMessage
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $cleanField = str_starts_with((string) $field, 'form.') ? substr((string) $field, 5) : (string) $field;
                foreach ((array) $messages as $msg) {
                    $this->addError($cleanField, $msg);
                    $this->addError('form.'.$cleanField, $msg);
                }
            }

            throw $e;
        }
    }

    /**
     * Why this placement cannot proceed as a single SK, or null when it can.
     * Drives the disabled state of the SK section and primary action; the real guard stays in MutasiForm::validateForm().
     */
    #[Computed]
    public function blockingReason(): ?string
    {
        return match (true) {
            (bool) ($this->employeeDetails['is_retired'] ?? false) => 'Karyawan telah melewati batas usia pensiun dan tidak dapat dimutasi.',
            ($this->targetAnalysis['type'] ?? null) === 'same' => 'Karyawan sudah menjabat pada formasi dan unit kerja yang sama.',
            ($this->targetAnalysis['type'] ?? null) === 'reserved',
            (bool) ($this->targetAnalysis['is_reserved'] ?? false) => 'Formasi telah dipesan oleh mutasi terjadwal. Pilih formasi lain.',
            ($this->targetAnalysis['type'] ?? null) === 'occupied' && ! $this->isSwapAllowed => 'Formasi masih terisi pejabat definitif. Selesaikan melalui Mutasi Berantai.',
            default => null,
        };
    }

    public function openSummaryModal(): void
    {
        if ($this->validateFormData()) {
            $this->showSummaryModal = true;
        }
    }

    public function closeSummaryModal(): void
    {
        $this->showSummaryModal = false;
    }

    /**
     * Open Slide-Over Talent Intelligence Drawer for a specific participant.
     */
    public function openTalentDrawer(string $nik, MutasiService $service): void
    {
        $this->selectedTalentNik = $nik;
        $this->selectedTalentData = $service->getTalentProfile($nik);
        $this->showTalentDrawer = true;
    }

    public function closeTalentDrawer(): void
    {
        $this->showTalentDrawer = false;
        $this->selectedTalentNik = null;
        $this->selectedTalentData = null;
    }

    public function submitMutasi(MutasiService $service): void
    {
        if (! $this->validateFormData()) {
            return;
        }

        $this->confirmSubmitMutasi($service);
    }

    public function confirmSubmitMutasi(MutasiService $service): void
    {
        if (! $this->validateFormData()) {
            $this->showSummaryModal = false;

            return;
        }

        // Handle PDF SK upload to PRIVATE storage (SECURITY.md §4.4)
        $fileSkPath = null;
        if ($this->form->fileSk) {
            $fileSkPath = $this->form->fileSk->store('dokumen_sk', 'local');
        }

        // Consolidate dispensation data
        $isDispensasi = $this->form->dispensasiTenure || $this->form->dispensasiPromosi;
        $alasanList = [];
        if ($this->form->dispensasiTenure && $this->form->alasanDispensasiTenure) {
            $alasanList[] = "Masa Dinas: {$this->form->alasanDispensasiTenure}";
        }
        if ($this->form->dispensasiPromosi && $this->form->alasanDispensasiPromosi) {
            $alasanList[] = "Promosi: {$this->form->alasanDispensasiPromosi}";
        }
        $alasanDispensasi = ! empty($alasanList) ? implode(' | ', $alasanList) : null;

        $request = new MutasiRequest(
            employeeNik: $this->form->employeeNik,
            targetJabatan: $this->form->targetJabatan,
            targetUnitKerja: $this->form->targetUnitKerja,
            tmtAwal: $this->form->tmtAwal,
            jenisMutasi: $this->form->jenisMutasi,
            nomorSk: $this->form->nomorSk,
            tanggalSk: $this->form->tanggalSk,
            catatan: $this->form->catatan,
            tanggalBerakhirSanksi: $this->form->isTargetDiperban ? $this->form->tanggalBerakhirSanksi : null,
            statusJabatan: $this->form->statusJabatan,
            tmtAkhirEstimasi: in_array($this->form->statusJabatan, ['Pj', 'Plt']) ? $this->form->tmtAkhirEstimasi : null,
            fileSk: $fileSkPath,
            createdBy: auth()->id(),
            isDispensasi: $isDispensasi,
            alasanDispensasi: $alasanDispensasi,
            newGolongan: $this->form->newGolongan,
            newJobGrade: $this->form->newJobGrade,
        );

        try {
            $result = $service->processMutasi($request);
        } catch (MutasiRuleViolationException|MutasiIntegrityException $e) {
            $this->showSummaryModal = false;
            $this->addError('form.employeeNik', $e->getMessage());
            $this->addError('employeeNik', $e->getMessage());

            return;
        }

        $msg = match ($result['type']) {
            'swap' => "Sukses! {$result['employee']} dan {$result['displaced']} berhasil bertukar jabatan.",
            'transfer' => "Sukses! {$result['employee']} berhasil dimutasi ke {$result['to_jabatan']} di {$result['to_unit']}.",
            default => 'Mutasi berhasil diproses.',
        };

        if (Carbon::parse($this->form->tmtAwal)->isFuture()) {
            $msg .= ' (TMT Efektif mulai '.Carbon::parse($this->form->tmtAwal)->translatedFormat('d F Y').').';
        }

        session()->flash('mutasi_success', $msg);
        $this->redirect(route('admin.mutasi.index', ['tab' => 'history']), navigate: false);
    }

    // ── Render ─────────────────────────────────────────────────────────────

    public function render(MutasiService $service)
    {
        return view('livewire.admin.mutasi-process', [
            'employees' => $service->getSelectableEmployees($this->form->sourceUnitFilter ?: null),
            'jabatanList' => $this->getFilteredJabatanList($service),
            'unitList' => $service->getKnownUnits(),
            'groupedUnits' => $service->getGroupedUnits(),
            'mutasiTypes' => JobHistory::MUTASI_TYPES,
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function service(): MutasiService
    {
        return app(MutasiService::class);
    }

    private function getFilteredJabatanList(MutasiService $service)
    {
        if (! $this->form->employeeNik || ! $this->form->jenisMutasi || ! $this->form->targetUnitKerja) {
            return collect(); // Return empty if no employee or unit selected
        }

        return collect($service->listTargetPositions(
            $this->form->employeeNik,
            $this->form->targetUnitKerja,
            $this->form->jenisMutasi
        ));
    }

    private function resolveEmployee(string $nik): ?array
    {
        if (! $nik) {
            return null;
        }

        return $this->service()->getTalentProfile($nik);
    }

    private function runAnalysis(): void
    {
        if (! $this->form->targetJabatan || ! $this->form->targetUnitKerja) {
            $this->targetAnalysis = null;
            $this->promotionAnalysis = null;
            $this->occupantTenureAnalysis = null;
            $this->isSwapAllowed = true;
            $this->swapIncompatibilityMessage = null;

            return;
        }

        // Same-position guard
        if (
            $this->employeeDetails &&
            $this->employeeDetails['jabatan'] === $this->form->targetJabatan &&
            $this->employeeDetails['unit_kerja'] === $this->form->targetUnitKerja
        ) {
            $this->targetAnalysis = ['type' => 'same'];
            $this->promotionAnalysis = null;
            $this->occupantTenureAnalysis = null;
            $this->isSwapAllowed = true;
            $this->swapIncompatibilityMessage = null;

            return;
        }

        $service = $this->service();
        $analysis = $service->analyzeTarget($this->form->targetJabatan, $this->form->targetUnitKerja);

        $occupant = $analysis['occupant'];
        $occupantRm = $occupant ? $service->resolveEmployeeRm($occupant) : null;
        $this->isSwapAllowed = true;
        $this->swapIncompatibilityMessage = null;
        $this->occupantTenureAnalysis = null;

        if ($occupant) {
            $empARm = $this->employeeDetails['rm_level'] ?? null;
            $empBRm = $occupantRm;

            if ($empARm && $empBRm && $empARm !== $empBRm) {
                $this->isSwapAllowed = false;
                if ($this->form->jenisMutasi === 'PROMOSI') {
                    $this->swapIncompatibilityMessage = "Formasi target saat ini diduduki oleh {$occupant->nama} ({$empBRm}). Penempatan Promosi pada formasi yang masih terisi tidak dapat diproses melalui Mutasi Tunggal (Swap Bilateral) karena memerlukan pergeseran pejabat petahana.";
                } elseif ($this->form->jenisMutasi === 'DEMOSI') {
                    $this->swapIncompatibilityMessage = "Formasi target saat ini diduduki oleh {$occupant->nama} ({$empBRm}). Penempatan Demosi pada formasi yang masih terisi tidak dapat diproses melalui Mutasi Tunggal.";
                } else {
                    $this->swapIncompatibilityMessage = "Tukar jabatan (Swap) tidak diizinkan antar eselon yang berbeda. Karyawan asal berada pada tingkat {$empARm}, sedangkan pejabat saat ini berada pada tingkat {$empBRm}.";
                }
            }

            // Check Employee B's tenure in their current unit
            if ($this->employeeDetails) {
                $this->occupantTenureAnalysis = $service->checkTenureCompliance(
                    $occupant,
                    $this->employeeDetails['unit_kerja']
                );
            }
        }

        $occupantTenureText = null;
        if ($occupant?->tmt_unit_kerja) {
            $occupantTenureText = TalentProfileBuilder::formatMonths((int) Carbon::parse($occupant->tmt_unit_kerja)->diffInMonths(now()));
        }

        $this->targetAnalysis = [
            'type' => $analysis['type'],
            'is_reserved' => $analysis['is_reserved'] ?? false,
            'reserved_occupant' => ! empty($analysis['reserved_occupant']) ? [
                'nik' => $analysis['reserved_occupant']->nik,
                'nama' => $analysis['reserved_occupant']->nama,
                'tmt_awal' => $analysis['reserved_job']?->tmt_awal ? Carbon::parse($analysis['reserved_job']->tmt_awal)->format('d/m/Y') : null,
                'sk_nomor' => $analysis['reserved_job']?->nomor_sk,
            ] : null,
            'occupant' => $occupant ? [
                'nik' => $occupant->nik,
                'nama' => $occupant->nama,
                'jabatan' => $occupant->jabatan,
                'unit_kerja' => $occupant->unit_kerja,
                'golongan' => $occupant->golongan,
                'rm_level' => $occupantRm,
                'tmt_unit_kerja' => $occupant->tmt_unit_kerja ? Carbon::parse($occupant->tmt_unit_kerja)->format('d/m/Y') : null,
                'tenure_text' => $occupantTenureText,
            ] : null,
        ];

        $positionFlags = $service->getPositionFlags($this->form->targetJabatan);
        $this->form->isTargetDiperban = $positionFlags['is_diperban'];
        $this->form->isTargetOverflow = $positionFlags['is_overflow'];

        $this->runTenureAnalysis();
        $this->runPromotionAnalysis();
    }

    private function runTenureAnalysis(): void
    {
        if (! $this->form->employeeNik || ! $this->form->targetUnitKerja) {
            $this->tenureAnalysis = null;

            return;
        }

        $this->tenureAnalysis = $this->service()->tenureComplianceFor(
            $this->form->employeeNik,
            $this->form->targetUnitKerja
        );
    }

    private function runPromotionAnalysis(): void
    {
        if (! $this->form->employeeNik || ! $this->form->targetJabatan || $this->form->jenisMutasi !== 'PROMOSI') {
            $this->promotionAnalysis = null;

            return;
        }

        $this->promotionAnalysis = $this->service()->promotionEligibilityFor(
            $this->form->employeeNik,
            $this->form->targetJabatan
        );
    }
}
