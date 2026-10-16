<?php

namespace App\Livewire\Admin;

use App\Livewire\Forms\MutasiForm;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\ManPowerPlanningService;
use App\Services\MutasiService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /**
     * Magic property delegation to MutasiForm for clean access & backwards compatibility.
     */
    public function __get($name)
    {
        if (isset($this->form) && property_exists($this->form, $name)) {
            return $this->form->$name;
        }

        return null;
    }

    public function __set($name, $value)
    {
        if (isset($this->form) && property_exists($this->form, $name)) {
            $this->form->$name = $value;
            $this->updated($name, $value);

            return;
        }

        $this->$name = $value;
    }

    public function __isset($name)
    {
        return isset($this->form) && isset($this->form->$name);
    }

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
                }
            }

            throw $e;
        }
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

        try {
            $result = $service->processMutasi(
                employeeNik: $this->form->employeeNik,
                targetJabatan: $this->form->targetJabatan,
                targetUnitKerja: $this->form->targetUnitKerja,
                tmtAwal: $this->form->tmtAwal,
                jenisMutasi: $this->form->jenisMutasi,
                nomorSk: $this->form->nomorSk ?: null,
                tanggalSk: $this->form->tanggalSk ?: null,
                catatan: $this->form->catatan ?: null,
                tanggalBerakhirSanksi: $this->form->isTargetDiperban ? $this->form->tanggalBerakhirSanksi : null,
                statusJabatan: $this->form->statusJabatan,
                tmtAkhirEstimasi: in_array($this->form->statusJabatan, ['Pj', 'Plt']) && ! empty($this->form->tmtAkhirEstimasi) ? $this->form->tmtAkhirEstimasi : null,
                fileSk: $fileSkPath,
                createdBy: auth()->id(),
                isDispensasi: $isDispensasi,
                alasanDispensasi: $alasanDispensasi,
                newGolongan: ! empty($this->form->newGolongan) ? $this->form->newGolongan : null,
                newJobGrade: ! empty($this->form->newJobGrade) ? $this->form->newJobGrade : null
            );
        } catch (\InvalidArgumentException|\RuntimeException $e) {
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
        $empQuery = Employee::query()
            ->where(function ($q) {
                $q->whereNull('tanggal_pensiun')
                    ->orWhere('tanggal_pensiun', '>=', now()->startOfMonth());
            })
            ->orderBy('nama');

        if (! empty($this->form->sourceUnitFilter)) {
            $empQuery->where('unit_kerja', $this->form->sourceUnitFilter);
        }
        $employees = $empQuery->get(['nik', 'nama', 'jabatan', 'unit_kerja']);

        $jabatanList = $this->getFilteredJabatanList($service);
        $unitList = $service->getKnownUnits();
        $mutasiTypes = JobHistory::MUTASI_TYPES;

        $activeUnits = Unit::where('is_active', true)->orderBy('urutan')->orderBy('nama')->get();
        if ($activeUnits->isNotEmpty()) {
            $groupedUnits = $activeUnits->groupBy('wilayah')
                ->map(fn ($units) => $units->pluck('nama')->toArray())
                ->toArray();
        } else {
            $groupedUnits = Employee::GROUPED_UNITS;
        }

        return view('livewire.admin.mutasi-process', array_merge(
            $this->form->all(),
            compact('employees', 'jabatanList', 'unitList', 'groupedUnits', 'mutasiTypes')
        ));
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function getFilteredJabatanList(MutasiService $service)
    {
        if (! $this->form->employeeNik || ! $this->form->jenisMutasi || ! $this->form->targetUnitKerja) {
            return collect(); // Return empty if no employee or unit selected
        }

        $emp = Employee::where('nik', $this->form->employeeNik)->first();
        if (! $emp) {
            return collect();
        }

        $positionsArray = app(MutasiService::class)
            ->getAvailableTargetPositions($emp, $this->form->targetUnitKerja, $this->form->jenisMutasi);

        return collect($positionsArray);
    }

    private function resolveEmployee(string $nik): ?array
    {
        if (! $nik) {
            return null;
        }

        $emp = Employee::with('currentJob')->where('nik', $nik)->first();
        if (! $emp) {
            return null;
        }

        // Get RM Level
        $rmLevel = app(ManPowerPlanningService::class)->determineRmLevel($emp);

        return [
            'nik' => $emp->nik,
            'nama' => $emp->nama,
            'jabatan' => $emp->currentJob?->jabatan ?? $emp->jabatan,
            'unit_kerja' => $emp->currentJob?->unit_kerja ?? $emp->unit_kerja,
            'level' => $emp->currentJob?->level ?? $emp->level,
            'golongan' => $emp->golongan,
            'job_grade' => $emp->job_grade,
            'person_grade' => $emp->person_grade,
            'has_job' => (bool) $emp->currentJob,
            'rm_level' => $rmLevel,
            'tmt_unit_kerja' => $emp->tmt_unit_kerja?->toDateString(),
            'tanggal_dalam_jabatan' => $emp->tanggal_dalam_jabatan?->toDateString(),
        ];
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

        $analysis = app(MutasiService::class)->analyzeTarget(
            $this->form->targetJabatan,
            $this->form->targetUnitKerja
        );

        $occupant = $analysis['occupant'];
        $this->isSwapAllowed = true;
        $this->swapIncompatibilityMessage = null;
        $this->occupantTenureAnalysis = null;

        if ($occupant) {
            $empARm = $this->employeeDetails['rm_level'] ?? null;
            $empBRm = $occupant->rm_level ?? app(ManPowerPlanningService::class)->determineRmLevel($occupant);

            if ($empARm && $empBRm && $empARm !== $empBRm) {
                $this->isSwapAllowed = false;
                $this->swapIncompatibilityMessage = "Tukar jabatan (Swap) tidak diizinkan antar eselon yang berbeda. Karyawan asal berada pada tingkat {$empARm}, sedangkan pejabat saat ini berada pada tingkat {$empBRm}. Sesuai tata kelola HRIS Enterprise, gunakan mutasi ke formasi kosong.";
            }

            // Check Employee B's tenure in their current unit
            if ($this->employeeDetails) {
                $this->occupantTenureAnalysis = app(MutasiService::class)->checkTenureCompliance(
                    $occupant,
                    $this->employeeDetails['unit_kerja']
                );
            }
        }

        $this->targetAnalysis = [
            'type' => $analysis['type'],
            'occupant' => $occupant ? [
                'nik' => $occupant->nik,
                'nama' => $occupant->nama,
                'jabatan' => $occupant->jabatan,
                'unit_kerja' => $occupant->unit_kerja,
                'golongan' => $occupant->golongan,
                'rm_level' => $occupant->rm_level ?? app(ManPowerPlanningService::class)->determineRmLevel($occupant),
            ] : null,
        ];

        $targetPos = Position::getAllCached()->get($this->form->targetJabatan);
        $this->form->isTargetDiperban = $targetPos ? (bool) $targetPos->is_diperban : false;
        $this->form->isTargetOverflow = $targetPos ? (bool) $targetPos->is_overflow : false;

        $this->runTenureAnalysis();
        $this->runPromotionAnalysis();
    }

    private function runTenureAnalysis(): void
    {
        if (! $this->form->employeeNik || ! $this->form->targetUnitKerja) {
            $this->tenureAnalysis = null;

            return;
        }

        $emp = Employee::where('nik', $this->form->employeeNik)->first();
        if ($emp) {
            $this->tenureAnalysis = app(MutasiService::class)->checkTenureCompliance($emp, $this->form->targetUnitKerja);
        }
    }

    private function runPromotionAnalysis(): void
    {
        if (! $this->form->employeeNik || ! $this->form->targetJabatan || $this->form->jenisMutasi !== 'PROMOSI') {
            $this->promotionAnalysis = null;

            return;
        }

        $emp = Employee::where('nik', $this->form->employeeNik)->first();
        if ($emp) {
            $this->promotionAnalysis = app(MutasiService::class)->checkPromotionEligibility($emp, $this->form->targetJabatan);
        }
    }
}
